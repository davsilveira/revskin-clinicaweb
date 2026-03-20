<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProdutosTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['', '', '', '', '', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'codigo',
            'nome',
            'descricao',
            'anotacoes',
            'modo_uso',
            'ativo',
        ];
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
