<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogoProdutosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected Collection $produtos;

    public function __construct(Collection $produtos)
    {
        $this->produtos = $produtos;
    }

    public function collection(): Collection
    {
        return $this->produtos;
    }

    public function headings(): array
    {
        return [
            'Nome (Tipo)',
            'Código',
            'Fórmula (Etiqueta)',
            'Modo de Uso',
            'Anotações dos Especialistas',
        ];
    }

    public function map($produto): array
    {
        $descricao = $produto->descricao ?? '';
        $descricao = str_replace(['\\n', '/n'], "\n", $descricao);

        $modoUso = $produto->modo_uso ?? '';
        $modoUso = str_replace(['\\n', '/n'], "\n", $modoUso);

        return [
            $produto->nome,
            $produto->codigo,
            $descricao,
            $modoUso,
            $produto->anotacoes ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 35,
            'D' => 35,
            'E' => 35,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A:E')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A:E')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Catálogo de Produtos';
    }
}
