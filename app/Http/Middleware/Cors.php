<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next) 
    {
        // Define los orígenes permitidos
        $allowedOrigins = [
            'http://localhost:5173',
            'http://18.223.112.191',
        ];

        // Obtén el origen de la solicitud
        $origin = $request->header('Origin');

        // Verifica si el origen es permitido
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[1]; // por ejemplo, usa el de la IP

        // Manejo de preflight request (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin'      => $allowOrigin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        // Procesa la solicitud normal
        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
