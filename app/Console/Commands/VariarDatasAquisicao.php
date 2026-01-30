<?php

namespace App\Console\Commands;

use App\Models\ReceitaItemAquisicao;
use Illuminate\Console\Command;
use Carbon\Carbon;

class VariarDatasAquisicao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aquisicoes:variar-datas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Varia as datas de aquisição para permitir testar o tooltip';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Variando datas de aquisição...');

        // Buscar todas as aquisições agrupadas por produto_id e paciente_id
        $aquisicoes = ReceitaItemAquisicao::with('receitaItem.receita')
            ->get()
            ->groupBy(function ($aquisicao) {
                return $aquisicao->receitaItem->produto_id . '_' . $aquisicao->receitaItem->receita->paciente_id;
            });

        $variacoes = [
            -30, // 30 dias atrás
            -60, // 60 dias atrás
            -90, // 90 dias atrás
            -120, // 120 dias atrás
            -15, // 15 dias atrás
            -45, // 45 dias atrás
        ];

        $contador = 0;
        foreach ($aquisicoes as $grupo => $items) {
            // Para cada grupo (produto + paciente), variar algumas datas
            $itemsArray = $items->toArray();
            $totalItems = count($itemsArray);
            
            if ($totalItems > 1) {
                // Variar algumas datas, mantendo pelo menos uma com a data atual
                foreach ($items as $index => $aquisicao) {
                    if ($index > 0 && $index < count($variacoes) + 1) {
                        $diasAtras = $variacoes[($index - 1) % count($variacoes)];
                        $novaData = Carbon::now()->addDays($diasAtras)->format('Y-m-d');
                        
                        $aquisicao->update([
                            'data_aquisicao' => $novaData
                        ]);
                        
                        $contador++;
                    }
                }
            }
        }

        $this->info("Variadas {$contador} datas de aquisição com sucesso!");
        $this->info('Agora você pode testar o tooltip com diferentes datas.');

        return 0;
    }
}
