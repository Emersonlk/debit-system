<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verifica se o usuário já existe
        $adminUser = User::where('email', 'test@example.com')->first();

        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Usuário Admin',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
            ]);

            $this->command->info('Usuário admin criado com sucesso!');
        } else {
            $this->command->warn('Usuário admin já existe!');
        }

        // Cria usuário operador de teste
        $operadorUser = User::where('email', 'operador@example.com')->first();

        if (!$operadorUser) {
            $operadorUser = User::create([
                'name' => 'Usuário Operador',
                'email' => 'operador@example.com',
                'password' => Hash::make('password123'),
            ]);

            // Atribui role operador (será feito pelo RolePermissionSeeder)
            $this->command->info('Usuário operador criado com sucesso!');
        } else {
            $this->command->warn('Usuário operador já existe!');
        }

        $this->command->info('Email Admin: test@example.com | Senha: password123');
        $this->command->info('Email Operador: operador@example.com | Senha: password123');
    }
}
