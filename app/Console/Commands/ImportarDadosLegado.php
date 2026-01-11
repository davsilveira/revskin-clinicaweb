<?php

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarDadosLegado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrar:legado 
                            {--connection=legado : Nome da conexão do banco legado}
                            {--dry-run : Simula a importação sem gravar dados}
                            {--only= : Importar apenas: clinicas,medicos,pacientes,produtos,receitas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa dados do sistema ClinicaWeb legado para o novo sistema RevSkin';

    protected $legadoConnection;
    protected $dryRun = false;
    protected $stats = [
        'clinicas' => 0,
        'medicos' => 0,
        'pacientes' => 0,
        'produtos' => 0,
        'receitas' => 0,
        'receita_itens' => 0,
        'erros' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->legadoConnection = $this->option('connection');
        $this->dryRun = $this->option('dry-run');

        $this->info('=== Importação de Dados do Sistema Legado ===');
        
        if ($this->dryRun) {
            $this->warn('MODO SIMULAÇÃO - Nenhum dado será gravado');
        }

        $only = $this->option('only') ? explode(',', $this->option('only')) : null;

        try {
            // Test connection
            DB::connection($this->legadoConnection)->getPdo();
            $this->info("Conexão com banco legado estabelecida.");
        } catch (\Exception $e) {
            $this->error("Erro ao conectar com banco legado: " . $e->getMessage());
            return 1;
        }

        if (!$only || in_array('clinicas', $only)) {
            $this->importarClinicas();
        }

        if (!$only || in_array('produtos', $only)) {
            $this->importarProdutos();
        }

        if (!$only || in_array('medicos', $only)) {
            $this->importarMedicos();
        }

        if (!$only || in_array('pacientes', $only)) {
            $this->importarPacientes();
        }

        if (!$only || in_array('receitas', $only)) {
            $this->importarReceitas();
        }

        $this->mostrarResumo();

        return 0;
    }

    protected function importarClinicas()
    {
        $this->info("\n>>> Importando Clínicas...");

        $clinicas = DB::connection($this->legadoConnection)
            ->table('clinica')
            ->where('ativo', 1)
            ->get();

        $bar = $this->output->createProgressBar($clinicas->count());

        foreach ($clinicas as $legado) {
            try {
                if (!$this->dryRun) {
                    Clinica::updateOrCreate(
                        ['id' => $legado->id],
                        [
                            'nome' => $legado->nome ?? 'Sem nome',
                            'cnpj' => $legado->cnpj ?? null,
                            'telefone1' => $legado->fone1 ?? null,
                            'telefone2' => $legado->fone2 ?? null,
                            'telefone3' => $legado->fone3 ?? null,
                            'email' => $legado->email ?? null,
                            'endereco' => $legado->endereco ?? null,
                            'numero' => $legado->numero ?? null,
                            'complemento' => $legado->complemento ?? null,
                            'bairro' => $legado->bairro ?? null,
                            'cidade' => $legado->cidade ?? null,
                            'uf' => $legado->uf ?? null,
                            'cep' => $legado->cep ?? null,
                            'anotacoes' => $legado->anotacoes ?? null,
                            'ativo' => true,
                        ]
                    );
                }
                $this->stats['clinicas']++;
            } catch (\Exception $e) {
                $this->stats['erros']++;
                Log::error("Erro ao importar clínica {$legado->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function importarProdutos()
    {
        $this->info("\n>>> Importando Produtos...");

        $produtos = DB::connection($this->legadoConnection)
            ->table('produto')
            ->where('ativo', 1)
            ->get();

        $bar = $this->output->createProgressBar($produtos->count());

        foreach ($produtos as $legado) {
            try {
                if (!$this->dryRun) {
                    Produto::updateOrCreate(
                        ['codigo' => $legado->codigo],
                        [
                            'codigo_cq' => $legado->cod_cq ?? null,
                            'nome' => $legado->nome ?? $legado->codigo,
                            'descricao' => $legado->descricao ?? null,
                            'anotacoes' => $legado->anotacoes ?? null,
                            'local_uso' => $legado->local_uso ?? null,
                            'ativo' => true,
                        ]
                    );
                }
                $this->stats['produtos']++;
            } catch (\Exception $e) {
                $this->stats['erros']++;
                Log::error("Erro ao importar produto {$legado->codigo}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function importarMedicos()
    {
        $this->info("\n>>> Importando Médicos...");

        $medicos = DB::connection($this->legadoConnection)
            ->table('medico')
            ->where('ativo', 1)
            ->get();

        $bar = $this->output->createProgressBar($medicos->count());

        foreach ($medicos as $legado) {
            try {
                if (!$this->dryRun) {
                    Medico::updateOrCreate(
                        ['id' => $legado->id],
                        [
                            'nome' => $legado->nome ?? 'Sem nome',
                            'apelido' => $legado->apelido ?? null,
                            'crm' => $legado->crm ?? null,
                            'cpf' => $legado->cpf ?? null,
                            'rg' => $legado->rg ?? null,
                            'especialidade' => $legado->especialidade ?? null,
                            'clinica_id' => $legado->clinica_id ?? null,
                            'telefone1' => $legado->fone1 ?? null,
                            'telefone2' => $legado->fone2 ?? null,
                            'telefone3' => $legado->fone3 ?? null,
                            'email1' => $legado->email1 ?? null,
                            'email2' => $legado->email2 ?? null,
                            'endereco' => $legado->endereco ?? null,
                            'numero' => $legado->numero ?? null,
                            'complemento' => $legado->complemento ?? null,
                            'bairro' => $legado->bairro ?? null,
                            'cidade' => $legado->cidade ?? null,
                            'uf' => $legado->uf ?? null,
                            'cep' => $legado->cep ?? null,
                            'rodape_receita' => $legado->rodape_receita ?? null,
                            'anotacoes' => $legado->anotacoes ?? null,
                            'ativo' => true,
                        ]
                    );
                }
                $this->stats['medicos']++;
            } catch (\Exception $e) {
                $this->stats['erros']++;
                Log::error("Erro ao importar médico {$legado->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function importarPacientes()
    {
        $this->info("\n>>> Importando Pacientes...");

        $pacientes = DB::connection($this->legadoConnection)
            ->table('paciente')
            ->where('ativo', 1)
            ->get();

        $bar = $this->output->createProgressBar($pacientes->count());

        foreach ($pacientes as $legado) {
            try {
                if (!$this->dryRun) {
                    Paciente::updateOrCreate(
                        ['id' => $legado->id],
                        [
                            'codigo' => $legado->codigo ?? null,
                            'nome' => $legado->nome ?? 'Sem nome',
                            'data_nascimento' => $legado->dta_nascimento ?? null,
                            'sexo' => $legado->sexo ?? null,
                            'fototipo' => $legado->fototipo ?? null,
                            'cpf' => $legado->cpf ?? null,
                            'rg' => $legado->rg ?? null,
                            'telefone1' => $legado->fone1 ?? null,
                            'telefone2' => $legado->fone2 ?? null,
                            'telefone3' => $legado->fone3 ?? null,
                            'email1' => $legado->email1 ?? null,
                            'email2' => $legado->email2 ?? null,
                            'endereco' => $legado->endereco ?? null,
                            'numero' => $legado->numero ?? null,
                            'complemento' => $legado->complemento ?? null,
                            'bairro' => $legado->bairro ?? null,
                            'cidade' => $legado->cidade ?? null,
                            'uf' => $legado->uf ?? null,
                            'cep' => $legado->cep ?? null,
                            'indicado_por' => $legado->indicado_por ?? null,
                            'anotacoes' => $legado->anotacoes ?? null,
                            'medico_id' => $legado->medico_id ?? null,
                            'ativo' => true,
                        ]
                    );
                }
                $this->stats['pacientes']++;
            } catch (\Exception $e) {
                $this->stats['erros']++;
                Log::error("Erro ao importar paciente {$legado->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function importarReceitas()
    {
        $this->info("\n>>> Importando Receitas...");

        $receitas = DB::connection($this->legadoConnection)
            ->table('receita')
            ->where('ativo', 1)
            ->get();

        $bar = $this->output->createProgressBar($receitas->count());

        foreach ($receitas as $legado) {
            try {
                if (!$this->dryRun) {
                    $receita = Receita::updateOrCreate(
                        ['id' => $legado->id],
                        [
                            'numero' => $legado->numero ?? $legado->id,
                            'data_receita' => $legado->dta_receita ?? now(),
                            'paciente_id' => $legado->paciente_id,
                            'medico_id' => $legado->medico_id,
                            'anotacoes' => $legado->anotacoes ?? null,
                            'subtotal' => $legado->subtotal ?? 0,
                            'desconto_percentual' => $legado->desconto_percentual ?? 0,
                            'desconto_valor' => $legado->desconto_valor ?? 0,
                            'valor_caixa' => $legado->valor_caixa ?? 0,
                            'valor_frete' => $legado->valor_frete ?? 0,
                            'valor_total' => $legado->valor_total ?? 0,
                            'status' => 'finalizada',
                            'ativo' => true,
                        ]
                    );

                    // Import items
                    $itens = DB::connection($this->legadoConnection)
                        ->table('receita_item')
                        ->join('receita_itens', 'receita_item.id', '=', 'receita_itens.receita_item_id')
                        ->where('receita_itens.receita_id', $legado->id)
                        ->where('receita_item.ativo', 1)
                        ->get();

                    foreach ($itens as $item) {
                        // Find produto by codigo
                        $produto = Produto::where('codigo', $item->codigo ?? '')->first();
                        
                        if ($produto) {
                            ReceitaItem::updateOrCreate(
                                ['id' => $item->id],
                                [
                                    'receita_id' => $receita->id,
                                    'produto_id' => $produto->id,
                                    'local_uso' => $item->local_uso ?? null,
                                    'anotacoes' => $item->anotacoes ?? null,
                                    'quantidade' => $item->quantidade ?? 1,
                                    'valor_unitario' => $item->valor_unitario ?? 0,
                                    'valor_total' => $item->valor_total ?? 0,
                                    'imprimir' => $item->imprimir ?? true,
                                    'ordem' => $item->ordem ?? 0,
                                ]
                            );
                            $this->stats['receita_itens']++;
                        }
                    }
                }
                $this->stats['receitas']++;
            } catch (\Exception $e) {
                $this->stats['erros']++;
                Log::error("Erro ao importar receita {$legado->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function mostrarResumo()
    {
        $this->info("\n=== RESUMO DA IMPORTAÇÃO ===");
        $this->table(
            ['Entidade', 'Quantidade'],
            [
                ['Clínicas', $this->stats['clinicas']],
                ['Médicos', $this->stats['medicos']],
                ['Pacientes', $this->stats['pacientes']],
                ['Produtos', $this->stats['produtos']],
                ['Receitas', $this->stats['receitas']],
                ['Itens de Receita', $this->stats['receita_itens']],
                ['Erros', $this->stats['erros']],
            ]
        );

        if ($this->stats['erros'] > 0) {
            $this->warn("Verifique o log para detalhes dos erros.");
        }

        if ($this->dryRun) {
            $this->warn("\nMODO SIMULAÇÃO - Execute sem --dry-run para gravar os dados.");
        } else {
            $this->info("\nImportação concluída com sucesso!");
        }
    }
}










