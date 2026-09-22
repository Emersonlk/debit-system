<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Garantias estruturais de banco, complementares às de aplicação.
 *
 * Até aqui o isolamento dependia do hook `creating` do BelongsToCompany. Estes
 * testes verificam a camada de baixo: NOT NULL e a FK RESTRICT, que valem mesmo
 * para código que contorne o Eloquent.
 *
 * users e audit_logs continuam aceitando NULL por decisão arquitetural — também
 * verificado aqui, para que a fase não extrapole o escopo sem ninguém notar.
 */
class CompanyIdNotNullTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Company::factory()->create(['name' => 'Empresa A']);
    }

    private function comoEmpresa(callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($this->empresa, $callback);
    }

    // ------------------------------------------------------- NOT NULL

    public function test_clientes_nao_aceita_company_id_nulo(): void
    {
        $this->expectException(QueryException::class);

        DB::table('clientes')->insert([
            'company_id' => null,
            'nome' => 'Sem Empresa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_promissorias_nao_aceita_company_id_nulo(): void
    {
        $cliente = $this->comoEmpresa(fn () => Cliente::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('promissorias')->insert([
            'company_id' => null,
            'cliente_id' => $cliente->id,
            'valor' => 100,
            'data_vencimento' => now()->addDays(5)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_historico_pagamentos_nao_aceita_company_id_nulo(): void
    {
        $promissoria = $this->comoEmpresa(fn () => Promissoria::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('historico_pagamentos')->insert([
            'company_id' => null,
            'promissoria_id' => $promissoria->id,
            'valor_pago' => 50,
            'data_pagamento' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_company_id_inexistente_continua_sendo_rejeitado(): void
    {
        $this->expectException(QueryException::class);

        DB::table('clientes')->insert([
            'company_id' => 999999,
            'nome' => 'Empresa Fantasma',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --------------------------------------------- FK RESTRICT ao excluir

    public function test_nao_exclui_empresa_com_cliente(): void
    {
        $this->comoEmpresa(fn () => Cliente::factory()->create());

        $this->expectException(QueryException::class);

        Company::whereKey($this->empresa->id)->delete();
    }

    public function test_nao_exclui_empresa_com_promissoria(): void
    {
        $this->comoEmpresa(fn () => Promissoria::factory()->create());

        $this->expectException(QueryException::class);

        Company::whereKey($this->empresa->id)->delete();
    }

    public function test_nao_exclui_empresa_com_historico_de_pagamento(): void
    {
        $this->comoEmpresa(function () {
            Promissoria::factory()->create()->historicoPagamentos()->create([
                'valor_pago' => 50,
                'data_pagamento' => now()->format('Y-m-d'),
            ]);
        });

        $this->expectException(QueryException::class);

        Company::whereKey($this->empresa->id)->delete();
    }

    public function test_empresa_sem_dados_continua_podendo_ser_excluida(): void
    {
        $vazia = Company::factory()->create(['name' => 'Empresa Vazia']);

        Company::whereKey($vazia->id)->delete();

        $this->assertNull(Company::find($vazia->id));
    }

    public function test_dados_da_empresa_permanecem_apos_tentativa_de_exclusao(): void
    {
        $cliente = $this->comoEmpresa(fn () => Cliente::factory()->create());

        try {
            Company::whereKey($this->empresa->id)->delete();
        } catch (QueryException) {
            // esperado
        }

        // Com SET NULL o cliente viraria órfão invisível; com RESTRICT nada muda.
        $this->assertNotNull(Company::find($this->empresa->id));
        $this->assertSame(
            $this->empresa->id,
            (int) DB::table('clientes')->where('id', $cliente->id)->value('company_id')
        );
    }

    // ----------------------------- tabelas deliberadamente fora do escopo

    public function test_users_continua_aceitando_company_id_nulo(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertNull($superAdmin->company_id);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'company_id' => null]);
    }

    public function test_audit_logs_continua_aceitando_company_id_nulo(): void
    {
        DB::table('audit_logs')->insert([
            'company_id' => null,
            'user_id' => null,
            'action' => 'create',
            'model_type' => Cliente::class,
            'model_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', ['model_type' => Cliente::class, 'company_id' => null]);
    }
}
