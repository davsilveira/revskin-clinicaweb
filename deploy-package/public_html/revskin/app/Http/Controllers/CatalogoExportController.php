<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCatalogoExportJob;
use App\Models\CatalogoExportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CatalogoExportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:255'],
            'extra_emails' => ['nullable', 'array'],
            'extra_emails.*' => ['email'],
        ]);

        $search = $this->normalizeSearch($validated['search'] ?? null);
        $extraEmails = $this->normalizeExtraEmails($validated['extra_emails'] ?? []);

        $exportRequest = CatalogoExportRequest::create([
            'user_id' => $request->user()->id,
            'format' => $validated['format'],
            'search' => $search,
            'extra_emails' => $extraEmails,
            'status' => CatalogoExportRequest::STATUS_QUEUED,
        ]);

        ProcessCatalogoExportJob::dispatch($exportRequest);

        return redirect()
            ->route('produtos.catalogo')
            ->with('success', 'A exportação será processada em segundo plano. Você receberá um e-mail quando estiver pronto para download.');
    }

    public function download(Request $request, CatalogoExportRequest $catalogoExportRequest)
    {
        if ($request->user()->id !== $catalogoExportRequest->user_id) {
            abort(403, 'Acesso não autorizado.');
        }

        if (! $catalogoExportRequest->isCompleted() || ! $catalogoExportRequest->file_path) {
            return redirect()
                ->route('produtos.catalogo')
                ->with('error', 'Exportação ainda não disponível para download.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($catalogoExportRequest->file_path)) {
            return redirect()
                ->route('produtos.catalogo')
                ->with('error', 'Arquivo não encontrado. Refaça a exportação.');
        }

        return $disk->download(
            $catalogoExportRequest->file_path,
            $catalogoExportRequest->file_name
        );
    }

    private function normalizeSearch(?string $search): ?string
    {
        if (in_array($search, ['undefined', 'null', ''], true) || $search === null) {
            return null;
        }

        return $search;
    }

    private function normalizeExtraEmails(array $emails): array
    {
        $normalized = array_map(fn ($e) => strtolower(trim($e)), array_filter($emails));
        return array_values(array_unique(array_filter($normalized, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }
}
