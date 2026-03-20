<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MapearProdutosKarnaugh extends Command
{
    protected $signature = 'karnaugh:mapear-produtos
                            {--karnaugh-dir=docs/sanitization : Diretório com os XLSX das tabelas Karnaugh}
                            {--olist=docs/sanitization/produtos-olist.xls : Arquivo XLS com os produtos do oList}
                            {--output=docs/sanitization/mapeamento-karnaugh-olist.csv : Arquivo CSV de saída}';

    protected $description = 'Gera CSV de mapeamento entre códigos Karnaugh e SKUs do oList para revisão';

    public function handle(): int
    {
        $karnaughDir = base_path($this->option('karnaugh-dir'));
        $olistFile = base_path($this->option('olist'));
        $outputPath = base_path($this->option('output'));

        $this->info('=== Mapeamento Karnaugh -> oList ===');

        // 1. Extract unique product codes from Karnaugh XLSX files
        $karnaughCodes = $this->extractKarnaughCodes($karnaughDir);
        if (empty($karnaughCodes)) {
            $this->error('Nenhum código de produto encontrado nas tabelas Karnaugh.');
            return 1;
        }
        $this->info(count($karnaughCodes) . ' códigos únicos encontrados nas tabelas Karnaugh.');

        // 2. Extract all products from oList XLS
        $olistProducts = $this->extractOlistProducts($olistFile);
        if (empty($olistProducts)) {
            $this->error('Nenhum produto encontrado no arquivo oList.');
            return 1;
        }
        $this->info(count($olistProducts) . ' produtos com SKU encontrados no oList.');

        // 3. Build mapping
        $mapping = $this->buildMapping($karnaughCodes, $olistProducts);

        // 4. Add TONALITE variations (all TONALITE-*-G30 SKUs need the tag too)
        $tonaliteSkus = $this->findTonaliteVariations($olistProducts);

        // 5. Write CSV
        $this->writeCsv($outputPath, $mapping, $tonaliteSkus);

        // 6. Summary
        $this->printSummary($mapping, $tonaliteSkus);

        $this->newLine();
        $this->info("CSV gerado em: {$outputPath}");
        $this->info('Revise o arquivo e confirme antes de prosseguir com as próximas etapas.');

        return 0;
    }

    protected function extractKarnaughCodes(string $dir): array
    {
        $codes = [];
        $files = glob($dir . '/*.xlsx');

        if (empty($files)) {
            $this->warn("Nenhum arquivo XLSX encontrado em: {$dir}");
            return [];
        }

        foreach ($files as $file) {
            $this->line("  Lendo: " . basename($file));
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Data rows start at index 5 (row 6), product columns start at index 2 (col C)
            for ($r = 5; $r < count($rows); $r++) {
                for ($c = 2; $c < count($rows[$r]); $c++) {
                    $val = trim((string) ($rows[$r][$c] ?? ''));
                    if ($this->isProductCode($val)) {
                        $codes[$val] = ($codes[$val] ?? 0) + 1;
                    }
                }
            }
        }

        ksort($codes);
        return $codes;
    }

    protected function isProductCode(string $val): bool
    {
        if ($val === '' || $val === '*****') {
            return false;
        }
        if (str_starts_with($val, 'Linhas') || str_starts_with($val, 'Coluna') || $val === 'Fim') {
            return false;
        }
        return true;
    }

    protected function extractOlistProducts(string $file): array
    {
        if (!file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");
            return [];
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $products = [];
        // Row 0 = headers: ID, Código (SKU), Descrição, ...
        for ($r = 1; $r < count($rows); $r++) {
            $id = trim((string) ($rows[$r][0] ?? ''));
            $sku = trim((string) ($rows[$r][1] ?? ''));
            $descricao = trim((string) ($rows[$r][2] ?? ''));
            $situacao = trim((string) ($rows[$r][9] ?? ''));

            if ($sku !== '') {
                $products[$sku] = [
                    'tiny_id' => $id,
                    'sku' => $sku,
                    'descricao' => $descricao,
                    'situacao' => $situacao,
                ];
            }
        }

        return $products;
    }

    protected function buildMapping(array $karnaughCodes, array $olistProducts): array
    {
        $olistByNormalized = [];
        foreach ($olistProducts as $sku => $product) {
            $normalized = $this->normalize($sku);
            $olistByNormalized[$normalized] = $product;
        }

        $manualMap = [
            'AQUELANE-8-G30' => 'AQUELANE-8',
            'DEMERANE-ULTRA-30G' => 'DEMERANE-ULTRA-G30',
            'MAOS-DIA-DYNAMISEE-3' => 'MAOS-DIA',
            'IVERMECTINA-ROSACEA-20G' => 'IVERMECTINA-ROSACEA',
            'RETINOL-DIN3-30G' => 'NOITE-HIPOALERGENICO-RETINOL-LUMI-DYN3 3736',
            'RETINOL-HYDRAVELT-30G' => 'NOITE-HIPOALERGENICO-RETINOL-LUMI-HYDRAVELT 3303',
        ];

        $skipCodes = ['TONALITE-__-G30', 'GRUPO-1-TESTE-A', 'GRUPO-1-TESTE-B'];

        $mapping = [];
        foreach ($karnaughCodes as $code => $count) {
            if (in_array($code, $skipCodes)) {
                $matchType = $code === 'TONALITE-__-G30' ? 'template' : 'teste_ignorado';
                $mapping[] = [
                    'codigo_karnaugh' => $code,
                    'sku_olist' => '',
                    'tiny_id' => '',
                    'descricao_olist' => '',
                    'match_type' => $matchType,
                    'ocorrencias' => $count,
                ];
                continue;
            }

            // Exact match
            if (isset($olistProducts[$code])) {
                $p = $olistProducts[$code];
                $mapping[] = [
                    'codigo_karnaugh' => $code,
                    'sku_olist' => $p['sku'],
                    'tiny_id' => $p['tiny_id'],
                    'descricao_olist' => $p['descricao'],
                    'match_type' => 'exato',
                    'ocorrencias' => $count,
                ];
                continue;
            }

            // Normalized match (space -> dash)
            $normalized = $this->normalize($code);
            if (isset($olistByNormalized[$normalized])) {
                $p = $olistByNormalized[$normalized];
                $mapping[] = [
                    'codigo_karnaugh' => $code,
                    'sku_olist' => $p['sku'],
                    'tiny_id' => $p['tiny_id'],
                    'descricao_olist' => $p['descricao'],
                    'match_type' => 'normalizado',
                    'ocorrencias' => $count,
                ];
                continue;
            }

            // Manual mapping
            if (isset($manualMap[$code]) && isset($olistProducts[$manualMap[$code]])) {
                $p = $olistProducts[$manualMap[$code]];
                $mapping[] = [
                    'codigo_karnaugh' => $code,
                    'sku_olist' => $p['sku'],
                    'tiny_id' => $p['tiny_id'],
                    'descricao_olist' => $p['descricao'],
                    'match_type' => 'manual',
                    'ocorrencias' => $count,
                ];
                continue;
            }

            // No match
            $mapping[] = [
                'codigo_karnaugh' => $code,
                'sku_olist' => '',
                'tiny_id' => '',
                'descricao_olist' => '',
                'match_type' => 'nao_encontrado',
                'ocorrencias' => $count,
            ];
        }

        return $mapping;
    }

    protected function findTonaliteVariations(array $olistProducts): array
    {
        $tonalites = [];
        foreach ($olistProducts as $sku => $product) {
            if (preg_match('/^TONALITE-[\d,]+-G30$/', $sku)) {
                $tonalites[] = [
                    'codigo_karnaugh' => 'TONALITE-__-G30',
                    'sku_olist' => $product['sku'],
                    'tiny_id' => $product['tiny_id'],
                    'descricao_olist' => $product['descricao'],
                    'match_type' => 'tonalite_variacao',
                    'ocorrencias' => 0,
                ];
            }
        }
        usort($tonalites, fn($a, $b) => strnatcasecmp($a['sku_olist'], $b['sku_olist']));
        return $tonalites;
    }

    protected function normalize(string $code): string
    {
        return str_replace(' ', '-', strtoupper(trim($code)));
    }

    protected function writeCsv(string $path, array $mapping, array $tonaliteSkus): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = ['codigo_karnaugh', 'sku_olist', 'tiny_id', 'descricao_olist', 'match_type', 'ocorrencias'];
        fputcsv($fp, $headers, ';');

        foreach ($mapping as $row) {
            fputcsv($fp, $row, ';');
        }

        // Separator row for TONALITE variations
        if (!empty($tonaliteSkus)) {
            fputcsv($fp, ['--- TONALITE VARIACOES (tag clinicaweb) ---', '', '', '', '', ''], ';');
            foreach ($tonaliteSkus as $row) {
                fputcsv($fp, $row, ';');
            }
        }

        fclose($fp);
    }

    protected function printSummary(array $mapping, array $tonaliteSkus): void
    {
        $counts = ['exato' => 0, 'normalizado' => 0, 'manual' => 0, 'template' => 0, 'teste_ignorado' => 0, 'nao_encontrado' => 0];
        foreach ($mapping as $row) {
            $counts[$row['match_type']] = ($counts[$row['match_type']] ?? 0) + 1;
        }

        $this->newLine();
        $this->info('=== RESUMO ===');
        $this->table(
            ['Tipo', 'Quantidade'],
            [
                ['Match exato', $counts['exato']],
                ['Normalizado (espaço->hífen)', $counts['normalizado']],
                ['Mapeamento manual', $counts['manual']],
                ['Template (TONALITE)', $counts['template']],
                ['Teste (ignorado)', $counts['teste_ignorado']],
                ['Não encontrado', $counts['nao_encontrado']],
                ['Variações TONALITE para tag', count($tonaliteSkus)],
            ]
        );

        $naoEncontrados = array_filter($mapping, fn($r) => $r['match_type'] === 'nao_encontrado');
        if (!empty($naoEncontrados)) {
            $this->newLine();
            $this->warn('Produtos NÃO encontrados no oList:');
            foreach ($naoEncontrados as $row) {
                $this->line("  - {$row['codigo_karnaugh']}");
            }
        }
    }
}
