<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Sem WithoutModelEvents de propósito: o hook `creating` do BelongsToCompany é
     * quem preenche company_id, e a coluna é NOT NULL. Com os eventos desligados o
     * INSERT sai sem company_id e o banco rejeita. Antes isso passava despercebido
     * porque a coluna aceitava NULL e o CompanySeeder fazia o backfill depois.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            CompanySeeder::class,
        ]);
    }
}
