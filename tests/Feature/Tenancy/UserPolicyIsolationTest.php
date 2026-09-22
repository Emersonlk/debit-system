<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O model User não tem global scope (decisão arquitetural), então a UserPolicy é
 * hoje a única barreira entre empresas nas operações administrativas de usuário.
 *
 * Lembrete: com Spatie teams desativado, a role `admin` é global — quem separa um
 * Company Admin de outro é apenas o company_id.
 */
class UserPolicyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private User $adminA;

    private User $operadorA;

    private User $adminB;

    private User $usuarioB;

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

        $this->operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->operadorA->assignRole('operador');

        $this->adminB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->adminB->assignRole('admin');

        $this->usuarioB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->usuarioB->assignRole('operador');
    }

    // ------------------------------------------- dentro da própria empresa

    public function test_admin_visualiza_usuario_da_propria_empresa(): void
    {
        $this->assertTrue($this->adminA->can('view', $this->operadorA));
    }

    public function test_admin_atualiza_usuario_da_propria_empresa(): void
    {
        $this->assertTrue($this->adminA->can('update', $this->operadorA));
    }

    public function test_admin_gerencia_permissoes_de_usuario_da_propria_empresa(): void
    {
        $this->assertTrue($this->adminA->can('managePermissions', $this->operadorA));
    }

    public function test_admin_deleta_usuario_da_propria_empresa(): void
    {
        $this->assertTrue($this->adminA->can('delete', $this->operadorA));
        $this->assertTrue($this->adminA->can('restore', $this->operadorA));
        $this->assertTrue($this->adminA->can('forceDelete', $this->operadorA));
    }

    // ------------------------------------------------ contra a outra empresa

    public function test_admin_nao_visualiza_usuario_de_outra_empresa(): void
    {
        $this->assertFalse($this->adminA->can('view', $this->usuarioB));
        $this->assertFalse($this->adminA->can('view', $this->adminB));
    }

    public function test_admin_nao_atualiza_usuario_de_outra_empresa(): void
    {
        $this->assertFalse($this->adminA->can('update', $this->usuarioB));
        $this->assertFalse($this->adminA->can('update', $this->adminB));
    }

    public function test_admin_nao_gerencia_permissoes_de_usuario_de_outra_empresa(): void
    {
        $this->assertFalse(
            $this->adminA->can('managePermissions', $this->usuarioB),
            'Um Admin da empresa A não pode conceder/revogar roles na empresa B.'
        );
        $this->assertFalse($this->adminA->can('managePermissions', $this->adminB));
    }

    public function test_admin_nao_remove_usuario_de_outra_empresa(): void
    {
        $this->assertFalse($this->adminA->can('delete', $this->usuarioB));
        $this->assertFalse($this->adminA->can('restore', $this->usuarioB));
        $this->assertFalse($this->adminA->can('forceDelete', $this->usuarioB));
    }

    public function test_isolamento_vale_nos_dois_sentidos(): void
    {
        $this->assertFalse($this->adminB->can('view', $this->operadorA));
        $this->assertFalse($this->adminB->can('update', $this->operadorA));
        $this->assertFalse($this->adminB->can('managePermissions', $this->operadorA));
        $this->assertFalse($this->adminB->can('delete', $this->operadorA));
    }

    // ------------------------------------------------------ company_id nulo

    public function test_admin_nao_acessa_usuario_sem_empresa(): void
    {
        $semEmpresa = User::factory()->semEmpresa()->create();

        $this->assertFalse($this->adminA->can('view', $semEmpresa));
        $this->assertFalse($this->adminA->can('update', $semEmpresa));
        $this->assertFalse($this->adminA->can('managePermissions', $semEmpresa));
        $this->assertFalse($this->adminA->can('delete', $semEmpresa));
        $this->assertFalse($this->adminA->can('restore', $semEmpresa));
        $this->assertFalse($this->adminA->can('forceDelete', $semEmpresa));
    }

    public function test_dois_company_id_nulos_nao_se_autorizam(): void
    {
        $adminSemEmpresa = User::factory()->semEmpresa()->create();
        $adminSemEmpresa->assignRole('admin');

        $outroSemEmpresa = User::factory()->semEmpresa()->create();

        $this->assertNull($adminSemEmpresa->company_id);
        $this->assertNull($outroSemEmpresa->company_id);

        $this->assertFalse(
            $adminSemEmpresa->can('view', $outroSemEmpresa),
            'null === null não pode autorizar acesso.'
        );
        $this->assertFalse($adminSemEmpresa->can('update', $outroSemEmpresa));
        $this->assertFalse($adminSemEmpresa->can('managePermissions', $outroSemEmpresa));
        $this->assertFalse($adminSemEmpresa->can('delete', $outroSemEmpresa));
    }

    public function test_admin_sem_empresa_nao_acessa_usuario_de_empresa_existente(): void
    {
        $adminSemEmpresa = User::factory()->semEmpresa()->create();
        $adminSemEmpresa->assignRole('admin');

        $this->assertFalse($adminSemEmpresa->can('view', $this->operadorA));
        $this->assertFalse($adminSemEmpresa->can('managePermissions', $this->operadorA));
    }

    // ------------------------------------------------------- regras de role

    public function test_operador_continua_sem_permissoes_administrativas(): void
    {
        $outroDaEmpresaA = User::factory()->create(['company_id' => $this->empresaA->id]);

        $this->assertFalse($this->operadorA->can('viewAny', User::class));
        $this->assertFalse($this->operadorA->can('create', User::class));
        $this->assertFalse($this->operadorA->can('view', $outroDaEmpresaA));
        $this->assertFalse($this->operadorA->can('update', $outroDaEmpresaA));
        $this->assertFalse($this->operadorA->can('managePermissions', $outroDaEmpresaA));
        $this->assertFalse($this->operadorA->can('delete', $outroDaEmpresaA));
    }

    public function test_operador_nao_escala_privilegio_em_si_mesmo(): void
    {
        $this->assertFalse(
            $this->operadorA->can('managePermissions', $this->operadorA),
            'managePermissions nunca pode ser satisfeito por self-access.'
        );
    }

    public function test_admin_continua_podendo_listar_e_criar(): void
    {
        $this->assertTrue($this->adminA->can('viewAny', User::class));
        $this->assertTrue($this->adminA->can('create', User::class));
    }

    // --------------------------------------------------------- self-access

    public function test_self_access_de_view_e_update_preservado(): void
    {
        $this->assertTrue($this->operadorA->can('view', $this->operadorA));
        $this->assertTrue($this->operadorA->can('update', $this->operadorA));
        $this->assertTrue($this->adminA->can('view', $this->adminA));
        $this->assertTrue($this->adminA->can('update', $this->adminA));
    }

    public function test_self_access_preservado_mesmo_sem_empresa(): void
    {
        $semEmpresa = User::factory()->semEmpresa()->create();

        $this->assertTrue(
            $semEmpresa->can('view', $semEmpresa),
            'Ver a si mesmo é por identidade, não depende de empresa.'
        );
        $this->assertTrue($semEmpresa->can('update', $semEmpresa));
    }

    public function test_admin_nao_deleta_a_si_mesmo(): void
    {
        $this->assertFalse($this->adminA->can('delete', $this->adminA));
        $this->assertFalse($this->adminA->can('forceDelete', $this->adminA));
    }
}
