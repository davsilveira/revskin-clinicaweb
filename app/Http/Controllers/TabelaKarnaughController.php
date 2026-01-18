<?php

namespace App\Http\Controllers;

use App\Models\RegraCondicional;
use App\Models\TabelaKarnaugh;
use App\Services\KarnaughCsvParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TabelaKarnaughController extends Controller
{
    /**
     * Listar todas as tabelas Karnaugh.
     */
    public function index()
    {
        $tabelas = TabelaKarnaugh::orderByDesc('padrao')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($tabela) {
                $tabela->casos_count = $tabela->produtos()
                    ->distinct('caso_clinico')
                    ->count('caso_clinico');
                return $tabela;
            });

        return Inertia::render('AssistenteReceita/TabelasKarnaugh', [
            'tabelas' => $tabelas,
        ]);
    }

    /**
     * Visualizar uma tabela Karnaugh específica.
     */
    public function show(TabelaKarnaugh $tabelaKarnaugh)
    {
        // Agrupar produtos por caso clínico, ordenados pela ordem da linha original
        $produtosPorCaso = $tabelaKarnaugh->produtos()
            ->orderBy('ordem') // ordem da linha original do CSV
            ->orderBy('sequencia_coluna') // ordem da coluna original do CSV
            ->get()
            ->groupBy('caso_clinico');

        // Manter a ordem dos casos clínicos pela ordem de importação
        $casosOrdenados = $tabelaKarnaugh->produtos()
            ->select('caso_clinico', 'ordem')
            ->distinct()
            ->orderBy('ordem')
            ->pluck('caso_clinico')
            ->unique()
            ->values();

        // Reordenar produtosPorCaso na ordem dos casos
        $produtosPorCasoOrdenado = collect();
        foreach ($casosOrdenados as $caso) {
            if (isset($produtosPorCaso[$caso])) {
                $produtosPorCasoOrdenado[$caso] = $produtosPorCaso[$caso];
            }
        }

        // Obter lista única de categorias ordenadas pela sequência da coluna original do CSV
        $categorias = $tabelaKarnaugh->produtos()
            ->select('categoria', 'grupo', 'sequencia_coluna')
            ->distinct()
            ->get()
            ->sortBy('sequencia_coluna')
            ->pluck('categoria')
            ->unique()
            ->values();

        return Inertia::render('AssistenteReceita/TabelaKarnaughView', [
            'tabela' => $tabelaKarnaugh,
            'produtosPorCaso' => $produtosPorCasoOrdenado,
            'categorias' => $categorias,
        ]);
    }

    /**
     * Validar CSV antes de importar.
     */
    public function validar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $content = file_get_contents($request->file('arquivo')->getRealPath());
        $parser = new KarnaughCsvParser();
        $errors = $parser->validate($content);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Importar tabela Karnaugh a partir de CSV.
     */
    public function importar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:csv,txt|max:5120',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'padrao' => 'boolean',
        ]);

        $content = file_get_contents($request->file('arquivo')->getRealPath());
        $arquivoOriginal = $request->file('arquivo')->getClientOriginalName();

        $parser = new KarnaughCsvParser();
        
        // Validar primeiro
        $errors = $parser->validate($content);
        if (!empty($errors)) {
            return back()->withErrors(['arquivo' => implode('. ', $errors)]);
        }

        try {
            $tabela = $parser->parse(
                $content,
                $request->nome,
                $request->descricao,
                $arquivoOriginal,
                $request->boolean('padrao')
            );

            // Salvar o arquivo original no storage para download
            Storage::disk('local')->put('karnaugh/' . $tabela->id . '.csv', $content);

            return redirect()
                ->route('assistente.tabelas-karnaugh.index')
                ->with('success', "Tabela '{$tabela->nome}' importada com sucesso! {$tabela->produtos()->count()} produtos cadastrados.");
        } catch (\Exception $e) {
            return back()->withErrors(['arquivo' => 'Erro ao importar: ' . $e->getMessage()]);
        }
    }

    /**
     * Definir uma tabela como padrão.
     */
    public function definirPadrao(TabelaKarnaugh $tabelaKarnaugh)
    {
        $tabelaKarnaugh->definirComoPadrao();

        return back()->with('success', "Tabela '{$tabelaKarnaugh->nome}' definida como padrão.");
    }

    /**
     * Ativar/desativar uma tabela.
     */
    public function toggleAtivo(TabelaKarnaugh $tabelaKarnaugh)
    {
        $tabelaKarnaugh->update(['ativo' => !$tabelaKarnaugh->ativo]);
        $status = $tabelaKarnaugh->ativo ? 'ativada' : 'desativada';

        return back()->with('success', "Tabela '{$tabelaKarnaugh->nome}' {$status}.");
    }

    /**
     * Excluir uma tabela Karnaugh.
     */
    public function destroy(TabelaKarnaugh $tabelaKarnaugh)
    {
        $nome = $tabelaKarnaugh->nome;
        
        // Remover regras de seleção que têm ações apontando para esta tabela
        $regrasParaRemover = RegraCondicional::selecaoTabela()
            ->whereHas('acoes', function ($query) use ($tabelaKarnaugh) {
                $query->where('tipo_acao', 'usar_tabela')
                    ->where('tabela_karnaugh_id', $tabelaKarnaugh->id);
            })
            ->get();
        
        $regrasRemovidas = $regrasParaRemover->count();
        foreach ($regrasParaRemover as $regra) {
            $regra->delete();
        }
        
        // Regras de modificação serão removidas automaticamente pelo cascade
        
        $tabelaKarnaugh->delete();

        $mensagem = "Tabela '{$nome}' excluída com sucesso.";
        if ($regrasRemovidas > 0) {
            $mensagem .= " {$regrasRemovidas} regra(s) de seleção também foram removidas.";
        }

        return redirect()
            ->route('assistente.tabelas-karnaugh.index')
            ->with('success', $mensagem);
    }

    /**
     * Download do arquivo CSV original.
     */
    public function download(TabelaKarnaugh $tabelaKarnaugh)
    {
        if (!$tabelaKarnaugh->arquivo_original) {
            abort(404, 'Arquivo original não encontrado.');
        }

        // Verificar se o arquivo existe no storage
        $filePath = 'karnaugh/' . $tabelaKarnaugh->id . '.csv';
        
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath, $tabelaKarnaugh->arquivo_original);
        }

        // Se o arquivo não foi salvo, retornar erro
        abort(404, 'Arquivo original não disponível para download.');
    }
}
