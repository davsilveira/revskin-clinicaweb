<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles (comma-separated in route)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Usuário não autenticado.');
        }

        if (!$user->is_active) {
            abort(403, 'Usuário desativado.');
        }

        // Admin has access to everything
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check if user has one of the allowed roles
        if (!$user->hasAnyRole($roles)) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}










