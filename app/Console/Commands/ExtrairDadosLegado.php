<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExtrairDadosLegado extends Command
{
    protected $signature = 'migration:extrair-legado
                            {--sql=docs/clinicaweb/database/bkp_cw2_20251201.sql : Arquivo SQL dump do ClinicaWeb}
                            {--output=docs/migration : Diretório de saída para JSONs}';

    protected $description = 'Extrai dados do dump SQL do ClinicaWeb e gera JSONs para revisão antes da importação';

    private array $dadosBrutos = [];

    private const ROLE_MAP = [
        'ROLE_ADMIN'       => 'admin',
        'ROLE_FREITASADM'  => 'admin',
        'ROLE_MEDICO'      => 'medico',
        'ROLE_MEDICO_ADMIN'=> 'medico',
        'ROLE_SECRETARIA'  => 'secretaria',
        'ROLE_SECRETARIA_ADM' => 'secretaria',
        'ROLE_CALLCENTER'  => 'callcenter',
    ];

    private const IGNORED_ROLES = [
        'ROLE_USER', 'ROLE_RPT_RECEITA', 'ROLE_ORCA_RECEITA', 'ROLE_SELECT_TABPRECO',
    ];

    private const ROLE_PRIORITY = ['admin', 'medico', 'secretaria', 'callcenter'];

    private const CODIGO_MAPEAMENTO = [
        'NOITE-HIPOALERGENICO-LUMI-DYN3'              => 'NOITE-HIPOALERGENICO-LUMI-DYN3 3738',
        'NOITE-HIPOALERGENICO-LUMI-HYDRAVELT'          => 'NOITE-HIPOALERGENICO-LUMI-HYDRAVELT 3732',
        'NOITE-HIPOALERGENICO-R0,0015-DYN3'            => 'NOITE-HIPOALERGENICO-R0,0015-DYN3 3740',
        'NOITE-HIPOALERGENICO-R0,0015-HYDRAVELT'       => 'NOITE-HIPOALERGENICO-R0,0015-HYDRAVELT 3727',
        'NOITE-HIPOALERGENICO-R0,0025-DYN3'            => 'NOITE-HIPOALERGENICO-R0,0025-DYN3 3742',
        'NOITE-HIPOALERGENICO-R0,0025-HYDRAVELT'       => 'NOITE-HIPOALERGENICO-R0,0025-HYDRAVELT 3269',
        'NOITE-HIPOALERGENICO-R0,005-DYN3'             => 'NOITE-HIPOALERGENICO-R0,005-DYN3 3744',
        'NOITE-HIPOALERGENICO-R0,005-HYDRAVELT'        => 'NOITE-HIPOALERGENICO-R0,005-HYDRAVELT 3272',
        'NOITE-HIPOALERGENICO-R0,01-DYN3'              => 'NOITE-HIPOALERGENICO-R0,01-DYN3 3746',
        'NOITE-HIPOALERGENICO-R0,01-HYDRAVELT'         => 'NOITE-HIPOALERGENICO-R0,01-HYDRAVELT 3313',
        'NOITE-HIPOALERGENICO-RETINOL-LUMI-DYN3'       => 'NOITE-HIPOALERGENICO-RETINOL-LUMI-DYN3 3736',
        'NOITE-HIPOALERGENICO-RETINOL-LUMI-HYDRAVELT'  => 'NOITE-HIPOALERGENICO-RETINOL-LUMI-HYDRAVELT 3303',
    ];

    private const TABELAS_ALVO = [
        'clinica', 'medico', 'user', 'role', 'user_roles', 'user_medicos',
        'paciente', 'receita', 'receita_item', 'receita_itens',
        'receita_item_produtos', 'medico_receitas', 'paciente_receitas',
        'produto',
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
    ];

    private const COLS_ROLE = [
        'id' => 0, 'descricao' => 1, 'role_name' => 2,
    ];

    public function handle(): int
    {
        $sqlPath = base_path($this->option('sql'));
        $outputDir = base_path($this->option('output'));

        if (!file_exists($sqlPath)) {
            $this->error("Arquivo não encontrado: {$sqlPath}");
            return 1;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info('=== Extração de Dados Legado ClinicaWeb ===');
        $this->newLine();

        $this->info('1/6 Parsing SQL dump...');
        $this->parseSqlDump($sqlPath);
        $this->printTableCounts();

        $this->info('2/6 Extraindo clínicas...');
        $clinicas = $this->extrairClinicas();
        $this->line("   {$this->count($clinicas)} clínicas extraídas");

        $this->info('3/6 Extraindo médicos...');
        $medicos = $this->extrairMedicos();
        $this->line("   {$this->count($medicos)} médicos extraídos");

        $this->info('4/6 Extraindo usuários...');
        $users = $this->extrairUsers();
        $this->line("   {$this->count($users)} usuários extraídos");

        $this->info('5/6 Extraindo pacientes...');
        $pacientes = $this->extrairPacientes();
        $this->line("   {$this->count($pacientes)} pacientes extraídos");

        $this->info('6/6 Extraindo receitas...');
        $receitas = $this->extrairReceitas();
        $this->line("   {$this->count($receitas)} receitas extraídas");

        $this->newLine();
        $this->info('Escrevendo arquivos JSON...');
        $this->escreverJson($outputDir, 'clinicas', $clinicas);
        $this->escreverJson($outputDir, 'medicos', $medicos);
        $this->escreverJson($outputDir, 'users', $users);
        $this->escreverJson($outputDir, 'pacientes', $pacientes);
        $this->escreverJson($outputDir, 'receitas', $receitas);

        $resumo = $this->gerarResumo($clinicas, $medicos, $users, $pacientes, $receitas);
        $this->escreverJson($outputDir, 'resumo-extracao', $resumo);

        $this->newLine();
        $this->info("Arquivos gerados em: {$outputDir}/");
        $this->info('Revise os JSONs antes de rodar: php artisan migration:importar-legado');

        return 0;
    }

    // ─── SQL DUMP PARSER ───

    private function parseSqlDump(string $path): void
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
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
                $buffer .= ' ' . $trimmed;
            }

            if ($currentTable !== null && str_ends_with($trimmed, ';')) {
                if (!isset($this->dadosBrutos[$currentTable])) {
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
            $isFalse = ($result['value'] === "\0" || $result['value'] === "\\0" || $result['value'] === '');
            return ['value' => !$isFalse, 'end' => $result['end']];
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
                        '\\' => "\\",
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
        if ($idx === null || !isset($row[$idx])) {
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
        if (!$data || trim($data) === '') {
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
            if ($key === null) continue;

            if ($valCol !== null) {
                $index[$key] = $row[$valCol];
            } else {
                if (!isset($index[$key])) {
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
            if ($key === null || $val === null) continue;
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
            if (!$nome) continue;

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
            $temEnderecoPrincipal = !empty($enderecoPrincipal['endereco']) || !empty($enderecoPrincipal['cidade']);

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
                    'principal' => !$temEnderecoPrincipal,
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
                    array_map(fn($f) => ['numero' => $f, 'tipo' => 'Celular'], array_slice($fones['celulares'], 1)),
                    array_map(fn($f) => ['numero' => $f, 'tipo' => 'Residencial'], array_slice($fones['fixos'], 3)),
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

    private function extrairUsers(): array
    {
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
            $roleNames = array_map(fn($rid) => $rolesById[$rid] ?? "UNKNOWN_{$rid}", $roleIds);

            // Significant roles = excluding base/permission-only roles
            $significantRoles = array_values(array_filter(
                $roleNames,
                fn($r) => !in_array($r, self::IGNORED_ROLES)
            ));

            // Skip Call Center-only users (significant roles are only ROLE_CALLCENTER)
            $nonCallCenter = array_filter($significantRoles, fn($r) => $r !== 'ROLE_CALLCENTER');
            if (!empty($significantRoles) && empty($nonCallCenter)) {
                continue;
            }

            $newRole = $this->mapearRole($roleNames);

            $medicoIds = $userMedicos[$userId] ?? [];
            $email = $this->colStr($row, $c, 'email');
            $enabled = $this->col($row, $c, 'enabled');

            $avisos = [];
            if (!$email) {
                $avisos[] = 'Sem email - não poderá recuperar senha via link';
            }
            if ($enabled === false) {
                $avisos[] = 'Usuário desativado no sistema antigo';
            }

            $users[] = [
                'legado_id' => $userId,
                'nome' => $this->colStr($row, $c, 'nome'),
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

            if ($obs) $anotacoes[] = $obs;
            if ($amiga) $anotacoes[] = "Indicação: {$amiga}";
            if ($vip && strtolower($vip) !== 'n' && $vip !== '0') $anotacoes[] = "VIP: {$vip}";
            if ($email3) $anotacoes[] = "Email adicional: {$email3}";
            if ($apelido && $apelido !== $nome) $anotacoes[] = "Apelido: {$apelido}";

            $pacientes[] = [
                'legado_id' => $this->col($row, $c, 'id'),
                'codigo' => $this->colStr($row, $c, 'nr_registro'),
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
                if (!$itemRow) continue;

                $ic = self::COLS_RECEITA_ITEM;
                $produtoIdLegado = $produtoByItem[$itemId] ?? null;
                $codigoLegado = $produtoIdLegado ? ($produtosById[$produtoIdLegado] ?? null) : null;
                $codigoMapeado = $codigoLegado ? (self::CODIGO_MAPEAMENTO[$codigoLegado] ?? $codigoLegado) : null;

                if ($codigoLegado && !$codigoMapeado) {
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

        if (!empty($avisosProdutos)) {
            $this->warn('Avisos de produtos em receitas:');
            foreach (array_slice($avisosProdutos, 0, 10) as $a) {
                $this->line("   - {$a}");
            }
            if (\count($avisosProdutos) > 10) {
                $this->line('   ... e mais ' . (\count($avisosProdutos) - 10));
            }
        }

        return $receitas;
    }

    // ─── OUTPUT ───

    private function escreverJson(string $dir, string $nome, array $dados): void
    {
        $path = rtrim($dir, '/') . "/{$nome}.json";
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json);
        $this->line("   -> {$nome}.json (" . \count($dados) . (isset($dados[0]) ? ' registros' : ' campos') . ")");
    }

    private function gerarResumo(array $clinicas, array $medicos, array $users, array $pacientes, array $receitas): array
    {
        $totalItens = 0;
        $produtosSemCodigo = 0;
        $produtosComMapeamento = 0;
        $codigosMapeados = [];

        foreach ($receitas as $r) {
            $totalItens += \count($r['itens']);
            foreach ($r['itens'] as $item) {
                if (!$item['codigo_produto_mapeado']) {
                    $produtosSemCodigo++;
                }
                if ($item['codigo_produto_legado'] !== $item['codigo_produto_mapeado'] && $item['codigo_produto_mapeado']) {
                    $produtosComMapeamento++;
                    $codigosMapeados[$item['codigo_produto_legado']] = $item['codigo_produto_mapeado'];
                }
            }
        }

        $usersComEmail = \count(array_filter($users, fn($u) => !empty($u['email'])));
        $usersSemEmail = \count($users) - $usersComEmail;

        $usersPorRole = [];
        foreach ($users as $u) {
            $usersPorRole[$u['role']] = ($usersPorRole[$u['role']] ?? 0) + 1;
        }

        $usersComAvisos = array_filter($users, fn($u) => !empty($u['avisos']));

        return [
            'contagens' => [
                'clinicas' => \count($clinicas),
                'medicos' => \count($medicos),
                'users' => \count($users),
                'pacientes' => \count($pacientes),
                'receitas' => \count($receitas),
                'receita_itens' => $totalItens,
            ],
            'users' => [
                'com_email' => $usersComEmail,
                'sem_email' => $usersSemEmail,
                'por_role' => $usersPorRole,
            ],
            'produtos' => [
                'itens_sem_codigo' => $produtosSemCodigo,
                'itens_com_mapeamento_aplicado' => $produtosComMapeamento,
                'codigos_mapeados' => $codigosMapeados,
            ],
            'avisos_users' => array_map(fn($u) => [
                'legado_id' => $u['legado_id'],
                'nome' => $u['nome'],
                'avisos' => $u['avisos'],
            ], array_values($usersComAvisos)),
        ];
    }
}
