<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estabelece a empresa (tenant) da requisição a partir do usuário autenticado.
 *
 * Roda depois de auth:sanctum e antes de SubstituteBindings (ver a lista de
 * prioridade em bootstrap/app.php), para que o route model binding já enxergue
 * o contexto quando o isolamento por empresa entrar nas próximas fases.
 *
 * A empresa vem sempre do usuário autenticado — nunca de parâmetros da requisição.
 */
class EnsureTenantContext
{
    public function __construct(
        private CurrentCompany $currentCompany
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Defensivo: com auth:sanctum antes desta middleware isto não deve ocorrer.
        if (! $user instanceof User) {
            return $this->deny(401, 'Não autenticado.');
        }

        // Super Admin não opera sobre dados de empresa por estas rotas; terá rotas
        // administrativas próprias (/api/admin/*), em fase futura.
        if ($user->is_super_admin) {
            return $this->deny(403);
        }

        if ($user->company_id === null) {
            Log::warning('Usuário sem empresa tentou acessar rota multi-tenant.', [
                'user_id' => $user->getKey(),
                'path' => $request->path(),
            ]);

            return $this->deny(403);
        }

        $this->currentCompany->set((int) $user->company_id);

        return $next($request);
    }

    private function deny(int $status, string $message = 'Acesso negado.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status_code' => $status,
            'message' => $message,
        ], $status);
    }
}
