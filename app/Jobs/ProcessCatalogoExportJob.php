<?php

namespace App\Jobs;

use App\Exports\CatalogoProdutosExport;
use App\Mail\CatalogoExportReadyMail;
use App\Models\CatalogoExportRequest;
use App\Models\Produto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use setasign\Fpdi\Fpdi;
use Throwable;

class ProcessCatalogoExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    private const CHUNK_SIZE = 60;

    public function __construct(
        public CatalogoExportRequest $catalogoExportRequest
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $request = $this->catalogoExportRequest->fresh();

        if (! $request) {
            return;
        }

        if ($request->status === CatalogoExportRequest::STATUS_COMPLETED) {
            return;
        }

        $request->markAsProcessing();

        try {
            $search = $this->normalizeSearch($request->search);
            $produtos = $this->getProdutos($search);

            if ($request->format === 'pdf') {
                $result = $this->exportPdf($request, $produtos);
            } else {
                $result = $this->exportExcel($request, $produtos);
            }

            $request->markAsCompleted(
                $result['file_path'],
                $result['file_name'],
                $result['total_produtos']
            );

            $recipients = array_unique(array_filter([
                $request->user->email,
                ...($request->extra_emails ?? []),
            ]));
            foreach ($recipients as $email) {
                Mail::to($email)->send(new CatalogoExportReadyMail($request));
            }

            Log::info('Catalogo export completed', [
                'id' => $request->id,
                'format' => $request->format,
                'total' => $result['total_produtos'],
            ]);
        } catch (Throwable $e) {
            Log::error('Catalogo export failed', [
                'id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $request->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function normalizeSearch(?string $search): ?string
    {
        if (in_array($search, ['undefined', 'null', ''], true) || $search === null) {
            return null;
        }

        return $search;
    }

    private function getProdutos(?string $search)
    {
        return Produto::ativo()
            ->semLegadoSomenteLeitura()
            ->when($search, function ($q, $s) {
                $q->where(function ($query) use ($s) {
                    $query->where('nome', 'like', "%{$s}%")
                        ->orWhere('codigo', 'like', "%{$s}%");
                });
            })
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'descricao', 'modo_uso', 'anotacoes']);
    }

    private function exportPdf(CatalogoExportRequest $request, $produtos): array
    {
        $disk = Storage::disk('local');
        $tempDir = "catalogo-exports/{$request->id}";

        if (! $disk->exists($tempDir)) {
            $disk->makeDirectory($tempDir);
        }

        $chunks = $produtos->chunk(self::CHUNK_SIZE);
        $partFiles = [];
        $partIndex = 0;

        foreach ($chunks as $chunk) {
            $partIndex++;
            $produtosSanitized = $chunk->map(fn ($p) => (object) [
                'id' => $p->id,
                'codigo' => $this->sanitizeForPdf($p->codigo),
                'nome' => $this->sanitizeForPdf($p->nome),
                'descricao' => $this->sanitizeForPdf($p->descricao, 2000),
                'modo_uso' => $this->sanitizeForPdf($p->modo_uso, 2000),
                'anotacoes' => $this->sanitizeForPdf($p->anotacoes, 1000),
            ]);

            $pdf = Pdf::loadView('pdf.catalogo-produtos', [
                'produtos' => $produtosSanitized,
                'total' => $produtos->count(),
            ])->setPaper('a4', 'landscape');

            $partPath = "{$tempDir}/part_{$partIndex}.pdf";
            $fullPath = $disk->path($partPath);
            $pdf->save($fullPath);
            $partFiles[] = $fullPath;
        }

        $fileName = sprintf('catalogo-produtos_%s_%s.pdf', now()->format('Ymd_His'), $request->id);
        $filePath = 'exports/'.$fileName;

        if (! $disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $outputPath = $disk->path($filePath);
        $this->mergePdfs($partFiles, $outputPath);

        foreach ($partFiles as $partFile) {
            @unlink($partFile);
        }
        $disk->deleteDirectory($tempDir);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_produtos' => $produtos->count(),
        ];
    }

    private function mergePdfs(array $sourcePaths, string $outputPath): void
    {
        $pdf = new Fpdi;

        foreach ($sourcePaths as $sourcePath) {
            $pageCount = $pdf->setSourceFile($sourcePath);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pageId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($pageId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useImportedPage($pageId);
            }
        }

        $pdf->Output('F', $outputPath);
        $pdf->cleanUp();
    }

    private function exportExcel(CatalogoExportRequest $request, $produtos): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('exports')) {
            $disk->makeDirectory('exports');
        }

        $fileName = sprintf('catalogo-produtos_%s_%s.xlsx', now()->format('Ymd_His'), $request->id);
        $filePath = 'exports/'.$fileName;

        Excel::store(new CatalogoProdutosExport($produtos), $filePath, 'local');

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_produtos' => $produtos->count(),
        ];
    }

    private function sanitizeForPdf(mixed $value, int $maxLength = 500): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $clean = str_replace("\0", '', (string) $value);

        return mb_substr($clean, 0, $maxLength, 'UTF-8');
    }

    public function failed(Throwable $exception): void
    {
        $this->catalogoExportRequest->markAsFailed($exception->getMessage());
        Log::error('Catalogo export job failed permanently', [
            'id' => $this->catalogoExportRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
