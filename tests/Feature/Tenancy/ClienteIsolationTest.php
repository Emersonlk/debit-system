<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenantContextMissingException;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private User $usuarioA;

    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->usuarioA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->usuarioA->assignRole('admin');
        $this->tokenA = $this->usuarioA->createToken('test-token')->plainTextToken;
    }

    /**
     * Cria registros no contexto de uma empresa específica, sem depender de quem está
     * autenticado — é a forma explícita prevista pela arquitetura.
     */
    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    private function requisicaoComoA(): \Illuminate\Testing\TestResponse|\Tests\TestCase
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenA);
    }

    // ---------------------------------------------------------------- leitura

    public function test_empresa_lista_os_proprios_clientes(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->count(2)->create());

        $response = $this->requisicaoComoA()->getJson('/api/clientes');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_listagem_nao_inclui_clientes_de_outra_empresa(): void
    {
        $doA = $this->comoEmpresa(
            $this->empresaA,
            fn () => Cliente::factory()->create(['nome' => 'Cliente da Empresa A'])
        );
        $this->comoEmpresa(
            $this->empresaB,
            fn () => Cliente::factory()->count(3)->create(['nome' => 'Cliente da Empresa B'])
        );

        $response = $this->requisicaoComoA()->getJson('/api/clientes');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'), 'A listagem deve conter apenas o cliente da empresa A.');
        $this->assertSame([$doA->id], array_column($response->json('data'), 'id'));
        $response->assertJsonMissing(['nome' => 'Cliente da Empresa B']);
    }

    public function test_nao_obtem_cliente_de_outra_empresa_pelo_id(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->getJson("/api/clientes/{$doB->id}")
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------- escrita

    public function test_nao_atualiza_cliente_de_outra_empresa(): void
    {
        $doB = $this->comoEmpresa(
            $this->empresaB,
            fn () => Cliente::factory()->create(['nome' => 'Nome Original'])
        );

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$doB->id}", ['nome' => 'Nome Invadido'])
            ->assertStatus(404);

        $recarregado = $this->comoEmpresa($this->empresaB, fn () => Cliente::find($doB->id));
        $this->assertSame('Nome Original', $recarregado->nome);
    }

    public function test_nao_exclui_cliente_de_outra_empresa(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->deleteJson("/api/clientes/{$doB->id}")
            ->assertStatus(404);

        $aindaExiste = $this->comoEmpresa($this->empresaB, fn () => Cliente::find($doB->id));
        $this->assertNotNull($aindaExiste, 'O cliente da empresa B não pode ser excluído pela empresa A.');
    }

    public function test_cliente_criado_recebe_a_empresa_do_contexto(): void
    {
        $response = $this->requisicaoComoA()->postJson('/api/clientes', [
            'nome' => 'Novo Cliente',
            'email' => 'novo@example.com',
            'cpf' => '52998224725',
            'telefone' => '(11) 90000-0000',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('clientes', [
            'id' => $response->json('data.id'),
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_company_id_enviado_na_requisicao_e_ignorado(): void
    {
        $response = $this->requisicaoComoA()->postJson('/api/clientes', [
            'nome' => 'Cliente Forjado',
            'email' => 'forjado@example.com',
            'cpf' => '12345678909',
            'telefone' => '(11) 91111-1111',
            'company_id' => $this->empresaB->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('clientes', [
            'id' => $response->json('data.id'),
            'company_id' => $this->empresaA->id,
        ]);
        $this->assertDatabaseMissing('clientes', [
            'id' => $response->json('data.id'),
            'company_id' => $this->empresaB->id,
        ]);
    }

    // ------------------------------------------------------------ fail-closed

    public function test_criacao_sem_contexto_falha_de_forma_segura(): void
    {
        $this->expectException(TenantContextMissingException::class);

        Cliente::create([
            'nome' => 'Sem Empresa',
            'email' => 'sem@example.com',
            'cpf' => '98765432100',
            'telefone' => '(11) 92222-2222',
        ]);
    }

    public function test_consulta_sem_contexto_falha_de_forma_segura(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->expectException(TenantContextMissingException::class);

        Cliente::all();
    }

    public function test_contagem_sem_contexto_nao_retorna_dados_globais(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->expectException(TenantContextMissingException::class);

        Cliente::count();
    }

    public function test_run_as_isola_a_contagem_por_empresa(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->count(2)->create());
        $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->count(5)->create());

        $this->assertSame(2, $this->comoEmpresa($this->empresaA, fn () => Cliente::count()));
        $this->assertSame(5, $this->comoEmpresa($this->empresaB, fn () => Cliente::count()));
    }
}
