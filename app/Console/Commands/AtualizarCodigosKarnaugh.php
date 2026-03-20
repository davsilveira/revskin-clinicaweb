<?php

namespace App\Console\Commands;

use App\Models\Produto;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AtualizarCodigosKarnaugh extends Command
{
    protected $signature = 'karnaugh:atualizar-codigos
                            {--mapeamento=docs/sanitization/mapeamento-karnaugh-olist.csv : Arquivo CSV de mapeamento}
                            {--karnaugh-dir=docs/sanitization : Diretório com os XLSX das tabelas Karnaugh}
                            {--dry-run : Simular sem gravar alterações}';

    protected $description = 'Substitui códigos Karnaugh pelos SKUs do oList nos XLSX, conforme mapeamento aprovado';

    protected array $mapeamento = [];
    protected array $codigosIgnorados = ['TONALITE-__-G30', 'GRUPO-1-TESTE-A', 'GRUPO-1-TESTE-B'];

    public function handle(): int
    {
        $mapeamentoPath = base_path($this->option('mapeamento'));
        $karnaughDir = base_path($this->option('karnaugh-dir'));
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODO SIMULAÇÃO - Nenhum arquivo será alterado');
        }

        $this->info('=== Atualização de Códigos nas Tabelas Karnaugh ===');

        if (!file_exists($mapeamentoPath)) {
            $this->error("Arquivo de mapeamento não encontrado: {$mapeamentoPath}");
            return 1;
        }

        $this->mapeamento = $this->carregarMapeamento($mapeamentoPath);
        $this->info(count($this->mapeamento) . ' códigos serão substituídos (excluindo template e testes).');

        $files = glob($karnaughDir . '/*.xlsx');
        $karnaughFiles = array_filter($files, fn($f) => !str_contains(basename($f), '~$'));

        if (empty($karnaughFiles)) {
            $this->error("Nenhum arquivo XLSX encontrado em: {$karnaughDir}");
            return 1;
        }

        $totalSubstituicoes = 0;
        foreach ($karnaughFiles as $file) {
            $count = $this->processarArquivo($file, $dryRun);
            $totalSubstituicoes += $count;
        }

        $this->info("Total de substituições: {$totalSubstituicoes}");

        if (!$dryRun && $totalSubstituicoes > 0) {
            $this->verificarProdutosImportados();
        }

        return 0;
    }

    protected function carregarMapeamento(string $path): array
    {
        $map = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $header = null;

        foreach ($lines as $i => $line) {
            $row = str_getcsv($line, ';');
            if ($i === 0) {
                $header = $row;
                continue;
            }

            $codigoKarnaugh = trim($row[0] ?? '');
            $skuOlist = trim($row[1] ?? '');
            $matchType = trim($row[4] ?? '');

            if (str_starts_with($codigoKarnaugh, '---')) {
                continue;
            }
            if (in_array($codigoKarnaugh, $this->codigosIgnorados)) {
                continue;
            }
            if ($matchType === 'teste_ignorado') {
                continue;
            }
            if ($matchType === 'tonalite_variacao') {
                continue;
            }
            if ($skuOlist === '') {
                continue;
            }

            $map[$codigoKarnaugh] = $skuOlist;
        }

        return $map;
    }

    protected function processarArquivo(string $file, bool $dryRun): int
    {
        $this->line("Processando: " . basename($file));

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $substituicoes = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $colIndex = $cell->getColumn();
                $value = $cell->getValue();

                if (!is_string($value)) {
                    continue;
                }

                $original = trim($value);
                if ($original === '') {
                    continue;
                }

                if (isset($this->mapeamento[$original])) {
                    $novo = $this->mapeamento[$original];
                    if ($original !== $novo) {
                        if (!$dryRun) {
                            $cell->setValue($novo);
                        }
                        $substituicoes++;
                    }
                }
            }
        }

        if (!$dryRun && $substituicoes > 0) {
            $backupPath = preg_replace('/\.xlsx$/i', '_backup_' . date('YmdHis') . '.xlsx', $file);
            copy($file, $backupPath);
            $this->line("  Backup: " . basename($backupPath));

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($file);
        }

        $this->line("  Substituições: {$substituicoes}");
        return $substituicoes;
    }

    protected function verificarProdutosImportados(): void
    {
        $this->newLine();
        $this->info('=== Verificação: Produtos Karnaugh x Base Local ===');

        $skusNecessarios = array_unique(array_values($this->mapeamento));

        $tonaliteVariacoes = ['TONALITE-1-G30', 'TONALITE-1,5-G30', 'TONALITE-2-G30', 'TONALITE-2,5-G30', 'TONALITE-3-G30', 'TONALITE-3,5-G30', 'TONALITE-4-G30', 'TONALITE-4,5-G30'];
        $skusNecessarios = array_merge($skusNecessarios, $tonaliteVariacoes);
        $skusNecessarios = array_unique($skusNecessarios);

        $produtosNaBase = Produto::whereIn('codigo', $skusNecessarios)->pluck('codigo')->flip();

        $faltando = [];
        foreach ($skusNecessarios as $sku) {
            if (!isset($produtosNaBase[$sku])) {
                $faltando[] = $sku;
            }
        }

        if (empty($faltando)) {
            $this->info('Todos os ' . count($skusNecessarios) . ' produtos presentes nas tabelas Karnaugh foram importados.');
        } else {
            $this->warn('Produtos presentes nas tabelas Karnaugh que NÃO foram importados (' . count($faltando) . '):');
            foreach ($faltando as $sku) {
                $this->line("  - {$sku}");
            }
        }
    }
}
