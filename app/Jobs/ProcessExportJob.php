<?php

namespace App\Jobs;

use App\Mail\ExportReadyMail;
use App\Models\AtendimentoCallcenter;
use App\Models\ExportRequest;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Services\Export\FieldCatalog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public ExportRequest $exportRequest
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $exportRequest = $this->exportRequest->fresh();

        if (!$exportRequest) {
            return;
        }

        if ($exportRequest->status === ExportRequest::STATUS_COMPLETED) {
            return;
        }

        $exportRequest->markAsProcessing();

        try {
            $result = match ($exportRequest->type) {
                ExportRequest::TYPE_PACIENTES => $this->exportPacientes($exportRequest),
                ExportRequest::TYPE_RECEITAS => $this->exportReceitas($exportRequest),
                ExportRequest::TYPE_ATENDIMENTOS => $this->exportAtendimentos($exportRequest),
                ExportRequest::TYPE_MEDICOS => $this->exportMedicos($exportRequest),
                ExportRequest::TYPE_PRODUTOS => $this->exportProdutos($exportRequest),
                default => throw new \RuntimeException("Tipo de exportação não suportado: {$exportRequest->type}"),
            };

            $exportRequest->markAsCompleted(
                $result['file_path'],
                $result['file_name'],
                $result['total_records']
            );

            $this->sendNotificationEmail();

            Log::info("Export completed successfully", [
                'export_request_id' => $exportRequest->id,
                'type' => $exportRequest->type,
                'file_name' => $result['file_name'],
                'total_records' => $result['total_records'],
            ]);

        } catch (Throwable $exception) {
            Log::error("Export failed", [
                'export_request_id' => $exportRequest->id,
                'type' => $exportRequest->type,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $exportRequest->markAsFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * Generate the CSV for pacientes.
     *
     * @return array{file_name: string, file_path: string, total_records: int}
     */
    private function exportPacientes(ExportRequest $exportRequest): array
    {
        $metadata = collect(FieldCatalog::pacientesFieldMetadata());
        $availableKeys = $metadata->pluck('key')->all();
        $selectedKeys = $this->resolveSelectedKeys($exportRequest, $availableKeys);
        $headers = $metadata
            ->filter(fn ($field) => in_array($field['key'], $selectedKeys, true))
            ->pluck('label')
            ->all();

        $disk = Storage::disk('local');
        if (!$disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('pacientes_%s_%s.csv', now()->format('Ymd_His'), $exportRequest->id);
        $filePath = 'exports/' . $fileName;
        $fullPath = $disk->path($filePath);

        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível criar o arquivo de exportação.');
        }

        // Include UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        $totalRecords = 0;

        FieldCatalog::setExportContext($exportRequest);

        try {
            $query = Paciente::query()
                ->with(['medico:id,nome,crm', 'receitas:id,paciente_id,data_receita'])
                ->orderBy('id');

            $this->applyPacienteFilters($query, $exportRequest->filters ?? []);

            $query->chunkById(500, function ($pacientes) use ($handle, $selectedKeys, &$totalRecords) {
                foreach ($pacientes as $paciente) {
                    $row = [];
                    foreach ($selectedKeys as $key) {
                        $row[] = FieldCatalog::resolvePacienteField($key, $paciente);
                    }
                    fputcsv($handle, $row, ';');
                    $totalRecords++;
                }
            });
        } finally {
            FieldCatalog::clearExportContext();

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Generate the CSV for receitas.
     *
     * @return array{file_name: string, file_path: string, total_records: int}
     */
    private function exportReceitas(ExportRequest $exportRequest): array
    {
        $metadata = collect(FieldCatalog::receitasFieldMetadata());
        $availableKeys = $metadata->pluck('key')->all();
        $selectedKeys = $this->resolveSelectedKeys($exportRequest, $availableKeys);
        $headers = $metadata
            ->filter(fn ($field) => in_array($field['key'], $selectedKeys, true))
            ->pluck('label')
            ->all();

        $disk = Storage::disk('local');
        if (!$disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('receitas_%s_%s.csv', now()->format('Ymd_His'), $exportRequest->id);
        $filePath = 'exports/' . $fileName;
        $fullPath = $disk->path($filePath);

        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível criar o arquivo de exportação.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        $totalRecords = 0;

        FieldCatalog::setExportContext($exportRequest);

        try {
            $query = Receita::query()
                ->with([
                    'paciente:id,nome,cpf,codigo',
                    'medico:id,nome,crm,especialidade',
                    'itens.produto:id,nome',
                ])
                ->orderBy('id');

            $this->applyReceitaFilters($query, $exportRequest->filters ?? []);

            $query->chunkById(500, function ($receitas) use ($handle, $selectedKeys, &$totalRecords) {
                foreach ($receitas as $receita) {
                    $row = [];
                    foreach ($selectedKeys as $key) {
                        $row[] = FieldCatalog::resolveReceitaField($key, $receita);
                    }
                    fputcsv($handle, $row, ';');
                    $totalRecords++;
                }
            });
        } finally {
            FieldCatalog::clearExportContext();

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Generate the CSV for atendimentos.
     *
     * @return array{file_name: string, file_path: string, total_records: int}
     */
    private function exportAtendimentos(ExportRequest $exportRequest): array
    {
        $metadata = collect(FieldCatalog::atendimentosFieldMetadata());
        $availableKeys = $metadata->pluck('key')->all();
        $selectedKeys = $this->resolveSelectedKeys($exportRequest, $availableKeys);
        $headers = $metadata
            ->filter(fn ($field) => in_array($field['key'], $selectedKeys, true))
            ->pluck('label')
            ->all();

        $disk = Storage::disk('local');
        if (!$disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('atendimentos_%s_%s.csv', now()->format('Ymd_His'), $exportRequest->id);
        $filePath = 'exports/' . $fileName;
        $fullPath = $disk->path($filePath);

        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível criar o arquivo de exportação.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        $totalRecords = 0;

        FieldCatalog::setExportContext($exportRequest);

        try {
            $query = AtendimentoCallcenter::query()
                ->with([
                    'receita:id,numero,data_receita,valor_total',
                    'paciente:id,nome,cpf,telefone1,telefone2,telefone3',
                    'medico:id,nome,crm',
                    'usuario:id,name',
                    'usuarioAlteracao:id,name',
                    'acompanhamentos:id,atendimento_id,data_registro',
                ])
                ->orderBy('id');

            $this->applyAtendimentoFilters($query, $exportRequest->filters ?? []);

            $query->chunkById(500, function ($atendimentos) use ($handle, $selectedKeys, &$totalRecords) {
                foreach ($atendimentos as $atendimento) {
                    $row = [];
                    foreach ($selectedKeys as $key) {
                        $row[] = FieldCatalog::resolveAtendimentoField($key, $atendimento);
                    }
                    fputcsv($handle, $row, ';');
                    $totalRecords++;
                }
            });
        } finally {
            FieldCatalog::clearExportContext();

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Generate the CSV for medicos.
     *
     * @return array{file_name: string, file_path: string, total_records: int}
     */
    private function exportMedicos(ExportRequest $exportRequest): array
    {
        $metadata = collect(FieldCatalog::medicosFieldMetadata());
        $availableKeys = $metadata->pluck('key')->all();
        $selectedKeys = $this->resolveSelectedKeys($exportRequest, $availableKeys);
        $headers = $metadata
            ->filter(fn ($field) => in_array($field['key'], $selectedKeys, true))
            ->pluck('label')
            ->all();

        $disk = Storage::disk('local');
        if (!$disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('medicos_%s_%s.csv', now()->format('Ymd_His'), $exportRequest->id);
        $filePath = 'exports/' . $fileName;
        $fullPath = $disk->path($filePath);

        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível criar o arquivo de exportação.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        $totalRecords = 0;

        FieldCatalog::setExportContext($exportRequest);

        try {
            $query = Medico::query()
                ->with([
                    'clinica:id,nome,cnpj',
                    'clinicas:id,nome',
                    'pacientes:id,medico_id',
                    'receitas:id,medico_id,valor_total',
                ])
                ->orderBy('id');

            $this->applyMedicoFilters($query, $exportRequest->filters ?? []);

            $query->chunkById(500, function ($medicos) use ($handle, $selectedKeys, &$totalRecords) {
                foreach ($medicos as $medico) {
                    $row = [];
                    foreach ($selectedKeys as $key) {
                        $row[] = FieldCatalog::resolveMedicoField($key, $medico);
                    }
                    fputcsv($handle, $row, ';');
                    $totalRecords++;
                }
            });
        } finally {
            FieldCatalog::clearExportContext();

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Generate the CSV for produtos.
     *
     * @return array{file_name: string, file_path: string, total_records: int}
     */
    private function exportProdutos(ExportRequest $exportRequest): array
    {
        $metadata = collect(FieldCatalog::produtosFieldMetadata());
        $availableKeys = $metadata->pluck('key')->all();
        $selectedKeys = $this->resolveSelectedKeys($exportRequest, $availableKeys);
        $headers = $metadata
            ->filter(fn ($field) => in_array($field['key'], $selectedKeys, true))
            ->pluck('label')
            ->all();

        $disk = Storage::disk('local');
        if (!$disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('produtos_%s_%s.csv', now()->format('Ymd_His'), $exportRequest->id);
        $filePath = 'exports/' . $fileName;
        $fullPath = $disk->path($filePath);

        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível criar o arquivo de exportação.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        $totalRecords = 0;

        FieldCatalog::setExportContext($exportRequest);

        try {
            $query = Produto::query()
                ->with(['receitaItens:id,produto_id,quantidade,valor_total,created_at'])
                ->orderBy('id');

            $this->applyProdutoFilters($query, $exportRequest->filters ?? []);

            $query->chunkById(500, function ($produtos) use ($handle, $selectedKeys, &$totalRecords) {
                foreach ($produtos as $produto) {
                    $row = [];
                    foreach ($selectedKeys as $key) {
                        $row[] = FieldCatalog::resolveProdutoField($key, $produto);
                    }
                    fputcsv($handle, $row, ';');
                    $totalRecords++;
                }
            });
        } finally {
            FieldCatalog::clearExportContext();

            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Determine which fields should be exported.
     */
    private function resolveSelectedKeys(ExportRequest $exportRequest, array $availableKeys): array
    {
        if ($exportRequest->export_all_fields) {
            return $availableKeys;
        }

        $selected = array_values(array_intersect($availableKeys, $exportRequest->selected_fields ?? []));

        return !empty($selected) ? $selected : $availableKeys;
    }

    /**
     * Apply filters to paciente query.
     */
    private function applyPacienteFilters($query, array $filters): void
    {
        if (isset($filters['ativo']) && $filters['ativo'] !== 'all' && $filters['ativo'] !== '') {
            $query->where('ativo', $filters['ativo'] === '1' || $filters['ativo'] === 1 || $filters['ativo'] === true);
        }

        if (isset($filters['medico_id'])) {
            $query->where('medico_id', $filters['medico_id']);
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
        }

        if (isset($filters['cidade'])) {
            $query->where('cidade', $filters['cidade']);
        }

        if (isset($filters['uf'])) {
            $query->where('uf', $filters['uf']);
        }

        if (isset($filters['indicado_por']) && $filters['indicado_por'] !== '') {
            $query->where('indicado_por', 'like', '%' . $filters['indicado_por'] . '%');
        }
    }

    /**
     * Apply filters to receita query.
     */
    private function applyReceitaFilters($query, array $filters): void
    {
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['medico_id'])) {
            $query->where('medico_id', $filters['medico_id']);
        }

        if (isset($filters['paciente_id'])) {
            $query->where('paciente_id', $filters['paciente_id']);
        }

        if (isset($filters['data_receita_from'])) {
            $query->whereDate('data_receita', '>=', Carbon::parse($filters['data_receita_from'])->startOfDay());
        }

        if (isset($filters['data_receita_to'])) {
            $query->whereDate('data_receita', '<=', Carbon::parse($filters['data_receita_to'])->endOfDay());
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
        }

        if (isset($filters['valor_total_min'])) {
            $query->where('valor_total', '>=', $filters['valor_total_min']);
        }

        if (isset($filters['valor_total_max'])) {
            $query->where('valor_total', '<=', $filters['valor_total_max']);
        }
    }

    /**
     * Apply filters to atendimento query.
     */
    private function applyAtendimentoFilters($query, array $filters): void
    {
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['medico_id'])) {
            $query->where('medico_id', $filters['medico_id']);
        }

        if (isset($filters['paciente_id'])) {
            $query->where('paciente_id', $filters['paciente_id']);
        }

        if (isset($filters['usuario_id'])) {
            $query->where('usuario_id', $filters['usuario_id']);
        }

        if (isset($filters['data_abertura_from'])) {
            $query->whereDate('data_abertura', '>=', Carbon::parse($filters['data_abertura_from'])->startOfDay());
        }

        if (isset($filters['data_abertura_to'])) {
            $query->whereDate('data_abertura', '<=', Carbon::parse($filters['data_abertura_to'])->endOfDay());
        }

        if (isset($filters['data_alteracao_from'])) {
            $query->whereDate('data_alteracao', '>=', Carbon::parse($filters['data_alteracao_from'])->startOfDay());
        }

        if (isset($filters['data_alteracao_to'])) {
            $query->whereDate('data_alteracao', '<=', Carbon::parse($filters['data_alteracao_to'])->endOfDay());
        }
    }

    /**
     * Apply filters to medico query.
     */
    private function applyMedicoFilters($query, array $filters): void
    {
        if (isset($filters['ativo']) && $filters['ativo'] !== 'all' && $filters['ativo'] !== '') {
            $query->where('ativo', $filters['ativo'] === '1' || $filters['ativo'] === 1 || $filters['ativo'] === true);
        }

        if (isset($filters['clinica_id'])) {
            $query->where('clinica_id', $filters['clinica_id']);
        }

        if (isset($filters['especialidade']) && $filters['especialidade'] !== '') {
            $query->where('especialidade', 'like', '%' . $filters['especialidade'] . '%');
        }

        if (isset($filters['uf_crm'])) {
            $query->where('uf_crm', $filters['uf_crm']);
        }

        if (isset($filters['cidade']) && $filters['cidade'] !== '') {
            $query->where('cidade', 'like', '%' . $filters['cidade'] . '%');
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
        }
    }

    /**
     * Apply filters to produto query.
     */
    private function applyProdutoFilters($query, array $filters): void
    {
        if (isset($filters['ativo']) && $filters['ativo'] !== 'all' && $filters['ativo'] !== '') {
            $query->where('ativo', $filters['ativo'] === '1' || $filters['ativo'] === 1 || $filters['ativo'] === true);
        }

        if (isset($filters['categoria']) && $filters['categoria'] !== '') {
            $query->where('categoria', $filters['categoria']);
        }

        if (isset($filters['local_uso']) && $filters['local_uso'] !== '') {
            $query->where('local_uso', $filters['local_uso']);
        }

        if (isset($filters['preco_min'])) {
            $query->where('preco', '>=', $filters['preco_min']);
        }

        if (isset($filters['preco_max'])) {
            $query->where('preco', '<=', $filters['preco_max']);
        }

        if (isset($filters['tiny_id']) && $filters['tiny_id'] === '1') {
            $query->whereNotNull('tiny_id');
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
        }
    }

    /**
     * Send notification email.
     */
    protected function sendNotificationEmail(): void
    {
        $user = $this->exportRequest->user;

        if ($user && $user->email) {
            Mail::to($user->email)->send(new ExportReadyMail($this->exportRequest));
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->exportRequest->markAsFailed($exception->getMessage());

        Log::error("Export job failed permanently", [
            'export_request_id' => $this->exportRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
