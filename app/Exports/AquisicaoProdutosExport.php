<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithTitle;

class AquisicaoProdutosExport implements FromArray, ShouldAutoSize, WithCustomStartCell, WithTitle
{
    protected array $dados;
    protected string $dataInicio;
    protected string $dataFim;
    protected bool $isAdmin;

    public function __construct(array $dados, string $dataInicio, string $dataFim, bool $isAdmin = false)
    {
        $this->dados = $dados;
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Calcula última modificação (max data_receita) por nome de produto.
     */
    private function ultimaModificacaoPorProduto(array $produtos): array
    {
        $porProduto = [];
        foreach ($produtos as $p) {
            $nome = $p['produto_nome'];
            if (!isset($porProduto[$nome])) {
                $porProduto[$nome] = $p['data_receita'];
            } else {
                try {
                    $atual = Carbon::createFromFormat('d/m/Y', $p['data_receita']);
                    $max = Carbon::createFromFormat('d/m/Y', $porProduto[$nome]);
                    if ($atual->gt($max)) {
                        $porProduto[$nome] = $p['data_receita'];
                    }
                } catch (\Exception $e) {
                    if (strcmp($p['data_receita'], $porProduto[$nome]) > 0) {
                        $porProduto[$nome] = $p['data_receita'];
                    }
                }
            }
        }
        return $porProduto;
    }

    private function formatarCpf(?string $cpf): string
    {
        if (empty($cpf)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }
        return $cpf;
    }

    public function array(): array
    {
        $rows = [];

        $dataInicioFormatada = Carbon::parse($this->dataInicio)->format('d/m/Y');
        $dataFimFormatada = Carbon::parse($this->dataFim)->format('d/m/Y');

        $rows[] = ['Relatório de Aquisição de Produtos'];
        $rows[] = ['Período:', $dataInicioFormatada . ' a ' . $dataFimFormatada];
        $rows[] = [];

        foreach ($this->dados['pacientes'] as $pacienteData) {
            $cpf = $this->formatarCpf($pacienteData['paciente']['cpf'] ?? null);
            $medicoNome = $this->isAdmin ? ($pacienteData['paciente']['medico_nome'] ?? '') : '';

            $pacienteRow = [strtoupper($pacienteData['paciente']['nome'])];
            if ($cpf) {
                $pacienteRow[] = 'CPF: ' . $cpf;
            }
            if ($medicoNome) {
                $pacienteRow[] = 'Dra. ' . $medicoNome;
            }
            $rows[] = $pacienteRow;

            $ultimaModPorProduto = $this->ultimaModificacaoPorProduto($pacienteData['produtos']);
            foreach ($pacienteData['produtos'] as $p) {
                $rows[] = [
                    $p['produto_nome'],
                    $ultimaModPorProduto[$p['produto_nome']] ?? $p['data_receita'],
                    $p['data_aquisicao'],
                    (int) ($p['quantidade'] ?? 0),
                ];
            }

            $rows[] = [];
        }

        $rows[] = ['Data de Impressão:', now()->format('d/m/Y H:i')];

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
