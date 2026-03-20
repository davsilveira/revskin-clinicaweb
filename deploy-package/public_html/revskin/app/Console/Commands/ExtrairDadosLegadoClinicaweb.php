<?php

namespace App\Console\Commands;

use App\Models\Produto;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExtrairDadosLegadoClinicaweb extends Command
{
    protected $signature = 'produtos:extrair-dados-legado
                            {--clinicaweb=docs/sanitization/produtos-clinicaweb-2.xls : Arquivo XLS do Clinicaweb}
                            {--mapeamento=docs/sanitization/mapeamento-karnaugh-olist.csv : CSV de mapeamento codigo legado -> sku base}
                            {--output=docs/sanitization/dados-legado-extraidos.csv : Arquivo de saída para revisão}';

    protected $description = 'Extrai Nome, Fórmula e Modo de uso do Clinicaweb e gera arquivo para revisão antes do enrichment';

    protected array $mapeamentoLegadoParaBase = [];

    public function handle(): int
    {
        $clinicawebPath = base_path($this->option('clinicaweb'));
        $mapeamentoPath = base_path($this->option('mapeamento'));
        $outputPath = base_path($this->option('output'));

        $this->info('=== Extração de Dados Legado Clinicaweb ===');

        if (!file_exists($clinicawebPath)) {
            $this->error("Arquivo não encontrado: {$clinicawebPath}");
            return 1;
        }

        $this->carregarMapeamento($mapeamentoPath);

        $spreadsheet = IOFactory::load($clinicawebPath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $produtosBase = Produto::all()->keyBy('codigo');
        $dadosExtraidos = [];
        $naoEncontrados = [];
        $semDescricao = 0;

        foreach ($rows as $r => $row) {
            $codigoLegado = trim((string) ($row[1] ?? ''));
            $descricaoBruta = trim((string) ($row[3] ?? ''));

            if ($codigoLegado === '' || $codigoLegado === 'CODIGO' || $codigoLegado === '...') {
                continue;
            }

            $codigoBase = $this->mapeamentoLegadoParaBase[$codigoLegado] ?? $codigoLegado;

            if (!$produtosBase->has($codigoBase)) {
                $produto = Produto::where('codigo', $codigoBase)
                    ->orWhere('codigo', 'like', $codigoLegado . ' %')
                    ->first();
                if ($produto) {
                    $codigoBase = $produto->codigo;
                    $produtosBase->put($produto->codigo, $produto);
                }
            }

            if (!$produtosBase->has($codigoBase)) {
                $naoEncontrados[] = ['legado' => $codigoLegado, 'base' => $codigoBase];
                continue;
            }

            if ($descricaoBruta === '') {
                $semDescricao++;
                continue;
            }

            $parsed = $this->parsearDescricao($descricaoBruta);
            $dadosExtraidos[] = [
                'codigo_legado' => $codigoLegado,
                'codigo_base' => $codigoBase,
                'nome_atual' => $produtosBase[$codigoBase]->nome,
                'nome_extraido' => $parsed['nome'],
                'formula_extraida' => $parsed['formula'],
                'modo_uso_extraido' => $parsed['modo_uso'],
                'na_base' => 'sim',
            ];
        }

        $this->escreverCsv($outputPath, $dadosExtraidos);

        $this->newLine();
        $this->info('=== Resumo ===');
        $this->line('Produtos com dados extraídos: ' . count($dadosExtraidos));
        $this->line('Produtos na base sem descrição no legado: ' . $semDescricao);
        if (!empty($naoEncontrados)) {
            $this->warn('Produtos no legado não encontrados na base: ' . count($naoEncontrados));
            foreach (array_slice($naoEncontrados, 0, 10) as $n) {
                $this->line("  - {$n['legado']} (base esperada: {$n['base']})");
            }
            if (count($naoEncontrados) > 10) {
                $this->line('  ... e mais ' . (count($naoEncontrados) - 10));
            }
        }

        $this->newLine();
        $this->info("Arquivo gerado: {$outputPath}");
        $this->info('Revise o arquivo e confirme para prosseguir com o enriquecimento (produtos:enriquecer-dados-legado).');

        return 0;
    }

    protected function carregarMapeamento(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }

            $row = str_getcsv($line, ';');
            $codigoKarnaugh = trim($row[0] ?? '');
            $skuOlist = trim($row[1] ?? '');
            $matchType = trim($row[4] ?? '');

            if (str_starts_with($codigoKarnaugh, '---') || $skuOlist === '' || $matchType === 'teste_ignorado' || $matchType === 'tonalite_variacao') {
                continue;
            }

            if ($codigoKarnaugh !== $skuOlist) {
                $this->mapeamentoLegadoParaBase[$codigoKarnaugh] = $skuOlist;
            }
        }
    }

    protected function parsearDescricao(string $texto): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', $texto);
        $linhas = array_map('trim', $linhas);
        $linhas = array_values(array_filter($linhas, fn($l) => $l !== ''));

        if (empty($linhas)) {
            return ['nome' => '', 'formula' => '', 'modo_uso' => ''];
        }

        $nome = $linhas[0];
        $formula = '';
        $modoUso = '';
        $inicioModoUso = null;

        for ($i = 1; $i < count($linhas); $i++) {
            $linha = $linhas[$i];
            $linhaLower = mb_strtolower($linha);

            if ($inicioModoUso === null && $this->ehInicioModoUso($linha, $linhaLower)) {
                $inicioModoUso = $i;
            }

            if ($inicioModoUso !== null) {
                $modoUso .= ($modoUso ? "\n" : '') . $linha;
            } else {
                $formula .= ($formula ? "\n" : '') . $linha;
            }
        }

        return [
            'nome' => $nome,
            'formula' => trim($formula),
            'modo_uso' => trim($modoUso),
        ];
    }

    /**
     * Detecta se a linha inicia a seção "Modo de uso".
     */
    protected function ehInicioModoUso(string $linha, string $linhaLower): bool
    {
        if ($linha === '') {
            return false;
        }

        // Contém "Uso:" ou "Uso :" (indica instruções de uso)
        if (preg_match('/\buso\s*:/u', $linhaLower)) {
            return true;
        }
        // "Uso como" ou "Uso para" (ex: "1. Uso como Máscara:", "2. Uso para Limpeza:")
        if (str_contains($linhaLower, 'uso como') || str_contains($linhaLower, 'uso para')) {
            return true;
        }
        // "Modo de uso"
        if (str_contains($linhaLower, 'modo de uso')) {
            return true;
        }
        // Início com "Inicie" (ex: "Inicie o uso...", "Inicie alternando...")
        if (str_starts_with($linhaLower, 'inicie ')) {
            return true;
        }
        // "Deixe agir" (ex: "Deixe agir por 15 min...")
        if (str_starts_with($linhaLower, 'deixe agir')) {
            return true;
        }
        // "Nas primeiras" (ex: "Nas primeiras 2 semanas...")
        if (str_starts_with($linhaLower, 'nas primeiras')) {
            return true;
        }
        // Lista numerada de uso (ex: "1. Uso como...", "2. Uso para...")
        if (preg_match('/^\d+\.\s*(uso|aplique|aplicar|espalhe)/iu', $linha)) {
            return true;
        }
        // Comandos de aplicação no início da linha
        if (preg_match('/^(aplique|aplicar|usar|utilize|utilizar|espalhe)\b/u', $linhaLower)) {
            return true;
        }

        return false;
    }

    protected function escreverCsv(string $path, array $dados): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = ['codigo_legado', 'codigo_base', 'nome_atual', 'nome_extraido', 'formula_extraida', 'modo_uso_extraido', 'na_base'];
        fputcsv($fp, $headers, ';');

        foreach ($dados as $row) {
            fputcsv($fp, $row, ';');
        }

        fclose($fp);
    }
}
