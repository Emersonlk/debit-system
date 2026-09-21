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
 * CPF e e-mail são únicos dentro da empresa, não globalmente: duas empresas podem ter
 * o mesmo cliente na própria carteira.
 */
class ClienteUniquePorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $usuarioA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $usuarioA->assignRole('admin');
        $this->tokenA = $usuarioA->createToken('test-token')->plainTextToken;
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    private function requisicaoComoA(): \Tests\TestCase
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenA);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nome' => 'Cliente Novo',
            'email' => 'novo@example.com',
            'cpf' => '52998224725',
            'telefone' => '(11) 90000-0000',
        ], $override);
    }

    // ------------------------------------------------------------------- CPF

    public function test_cria_com_cpf_que_existe_somente_em_outra_empresa(): void
    {
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $response = $this->requisicaoComoA()->postJson('/api/clientes', $this->payload([
            'cpf' => $doB->cpf,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'id' => $response->json('data.id'),
            'cpf' => $doB->cpf,
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_nao_cria_com_cpf_ja_existente_na_propria_empresa(): void
    {
        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->postJson('/api/clientes', $this->payload(['cpf' => $doA->cpf]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }

    public function test_atualiza_mantendo_o_proprio_cpf(): void
    {
        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$doA->id}", [
                'nome' => 'Nome Atualizado',
                'cpf' => $doA->cpf,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('clientes', [
            'id' => $doA->id,
            'nome' => 'Nome Atualizado',
            'cpf' => $doA->cpf,
        ]);
    }

    public function test_nao_atualiza_para_cpf_de_outro_cliente_da_propria_empresa(): void
    {
        [$primeiro, $segundo] = $this->comoEmpresa(
            $this->empresaA,
            fn () => [Cliente::factory()->create(), Cliente::factory()->create()]
        );

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$primeiro->id}", ['cpf' => $segundo->cpf])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }

    public function test_atualiza_para_cpf_que_existe_somente_em_outra_empresa(): void
    {
        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $doB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$doA->id}", ['cpf' => $doB->cpf])
            ->assertStatus(200);

        $this->assertDatabaseHas('clientes', [
            'id' => $doA->id,
            'cpf' => $doB->cpf,
            'company_id' => $this->empresaA->id,
        ]);
    }

    // ----------------------------------------------------------------- EMAIL

    public function test_cria_com_email_que_existe_somente_em_outra_empresa(): void
    {
        $doB = $this->comoEmpresa(
            $this->empresaB,
            fn () => Cliente::factory()->create(['email' => 'compartilhado@example.com'])
        );

        $response = $this->requisicaoComoA()->postJson('/api/clientes', $this->payload([
            'email' => $doB->email,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'id' => $response->json('data.id'),
            'email' => 'compartilhado@example.com',
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_nao_cria_com_email_ja_existente_na_propria_empresa(): void
    {
        $doA = $this->comoEmpresa(
            $this->empresaA,
            fn () => Cliente::factory()->create(['email' => 'ocupado@example.com'])
        );

        $this->requisicaoComoA()
            ->postJson('/api/clientes', $this->payload(['email' => $doA->email]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_atualiza_mantendo_o_proprio_email(): void
    {
        $doA = $this->comoEmpresa(
            $this->empresaA,
            fn () => Cliente::factory()->create(['email' => 'meu@example.com'])
        );

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$doA->id}", [
                'nome' => 'Outro Nome',
                'email' => 'meu@example.com',
            ])
            ->assertStatus(200);
    }

    public function test_nao_atualiza_para_email_de_outro_cliente_da_propria_empresa(): void
    {
        [$primeiro, $segundo] = $this->comoEmpresa($this->empresaA, fn () => [
            Cliente::factory()->create(['email' => 'um@example.com']),
            Cliente::factory()->create(['email' => 'dois@example.com']),
        ]);

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$primeiro->id}", ['email' => $segundo->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_atualiza_para_email_que_existe_somente_em_outra_empresa(): void
    {
        $doA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $doB = $this->comoEmpresa(
            $this->empresaB,
            fn () => Cliente::factory()->create(['email' => 'so-da-b@example.com'])
        );

        $this->requisicaoComoA()
            ->putJson("/api/clientes/{$doA->id}", ['email' => $doB->email])
            ->assertStatus(200);

        $this->assertDatabaseHas('clientes', [
            'id' => $doA->id,
            'email' => 'so-da-b@example.com',
            'company_id' => $this->empresaA->id,
        ]);
    }

    // ------------------------------------------------------------- integração

    public function test_as_duas_empresas_podem_ter_o_mesmo_cpf_e_email(): void
    {
        $doB = $this->comoEmpresa(
            $this->empresaB,
            fn () => Cliente::factory()->create(['email' => 'mesmo@example.com'])
        );

        $this->requisicaoComoA()
            ->postJson('/api/clientes', $this->payload([
                'cpf' => $doB->cpf,
                'email' => $doB->email,
            ]))
            ->assertStatus(201);

        $this->assertSame(1, $this->comoEmpresa($this->empresaA, fn () => Cliente::count()));
        $this->assertSame(1, $this->comoEmpresa($this->empresaB, fn () => Cliente::count()));
    }
}
