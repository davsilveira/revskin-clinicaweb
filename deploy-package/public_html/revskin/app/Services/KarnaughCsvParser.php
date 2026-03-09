<?php

namespace App\Services;

use App\Models\TabelaKarnaugh;
use App\Models\TabelaKarnaughProduto;
use Illuminate\Support\Facades\DB;

class KarnaughCsvParser
{
    /**
     * Parsear e importar um arquivo CSV de tabela Karnaugh.
     *
     * Estrutura esperada do CSV:
     * - Linha 1: Grupos (Primeiro Grupo / Segundo Grupo)
     * - Linha 2: Sequência/ordem
     * - Linha 3: Nomes das categorias (colunas de produtos)
     * - Linha 4: Teste? (indica campos condicionais)
     * - Linha 5: Marcar? (indica se o produto deve vir pré-selecionado)
     * - Linhas 6+: Dados dos casos clínicos
     *
     * @param string $csvContent Conteúdo do CSV
     * @param string $nome Nome da tabela
     * @param string|null $descricao Descrição opcional
     * @param string|null $arquivoOriginal Nome do arquivo original
     * @param bool $definirComoPadrao Se deve ser a tabela padrão
     * @return TabelaKarnaugh
     */
    public function parse(
        string $csvContent,
        string $nome,
        ?string $descricao = null,
        ?string $arquivoOriginal = null,
        bool $definirComoPadrao = false
    ): TabelaKarnaugh {
        $lines = $this->parseCsvLines($csvContent);
        
        if (count($lines) < 6) {
            throw new \InvalidArgumentException('CSV deve ter pelo menos 6 linhas (cabeçalhos + dados)');
        }

        // Parsear estrutura do cabeçalho
        $gruposRow = $lines[0] ?? [];
        $seqRow = $lines[1] ?? [];
        $categoriasRow = $lines[2] ?? [];
        $testeRow = $lines[3] ?? [];
        $marcarRow = $lines[4] ?? [];

        // Identificar colunas de produtos e seus grupos
        $colunasProdutos = $this->identificarColunasProdutos($gruposRow, $categoriasRow, $marcarRow, $seqRow);

        return DB::transaction(function () use (
            $nome, $descricao, $arquivoOriginal, $definirComoPadrao,
            $lines, $colunasProdutos
        ) {
            // Criar a tabela
            $tabela = TabelaKarnaugh::create([
                'nome' => $nome,
                'descricao' => $descricao,
                'arquivo_original' => $arquivoOriginal,
                'ativo' => true,
                'padrao' => false,
            ]);

            // Processar linhas de dados (a partir da linha 6, índice 5)
            $ordemLinha = 0;
            for ($i = 5; $i < count($lines); $i++) {
                $row = $lines[$i];
                
                // Ignorar linhas vazias ou de legenda
                if (count($row) < 2 || empty(trim($row[1] ?? ''))) {
                    continue;
                }

                $casoClinico = trim($row[1] ?? '');
                
                // Ignorar se não parece um código de caso clínico válido
                if (empty($casoClinico) || !preg_match('/^P[SNRMO]/i', $casoClinico)) {
                    continue;
                }

                // Processar cada coluna de produto
                foreach ($colunasProdutos as $colIndex => $colInfo) {
                    $produtoCodigo = trim($row[$colIndex] ?? '');
                    
                    // Ignorar células vazias ou marcadores especiais
                    if (empty($produtoCodigo) || $produtoCodigo === '*****' || $produtoCodigo === 'Fim') {
                        continue;
                    }
                    
                    // Ignorar marcadores como "Linhas 0"
                    if (preg_match('/^Linhas?\s+\d+$/i', $produtoCodigo)) {
                        continue;
                    }

                    TabelaKarnaughProduto::create([
                        'tabela_karnaugh_id' => $tabela->id,
                        'caso_clinico' => $casoClinico,
                        'categoria' => $colInfo['categoria'],
                        'produto_codigo' => $produtoCodigo,
                        'grupo' => $colInfo['grupo'],
                        'marcar' => $colInfo['marcar'],
                        'ordem' => $ordemLinha,
                        'sequencia_coluna' => $colInfo['sequencia'],
                    ]);
                }
                
                $ordemLinha++;
            }

            // Definir como padrão se solicitado
            if ($definirComoPadrao) {
                $tabela->definirComoPadrao();
            }

            return $tabela->fresh();
        });
    }

    /**
     * Parsear linhas do CSV.
     */
    private function parseCsvLines(string $content): array
    {
        $lines = [];
        $rows = explode("\n", $content);
        
        foreach ($rows as $row) {
            $row = trim($row, "\r\n");
            if (empty($row)) {
                continue;
            }
            
            // CSV usa ponto-e-vírgula como separador
            $cells = str_getcsv($row, ';');
            $lines[] = $cells;
        }
        
        return $lines;
    }

    /**
     * Identificar colunas de produtos e seus respectivos grupos/marcar.
     */
    private function identificarColunasProdutos(array $gruposRow, array $categoriasRow, array $marcarRow, array $seqRow): array
    {
        $colunas = [];
        $grupoAtual = 'primeiro';

        for ($i = 2; $i < count($categoriasRow); $i++) {
            $categoria = trim($categoriasRow[$i] ?? '');
            $grupo = trim($gruposRow[$i] ?? '');
            $marcar = trim($marcarRow[$i] ?? '');
            $seq = trim($seqRow[$i] ?? '');

            // Ignorar colunas sem categoria válida
            if (empty($categoria) || $categoria === 'Fim' || $categoria === 'Coluna Extra') {
                continue;
            }

            // Determinar grupo baseado na linha de grupos
            if (stripos($grupo, 'Segundo Grupo') !== false) {
                $grupoAtual = 'segundo';
            } elseif (stripos($grupo, 'Primeiro Grupo') !== false) {
                $grupoAtual = 'primeiro';
            }

            // Determinar se deve marcar
            $deveMarcar = stripos($marcar, 'Marcar') !== false || $grupoAtual === 'primeiro';

            // Sequência da coluna (para ordenação)
            $sequencia = is_numeric($seq) ? (int) $seq : 9999;

            $colunas[$i] = [
                'categoria' => $categoria,
                'grupo' => $grupoAtual,
                'marcar' => $deveMarcar && $grupoAtual === 'primeiro',
                'sequencia' => $sequencia,
            ];
        }

        return $colunas;
    }

    /**
     * Validar estrutura do CSV antes de importar.
     */
    public function validate(string $csvContent): array
    {
        $errors = [];
        $lines = $this->parseCsvLines($csvContent);

        if (count($lines) < 6) {
            $errors[] = 'CSV deve ter pelo menos 6 linhas (5 de cabeçalho + dados)';
            return $errors;
        }

        // Verificar linha de grupos
        $gruposRow = $lines[0];
        $hasPrimeiroGrupo = false;
        foreach ($gruposRow as $cell) {
            if (stripos($cell, 'Primeiro Grupo') !== false) {
                $hasPrimeiroGrupo = true;
                break;
            }
        }
        if (!$hasPrimeiroGrupo) {
            $errors[] = 'Linha 1 deve conter "Primeiro Grupo"';
        }

        // Verificar linha de categorias
        $categoriasRow = $lines[2];
        if (empty(trim($categoriasRow[0] ?? '')) && empty(trim($categoriasRow[1] ?? ''))) {
            $errors[] = 'Linha 3 deve conter as categorias de produtos';
        }

        // Contar casos clínicos válidos
        $casosValidos = 0;
        for ($i = 5; $i < count($lines); $i++) {
            $row = $lines[$i];
            $casoClinico = trim($row[1] ?? '');
            if (!empty($casoClinico) && preg_match('/^P[SNRMO]/i', $casoClinico)) {
                $casosValidos++;
            }
        }

        if ($casosValidos === 0) {
            $errors[] = 'Nenhum caso clínico válido encontrado (códigos devem começar com P seguido de S, N, R, M ou O)';
        }

        return $errors;
    }
}
