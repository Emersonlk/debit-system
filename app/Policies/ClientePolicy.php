<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientePolicy
{
    /**
     * O cliente pertence à mesma empresa do usuário?
     *
     * Segunda camada de proteção, independente do global scope de Cliente: se algum
     * código carregar o registro com withoutGlobalScope(), a autorização ainda nega.
     *
     * Dois valores nulos NÃO se equivalem aqui — um usuário sem empresa (ou um
     * registro sem empresa) nunca deve ser autorizado por coincidência.
     */
    private function mesmaEmpresa(User $user, Cliente $cliente): bool
    {
        if ($user->company_id === null || $cliente->company_id === null) {
            return false;
        }

        return (int) $user->company_id === (int) $cliente->company_id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operador']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cliente $cliente): bool
    {
        return $user->hasRole(['admin', 'operador'])
            && $this->mesmaEmpresa($user, $cliente);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operador']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $user->hasRole(['admin', 'operador'])
            && $this->mesmaEmpresa($user, $cliente);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        // Apenas admin pode deletar
        return $user->hasRole('admin')
            && $this->mesmaEmpresa($user, $cliente);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin')
            && $this->mesmaEmpresa($user, $cliente);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin')
            && $this->mesmaEmpresa($user, $cliente);
    }
}
