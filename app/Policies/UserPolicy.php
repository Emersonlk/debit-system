<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksCompanyOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Autorização sobre usuários.
 *
 * Atenção: com Spatie teams desativado, a role `admin` é global — `hasRole('admin')`
 * diz que o usuário administra *alguma* empresa, nunca *qual*. Quem separa um Company
 * Admin de outro é exclusivamente o company_id, checado aqui. O model User não tem
 * global scope (decisão arquitetural), então esta Policy é hoje a única barreira
 * entre empresas nas operações administrativas de usuário.
 */
class UserPolicy
{
    use ChecksCompanyOwnership;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Apenas admin pode listar usuários
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Ver a si mesmo é autorizado por identidade — não por coincidência de empresa.
        if ($user->id === $model->id) {
            return true;
        }

        // Admin só enxerga usuários da própria empresa.
        return $user->hasRole('admin') && $this->mesmaEmpresa($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Apenas admin pode criar usuários
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Atualizar a si mesmo é autorizado por identidade.
        if ($user->id === $model->id) {
            return true;
        }

        // Admin só atualiza usuários da própria empresa.
        return $user->hasRole('admin') && $this->mesmaEmpresa($user, $model);
    }

    /**
     * Determine whether the user can grant/revoke roles or permissions of the model.
     * Somente admin — não pode ser satisfeito por self-update, para impedir que um
     * usuário atribua roles/permissões a si mesmo (auto-escalonamento de privilégio).
     */
    public function managePermissions(User $user, User $model): bool
    {
        return $user->hasRole('admin') && $this->mesmaEmpresa($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Apenas admin pode deletar usuários da própria empresa, e nunca a si mesmo
        return $user->hasRole('admin')
            && $user->id !== $model->id
            && $this->mesmaEmpresa($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('admin')
            && $this->mesmaEmpresa($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('admin')
            && $user->id !== $model->id
            && $this->mesmaEmpresa($user, $model);
    }
}
