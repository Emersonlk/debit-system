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
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            User::create([
                'name' => 'Usuário Teste',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
            ]);

            $this->command->info('Usuário de teste criado com sucesso!');
            $this->command->info('Email: test@example.com');
            $this->command->info('Senha: password123');
        } else {
            $this->command->warn('Usuário de teste já existe!');
            $this->command->info('Email: test@example.com');
            $this->command->info('Senha: password123');
        }
    }
}
