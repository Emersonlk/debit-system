<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Endpoints administrativos de usuário isolados por empresa.
 *
 * A listagem é filtrada explicitamente no controller (viewAny não recebe model, e
 * User não tem global scope); os endpoints com {usuario} são resolvidos pelo
 * middleware tenant.usuario, que devolve 404 para usuário de outra empresa.
 */
class UserAdminEndpointIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private User $adminA;

    private User $operadorA;

    private User $adminB;

    private User $operadorB;

    private User $semEmpresa;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'operador']);
        Permission::firstOrCreate(['name' => 'clientes.listar']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->adminA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->adminA->assignRole('admin');
        $this->operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->operadorA->assignRole('operador');

        $this->adminB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->adminB->assignRole('admin');
        $this->operadorB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->operadorB->assignRole('operador');

        $this->semEmpresa = User::factory()->semEmpresa()->create();
        $this->semEmpresa->assignRole('operador');
    }

    /**
     * O RequestGuard do Sanctum memoiza o usuário, e o auth manager sobrevive entre
     * chamadas HTTP no mesmo teste. Em produção cada requisição boota a app do zero.
     */
    private function como(User $usuario): \Tests\TestCase
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader(
            'Authorization',
            'Bearer ' . $usuario->createToken('test-token')->plainTextToken
        );
    }

    // ------------------------------------------------------------- listagem

    public function test_admin_lista_somente_usuarios_da_propria_empresa(): void
    {
        $response = $this->como($this->adminA)->getJson('/api/permissoes/usuarios');

        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        sort($ids);
        $esperado = [$this->adminA->id, $this->operadorA->id];
        sort($esperado);

        $this->assertSame($esperado, $ids);
    }

    public function test_listagem_nao_inclui_usuarios_de_outra_empresa(): void
    {
        $response = $this->como($this->adminA)->getJson('/api/permissoes/usuarios');

        $ids = array_column($response->json('data'), 'id');

        $this->assertNotContains($this->adminB->id, $ids);
        $this->assertNotContains($this->operadorB->id, $ids);
        $response->assertJsonMissing(['email' => $this->adminB->email]);
    }

    public function test_listagem_nao_inclui_usuarios_sem_empresa(): void
    {
        $response = $this->como($this->adminA)->getJson('/api/permissoes/usuarios');

        $ids = array_column($response->json('data'), 'id');

        $this->assertNotContains(
            $this->semEmpresa->id,
            $ids,
            'Usuário com company_id nulo não pertence a nenhuma empresa.'
        );
    }

    public function test_admin_da_outra_empresa_recebe_a_propria_lista(): void
    {
        $response = $this->como($this->adminB)->getJson('/api/permissoes/usuarios');

        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        sort($ids);
        $esperado = [$this->adminB->id, $this->operadorB->id];
        sort($esperado);

        $this->assertSame($esperado, $ids);
    }

    public function test_listagem_preserva_a_estrutura_da_resposta(): void
    {
        $response = $this->como($this->adminA)->getJson('/api/permissoes/usuarios');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => [['id', 'name', 'email', 'roles', 'permissions']],
            ]);

        $primeiro = collect($response->json('data'))->firstWhere('id', $this->adminA->id);
        $this->assertContains('admin', $primeiro['roles']);
    }

    public function test_operador_continua_sem_acesso_a_listagem(): void
    {
        $this->como($this->operadorA)
            ->getJson('/api/permissoes/usuarios')
            ->assertStatus(403);
    }

    // ------------------------------------------------- endpoints com {usuario}

    public function test_admin_acessa_usuario_da_propria_empresa(): void
    {
        $this->como($this->adminA)
            ->getJson("/api/permissoes/usuarios/{$this->operadorA->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->operadorA->id);
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function endpointsComIdProvider(): array
    {
        return [
            'visualizar' => ['getJson', '', []],
            'atribuir role' => ['postJson', '/role', ['role' => 'operador']],
            'remover role' => ['deleteJson', '/role', ['role' => 'operador']],
            'atribuir permissao' => ['postJson', '/permissao', ['permission' => 'clientes.listar']],
            'remover permissao' => ['deleteJson', '/permissao', ['permission' => 'clientes.listar']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpointsComIdProvider')]
    public function test_usuario_de_outra_empresa_resulta_em_404(string $metodo, string $sufixo, array $payload): void
    {
        $this->como($this->adminA)
            ->{$metodo}("/api/permissoes/usuarios/{$this->operadorB->id}{$sufixo}", $payload)
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpointsComIdProvider')]
    public function test_usuario_sem_empresa_resulta_em_404(string $metodo, string $sufixo, array $payload): void
    {
        $this->como($this->adminA)
            ->{$metodo}("/api/permissoes/usuarios/{$this->semEmpresa->id}{$sufixo}", $payload)
            ->assertStatus(404);
    }

    public function test_admin_da_empresa_b_nao_alcanca_usuario_da_empresa_a(): void
    {
        $this->como($this->adminB)
            ->getJson("/api/permissoes/usuarios/{$this->operadorA->id}")
            ->assertStatus(404);
    }

    public function test_roles_de_usuario_de_outra_empresa_permanecem_intactas(): void
    {
        $this->como($this->adminA)
            ->postJson("/api/permissoes/usuarios/{$this->operadorB->id}/role", ['role' => 'admin'])
            ->assertStatus(404);

        $this->assertFalse(
            $this->operadorB->fresh()->hasRole('admin'),
            'Um Admin da empresa A não pode promover usuário da empresa B.'
        );
    }

    public function test_admin_gerencia_role_de_usuario_da_propria_empresa(): void
    {
        $this->como($this->adminA)
            ->postJson("/api/permissoes/usuarios/{$this->operadorA->id}/role", ['role' => 'admin'])
            ->assertStatus(200);

        $this->assertTrue($this->operadorA->fresh()->hasRole('admin'));
    }

    public function test_operador_nao_acessa_endpoints_com_id(): void
    {
        $this->como($this->operadorA)
            ->getJson("/api/permissoes/usuarios/{$this->adminA->id}")
            ->assertStatus(403);

        $this->como($this->operadorA)
            ->postJson("/api/permissoes/usuarios/{$this->adminA->id}/role", ['role' => 'admin'])
            ->assertStatus(403);
    }

    // ------------------------------------------------------------ regressão

    public function test_self_access_continua_funcionando(): void
    {
        $this->como($this->operadorA)
            ->getJson("/api/permissoes/usuarios/{$this->operadorA->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->operadorA->id);
    }

    public function test_minhas_permissoes_continua_acessivel_a_qualquer_usuario(): void
    {
        $this->como($this->operadorA)
            ->getJson('/api/permissoes/minhas')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $this->operadorA->id);
    }

    public function test_super_admin_continua_bloqueado_nas_rotas_tenant(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $superAdmin->assignRole('admin');

        $this->como($superAdmin)
            ->getJson('/api/permissoes/usuarios')
            ->assertStatus(403);

        $this->como($superAdmin)
            ->getJson("/api/permissoes/usuarios/{$this->operadorA->id}")
            ->assertStatus(403);
    }
}
