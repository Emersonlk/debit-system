<?php

namespace Tests\Feature\Tenancy;

use App\Models\Cliente;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Segunda camada de proteção: a Policy nega acesso a cliente de outra empresa mesmo
 * quando o registro é obtido fora do global scope (ex.: withoutGlobalScope()).
 */
class ClientePolicyIsolationTest extends TestCase
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

    public function test_autoriza_atualizacao_de_cliente_da_propria_empresa(): void
    {
        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->assertTrue($this->adminA->can('update', $doA));
        $this->assertTrue($this->adminA->can('view', $doA));
        $this->assertTrue($this->adminA->can('delete', $doA));
    }

    public function test_nega_visualizacao_de_cliente_de_outra_empresa(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->assertFalse($this->adminA->can('view', $doB));
    }

    public function test_nega_atualizacao_de_cliente_de_outra_empresa(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->assertFalse($this->adminA->can('update', $doB));
    }

    public function test_nega_exclusao_de_cliente_de_outra_empresa(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->assertFalse($this->adminA->can('delete', $doB));
        $this->assertFalse($this->adminA->can('restore', $doB));
        $this->assertFalse($this->adminA->can('forceDelete', $doB));
    }

    public function test_nega_mesmo_com_o_registro_carregado_sem_o_global_scope(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        // Simula o cenário que a Policy existe para cobrir: alguém contorna o scope.
        $semScope = Cliente::withoutGlobalScope(Cliente::$companyScope)->find($doB->id);

        $this->assertNotNull($semScope, 'O bypass do scope deve mesmo encontrar o registro.');
        $this->assertFalse($this->adminA->can('view', $semScope));
        $this->assertFalse($this->adminA->can('update', $semScope));
        $this->assertFalse($this->adminA->can('delete', $semScope));
    }

    public function test_cliente_sem_empresa_nao_autoriza_usuario_com_empresa(): void
    {
        $semEmpresa = new Cliente(['nome' => 'Órfão']);
        $semEmpresa->company_id = null;

        $this->assertFalse($this->adminA->can('view', $semEmpresa));
        $this->assertFalse($this->adminA->can('update', $semEmpresa));
        $this->assertFalse($this->adminA->can('delete', $semEmpresa));
    }

    public function test_dois_company_id_nulos_nao_se_autorizam(): void
    {
        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();
        $usuarioSemEmpresa->assignRole('admin');

        $clienteSemEmpresa = new Cliente(['nome' => 'Órfão']);
        $clienteSemEmpresa->company_id = null;

        $this->assertNull($usuarioSemEmpresa->company_id);
        $this->assertNull($clienteSemEmpresa->company_id);

        $this->assertFalse(
            $usuarioSemEmpresa->can('view', $clienteSemEmpresa),
            'null === null não pode autorizar acesso.'
        );
        $this->assertFalse($usuarioSemEmpresa->can('update', $clienteSemEmpresa));
        $this->assertFalse($usuarioSemEmpresa->can('delete', $clienteSemEmpresa));
    }

    public function test_usuario_sem_empresa_nao_acessa_cliente_de_empresa_existente(): void
    {
        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();
        $usuarioSemEmpresa->assignRole('admin');

        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->assertFalse($usuarioSemEmpresa->can('view', $doA));
        $this->assertFalse($usuarioSemEmpresa->can('update', $doA));
    }

    public function test_viewany_e_create_continuam_por_role(): void
    {
        $operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $operadorA->assignRole('operador');

        $this->assertTrue($this->adminA->can('viewAny', Cliente::class));
        $this->assertTrue($this->adminA->can('create', Cliente::class));
        $this->assertTrue($operadorA->can('viewAny', Cliente::class));
        $this->assertTrue($operadorA->can('create', Cliente::class));
    }

    public function test_operador_nao_deleta_mesmo_na_propria_empresa(): void
    {
        $operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $operadorA->assignRole('operador');

        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->assertTrue($operadorA->can('update', $doA), 'A regra de role não pode ter sido perdida.');
        $this->assertFalse($operadorA->can('delete', $doA));
    }
}
