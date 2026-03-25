<?php

namespace App\Http\Controllers;

use App\Manual\ManualContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || $user->isCallcenter()) {
            abort(403, 'Manual indisponível para este perfil.');
        }

        return Inertia::render('Manual/Index', [
            'modules' => ManualContent::forUser($user),
        ]);
    }
}
