<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureTenantContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Havia contexto de empresa no momento em que o route model binding foi resolvido?
     */
    public static ?bool $contextoNoBinding = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$contextoNoBinding = null;

        // Rotas exclusivas deste teste: exercitam o middleware sem expor endpoints
        // de depuração na API de produção (routes/api.php não é alterado).
        Route::middleware(['auth:sanctum', 'tenant'])->get(
            '/api/_test/tenant-context',
            fn (CurrentCompany $currentCompany) => response()->json([
                'company_id' => $currentCompany->id(),
            ])
        );

        Route::bind('probe', function (string $value) {
            self::$contextoNoBinding = app(CurrentCompany::class)->has();

            return $value;
        });

        // Middlewares declarados de propósito fora de ordem: a lista de prioridade
        // definida em bootstrap/app.php deve reordenar para tenant -> bindings.
        Route::middleware(['auth:sanctum', SubstituteBindings::class, 'tenant'])->get(
            '/api/_test/tenant-binding/{probe}',
            fn (string $probe) => response()->json(['probe' => $probe])
        );
    }

    private function tokenPara(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }

    public function test_usuario_com_empresa_prossegue_e_contexto_e_o_da_empresa(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-context');

        $response->assertStatus(200)
            ->assertJson(['company_id' => $company->id]);
    }

    public function test_usuario_sem_empresa_recebe_403(): void
    {
        Log::spy();

        $user = User::factory()->semEmpresa()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-context');

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'status_code' => 403]);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_super_admin_recebe_403_em_rota_tenant(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-context');

        $response->assertStatus(403);
    }

    public function test_requisicao_sem_autenticacao_para_em_auth_sanctum(): void
    {
        $this->getJson('/api/_test/tenant-context')->assertStatus(401);
    }

    public function test_resposta_negada_nao_expoe_detalhes_internos(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-context');

        $mensagem = $response->json('message');

        $this->assertSame('Acesso negado.', $mensagem);
        $this->assertStringNotContainsStringIgnoringCase('super', $mensagem);
        $this->assertStringNotContainsStringIgnoringCase('company', $mensagem);
    }

    public function test_contexto_e_estabelecido_antes_do_route_model_binding(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-binding/abc')
            ->assertStatus(200);

        $this->assertTrue(
            self::$contextoNoBinding,
            'SubstituteBindings deve rodar depois do middleware tenant.'
        );
    }

    public function test_binding_nao_roda_quando_o_tenant_e_negado(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/_test/tenant-binding/abc')
            ->assertStatus(403);

        $this->assertNull(
            self::$contextoNoBinding,
            'O binding não deve ser resolvido em requisição barrada pelo tenant.'
        );
    }

    public function test_rotas_reais_da_api_exigem_contexto_de_empresa(): void
    {
        $user = User::factory()->semEmpresa()->create();
        $token = $this->tokenPara($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/clientes')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard')
            ->assertStatus(403);
    }

    public function test_super_admin_nao_acessa_rotas_reais_da_api(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenPara($user))
            ->getJson('/api/clientes')
            ->assertStatus(403);
    }
}
