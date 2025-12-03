<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExportJob;
use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request->user());

        $historyQuery = ExportRequest::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('history_status') && $request->history_status !== 'all') {
            $historyQuery->where('status', $request->history_status);
        }

        $history = $historyQuery
            ->paginate(10)
            ->withQueryString()
            ->through(function (ExportRequest $exportRequest) {
                return [
                    'id' => $exportRequest->id,
                    'type' => $exportRequest->type,
                    'type_label' => $this->typeLabel($exportRequest->type),
                    'status' => $exportRequest->status,
                    'status_label' => $this->statusLabel($exportRequest->status),
                    'status_badge' => $this->statusBadgeClass($exportRequest->status),
                    'export_all_fields' => $exportRequest->export_all_fields,
                    'selected_fields' => $exportRequest->selected_fields ?? [],
                    'filters' => $exportRequest->filters ?? [],
                    'total_records' => $exportRequest->total_records,
                    'file_name' => $exportRequest->file_name,
                    'download_url' => $exportRequest->isCompleted() && $exportRequest->file_path
                        ? route('exports.download', $exportRequest)
                        : null,
                    'created_at' => optional($exportRequest->created_at)?->format('d/m/Y H:i'),
                    'completed_at' => optional($exportRequest->completed_at)?->format('d/m/Y H:i'),
                    'requested_by' => $exportRequest->user ? [
                        'id' => $exportRequest->user->id,
                        'name' => $exportRequest->user->name,
                        'email' => $exportRequest->user->email,
                    ] : null,
                    'error_message' => $exportRequest->error_message,
                ];
            });

        return Inertia::render('Exports/Index', [
            'history' => $history,
            'historyFilters' => [
                'status' => $request->get('history_status', 'all'),
            ],
            'historyStatusOptions' => $this->historyStatusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'export_all_fields' => ['required', 'boolean'],
            'selected_fields' => ['array'],
            'filters' => ['array'],
        ]);

        $exportRequest = ExportRequest::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'status' => ExportRequest::STATUS_QUEUED,
            'export_all_fields' => $validated['export_all_fields'],
            'selected_fields' => $request->input('selected_fields', []),
            'filters' => $request->input('filters', []),
            'base_url' => $request->getSchemeAndHttpHost(),
        ]);

        ProcessExportJob::dispatch($exportRequest);

        return redirect()
            ->route('exports.index')
            ->with('success', 'Exportação agendada! Você será notificado por e-mail quando o arquivo estiver disponível.');
    }

    public function download(Request $request, ExportRequest $exportRequest)
    {
        $this->ensureAdmin($request->user());

        if (!$exportRequest->isCompleted() || !$exportRequest->file_path) {
            return redirect()
                ->route('exports.index')
                ->withErrors(['error' => 'Exportação ainda não disponível para download.']);
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($exportRequest->file_path)) {
            return redirect()
                ->route('exports.index')
                ->withErrors(['error' => 'Arquivo não encontrado. Refaça a exportação.']);
        }

        return $disk->download($exportRequest->file_path, $exportRequest->file_name);
    }

    public function clearHistory(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $disk = Storage::disk('local');

        ExportRequest::chunkById(200, function ($requests) use ($disk) {
            foreach ($requests as $exportRequest) {
                if ($exportRequest->file_path && $disk->exists($exportRequest->file_path)) {
                    $disk->delete($exportRequest->file_path);
                }
            }
        });

        ExportRequest::query()->delete();

        return redirect()
            ->route('exports.index')
            ->with('success', 'Histórico de exportações limpo com sucesso.');
    }

    private function ensureAdmin(?User $user): void
    {
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Acesso não autorizado.');
        }
    }

    private function typeLabel(string $type): string
    {
        return ucfirst($type);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_QUEUED => 'Na fila',
            ExportRequest::STATUS_PROCESSING => 'Processando',
            ExportRequest::STATUS_COMPLETED => 'Concluído',
            ExportRequest::STATUS_FAILED => 'Falhou',
            default => ucfirst($status),
        };
    }

    private function statusBadgeClass(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_COMPLETED => 'bg-green-100 text-green-800',
            ExportRequest::STATUS_FAILED => 'bg-red-100 text-red-800',
            ExportRequest::STATUS_PROCESSING => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    private function historyStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todos'],
            ['value' => ExportRequest::STATUS_QUEUED, 'label' => 'Na fila'],
            ['value' => ExportRequest::STATUS_PROCESSING, 'label' => 'Processando'],
            ['value' => ExportRequest::STATUS_COMPLETED, 'label' => 'Concluído'],
            ['value' => ExportRequest::STATUS_FAILED, 'label' => 'Falhou'],
        ];
    }
}

