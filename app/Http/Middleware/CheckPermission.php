<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'status_code' => 401,
                'message' => 'Não autenticado'
            ], 401);
        }

        // Verifica se o usuário tem a permissão específica
        if (!$user->can($permission)) {
            return response()->json([
                'success' => false,
                'status_code' => 403,
                'message' => 'Você não tem permissão para realizar esta ação'
            ], 403);
        }

        return $next($request);
    }
}
