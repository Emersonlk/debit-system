<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Garante a empresa padrão (negócio atual) e associa a ela todo
     * registro existente que ainda não tenha company_id definido.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Adilson Vendas'],
            ['status' => 'active']
        );

        User::whereNull('company_id')->update(['company_id' => $company->id]);
        Cliente::whereNull('company_id')->update(['company_id' => $company->id]);
        Promissoria::whereNull('company_id')->update(['company_id' => $company->id]);
        HistoricoPagamento::whereNull('company_id')->update(['company_id' => $company->id]);
        AuditLog::whereNull('company_id')->update(['company_id' => $company->id]);

        $this->command->info("Empresa padrão '{$company->name}' (id {$company->id}) associada a todos os registros sem company_id.");
    }
}
