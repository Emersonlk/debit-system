<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Segunda camada de proteção: a Policy nega acesso a promissória de outra empresa mesmo
 * quando o registro é obtido fora do global scope (ex.: withoutGlobalScope()).
 */
class PromissoriaPolicyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'operador']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->adminA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->adminA->assignRole('admin');
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    public function test_autoriza_promissoria_da_propria_empresa(): void
    {
        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        $this->assertTrue($this->adminA->can('view', $daA));
        $this->assertTrue($this->adminA->can('update', $daA));
        $this->assertTrue($this->adminA->can('delete', $daA));
        $this->assertTrue($this->adminA->can('markAsPaid', $daA));
    }

    public function test_nega_promissoria_de_outra_empresa_carregada_sem_o_global_scope(): void
    {
        $daB = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create());

        $semScope = Promissoria::withoutGlobalScope(Promissoria::$companyScope)->find($daB->id);

        $this->assertNotNull($semScope, 'O bypass do scope deve mesmo encontrar o registro.');
        $this->assertFalse($this->adminA->can('view', $semScope));
        $this->assertFalse($this->adminA->can('update', $semScope));
        $this->assertFalse($this->adminA->can('delete', $semScope));
        $this->assertFalse($this->adminA->can('markAsPaid', $semScope));
        $this->assertFalse($this->adminA->can('restore', $semScope));
        $this->assertFalse($this->adminA->can('forceDelete', $semScope));
    }

    public function test_usuario_sem_empresa_nao_recebe_autorizacao(): void
    {
        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();
        $usuarioSemEmpresa->assignRole('admin');

        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        $this->assertFalse($usuarioSemEmpresa->can('view', $daA));
        $this->assertFalse($usuarioSemEmpresa->can('update', $daA));
        $this->assertFalse($usuarioSemEmpresa->can('markAsPaid', $daA));
    }

    public function test_dois_company_id_nulos_nao_se_autorizam(): void
    {
        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();
        $usuarioSemEmpresa->assignRole('admin');

        $promissoriaSemEmpresa = new Promissoria(['valor' => 100]);
        $promissoriaSemEmpresa->company_id = null;

        $this->assertNull($usuarioSemEmpresa->company_id);
        $this->assertNull($promissoriaSemEmpresa->company_id);

        $this->assertFalse(
            $usuarioSemEmpresa->can('view', $promissoriaSemEmpresa),
            'null === null não pode autorizar acesso.'
        );
        $this->assertFalse($usuarioSemEmpresa->can('update', $promissoriaSemEmpresa));
        $this->assertFalse($usuarioSemEmpresa->can('markAsPaid', $promissoriaSemEmpresa));
    }

    public function test_promissoria_sem_empresa_nao_autoriza_usuario_com_empresa(): void
    {
        $semEmpresa = new Promissoria(['valor' => 100]);
        $semEmpresa->company_id = null;

        $this->assertFalse($this->adminA->can('view', $semEmpresa));
        $this->assertFalse($this->adminA->can('update', $semEmpresa));
    }

    public function test_regras_de_role_continuam_valendo(): void
    {
        $operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $operadorA->assignRole('operador');

        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        $this->assertTrue($operadorA->can('viewAny', Promissoria::class));
        $this->assertTrue($operadorA->can('create', Promissoria::class));
        $this->assertTrue($operadorA->can('update', $daA));
        $this->assertTrue($operadorA->can('markAsPaid', $daA));
        $this->assertFalse($operadorA->can('delete', $daA), 'Operador continua sem poder excluir.');
    }
}
