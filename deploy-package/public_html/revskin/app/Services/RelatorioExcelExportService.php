<?php

namespace App\Services;

use App\Exports\AquisicaoProdutosExport;
use App\Exports\ReceitasMedicoExport;
use App\Http\Controllers\RelatorioController;
use App\Models\Medico;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelatorioExcelExportService
{
    /**
     * @return array{export: AquisicaoProdutosExport, total_records: int}
     */
    public function buildAquisicaoProdutosExport(User $user, array $filters): array
    {
        $isAdmin = $user->isAdmin();

        $medicoIdsFiltro = null;
        if ($user->isMedico()) {
            $medicoIdsFiltro = [$user->medico_id];
        } elseif (! empty($filters['medico_ids'])) {
            $medicoIdsFiltro = $filters['medico_ids'];
        }

        $laravelRequest = Request::create('/internal', 'GET', $filters);
        $relatorioController = app(RelatorioController::class);
        $dados = $relatorioController->buscarAquisicoes($laravelRequest, $medicoIdsFiltro, false);
        if (! $isAdmin) {
            $dados = $relatorioController->sanitizarAquisicaoSemValoresMonetarios($dados);
        }

        $dataInicio = $filters['data_inicio'] ?? now()->format('Y-m-d');
        $dataFim = $filters['data_fim'] ?? now()->format('Y-m-d');

        $totalRecords = (int) collect($dados['pacientes'] ?? [])->sum(fn ($p) => count($p['produtos'] ?? []));
        if ($totalRecords === 0 && ! empty($dados['pacientes'])) {
            $totalRecords = count($dados['pacientes']);
        }

        return [
            'export' => new AquisicaoProdutosExport($dados, $dataInicio, $dataFim, $isAdmin),
            'total_records' => $totalRecords,
        ];
    }

    public function downloadAquisicaoProdutosExcel(User $user, array $filters): BinaryFileResponse
    {
        $bundle = $this->buildAquisicaoProdutosExport($user, $filters);
        $fileName = sprintf('relatorio-aquisicao-produtos_%s.xlsx', now()->format('Ymd_His'));

        return Excel::download($bundle['export'], $fileName);
    }

    /**
     * @return array{export: ReceitasMedicoExport, receitas: Collection, medico: ?Medico, total_records: int}
     */
    public function buildReceitasMedicoExport(array $filters): array
    {
        $query = Receita::with(['paciente:id,nome', 'medico:id', 'medico.linkedUser:id,name,medico_id'])
            ->whereIn('status', ['finalizada', 'aberta'])
            ->when($filters['medico_id'] ?? null, fn ($q, $id) => $q->where('medico_id', $id))
            ->when($filters['data_inicio'] ?? null, fn ($q, $data) => $q->whereDate('data_receita', '>=', $data))
            ->when($filters['data_fim'] ?? null, fn ($q, $data) => $q->whereDate('data_receita', '<=', $data))
            ->orderBy('data_receita', 'desc');

        $receitas = $query->get();
        $medico = ($filters['medico_id'] ?? null) ? Medico::with('linkedUser:id,name,medico_id')->find($filters['medico_id']) : null;

        return [
            'export' => new ReceitasMedicoExport($receitas, $medico),
            'receitas' => $receitas,
            'medico' => $medico,
            'total_records' => $receitas->count(),
        ];
    }

    public function downloadReceitasMedicoExcel(array $filters): BinaryFileResponse
    {
        $bundle = $this->buildReceitasMedicoExport($filters);
        $fileName = sprintf('relatorio-receitas-medico_%s.xlsx', now()->format('Ymd_His'));

        return Excel::download($bundle['export'], $fileName);
    }
}
