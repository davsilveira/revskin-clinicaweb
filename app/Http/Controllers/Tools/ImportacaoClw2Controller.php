<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Migration\LegadoIncrementalImporter;
use App\Services\Migration\LegadoMedicoResolver;
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
                    // Não embutir signals completos no HTML data-page (estoura o atributo).
                    $latestReport = $this->slimReportForUi($full);
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

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function slimReportForUi(array $full): array
    {
        $signals = $full['signals'] ?? [];

        return [
            'dry_run' => $full['dry_run'] ?? null,
            'sql' => $full['sql'] ?? null,
            'work_dir' => $full['work_dir'] ?? null,
            'generated_at' => $full['generated_at'] ?? null,
            'mappings' => $full['mappings'] ?? [],
            'stats' => $full['stats'] ?? [],
            'signals_count' => is_array($signals) ? count($signals) : 0,
            'signals' => array_slice(is_array($signals) ? $signals : [], 0, 40),
            'pacientes_filtrados' => $full['pacientes_filtrados'] ?? null,
            'receitas_filtradas' => $full['receitas_filtradas'] ?? null,
        ];
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
