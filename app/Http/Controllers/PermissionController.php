<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function __construct(
        private CurrentCompany $currentCompany
    ) {
    }

    /**
     * Lista os usuários da empresa atual com suas roles e permissões
     */
    public function usuarios(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        // Filtro explícito: `viewAny` não recebe model, então a Policy não tem como
        // isolar a listagem — e User não tem global scope. Usuários sem empresa
        // (Super Admin, por exemplo) não casam com a comparação e ficam de fora.
        $usuarios = User::where('company_id', $this->currentCompany->id())
            ->with('roles', 'permissions')
            ->get();

        $data = $usuarios->map(function ($usuario) {
            return [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => $usuario->roles->pluck('name'),
                'permissions' => $usuario->getAllPermissions()->pluck('name'),
            ];
        });

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $data
        ], 200);
    }

    /**
     * Mostra as permissões de um usuário específico
     */
    public function usuario(User $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        $roles = $usuario->roles->pluck('name');
        $permissions = $usuario->getAllPermissions()->pluck('name');
        $permissionsDirect = $usuario->permissions->pluck('name');
        $permissionsViaRoles = $permissions->diff($permissionsDirect);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => $roles,
                'permissions' => [
                    'all' => $permissions,
                    'direct' => $permissionsDirect,
                    'via_roles' => $permissionsViaRoles,
                ],
            ]
        ], 200);
    }

    /**
     * Atribui uma role a um usuário
     */
    public function atribuirRole(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);

        $request->validate([
            'role' => 'required|string|exists:roles,name'
        ]);

        $role = Role::findByName($request->role);
        $usuario->assignRole($role);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => "Role '{$role->name}' atribuída com sucesso ao usuário",
            'data' => [
                'user' => $usuario->name,
                'roles' => $usuario->roles->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Remove uma role de um usuário
     */
    public function removerRole(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);

        $request->validate([
            'role' => 'required|string|exists:roles,name'
        ]);

        $role = Role::findByName($request->role);
        $usuario->removeRole($role);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => "Role '{$role->name}' removida com sucesso do usuário",
            'data' => [
                'user' => $usuario->name,
                'roles' => $usuario->roles->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Atribui uma permissão direta a um usuário
     */
    public function atribuirPermissao(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);

        $request->validate([
            'permission' => 'required|string|exists:permissions,name'
        ]);

        $permission = Permission::findByName($request->permission);
        $usuario->givePermissionTo($permission);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => "Permissão '{$permission->name}' atribuída com sucesso ao usuário",
            'data' => [
                'user' => $usuario->name,
                'permissions' => $usuario->getAllPermissions()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Remove uma permissão direta de um usuário
     */
    public function removerPermissao(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);

        $request->validate([
            'permission' => 'required|string|exists:permissions,name'
        ]);

        $permission = Permission::findByName($request->permission);
        $usuario->revokePermissionTo($permission);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => "Permissão '{$permission->name}' removida com sucesso do usuário",
            'data' => [
                'user' => $usuario->name,
                'permissions' => $usuario->getAllPermissions()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Lista todas as roles disponíveis
     */
    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::with('permissions')->get();

        $data = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $data
        ], 200);
    }

    /**
     * Lista todas as permissões disponíveis
     */
    public function permissoes(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $permissoes = Permission::all()->pluck('name');

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $permissoes
        ], 200);
    }

    /**
     * Retorna as permissões do usuário autenticado
     */
    public function minhasPermissoes(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $roles = $usuario->roles->pluck('name');
        $permissions = $usuario->getAllPermissions()->pluck('name');

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => [
                'user' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                ],
                'roles' => $roles,
                'permissions' => $permissions,
            ]
        ], 200);
    }
}
