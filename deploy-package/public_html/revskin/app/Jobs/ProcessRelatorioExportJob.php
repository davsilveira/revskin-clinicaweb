<?php

namespace App\Jobs;

use App\Http\Controllers\RelatorioController;
use App\Mail\RelatorioExportReadyMail;
use App\Models\RelatorioExportRequest;
use App\Services\RelatorioExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessRelatorioExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public RelatorioExportRequest $relatorioExportRequest
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $request = $this->relatorioExportRequest->fresh();

        if (! $request) {
            return;
        }

        if ($request->status === RelatorioExportRequest::STATUS_COMPLETED) {
            return;
        }

        $request->markAsProcessing();

        try {
            if ($request->type === RelatorioExportRequest::TYPE_AQUISICAO_PRODUTOS) {
                $result = $this->exportAquisicaoProdutos($request);
            } else {
                $result = $this->exportReceitasMedico($request);
            }

            $request->markAsCompleted(
                $result['file_path'],
                $result['file_name'],
                $result['total_records']
            );

            $recipients = array_unique(array_filter([
                $request->user->email,
                ...($request->extra_emails ?? []),
            ]));
            foreach ($recipients as $email) {
                Mail::to($email)->send(new RelatorioExportReadyMail($request));
            }

            Log::info('Relatorio export completed', [
                'id' => $request->id,
                'type' => $request->type,
                'format' => $request->format,
                'total' => $result['total_records'],
            ]);
        } catch (Throwable $e) {
            Log::error('Relatorio export failed', [
                'id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $request->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function exportAquisicaoProdutos(RelatorioExportRequest $req): array
    {
        $filters = $req->filters ?? [];
        $user = $req->user;
        $isAdmin = $user->isAdmin();

        $medicoIdsFiltro = null;
        if ($user->isMedico()) {
            $medicoIdsFiltro = [$user->medico_id];
        } elseif (! empty($filters['medico_ids'])) {
            $medicoIdsFiltro = $filters['medico_ids'];
        }

        $laravelRequest = Request::create('/internal', 'GET', $filters);
        $relatorioController = app(RelatorioController::class);
        $dados = $relatorioController->buscarAquisicoes($laravelRequest, $medicoIdsFiltro, false);
        if (! $isAdmin) {
            $dados = $relatorioController->sanitizarAquisicaoSemValoresMonetarios($dados);
        }

        $dataInicio = $filters['data_inicio'] ?? now()->format('Y-m-d');
        $dataFim = $filters['data_fim'] ?? now()->format('Y-m-d');

        $totalRecords = (int) collect($dados['pacientes'] ?? [])->sum(fn ($p) => count($p['produtos'] ?? []));
        if ($totalRecords === 0 && ! empty($dados['pacientes'])) {
            $totalRecords = count($dados['pacientes']);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('relatorio-aquisicao-produtos_%s_%s.%s', now()->format('Ymd_His'), $req->id, $req->format);
        $filePath = 'exports/'.$fileName;

        if ($req->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.relatorio-aquisicao-produtos', [
                'dados' => $dados,
                'dataInicio' => $dataInicio,
                'dataFim' => $dataFim,
                'isAdmin' => $isAdmin,
            ]);
            $pdf->save($disk->path($filePath));
        } else {
            $bundle = app(RelatorioExcelExportService::class)->buildAquisicaoProdutosExport($user, $filters);
            Excel::store($bundle['export'], $filePath, 'local');
            $totalRecords = $bundle['total_records'];
        }

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_records' => $totalRecords,
        ];
    }

    private function exportReceitasMedico(RelatorioExportRequest $req): array
    {
        $filters = $req->filters ?? [];

        $disk = Storage::disk('local');
        if (! $disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('relatorio-receitas-medico_%s_%s.%s', now()->format('Ymd_His'), $req->id, $req->format);
        $filePath = 'exports/'.$fileName;

        if ($req->format === 'pdf') {
            $bundle = app(RelatorioExcelExportService::class)->buildReceitasMedicoExport($filters);
            $receitas = $bundle['receitas'];
            $medico = $bundle['medico'];

            $pdf = Pdf::loadView('pdf.relatorio-receitas-medico', [
                'receitas' => $receitas,
                'medico' => $medico,
                'dataInicio' => $filters['data_inicio'] ?? null,
                'dataFim' => $filters['data_fim'] ?? null,
                'totais' => [
                    'quantidade' => $receitas->count(),
                    'valor_total' => $receitas->sum('valor_total'),
                ],
            ]);
            $pdf->save($disk->path($filePath));

            return [
                'file_path' => $filePath,
                'file_name' => $fileName,
                'total_records' => $receitas->count(),
            ];
        }

        $bundle = app(RelatorioExcelExportService::class)->buildReceitasMedicoExport($filters);
        Excel::store($bundle['export'], $filePath, 'local');

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_records' => $bundle['total_records'],
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->relatorioExportRequest->markAsFailed($exception->getMessage());
        Log::error('Relatorio export job failed permanently', [
            'id' => $this->relatorioExportRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
