<?php

namespace Database\Seeders;

use App\Models\Produto;
use Illuminate\Database\Seeder;

class ProdutoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Cria produtos necessários para o Assistente de Receitas.
     * Os códigos correspondem exatamente aos usados na Tabela Karnaugh.
     */
    public function run(): void
    {
        $produtos = $this->getProdutos();

        foreach ($produtos as $produto) {
            Produto::updateOrCreate(
                ['codigo' => $produto['codigo']],
                [
                    'nome' => $produto['nome'],
                    'descricao' => $produto['descricao'],
                    'anotacoes' => $produto['anotacoes'],
                    'local_uso' => $produto['local_uso'],
                    'ativo' => true,
                ]
            );
        }

        $this->command->info('ProdutoSeeder: ' . count($produtos) . ' produtos criados/atualizados.');
    }

    /**
     * Retorna array com todos os produtos.
     */
    private function getProdutos(): array
    {
        return array_merge(
            $this->getCremesDaNoite(),
            $this->getCremesDoDia(),
            $this->getLimpeza(),
            $this->getOlhos(),
            $this->getTratamentos(),
            $this->getMaos(),
            $this->getCorpo(),
            $this->getCabelo()
        );
    }

    /**
     * Cremes da Noite - Fórmulas com Tretinoína e Hidroquinona
     */
    private function getCremesDaNoite(): array
    {
        $anotacoesPadrao = 'Uso: Aplique uma camada fina por todo o rosto, pescoço e decote à noite. Inicie alternando as noites conforme orientação. Se ocorrer vermelhidão, interrompa o uso e contate-nos.';

        return [
            // Tretinoína 0,0015% + Hidroquinona 3%
            [
                'codigo' => 'R0,0015H3-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0015H3-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,0015% + Hidroquinona 8%
            [
                'codigo' => 'R0,0015H8-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0015H8-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,0015% + Hidroquinona 12%
            [
                'codigo' => 'R0,0015H12-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0015H12-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0015%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,0025% + Hidroquinona 3%
            [
                'codigo' => 'R0,0025H3-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0025H3-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,0025% + Hidroquinona 8%
            [
                'codigo' => 'R0,0025H8-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0025H8-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,0025% + Hidroquinona 12%
            [
                'codigo' => 'R0,0025H12-DYN3',
                'nome' => 'Creme da Noite Neodeline DYN3',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nHydraction Suisse 30,0%\nBase Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'R0,0025H12-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,0025%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,005% + Hidroquinona 3%
            [
                'codigo' => 'R0,005H3-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,005%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,005% + Hidroquinona 6%
            [
                'codigo' => 'R0,005H6-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,005%\nHidroquisan 6%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,005% + Hidroquinona 8%
            [
                'codigo' => 'R0,005H8-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,005%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,005% + Hidroquinona 12%
            [
                'codigo' => 'R0,005H12-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,005%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,01% + Hidroquinona 3%
            [
                'codigo' => 'R0,01H3-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,01%\nHidroquisan 3%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,01% + Hidroquinona 6%
            [
                'codigo' => 'R0,01H6-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,01%\nHidroquisan 6%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,01% + Hidroquinona 8%
            [
                'codigo' => 'R0,01H8-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,01%\nHidroquisan 8%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,01% + Hidroquinona 12%
            [
                'codigo' => 'R0,01H12-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,01%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            // Tretinoína 0,015% + Hidroquinona 12%
            [
                'codigo' => 'R0,015H12-NEODELINE',
                'nome' => 'Creme da Noite Neodeline',
                'descricao' => "Tretinoína 0,015%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Neodeline Nutrissome qsp 30g.",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
        ];
    }

    /**
     * Cremes do Dia - Protetores Solares FPS60
     */
    private function getCremesDoDia(): array
    {
        $anotacoesPadrao = 'Aplique na face, pescoço e colo. Use duas vezes ao dia, pela manhã e após o almoço. Se for ao sol, reaplique a cada 2 horas.';

        return [
            [
                'codigo' => 'HYDRAMINCE DYNAMISEE 1',
                'nome' => 'Creme do Dia Hydramince Dynamisée 1',
                'descricao' => "FPS60 Anti-UVA/B\nInnovare 6,0%\nCiclometicone 5 gts\nB. Hydramince Dynamisée 30,0%\nB. Hydramince Synchron qsp 50g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'HYDRAMINCE DYNAMISEE 2',
                'nome' => 'Creme do Dia Hydramince Dynamisée 2',
                'descricao' => "FPS60 anti-UVA/B\nInnovare 6,0%\nB. Hydramince Dynamisée qsp 50g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'HYDRAMINCE DYNAMISEE 3',
                'nome' => 'Creme do Dia Hydramince Dynamisée 3',
                'descricao' => "FPS60 anti-UVA/B\nInnovare 6,0%\nB. Hydraction Suisse 10,0%\nB. Hydramince Dynamisée qsp 50g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'HYDRAMINCE SYNCHRON',
                'nome' => 'Creme do Dia Hydramince Synchron',
                'descricao' => "FPS60 Anti-UVA/B\nNutradvance 6,0%\nNeodeline 6,0%\nB. Hydramince Synchron qsp 50g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'face',
            ],
        ];
    }

    /**
     * Produtos de Limpeza
     */
    private function getLimpeza(): array
    {
        return [
            [
                'codigo' => 'BIONAISSANCE-SENSITIVE',
                'nome' => 'Creme Regenerador de Limpeza Bionaissance Sensitive',
                'descricao' => "Neodeline 6,0%\nB. Bionaissance Sensitive qsp 100g",
                'anotacoes' => 'Aplique no rosto massageando suavemente e enxague com água. A seguir, aplique os cremes indicados.',
                'local_uso' => 'face',
            ],
        ];
    }

    /**
     * Cremes para Olhos
     */
    private function getOlhos(): array
    {
        $anotacoesPadrao = 'Usar sobre os cremes do Dia e da Noite. Aplicar nas pálpebras inferiores, na lateral dos olhos, ao redor dos lábios e entre as sobrancelhas.';

        return [
            [
                'codigo' => 'HYALU CREAM',
                'nome' => 'Creme dos Olhos Hyalu Cream',
                'descricao' => "Innovare 6,0%\nB. Eyecontour Hyalu qsp 15g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'olhos',
            ],
            [
                'codigo' => 'HYALU-CREAM',
                'nome' => 'Creme dos Olhos Hyalu Cream',
                'descricao' => "Innovare 6,0%\nB. Eyecontour Hyalu qsp 15g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'olhos',
            ],
            [
                'codigo' => 'HYALU GEL',
                'nome' => 'Creme dos Olhos Hyalu Gel',
                'descricao' => "Innovare 6,0%\nB. Eyecontour Hyalu 20,0%\nÁgua purificada 5,0%\nB. Vitessence MaxiTRD qsp 15g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'olhos',
            ],
            [
                'codigo' => 'HYALU-GEL',
                'nome' => 'Creme dos Olhos Hyalu Gel',
                'descricao' => "Innovare 6,0%\nB. Eyecontour Hyalu 20,0%\nÁgua purificada 5,0%\nB. Vitessence MaxiTRD qsp 15g",
                'anotacoes' => $anotacoesPadrao,
                'local_uso' => 'olhos',
            ],
        ];
    }

    /**
     * Tratamentos Específicos
     */
    private function getTratamentos(): array
    {
        return [
            [
                'codigo' => 'TONALITE-__-G30',
                'nome' => 'Creme do Dia BB Cream Tonalité',
                'descricao' => "FPS60 anti-UVA/B\nNutradvance 6,0%\nB. Tonalité Nº __ qsp 30g",
                'anotacoes' => 'Aplique uma a duas vezes ao dia.',
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'REVELUMIE-G15',
                'nome' => 'Sérum de Vitamina C Revelumiê',
                'descricao' => "Neolipossome VC 6,0%\nB. Vitessence Revelumiê qsp 15g",
                'anotacoes' => 'Após lavar a pele, aplique-o no horário recomendado. Aguarde 30 segundos, e a seguir aplique os outros cremes.',
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'DESINFLAM-G15',
                'nome' => 'Gel Anti-inflamatório Desinflam',
                'descricao' => "Triancinolona acetonido 1,0%\nNeodeline 6,0%\nHerbactivine qs\nSepigel 305 3,0%\nB. Vitessence Maxi TRD qsp 15g",
                'anotacoes' => 'Aplicar 3-4 vezes ao dia apenas nas áreas recomendadas.',
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'DEMERANE-ULTRA-30G',
                'nome' => 'Creme Firmador Démerane Ultra',
                'descricao' => "Hydraintense 6,0%\nVitessence Revelumiê qs\nB. Démerane Ultra qsp 30g",
                'anotacoes' => 'Aplicar de manhã e a noite, 10 segundos antes dos cremes do Dia e da Noite, apenas nas bochechas e em todo o pescoço.',
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'DUO-MASK',
                'nome' => 'Creme Duo Mask',
                'descricao' => "Ácido glicólico 4,0%\nÁcido salicílico 4,0%\nMelavan qs\nBionaissance Sensitive qs\nB. Aquelane pura qsp 100g pH4,0",
                'anotacoes' => '1. Uso como Máscara: Aplique camada generosa e uniforme em toda a face, 1 vez ou mais por semana. Deixe agir por 15 min e enxágue bem. Evite pálpebras, contorno dos olhos e o vermelho dos lábios. 2. Uso para Limpeza: espalhe na face 2x ao dia e enxágue em seguida com água abundante.',
                'local_uso' => 'face',
            ],
            [
                'codigo' => 'AQUELANE-8-G30',
                'nome' => 'Loção Clareadora Aquelane 8',
                'descricao' => "Hidroquisan 8,0%\nNeodeline 6,0%\nVitessence 6,0%\nMelavan qs\nB. Aquelane qsp 30g",
                'anotacoes' => 'Aplique de manhã, antes do creme do dia, em toda a face ou como orientado. Se indicado, use no dorso das mãos e nos antebraços.',
                'local_uso' => 'face',
            ],
        ];
    }

    /**
     * Produtos para Mãos
     */
    private function getMaos(): array
    {
        return [
            [
                'codigo' => 'MAOS-NOITE-R0,035H12-DYN3',
                'nome' => 'Creme Mãos Noite Rejuvenescedor',
                'descricao' => "Tretinoína 0,035%\nHidroquisan 12%\nVitessence 6,0%\nNeolipossome VC qs\nB. Hydraction Suisse 30,0%\nB. Neodeline Nutrissome qsp 30g",
                'anotacoes' => 'Uso: aplicar fina camada a noite nas mãos e antebraços. Inicie alternando as noites conforme orientação. Se ocorrer vermelhidão, interrompa o uso e contate-nos.',
                'local_uso' => 'maos',
            ],
            [
                'codigo' => 'MAOS-DIA-DYNAMISEE-3',
                'nome' => 'Creme Dia Mãos Rejuvenescedor',
                'descricao' => "FPS60 anti-UVA/B\nInnovare 6,0%\nB. Hydraction Suisse 10,0%\nB. Hydramince Dynamisée qsp 50g",
                'anotacoes' => 'Aplicar nas mãos e antebraços 2 vezes ao dia, pela manhã e após almoço. Ao sol, reaplique a cada 2 horas.',
                'local_uso' => 'maos',
            ],
        ];
    }

    /**
     * Produtos para Corpo
     */
    private function getCorpo(): array
    {
        return [
            [
                'codigo' => 'CORPO-CREME',
                'nome' => 'Creme do Corpo Hydraessential',
                'descricao' => "Nutradvance 6,0%\nHydraessential qsp 200g",
                'anotacoes' => 'Aplique em todo o corpo após o banho. Se necessário, aplicar mais vezes ao dia. Uso ininterrupto para ação anti-flacidez, anti-estrias, anti-idade e nutrição profunda.',
                'local_uso' => 'corpo',
            ],
        ];
    }

    /**
     * Produtos para Cabelo
     */
    private function getCabelo(): array
    {
        return [
            [
                'codigo' => 'CAPILVELT',
                'nome' => 'Loção Capilar Capilvelt',
                'descricao' => "Minoxidil 5%\nD-pantenol 1%\nVitessence qs\nCapilvelt Pro FC 5%\nCapilvelt Acqua 15%\nÁgua purificada 10%\nSérum Capilar pH 4,5 qsp 100mL",
                'anotacoes' => 'Modo de uso: Aplicar 6 puffs no couro cabeludo (seco ou quase seco) 1 ou 2 vezes ao dia, massageando suavemente para espalhar o produto na pele do couro cabeludo. Evitar lavar os cabelos por 6 horas após a aplicação. O uso de secador, géis ou cremes capilares não interfere no efeito. Seu uso é diário e contínuo, para não perder os resultados que se iniciam dentro de 3 a 4 meses.',
                'local_uso' => 'cabelo',
            ],
        ];
    }
}
