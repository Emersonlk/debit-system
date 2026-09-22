<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o parâmetro de rota {usuario} restrito à empresa do contexto atual.
 *
 * Por que não um Route::bind global nem um global scope no model: ambos seriam
 * globais e passariam a valer para as futuras rotas de Super Admin (/api/admin/*),
 * que precisam justamente enxergar usuários de qualquer empresa — e que rodam sem
 * contexto de tenant. Este middleware é declarado apenas no grupo de rotas de
 * usuários do tenant; rotas administrativas simplesmente não o incluem.
 *
 * Usuário de outra empresa — ou sem empresa — passa a ser inexistente aqui: a
 * ModelNotFoundException vira 404 no handler da aplicação, igual ao que já acontece
 * com Cliente e Promissória. A UserPolicy segue como segunda barreira.
 *
 * Substituir o parâmetro já resolvido é seguro em qualquer posição da pilha:
 * SubstituteBindings ignora parâmetros que já são UrlRoutable.
 */
class ResolveTenantUser
{
    public function __construct(
        private CurrentCompany $currentCompany
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $parametro = $request->route('usuario');

        if ($parametro !== null) {
            $id = $parametro instanceof User ? $parametro->getKey() : $parametro;

            // where('company_id', X) nunca casa com NULL, então usuário sem empresa
            // também não é encontrado — que é o comportamento desejado no tenant.
            $usuario = User::query()
                ->where('company_id', $this->currentCompany->id())
                ->find($id);

            if ($usuario === null) {
                throw (new ModelNotFoundException())->setModel(User::class, [$id]);
            }

            $request->route()->setParameter('usuario', $usuario);
        }

        return $next($request);
    }
}
