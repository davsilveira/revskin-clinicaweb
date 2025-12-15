<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMedico
{
    /**
     * Handle an incoming request.
     * Allows access to admin and medico roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'Acesso não autorizado.');
        }

        if (!$user->isAdmin() && !$user->isMedico()) {
            abort(403, 'Acesso restrito a médicos.');
        }

        return $next($request);
    }
}




