<?php

namespace App\Exports;

use App\Models\Medico;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReceitasMedicoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $receitas;
    protected ?Medico $medico;

    public function __construct(Collection $receitas, ?Medico $medico = null)
    {
        $this->receitas = $receitas;
        $this->medico = $medico;
    }

    public function collection(): Collection
    {
        return $this->receitas;
    }

    public function headings(): array
    {
        return [
            'Número',
            'Data',
            'Paciente',
            'Médico',
            'Status',
            'Subtotal',
            'Desconto',
            'Frete',
            'Valor Total',
        ];
    }

    public function map($receita): array
    {
        return [
            $receita->numero,
            $receita->data_receita->format('d/m/Y'),
            $receita->paciente->nome,
            $receita->medico->nome,
            $receita->status_label,
            'R$ ' . number_format($receita->subtotal, 2, ',', '.'),
            'R$ ' . number_format($receita->desconto_valor, 2, ',', '.'),
            'R$ ' . number_format($receita->valor_frete, 2, ',', '.'),
            'R$ ' . number_format($receita->valor_total, 2, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return $this->medico
            ? 'Receitas - ' . substr($this->medico->nome, 0, 20)
            : 'Receitas por Médico';
    }
}

