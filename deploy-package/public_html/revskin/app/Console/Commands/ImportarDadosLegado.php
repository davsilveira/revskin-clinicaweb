<?php

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Models\Medico;
use App\Models\MedicoEndereco;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use App\Models\User;
use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoProdutoDescricaoParser;
use App\Support\LegadoProdutoResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportarDadosLegado extends Command
{
    protected $signature = 'migration:importar-legado
                            {--source=docs/migration : Diretório com JSONs gerados pela extração}
                            {--only= : Importar apenas: clinicas,medicos,users,pacientes,produtos,receitas,itemAquisicoesLegado (produtos: não cria; itemAquisicoesLegado após receitas)}
                            {--dry-run : Apenas simula, não persiste nada}
                            {--mapeamento-codigos=docs/sanitization/mapeamento-codigos-legado-base.md : Tabela markdown legado → código base (Tiny) para produtos e itens de receita}';

    protected $description = 'Importa dados extraídos do ClinicaWeb (idempotente). Usa mapeamento-codigos para localizar produtos quando o código legado ≠ base.';

    private array $idMapping = [];

    private string $mappingPath;

    private array $stats = [];

    /** @var array<string, string> */
    private array $mapeamentoCodigoLegadoBase = [];

    public function handle(): int
    {
        $sourceDir = base_path($this->option('source'));
        $this->mappingPath = rtrim($sourceDir, '/').'/id-mapping.json';
        $only = $this->option('only');
        $dryRun = $this->option('dry-run');

        if (! is_dir($sourceDir)) {
            $this->error("Diretório não encontrado: {$sourceDir}");
            $this->error('Execute primeiro: php artisan migration:extrair-legado');

            return 1;
        }

        $this->loadIdMapping();

        $mapPath = base_path($this->option('mapeamento-codigos'));
        $this->mapeamentoCodigoLegadoBase = LegadoCodigoProdutoMapeamento::fromMarkdownFile($mapPath);
        if ($this->mapeamentoCodigoLegadoBase !== []) {
            $this->line('Mapeamento legado→base: '.\count($this->mapeamentoCodigoLegadoBase).' códigos ('.basename($mapPath).')');
        }

        $this->info('=== Importação de Dados Legado ClinicaWeb ===');
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: nenhum dado será persistido');
        }
        $this->newLine();

        $entidades = $only ? explode(',', $only) : ['clinicas', 'medicos', 'users', 'pacientes', 'produtos', 'receitas', 'itemAquisicoesLegado'];

        foreach ($entidades as $entidade) {
            $entidade = trim($entidade);
            $jsonPath = rtrim($sourceDir, '/')."/{$entidade}.json";

            if (! file_exists($jsonPath)) {
                $this->warn("Arquivo não encontrado: {$entidade}.json - pulando");

                continue;
            }

            $dados = json_decode(file_get_contents($jsonPath), true);
            if (! is_array($dados)) {
                $this->error("Erro ao ler {$entidade}.json");

                continue;
            }

            $this->info("Importando {$entidade} (".count($dados).' registros)...');
            $this->stats[$entidade] = ['importados' => 0, 'existentes' => 0, 'erros' => 0];

            $method = 'importar'.ucfirst($entidade);
            if (! method_exists($this, $method)) {
                $this->error("Método {$method} não encontrado");

                continue;
            }

            if ($dryRun) {
                $this->stats[$entidade]['importados'] = count($dados);
                $this->line("   [dry-run] {$this->count($dados)} registros seriam importados");

                continue;
            }

            DB::beginTransaction();
            try {
                $this->{$method}($dados);
                DB::commit();
                $this->saveIdMapping();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("   ERRO: {$e->getMessage()}");
                $this->stats[$entidade]['erros']++;
            }

            $s = $this->stats[$entidade];
            $this->line("   Importados: {$s['importados']} | Já existentes: {$s['existentes']} | Erros: {$s['erros']}");
        }

        $this->newLine();
        $this->info('Importação concluída.');
        $this->printResumo();

        return 0;
    }

    // ─── ID MAPPING ───

    private function loadIdMapping(): void
    {
        if (file_exists($this->mappingPath)) {
            $this->idMapping = json_decode(file_get_contents($this->mappingPath), true) ?? [];
        }
    }

    private function saveIdMapping(): void
    {
        $dir = dirname($this->mappingPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->mappingPath,
            json_encode($this->idMapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function setMapping(string $entity, int|string $oldId, int|string $newId): void
    {
        $this->idMapping[$entity][$oldId] = $newId;
    }

    private function getMapping(string $entity, int|string|null $oldId): int|string|null
    {
        if ($oldId === null) {
            return null;
        }

        return $this->idMapping[$entity][$oldId] ?? null;
    }

    // ─── IMPORT: CLINICAS ───

    private function importarClinicas(array $dados): void
    {
        foreach ($dados as $item) {
            $existente = $this->getMapping('clinicas', $item['legado_id'])
                ? Clinica::find($this->getMapping('clinicas', $item['legado_id']))
                : null;

            if (! $existente) {
                $existente = Clinica::where('nome', $item['nome'])->first();
            }

            if ($existente) {
                $this->setMapping('clinicas', $item['legado_id'], $existente->id);
                $this->stats['clinicas']['existentes']++;

                continue;
            }

            try {
                $clinica = Clinica::create([
                    'nome' => $item['nome'],
                    'cnpj' => $item['cnpj'],
                    'telefone1' => $item['telefone1'] ?? $item['celular'],
                    'telefone2' => $item['telefone2'],
                    'telefone3' => $item['telefone3'],
                    'email' => $item['email'],
                    'endereco' => $item['endereco'],
                    'numero' => $item['numero'],
                    'complemento' => $item['complemento'],
                    'bairro' => $item['bairro'],
                    'cidade' => $item['cidade'],
                    'uf' => $item['uf'],
                    'cep' => $item['cep'],
                    'anotacoes' => $item['anotacoes'],
                    'ativo' => $item['ativo'],
                ]);

                $this->setMapping('clinicas', $item['legado_id'], $clinica->id);
                $this->stats['clinicas']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro clínica '{$item['nome']}': {$e->getMessage()}");
                $this->stats['clinicas']['erros']++;
            }
        }
    }

    // ─── IMPORT: MEDICOS ───

    private function importarMedicos(array $dados): void
    {
        foreach ($dados as $item) {
            $existente = $this->getMapping('medicos', $item['legado_id'])
                ? Medico::find($this->getMapping('medicos', $item['legado_id']))
                : null;

            if (! $existente && ! empty($item['crm'])) {
                $existente = Medico::where('crm', $item['crm'])->first();
            }

            if (! $existente && ! empty($item['apelido'])) {
                $existente = Medico::where('apelido', $item['apelido'])->first();
            }

            if ($existente) {
                $this->setMapping('medicos', $item['legado_id'], $existente->id);
                $this->stats['medicos']['existentes']++;
                $nomeLegado = isset($item['nome_legado']) ? trim((string) $item['nome_legado']) : '';
                if ($nomeLegado !== '' && blank($existente->nome_legado)) {
                    $existente->nome_legado = $nomeLegado;
                    $existente->saveQuietly();
                }

                continue;
            }

            try {
                $clinicaId = $this->getMapping('clinicas', $item['legado_clinica_id']);

                $medico = Medico::create([
                    'apelido' => $item['apelido'],
                    'nome_legado' => isset($item['nome_legado']) ? trim((string) $item['nome_legado']) ?: null : null,
                    'crm' => $item['crm'],
                    'uf_crm' => $item['uf_crm'],
                    'cpf' => $item['cpf'],
                    'rg' => $item['rg'],
                    'especialidade' => $item['especialidade'],
                    'clinica_id' => $clinicaId,
                    'telefone1' => $item['telefone1'] ?? $item['celular'],
                    'telefone2' => $item['telefone2'],
                    'telefone3' => $item['telefone3'],
                    'email1' => $item['email1'],
                    'email2' => $item['email2'],
                    'endereco' => $item['endereco'],
                    'numero' => $item['numero'],
                    'complemento' => $item['complemento'],
                    'bairro' => $item['bairro'],
                    'cidade' => $item['cidade'],
                    'uf' => $item['uf'],
                    'cep' => $item['cep'],
                    'rodape_receita' => $item['rodape_receita'],
                    'anotacoes' => $item['anotacoes'],
                    'ativo' => $item['ativo'],
                ]);

                // Enderecos repeater
                foreach ($item['enderecos'] ?? [] as $end) {
                    MedicoEndereco::create([
                        'medico_id' => $medico->id,
                        'nome' => $end['nome'],
                        'endereco' => $end['endereco'],
                        'numero' => $end['numero'],
                        'complemento' => $end['complemento'],
                        'bairro' => $end['bairro'],
                        'cidade' => $end['cidade'],
                        'uf' => $end['uf'],
                        'cep' => $end['cep'],
                        'principal' => $end['principal'],
                    ]);
                }

                // clinica_medico pivot
                if ($clinicaId) {
                    $medico->clinicas()->syncWithoutDetaching([$clinicaId]);
                }

                $this->setMapping('medicos', $item['legado_id'], $medico->id);
                $this->stats['medicos']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro médico '{$item['nome_legado']}': {$e->getMessage()}");
                $this->stats['medicos']['erros']++;
            }
        }
    }

    // ─── IMPORT: USERS ───

    private function importarUsers(array $dados): void
    {
        foreach ($dados as $item) {
            $existente = $this->getMapping('users', $item['legado_id'])
                ? User::find($this->getMapping('users', $item['legado_id']))
                : null;

            // Vários users legado (ex.: usernames distintos) para o mesmo médico → um único User no novo sistema
            if (! $existente && ($item['role'] ?? '') === 'medico' && ! empty($item['legado_medico_ids'])) {
                $medicoIdNovo = $this->getMapping('medicos', $item['legado_medico_ids'][0]);
                if ($medicoIdNovo) {
                    $existente = User::query()
                        ->where('role', 'medico')
                        ->where('medico_id', $medicoIdNovo)
                        ->first();
                }
            }

            if (! $existente && ! empty($item['email'])) {
                $existente = User::where('email', $item['email'])->first();
            }

            if (! $existente && ! empty($item['nome'])) {
                $existente = User::where('name', $item['nome'])->first();
            }

            if ($existente) {
                $this->setMapping('users', $item['legado_id'], $existente->id);
                $this->stats['users']['existentes']++;

                $novoRole = $item['role'] ?? 'admin';
                $novoMedicoId = null;
                if ($novoRole === 'medico' && ! empty($item['legado_medico_ids'])) {
                    $novoMedicoId = $this->getMapping('medicos', $item['legado_medico_ids'][0]);
                }
                $novoClinicaId = $novoRole === 'secretaria'
                    ? $this->getMapping('clinicas', $item['legado_clinica_id'] ?? null)
                    : null;

                $atualizar = false;
                if ($existente->role !== $novoRole) {
                    $existente->role = $novoRole;
                    $atualizar = true;
                }
                $medicoIdEsperado = $novoRole === 'medico' ? $novoMedicoId : null;
                if ((string) $existente->medico_id !== (string) ($medicoIdEsperado ?? '')) {
                    $existente->medico_id = $medicoIdEsperado;
                    $atualizar = true;
                }
                if ($novoRole === 'secretaria') {
                    if ((string) $existente->clinica_id !== (string) ($novoClinicaId ?? '')) {
                        $existente->clinica_id = $novoClinicaId;
                        $atualizar = true;
                    }
                } elseif ($existente->clinica_id !== null) {
                    $existente->clinica_id = null;
                    $atualizar = true;
                }
                if ($atualizar) {
                    $existente->saveQuietly();
                }

                $existente->refresh();
                $this->syncUserMedicoLinks($existente, $item);

                continue;
            }

            try {
                $medicoId = null;
                $clinicaId = $this->getMapping('clinicas', $item['legado_clinica_id']);

                if ($item['role'] === 'medico' && ! empty($item['legado_medico_ids'])) {
                    $medicoId = $this->getMapping('medicos', $item['legado_medico_ids'][0]);
                }

                $email = $item['email'];
                if (! $email) {
                    $email = Str::slug($item['username'] ?? $item['nome'], '.').'@legado.revskin.com.br';
                }

                $user = User::create([
                    'name' => $item['nome'] ?? $item['username'],
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => $item['role'],
                    'medico_id' => $medicoId,
                    'clinica_id' => $item['role'] === 'secretaria' ? $clinicaId : null,
                    'is_active' => $item['is_active'],
                ]);

                $this->syncUserMedicoLinks($user, $item);

                $this->setMapping('users', $item['legado_id'], $user->id);
                $this->stats['users']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro user '{$item['nome']}': {$e->getMessage()}");
                $this->stats['users']['erros']++;
            }
        }
    }

    private function syncUserMedicoLinks(User $user, array $item): void
    {
        $medicoIds = [];
        foreach ($item['legado_medico_ids'] ?? [] as $legadoMedicoId) {
            $newMedicoId = $this->getMapping('medicos', $legadoMedicoId);
            if ($newMedicoId) {
                $medicoIds[] = $newMedicoId;
            }
        }

        if (! empty($medicoIds)) {
            // For medico users, the first link is via medico_id (already set on create)
            // Additional links go to pivot
            $pivotIds = $medicoIds;
            if ($user->medico_id && in_array($user->medico_id, $pivotIds)) {
                $pivotIds = array_diff($pivotIds, [$user->medico_id]);
            }
            if (! empty($pivotIds)) {
                $user->medicos()->syncWithoutDetaching($pivotIds);
            }
        }
    }

    // ─── IMPORT: PACIENTES ───

    private function importarPacientes(array $dados): void
    {
        foreach ($dados as $item) {
            $existente = $this->getMapping('pacientes', $item['legado_id'])
                ? Paciente::find($this->getMapping('pacientes', $item['legado_id']))
                : null;

            if (! $existente && ! empty($item['cpf'])) {
                $existente = Paciente::where('cpf', $item['cpf'])->first();
            }

            if (! $existente && ! empty($item['nome'])) {
                $medicoId = $this->getMapping('medicos', $item['legado_medico_id']);
                $query = Paciente::where('nome', $item['nome']);
                if ($medicoId) {
                    $query->where('medico_id', $medicoId);
                }
                $existente = $query->first();
            }

            if ($existente) {
                $this->setMapping('pacientes', $item['legado_id'], $existente->id);
                $this->stats['pacientes']['existentes']++;

                continue;
            }

            try {
                $medicoId = $this->getMapping('medicos', $item['legado_medico_id']);

                $paciente = Paciente::create([
                    'codigo' => $item['codigo'] ?? null,
                    'indicado_por' => $item['indicado_por'] ?? null,
                    'nome' => $item['nome'],
                    'data_nascimento' => $this->sanitizarDataNascimento($item['data_nascimento'] ?? null),
                    'sexo' => $item['sexo'],
                    'fototipo' => $item['fototipo'],
                    'cpf' => $item['cpf'],
                    'rg' => $item['rg'],
                    'celular' => $item['celular'],
                    'telefone1' => $item['telefone1'],
                    'email1' => $item['email1'],
                    'email2' => $item['email2'],
                    'tipo_endereco' => $item['tipo_endereco'],
                    'endereco' => $item['endereco'],
                    'numero' => $item['numero'],
                    'complemento' => $item['complemento'],
                    'bairro' => $item['bairro'],
                    'cidade' => $item['cidade'],
                    'uf' => $item['uf'],
                    'cep' => $item['cep'],
                    'medico_id' => $medicoId,
                    'anotacoes' => $item['anotacoes'],
                    'ativo' => $item['ativo'],
                ]);

                // Telefones repeater
                foreach ($item['telefones_adicionais'] ?? [] as $tel) {
                    PacienteTelefone::create([
                        'paciente_id' => $paciente->id,
                        'numero' => $tel['numero'],
                        'tipo' => $tel['tipo'],
                        'principal' => false,
                    ]);
                }

                $this->setMapping('pacientes', $item['legado_id'], $paciente->id);
                $this->stats['pacientes']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro paciente '{$item['nome']}': {$e->getMessage()}");
                $this->stats['pacientes']['erros']++;
            }
        }
    }

    // ─── IMPORT: PRODUTOS (descricao legado → nome/descricao/modo_uso; nomeGenerico → anotacoes_internas) ───

    private function importarProdutos(array $dados): void
    {
        foreach ($dados as $item) {
            $codigo = trim((string) ($item['codigo'] ?? ''));
            if ($codigo === '') {
                $this->stats['produtos']['erros']++;

                continue;
            }

            $produto = $this->findProdutoPorCodigoLegado($codigo);
            if (! $produto) {
                $this->stats['produtos']['erros']++;

                continue;
            }

            $descLegado = trim((string) ($item['descricao_legado'] ?? ''));
            if ($descLegado !== '') {
                $parsed = LegadoProdutoDescricaoParser::parse($descLegado);
                if ($parsed['nome'] !== '') {
                    $produto->nome = $parsed['nome'];
                }
                if ($parsed['formula'] !== '') {
                    $produto->descricao = $parsed['formula'];
                }
                if ($parsed['modo_uso'] !== '') {
                    $produto->modo_uso = $parsed['modo_uso'];
                }
            }

            $texto = $item['anotacoes_internas'] ?? $item['nome_generico_legado'] ?? '';
            $produto->anotacoes_internas = is_string($texto) ? $texto : '';

            try {
                $produto->saveQuietly();
                $this->stats['produtos']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro produto '{$codigo}': {$e->getMessage()}");
                $this->stats['produtos']['erros']++;
            }
        }
    }

    private function sanitizarDataNascimento(mixed $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        $data = (string) $data;
        if (str_starts_with($data, '-') || str_starts_with($data, '0000-')) {
            return null;
        }

        return $data;
    }

    private function findProdutoPorCodigoLegado(string $codigoLegado): ?Produto
    {
        $cache = Produto::query()->get()->keyBy('codigo');

        return LegadoProdutoResolver::findPorCodigo(
            LegadoCodigoProdutoMapeamento::paraBase($codigoLegado, $this->mapeamentoCodigoLegadoBase),
            $cache
        );
    }

    // ─── IMPORT: RECEITAS ───

    private function importarReceitas(array $dados): void
    {
        $produtoCache = Produto::all()->keyBy('codigo');
        $receitasSemProduto = 0;

        foreach ($dados as $item) {
            if (($item['status'] ?? '') === 'cancelada') {
                continue;
            }

            $pacienteId = $this->getMapping('pacientes', $item['legado_paciente_id']);
            $medicoId = $this->getMapping('medicos', $item['legado_medico_id']);

            if (! $pacienteId || ! $medicoId) {
                $this->stats['receitas']['erros']++;

                continue;
            }

            $existente = $this->findReceitaByLegadoTag($item['legado_id']);

            if ($existente) {
                $this->setMapping('receitas', $item['legado_id'], $existente->id);
                $this->sincronizarMapeamentoReceitaItensExistente($existente, $item['itens'] ?? [], $produtoCache);
                $this->stats['receitas']['existentes']++;

                continue;
            }

            try {
                $numero = Receita::gerarNumero($pacienteId);

                $anotacoes = $item['anotacoes'] ?? '';
                $anotacoes = trim($anotacoes."\n[legado:{$item['legado_id']}|num:{$item['numero_legado']}]");

                $receita = new Receita;
                $receita->numero = $numero;
                $receita->data_receita = $item['data_receita'];
                $receita->paciente_id = $pacienteId;
                $receita->medico_id = $medicoId;
                $receita->anotacoes = $anotacoes;
                $receita->subtotal = $item['subtotal'] ?? 0;
                $receita->desconto_percentual = $item['desconto_percentual'] ?? 0;
                $receita->desconto_valor = $item['desconto_valor'] ?? 0;
                $receita->desconto_motivo = $item['desconto_motivo'];
                $receita->valor_frete = $item['valor_frete'] ?? 0;
                $receita->valor_total = $item['valor_total'] ?? 0;
                $receita->status = $item['status'];
                $receita->ativo = true;
                $receita->saveQuietly();

                $separadorOrdem = $this->detectarSeparadorGrupoLegado($item['itens']);

                $ordemNova = 0;
                foreach ($item['itens'] as $receitaItem) {
                    $produtoId = $this->resolverProdutoIdItemLegadoJson($receitaItem, $produtoCache);

                    if (! $produtoId) {
                        $receitasSemProduto++;

                        continue;
                    }

                    $ordemNova++;
                    $ri = new ReceitaItem;
                    $ri->receita_id = $receita->id;
                    $ri->produto_id = $produtoId;
                    $ri->local_uso = $receitaItem['local_uso'];
                    $ri->anotacoes = $receitaItem['anotacoes'];
                    $ri->quantidade = $receitaItem['quantidade'] ?? 1;
                    $ri->valor_unitario = $receitaItem['valor_unitario'] ?? 0;
                    $ri->valor_total = ($receitaItem['quantidade'] ?? 1) * ($receitaItem['valor_unitario'] ?? 0);
                    $ri->data_aquisicao = $receitaItem['data_aquisicao'];
                    $ri->imprimir = $receitaItem['imprimir'];
                    $ri->ordem = $ordemNova;
                    $ri->grupo = ($separadorOrdem !== null && $receitaItem['ordem'] >= $separadorOrdem)
                        ? 'opcional'
                        : 'recomendado';
                    $ri->saveQuietly();
                    $this->setMapping('receita_itens', $receitaItem['legado_id'], $ri->id);
                }

                $this->setMapping('receitas', $item['legado_id'], $receita->id);
                $this->stats['receitas']['importados']++;
            } catch (\Exception $e) {
                $this->warn("   Erro receita legado #{$item['legado_id']}: {$e->getMessage()}");
                $this->stats['receitas']['erros']++;
            }
        }

        if ($receitasSemProduto > 0) {
            $this->warn("   {$receitasSemProduto} itens de receita sem produto correspondente na base");
        }
    }

    private function findReceitaByLegadoTag(int|string $legadoId): ?Receita
    {
        return Receita::where('anotacoes', 'like', "%[legado:{$legadoId}|%")->first();
    }

    /**
     * Encontra o separador entre recomendados e complementares usando o maior
     * bloco contiguo de linhas em branco. Em caso de empate, usa o primeiro.
     */
    private function detectarSeparadorGrupoLegado(array $itens): ?int
    {
        $sorted = $itens;
        usort($sorted, fn ($a, $b) => ((int) $a['ordem']) - ((int) $b['ordem']));

        $runs = [];
        $currentLen = 0;
        $currentStartIdx = 0;

        foreach ($sorted as $idx => $ri) {
            $code = trim((string) ($ri['codigo_produto_legado'] ?? ''));
            $isBlank = $code === '' || in_array($code, ['...', 'W-AMOSTRA'], true);

            if ($isBlank) {
                if ($currentLen === 0) {
                    $currentStartIdx = $idx;
                }
                $currentLen++;
            } else {
                if ($currentLen > 0) {
                    $runs[] = [
                        'len' => $currentLen,
                        'startIdx' => $currentStartIdx,
                        'ordem' => (int) $sorted[$currentStartIdx]['ordem'],
                    ];
                    $currentLen = 0;
                }
            }
        }

        $validRuns = array_filter($runs, function ($run) use ($sorted) {
            for ($j = 0; $j < $run['startIdx']; $j++) {
                $c = trim((string) ($sorted[$j]['codigo_produto_legado'] ?? ''));
                if ($c !== '' && ! in_array($c, ['...', 'W-AMOSTRA'], true)) {
                    return true;
                }
            }

            return false;
        });

        if (empty($validRuns)) {
            return null;
        }

        $maxLen = max(array_column($validRuns, 'len'));
        foreach ($validRuns as $run) {
            if ($run['len'] === $maxLen) {
                return $run['ordem'];
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection  $produtoCache  keyBy codigo
     */
    private function resolverProdutoIdItemLegadoJson(array $receitaItem, $produtoCache): ?int
    {
        $produto = LegadoProdutoResolver::findPorItemLegado(
            $receitaItem,
            $produtoCache,
            $this->mapeamentoCodigoLegadoBase
        );

        if ($produto) {
            return $produto->id;
        }

        $stub = LegadoProdutoResolver::criarStubSeNecessario(
            $receitaItem,
            $produtoCache,
            $this->mapeamentoCodigoLegadoBase,
            true
        );

        return $stub?->id;
    }

    /**
     * @param  \Illuminate\Support\Collection  $produtoCache
     */
    private function sincronizarMapeamentoReceitaItensExistente(Receita $receita, array $itensJson, $produtoCache): void
    {
        $dbItens = $receita->itens()->orderBy('ordem')->get();
        $usedDbIds = [];

        foreach ($itensJson as $ji) {
            $legadoItemId = $ji['legado_id'] ?? null;
            if ($legadoItemId === null || $legadoItemId === '') {
                continue;
            }

            $produtoId = $this->resolverProdutoIdItemLegadoJson($ji, $produtoCache);
            if (! $produtoId) {
                continue;
            }

            foreach ($dbItens as $db) {
                if (isset($usedDbIds[$db->id])) {
                    continue;
                }
                if ((int) $db->produto_id === (int) $produtoId) {
                    $this->setMapping('receita_itens', $legadoItemId, $db->id);
                    $usedDbIds[$db->id] = true;
                    break;
                }
            }
        }
    }

    // ─── IMPORT: ITEM AQUISIÇÕES (histórico legado) ───

    private function importarItemAquisicoesLegado(array $dados): void
    {
        foreach ($dados as $row) {
            $legadoItemId = $row['legado_receita_item_id'] ?? null;
            $dataAquisicao = $row['data_aquisicao'] ?? null;
            if ($legadoItemId === null || $legadoItemId === '' || ! $dataAquisicao) {
                $this->stats['itemAquisicoesLegado']['erros']++;

                continue;
            }

            $newItemId = $this->getMapping('receita_itens', $legadoItemId);
            if (! $newItemId) {
                $this->stats['itemAquisicoesLegado']['erros']++;

                continue;
            }

            $jaExiste = ReceitaItemAquisicao::query()
                ->where('receita_item_id', $newItemId)
                ->whereDate('data_aquisicao', $dataAquisicao)
                ->exists();

            if ($jaExiste) {
                $this->stats['itemAquisicoesLegado']['existentes']++;

                continue;
            }

            ReceitaItemAquisicao::create([
                'receita_item_id' => $newItemId,
                'data_aquisicao' => $dataAquisicao,
                'atendimento_id' => null,
            ]);
            $this->stats['itemAquisicoesLegado']['importados']++;
        }
    }

    // ─── HELPERS ───

    private function count(array $arr): int
    {
        return \count($arr);
    }

    private function printResumo(): void
    {
        $this->newLine();
        $this->info('=== Resumo da Importação ===');
        foreach ($this->stats as $entidade => $s) {
            $this->line(sprintf(
                '  %-12s  Importados: %-5d  Existentes: %-5d  Erros: %d',
                ucfirst($entidade),
                $s['importados'],
                $s['existentes'],
                $s['erros']
            ));
        }
        $this->newLine();
        $this->line("Mapeamento de IDs salvo em: {$this->mappingPath}");
    }
}
