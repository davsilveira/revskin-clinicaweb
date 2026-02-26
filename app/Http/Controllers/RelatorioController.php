<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use App\Models\Paciente;
use App\Models\Produto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    /**
     * Show reports dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $isMedico = $user->isMedico();

        $relatoriosDisponiveis = [];
        
        if ($isAdmin) {
            $relatoriosDisponiveis[] = [
                'id' => 'receitas-medico',
                'titulo' => 'Receitas por Médico',
                'descricao' => 'Relatório detalhado de receitas agrupadas por médico',
                'rota' => '/relatorios/receitas-medico',
                'icone' => 'receita',
            ];
        }
        
        if ($isAdmin || $isMedico) {
            $relatoriosDisponiveis[] = [
                'id' => 'aquisicao-produtos',
                'titulo' => 'Aquisição de Produtos',
                'descricao' => 'Relatório de produtos adquiridos por paciente no período',
                'rota' => '/relatorios/aquisicao-produtos',
                'icone' => 'produtos',
            ];
        }

        return Inertia::render('Relatorios/Index', [
            'relatorios' => $relatoriosDisponiveis,
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

        $medicos = Medico::ativo()
            ->join('users', 'users.medico_id', '=', 'medicos.id')
            ->orderBy('users.name')
            ->select('medicos.id')
            ->get()
            ->load('linkedUser:id,name,medico_id');

        $dados = null;
        // Executar query se houver filtros ou se houver paginação (page parameter)
        if ($request->has('medico_id') || $request->has('data_inicio') || $request->has('page')) {
            $query = Receita::with(['paciente:id,nome', 'medico:id', 'medico.linkedUser:id,name,medico_id'])
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

        $query = Receita::with(['paciente:id,nome', 'medico:id', 'medico.linkedUser:id,name,medico_id'])
            ->whereIn('status', ['finalizada', 'rascunho'])
            ->when($request->medico_id, fn($q, $id) => $q->where('medico_id', $id))
            ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_receita', '>=', $data))
            ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_receita', '<=', $data))
            ->orderBy('data_receita', 'desc');

        $receitas = $query->get();
        $medico = $request->medico_id ? Medico::with('linkedUser:id,name,medico_id')->find($request->medico_id) : null;

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

    /**
     * Relatório de Aquisição de Produtos.
     */
    public function aquisicaoProdutos(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $isMedico = $user->isMedico();

        if (!$isAdmin && !$isMedico) {
            abort(403, 'Acesso não autorizado.');
        }

        $request->validate([
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'medico_id' => 'nullable|exists:medicos,id',
            'medico_ids' => 'nullable|array',
            'medico_ids.*' => 'exists:medicos,id',
            'paciente_id' => 'nullable|exists:pacientes,id',
            'paciente_ids' => 'nullable|array',
            'paciente_ids.*' => 'exists:pacientes,id',
            'produto_id' => 'nullable|exists:produtos,id',
            'produto_ids' => 'nullable|array',
            'produto_ids.*' => 'exists:produtos,id',
        ]);

        // Se médico, filtrar automaticamente pelo médico logado
        $medicoIdsFiltro = null;
        if ($isMedico) {
            $medicoIdsFiltro = [$user->medico_id];
        } elseif ($request->has('medico_ids') && is_array($request->medico_ids) && count($request->medico_ids) > 0) {
            $medicoIdsFiltro = $request->medico_ids;
        } elseif ($request->has('medico_id')) {
            $medicoIdsFiltro = [$request->medico_id];
        }

        $medicos = $isAdmin
            ? Medico::ativo()->join('users', 'users.medico_id', '=', 'medicos.id')->orderBy('users.name')->select('medicos.id')->get()->load('linkedUser:id,name,medico_id')
            : collect([]);
        $pacientes = Paciente::ativo()->orderBy('nome')->get(['id', 'nome']);
        $produtos = Produto::ativo()->orderBy('nome')->get(['id', 'nome']);

        // Preparar datas padrão para o frontend
        $dataInicio = $request->get('data_inicio', now()->subDays(7)->format('Y-m-d'));
        $dataFim = $request->get('data_fim', now()->format('Y-m-d'));
        
        $dados = null;
        
        // Executar query apenas se houver parâmetros de filtro na requisição (usuário clicou em Filtrar)
        // Verifica se há algum parâmetro além dos valores padrão ou outros filtros aplicados
        $hasFilters = $request->has('data_inicio') || $request->has('data_fim') || 
                     $request->has('medico_ids') || $request->has('medico_id') || 
                     $request->has('paciente_ids') || $request->has('paciente_id') ||
                     $request->has('produto_ids') || $request->has('produto_id');
        
        if ($hasFilters) {
            $request->merge([
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
            ]);
            $dados = $this->buscarAquisicoes($request, $medicoIdsFiltro);
        }

        // Preparar filters para retornar ao frontend (sempre com datas padrão)
        $filters = [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
        
        if ($request->has('medico_ids')) {
            $filters['medico_ids'] = $request->medico_ids;
        }
        if ($request->has('paciente_ids')) {
            $filters['paciente_ids'] = $request->paciente_ids;
        }
        if ($request->has('produto_ids')) {
            $filters['produto_ids'] = $request->produto_ids;
        }

        return Inertia::render('Relatorios/AquisicaoProdutos', [
            'medicos' => $medicos,
            'pacientes' => $pacientes,
            'produtos' => $produtos,
            'dados' => $dados,
            'filters' => $filters,
            'isAdmin' => $isAdmin,
            'isMedico' => $isMedico,
        ]);
    }

    /**
     * Export relatório de aquisição de produtos.
     */
    public function exportAquisicaoProdutos(Request $request, string $format)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $isMedico = $user->isMedico();

        if (!$isAdmin && !$isMedico) {
            abort(403, 'Acesso não autorizado.');
        }

        $request->validate([
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'medico_id' => 'nullable|exists:medicos,id',
            'medico_ids' => 'nullable|array',
            'medico_ids.*' => 'exists:medicos,id',
            'paciente_id' => 'nullable|exists:pacientes,id',
            'paciente_ids' => 'nullable|array',
            'paciente_ids.*' => 'exists:pacientes,id',
            'produto_id' => 'nullable|exists:produtos,id',
            'produto_ids' => 'nullable|array',
            'produto_ids.*' => 'exists:produtos,id',
        ]);

        // Se médico, filtrar automaticamente pelo médico logado
        $medicoIdsFiltro = null;
        if ($isMedico) {
            $medicoIdsFiltro = [$user->medico_id];
        } elseif ($request->has('medico_ids') && is_array($request->medico_ids) && count($request->medico_ids) > 0) {
            $medicoIdsFiltro = $request->medico_ids;
        } elseif ($request->has('medico_id')) {
            $medicoIdsFiltro = [$request->medico_id];
        }

        $dados = $this->buscarAquisicoes($request, $medicoIdsFiltro, false);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.relatorio-aquisicao-produtos', [
                'dados' => $dados,
                'dataInicio' => $request->data_inicio,
                'dataFim' => $request->data_fim,
                'isAdmin' => $isAdmin,
            ]);

            return $pdf->download('relatorio-aquisicao-produtos.pdf');
        }

        if ($format === 'xlsx' || $format === 'csv') {
            return Excel::download(
                new \App\Exports\AquisicaoProdutosExport($dados, $request->data_inicio, $request->data_fim, $isAdmin),
                'relatorio-aquisicao-produtos.' . $format
            );
        }

        abort(400, 'Formato não suportado');
    }

    /**
     * Buscar aquisições agrupadas por paciente.
     */
    private function buscarAquisicoes(Request $request, ?array $medicoIdsFiltro, bool $paginate = true): array
    {
        // Buscar aquisições da tabela receita_item_aquisicoes
        $queryAquisicoes = ReceitaItemAquisicao::query()
            ->with([
                'receitaItem.receita.paciente',
                'receitaItem.receita.medico',
                'receitaItem.produto',
                'receitaItem.receita' => function($q) {
                    $q->select('id', 'paciente_id', 'medico_id', 'data_receita', 'valor_frete', 'desconto_valor');
                }
            ])
            ->whereHas('receitaItem.receita', function($q) use ($request, $medicoIdsFiltro) {
                $q->whereIn('status', ['finalizada', 'rascunho']);
                
                if ($medicoIdsFiltro && count($medicoIdsFiltro) > 0) {
                    $q->whereIn('medico_id', $medicoIdsFiltro);
                }
                
                $pacienteIds = $request->has('paciente_ids') && is_array($request->paciente_ids) && count($request->paciente_ids) > 0
                    ? $request->paciente_ids
                    : ($request->paciente_id ? [$request->paciente_id] : null);
                
                if ($pacienteIds) {
                    $q->whereIn('paciente_id', $pacienteIds);
                }
                
                if ($request->data_inicio) {
                    $q->whereDate('data_receita', '>=', $request->data_inicio);
                }
                
                if ($request->data_fim) {
                    $q->whereDate('data_receita', '<=', $request->data_fim);
                }
            })
            ->when($request->data_inicio, function($q, $data) {
                $q->whereDate('data_aquisicao', '>=', $data);
            })
            ->when($request->data_fim, function($q, $data) {
                $q->whereDate('data_aquisicao', '<=', $data);
            })
            ->when($request->has('produto_ids') && is_array($request->produto_ids) && count($request->produto_ids) > 0, function($q) use ($request) {
                $q->whereHas('receitaItem', function($subQ) use ($request) {
                    $subQ->whereIn('produto_id', $request->produto_ids);
                });
            })
            ->when($request->produto_id && !$request->has('produto_ids'), function($q, $produtoId) {
                $q->whereHas('receitaItem', function($subQ) use ($produtoId) {
                    $subQ->where('produto_id', $produtoId);
                });
            });

        // Também buscar itens com data_aquisicao legacy (sem registro em receita_item_aquisicoes)
        $queryLegacy = ReceitaItem::query()
            ->with(['receita.paciente', 'receita.medico.linkedUser:id,name,medico_id', 'produto'])
            ->whereNotNull('data_aquisicao')
            ->whereHas('receita', function($q) use ($request, $medicoIdsFiltro) {
                $q->whereIn('status', ['finalizada', 'rascunho']);
                
                if ($medicoIdsFiltro && count($medicoIdsFiltro) > 0) {
                    $q->whereIn('medico_id', $medicoIdsFiltro);
                }
                
                $pacienteIds = $request->has('paciente_ids') && is_array($request->paciente_ids) && count($request->paciente_ids) > 0
                    ? $request->paciente_ids
                    : ($request->paciente_id ? [$request->paciente_id] : null);
                
                if ($pacienteIds) {
                    $q->whereIn('paciente_id', $pacienteIds);
                }
                
                if ($request->data_inicio) {
                    $q->whereDate('data_receita', '>=', $request->data_inicio);
                }
                
                if ($request->data_fim) {
                    $q->whereDate('data_receita', '<=', $request->data_fim);
                }
            })
            ->when($request->data_inicio, function($q, $data) {
                $q->whereDate('data_aquisicao', '>=', $data);
            })
            ->when($request->data_fim, function($q, $data) {
                $q->whereDate('data_aquisicao', '<=', $data);
            })
            ->when($request->has('produto_ids') && is_array($request->produto_ids) && count($request->produto_ids) > 0, function($q) use ($request) {
                $q->whereIn('produto_id', $request->produto_ids);
            })
            ->when($request->produto_id && !$request->has('produto_ids'), function($q, $produtoId) {
                $q->where('produto_id', $produtoId);
            })
            ->whereDoesntHave('aquisicoes'); // Excluir itens que já têm aquisições na tabela nova

        // Combinar resultados
        $aquisicoes = $queryAquisicoes->get();
        $legacyItems = $queryLegacy->get();

        // Agrupar por paciente
        $agrupadoPorPaciente = [];
        
        // Processar aquisições da tabela nova
        foreach ($aquisicoes as $aquisicao) {
            $receitaItem = $aquisicao->receitaItem;
            $receita = $receitaItem->receita;
            $paciente = $receita->paciente;
            $produto = $receitaItem->produto;
            
            if (!$paciente || !$produto) {
                continue;
            }
            
            $pacienteId = $paciente->id;
            
            if (!isset($agrupadoPorPaciente[$pacienteId])) {
                $agrupadoPorPaciente[$pacienteId] = [
                    'paciente' => [
                        'id' => $paciente->id,
                        'nome' => $paciente->nome,
                        'telefone' => $paciente->telefone_principal,
                        'cpf' => $paciente->cpf ?? '',
                        'medico_nome' => $receita->medico->nome ?? '',
                    ],
                    'produtos' => [],
                    'receitas_unicas' => [],
                    'totais' => [
                        'qtd_produtos' => 0,
                        'vlr_frete' => 0,
                        'vlr_desconto' => 0,
                        'total' => 0,
                    ],
                ];
            }
            
            $produtoKey = $receitaItem->id . '_' . $aquisicao->data_aquisicao->format('Y-m-d');
            
            if (!isset($agrupadoPorPaciente[$pacienteId]['produtos'][$produtoKey])) {
                $agrupadoPorPaciente[$pacienteId]['produtos'][$produtoKey] = [
                    'produto_nome' => $produto->nome,
                    'data_receita' => $receita->data_receita->format('d/m/Y'),
                    'data_aquisicao' => $aquisicao->data_aquisicao->format('d/m/Y'),
                    'valor_unitario' => $receitaItem->valor_unitario,
                    'quantidade' => $receitaItem->quantidade,
                    'valor_total' => $receitaItem->valor_total,
                ];
                
                $agrupadoPorPaciente[$pacienteId]['totais']['total'] += $receitaItem->valor_total;
                $agrupadoPorPaciente[$pacienteId]['totais']['qtd_produtos']++;
            }
            
            // Adicionar receita única para cálculo de frete e desconto
            if (!in_array($receita->id, $agrupadoPorPaciente[$pacienteId]['receitas_unicas'])) {
                $agrupadoPorPaciente[$pacienteId]['receitas_unicas'][] = $receita->id;
                $agrupadoPorPaciente[$pacienteId]['totais']['vlr_frete'] += $receita->valor_frete ?? 0;
                $agrupadoPorPaciente[$pacienteId]['totais']['vlr_desconto'] += $receita->desconto_valor ?? 0;
            }
        }
        
        // Processar itens legacy
        foreach ($legacyItems as $item) {
            $receita = $item->receita;
            $paciente = $receita->paciente;
            $produto = $item->produto;
            
            if (!$paciente || !$produto) {
                continue;
            }
            
            $pacienteId = $paciente->id;
            
            if (!isset($agrupadoPorPaciente[$pacienteId])) {
                $agrupadoPorPaciente[$pacienteId] = [
                    'paciente' => [
                        'id' => $paciente->id,
                        'nome' => $paciente->nome,
                        'telefone' => $paciente->telefone_principal,
                        'cpf' => $paciente->cpf ?? '',
                        'medico_nome' => $receita->medico->nome ?? '',
                    ],
                    'produtos' => [],
                    'receitas_unicas' => [],
                    'totais' => [
                        'qtd_produtos' => 0,
                        'vlr_frete' => 0,
                        'vlr_desconto' => 0,
                        'total' => 0,
                    ],
                ];
            }
            
            $produtoKey = $item->id . '_' . ($item->data_aquisicao ? $item->data_aquisicao->format('Y-m-d') : 'legacy');
            
            if (!isset($agrupadoPorPaciente[$pacienteId]['produtos'][$produtoKey])) {
                $agrupadoPorPaciente[$pacienteId]['produtos'][$produtoKey] = [
                    'produto_nome' => $produto->nome,
                    'data_receita' => $receita->data_receita->format('d/m/Y'),
                    'data_aquisicao' => $item->data_aquisicao ? $item->data_aquisicao->format('d/m/Y') : $receita->data_receita->format('d/m/Y'),
                    'valor_unitario' => $item->valor_unitario,
                    'quantidade' => $item->quantidade,
                    'valor_total' => $item->valor_total,
                ];
                
                $agrupadoPorPaciente[$pacienteId]['totais']['total'] += $item->valor_total;
                $agrupadoPorPaciente[$pacienteId]['totais']['qtd_produtos']++;
            }
            
            // Adicionar receita única para cálculo de frete e desconto
            if (!in_array($receita->id, $agrupadoPorPaciente[$pacienteId]['receitas_unicas'])) {
                $agrupadoPorPaciente[$pacienteId]['receitas_unicas'][] = $receita->id;
                $agrupadoPorPaciente[$pacienteId]['totais']['vlr_frete'] += $receita->valor_frete ?? 0;
                $agrupadoPorPaciente[$pacienteId]['totais']['vlr_desconto'] += $receita->desconto_valor ?? 0;
            }
        }
        
        // Converter arrays associativos em arrays indexados e ordenar por nome do paciente
        $resultado = collect($agrupadoPorPaciente)
            ->map(function($pacienteData) {
                $pacienteData['produtos'] = array_values($pacienteData['produtos']);
                unset($pacienteData['receitas_unicas']);
                return $pacienteData;
            })
            ->sortBy('paciente.nome')
            ->values()
            ->toArray();
        
        // Calcular totais gerais
        $totaisGerais = [
            'qtd_total_produtos' => collect($resultado)->sum('totais.qtd_produtos'),
            'valor_total_produtos' => collect($resultado)->sum('totais.total'),
        ];
        
        return [
            'pacientes' => $resultado,
            'totais_gerais' => $totaisGerais,
        ];
    }
}

