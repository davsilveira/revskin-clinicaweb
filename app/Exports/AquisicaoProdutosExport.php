<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class AquisicaoProdutosExport implements FromArray, WithCustomStartCell, WithTitle
{
    protected array $dados;
    protected string $dataInicio;
    protected string $dataFim;

    public function __construct(array $dados, string $dataInicio, string $dataFim)
    {
        $this->dados = $dados;
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
    }

    public function array(): array
    {
        $rows = [];
        
        // Linha 1: Título
        $rows[] = ['Relatório', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        
        // Linha 2: Período
        $dataInicioFormatada = Carbon::parse($this->dataInicio)->format('d/m/Y');
        $dataFimFormatada = Carbon::parse($this->dataFim)->format('d/m/Y');
        $rows[] = ['', 'Periodo', $dataInicioFormatada, '', '', 'a', $dataFimFormatada, '', '', '', '', '', '', '', '', '', '', '', ''];
        
        // Linha 3: Vazia
        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        
        $qtdTotalProdutos = 0;
        $valorTotalProdutos = 0;
        $pageNumber = 1;
        $itemsPerPage = 50; // Aproximadamente 50 itens por página
        $currentPageItems = 0;
        $totalPages = 1;
        
        // Calcular total de páginas
        $totalItems = 0;
        foreach ($this->dados['pacientes'] as $pacienteData) {
            $totalItems += count($pacienteData['produtos']) + 2; // +2 para cabeçalho e rodapé do paciente
        }
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        // Processar cada paciente
        foreach ($this->dados['pacientes'] as $pacienteData) {
            // Cabeçalho do paciente: Nome + Telefone
            $telefoneFormatado = '';
            if ($pacienteData['paciente']['telefone']) {
                $telefone = preg_replace('/\D/', '', $pacienteData['paciente']['telefone']);
                if (strlen($telefone) === 11) {
                    $telefoneFormatado = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
                } elseif (strlen($telefone) === 10) {
                    $telefoneFormatado = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
                } else {
                    $telefoneFormatado = $pacienteData['paciente']['telefone'];
                }
            }
            
            $pacienteRow = array_fill(0, 19, '');
            $pacienteRow[0] = "\t" . strtoupper($pacienteData['paciente']['nome']);
            if ($telefoneFormatado) {
                $pacienteRow[10] = $telefoneFormatado;
            }
            $rows[] = $pacienteRow;
            $currentPageItems++;
            
            // Produtos do paciente
            foreach ($pacienteData['produtos'] as $produto) {
                $produtoRow = array_fill(0, 19, '');
                $produtoRow[0] = $produto['produto_nome'];
                $produtoRow[7] = $produto['data_receita'];
                $produtoRow[9] = $produto['data_aquisicao'];
                $produtoRow[13] = number_format($produto['valor_unitario'], 2, '.', '');
                $produtoRow[16] = $produto['quantidade'];
                $produtoRow[17] = number_format($produto['valor_total'], 2, '.', '');
                
                $rows[] = $produtoRow;
                $currentPageItems++;
                $qtdTotalProdutos++;
                $valorTotalProdutos += $produto['valor_total'];
                
                // Adicionar número de página se necessário
                if ($currentPageItems >= $itemsPerPage && $pageNumber < $totalPages) {
                    $pageRow = array_fill(0, 19, '');
                    $pageRow[2] = 'Data de Impressão:';
                    $pageRow[3] = now()->format('d/m/Y H.i.s');
                    $pageRow[9] = 'Pagina ' . $pageNumber . ' of';
                    $pageRow[10] = ' ' . $totalPages;
                    $rows[] = $pageRow;
                    $pageNumber++;
                    $currentPageItems = 0;
                }
            }
            
            // Rodapé do paciente
            $footerRow = array_fill(0, 19, '');
            $footerRow[0] = ',Qtd. Produtos: ' . $pacienteData['totais']['qtd_produtos'];
            $footerRow[2] = 'Vlr.Frete: ' . number_format($pacienteData['totais']['vlr_frete'], 2, '.', '');
            $footerRow[5] = 'Vlr.Desconto: ' . number_format($pacienteData['totais']['vlr_desconto'], 2, '.', '');
            $footerRow[10] = 'Total:';
            $footerRow[13] = number_format($pacienteData['totais']['total'], 2, '.', '');
            // Se o total tem mais de 3 dígitos antes da vírgula, usar formatação com vírgula
            if ($pacienteData['totais']['total'] >= 1000) {
                $footerRow[13] = number_format($pacienteData['totais']['total'], 2, '.', ',');
            }
            $rows[] = $footerRow;
            $currentPageItems++;
            
            // Adicionar número de página se necessário após rodapé
            if ($currentPageItems >= $itemsPerPage && $pageNumber < $totalPages) {
                $pageRow = array_fill(0, 19, '');
                $pageRow[2] = 'Data de Impressão:';
                $pageRow[3] = now()->format('d/m/Y H.i.s');
                $pageRow[9] = 'Pagina ' . $pageNumber . ' of';
                $pageRow[10] = ' ' . $totalPages;
                $rows[] = $pageRow;
                $pageNumber++;
                $currentPageItems = 0;
            }
        }
        
        // Totais gerais
        $totaisRow = array_fill(0, 19, '');
        $totaisRow[0] = ',Qtd. Total Produtos: ' . $qtdTotalProdutos;
        $totaisRow[10] = 'Valor Total de Produtos:';
        // Formatar com vírgula como separador de milhar
        $totaisRow[13] = number_format($valorTotalProdutos, 2, '.', ',');
        $rows[] = $totaisRow;
        
        // Rodapé final
        $footerFinalRow = array_fill(0, 19, '');
        $footerFinalRow[2] = 'Data de Impressão:';
        $footerFinalRow[3] = now()->format('d/m/Y H.i.s');
        $footerFinalRow[9] = 'Pagina ' . $pageNumber . ' of';
        $footerFinalRow[10] = ' ' . $totalPages;
        $rows[] = $footerFinalRow;
        
        return $rows;
    }

    public function startCell(): string
    {
        return 'A1';
    }

    public function title(): string
    {
        return 'Aquisição de Produtos';
    }
}
