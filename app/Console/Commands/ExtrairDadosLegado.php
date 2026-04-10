<?php

namespace App\Console\Commands;

use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoProdutoDescricaoParser;
use Illuminate\Console\Command;

class ExtrairDadosLegado extends Command
{
    protected $signature = 'migration:extrair-legado
                            {--sql=docs/clinicaweb/database/bkp_cw2_20260325.sql : Arquivo SQL dump do ClinicaWeb}
                            {--output=docs/migration : Diretório de saída para JSONs e CSVs}
                            {--sem-csv : Não gerar arquivos CSV (apenas JSON)}
                            {--mapeamento-codigos=docs/sanitization/mapeamento-codigos-legado-base.md : Markdown com tabela legado → base (receitas e preview)}';

    protected $description = 'Extrai dados do dump SQL do ClinicaWeb e gera JSON/CSV para revisão. Gera produtos-import-preview.csv com nome/descricao/modo_uso após LegadoProdutoDescricaoParser (igual importação).';

    private array $dadosBrutos = [];

    /** @var array<string, string> */
    private array $mapeamentoCodigoLegadoBase = [];

    private const ROLE_MAP = [
        'ROLE_ADMIN' => 'admin',
        'ROLE_FREITASADM' => 'admin',
        'ROLE_MEDICO' => 'medico',
        'ROLE_MEDICO_ADMIN' => 'medico',
        'ROLE_SECRETARIA' => 'secretaria',
        'ROLE_SECRETARIA_ADM' => 'secretaria',
        'ROLE_CALLCENTER' => 'callcenter',
    ];

    private const IGNORED_ROLES = [
        'ROLE_USER', 'ROLE_RPT_RECEITA', 'ROLE_ORCA_RECEITA', 'ROLE_SELECT_TABPRECO',
    ];

    private const ROLE_PRIORITY = ['admin', 'medico', 'secretaria', 'callcenter'];

    private const TABELAS_ALVO = [
        'clinica', 'medico', 'user', 'role', 'user_roles', 'user_medicos',
        'paciente', 'receita', 'receita_item', 'receita_itens',
        'receita_item_produtos', 'medico_receitas', 'paciente_receitas',
        'produto',
        'aquisicao', 'aquisicao_produto', 'aquisicao_produtos', 'receita_atend_callcenter',
    ];

    // Column indices based on CREATE TABLE order in the dump
    private const COLS_CLINICA = [
        'id' => 0, 'ativo' => 1, 'bairro' => 2, 'cep' => 3, 'cidade' => 4,
        'complemento' => 5, 'cpf' => 6, 'dta_inclusao' => 7, 'dta_ult_alteracao' => 8,
        'email1' => 9, 'email2' => 10, 'email3' => 11, 'fone1' => 12, 'fone2' => 13,
        'fone3' => 14, 'logradouro' => 15, 'nome' => 16, 'numero' => 17,
        'observacao' => 18, 'rg' => 19, 'tipoLogradouro' => 20, 'trat' => 21,
        'uf' => 22, 'usuario_alteracao' => 23, 'usuario_inclusao' => 24,
        'apelido' => 25, 'clinicaConfFinanceira_id' => 26, 'tabelaPreco_id' => 27,
        'dataNascimento' => 28, 'fototipo' => 29, 'sexo' => 30,
    ];

    private const COLS_MEDICO = [
        'id' => 0, 'ativo' => 1, 'bairro' => 2, 'cep' => 3, 'cidade' => 4,
        'complemento' => 5, 'cpf' => 6, 'dta_inclusao' => 7, 'dta_ult_alteracao' => 8,
        'email1' => 9, 'email2' => 10, 'email3' => 11, 'fone1' => 12, 'fone2' => 13,
        'fone3' => 14, 'logradouro' => 15, 'nome' => 16, 'numero' => 17,
        'observacao' => 18, 'rg' => 19, 'tipoLogradouro' => 20, 'trat' => 21,
        'uf' => 22, 'usuario_alteracao' => 23, 'usuario_inclusao' => 24,
        'cabec1' => 25, 'cabec2' => 26, 'cabec3' => 27,
        'clinica_contato' => 28, 'clinica_endereco' => 29, 'clinica_nome' => 30,
        'crm' => 31, 'crmUF' => 32, 'especialidade' => 33,
        'fileNameCabec' => 34, 'fileNameCarimbo' => 35, 'fileNameRodape' => 36,
        'orientacaoCallCenter' => 37, 'rodape1' => 38, 'rodape2' => 39, 'rodape3' => 40,
        'clinica_id' => 41, 'apelido' => 42, 'tabelaPreco_id' => 43,
        'dataNascimento' => 44, 'fototipo' => 45, 'sexo' => 46,
    ];

    private const COLS_USER = [
        'id' => 0, 'dta_inclusao' => 1, 'dta_ult_alteracao' => 2,
        'email' => 3, 'enabled' => 4, 'nome' => 5, 'password' => 6,
        'username' => 7, 'usuario_alteracao' => 8, 'usuario_inclusao' => 9,
        'clinica_id' => 10,
    ];

    private const COLS_PACIENTE = [
        'id' => 0, 'ativo' => 1, 'bairro' => 2, 'cep' => 3, 'cidade' => 4,
        'complemento' => 5, 'cpf' => 6, 'dta_inclusao' => 7, 'dta_ult_alteracao' => 8,
        'email1' => 9, 'email2' => 10, 'email3' => 11, 'fone1' => 12, 'fone2' => 13,
        'fone3' => 14, 'logradouro' => 15, 'nome' => 16, 'numero' => 17,
        'observacao' => 18, 'rg' => 19, 'tipoLogradouro' => 20, 'trat' => 21,
        'uf' => 22, 'usuario_alteracao' => 23, 'usuario_inclusao' => 24,
        'nr_registro' => 25, 'amiga' => 26, 'vip' => 27, 'medico_id' => 28,
        'apelido' => 29, 'dataNascimento' => 30, 'fototipo' => 31, 'sexo' => 32,
    ];

    private const COLS_RECEITA = [
        'id' => 0, 'ativo' => 1, 'dta_receita' => 2, 'dta_inclusao' => 3,
        'dta_ult_alteracao' => 4, 'receita_numero' => 5, 'observacao' => 6,
        'ultimoAtendimentoId' => 7, 'usuario_alteracao' => 8, 'usuario_inclusao' => 9,
        'justif_desc' => 10, 'motivo_desc' => 11, 'pct_desc' => 12, 'pct_desc_adm' => 13,
        'vlr_subtotal' => 14, 'vlr_desc' => 15, 'vlr_frete' => 16,
        'vlr_frete_embalagem' => 17, 'vlr_frete_total' => 18, 'vlr_total' => 19,
    ];

    private const COLS_RECEITA_ITEM = [
        'id' => 0, 'anotacoes' => 1, 'ativo' => 2, 'dta_inclusao' => 3,
        'dta_ult_alteracao' => 4, 'dta_ult_aquisicao' => 5, 'imprime' => 6,
        'local_uso' => 7, 'quant' => 8, 'usuario_alteracao' => 9,
        'usuario_inclusao' => 10, 'vlr_total' => 11, 'vlr_unit' => 12, 'vlr_custo' => 13,
    ];

    private const COLS_PRODUTO = [
        'id' => 0, 'ativo' => 1, 'codigo' => 2, 'codigoCQ' => 3,
        'dta_inclusao' => 4, 'dta_ult_alteracao' => 5, 'descricao' => 6, 'sequencia' => 7,
        'usuario_alteracao' => 8, 'usuario_inclusao' => 9, 'nomeGenerico' => 10,
        'valor1' => 11, 'valor2' => 12, 'descr_orca' => 13, 'est_max' => 14, 'est_min' => 15,
        'grupo' => 16, 'peso' => 17, 'subgrupo' => 18, 'tipoUnidadeMedida_id' => 19,
    ];

    private const COLS_ROLE = [
        'id' => 0, 'descricao' => 1, 'role_name' => 2,
    ];

    private const COLS_AQUISICAO = [
        'id' => 0, 'ativo' => 1, 'dta_inclusao' => 2, 'dta_ult_alteracao' => 3,
        'motivo_desc' => 4, 'pct_desc' => 5, 'pct_desc_adm' => 6, 'vlr_subtotal' => 7,
        'usuario_alteracao' => 8, 'usuario_inclusao' => 9, 'vlr_desc' => 10,
        'vlr_frete' => 11, 'vlr_frete_embalagem' => 12, 'vlr_frete_total' => 13,
        'vlr_total' => 14, 'atendimento_id' => 15, 'tabelaPreco_id' => 16,
    ];

    private const COLS_AQUISICAO_PRODUTO = [
        'id' => 0, 'ativo' => 1, 'descricao' => 2, 'local_uso' => 3, 'quant' => 4,
        'produto_id' => 5, 'dta_aquisicao' => 6, 'dta_receita' => 7, 'pct_desc' => 8,
        'sub_total' => 9, 'vlr_desc' => 10, 'vlr_total' => 11, 'vlr_unit' => 12,
        'vlr_subtotal' => 13, 'vlr_custo' => 14, 'vlr_repasse' => 15,
        'vlr_repa_farmacia' => 16, 'vlr_venda' => 17,
    ];

    public function handle(): int
    {
        @ini_set('memory_limit', '512M');

        $sqlPath = base_path($this->option('sql'));
        $outputDir = base_path($this->option('output'));

        if (! file_exists($sqlPath)) {
            $this->error("Arquivo não encontrado: {$sqlPath}");

            return 1;
        }

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $mapPath = base_path($this->option('mapeamento-codigos'));
        $this->mapeamentoCodigoLegadoBase = LegadoCodigoProdutoMapeamento::fromMarkdownFile($mapPath);
        if ($this->mapeamentoCodigoLegadoBase !== []) {
            $this->line('Mapeamento legado→base: '.\count($this->mapeamentoCodigoLegadoBase).' códigos ('.basename($mapPath).')');
        } elseif (is_file($mapPath)) {
            $this->warn('Mapeamento: ficheiro encontrado mas nenhuma linha de tabela válida: '.$mapPath);
        }

        $this->info('=== Extração de Dados Legado ClinicaWeb ===');
        $this->newLine();

        $this->info('1/8 Parsing SQL dump...');
        $this->parseSqlDump($sqlPath);
        $this->printTableCounts();

        $this->info('2/8 Extraindo clínicas...');
        $clinicas = $this->extrairClinicas();
        $this->line("   {$this->count($clinicas)} clínicas extraídas");

        $this->info('3/8 Extraindo médicos...');
        $medicos = $this->extrairMedicos();
        $this->line("   {$this->count($medicos)} médicos extraídos");

        $this->info('4/8 Extraindo usuários...');
        $users = $this->extrairUsers($medicos);
        $this->line("   {$this->count($users)} usuários extraídos");

        $this->info('5/8 Extraindo pacientes...');
        $pacientes = $this->extrairPacientes();
        $this->line("   {$this->count($pacientes)} pacientes extraídos");

        $this->info('6/8 Extraindo receitas...');
        $receitas = $this->extrairReceitas();
        $this->line("   {$this->count($receitas)} receitas extraídas");

        $this->info('7/8 Extraindo produtos (nomeGenerico → anotacoes_internas)...');
        $produtos = $this->extrairProdutos();
        $this->line("   {$this->count($produtos)} produtos extraídos");

        $this->info('8/8 Extraindo histórico de aquisições (aquisicao* + receita_atend_callcenter)...');
        $itemAquisicoesLegado = $this->extrairItemAquisicoesLegado();
        $this->line('   '.$this->count($itemAquisicoesLegado).' linhas (dedup por item legado + data)');

        // Liberta memória do dump bruto antes de json_encode (receitas é grande)
        $this->dadosBrutos = [];
        gc_collect_cycles();

        $this->newLine();
        $this->info('Escrevendo arquivos JSON...');
        $this->escreverJson($outputDir, 'clinicas', $clinicas);
        $this->escreverJson($outputDir, 'medicos', $medicos);
        $this->escreverJson($outputDir, 'users', $users);
        $this->escreverJson($outputDir, 'pacientes', $pacientes);
        $this->escreverJson($outputDir, 'receitas', $receitas);
        $this->escreverJson($outputDir, 'produtos', $produtos);
        $this->escreverJson($outputDir, 'itemAquisicoesLegado', $itemAquisicoesLegado);

        $resumo = $this->gerarResumo($clinicas, $medicos, $users, $pacientes, $receitas, $produtos, $itemAquisicoesLegado);
        $this->escreverJson($outputDir, 'resumo-extracao', $resumo);

        if (! $this->option('sem-csv')) {
            $this->newLine();
            $this->info('Escrevendo arquivos CSV (revisão)...');
            $this->escreverCsvsMigracao($outputDir, $clinicas, $medicos, $users, $pacientes, $receitas, $produtos, $itemAquisicoesLegado, $this->mapeamentoCodigoLegadoBase);
        }

        $this->newLine();
        $this->info("Arquivos gerados em: {$outputDir}/");
        $this->info('Revise os JSONs'.($this->option('sem-csv') ? '' : ' e CSVs').' antes de rodar: php artisan migration:importar-legado');

        return 0;
    }

    // ─── SQL DUMP PARSER ───

    private function parseSqlDump(string $path): void
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            $this->error("Não foi possível abrir: {$path}");

            return;
        }

        $buffer = '';
        $currentTable = null;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, 'LOCK') || str_starts_with($trimmed, 'UNLOCK') || str_starts_with($trimmed, '/*!')) {
                continue;
            }

            if (preg_match('/^INSERT INTO `(\w+)` VALUES\s*/i', $trimmed, $matches)) {
                $tableName = $matches[1];
                if (in_array($tableName, self::TABELAS_ALVO)) {
                    $currentTable = $tableName;
                    $buffer = $trimmed;
                } else {
                    $currentTable = null;

                    continue;
                }
            } elseif ($currentTable !== null) {
                $buffer .= ' '.$trimmed;
            }

            if ($currentTable !== null && str_ends_with($trimmed, ';')) {
                if (! isset($this->dadosBrutos[$currentTable])) {
                    $this->dadosBrutos[$currentTable] = [];
                }

                $rows = $this->parseInsertValues($buffer);
                $this->dadosBrutos[$currentTable] = array_merge(
                    $this->dadosBrutos[$currentTable],
                    $rows
                );

                $currentTable = null;
                $buffer = '';
            }
        }

        fclose($handle);
    }

    private function parseInsertValues(string $sql): array
    {
        $pos = stripos($sql, 'VALUES');
        if ($pos === false) {
            return [];
        }

        $valuesPart = substr($sql, $pos + 6);
        $valuesPart = rtrim($valuesPart, '; ');
        $valuesPart = trim($valuesPart);

        $rows = [];
        $i = 0;
        $len = strlen($valuesPart);

        while ($i < $len) {
            if ($valuesPart[$i] === '(') {
                $tuple = $this->parseTuple($valuesPart, $i);
                $rows[] = $tuple['values'];
                $i = $tuple['end'] + 1;

                while ($i < $len && ($valuesPart[$i] === ',' || $valuesPart[$i] === ' ')) {
                    $i++;
                }
            } else {
                $i++;
            }
        }

        return $rows;
    }

    private function parseTuple(string $str, int $start): array
    {
        $values = [];
        $i = $start + 1;
        $len = strlen($str);

        while ($i < $len && $str[$i] !== ')') {
            while ($i < $len && $str[$i] === ' ') {
                $i++;
            }
            if ($i >= $len || $str[$i] === ')') {
                break;
            }

            $result = $this->parseValue($str, $i);
            $values[] = $result['value'];
            $i = $result['end'];

            while ($i < $len && ($str[$i] === ',' || $str[$i] === ' ')) {
                $i++;
            }
        }

        return ['values' => $values, 'end' => $i];
    }

    private function parseValue(string $str, int $pos): array
    {
        $len = strlen($str);

        if (substr($str, $pos, 4) === 'NULL') {
            return ['value' => null, 'end' => $pos + 4];
        }

        if (substr($str, $pos, 7) === '_binary') {
            $pos += 7;
            while ($pos < $len && $str[$pos] === ' ') {
                $pos++;
            }
            $result = $this->parseQuotedString($str, $pos);
            $isFalse = ($result['value'] === "\0" || $result['value'] === '\\0' || $result['value'] === '');

            return ['value' => ! $isFalse, 'end' => $result['end']];
        }

        if ($str[$pos] === '\'') {
            return $this->parseQuotedString($str, $pos);
        }

        $end = $pos;
        while ($end < $len && $str[$end] !== ',' && $str[$end] !== ')') {
            $end++;
        }
        $value = trim(substr($str, $pos, $end - $pos));

        if (is_numeric($value)) {
            $value = str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return ['value' => $value, 'end' => $end];
    }

    private function parseQuotedString(string $str, int $pos): array
    {
        $result = '';
        $pos++;
        $len = strlen($str);

        while ($pos < $len) {
            if ($str[$pos] === '\\') {
                $pos++;
                if ($pos < $len) {
                    $result .= match ($str[$pos]) {
                        '0' => "\0",
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '\\' => '\\',
                        '\'' => "'",
                        '"' => '"',
                        default => $str[$pos],
                    };
                }
            } elseif ($str[$pos] === '\'') {
                if ($pos + 1 < $len && $str[$pos + 1] === '\'') {
                    $result .= "'";
                    $pos++;
                } else {
                    return ['value' => $result, 'end' => $pos + 1];
                }
            } else {
                $result .= $str[$pos];
            }
            $pos++;
        }

        return ['value' => $result, 'end' => $pos];
    }

    // ─── HELPERS ───

    private function col(array $row, array $cols, string $name): mixed
    {
        $idx = $cols[$name] ?? null;
        if ($idx === null || ! isset($row[$idx])) {
            return null;
        }

        return $row[$idx];
    }

    private function colStr(array $row, array $cols, string $name): ?string
    {
        $val = $this->col($row, $cols, $name);
        if ($val === null || $val === '') {
            return null;
        }

        return trim((string) $val);
    }

    private function count(array $arr): int
    {
        return \count($arr);
    }

    private function normalizarData(?string $data): ?string
    {
        if (! $data || trim($data) === '') {
            return null;
        }
        $data = trim($data);

        // Already ISO format (yyyy-mm-dd or yyyy-mm-dd HH:mm:ss)
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            return substr($data, 0, 10);
        }

        // dd/mm/yyyy
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $data, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // dd/mm/yy
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2})$#', $data, $m)) {
            $year = (int) $m[3];
            $year = $year > 50 ? 1900 + $year : 2000 + $year;

            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function classificarTelefone(string $numero): string
    {
        $limpo = preg_replace('/[\s\-\(\)\+\.]/', '', $numero);
        if (str_starts_with($limpo, '55') && strlen($limpo) > 11) {
            $limpo = substr($limpo, 2);
        }
        if (strlen($limpo) > 9) {
            $limpo = substr($limpo, strlen($limpo) - 9 > 0 ? strlen($limpo) - 9 : 0);
            if (strlen($limpo) > 9) {
                $limpo = substr($limpo, -9);
            }
        }

        return (strlen($limpo) >= 9 && str_starts_with($limpo, '9')) ? 'celular' : 'fixo';
    }

    private function classificarTelefones(array $fones): array
    {
        $celulares = [];
        $fixos = [];

        foreach ($fones as $fone) {
            if ($fone === null || trim($fone) === '') {
                continue;
            }
            $fone = trim($fone);
            if ($this->classificarTelefone($fone) === 'celular') {
                $celulares[] = $fone;
            } else {
                $fixos[] = $fone;
            }
        }

        return ['celulares' => $celulares, 'fixos' => $fixos];
    }

    private function buildIndex(string $table, int $keyCol, ?int $valCol = null): array
    {
        $index = [];
        foreach ($this->dadosBrutos[$table] ?? [] as $row) {
            $key = $row[$keyCol] ?? null;
            if ($key === null) {
                continue;
            }

            if ($valCol !== null) {
                $index[$key] = $row[$valCol];
            } else {
                if (! isset($index[$key])) {
                    $index[$key] = [];
                }
                $index[$key][] = $row;
            }
        }

        return $index;
    }

    private function buildGroupIndex(string $table, int $keyCol, int $valCol): array
    {
        $index = [];
        foreach ($this->dadosBrutos[$table] ?? [] as $row) {
            $key = $row[$keyCol] ?? null;
            $val = $row[$valCol] ?? null;
            if ($key === null || $val === null) {
                continue;
            }
            $index[$key][] = $val;
        }

        return $index;
    }

    private function printTableCounts(): void
    {
        foreach (self::TABELAS_ALVO as $table) {
            $count = \count($this->dadosBrutos[$table] ?? []);
            $this->line("   {$table}: {$count} registros");
        }
        $this->newLine();
    }

    // ─── ENTITY EXTRACTION ───

    private function extrairClinicas(): array
    {
        $clinicas = [];
        foreach ($this->dadosBrutos['clinica'] ?? [] as $row) {
            $c = self::COLS_CLINICA;
            $nome = $this->colStr($row, $c, 'nome');
            if (! $nome) {
                continue;
            }

            $fones = $this->classificarTelefones([
                $this->colStr($row, $c, 'fone1'),
                $this->colStr($row, $c, 'fone2'),
                $this->colStr($row, $c, 'fone3'),
            ]);

            $clinicas[] = [
                'legado_id' => $this->col($row, $c, 'id'),
                'nome' => $nome,
                'cnpj' => $this->colStr($row, $c, 'cpf'),
                'telefone1' => $fones['fixos'][0] ?? $fones['celulares'][1] ?? null,
                'telefone2' => $fones['fixos'][1] ?? null,
                'telefone3' => $fones['fixos'][2] ?? null,
                'celular' => $fones['celulares'][0] ?? null,
                'email' => $this->colStr($row, $c, 'email1'),
                'endereco' => $this->colStr($row, $c, 'logradouro'),
                'numero' => $this->colStr($row, $c, 'numero'),
                'complemento' => $this->colStr($row, $c, 'complemento'),
                'bairro' => $this->colStr($row, $c, 'bairro'),
                'cidade' => $this->colStr($row, $c, 'cidade'),
                'uf' => $this->colStr($row, $c, 'uf'),
                'cep' => $this->colStr($row, $c, 'cep'),
                'anotacoes' => $this->colStr($row, $c, 'observacao'),
                'ativo' => (bool) $this->col($row, $c, 'ativo'),
            ];
        }

        return $clinicas;
    }

    private function extrairMedicos(): array
    {
        $medicos = [];
        foreach ($this->dadosBrutos['medico'] ?? [] as $row) {
            $c = self::COLS_MEDICO;

            $fones = $this->classificarTelefones([
                $this->colStr($row, $c, 'fone1'),
                $this->colStr($row, $c, 'fone2'),
                $this->colStr($row, $c, 'fone3'),
            ]);

            $enderecoPrincipal = [
                'nome' => $this->colStr($row, $c, 'apelido') ?? $this->colStr($row, $c, 'nome'),
                'endereco' => $this->colStr($row, $c, 'logradouro'),
                'numero' => $this->colStr($row, $c, 'numero'),
                'complemento' => $this->colStr($row, $c, 'complemento'),
                'bairro' => $this->colStr($row, $c, 'bairro'),
                'cidade' => $this->colStr($row, $c, 'cidade'),
                'uf' => $this->colStr($row, $c, 'uf'),
                'cep' => $this->colStr($row, $c, 'cep'),
                'principal' => true,
            ];
            $temEnderecoPrincipal = ! empty($enderecoPrincipal['endereco']) || ! empty($enderecoPrincipal['cidade']);

            $enderecoClinica = null;
            $clinicaNome = $this->colStr($row, $c, 'clinica_nome');
            $clinicaEndereco = $this->colStr($row, $c, 'clinica_endereco');
            if ($clinicaNome || $clinicaEndereco) {
                $enderecoClinica = [
                    'nome' => $clinicaNome,
                    'endereco' => $clinicaEndereco,
                    'numero' => null,
                    'complemento' => null,
                    'bairro' => null,
                    'cidade' => null,
                    'uf' => null,
                    'cep' => null,
                    'principal' => ! $temEnderecoPrincipal,
                ];
            }

            $enderecos = [];
            if ($temEnderecoPrincipal) {
                $enderecos[] = $enderecoPrincipal;
            }
            if ($enderecoClinica) {
                $enderecos[] = $enderecoClinica;
            }

            $rodape = implode("\n", array_filter([
                $this->colStr($row, $c, 'rodape1'),
                $this->colStr($row, $c, 'rodape2'),
                $this->colStr($row, $c, 'rodape3'),
            ])) ?: null;

            $medicos[] = [
                'legado_id' => $this->col($row, $c, 'id'),
                'nome_legado' => $this->colStr($row, $c, 'nome'),
                'apelido' => $this->colStr($row, $c, 'apelido'),
                'crm' => $this->colStr($row, $c, 'crm'),
                'uf_crm' => $this->colStr($row, $c, 'crmUF'),
                'cpf' => $this->colStr($row, $c, 'cpf'),
                'rg' => $this->colStr($row, $c, 'rg'),
                'especialidade' => $this->colStr($row, $c, 'especialidade'),
                'legado_clinica_id' => $this->col($row, $c, 'clinica_id'),
                'telefone1' => $fones['fixos'][0] ?? null,
                'telefone2' => $fones['fixos'][1] ?? null,
                'telefone3' => $fones['fixos'][2] ?? null,
                'celular' => $fones['celulares'][0] ?? null,
                'telefones_adicionais' => array_merge(
                    array_map(fn ($f) => ['numero' => $f, 'tipo' => 'Celular'], array_slice($fones['celulares'], 1)),
                    array_map(fn ($f) => ['numero' => $f, 'tipo' => 'Residencial'], array_slice($fones['fixos'], 3)),
                ),
                'email1' => $this->colStr($row, $c, 'email1'),
                'email2' => $this->colStr($row, $c, 'email2'),
                'endereco' => $this->colStr($row, $c, 'logradouro'),
                'numero' => $this->colStr($row, $c, 'numero'),
                'complemento' => $this->colStr($row, $c, 'complemento'),
                'bairro' => $this->colStr($row, $c, 'bairro'),
                'cidade' => $this->colStr($row, $c, 'cidade'),
                'uf' => $this->colStr($row, $c, 'uf'),
                'cep' => $this->colStr($row, $c, 'cep'),
                'enderecos' => $enderecos,
                'rodape_receita' => $rodape,
                'anotacoes' => $this->colStr($row, $c, 'observacao'),
                'ativo' => (bool) $this->col($row, $c, 'ativo'),
            ];
        }

        return $medicos;
    }

    /**
     * @param  array<int, array<string, mixed>>  $medicos  saída de extrairMedicos() para nome canónico por legado_id
     */
    private function extrairUsers(array $medicos = []): array
    {
        $nomeMedicoPorLegadoId = [];
        foreach ($medicos as $m) {
            $lid = $m['legado_id'] ?? null;
            if ($lid === null || $lid === '') {
                continue;
            }
            $nomeMedicoPorLegadoId[(int) $lid] = $m['nome_legado'] ?? '';
        }

        // Build lookup indices
        $rolesById = [];
        foreach ($this->dadosBrutos['role'] ?? [] as $row) {
            $rolesById[$row[self::COLS_ROLE['id']]] = $row[self::COLS_ROLE['role_name']];
        }

        // user_roles: role_id(0), user_id(1)
        $userRoles = $this->buildGroupIndex('user_roles', 1, 0);

        // user_medicos: user_id(0), medico_id(1)
        $userMedicos = $this->buildGroupIndex('user_medicos', 0, 1);

        $users = [];
        foreach ($this->dadosBrutos['user'] ?? [] as $row) {
            $c = self::COLS_USER;
            $userId = $this->col($row, $c, 'id');

            $roleIds = $userRoles[$userId] ?? [];
            $roleNames = array_map(fn ($rid) => $rolesById[$rid] ?? "UNKNOWN_{$rid}", $roleIds);

            // Significant roles = excluding base/permission-only roles
            $significantRoles = array_values(array_filter(
                $roleNames,
                fn ($r) => ! in_array($r, self::IGNORED_ROLES)
            ));

            // Skip Call Center-only users (significant roles are only ROLE_CALLCENTER)
            $nonCallCenter = array_filter($significantRoles, fn ($r) => $r !== 'ROLE_CALLCENTER');
            if (! empty($significantRoles) && empty($nonCallCenter)) {
                continue;
            }

            $newRole = $this->mapearRole($roleNames);

            $medicoIds = $userMedicos[$userId] ?? [];
            $email = $this->colStr($row, $c, 'email');
            $enabled = $this->col($row, $c, 'enabled');

            $avisos = [];
            if (! $email) {
                $avisos[] = 'Sem email - não poderá recuperar senha via link';
            }
            if ($enabled === false) {
                $avisos[] = 'Usuário desativado no sistema antigo';
            }

            $nomeUser = $this->colStr($row, $c, 'nome');
            if ($newRole === 'medico' && \count($medicoIds) === 1) {
                $mid = (int) $medicoIds[0];
                $nomeMedico = $nomeMedicoPorLegadoId[$mid] ?? '';
                if ($nomeMedico !== '' && $nomeMedico !== $nomeUser) {
                    $avisos[] = 'Nome no user legado ('.$nomeUser.') substituído pelo cadastro de médico ('.$nomeMedico.').';
                    $nomeUser = $nomeMedico;
                } elseif ($nomeMedico !== '') {
                    $nomeUser = $nomeMedico;
                }
            }

            $users[] = [
                'legado_id' => $userId,
                'nome' => $nomeUser,
                'email' => $email,
                'username' => $this->colStr($row, $c, 'username'),
                'role' => $newRole,
                'legado_roles' => $roleNames,
                'legado_medico_ids' => $medicoIds,
                'legado_clinica_id' => $this->col($row, $c, 'clinica_id'),
                'is_active' => $enabled !== false,
                'avisos' => $avisos,
            ];
        }

        return $users;
    }

    private function mapearRole(array $roleNames): string
    {
        $mappedRoles = [];
        foreach ($roleNames as $rn) {
            if (in_array($rn, self::IGNORED_ROLES)) {
                continue;
            }
            $mapped = self::ROLE_MAP[$rn] ?? null;
            if ($mapped && $mapped !== 'callcenter') {
                $mappedRoles[] = $mapped;
            }
        }

        $mappedRoles = array_unique($mappedRoles);
        foreach (self::ROLE_PRIORITY as $priority) {
            if (in_array($priority, $mappedRoles)) {
                return $priority;
            }
        }

        return 'admin';
    }

    private function extrairPacientes(): array
    {
        $pacientes = [];
        foreach ($this->dadosBrutos['paciente'] ?? [] as $row) {
            $c = self::COLS_PACIENTE;

            $fones = $this->classificarTelefones([
                $this->colStr($row, $c, 'fone1'),
                $this->colStr($row, $c, 'fone2'),
                $this->colStr($row, $c, 'fone3'),
            ]);

            $celular = $fones['celulares'][0] ?? null;
            $telefone1 = $fones['fixos'][0] ?? null;

            $telefonesAdicionais = [];
            foreach (array_slice($fones['celulares'], 1) as $f) {
                $telefonesAdicionais[] = ['numero' => $f, 'tipo' => 'Celular'];
            }
            foreach (array_slice($fones['fixos'], 1) as $f) {
                $telefonesAdicionais[] = ['numero' => $f, 'tipo' => 'Residencial'];
            }

            $anotacoes = [];
            $obs = $this->colStr($row, $c, 'observacao');
            $amiga = $this->colStr($row, $c, 'amiga');
            $vip = $this->colStr($row, $c, 'vip');
            $email3 = $this->colStr($row, $c, 'email3');
            $apelido = $this->colStr($row, $c, 'apelido');
            $nome = $this->colStr($row, $c, 'nome');

            if ($obs) {
                $anotacoes[] = $obs;
            }
            // Indicação vai para indicado_por no JSON; não duplicar em anotacoes
            if ($vip && strtolower($vip) !== 'n' && $vip !== '0') {
                $anotacoes[] = "VIP: {$vip}";
            }
            if ($email3) {
                $anotacoes[] = "Email adicional: {$email3}";
            }
            if ($apelido && $apelido !== $nome) {
                $anotacoes[] = "Apelido: {$apelido}";
            }

            $pacientes[] = [
                'legado_id' => $this->col($row, $c, 'id'),
                'codigo' => $this->colStr($row, $c, 'nr_registro'),
                'indicado_por' => $amiga !== '' ? $amiga : null,
                'nome' => $nome,
                'data_nascimento' => $this->normalizarData($this->colStr($row, $c, 'dataNascimento')),
                'sexo' => $this->colStr($row, $c, 'sexo'),
                'fototipo' => $this->colStr($row, $c, 'fototipo'),
                'cpf' => $this->colStr($row, $c, 'cpf'),
                'rg' => $this->colStr($row, $c, 'rg'),
                'celular' => $celular,
                'telefone1' => $telefone1,
                'telefones_adicionais' => $telefonesAdicionais,
                'email1' => $this->colStr($row, $c, 'email1'),
                'email2' => $this->colStr($row, $c, 'email2'),
                'tipo_endereco' => $this->colStr($row, $c, 'tipoLogradouro'),
                'endereco' => $this->colStr($row, $c, 'logradouro'),
                'numero' => $this->colStr($row, $c, 'numero'),
                'complemento' => $this->colStr($row, $c, 'complemento'),
                'bairro' => $this->colStr($row, $c, 'bairro'),
                'cidade' => $this->colStr($row, $c, 'cidade'),
                'uf' => $this->colStr($row, $c, 'uf'),
                'cep' => $this->colStr($row, $c, 'cep'),
                'legado_medico_id' => $this->col($row, $c, 'medico_id'),
                'anotacoes' => implode("\n", $anotacoes) ?: null,
                'ativo' => (bool) $this->col($row, $c, 'ativo'),
            ];
        }

        return $pacientes;
    }

    private function extrairReceitas(): array
    {
        // Build lookups
        // medico_receitas: medico_id(0), receita_id(1)
        $medicoByReceita = [];
        foreach ($this->dadosBrutos['medico_receitas'] ?? [] as $row) {
            $medicoByReceita[$row[1]] = $row[0];
        }

        // paciente_receitas: paciente_id(0), receita_id(1)
        $pacienteByReceita = [];
        foreach ($this->dadosBrutos['paciente_receitas'] ?? [] as $row) {
            $pacienteByReceita[$row[1]] = $row[0];
        }

        // receita_itens: receita_id(0), receita_item_id(1)
        $itensByReceita = $this->buildGroupIndex('receita_itens', 0, 1);

        // receita_item indexed by id
        $itensById = [];
        foreach ($this->dadosBrutos['receita_item'] ?? [] as $row) {
            $itensById[$row[self::COLS_RECEITA_ITEM['id']]] = $row;
        }

        // receita_item_produtos: produto_id(0), receita_item_id(1)
        $produtoByItem = [];
        foreach ($this->dadosBrutos['receita_item_produtos'] ?? [] as $row) {
            $produtoByItem[$row[1]] = $row[0];
        }

        // produto indexed by id (we only need codigo)
        $produtosById = [];
        foreach ($this->dadosBrutos['produto'] ?? [] as $row) {
            $produtosById[$row[self::COLS_PRODUTO['id']]] = $row[self::COLS_PRODUTO['codigo']];
        }

        $avisosProdutos = [];
        $receitas = [];

        foreach ($this->dadosBrutos['receita'] ?? [] as $row) {
            $c = self::COLS_RECEITA;
            $receitaId = $this->col($row, $c, 'id');
            $ativo = $this->col($row, $c, 'ativo');

            $itemIds = $itensByReceita[$receitaId] ?? [];
            $itensExtraidos = [];
            $ordem = 0;

            foreach ($itemIds as $itemId) {
                $itemRow = $itensById[$itemId] ?? null;
                if (! $itemRow) {
                    continue;
                }

                $ic = self::COLS_RECEITA_ITEM;
                $produtoIdLegado = $produtoByItem[$itemId] ?? null;
                $codigoLegado = $produtoIdLegado ? ($produtosById[$produtoIdLegado] ?? null) : null;
                $codigoMapeado = $codigoLegado
                    ? LegadoCodigoProdutoMapeamento::paraBase($codigoLegado, $this->mapeamentoCodigoLegadoBase)
                    : null;

                if ($produtoIdLegado && ! $codigoLegado) {
                    $avisosProdutos[] = "Receita {$receitaId}, item {$itemId}: produto legado ID {$produtoIdLegado} sem código";
                }

                $ordem++;
                $itensExtraidos[] = [
                    'legado_id' => $this->col($itemRow, $ic, 'id'),
                    'legado_produto_id' => $produtoIdLegado,
                    'codigo_produto_legado' => $codigoLegado,
                    'codigo_produto_mapeado' => $codigoMapeado,
                    'local_uso' => $this->colStr($itemRow, $ic, 'local_uso'),
                    'anotacoes' => $this->colStr($itemRow, $ic, 'anotacoes'),
                    'quantidade' => $this->col($itemRow, $ic, 'quant') ?? 1,
                    'valor_unitario' => $this->col($itemRow, $ic, 'vlr_unit'),
                    'valor_total' => $this->col($itemRow, $ic, 'vlr_total'),
                    'data_aquisicao' => $this->colStr($itemRow, $ic, 'dta_ult_aquisicao'),
                    'imprimir' => (bool) $this->col($itemRow, $ic, 'imprime'),
                    'ordem' => $ordem,
                ];
            }

            $receitas[] = [
                'legado_id' => $receitaId,
                'numero_legado' => $this->colStr($row, $c, 'receita_numero'),
                'data_receita' => $this->colStr($row, $c, 'dta_receita'),
                'legado_paciente_id' => $pacienteByReceita[$receitaId] ?? null,
                'legado_medico_id' => $medicoByReceita[$receitaId] ?? null,
                'anotacoes' => $this->colStr($row, $c, 'observacao'),
                'subtotal' => $this->col($row, $c, 'vlr_subtotal'),
                'desconto_percentual' => $this->col($row, $c, 'pct_desc'),
                'desconto_valor' => $this->col($row, $c, 'vlr_desc'),
                'desconto_motivo' => $this->colStr($row, $c, 'motivo_desc'),
                'valor_frete' => $this->col($row, $c, 'vlr_frete_total'),
                'valor_total' => $this->col($row, $c, 'vlr_total'),
                'status' => $ativo ? 'finalizada' : 'cancelada',
                'itens' => $itensExtraidos,
            ];
        }

        if (! empty($avisosProdutos)) {
            $this->warn('Avisos de produtos em receitas:');
            foreach (array_slice($avisosProdutos, 0, 10) as $a) {
                $this->line("   - {$a}");
            }
            if (\count($avisosProdutos) > 10) {
                $this->line('   ... e mais '.(\count($avisosProdutos) - 10));
            }
        }

        return $receitas;
    }

    /**
     * Liga aquisicao (atendimento CC) → receita_atend_callcenter → receita → receita_item pelo produto legado.
     * Não migra atendimentos CC; usa só a tabela de junção do dump.
     *
     * @return array<int, array{legado_receita_item_id: int, legado_aquisicao_produto_id: int, legado_aquisicao_id: int, data_aquisicao: string}>
     */
    private function extrairItemAquisicoesLegado(): array
    {
        $aquisicoesById = [];
        foreach ($this->dadosBrutos['aquisicao'] ?? [] as $row) {
            $id = $this->col($row, self::COLS_AQUISICAO, 'id');
            if ($id !== null && $id !== '') {
                $aquisicoesById[$id] = $row;
            }
        }

        $receitasPorAtendimento = [];
        foreach ($this->dadosBrutos['receita_atend_callcenter'] ?? [] as $row) {
            $recId = $row[0] ?? null;
            $atendId = $row[1] ?? null;
            if ($recId === null || $atendId === null) {
                continue;
            }
            $receitasPorAtendimento[$atendId][] = $recId;
        }

        $itensByReceita = $this->buildGroupIndex('receita_itens', 0, 1);

        $itensById = [];
        foreach ($this->dadosBrutos['receita_item'] ?? [] as $row) {
            $iid = $row[self::COLS_RECEITA_ITEM['id']] ?? null;
            if ($iid !== null && $iid !== '') {
                $itensById[$iid] = $row;
            }
        }

        $produtoByItem = [];
        foreach ($this->dadosBrutos['receita_item_produtos'] ?? [] as $row) {
            $produtoByItem[$row[1]] = $row[0];
        }

        $dtaReceitaByReceita = [];
        $cRec = self::COLS_RECEITA;
        foreach ($this->dadosBrutos['receita'] ?? [] as $row) {
            $rid = $this->col($row, $cRec, 'id');
            if ($rid === null || $rid === '') {
                continue;
            }
            $dtaReceitaByReceita[$rid] = $this->normalizarData($this->colStr($row, $cRec, 'dta_receita'));
        }

        $aquisicaoProdutoById = [];
        foreach ($this->dadosBrutos['aquisicao_produto'] ?? [] as $row) {
            $apid = $this->col($row, self::COLS_AQUISICAO_PRODUTO, 'id');
            if ($apid !== null && $apid !== '') {
                $aquisicaoProdutoById[$apid] = $row;
            }
        }

        $out = [];
        $skippedSemAtendimento = 0;
        $skippedSemReceita = 0;
        $skippedSemItem = 0;
        $skippedSemData = 0;
        $skippedNoLegacyLineMatch = 0;
        $skippedAmbiguousMultiLine = 0;

        $ap = self::COLS_AQUISICAO_PRODUTO;
        $aq = self::COLS_AQUISICAO;
        $ic = self::COLS_RECEITA_ITEM;

        foreach ($this->dadosBrutos['aquisicao_produtos'] ?? [] as $link) {
            $aquisicaoId = $link[0] ?? null;
            $apId = $link[1] ?? null;
            if ($aquisicaoId === null || $apId === null) {
                continue;
            }

            $aqRow = $aquisicoesById[$aquisicaoId] ?? null;
            $apRow = $aquisicaoProdutoById[$apId] ?? null;
            if (! $aqRow || ! $apRow) {
                continue;
            }

            $atendimentoId = $this->col($aqRow, $aq, 'atendimento_id');
            if ($atendimentoId === null || $atendimentoId === '') {
                $skippedSemAtendimento++;

                continue;
            }

            $produtoIdLegado = $this->col($apRow, $ap, 'produto_id');
            if ($produtoIdLegado === null || $produtoIdLegado === '') {
                continue;
            }

            $dtaAquisicao = $this->normalizarData($this->colStr($apRow, $ap, 'dta_aquisicao'));
            $dtaReceitaAp = $this->normalizarData($this->colStr($apRow, $ap, 'dta_receita'));
            $dtaInclusaoAq = $this->normalizarData($this->colStr($aqRow, $aq, 'dta_inclusao'));
            $dataFinal = $dtaAquisicao ?? $dtaReceitaAp ?? $dtaInclusaoAq;
            if (! $dataFinal) {
                $skippedSemData++;

                continue;
            }

            $candidatas = $receitasPorAtendimento[$atendimentoId] ?? [];
            if ($candidatas === []) {
                $skippedSemReceita++;

                continue;
            }

            $comProduto = [];
            foreach ($candidatas as $recId) {
                foreach ($itensByReceita[$recId] ?? [] as $itemId) {
                    $pLeg = $produtoByItem[$itemId] ?? null;
                    if ($pLeg !== null && $pLeg !== '' && (string) $pLeg === (string) $produtoIdLegado) {
                        $comProduto[] = ['receita_id' => $recId, 'item_id' => $itemId];
                    }
                }
            }

            if ($comProduto === []) {
                $skippedSemItem++;

                continue;
            }

            usort($comProduto, fn ($a, $b) => [$a['receita_id'], $a['item_id']] <=> [$b['receita_id'], $b['item_id']]);

            $filtradas = $comProduto;
            if ($dtaReceitaAp !== null) {
                $filtradas = array_values(array_filter(
                    $comProduto,
                    fn ($c) => ($dtaReceitaByReceita[$c['receita_id']] ?? null) === $dtaReceitaAp
                ));
                if ($filtradas === []) {
                    $filtradas = $comProduto;
                }
            }

            $matches = [];
            foreach ($filtradas as $cand) {
                $itemRow = $itensById[$cand['item_id']] ?? null;
                if (! $itemRow) {
                    continue;
                }
                $dtaUlt = $this->normalizarData($this->colStr($itemRow, $ic, 'dta_ult_aquisicao'));
                if ($dtaUlt === $dataFinal) {
                    $matches[] = $cand;
                }
            }

            if (\count($matches) === 0) {
                $skippedNoLegacyLineMatch++;

                continue;
            }

            if (\count($matches) > 1) {
                $skippedAmbiguousMultiLine++;

                continue;
            }

            $escolhido = $matches[0];

            $out[] = [
                'legado_receita_item_id' => (int) $escolhido['item_id'],
                'legado_aquisicao_produto_id' => (int) $apId,
                'legado_aquisicao_id' => (int) $aquisicaoId,
                'data_aquisicao' => $dataFinal,
            ];
        }

        $seen = [];
        $deduped = [];
        foreach ($out as $row) {
            $k = $row['legado_receita_item_id'].'|'.$row['data_aquisicao'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $deduped[] = $row;
        }

        if ($skippedSemAtendimento + $skippedSemReceita + $skippedSemItem + $skippedSemData
            + $skippedNoLegacyLineMatch + $skippedAmbiguousMultiLine > 0) {
            $this->warn(
                'Item aquisições: sem atendimento='.$skippedSemAtendimento
                .', sem receita (CC)='.$skippedSemReceita
                .', sem linha receita/produto='.$skippedSemItem
                .', sem data='.$skippedSemData
                .', sem linha com dta_ult_aquisicao = data CC='.$skippedNoLegacyLineMatch
                .', múltiplas linhas candidatas (ambíguo)='.$skippedAmbiguousMultiLine
            );
        }

        return $deduped;
    }

    private function extrairProdutos(): array
    {
        $out = [];
        foreach ($this->dadosBrutos['produto'] ?? [] as $row) {
            $c = self::COLS_PRODUTO;
            $codigo = $this->colStr($row, $c, 'codigo');
            if (! $codigo) {
                continue;
            }

            $nomeGenRaw = $this->col($row, $c, 'nomeGenerico');
            $nomeGen = $nomeGenRaw === null || $nomeGenRaw === '' ? '' : trim((string) $nomeGenRaw);

            $descRaw = $this->col($row, $c, 'descricao');
            $descLegado = $descRaw === null || $descRaw === '' ? '' : trim((string) $descRaw);

            $out[] = [
                'legado_id' => $this->col($row, $c, 'id'),
                'codigo' => $codigo,
                'codigo_cq' => $this->colStr($row, $c, 'codigoCQ') ?? '',
                'ativo_legado' => (bool) $this->col($row, $c, 'ativo'),
                'descricao_legado' => $descLegado,
                'nome_generico_legado' => $nomeGen,
                'anotacoes_internas' => $nomeGen,
            ];
        }

        return $out;
    }

    // ─── OUTPUT ───

    private function escreverJson(string $dir, string $nome, array $dados): void
    {
        $path = rtrim($dir, '/')."/{$nome}.json";
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json);
        $this->line("   -> {$nome}.json (".\count($dados).(isset($dados[0]) ? ' registros' : ' campos').')');
    }

    private function gerarResumo(array $clinicas, array $medicos, array $users, array $pacientes, array $receitas, array $produtos, array $itemAquisicoesLegado = []): array
    {
        $totalItens = 0;
        $produtosSemCodigo = 0;
        $produtosComMapeamento = 0;
        $codigosMapeados = [];

        $produtosComAnotacoes = 0;
        foreach ($produtos as $p) {
            if (($p['anotacoes_internas'] ?? '') !== '') {
                $produtosComAnotacoes++;
            }
        }

        foreach ($receitas as $r) {
            $totalItens += \count($r['itens']);
            foreach ($r['itens'] as $item) {
                if (! $item['codigo_produto_mapeado']) {
                    $produtosSemCodigo++;
                }
                if ($item['codigo_produto_legado'] !== $item['codigo_produto_mapeado'] && $item['codigo_produto_mapeado']) {
                    $produtosComMapeamento++;
                    $codigosMapeados[$item['codigo_produto_legado']] = $item['codigo_produto_mapeado'];
                }
            }
        }

        $usersComEmail = \count(array_filter($users, fn ($u) => ! empty($u['email'])));
        $usersSemEmail = \count($users) - $usersComEmail;

        $usersPorRole = [];
        foreach ($users as $u) {
            $usersPorRole[$u['role']] = ($usersPorRole[$u['role']] ?? 0) + 1;
        }

        $usersComAvisos = array_filter($users, fn ($u) => ! empty($u['avisos']));

        return [
            'contagens' => [
                'clinicas' => \count($clinicas),
                'medicos' => \count($medicos),
                'users' => \count($users),
                'pacientes' => \count($pacientes),
                'receitas' => \count($receitas),
                'receita_itens' => $totalItens,
                'produtos' => \count($produtos),
                'item_aquisicoes_legado' => \count($itemAquisicoesLegado),
            ],
            'produtos_legado' => [
                'total_extraidos' => \count($produtos),
                'com_anotacoes_internas_preenchidas' => $produtosComAnotacoes,
            ],
            'users' => [
                'com_email' => $usersComEmail,
                'sem_email' => $usersSemEmail,
                'por_role' => $usersPorRole,
            ],
            'receitas_codigos_produto' => [
                'itens_sem_codigo' => $produtosSemCodigo,
                'itens_com_mapeamento_aplicado' => $produtosComMapeamento,
                'codigos_mapeados' => $codigosMapeados,
            ],
            'avisos_users' => array_map(fn ($u) => [
                'legado_id' => $u['legado_id'],
                'nome' => $u['nome'],
                'avisos' => $u['avisos'],
            ], array_values($usersComAvisos)),
        ];
    }

    private function escreverCsv(string $dir, string $nome, array $headers, array $rows): void
    {
        $path = rtrim($dir, '/')."/{$nome}.csv";
        $fh = fopen($path, 'w');
        if ($fh === false) {
            $this->warn("Não foi possível criar: {$path}");

            return;
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers, ';');
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $v = $row[$h] ?? '';
                if (is_array($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif (is_bool($v)) {
                    $v = $v ? '1' : '0';
                } elseif ($v === null) {
                    $v = '';
                } elseif (! is_scalar($v)) {
                    $v = (string) $v;
                }
                $line[] = $v;
            }
            fputcsv($fh, $line, ';');
        }
        fclose($fh);
        $this->line('   -> '.$nome.'.csv ('.\count($rows).' linhas)');
    }

    /**
     * @param  array<string, string>  $mapeamentoCodigo
     */
    private function escreverCsvsMigracao(
        string $outputDir,
        array $clinicas,
        array $medicos,
        array $users,
        array $pacientes,
        array $receitas,
        array $produtos,
        array $itemAquisicoesLegado,
        array $mapeamentoCodigo,
    ): void {
        $hClin = ['legado_id', 'nome', 'cnpj', 'telefone1', 'telefone2', 'telefone3', 'celular', 'email', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'anotacoes', 'ativo'];
        $rowsClin = [];
        foreach ($clinicas as $c) {
            $rowsClin[] = [
                'legado_id' => $c['legado_id'] ?? '',
                'nome' => $c['nome'] ?? '',
                'cnpj' => $c['cnpj'] ?? '',
                'telefone1' => $c['telefone1'] ?? '',
                'telefone2' => $c['telefone2'] ?? '',
                'telefone3' => $c['telefone3'] ?? '',
                'celular' => $c['celular'] ?? '',
                'email' => $c['email'] ?? '',
                'endereco' => $c['endereco'] ?? '',
                'numero' => $c['numero'] ?? '',
                'complemento' => $c['complemento'] ?? '',
                'bairro' => $c['bairro'] ?? '',
                'cidade' => $c['cidade'] ?? '',
                'uf' => $c['uf'] ?? '',
                'cep' => $c['cep'] ?? '',
                'anotacoes' => $c['anotacoes'] ?? '',
                'ativo' => ! empty($c['ativo']),
            ];
        }
        $this->escreverCsv($outputDir, 'clinicas', $hClin, $rowsClin);

        $hMed = ['legado_id', 'nome_legado', 'apelido', 'crm', 'uf_crm', 'cpf', 'rg', 'especialidade', 'legado_clinica_id', 'telefone1', 'telefone2', 'telefone3', 'celular', 'email1', 'email2', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'enderecos_json', 'rodape_receita', 'anotacoes', 'ativo'];
        $rowsMed = [];
        foreach ($medicos as $m) {
            $rowsMed[] = [
                'legado_id' => $m['legado_id'] ?? '',
                'nome_legado' => $m['nome_legado'] ?? '',
                'apelido' => $m['apelido'] ?? '',
                'crm' => $m['crm'] ?? '',
                'uf_crm' => $m['uf_crm'] ?? '',
                'cpf' => $m['cpf'] ?? '',
                'rg' => $m['rg'] ?? '',
                'especialidade' => $m['especialidade'] ?? '',
                'legado_clinica_id' => $m['legado_clinica_id'] ?? '',
                'telefone1' => $m['telefone1'] ?? '',
                'telefone2' => $m['telefone2'] ?? '',
                'telefone3' => $m['telefone3'] ?? '',
                'celular' => $m['celular'] ?? '',
                'email1' => $m['email1'] ?? '',
                'email2' => $m['email2'] ?? '',
                'endereco' => $m['endereco'] ?? '',
                'numero' => $m['numero'] ?? '',
                'complemento' => $m['complemento'] ?? '',
                'bairro' => $m['bairro'] ?? '',
                'cidade' => $m['cidade'] ?? '',
                'uf' => $m['uf'] ?? '',
                'cep' => $m['cep'] ?? '',
                'enderecos_json' => $m['enderecos'] ?? [],
                'rodape_receita' => $m['rodape_receita'] ?? '',
                'anotacoes' => $m['anotacoes'] ?? '',
                'ativo' => ! empty($m['ativo']),
            ];
        }
        $this->escreverCsv($outputDir, 'medicos', $hMed, $rowsMed);

        $hUsr = ['legado_id', 'nome', 'email', 'username', 'role', 'legado_roles_json', 'legado_medico_ids_json', 'legado_clinica_id', 'is_active', 'avisos_json'];
        $rowsUsr = [];
        foreach ($users as $u) {
            $rowsUsr[] = [
                'legado_id' => $u['legado_id'] ?? '',
                'nome' => $u['nome'] ?? '',
                'email' => $u['email'] ?? '',
                'username' => $u['username'] ?? '',
                'role' => $u['role'] ?? '',
                'legado_roles_json' => $u['legado_roles'] ?? [],
                'legado_medico_ids_json' => $u['legado_medico_ids'] ?? [],
                'legado_clinica_id' => $u['legado_clinica_id'] ?? '',
                'is_active' => ! empty($u['is_active']),
                'avisos_json' => $u['avisos'] ?? [],
            ];
        }
        $this->escreverCsv($outputDir, 'users', $hUsr, $rowsUsr);

        $hPac = ['legado_id', 'codigo', 'nome', 'data_nascimento', 'sexo', 'fototipo', 'cpf', 'rg', 'celular', 'telefone1', 'telefones_adicionais_json', 'email1', 'email2', 'tipo_endereco', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'legado_medico_id', 'anotacoes', 'ativo'];
        $rowsPac = [];
        foreach ($pacientes as $p) {
            $rowsPac[] = [
                'legado_id' => $p['legado_id'] ?? '',
                'codigo' => $p['codigo'] ?? '',
                'nome' => $p['nome'] ?? '',
                'data_nascimento' => $p['data_nascimento'] ?? '',
                'sexo' => $p['sexo'] ?? '',
                'fototipo' => $p['fototipo'] ?? '',
                'cpf' => $p['cpf'] ?? '',
                'rg' => $p['rg'] ?? '',
                'celular' => $p['celular'] ?? '',
                'telefone1' => $p['telefone1'] ?? '',
                'telefones_adicionais_json' => $p['telefones_adicionais'] ?? [],
                'email1' => $p['email1'] ?? '',
                'email2' => $p['email2'] ?? '',
                'tipo_endereco' => $p['tipo_endereco'] ?? '',
                'endereco' => $p['endereco'] ?? '',
                'numero' => $p['numero'] ?? '',
                'complemento' => $p['complemento'] ?? '',
                'bairro' => $p['bairro'] ?? '',
                'cidade' => $p['cidade'] ?? '',
                'uf' => $p['uf'] ?? '',
                'cep' => $p['cep'] ?? '',
                'legado_medico_id' => $p['legado_medico_id'] ?? '',
                'anotacoes' => $p['anotacoes'] ?? '',
                'ativo' => ! empty($p['ativo']),
            ];
        }
        $this->escreverCsv($outputDir, 'pacientes', $hPac, $rowsPac);

        $hProd = [
            'legado_id', 'codigo', 'codigo_cq', 'ativo_legado',
            'descricao_legado', 'nome_generico_legado', 'anotacoes_internas',
        ];
        $rowsProd = [];
        foreach ($produtos as $pr) {
            $rowsProd[] = [
                'legado_id' => $pr['legado_id'] ?? '',
                'codigo' => $pr['codigo'] ?? '',
                'codigo_cq' => $pr['codigo_cq'] ?? '',
                'ativo_legado' => ! empty($pr['ativo_legado']),
                'descricao_legado' => $pr['descricao_legado'] ?? '',
                'nome_generico_legado' => $pr['nome_generico_legado'] ?? '',
                'anotacoes_internas' => $pr['anotacoes_internas'] ?? '',
            ];
        }
        $this->escreverCsv($outputDir, 'produtos', $hProd, $rowsProd);

        $hPrev = [
            'legado_id', 'codigo_legado', 'codigo_base_busca', 'codigo_cq', 'ativo_legado',
            'import_nome', 'import_descricao_formula', 'import_modo_uso', 'import_anotacoes_internas',
        ];
        $rowsPrev = [];
        foreach ($produtos as $pr) {
            $codLeg = (string) ($pr['codigo'] ?? '');
            $desc = trim((string) ($pr['descricao_legado'] ?? ''));
            $parsed = $desc !== '' ? LegadoProdutoDescricaoParser::parse($desc) : ['nome' => '', 'formula' => '', 'modo_uso' => ''];
            $rowsPrev[] = [
                'legado_id' => $pr['legado_id'] ?? '',
                'codigo_legado' => $codLeg,
                'codigo_base_busca' => LegadoCodigoProdutoMapeamento::paraBase($codLeg, $mapeamentoCodigo),
                'codigo_cq' => $pr['codigo_cq'] ?? '',
                'ativo_legado' => ! empty($pr['ativo_legado']),
                'import_nome' => $parsed['nome'],
                'import_descricao_formula' => $parsed['formula'],
                'import_modo_uso' => $parsed['modo_uso'],
                'import_anotacoes_internas' => $pr['anotacoes_internas'] ?? '',
            ];
        }
        $this->escreverCsv($outputDir, 'produtos-import-preview', $hPrev, $rowsPrev);

        $hRecSum = ['legado_id', 'numero_legado', 'data_receita', 'legado_paciente_id', 'legado_medico_id', 'n_itens', 'subtotal', 'desconto_percentual', 'desconto_valor', 'valor_frete', 'valor_total', 'status', 'anotacoes'];
        $rowsRecSum = [];
        foreach ($receitas as $r) {
            $rowsRecSum[] = [
                'legado_id' => $r['legado_id'] ?? '',
                'numero_legado' => $r['numero_legado'] ?? '',
                'data_receita' => $r['data_receita'] ?? '',
                'legado_paciente_id' => $r['legado_paciente_id'] ?? '',
                'legado_medico_id' => $r['legado_medico_id'] ?? '',
                'n_itens' => \count($r['itens'] ?? []),
                'subtotal' => $r['subtotal'] ?? '',
                'desconto_percentual' => $r['desconto_percentual'] ?? '',
                'desconto_valor' => $r['desconto_valor'] ?? '',
                'valor_frete' => $r['valor_frete'] ?? '',
                'valor_total' => $r['valor_total'] ?? '',
                'status' => $r['status'] ?? '',
                'anotacoes' => $r['anotacoes'] ?? '',
            ];
        }
        $this->escreverCsv($outputDir, 'receitas-resumo', $hRecSum, $rowsRecSum);

        $hRecIt = ['receita_legado_id', 'item_legado_id', 'legado_produto_id', 'codigo_produto_legado', 'codigo_produto_mapeado', 'local_uso', 'anotacoes', 'quantidade', 'valor_unitario', 'valor_total', 'data_aquisicao', 'imprimir', 'ordem'];
        $rowsRecIt = [];
        foreach ($receitas as $r) {
            $rid = $r['legado_id'] ?? '';
            foreach ($r['itens'] ?? [] as $it) {
                $rowsRecIt[] = [
                    'receita_legado_id' => $rid,
                    'item_legado_id' => $it['legado_id'] ?? '',
                    'legado_produto_id' => $it['legado_produto_id'] ?? '',
                    'codigo_produto_legado' => $it['codigo_produto_legado'] ?? '',
                    'codigo_produto_mapeado' => $it['codigo_produto_mapeado'] ?? '',
                    'local_uso' => $it['local_uso'] ?? '',
                    'anotacoes' => $it['anotacoes'] ?? '',
                    'quantidade' => $it['quantidade'] ?? '',
                    'valor_unitario' => $it['valor_unitario'] ?? '',
                    'valor_total' => $it['valor_total'] ?? '',
                    'data_aquisicao' => $it['data_aquisicao'] ?? '',
                    'imprimir' => ! empty($it['imprimir']),
                    'ordem' => $it['ordem'] ?? '',
                ];
            }
        }
        $this->escreverCsv($outputDir, 'receitas-itens', $hRecIt, $rowsRecIt);

        $hAq = ['legado_receita_item_id', 'legado_aquisicao_produto_id', 'legado_aquisicao_id', 'data_aquisicao'];
        $rowsAq = [];
        foreach ($itemAquisicoesLegado as $row) {
            $rowsAq[] = [
                'legado_receita_item_id' => $row['legado_receita_item_id'] ?? '',
                'legado_aquisicao_produto_id' => $row['legado_aquisicao_produto_id'] ?? '',
                'legado_aquisicao_id' => $row['legado_aquisicao_id'] ?? '',
                'data_aquisicao' => $row['data_aquisicao'] ?? '',
            ];
        }
        $this->escreverCsv($outputDir, 'item-aquisicoes-legado', $hAq, $rowsAq);
    }
}
