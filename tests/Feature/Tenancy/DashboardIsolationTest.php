<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PromissoriaStatus;
use App\Exceptions\TenantContextMissingException;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Dashboard\Metrics\DistribuicaoClienteMetric;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private string $tokenA;

    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $usuarioA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $usuarioA->assignRole('admin');
        $this->tokenA = $usuarioA->createToken('token-a')->plainTextToken;

        $usuarioB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $usuarioB->assignRole('admin');
        $this->tokenB = $usuarioB->createToken('token-b')->plainTextToken;
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    /**
     * O RequestGuard do Sanctum memoiza o usuário resolvido, e o auth manager é um
     * singleton que sobrevive entre chamadas HTTP dentro do mesmo teste. Sem este reset,
     * a segunda requisição continuaria autenticada como o usuário da primeira.
     *
     * Em produção isso não acontece: cada requisição boota a aplicação do zero.
     */
    private function comoUsuarioDoToken(string $token): \Tests\TestCase
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function dashboardVia(string $token, array $query = []): array
    {
        $url = '/api/dashboard' . ($query ? '?' . http_build_query($query) : '');

        $response = $this->comoUsuarioDoToken($token)->getJson($url);
        $response->assertStatus(200);

        return $response->json('data');
    }

    /**
     * Popula uma empresa com volume distinto, para que qualquer vazamento apareça
     * como número errado — e não como coincidência.
     *
     * @return array{clientes: int, promissorias: int}
     */
    private function popular(Company $company, int $qtdClientes, float $valor, string $prefixoNome): array
    {
        return $this->comoEmpresa($company, function () use ($qtdClientes, $valor, $prefixoNome) {
            for ($i = 1; $i <= $qtdClientes; $i++) {
                $cliente = Cliente::factory()->create(['nome' => "{$prefixoNome} {$i}"]);

                // pendente (entra em distribuicao_cliente e maiores_dividas)
                Promissoria::factory()->create([
                    'cliente_id' => $cliente->id,
                    'valor' => $valor,
                    'status' => PromissoriaStatus::PENDENTE->value,
                    'data_vencimento' => now()->addDays(10)->format('Y-m-d'),
                ]);

                // paga com histórico (entra em recebimentos_periodo e ultimos_pagamentos)
                $paga = Promissoria::factory()->create([
                    'cliente_id' => $cliente->id,
                    'valor' => $valor,
                    'status' => PromissoriaStatus::PAGA->value,
                    'data_pagamento' => now(),
                ]);
                $paga->historicoPagamentos()->create([
                    'valor_pago' => $valor,
                    'data_pagamento' => now()->format('Y-m-d'),
                ]);
            }

            return ['clientes' => $qtdClientes, 'promissorias' => $qtdClientes * 2];
        });
    }

    // ------------------------------------------------- Cenário A: dados isolados

    public function test_dashboard_de_cada_empresa_reflete_apenas_os_proprios_dados(): void
    {
        $this->popular($this->empresaA, 2, 100.00, 'Cliente A');
        $this->popular($this->empresaB, 5, 700.00, 'Cliente B');

        $dashboardA = $this->dashboardVia($this->tokenA);
        $dashboardB = $this->dashboardVia($this->tokenB);

        $this->assertSame(2, $dashboardA['clientes']['total']);
        $this->assertSame(5, $dashboardB['clientes']['total']);

        $this->assertSame(4, $dashboardA['promissorias']['total']);
        $this->assertSame(10, $dashboardB['promissorias']['total']);

        $this->assertSame('200.00', $dashboardA['recebimentos_periodo']['valor_total']);
        $this->assertSame('3500.00', $dashboardB['recebimentos_periodo']['valor_total']);

        $this->assertNotEquals($dashboardA, $dashboardB);
    }

    public function test_nenhuma_metrica_com_nomes_expoe_clientes_da_outra_empresa(): void
    {
        $this->popular($this->empresaA, 2, 100.00, 'Cliente A');
        $this->popular($this->empresaB, 3, 900.00, 'Cliente B');

        $dashboardA = json_encode($this->dashboardVia($this->tokenA));
        $dashboardB = json_encode($this->dashboardVia($this->tokenB));

        $this->assertStringNotContainsString('Cliente B', $dashboardA);
        $this->assertStringNotContainsString('Cliente A', $dashboardB);
    }

    // ------------------------------------------------ Cenário B: cache dashboard

    public function test_cache_do_dashboard_nao_e_compartilhado_entre_empresas(): void
    {
        $this->popular($this->empresaA, 2, 100.00, 'Cliente A');
        $this->popular($this->empresaB, 5, 700.00, 'Cliente B');

        // A primeira chamada popula o cache; a segunda usa parâmetros idênticos.
        $primeiroA = $this->dashboardVia($this->tokenA);
        $primeiroB = $this->dashboardVia($this->tokenB);

        $this->assertSame(2, $primeiroA['clientes']['total']);
        $this->assertSame(
            5,
            $primeiroB['clientes']['total'],
            'A empresa B recebeu o dashboard cacheado da empresa A.'
        );
    }

    public function test_cache_do_dashboard_permanece_isolado_em_chamadas_alternadas(): void
    {
        $this->popular($this->empresaA, 2, 100.00, 'Cliente A');
        $this->popular($this->empresaB, 5, 700.00, 'Cliente B');

        foreach (range(1, 2) as $rodada) {
            $this->assertSame(2, $this->dashboardVia($this->tokenA)['clientes']['total'], "rodada {$rodada} (A)");
            $this->assertSame(5, $this->dashboardVia($this->tokenB)['clientes']['total'], "rodada {$rodada} (B)");
        }
    }

    public function test_chave_de_cache_do_dashboard_contem_a_empresa(): void
    {
        $this->popular($this->empresaA, 1, 100.00, 'Cliente A');

        $this->dashboardVia($this->tokenA, ['periodo' => '7', 'dias' => 3]);

        [$inicio, $fim] = app(\App\Services\Dashboard\DashboardPeriodResolver::class)->resolve('7');
        $chaveDaEmpresa = 'dashboard.' . $this->empresaA->id . '.3.7.' . $inicio->format('Y-m-d') . '.' . $fim->format('Y-m-d');
        $chaveAntigaSemEmpresa = 'dashboard.3.7.' . $inicio->format('Y-m-d') . '.' . $fim->format('Y-m-d');

        $this->assertNotNull(Cache::get($chaveDaEmpresa), 'A chave deve ser escopada pela empresa.');
        $this->assertNull(Cache::get($chaveAntigaSemEmpresa), 'Não pode existir entrada sem empresa na chave.');
    }

    // --------------------------------------- Cenário C: cache do resumo vencimento

    public function test_cache_do_resumo_de_vencimento_nao_e_compartilhado(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->count(2)->create([
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_vencimento' => now()->addDays(2)->format('Y-m-d'),
        ]));
        $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->count(4)->create([
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_vencimento' => now()->addDays(2)->format('Y-m-d'),
        ]));

        $resumoA = $this->comoUsuarioDoToken($this->tokenA)
            ->getJson('/api/promissorias/resumo/vencimento?dias=3');
        $resumoB = $this->comoUsuarioDoToken($this->tokenB)
            ->getJson('/api/promissorias/resumo/vencimento?dias=3');

        $resumoA->assertStatus(200);
        $resumoB->assertStatus(200);

        $this->assertSame(2, $resumoA->json('data.proximas_vencimento.quantidade'));
        $this->assertSame(
            4,
            $resumoB->json('data.proximas_vencimento.quantidade'),
            'A empresa B recebeu o resumo cacheado da empresa A.'
        );
    }

    public function test_chave_de_cache_do_resumo_contem_a_empresa(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create([
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_vencimento' => now()->addDays(2)->format('Y-m-d'),
        ]));

        $this->comoUsuarioDoToken($this->tokenA)
            ->getJson('/api/promissorias/resumo/vencimento?dias=3')
            ->assertStatus(200);

        $this->assertNotNull(Cache::get('promissorias.resumo_vencimento.' . $this->empresaA->id . '.3'));
        $this->assertNull(
            Cache::get('promissorias.resumo_vencimento.3'),
            'Não pode existir entrada sem empresa na chave.'
        );
    }

    // ------------------------------------- Cenário D: DistribuicaoClienteMetric

    public function test_distribuicao_cliente_nao_mistura_empresas(): void
    {
        $this->popular($this->empresaA, 2, 150.00, 'Cliente A');
        $this->popular($this->empresaB, 3, 250.00, 'Cliente B');

        $metric = app(DistribuicaoClienteMetric::class);

        $dadosA = $this->comoEmpresa($this->empresaA, fn () => $metric->getData([]));
        $dadosB = $this->comoEmpresa($this->empresaB, fn () => $metric->getData([]));

        $this->assertCount(2, $dadosA);
        $this->assertCount(3, $dadosB);

        foreach ($dadosA as $linha) {
            $this->assertStringStartsWith('Cliente A', $linha['nome']);
            $this->assertSame('150.00', $linha['valor_a_receber']);
        }
        foreach ($dadosB as $linha) {
            $this->assertStringStartsWith('Cliente B', $linha['nome']);
            $this->assertSame('250.00', $linha['valor_a_receber']);
        }
    }

    public function test_distribuicao_cliente_ignora_cliente_id_de_outra_empresa(): void
    {
        $this->popular($this->empresaA, 1, 100.00, 'Cliente A');
        $this->popular($this->empresaB, 1, 100.00, 'Cliente B');

        $idsDaB = $this->comoEmpresa($this->empresaB, fn () => Cliente::pluck('id')->all());

        $dadosA = $this->comoEmpresa($this->empresaA, fn () => app(DistribuicaoClienteMetric::class)->getData([]));

        foreach ($dadosA as $linha) {
            $this->assertNotContains($linha['cliente_id'], $idsDaB);
        }
    }

    // ------------------------------------------------- Cenário E: fail-closed

    public function test_dashboard_sem_contexto_falha_de_forma_segura(): void
    {
        $this->popular($this->empresaA, 1, 100.00, 'Cliente A');

        $this->expectException(TenantContextMissingException::class);

        app(DashboardServiceInterface::class)->dadosParaGraficos();
    }

    public function test_distribuicao_cliente_sem_contexto_falha_de_forma_segura(): void
    {
        $this->popular($this->empresaA, 1, 100.00, 'Cliente A');

        $this->expectException(TenantContextMissingException::class);

        app(DistribuicaoClienteMetric::class)->getData([]);
    }

    public function test_dashboard_sem_contexto_nao_deixa_entrada_no_cache(): void
    {
        $this->popular($this->empresaA, 1, 100.00, 'Cliente A');

        try {
            app(DashboardServiceInterface::class)->dadosParaGraficos();
            $this->fail('A chamada sem contexto deveria ter falhado.');
        } catch (TenantContextMissingException) {
            // esperado
        }

        [$inicio, $fim] = app(\App\Services\Dashboard\DashboardPeriodResolver::class)->resolve('30');
        $this->assertNull(Cache::get('dashboard.3.30.' . $inicio->format('Y-m-d') . '.' . $fim->format('Y-m-d')));
        $this->assertNull(Cache::get('dashboard..3.30.' . $inicio->format('Y-m-d') . '.' . $fim->format('Y-m-d')));
    }

    public function test_endpoint_do_dashboard_exige_contexto_de_empresa(): void
    {
        $usuarioSemEmpresa = User::factory()->semEmpresa()->create();
        $usuarioSemEmpresa->assignRole('admin');
        $token = $usuarioSemEmpresa->createToken('sem-empresa')->plainTextToken;

        $this->comoUsuarioDoToken($token)
            ->getJson('/api/dashboard')
            ->assertStatus(403);
    }
}
