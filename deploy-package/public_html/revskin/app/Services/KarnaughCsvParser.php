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
     * @param string $csvContent Conteúdo do CSV
     */
    public function parse(
        string $csvContent,
        string $nome,
        ?string $descricao = null,
        ?string $arquivoOriginal = null,
        bool $definirComoPadrao = false
    ): TabelaKarnaugh {
        $lines = $this->parseCsvLines($csvContent);
        return $this->parseFromRows($lines, $nome, $descricao, $arquivoOriginal, $definirComoPadrao);
    }

    /**
     * Parsear e importar tabela Karnaugh a partir de array de linhas (CSV ou XLSX).
     *
     * Estrutura esperada:
     * - Linha 1: Grupos (Primeiro Grupo / Segundo Grupo)
     * - Linha 2: Sequência/ordem
     * - Linha 3: Nomes das categorias (colunas de produtos)
     * - Linha 4: Teste? (indica campos condicionais)
     * - Linha 5: Marcar? (indica se o produto deve vir pré-selecionado)
     * - Linhas 6+: Dados dos casos clínicos
     *
     * @param array $lines Array de linhas, cada linha é array de células
     */
    public function parseFromRows(
        array $lines,
        string $nome,
        ?string $descricao = null,
        ?string $arquivoOriginal = null,
        bool $definirComoPadrao = false
    ): TabelaKarnaugh {
        if (count($lines) < 6) {
            throw new \InvalidArgumentException('Arquivo deve ter pelo menos 6 linhas (cabeçalhos + dados)');
        }

        $gruposRow = $lines[0] ?? [];
        $seqRow = $lines[1] ?? [];
        $categoriasRow = $lines[2] ?? [];
        $marcarRow = $lines[4] ?? [];

        $colunasProdutos = $this->identificarColunasProdutos($gruposRow, $categoriasRow, $marcarRow, $seqRow);

        return DB::transaction(function () use (
            $nome, $descricao, $arquivoOriginal, $definirComoPadrao,
            $lines, $colunasProdutos
        ) {
            $tabela = TabelaKarnaugh::create([
                'nome' => $nome,
                'descricao' => $descricao,
                'arquivo_original' => $arquivoOriginal,
                'ativo' => true,
                'padrao' => false,
            ]);

            $ordemLinha = 0;
            $totalLinhas = count($lines);
            for ($i = 5; $i < $totalLinhas; $i++) {
                $row = $lines[$i];
                $row = is_array($row) ? $row : [];

                $celulaCaso = $this->cellValue($row[1] ?? null);
                if (count($row) < 2 || trim($celulaCaso) === '') {
                    continue;
                }

                $casoClinico = trim($celulaCaso);

                if (empty($casoClinico) || !preg_match('/^P[SNRMO]/i', $casoClinico)) {
                    continue;
                }

                foreach ($colunasProdutos as $colIndex => $colInfo) {
                    $produtoCodigo = trim($this->cellValue($row[$colIndex] ?? null));

                    if (empty($produtoCodigo) || $produtoCodigo === '*****' || $produtoCodigo === 'Fim') {
                        continue;
                    }

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

            if ($definirComoPadrao) {
                $tabela->definirComoPadrao();
            }

            return $tabela->fresh();
        });
    }

    /**
     * Normalizar valor de célula (null do XLSX vira string vazia).
     */
    private function cellValue(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    /**
     * Parsear linhas do CSV.
     */
    public function parseCsvLines(string $content): array
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
            $categoria = trim($this->cellValue($categoriasRow[$i] ?? null));
            $grupo = trim($this->cellValue($gruposRow[$i] ?? null));
            $marcar = trim($this->cellValue($marcarRow[$i] ?? null));
            $seq = trim($this->cellValue($seqRow[$i] ?? null));

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
        $lines = $this->parseCsvLines($csvContent);
        return $this->validateRows($lines);
    }

    /**
     * Validar estrutura do array de linhas antes de importar (CSV ou XLSX).
     */
    public function validateRows(array $lines): array
    {
        $errors = [];

        if (count($lines) < 6) {
            $errors[] = 'Arquivo deve ter pelo menos 6 linhas (5 de cabeçalho + dados)';
            return $errors;
        }

        $gruposRow = $lines[0];
        $hasPrimeiroGrupo = false;
        foreach ($gruposRow as $cell) {
            if (stripos($this->cellValue($cell), 'Primeiro Grupo') !== false) {
                $hasPrimeiroGrupo = true;
                break;
            }
        }
        if (!$hasPrimeiroGrupo) {
            $errors[] = 'Linha 1 deve conter "Primeiro Grupo"';
        }

        $categoriasRow = $lines[2];
        $cat0 = trim($this->cellValue($categoriasRow[0] ?? null));
        $cat1 = trim($this->cellValue($categoriasRow[1] ?? null));
        if ($cat0 === '' && $cat1 === '') {
            $errors[] = 'Linha 3 deve conter as categorias de produtos';
        }

        $casosValidos = 0;
        for ($i = 5; $i < count($lines); $i++) {
            $row = $lines[$i];
            $casoClinico = trim($this->cellValue($row[1] ?? null));
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
