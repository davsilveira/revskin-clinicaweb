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

class ProdutosAdminExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
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
            'codigo',
            'codigo_cq',
            'nome',
            'descricao',
            'anotacoes',
            'modo_uso',
            'preco',
            'unidade',
            'tiny_id',
            'tiny_sync_at',
            'ativo',
        ];
    }

    public function map($produto): array
    {
        $descricao = $produto->descricao ?? '';
        $descricao = str_replace(['\\n', '/n'], "\n", $descricao);
        $modoUso = $produto->modo_uso ?? '';
        $modoUso = str_replace(['\\n', '/n'], "\n", $modoUso);
        $anotacoes = $produto->anotacoes ?? '';
        $anotacoes = str_replace(['\\n', '/n'], "\n", $anotacoes);

        return [
            $produto->codigo,
            $produto->codigo_cq ?? '',
            $produto->nome ?? '',
            $descricao,
            $anotacoes,
            $modoUso,
            $produto->preco ? (float) $produto->preco : '',
            $produto->unidade ?? '',
            $produto->tiny_id ?? '',
            $produto->tiny_sync_at ? $produto->tiny_sync_at->format('Y-m-d H:i:s') : '',
            $produto->ativo ? '1' : '0',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 15,
            'C' => 35,
            'D' => 40,
            'E' => 30,
            'F' => 40,
            'G' => 10,
            'H' => 8,
            'I' => 12,
            'J' => 18,
            'K' => 6,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'K';
        $sheet->getStyle("A:{$lastCol}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A:{$lastCol}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

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
        return 'Produtos';
    }
}
