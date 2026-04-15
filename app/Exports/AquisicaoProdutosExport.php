<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AquisicaoProdutosExport implements FromArray, ShouldAutoSize, WithCustomStartCell, WithEvents, WithTitle
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

    private function formatarCpf(?string $cpf): string
    {
        if (empty($cpf)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        return $cpf;
    }

    private function formatarTelefone(?string $telefone): string
    {
        if ($telefone === null || $telefone === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $telefone);
        if (strlen($digits) === 11) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 5).'-'.substr($digits, 7);
        }
        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 4).'-'.substr($digits, 6);
        }

        return $telefone;
    }

    private function brl(?float $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }

    public function array(): array
    {
        $rows = [];

        $dataInicioFormatada = Carbon::parse($this->dataInicio)->format('d/m/Y');
        $dataFimFormatada = Carbon::parse($this->dataFim)->format('d/m/Y');

        $rows[] = ['Relatório de Aquisição de Produtos'];
        $rows[] = ['Período:', $dataInicioFormatada.' a '.$dataFimFormatada];
        $rows[] = [];

        if ($this->isAdmin) {
            $rows[] = [
                'Produto',
                'Data da receita',
                'Data da manipulação',
                'Valor unitário',
                'Qtd',
                'Total',
            ];
        } else {
            $rows[] = [
                'Produto',
                'Data da receita',
                'Data da manipulação',
                'Qtd',
            ];
        }

        foreach ($this->dados['pacientes'] as $pacienteData) {
            $cpf = $this->formatarCpf($pacienteData['paciente']['cpf'] ?? null);
            $tel = $this->formatarTelefone($pacienteData['paciente']['telefone'] ?? null);
            $medicoNome = $this->isAdmin ? ($pacienteData['paciente']['medico_nome'] ?? '') : '';

            $pacienteRow = [strtoupper($pacienteData['paciente']['nome'])];
            if ($tel !== '') {
                $pacienteRow[] = $tel;
            }
            if ($cpf) {
                $pacienteRow[] = 'CPF: '.$cpf;
            }
            if ($medicoNome) {
                $pacienteRow[] = $medicoNome;
            }
            $rows[] = $pacienteRow;

            foreach ($pacienteData['produtos'] as $p) {
                if ($this->isAdmin) {
                    $rows[] = [
                        $p['produto_nome'],
                        $p['data_receita'],
                        $p['data_aquisicao'],
                        $this->brl((float) ($p['valor_unitario'] ?? 0)),
                        (int) ($p['quantidade'] ?? 0),
                        $this->brl((float) ($p['valor_total'] ?? 0)),
                    ];
                } else {
                    $rows[] = [
                        $p['produto_nome'],
                        $p['data_receita'],
                        $p['data_aquisicao'],
                        (int) ($p['quantidade'] ?? 0),
                    ];
                }
            }

            $tot = $pacienteData['totais'] ?? [];
            if ($this->isAdmin) {
                $rows[] = [
                    'Qtd. Produtos: '.(int) ($tot['qtd_produtos'] ?? 0),
                    '',
                    '',
                    'Vlr. Frete: '.$this->brl((float) ($tot['vlr_frete'] ?? 0)),
                    'Vlr. Desconto: '.$this->brl((float) ($tot['vlr_desconto'] ?? 0)),
                    'Total: '.$this->brl((float) ($tot['total'] ?? 0)),
                ];
            } else {
                $rows[] = [
                    'Qtd. Produtos: '.(int) ($tot['qtd_produtos'] ?? 0),
                    '',
                    '',
                    '',
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $sheet->getHighestColumn();
                if ($lastRow < 1) {
                    return;
                }
                $sheet->getStyle('A1:'.$lastCol.$lastRow)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $endCol = $this->isAdmin ? 'F' : 'D';

                for ($row = 1; $row <= $lastRow; $row++) {
                    $cellA = $sheet->getCell('A'.$row)->getValue();
                    if (! is_string($cellA) || ! str_starts_with($cellA, 'Qtd. Produtos:')) {
                        continue;
                    }
                    $sheet->getStyle('A'.$row.':'.$endCol.$row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E5E7EB'],
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '111827'],
                        ],
                    ]);
                    $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    if ($this->isAdmin) {
                        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }
            },
        ];
    }
}
