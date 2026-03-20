<?php

namespace App\Console\Commands;

use App\Jobs\SyncProdutosTinyJob;
use App\Models\Produto;
use App\Models\ReceitaItem;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimparESincronizarProdutos extends Command
{
    protected $signature = 'produtos:limpar-e-sincronizar
                            {--confirm : Confirmar execução (obrigatório para evitar execução acidental)}';

    protected $description = 'Remove todos os produtos locais e re-sincroniza apenas os com tag clinicaweb do Tiny';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Use --confirm para executar. Este comando remove TODOS os produtos e itens de receita vinculados.');
            return 1;
        }

        $produtosCount = Produto::count();
        $itensEmReceitas = ReceitaItem::count();

        $this->warn('ATENÇÃO: Este comando irá:');
        $this->line('  1. Remover todos os ' . $produtosCount . ' produtos da base local');
        $this->line('  2. Remover ' . $itensEmReceitas . ' itens de receita');
        $this->line('  3. Sincronizar apenas produtos com tag "clinicaweb" do Tiny');
        $this->newLine();

        if (!$this->confirm('Tem certeza que deseja continuar?', false)) {
            $this->info('Operação cancelada.');
            return 0;
        }

        $this->info('Removendo itens de receita e produtos...');

        DB::transaction(function () {
            // Primeiro itens (FK para produtos), usando model para disparar eventos (calcularTotais)
            ReceitaItem::query()->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $item->delete();
                }
            });
            Produto::query()->delete();
        });

        $this->info('Produtos removidos. Disparando sincronização...');

        Setting::set('tiny_sync_apenas_clinicaweb', true, 'tiny');

        SyncProdutosTinyJob::dispatchSync();

        $novoTotal = Produto::count();
        $this->info("Sincronização concluída. Total de produtos agora: {$novoTotal}");

        return 0;
    }
}
