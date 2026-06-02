<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LimparDadosReimport extends Command
{
    protected $signature = 'migration:limpar-dados-reimport
                            {--apos-backup : Obrigatório: confirma que migration:exportar-backup-reimport já foi executado}
                            {--confirm= : Digite REIMPORT para confirmar a exclusão}
                            {--incluir-produtos : Também apaga produtos (CASCADE em assistente_tratamento_itens, assistente_regra_acoes, receita_itens já vazios; Karnaugh usa só codigo string)}
                            {--preservar-users : Mantém todos os users (senhas intactas); apenas zera medico_id/clinica_id e limpa user_medico}
                            {--remover-id-mapping=docs/migration/id-mapping.json : Apaga o ficheiro de mapeamento após limpar (caminho relativo à raiz do projeto)}';

    protected $description = 'Apaga dados importáveis (receitas, pacientes, usuários não-admin, médicos, clínicas). Exige backup e confirmação explícita.';

    public function handle(): int
    {
        if (! $this->option('apos-backup')) {
            $this->error('É obrigatório exportar o backup antes: php artisan migration:exportar-backup-reimport');
            $this->error('Depois repita este comando com --apos-backup.');

            return 1;
        }

        if ($this->option('confirm') !== 'REIMPORT') {
            $this->error('Confirmação inválida. Use: --confirm=REIMPORT');

            return 1;
        }

        if ($this->option('preservar-users')) {
            $this->warn('Com --preservar-users: mantém todos os utilizadores; apenas zera vínculos médico/clínica.');
        } else {
            $this->warn('Isto remove receitas, call center, pacientes, utilizadores (exceto admin), médicos e clínicas.');
        }
        if ($this->option('incluir-produtos')) {
            $this->warn('Com --incluir-produtos: apaga todos os produtos e linhas dependentes (assistente com FK a produtos).');
        }
        $this->warn('Tabelas Karnaugh (tabela_karnaugh_produtos) usam código em texto — não são apagadas automaticamente.');
        $this->newLine();

        DB::transaction(function () {
            $this->limparReceitasEPacientes();
            $this->limparUsersEMedicosEClinicas();
            if ($this->option('incluir-produtos')) {
                $this->limparProdutos();
            }
        });

        $mappingPath = base_path($this->option('remover-id-mapping'));
        if (is_file($mappingPath)) {
            unlink($mappingPath);
            $this->info('Removido: '.$mappingPath);
        } else {
            $this->line('Ficheiro id-mapping não encontrado (ok se já não existia): '.$mappingPath);
        }

        $this->newLine();
        $this->info('Limpeza concluída. Sincronize produtos (Tiny) se necessário, depois: migration:extrair-legado e migration:importar-legado.');

        return 0;
    }

    private function limparReceitasEPacientes(): void
    {
        if (Schema::hasTable('receita_item_aquisicoes')) {
            DB::table('receita_item_aquisicoes')->delete();
        }
        if (Schema::hasTable('acompanhamentos_callcenter')) {
            DB::table('acompanhamentos_callcenter')->delete();
        }
        if (Schema::hasTable('atendimentos_callcenter')) {
            DB::table('atendimentos_callcenter')->delete();
        }
        if (Schema::hasTable('receita_itens')) {
            DB::table('receita_itens')->delete();
        }
        if (Schema::hasTable('receitas')) {
            DB::table('receitas')->delete();
        }
        if (Schema::hasTable('paciente_telefones')) {
            DB::table('paciente_telefones')->delete();
        }
        if (Schema::hasTable('pacientes')) {
            DB::table('pacientes')->delete();
        }
        $this->line('Receitas, atendimentos e pacientes removidos.');
    }

    private function limparUsersEMedicosEClinicas(): void
    {
        if (Schema::hasTable('user_medico')) {
            DB::table('user_medico')->delete();
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->update([
                'medico_id' => null,
                'clinica_id' => null,
            ]);

            if (! $this->option('preservar-users')) {
                DB::table('users')->where('role', '!=', 'admin')->delete();
            }
        }

        if (Schema::hasTable('medico_enderecos')) {
            DB::table('medico_enderecos')->delete();
        }
        if (Schema::hasTable('clinica_medico')) {
            DB::table('clinica_medico')->delete();
        }
        if (Schema::hasTable('medicos')) {
            DB::table('medicos')->delete();
        }
        if (Schema::hasTable('clinicas')) {
            DB::table('clinicas')->delete();
        }

        if ($this->option('preservar-users')) {
            $this->line('Vínculos user_medico limpos; users preservados. Médicos e clínicas removidos.');
        } else {
            $this->line('Utilizadores não-admin, médicos e clínicas removidos.');
        }
    }

    private function limparProdutos(): void
    {
        if (! Schema::hasTable('produtos')) {
            return;
        }

        if (Schema::hasTable('tabela_preco_itens')) {
            DB::table('tabela_preco_itens')->delete();
        }

        DB::table('produtos')->delete();
        $this->line('Produtos removidos.');
    }
}
