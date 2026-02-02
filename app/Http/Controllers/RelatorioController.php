<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Receita;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class RelatorioController extends Controller
{
    /**
     * Show reports dashboard.
     */
    public function index(): Response
    {
        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Relatorios/Index', [
            'medicos' => $medicos,
        ]);
    }

    /**
     * Receitas por médico report.
     */
    public function receitasPorMedico(Request $request): Response
    {
        $request->validate([
            'medico_id' => 'nullable|exists:medicos,id',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'per_page' => 'nullable|integer|in:15,30,50,100',
        ]);

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        $dados = null;
        // Executar query se houver filtros ou se houver paginação (page parameter)
        if ($request->has('medico_id') || $request->has('data_inicio') || $request->has('page')) {
            $query = Receita::with(['paciente:id,nome', 'medico:id,nome'])
                ->whereIn('status', ['finalizada', 'rascunho'])
                ->when($request->medico_id, fn($q, $id) => $q->where('medico_id', $id))
                ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_receita', '>=', $data))
                ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_receita', '<=', $data))
                ->orderBy('data_receita', 'desc');

            // Calcular totais antes da paginação (clonar query para não afetar paginação)
            $totaisQuery = clone $query;
            $totais = [
                'quantidade' => $totaisQuery->count(),
                'valor_total' => $totaisQuery->sum('valor_total'),
            ];

            // Aplicar paginação preservando query strings
            $perPage = $request->input('per_page', 15);
            $receitas = $query->paginate($perPage)->withQueryString();

            $dados = [
                'receitas' => $receitas,
                'totais' => $totais,
            ];
        }

        return Inertia::render('Relatorios/ReceitasMedico', [
            'medicos' => $medicos,
            'dados' => $dados,
            'filters' => $request->only(['medico_id', 'data_inicio', 'data_fim', 'per_page']),
        ]);
    }

    /**
     * Export receitas por médico.
     */
    public function exportReceitasMedico(Request $request, string $format)
    {
        $request->validate([
            'medico_id' => 'nullable|exists:medicos,id',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',
        ]);

        $query = Receita::with(['paciente:id,nome', 'medico:id,nome'])
            ->whereIn('status', ['finalizada', 'rascunho'])
            ->when($request->medico_id, fn($q, $id) => $q->where('medico_id', $id))
            ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_receita', '>=', $data))
            ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_receita', '<=', $data))
            ->orderBy('data_receita', 'desc');

        $receitas = $query->get();
        $medico = $request->medico_id ? Medico::find($request->medico_id) : null;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.relatorio-receitas-medico', [
                'receitas' => $receitas,
                'medico' => $medico,
                'dataInicio' => $request->data_inicio,
                'dataFim' => $request->data_fim,
                'totais' => [
                    'quantidade' => $receitas->count(),
                    'valor_total' => $receitas->sum('valor_total'),
                ],
            ]);

            return $pdf->download('relatorio-receitas-medico.pdf');
        }

        if ($format === 'xlsx') {
            return Excel::download(
                new \App\Exports\ReceitasMedicoExport($receitas, $medico),
                'relatorio-receitas-medico.xlsx'
            );
        }

        abort(400, 'Formato não suportado');
    }
}

