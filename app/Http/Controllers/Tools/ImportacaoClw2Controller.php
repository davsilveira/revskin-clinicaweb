<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Migration\LegadoIncrementalImporter;
use App\Services\Migration\LegadoMedicoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportacaoClw2Controller extends Controller
{
    public function index(Request $request, LegadoIncrementalImporter $importer, LegadoMedicoResolver $resolver): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acesso restrito a administradores.');

        $dumps = $importer->listSqlDumps();
        $medicos = $resolver->listActiveMedicos()->map(function ($m) {
            $nome = trim((string) ($m->nome_legado ?: $m->apelido ?: ''));
            if ($nome === '') {
                $nome = trim((string) ($m->linkedUser?->name ?? ''));
            }
            if ($nome === '') {
                $nome = 'Médico #'.$m->id;
            }

            return [
                'id' => $m->id,
                'nome' => $nome,
                'crm' => $m->crm,
                'uf_crm' => $m->uf_crm,
                'cpf' => $m->cpf,
                'email1' => $m->email1,
            ];
        })->values();

        $latestReport = null;
        foreach ($dumps as $dump) {
            $hash = @md5_file($dump['path']);
            if (! $hash) {
                continue;
            }
            $path = storage_path('app/imports/'.$hash.'/report-latest.json');
            if (is_file($path)) {
                $full = json_decode((string) file_get_contents($path), true);
                if (is_array($full)) {
                    $latestReport = $this->slimReportForUi($full, $hash);
                }
                break;
            }
        }

        $pilotNames = [
            'Bhertha Miyuki Tamura',
            'Sullege Suzuki',
            'Maria Figueiredo Almeida',
        ];
        $pilotHint = $medicos->filter(function ($m) use ($pilotNames) {
            $nome = (string) ($m['nome'] ?? '');
            foreach ($pilotNames as $p) {
                if (strcasecmp($nome, $p) === 0 || str_contains(mb_strtolower($nome), mb_strtolower(explode(' ', $p)[0]))) {
                    if (str_contains(mb_strtolower($nome), 'bhertha')
                        || str_contains(mb_strtolower($nome), 'sullege')
                        || str_contains(mb_strtolower($nome), 'figueiredo')) {
                        return true;
                    }
                }
            }

            return false;
        })->map(fn ($m) => [
            'nome' => $m['nome'],
            'cpf' => $m['cpf'],
            'clw3_id' => $m['id'],
        ])->values()->all();

        return Inertia::render('Tools/ImportacaoClw2', [
            'dumps' => $dumps,
            'medicos' => $medicos,
            'sqlDirectory' => $importer->sqlDirectory(),
            'latestReport' => $latestReport,
            'pilotHint' => $pilotHint,
        ]);
    }

    public function preview(Request $request, LegadoIncrementalImporter $importer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'sql_name' => 'required|string',
            'medico_ids' => 'required|array|min:1',
            'medico_ids.*' => 'integer|exists:medicos,id',
        ]);

        $sqlPath = $this->resolveSql($importer, $validated['sql_name']);

        try {
            $preview = $importer->previewMapping($sqlPath, $validated['medico_ids']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with([
            'success' => 'Mapeamento gerado.',
            'import_preview' => $preview,
        ]);
    }

    public function dryRun(Request $request, LegadoIncrementalImporter $importer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'sql_name' => 'required|string',
            'medico_ids' => 'required|array|min:1',
            'medico_ids.*' => 'integer|exists:medicos,id',
        ]);

        $sqlPath = $this->resolveSql($importer, $validated['sql_name']);

        try {
            @set_time_limit(300);
            $report = $importer->run($sqlPath, $validated['medico_ids'], true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with([
            'success' => 'Dry-run concluído. Nada foi persistido.',
            'import_report' => $this->slimReportForUi($report),
        ]);
    }

    public function apply(Request $request, LegadoIncrementalImporter $importer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'sql_name' => 'required|string',
            'medico_ids' => 'required|array|min:1',
            'medico_ids.*' => 'integer|exists:medicos,id',
            'confirm' => 'accepted',
        ]);

        $sqlPath = $this->resolveSql($importer, $validated['sql_name']);

        try {
            @set_time_limit(300);
            $report = $importer->run($sqlPath, $validated['medico_ids'], false);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with([
            'success' => 'Importação aplicada.',
            'import_report' => $this->slimReportForUi($report),
        ]);
    }

    public function reportChanges(Request $request, string $hash): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $full = $this->loadReport($hash);
        $pacientes = $full['changes']['pacientes'] ?? [];
        $receitas = $full['changes']['receitas'] ?? [];

        return response()->json([
            'report_hash' => $hash,
            'generated_at' => $full['generated_at'] ?? null,
            'dry_run' => $full['dry_run'] ?? null,
            'pacientes' => array_map(fn ($c) => $this->summarizeChange($c), is_array($pacientes) ? $pacientes : []),
            'receitas' => array_map(fn ($c) => $this->summarizeChange($c), is_array($receitas) ? $receitas : []),
        ]);
    }

    public function reportChange(Request $request, string $hash, string $id): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $full = $this->loadReport($hash);
        $pacientes = $full['changes']['pacientes'] ?? [];
        $receitas = $full['changes']['receitas'] ?? [];
        $all = array_merge(
            is_array($pacientes) ? $pacientes : [],
            is_array($receitas) ? $receitas : []
        );

        foreach ($all as $change) {
            if (($change['id'] ?? null) === $id) {
                return response()->json($change);
            }
        }

        abort(404, 'Alteração não encontrada neste report.');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(string $hash): array
    {
        $hash = preg_replace('/[^a-f0-9]/', '', strtolower($hash)) ?? '';
        abort_if(strlen($hash) < 16, 404);

        $path = storage_path('app/imports/'.$hash.'/report-latest.json');
        abort_unless(is_file($path), 404, 'Report não encontrado.');

        $full = json_decode((string) file_get_contents($path), true);
        abort_unless(is_array($full), 500, 'Report inválido.');

        return $full;
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function summarizeChange(array $change): array
    {
        $diff = $change['diff'] ?? [];

        return [
            'id' => $change['id'] ?? null,
            'entity' => $change['entity'] ?? null,
            'action' => $change['action'] ?? null,
            'action_label' => $change['action_label'] ?? null,
            'legado_id' => $change['legado_id'] ?? null,
            'clw3_id' => $change['clw3_id'] ?? null,
            'label' => $change['label'] ?? null,
            'subtitle' => $change['subtitle'] ?? null,
            'warning' => $change['warning'] ?? null,
            'diff_count' => is_array($diff) ? count($diff) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function slimReportForUi(array $full, ?string $fallbackHash = null): array
    {
        $signals = $full['signals'] ?? [];
        $pacientes = $full['changes']['pacientes'] ?? [];
        $receitas = $full['changes']['receitas'] ?? [];
        $hash = $full['report_hash']
            ?? $fallbackHash
            ?? (isset($full['work_dir']) ? basename((string) $full['work_dir']) : null);

        return [
            'dry_run' => $full['dry_run'] ?? null,
            'sql' => $full['sql'] ?? null,
            'work_dir' => $full['work_dir'] ?? null,
            'generated_at' => $full['generated_at'] ?? null,
            'mappings' => $full['mappings'] ?? [],
            'stats' => $full['stats'] ?? [],
            'signals_count' => is_array($signals) ? count($signals) : 0,
            'pacientes_filtrados' => $full['pacientes_filtrados'] ?? null,
            'receitas_filtradas' => $full['receitas_filtradas'] ?? null,
            'report_hash' => $hash,
            'changes_summary' => [
                'pacientes' => is_array($pacientes) ? count($pacientes) : 0,
                'receitas' => is_array($receitas) ? count($receitas) : 0,
                'pacientes_by_action' => $this->countByAction(is_array($pacientes) ? $pacientes : []),
                'receitas_by_action' => $this->countByAction(is_array($receitas) ? $receitas : []),
            ],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array<string, int>
     */
    private function countByAction(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $action = (string) ($item['action'] ?? 'outro');
            $out[$action] = ($out[$action] ?? 0) + 1;
        }

        return $out;
    }

    private function resolveSql(LegadoIncrementalImporter $importer, string $name): string
    {
        $name = basename($name);
        foreach ($importer->listSqlDumps() as $dump) {
            if ($dump['name'] === $name) {
                return $dump['path'];
            }
        }

        throw new \InvalidArgumentException("Dump SQL não encontrado: {$name}");
    }
}
