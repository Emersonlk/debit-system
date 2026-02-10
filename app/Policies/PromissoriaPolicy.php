<?php

namespace App\Policies;

use App\Models\Promissoria;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PromissoriaPolicy
{
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
    public function view(User $user, Promissoria $promissoria): bool
    {
        return $user->hasRole(['admin', 'operador']);
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
    public function update(User $user, Promissoria $promissoria): bool
    {
        return $user->hasRole(['admin', 'operador']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Promissoria $promissoria): bool|Response
    {
        // Apenas admin pode deletar
        if ($user->hasRole('admin')) {
            return true;
        }
        return Response::deny('Você não tem permissão para excluir esta promissória.');
    }

    /**
     * Determine whether the user can mark as paid.
     */
    public function markAsPaid(User $user, Promissoria $promissoria): bool
    {
        return $user->hasRole(['admin', 'operador']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Promissoria $promissoria): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Promissoria $promissoria): bool
    {
        return $user->hasRole('admin');
    }
}
