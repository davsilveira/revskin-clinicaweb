<?php

namespace App\Http\Controllers;

use App\Manual\ManualContent;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authorizeManual($request);

        return Inertia::render('Manual/Index', [
            'modules' => ManualContent::forUser($user),
        ]);
    }

    /**
     * Versão PDF (dompdf): índice na primeira página com links para as seções e
     * conteúdo em largura total, sem menu lateral.
     */
    public function pdf(Request $request): HttpResponse
    {
        $user = $this->authorizeManual($request);

        $pdf = Pdf::loadView('manual.document', $this->documentData($user, forPdf: true))
            ->setPaper('a4');

        return $pdf->download('manual-de-uso.pdf');
    }

    /**
     * Versão de impressão (HTML limpo que dispara a caixa de impressão do
     * navegador). Mesmo layout do PDF, sem o menu lateral do sistema.
     */
    public function imprimir(Request $request): View
    {
        $user = $this->authorizeManual($request);

        return view('manual.document', $this->documentData($user, forPdf: false, autoprint: true));
    }

    private function authorizeManual(Request $request): User
    {
        $user = $request->user();
        if ($user === null || $user->isCallcenter()) {
            abort(403, 'Manual indisponível para este perfil.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(User $user, bool $forPdf, bool $autoprint = false): array
    {
        return [
            'modules' => ManualContent::forUser($user),
            'forPdf' => $forPdf,
            'autoprint' => $autoprint,
            'appName' => config('app.name'),
            'generatedAt' => now()->format('d/m/Y'),
        ];
    }
}
