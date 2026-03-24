<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRelatorioExportJob;
use App\Models\RelatorioExportRequest;
use App\Services\RelatorioExcelExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelatorioExportController extends Controller
{
    public function storeAquisicao(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isMedico()) {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'medico_ids' => ['nullable', 'array'],
            'medico_ids.*' => ['exists:medicos,id'],
            'paciente_ids' => ['nullable', 'array'],
            'paciente_ids.*' => ['exists:pacientes,id'],
            'produto_ids' => ['nullable', 'array'],
            'produto_ids.*' => ['exists:produtos,id'],
            'extra_emails' => ['nullable', 'array'],
            'extra_emails.*' => ['email'],
        ]);

        $filters = [
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $validated['data_fim'],
            'medico_ids' => $validated['medico_ids'] ?? [],
            'paciente_ids' => $validated['paciente_ids'] ?? [],
            'produto_ids' => $validated['produto_ids'] ?? [],
        ];

        $exportRequest = RelatorioExportRequest::create([
            'user_id' => $user->id,
            'type' => RelatorioExportRequest::TYPE_AQUISICAO_PRODUTOS,
            'format' => $validated['format'],
            'filters' => $filters,
            'status' => RelatorioExportRequest::STATUS_QUEUED,
            'extra_emails' => $this->sanitizeExtraEmails($validated['extra_emails'] ?? []),
        ]);

        ProcessRelatorioExportJob::dispatch($exportRequest);

        return redirect()->back()
            ->with('success', 'Pedido registrado. Você receberá um e-mail com o link para download quando o arquivo estiver pronto.');
    }

    public function storeReceitasMedico(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'medico_id' => ['nullable', 'exists:medicos,id'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'extra_emails' => ['nullable', 'array'],
            'extra_emails.*' => ['email'],
        ]);

        $filters = [
            'medico_id' => $validated['medico_id'] ?? null,
            'data_inicio' => $validated['data_inicio'] ?? null,
            'data_fim' => $validated['data_fim'] ?? null,
        ];

        $exportRequest = RelatorioExportRequest::create([
            'user_id' => $user->id,
            'type' => RelatorioExportRequest::TYPE_RECEITAS_MEDICO,
            'format' => $validated['format'],
            'filters' => $filters,
            'status' => RelatorioExportRequest::STATUS_QUEUED,
            'extra_emails' => $this->sanitizeExtraEmails($validated['extra_emails'] ?? []),
        ]);

        ProcessRelatorioExportJob::dispatch($exportRequest);

        return redirect()->back()
            ->with('success', 'Pedido registrado. Você receberá um e-mail com o link para download quando o arquivo estiver pronto.');
    }

    public function downloadExcelAquisicao(Request $request, RelatorioExcelExportService $excelService): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isMedico()) {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'medico_ids' => ['nullable', 'array'],
            'medico_ids.*' => ['exists:medicos,id'],
            'paciente_ids' => ['nullable', 'array'],
            'paciente_ids.*' => ['exists:pacientes,id'],
            'produto_ids' => ['nullable', 'array'],
            'produto_ids.*' => ['exists:produtos,id'],
        ]);

        $filters = [
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $validated['data_fim'],
            'medico_ids' => $validated['medico_ids'] ?? [],
            'paciente_ids' => $validated['paciente_ids'] ?? [],
            'produto_ids' => $validated['produto_ids'] ?? [],
        ];

        return $excelService->downloadAquisicaoProdutosExcel($user, $filters);
    }

    public function downloadExcelReceitasMedico(Request $request, RelatorioExcelExportService $excelService): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'medico_id' => ['nullable', 'exists:medicos,id'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $filters = [
            'medico_id' => $validated['medico_id'] ?? null,
            'data_inicio' => $validated['data_inicio'] ?? null,
            'data_fim' => $validated['data_fim'] ?? null,
        ];

        return $excelService->downloadReceitasMedicoExcel($filters);
    }

    public function download(Request $request, RelatorioExportRequest $relatorioExportRequest)
    {
        if ($request->user()->id !== $relatorioExportRequest->user_id) {
            abort(403, 'Acesso não autorizado.');
        }

        if (! $relatorioExportRequest->isCompleted() || ! $relatorioExportRequest->file_path) {
            $route = $relatorioExportRequest->type === RelatorioExportRequest::TYPE_AQUISICAO_PRODUTOS
                ? 'relatorios.aquisicao-produtos'
                : 'relatorios.receitas-medico';

            return redirect()->route($route)->with('error', 'Exportação ainda não disponível para download.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($relatorioExportRequest->file_path)) {
            $route = $relatorioExportRequest->type === RelatorioExportRequest::TYPE_AQUISICAO_PRODUTOS
                ? 'relatorios.aquisicao-produtos'
                : 'relatorios.receitas-medico';

            return redirect()->route($route)->with('error', 'Arquivo não encontrado. Refaça a exportação.');
        }

        return $disk->download(
            $relatorioExportRequest->file_path,
            $relatorioExportRequest->file_name
        );
    }

    private function sanitizeExtraEmails(array $emails): array
    {
        $valid = array_filter($emails, fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL));

        return array_values(array_unique($valid));
    }
}
