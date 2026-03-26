<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProdutosAdminExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $produtos;

    public function __construct(
        Collection $produtos,
        protected bool $includeAnotacoesInternas = false,
    ) {
        $this->produtos = $produtos;
    }

    public function collection(): Collection
    {
        return $this->produtos;
    }

    public function headings(): array
    {
        $headings = [
            'codigo',
            'codigo_cq',
            'nome',
            'descricao_formula',
            'modo_uso',
            'anotacoes_especialista',
        ];
        if ($this->includeAnotacoesInternas) {
            $headings[] = 'anotacoes_internas';
        }
        $headings = array_merge($headings, [
            'preco',
            'unidade',
            'tiny_id',
            'tiny_sync_at',
            'ativo',
        ]);

        return $headings;
    }

    public function map($produto): array
    {
        $descricao = $produto->descricao ?? '';
        $descricao = str_replace(['\\n', '/n'], "\n", $descricao);
        $modoUso = $produto->modo_uso ?? '';
        $modoUso = str_replace(['\\n', '/n'], "\n", $modoUso);
        $anotacoes = $produto->anotacoes ?? '';
        $anotacoes = str_replace(['\\n', '/n'], "\n", $anotacoes);
        $anotacoesInternas = $produto->anotacoes_internas ?? '';
        $anotacoesInternas = str_replace(['\\n', '/n'], "\n", $anotacoesInternas);

        $row = [
            $produto->codigo,
            $produto->codigo_cq ?? '',
            $produto->nome ?? '',
            $descricao,
            $modoUso,
            $anotacoes,
        ];
        if ($this->includeAnotacoesInternas) {
            $row[] = $anotacoesInternas;
        }
        $row = array_merge($row, [
            $produto->preco ? (float) $produto->preco : '',
            $produto->unidade ?? '',
            $produto->tiny_id ?? '',
            $produto->tiny_sync_at ? $produto->tiny_sync_at->format('Y-m-d H:i:s') : '',
            $produto->ativo ? '1' : '0',
        ]);

        return $row;
    }

    public function columnWidths(): array
    {
        if ($this->includeAnotacoesInternas) {
            return [
                'A' => 22,
                'B' => 15,
                'C' => 35,
                'D' => 40,
                'E' => 40,
                'F' => 30,
                'G' => 30,
                'H' => 10,
                'I' => 8,
                'J' => 12,
                'K' => 18,
                'L' => 6,
            ];
        }

        return [
            'A' => 22,
            'B' => 15,
            'C' => 35,
            'D' => 40,
            'E' => 40,
            'F' => 30,
            'G' => 10,
            'H' => 8,
            'I' => 12,
            'J' => 18,
            'K' => 6,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->includeAnotacoesInternas ? 'L' : 'K';
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
