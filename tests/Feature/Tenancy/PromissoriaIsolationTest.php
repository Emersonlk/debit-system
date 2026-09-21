<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PromissoriaStatus;
use App\Exceptions\TenantContextMissingException;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromissoriaIsolationTest extends TestCase
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

    // ------------------------------------------------------------- isolamento

    public function test_lista_apenas_as_proprias_promissorias(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->count(2)->create());
        $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->count(3)->create());

        $response = $this->requisicaoComoA()->getJson('/api/promissorias');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_listagem_nao_inclui_promissorias_de_outra_empresa(): void
    {
        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());
        $daB = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create());

        $ids = array_column($this->requisicaoComoA()->getJson('/api/promissorias')->json('data'), 'id');

        $this->assertSame([$daA->id], $ids);
        $this->assertNotContains($daB->id, $ids);
    }

    /**
     * Todos os endpoints que recebem uma promissória por ID devem devolver 404 —
     * o global scope impede o route model binding de resolver o registro.
     *
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function endpointsComIdProvider(): array
    {
        return [
            'show' => ['getJson', '', []],
            'update' => ['putJson', '', ['valor' => 999]],
            'destroy' => ['deleteJson', '', []],
            'marcar-como-paga' => ['postJson', '/marcar-como-paga', []],
            'pagamento-parcial' => ['postJson', '/pagamento-parcial', ['valor_pago' => 10, 'data_pagamento' => '2026-01-01']],
            'cancelar' => ['postJson', '/cancelar', ['observacoes' => 'x']],
            'historico-pagamentos' => ['getJson', '/historico-pagamentos', []],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpointsComIdProvider')]
    public function test_endpoints_com_id_de_outra_empresa_retornam_404(string $metodo, string $sufixo, array $payload): void
    {
        $daB = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create());

        $response = $this->requisicaoComoA()->{$metodo}("/api/promissorias/{$daB->id}{$sufixo}", $payload);

        $response->assertStatus(404);
    }

    public function test_promissoria_de_outra_empresa_permanece_intacta_apos_tentativas(): void
    {
        $daB = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create([
            'valor' => 500,
            'status' => PromissoriaStatus::PENDENTE->value,
        ]));

        $this->requisicaoComoA()->putJson("/api/promissorias/{$daB->id}", ['valor' => 1])->assertStatus(404);
        $this->requisicaoComoA()->postJson("/api/promissorias/{$daB->id}/marcar-como-paga")->assertStatus(404);
        $this->requisicaoComoA()->deleteJson("/api/promissorias/{$daB->id}")->assertStatus(404);

        $recarregada = $this->comoEmpresa($this->empresaB, fn () => Promissoria::find($daB->id));
        $this->assertNotNull($recarregada);
        $this->assertSame('500.00', $recarregada->valor);
        $this->assertSame(PromissoriaStatus::PENDENTE, $recarregada->status);
    }

    // ------------------------------------------- integridade cliente/promissória

    public function test_cria_promissoria_para_cliente_da_propria_empresa(): void
    {
        $clienteA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $response = $this->requisicaoComoA()->postJson('/api/promissorias', [
            'cliente_id' => $clienteA->id,
            'valor' => 1500.00,
            'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promissorias', [
            'id' => $response->json('data.id'),
            'cliente_id' => $clienteA->id,
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_nao_cria_promissoria_para_cliente_de_outra_empresa(): void
    {
        $clienteB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->postJson('/api/promissorias', [
                'cliente_id' => $clienteB->id,
                'valor' => 1500.00,
                'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->assertSame(0, $this->comoEmpresa($this->empresaA, fn () => Promissoria::count()));
    }

    public function test_nao_cria_promissoria_para_cliente_inexistente(): void
    {
        $this->requisicaoComoA()
            ->postJson('/api/promissorias', [
                'cliente_id' => 999999,
                'valor' => 100.00,
                'data_vencimento' => now()->addDays(10)->format('Y-m-d'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_nao_reaponta_promissoria_para_cliente_de_outra_empresa(): void
    {
        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());
        $clienteB = $this->comoEmpresa($this->empresaB, fn () => Cliente::factory()->create());

        $this->requisicaoComoA()
            ->putJson("/api/promissorias/{$daA->id}", ['cliente_id' => $clienteB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->assertSame(
            $daA->cliente_id,
            $this->comoEmpresa($this->empresaA, fn () => Promissoria::find($daA->id))->cliente_id
        );
    }

    public function test_reaponta_promissoria_para_outro_cliente_da_propria_empresa(): void
    {
        [$daA, $outroClienteA] = $this->comoEmpresa($this->empresaA, fn () => [
            Promissoria::factory()->create(),
            Cliente::factory()->create(),
        ]);

        $this->requisicaoComoA()
            ->putJson("/api/promissorias/{$daA->id}", ['cliente_id' => $outroClienteA->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('promissorias', [
            'id' => $daA->id,
            'cliente_id' => $outroClienteA->id,
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_company_id_enviado_na_requisicao_e_ignorado(): void
    {
        $clienteA = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create());

        $response = $this->requisicaoComoA()->postJson('/api/promissorias', [
            'cliente_id' => $clienteA->id,
            'valor' => 800.00,
            'data_vencimento' => now()->addDays(15)->format('Y-m-d'),
            'company_id' => $this->empresaB->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promissorias', [
            'id' => $response->json('data.id'),
            'company_id' => $this->empresaA->id,
        ]);
    }

    // --------------------------------------------------------------- histórico

    public function test_historico_criado_recebe_a_empresa_da_promissoria(): void
    {
        $daA = $this->comoEmpresa(
            $this->empresaA,
            fn () => Promissoria::factory()->create(['valor' => 1000, 'status' => PromissoriaStatus::PENDENTE->value])
        );

        $this->requisicaoComoA()
            ->postJson("/api/promissorias/{$daA->id}/pagamento-parcial", [
                'valor_pago' => 400,
                'data_pagamento' => now()->format('Y-m-d'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('historico_pagamentos', [
            'promissoria_id' => $daA->id,
            'valor_pago' => 400.00,
            'company_id' => $this->empresaA->id,
        ]);
    }

    public function test_historico_da_propria_empresa_pode_ser_consultado(): void
    {
        $daA = $this->comoEmpresa(
            $this->empresaA,
            fn () => Promissoria::factory()->create(['valor' => 1000, 'status' => PromissoriaStatus::PENDENTE->value])
        );

        $this->requisicaoComoA()->postJson("/api/promissorias/{$daA->id}/pagamento-parcial", [
            'valor_pago' => 250,
            'data_pagamento' => now()->format('Y-m-d'),
        ])->assertStatus(201);

        $response = $this->requisicaoComoA()->getJson("/api/promissorias/{$daA->id}/historico-pagamentos");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.historico_pagamentos'));
    }

    public function test_historico_de_outra_empresa_nao_e_visivel(): void
    {
        $daB = $this->comoEmpresa($this->empresaB, function () {
            $p = Promissoria::factory()->create(['valor' => 1000, 'status' => PromissoriaStatus::PENDENTE->value]);
            $p->historicoPagamentos()->create([
                'valor_pago' => 100,
                'data_pagamento' => now()->format('Y-m-d'),
            ]);

            return $p;
        });

        // Pela API: a promissória sequer é resolvida.
        $this->requisicaoComoA()
            ->getJson("/api/promissorias/{$daB->id}/historico-pagamentos")
            ->assertStatus(404);

        // E o próprio model fica invisível no contexto da empresa A.
        $this->assertSame(0, $this->comoEmpresa($this->empresaA, fn () => HistoricoPagamento::count()));
        $this->assertSame(1, $this->comoEmpresa($this->empresaB, fn () => HistoricoPagamento::count()));
    }

    public function test_promissoria_de_outra_empresa_nao_e_alcancavel_para_criar_historico(): void
    {
        $daA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        // Estando no contexto da empresa B, a promissória da A não é sequer carregável —
        // é o que estruturalmente impede um histórico de nascer com empresa divergente
        // da sua promissória.
        $naoEncontrada = $this->comoEmpresa($this->empresaB, fn () => Promissoria::find($daA->id));

        $this->assertNull($naoEncontrada);
    }

    public function test_historico_e_promissoria_sempre_compartilham_a_empresa(): void
    {
        $daA = $this->comoEmpresa($this->empresaA, function () {
            $p = Promissoria::factory()->create(['valor' => 500, 'status' => PromissoriaStatus::PENDENTE->value]);
            $p->historicoPagamentos()->create([
                'valor_pago' => 200,
                'data_pagamento' => now()->format('Y-m-d'),
            ]);

            return $p;
        });

        $historico = $this->comoEmpresa(
            $this->empresaA,
            fn () => HistoricoPagamento::where('promissoria_id', $daA->id)->first()
        );

        $this->assertSame((int) $daA->company_id, (int) $historico->company_id);
        $this->assertSame($this->empresaA->id, (int) $historico->company_id);
    }

    // ------------------------------------------------------------- fail-closed

    public function test_criacao_de_promissoria_sem_contexto_falha_de_forma_segura(): void
    {
        $clienteId = $this->comoEmpresa($this->empresaA, fn () => Cliente::factory()->create())->id;

        $this->expectException(TenantContextMissingException::class);

        Promissoria::create([
            'cliente_id' => $clienteId,
            'valor' => 100,
            'data_vencimento' => now()->addDays(5)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);
    }

    public function test_consulta_de_promissoria_sem_contexto_falha_de_forma_segura(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create());

        $this->expectException(TenantContextMissingException::class);

        Promissoria::all();
    }

    public function test_consulta_de_historico_sem_contexto_falha_de_forma_segura(): void
    {
        $this->comoEmpresa($this->empresaA, function () {
            Promissoria::factory()->create()->historicoPagamentos()->create([
                'valor_pago' => 50,
                'data_pagamento' => now()->format('Y-m-d'),
            ]);
        });

        $this->expectException(TenantContextMissingException::class);

        HistoricoPagamento::count();
    }

    // --------------------------------------------- consultas fora do CRUD

    public function test_atualizar_status_vencidas_afeta_apenas_a_propria_empresa(): void
    {
        $vencidaA = $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->create([
            'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
        ]));
        $vencidaB = $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->create([
            'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
        ]));

        $afetadas = $this->comoEmpresa(
            $this->empresaA,
            fn () => app(\App\Repositories\PromissoriaRepository::class)->atualizarStatusVencidas()
        );

        $this->assertSame(1, $afetadas, 'O UPDATE em massa deve respeitar o global scope.');
        $this->assertSame(
            PromissoriaStatus::VENCIDA,
            $this->comoEmpresa($this->empresaA, fn () => Promissoria::find($vencidaA->id))->status
        );
        $this->assertSame(
            PromissoriaStatus::PENDENTE,
            $this->comoEmpresa($this->empresaB, fn () => Promissoria::find($vencidaB->id))->status,
            'A promissória da empresa B não pode ser alterada.'
        );
    }

    public function test_ordenacao_por_nome_do_cliente_nao_devolve_cliente_nulo(): void
    {
        $this->comoEmpresa($this->empresaA, fn () => Promissoria::factory()->count(3)->create());
        $this->comoEmpresa($this->empresaB, fn () => Promissoria::factory()->count(2)->create());

        $response = $this->requisicaoComoA()
            ->getJson('/api/promissorias?sort_by=cliente_nome&sort_order=asc');

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('meta.total'));

        foreach ($response->json('data') as $promissoria) {
            $this->assertNotNull(
                $promissoria['cliente'] ?? null,
                'Com Promissoria isolada, nenhuma linha pode vir com cliente nulo.'
            );
        }
    }
}
