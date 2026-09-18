<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar permissões
        $permissions = [
            // Cliente
            'clientes.listar',
            'clientes.visualizar',
            'clientes.criar',
            'clientes.editar',
            'clientes.deletar',
            
            // Promissória
            'promissorias.listar',
            'promissorias.visualizar',
            'promissorias.criar',
            'promissorias.editar',
            'promissorias.deletar',
            'promissorias.marcar-paga',
        ];

        // guard_name explícito: a API autentica exclusivamente via Sanctum, então roles e
        // permissions precisam existir sob o guard 'sanctum' para que Role::findByName()/
        // Permission::findByName() (usados em PermissionController) as encontrem em runtime.
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Criar roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $operadorRole = Role::firstOrCreate(['name' => 'operador', 'guard_name' => 'sanctum']);

        // Atribuir todas as permissões ao admin
        $adminRole->givePermissionTo(Permission::all());

        // Atribuir permissões ao operador (sem deletar)
        $operadorRole->givePermissionTo([
            'clientes.listar',
            'clientes.visualizar',
            'clientes.criar',
            'clientes.editar',
            'promissorias.listar',
            'promissorias.visualizar',
            'promissorias.criar',
            'promissorias.editar',
            'promissorias.marcar-paga',
        ]);

        // Atribuir role admin ao usuário de teste (se existir)
        $adminUser = User::where('email', 'test@example.com')->first();
        if ($adminUser && !$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        // Atribuir role operador ao usuário operador (se existir)
        $operadorUser = User::where('email', 'operador@example.com')->first();
        if ($operadorUser && !$operadorUser->hasRole('operador')) {
            $operadorUser->assignRole('operador');
        }

        $this->command->info('Roles e Permissions criados com sucesso!');
        $this->command->info('Roles: admin, operador');
        $this->command->info('Usuário test@example.com recebeu role: admin');
        $this->command->info('Usuário operador@example.com recebeu role: operador');
    }
}
