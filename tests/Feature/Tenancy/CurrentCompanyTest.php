<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenantContextMissingException;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CurrentCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function currentCompany(): CurrentCompany
    {
        return app(CurrentCompany::class);
    }

    public function test_esta_registrado_como_scoped_no_container(): void
    {
        $this->assertSame(
            app(CurrentCompany::class),
            app(CurrentCompany::class),
            'CurrentCompany deve ser a mesma instância dentro da requisição.'
        );
    }

    public function test_sem_contexto_nao_ha_empresa(): void
    {
        $this->assertFalse($this->currentCompany()->has());
    }

    public function test_id_falha_fechado_quando_nao_ha_contexto(): void
    {
        $this->expectException(TenantContextMissingException::class);

        $this->currentCompany()->id();
    }

    public function test_company_falha_fechado_quando_nao_ha_contexto(): void
    {
        $this->expectException(TenantContextMissingException::class);

        $this->currentCompany()->company();
    }

    public function test_set_define_a_empresa_do_contexto(): void
    {
        $company = Company::factory()->create();

        $this->currentCompany()->set($company);

        $this->assertTrue($this->currentCompany()->has());
        $this->assertSame($company->id, $this->currentCompany()->id());
        $this->assertTrue($company->is($this->currentCompany()->company()));
    }

    public function test_set_aceita_apenas_o_id_e_carrega_a_empresa_sob_demanda(): void
    {
        $company = Company::factory()->create();

        $this->currentCompany()->set($company->id);

        $this->assertSame($company->id, $this->currentCompany()->id());
        $this->assertTrue($company->is($this->currentCompany()->company()));
    }

    public function test_resolve_a_empresa_do_usuario_autenticado(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $this->assertTrue($this->currentCompany()->has());
        $this->assertSame($company->id, $this->currentCompany()->id());
    }

    public function test_usuario_sem_empresa_nao_estabelece_contexto(): void
    {
        $this->actingAs(User::factory()->semEmpresa()->create());

        $this->assertFalse($this->currentCompany()->has());
        $this->expectException(TenantContextMissingException::class);
        $this->currentCompany()->id();
    }

    public function test_super_admin_nao_e_tratado_como_tenant_valido(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertFalse(
            $this->currentCompany()->has(),
            'Super Admin tem company_id nulo e não pode valer como empresa.'
        );

        $this->expectException(TenantContextMissingException::class);
        $this->currentCompany()->id();
    }

    public function test_run_as_troca_e_restaura_o_contexto(): void
    {
        $empresaA = Company::factory()->create();
        $empresaB = Company::factory()->create();

        $current = $this->currentCompany();
        $current->set($empresaA);

        $this->assertSame($empresaA->id, $current->id());

        $retorno = $current->runAs($empresaB, function (CurrentCompany $dentro) use ($empresaB) {
            $this->assertSame($empresaB->id, $dentro->id());

            return 'ok';
        });

        $this->assertSame('ok', $retorno);
        $this->assertSame($empresaA->id, $current->id(), 'O contexto anterior deve ser restaurado.');
    }

    public function test_run_as_restaura_o_contexto_mesmo_com_excecao(): void
    {
        $empresaA = Company::factory()->create();
        $empresaB = Company::factory()->create();

        $current = $this->currentCompany();
        $current->set($empresaA);

        try {
            $current->runAs($empresaB, fn () => throw new RuntimeException('falhou'));
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame($empresaA->id, $current->id());
    }

    public function test_run_as_aninhado_restaura_cada_nivel(): void
    {
        $empresaA = Company::factory()->create();
        $empresaB = Company::factory()->create();
        $empresaC = Company::factory()->create();

        $current = $this->currentCompany();
        $current->set($empresaA);

        $current->runAs($empresaB, function (CurrentCompany $nivelB) use ($empresaB, $empresaC) {
            $nivelB->runAs($empresaC, function (CurrentCompany $nivelC) use ($empresaC) {
                $this->assertSame($empresaC->id, $nivelC->id());
            });

            $this->assertSame($empresaB->id, $nivelB->id());
        });

        $this->assertSame($empresaA->id, $current->id());
    }

    public function test_run_as_fora_de_requisicao_e_a_unica_forma_de_obter_contexto(): void
    {
        $company = Company::factory()->create();
        $current = $this->currentCompany();

        // Contexto de console/job: ninguém autenticado, nenhum contexto explícito.
        $this->assertFalse($current->has());

        $idDentro = $current->runAs($company, fn (CurrentCompany $c) => $c->id());

        $this->assertSame($company->id, $idDentro);

        // Encerrado o runAs, volta a falhar fechado — nunca "todas as empresas".
        $this->assertFalse($current->has());
        $this->expectException(TenantContextMissingException::class);
        $current->id();
    }

    public function test_contexto_explicito_tem_precedencia_sobre_o_usuario_autenticado(): void
    {
        $empresaDoUsuario = Company::factory()->create();
        $outraEmpresa = Company::factory()->create();

        $this->actingAs(User::factory()->create(['company_id' => $empresaDoUsuario->id]));

        $current = $this->currentCompany();
        $this->assertSame($empresaDoUsuario->id, $current->id());

        $current->runAs($outraEmpresa, function (CurrentCompany $dentro) use ($outraEmpresa) {
            $this->assertSame($outraEmpresa->id, $dentro->id());
        });

        $this->assertSame($empresaDoUsuario->id, $current->id());
    }
}
