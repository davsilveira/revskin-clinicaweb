<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoCallcenter;
use App\Models\Medico;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AtendimentoCallcenter::with([
                'paciente:id,nome',
                'medico:id,nome',
                'receita:id,numero',
                'usuario:id,name',
            ])
            ->ativo()
            ->when($request->search, function ($q, $search) {
                $q->whereHas('paciente', fn($pq) => $pq->where('nome', 'like', "%{$search}%"));
            })
            ->when($request->medico_id, fn($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_abertura', '>=', $data))
            ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_abertura', '<=', $data));

        $ordenarPor = $request->get('ordenar_por', 'data_abertura');
        $ordem = $request->get('ordem', 'desc');

        $atendimentos = $query->orderBy($ordenarPor, $ordem)
            ->paginate(20)
            ->withQueryString();

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);
        $statusOptions = AtendimentoCallcenter::getStatusOptions();

        return Inertia::render('CallCenter/Index', [
            'atendimentos' => $atendimentos,
            'medicos' => $medicos,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'medico_id', 'status', 'data_inicio', 'data_fim', 'ordenar_por', 'ordem']),
        ]);
    }

    public function show(AtendimentoCallcenter $atendimento): Response
    {
        $atendimento->load([
            'paciente',
            'medico',
            'receita.itens.produto',
            'usuario',
            'usuarioAlteracao',
            'acompanhamentos.usuario',
        ]);

        $statusOptions = AtendimentoCallcenter::getStatusOptions();

        return Inertia::render('CallCenter/Show', [
            'atendimento' => $atendimento,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function atualizarStatus(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(AtendimentoCallcenter::getStatusOptions())),
            'acompanhamento' => 'nullable|string',
        ]);

        $atendimento->atualizarStatus(
            $validated['status'],
            $request->user(),
            $validated['acompanhamento'] ?? null
        );

        return back()->with('success', 'Status atualizado com sucesso!');
    }

    public function addAcompanhamento(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'descricao' => 'required|string',
        ]);

        $atendimento->acompanhamentos()->create([
            'usuario_id' => $request->user()->id,
            'descricao' => $validated['descricao'],
            'data_registro' => now(),
        ]);

        $atendimento->update([
            'data_alteracao' => now(),
            'usuario_alteracao_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Acompanhamento registrado com sucesso!');
    }

    public function cancelarMultiplos(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:atendimentos_callcenter,id',
        ]);

        AtendimentoCallcenter::whereIn('id', $validated['ids'])
            ->update([
                'status' => AtendimentoCallcenter::STATUS_CANCELADO,
                'data_alteracao' => now(),
                'usuario_alteracao_id' => $request->user()->id,
            ]);

        return back()->with('success', 'Atendimentos cancelados com sucesso!');
    }
}




