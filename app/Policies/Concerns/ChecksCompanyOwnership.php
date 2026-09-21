<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra de pertencimento à empresa, usada pelas Policies dos models isolados por tenant.
 *
 * Segunda camada de proteção, independente do global scope: se algum código carregar
 * o registro com withoutGlobalScope(), a autorização ainda nega.
 */
trait ChecksCompanyOwnership
{
    /**
     * O registro pertence à mesma empresa do usuário?
     *
     * Dois valores nulos NÃO se equivalem aqui — um usuário sem empresa (ou um
     * registro sem empresa) nunca deve ser autorizado por coincidência.
     */
    private function mesmaEmpresa(User $user, Model $model): bool
    {
        if ($user->company_id === null || $model->company_id === null) {
            return false;
        }

        return (int) $user->company_id === (int) $model->company_id;
    }
}
