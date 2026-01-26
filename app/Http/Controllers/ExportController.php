<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExportJob;
use App\Models\Clinica;
use App\Models\ExportRequest;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\User;
use App\Services\Export\FieldCatalog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            'fieldCatalog' => [
                'pacientes' => FieldCatalog::pacientesFieldMetadata(),
                'receitas' => FieldCatalog::receitasFieldMetadata(),
                'atendimentos' => FieldCatalog::atendimentosFieldMetadata(),
                'medicos' => FieldCatalog::medicosFieldMetadata(),
                'produtos' => FieldCatalog::produtosFieldMetadata(),
            ],
            'filterOptions' => $this->getFilterOptions(),
            'defaults' => [
                'type' => ExportRequest::TYPE_PACIENTES,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                ExportRequest::TYPE_PACIENTES,
                ExportRequest::TYPE_RECEITAS,
                ExportRequest::TYPE_ATENDIMENTOS,
                ExportRequest::TYPE_MEDICOS,
                ExportRequest::TYPE_PRODUTOS,
            ])],
            'export_all_fields' => ['required', 'boolean'],
            'selected_fields' => ['array'],
            'filters' => ['array'],
        ]);

        // Get available field keys for the selected type
        $availableKeys = $this->getAvailableFieldKeys($validated['type']);

        // Validate selected fields if not exporting all
        if (!$validated['export_all_fields']) {
            $request->validate([
                'selected_fields' => ['required', 'array', 'min:1'],
                'selected_fields.*' => [Rule::in($availableKeys)],
            ]);
        }

        // Prepare filters
        $filters = $this->prepareFilters($validated['type'], $request->input('filters', []));

        // Prepare selected fields
        $selectedFields = $validated['export_all_fields']
            ? null
            : array_values(array_intersect($availableKeys, $request->input('selected_fields', [])));

        $exportRequest = ExportRequest::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'status' => ExportRequest::STATUS_QUEUED,
            'export_all_fields' => $validated['export_all_fields'],
            'selected_fields' => $selectedFields,
            'filters' => $filters,
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
        return match ($type) {
            ExportRequest::TYPE_PACIENTES => 'Pacientes',
            ExportRequest::TYPE_RECEITAS => 'Receitas',
            ExportRequest::TYPE_ATENDIMENTOS => 'Atendimentos Call Center',
            ExportRequest::TYPE_MEDICOS => 'Médicos',
            ExportRequest::TYPE_PRODUTOS => 'Produtos',
            default => ucfirst($type),
        };
    }

    /**
     * Get available field keys for a given export type.
     */
    private function getAvailableFieldKeys(string $type): array
    {
        return match ($type) {
            ExportRequest::TYPE_PACIENTES => collect(FieldCatalog::pacientesFieldMetadata())->pluck('key')->all(),
            ExportRequest::TYPE_RECEITAS => collect(FieldCatalog::receitasFieldMetadata())->pluck('key')->all(),
            ExportRequest::TYPE_ATENDIMENTOS => collect(FieldCatalog::atendimentosFieldMetadata())->pluck('key')->all(),
            ExportRequest::TYPE_MEDICOS => collect(FieldCatalog::medicosFieldMetadata())->pluck('key')->all(),
            ExportRequest::TYPE_PRODUTOS => collect(FieldCatalog::produtosFieldMetadata())->pluck('key')->all(),
            default => [],
        };
    }

    /**
     * Prepare and sanitize filters for export.
     */
    private function prepareFilters(string $type, array $filters): array
    {
        $allowedKeys = $this->getAllowedFilterKeys($type);

        $sanitized = [];

        foreach ($allowedKeys as $key) {
            if (!Arr::has($filters, $key)) {
                continue;
            }

            $value = Arr::get($filters, $key);

            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }

            // Handle date fields
            if (in_array($key, ['created_from', 'created_to', 'data_receita_from', 'data_receita_to', 'data_abertura_from', 'data_abertura_to', 'data_alteracao_from', 'data_alteracao_to'], true)) {
                try {
                    $sanitized[$key] = Carbon::parse($value)->toDateString();
                } catch (\Exception $e) {
                    continue;
                }
                continue;
            }

            // Handle numeric fields
            if (in_array($key, ['medico_id', 'paciente_id', 'usuario_id', 'clinica_id', 'valor_total_min', 'valor_total_max', 'preco_min', 'preco_max'], true)) {
                $sanitized[$key] = (int) $value;
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Get allowed filter keys for a given export type.
     */
    private function getAllowedFilterKeys(string $type): array
    {
        return match ($type) {
            ExportRequest::TYPE_PACIENTES => ['ativo', 'medico_id', 'created_from', 'created_to', 'cidade', 'uf', 'indicado_por'],
            ExportRequest::TYPE_RECEITAS => ['status', 'medico_id', 'paciente_id', 'data_receita_from', 'data_receita_to', 'created_from', 'created_to', 'valor_total_min', 'valor_total_max'],
            ExportRequest::TYPE_ATENDIMENTOS => ['status', 'medico_id', 'paciente_id', 'usuario_id', 'data_abertura_from', 'data_abertura_to', 'data_alteracao_from', 'data_alteracao_to'],
            ExportRequest::TYPE_MEDICOS => ['ativo', 'clinica_id', 'especialidade', 'uf_crm', 'cidade', 'created_from', 'created_to'],
            ExportRequest::TYPE_PRODUTOS => ['ativo', 'categoria', 'local_uso', 'preco_min', 'preco_max', 'tiny_id', 'created_from', 'created_to'],
            default => [],
        };
    }

    /**
     * Get filter options for frontend.
     */
    private function getFilterOptions(): array
    {
        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);
        $pacientes = Paciente::ativo()->orderBy('nome')->get(['id', 'nome']);
        $clinicas = Clinica::ativo()->orderBy('nome')->get(['id', 'nome']);
        $usuarios = User::where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name']);

        // Get unique values for filters
        $categorias = Produto::distinct()->whereNotNull('categoria')->pluck('categoria')->sort()->values();
        $localUso = Produto::distinct()->whereNotNull('local_uso')->pluck('local_uso')->sort()->values();
        $cidades = Paciente::distinct()->whereNotNull('cidade')->pluck('cidade')->sort()->values();
        $ufs = Paciente::distinct()->whereNotNull('uf')->pluck('uf')->sort()->values();
        $especialidades = Medico::distinct()->whereNotNull('especialidade')->pluck('especialidade')->sort()->values();
        $ufsCrm = Medico::distinct()->whereNotNull('uf_crm')->pluck('uf_crm')->sort()->values();

        return [
            'pacientes' => [
                'status' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => '1', 'label' => 'Ativo'],
                    ['value' => '0', 'label' => 'Inativo'],
                ],
                'medicos' => $medicos->map(fn ($m) => ['value' => $m->id, 'label' => $m->nome])->toArray(),
                'cidades' => $cidades->map(fn ($c) => ['value' => $c, 'label' => $c])->toArray(),
                'ufs' => $ufs->map(fn ($u) => ['value' => $u, 'label' => $u])->toArray(),
            ],
            'receitas' => [
                'status' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => 'rascunho', 'label' => 'Rascunho'],
                    ['value' => 'finalizada', 'label' => 'Finalizada'],
                    ['value' => 'cancelada', 'label' => 'Cancelada'],
                ],
                'medicos' => $medicos->map(fn ($m) => ['value' => $m->id, 'label' => $m->nome])->toArray(),
                'pacientes' => $pacientes->map(fn ($p) => ['value' => $p->id, 'label' => $p->nome])->toArray(),
            ],
            'atendimentos' => [
                'status' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => 'entrar_em_contato', 'label' => 'Entrar em Contato'],
                    ['value' => 'aguardando_retorno', 'label' => 'Aguardando Retorno'],
                    ['value' => 'em_producao', 'label' => 'Em Produção'],
                    ['value' => 'finalizado', 'label' => 'Finalizado'],
                    ['value' => 'cancelado', 'label' => 'Cancelado'],
                ],
                'medicos' => $medicos->map(fn ($m) => ['value' => $m->id, 'label' => $m->nome])->toArray(),
                'pacientes' => $pacientes->map(fn ($p) => ['value' => $p->id, 'label' => $p->nome])->toArray(),
                'usuarios' => $usuarios->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->toArray(),
            ],
            'medicos' => [
                'status' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => '1', 'label' => 'Ativo'],
                    ['value' => '0', 'label' => 'Inativo'],
                ],
                'clinicas' => $clinicas->map(fn ($c) => ['value' => $c->id, 'label' => $c->nome])->toArray(),
                'especialidades' => $especialidades->map(fn ($e) => ['value' => $e, 'label' => $e])->toArray(),
                'ufs_crm' => $ufsCrm->map(fn ($u) => ['value' => $u, 'label' => $u])->toArray(),
            ],
            'produtos' => [
                'status' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => '1', 'label' => 'Ativo'],
                    ['value' => '0', 'label' => 'Inativo'],
                ],
                'categorias' => $categorias->map(fn ($c) => ['value' => $c, 'label' => $c])->toArray(),
                'local_uso' => $localUso->map(fn ($l) => ['value' => $l, 'label' => $l])->toArray(),
            ],
        ];
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

