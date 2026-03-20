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
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportarDadosLegado extends Command
{
    protected $signature = 'migration:importar-legado
                            {--source=docs/migration : Diretório com JSONs gerados pela extração}
                            {--only= : Importar apenas uma entidade: clinicas,medicos,users,pacientes,receitas}
                            {--dry-run : Apenas simula, não persiste nada}';

    protected $description = 'Importa dados extraídos do ClinicaWeb para o novo sistema (idempotente)';

    private array $idMapping = [];
    private string $mappingPath;
    private array $stats = [];

    public function handle(): int
    {
        $sourceDir = base_path($this->option('source'));
        $this->mappingPath = rtrim($sourceDir, '/') . '/id-mapping.json';
        $only = $this->option('only');
        $dryRun = $this->option('dry-run');

        if (!is_dir($sourceDir)) {
            $this->error("Diretório não encontrado: {$sourceDir}");
            $this->error('Execute primeiro: php artisan migration:extrair-legado');
            return 1;
        }

        $this->loadIdMapping();

        $this->info('=== Importação de Dados Legado ClinicaWeb ===');
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: nenhum dado será persistido');
        }
        $this->newLine();

        $entidades = $only ? explode(',', $only) : ['clinicas', 'medicos', 'users', 'pacientes', 'receitas'];

        foreach ($entidades as $entidade) {
            $entidade = trim($entidade);
            $jsonPath = rtrim($sourceDir, '/') . "/{$entidade}.json";

            if (!file_exists($jsonPath)) {
                $this->warn("Arquivo não encontrado: {$entidade}.json - pulando");
                continue;
            }

            $dados = json_decode(file_get_contents($jsonPath), true);
            if (!is_array($dados)) {
                $this->error("Erro ao ler {$entidade}.json");
                continue;
            }

            $this->info("Importando {$entidade} (" . count($dados) . " registros)...");
            $this->stats[$entidade] = ['importados' => 0, 'existentes' => 0, 'erros' => 0];

            $method = 'importar' . ucfirst($entidade);
            if (!method_exists($this, $method)) {
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
        if (!is_dir($dir)) {
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
        if ($oldId === null) return null;
        return $this->idMapping[$entity][$oldId] ?? null;
    }

    // ─── IMPORT: CLINICAS ───

    private function importarClinicas(array $dados): void
    {
        foreach ($dados as $item) {
            $existente = $this->getMapping('clinicas', $item['legado_id'])
                ? Clinica::find($this->getMapping('clinicas', $item['legado_id']))
                : null;

            if (!$existente) {
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

            if (!$existente && !empty($item['crm'])) {
                $existente = Medico::where('crm', $item['crm'])->first();
            }

            if (!$existente && !empty($item['apelido'])) {
                $existente = Medico::where('apelido', $item['apelido'])->first();
            }

            if ($existente) {
                $this->setMapping('medicos', $item['legado_id'], $existente->id);
                $this->stats['medicos']['existentes']++;
                continue;
            }

            try {
                $clinicaId = $this->getMapping('clinicas', $item['legado_clinica_id']);

                $medico = Medico::create([
                    'apelido' => $item['apelido'],
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

            if (!$existente && !empty($item['email'])) {
                $existente = User::where('email', $item['email'])->first();
            }

            if (!$existente && !empty($item['nome'])) {
                $existente = User::where('name', $item['nome'])->first();
            }

            if ($existente) {
                $this->setMapping('users', $item['legado_id'], $existente->id);
                $this->stats['users']['existentes']++;

                $this->syncUserMedicoLinks($existente, $item);
                continue;
            }

            try {
                $medicoId = null;
                $clinicaId = $this->getMapping('clinicas', $item['legado_clinica_id']);

                if ($item['role'] === 'medico' && !empty($item['legado_medico_ids'])) {
                    $medicoId = $this->getMapping('medicos', $item['legado_medico_ids'][0]);
                }

                $email = $item['email'];
                if (!$email) {
                    $email = Str::slug($item['username'] ?? $item['nome'], '.') . '@legado.revskin.com.br';
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

        if (!empty($medicoIds)) {
            // For medico users, the first link is via medico_id (already set on create)
            // Additional links go to pivot
            $pivotIds = $medicoIds;
            if ($user->medico_id && in_array($user->medico_id, $pivotIds)) {
                $pivotIds = array_diff($pivotIds, [$user->medico_id]);
            }
            if (!empty($pivotIds)) {
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

            if (!$existente && !empty($item['cpf'])) {
                $existente = Paciente::where('cpf', $item['cpf'])->first();
            }

            if (!$existente && !empty($item['nome'])) {
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
                    'codigo' => $item['codigo'],
                    'nome' => $item['nome'],
                    'data_nascimento' => $item['data_nascimento'],
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

    // ─── IMPORT: RECEITAS ───

    private function importarReceitas(array $dados): void
    {
        $produtoCache = Produto::all()->keyBy('codigo');
        $receitasSemProduto = 0;

        foreach ($dados as $item) {
            $pacienteId = $this->getMapping('pacientes', $item['legado_paciente_id']);
            $medicoId = $this->getMapping('medicos', $item['legado_medico_id']);

            if (!$pacienteId || !$medicoId) {
                $this->stats['receitas']['erros']++;
                continue;
            }

            $existente = $this->findReceitaByLegadoTag($item['legado_id']);

            if ($existente) {
                $this->setMapping('receitas', $item['legado_id'], $existente->id);
                $this->stats['receitas']['existentes']++;
                continue;
            }

            try {
                $numero = Receita::gerarNumero($pacienteId);

                $anotacoes = $item['anotacoes'] ?? '';
                $anotacoes = trim($anotacoes . "\n[legado:{$item['legado_id']}|num:{$item['numero_legado']}]");

                $receita = new Receita();
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

                foreach ($item['itens'] as $receitaItem) {
                    $produtoId = null;
                    $codigoMapeado = $receitaItem['codigo_produto_mapeado'];

                    if ($codigoMapeado) {
                        $produto = $produtoCache->get($codigoMapeado);
                        if (!$produto) {
                            $produto = Produto::where('codigo', $codigoMapeado)
                                ->orWhere('codigo', 'like', $codigoMapeado . ' %')
                                ->first();
                            if ($produto) {
                                $produtoCache->put($produto->codigo, $produto);
                            }
                        }
                        $produtoId = $produto?->id;
                    }

                    if (!$produtoId) {
                        $receitasSemProduto++;
                        continue;
                    }

                    $ri = new ReceitaItem();
                    $ri->receita_id = $receita->id;
                    $ri->produto_id = $produtoId;
                    $ri->local_uso = $receitaItem['local_uso'];
                    $ri->anotacoes = $receitaItem['anotacoes'];
                    $ri->quantidade = $receitaItem['quantidade'] ?? 1;
                    $ri->valor_unitario = $receitaItem['valor_unitario'] ?? 0;
                    $ri->valor_total = ($receitaItem['quantidade'] ?? 1) * ($receitaItem['valor_unitario'] ?? 0);
                    $ri->data_aquisicao = $receitaItem['data_aquisicao'];
                    $ri->imprimir = $receitaItem['imprimir'];
                    $ri->ordem = $receitaItem['ordem'];
                    $ri->grupo = 'recomendado';
                    $ri->saveQuietly();
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
