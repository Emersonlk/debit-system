<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ApenasEmAmbienteLocal;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use ApenasEmAmbienteLocal;

    /**
     * Seed the application's database.
     *
     * Este é o seeder padrão de `db:seed` e monta um ambiente de demonstração
     * completo, então está restrito a APP_ENV=local. Em produção os seeders que
     * fazem sentido continuam disponíveis individualmente:
     * `db:seed --class=RolePermissionSeeder` e `--class=CompanySeeder`.
     *
     * Sem WithoutModelEvents de propósito: o hook `creating` do BelongsToCompany é
     * quem preenche company_id, e a coluna é NOT NULL. Com os eventos desligados o
     * INSERT sai sem company_id e o banco rejeita. Antes isso passava despercebido
     * porque a coluna aceitava NULL e o CompanySeeder fazia o backfill depois.
     */
    public function run(): void
    {
        $this->exigirAmbienteLocal();

        $this->call([
            UserSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            CompanySeeder::class,
        ]);
    }
}
