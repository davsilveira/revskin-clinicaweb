<?php

namespace App\Console\Commands;

use App\Models\Produto;
use Illuminate\Console\Command;

class EnriquecerDadosLegadoClinicaweb extends Command
{
    protected $signature = 'produtos:enriquecer-dados-legado
                            {--arquivo=docs/sanitization/dados-legado-extraidos.csv : CSV extraído para enriquecimento}
                            {--dry-run : Simula sem gravar alterações}';

    protected $description = 'Enriquece produtos da base com nome, descrição (fórmula) e modo de uso do legado Clinicaweb';

    public function handle(): int
    {
        $arquivoPath = base_path($this->option('arquivo'));
        $dryRun = $this->option('dry-run');

        $this->info('=== Enriquecimento de Dados Legado ===');

        if (!file_exists($arquivoPath)) {
            $this->error("Arquivo não encontrado: {$arquivoPath}");
            return 1;
        }

        if ($dryRun) {
            $this->warn('MODO SIMULAÇÃO - Nenhuma alteração será gravada');
        }

        $linhas = $this->lerCsv($arquivoPath);

        if (empty($linhas)) {
            $this->error('Nenhum dado válido no arquivo.');
            return 1;
        }

        $atualizados = 0;
        $naoEncontrados = [];
        $ignorados = 0;

        $bar = $this->output->createProgressBar(count($linhas));
        $bar->start();

        foreach ($linhas as $linha) {
            $codigoBase = trim($linha['codigo_base'] ?? '');
            $naBase = strtolower(trim($linha['na_base'] ?? ''));

            if ($codigoBase === '' || $codigoBase === 'sim') {
                $ignorados++;
                $bar->advance();
                continue;
            }

            if ($naBase !== 'sim') {
                $ignorados++;
                $bar->advance();
                continue;
            }

            $produto = Produto::where('codigo', $codigoBase)->first();

            if (!$produto) {
                $naoEncontrados[] = $codigoBase;
                $bar->advance();
                continue;
            }

            $nome = trim($linha['nome_extraido'] ?? '');
            $formula = trim($linha['formula_extraida'] ?? '');
            $modoUso = trim($linha['modo_uso_extraido'] ?? '');

            if (!$dryRun) {
                $produto->nome = $nome ?: $produto->nome;
                $produto->descricao = $formula ?: $produto->descricao;
                $produto->modo_uso = $modoUso ?: $produto->modo_uso;
                $produto->save();
            }

            $atualizados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('=== Resumo ===');
        $this->line("Produtos enriquecidos: {$atualizados}");
        $this->line("Linhas ignoradas: {$ignorados}");

        if (!empty($naoEncontrados)) {
            $this->warn('Códigos não encontrados na base: ' . count($naoEncontrados));
            foreach (array_slice($naoEncontrados, 0, 10) as $c) {
                $this->line("  - {$c}");
            }
            if (count($naoEncontrados) > 10) {
                $this->line('  ... e mais ' . (count($naoEncontrados) - 10));
            }
        }

        if ($dryRun) {
            $this->warn('Execute sem --dry-run para aplicar as alterações.');
        } else {
            $this->info('Enriquecimento concluído com sucesso.');
        }

        return 0;
    }

    /**
     * Lê o CSV com suporte a campos multilinha (entre aspas).
     */
    protected function lerCsv(string $path): array
    {
        $fp = fopen($path, 'r');
        if (!$fp) {
            return [];
        }

        $linhas = [];
        $headers = null;
        $separador = ';';

        while (($row = fgetcsv($fp, 0, $separador)) !== false) {
            if ($headers === null) {
                $headers = $row;
                continue;
            }

            if (count($row) < 2) {
                continue;
            }

            $linha = [];
            foreach ($headers as $i => $h) {
                $linha[trim($h)] = trim($row[$i] ?? '');
            }
            $linhas[] = $linha;
        }

        fclose($fp);

        return $linhas;
    }
}
