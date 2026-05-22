<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $usuario = Auth::user();

        if (!$usuario || !in_array($usuario->rol->puesto, $roles)) {
            return response()->json([
                'mensaje' => 'No autorizado',
                'tu_rol' => $usuario->rol->puesto ?? 'Ninguno',
                'roles_permitidos' => $roles
            ], 403);
        }

        return $next($request);
    }
}
