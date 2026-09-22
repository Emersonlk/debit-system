<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\Endereco;
use App\Models\Promissoria;
use App\Models\User;
use App\Services\Contracts\AuditServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A empresa do log vem do model auditado (fonte primária), com o usuário como
 * fallback. O AuditLog não usa BelongsToCompany: auditoria é observabilidade, não
 * barreira de acesso, e por isso não derruba a operação de negócio quando a empresa
 * não é determinável.
 */
class AuditLogTenantTest extends TestCase
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

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->adminA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->adminA->assignRole('admin');
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    private function servico(): AuditServiceInterface
    {
        return app(AuditServiceInterface::class);
    }

    // ------------------------------------- empresa vem do model auditado

    public function test_log_create_grava_a_empresa_do_model(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logCreate($cliente, $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $log->company_id);
        $this->assertSame('create', $log->action);
    }

    public function test_log_update_grava_a_empresa_do_model(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logUpdate($cliente, ['nome' => 'Antigo'], $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $log->company_id);
        $this->assertSame('update', $log->action);
    }

    public function test_log_delete_grava_a_empresa_do_model(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logDelete($cliente, $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $log->company_id);
        $this->assertSame('delete', $log->action);
    }

    public function test_log_view_grava_a_empresa_do_model(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logView($cliente, $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $log->company_id);
        $this->assertSame('view', $log->action);
    }

    public function test_cada_empresa_produz_log_com_a_propria_empresa(): void
    {
        $clienteA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $clienteB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $logA = $this->servico()->logView($clienteA, $this->adminA);
        $logB = $this->servico()->logView($clienteB, $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $logA->company_id);
        $this->assertSame(
            $this->empresaB->id,
            (int) $logB->company_id,
            'A empresa vem do model auditado, não do usuário que agiu.'
        );
    }

    public function test_promissoria_tambem_tem_a_empresa_gravada(): void
    {
        $promissoria = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create());

        $log = $this->servico()->logCreate($promissoria, $this->adminA);

        $this->assertSame($this->empresaB->id, (int) $log->company_id);
    }

    /**
     * O model é a fonte primária: mesmo que o usuário pertença a outra empresa, o log
     * segue o dado auditado. É o que fará o log ficar correto quando um Super Admin
     * (company_id nulo) agir sobre a empresa X pelas rotas administrativas.
     */
    public function test_empresa_do_model_prevalece_sobre_a_do_usuario(): void
    {
        $clienteB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $log = $this->servico()->logView($clienteB, $this->adminA);

        $this->assertSame($this->empresaB->id, (int) $log->company_id);
        $this->assertNotSame($this->adminA->company_id, (int) $log->company_id);
    }

    // ------------------------------------------------ fallback e ausência

    public function test_model_sem_empresa_usa_a_empresa_do_usuario(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $endereco = Endereco::create([
            'cliente_id' => $cliente->id,
            'rua' => 'Rua Sem Empresa',
            'numero' => '10',
        ]);

        $this->assertNull($endereco->getAttribute('company_id'), 'Endereco não tem company_id.');

        $log = $this->servico()->logCreate($endereco, $this->adminA);

        $this->assertSame($this->empresaA->id, (int) $log->company_id);
    }

    public function test_sem_empresa_determinavel_grava_null_e_nao_lanca(): void
    {
        Log::spy();

        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $endereco = Endereco::create([
            'cliente_id' => $cliente->id,
            'rua' => 'Rua Órfã',
            'numero' => '20',
        ]);

        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();

        $log = $this->servico()->logCreate($endereco, $usuarioSemEmpresa);

        $this->assertNull($log->company_id);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'company_id' => null]);
    }

    public function test_sem_empresa_determinavel_emite_warning(): void
    {
        Log::spy();

        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $endereco = Endereco::create([
            'cliente_id' => $cliente->id,
            'rua' => 'Rua Órfã',
            'numero' => '30',
        ]);

        $this->servico()->logView($endereco, User::factory()->semEmpresa()->create());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $mensagem, array $contexto = []) => str_contains($mensagem, 'sem empresa')
                && $contexto['model_type'] === Endereco::class
                && $contexto['action'] === 'view')
            ->once();
    }

    public function test_operacao_principal_nao_e_interrompida_sem_empresa(): void
    {
        Log::spy();

        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $endereco = Endereco::create([
            'cliente_id' => $cliente->id,
            'rua' => 'Rua Órfã',
            'numero' => '40',
        ]);

        // A ausência de empresa é exceção deliberada ao fail-closed: nada é lançado.
        $log = $this->servico()->logDelete($endereco, User::factory()->semEmpresa()->create());

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertTrue($log->exists);
    }

    // ------------------------------------------------- mass assignment

    public function test_company_id_nao_pode_ser_definido_por_mass_assignment(): void
    {
        $log = AuditLog::create([
            'company_id' => $this->empresaB->id,
            'user_id' => $this->adminA->id,
            'action' => 'create',
            'model_type' => Cliente::class,
            'model_id' => 1,
        ]);

        $this->assertNull(
            $log->company_id,
            'company_id está fora do $fillable e deve ser descartado na atribuição em massa.'
        );
    }

    public function test_company_id_do_request_nao_contamina_o_log(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logUpdate(
            $cliente,
            ['company_id' => $this->empresaB->id, 'nome' => 'Antigo'],
            $this->adminA
        );

        $this->assertSame(
            $this->empresaA->id,
            (int) $log->company_id,
            'Valores auditados não podem influenciar a empresa do log.'
        );
    }

    // --------------------------------------------- payload inalterado

    public function test_old_values_e_new_values_continuam_como_antes(): void
    {
        $cliente = $this->comoEmpresa(
            $this->empresaA,
            fn () => Cliente::factory()->create(['nome' => 'Nome Atual'])
        );

        $antigos = ['nome' => 'Nome Antigo', 'email' => 'antigo@example.com'];

        $log = $this->servico()->logUpdate($cliente, $antigos, $this->adminA);

        $this->assertSame($antigos, $log->old_values);
        $this->assertSame($cliente->getAttributes(), $log->new_values);
        $this->assertSame(Cliente::class, $log->model_type);
        $this->assertSame($cliente->id, $log->model_id);
        $this->assertSame($this->adminA->id, $log->user_id);
    }

    public function test_log_view_nao_guarda_valores(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logView($cliente, $this->adminA);

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_relacao_company_resolve_a_empresa(): void
    {
        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $log = $this->servico()->logCreate($cliente, $this->adminA);

        $this->assertTrue($this->empresaA->is($log->company));
    }

    // ------------------------------------------------------- integração

    public function test_post_clientes_produz_log_com_a_empresa_correta(): void
    {
        $token = $this->adminA->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/clientes', [
                'nome' => 'Cliente Auditado',
                'email' => 'auditado@example.com',
                'cpf' => '52998224725',
                'telefone' => '(11) 90000-0000',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'model_type' => Cliente::class,
            'model_id' => $response->json('data.id'),
            'company_id' => $this->empresaA->id,
            'user_id' => $this->adminA->id,
        ]);
    }

    public function test_rotas_de_cliente_e_promissoria_continuam_funcionando(): void
    {
        $token = $this->adminA->createToken('test-token')->plainTextToken;
        $cabecalho = ['Authorization' => 'Bearer ' . $token];

        $cliente = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());
        $promissoria = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        $this->withHeaders($cabecalho)->getJson("/api/clientes/{$cliente->id}")->assertStatus(200);
        $this->withHeaders($cabecalho)->getJson("/api/promissorias/{$promissoria->id}")->assertStatus(200);
        $this->withHeaders($cabecalho)
            ->putJson("/api/clientes/{$cliente->id}", ['nome' => 'Atualizado'])
            ->assertStatus(200);

        // Cada requisição acima gerou log, todos na empresa correta.
        $this->assertSame(
            0,
            AuditLog::whereNull('company_id')->count(),
            'Nenhum log das rotas normais pode ficar sem empresa.'
        );
    }
}
