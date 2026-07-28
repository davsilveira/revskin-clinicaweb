<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProdutosTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected bool $includeAnotacoesInternas = false,
    ) {}

    public function array(): array
    {
        $cols = $this->includeAnotacoesInternas ? 6 : 5;

        return [array_fill(0, $cols, '')];
    }

    public function headings(): array
    {
        $headings = [
            'codigo',
            'descricao_formula',
            'modo_uso',
            'anotacoes_especialista',
        ];
        if ($this->includeAnotacoesInternas) {
            $headings[] = 'anotacoes_internas';
        }
        $headings[] = 'ativo';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Modelo';
    }
}
