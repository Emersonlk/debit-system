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
     * Nome da empresa padrão (o negócio já existente antes do multi-tenancy).
     */
    public const EMPRESA_PADRAO = 'Adilson Vendas';

    /**
     * Garante a empresa padrão (negócio atual) e associa a ela todo
     * registro existente que ainda não tenha company_id definido.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => self::EMPRESA_PADRAO],
            ['status' => 'active']
        );

        User::whereNull('company_id')->update(['company_id' => $company->id]);
        // Sem o global scope: este backfill age justamente sobre registros que ainda
        // não pertencem a nenhuma empresa, que por definição estão fora do contexto.
        Cliente::withoutGlobalScope(Cliente::$companyScope)
            ->whereNull('company_id')
            ->update(['company_id' => $company->id]);
        Promissoria::withoutGlobalScope(Promissoria::$companyScope)
            ->whereNull('company_id')
            ->update(['company_id' => $company->id]);
        HistoricoPagamento::withoutGlobalScope(HistoricoPagamento::$companyScope)
            ->whereNull('company_id')
            ->update(['company_id' => $company->id]);
        AuditLog::whereNull('company_id')->update(['company_id' => $company->id]);

        $this->command->info("Empresa padrão '{$company->name}' (id {$company->id}) associada a todos os registros sem company_id.");
    }
}
