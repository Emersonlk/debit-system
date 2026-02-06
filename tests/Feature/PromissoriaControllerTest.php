<?php

namespace Tests\Feature;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\Promissoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromissoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar roles e permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $operadorRole = Role::firstOrCreate(['name' => 'operador']);

        // Criar permissões
        $permissions = [
            'clientes.listar',
            'clientes.visualizar',
            'clientes.criar',
            'clientes.editar',
            'clientes.deletar',
            'promissorias.listar',
            'promissorias.visualizar',
            'promissorias.criar',
            'promissorias.editar',
            'promissorias.deletar',
            'promissorias.marcar-paga',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Atribuir todas as permissões ao admin
        $adminRole->givePermissionTo(Permission::all());

        // Cria um usuário para autenticação
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Atribui role admin ao usuário de teste
        $this->user->assignRole('admin');

        // Faz login e obtém o token
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->token = $response->json('token');
        $this->cliente = Cliente::factory()->create();
    }

    /**
     * Testa se a listagem de promissórias requer autenticação
     */
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/promissorias');
        $response->assertStatus(401);
    }

    /**
     * Testa a listagem de promissórias com autenticação
     */
    public function test_index_returns_paginated_promissorias(): void
    {
        Promissoria::factory()->count(20)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => [
                    '*' => ['id', 'cliente_id', 'valor', 'data_vencimento', 'status', 'cliente']
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200
            ]);

        $this->assertCount(15, $response->json('data')); // Padrão é 15 por página
    }

    /**
     * Testa a listagem com paginação customizada
     */
    public function test_index_with_custom_per_page(): void
    {
        Promissoria::factory()->count(25)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    /**
     * Testa filtro por status usando Enum
     */
    public function test_index_filters_by_status(): void
    {
        Promissoria::factory()->count(5)->create(['status' => PromissoriaStatus::PENDENTE->value]);
        Promissoria::factory()->count(3)->paga()->create();
        Promissoria::factory()->count(2)->vencida()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias?status=' . PromissoriaStatus::PAGA->value);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'status_code' => 200]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $promissoria) {
            $this->assertEquals(PromissoriaStatus::PAGA->value, $promissoria['status']);
        }
    }

    /**
     * Testa filtro por cliente_id
     */
    public function test_index_filters_by_cliente_id(): void
    {
        $cliente1 = Cliente::factory()->create();
        $cliente2 = Cliente::factory()->create();

        Promissoria::factory()->count(3)->create(['cliente_id' => $cliente1->id]);
        Promissoria::factory()->count(2)->create(['cliente_id' => $cliente2->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promissorias?cliente_id={$cliente1->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $promissoria) {
            $this->assertEquals($cliente1->id, $promissoria['cliente_id']);
        }
    }

    /**
     * Testa filtro de promissórias vencidas
     */
    public function test_index_filters_vencidas(): void
    {
        // Cria 3 promissórias vencidas (status VENCIDA, data no passado)
        Promissoria::factory()->count(3)->vencida()->create();
        
        // Cria 2 promissórias pagas (não devem aparecer no filtro)
        Promissoria::factory()->count(2)->paga()->create();
        
        // Cria 4 promissórias pendentes com data futura (não devem aparecer no filtro)
        Promissoria::factory()->count(4)->create([
            'data_vencimento' => now()->addDays(10)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias?vencidas=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        
        // Verifica que todas são vencidas
        foreach ($data as $promissoria) {
            $this->assertTrue(
                in_array($promissoria['status'], [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value]),
                'Todas as promissórias devem ter status PENDENTE ou VENCIDA'
            );
            $this->assertTrue(
                \Carbon\Carbon::parse($promissoria['data_vencimento'])->lt(now()),
                'Todas as promissórias devem ter data de vencimento no passado'
            );
        }
    }

    /**
     * Testa filtro de promissórias próximas do vencimento
     */
    public function test_index_filters_proximas_vencimento(): void
    {
        Promissoria::factory()->count(2)->proximaVencimento(2)->create();
        Promissoria::factory()->count(3)->proximaVencimento(5)->create();
        Promissoria::factory()->count(1)->vencida()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias?proximas_vencimento=1&dias=3');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Apenas as que vencem em até 3 dias
    }

    /**
     * Testa a criação de uma nova promissória
     */
    public function test_store_creates_new_promissoria(): void
    {
        $promissoriaData = [
            'cliente_id' => $this->cliente->id,
            'valor' => 500.50,
            'data_vencimento' => now()->addDays(15)->format('Y-m-d'),
            'observacoes' => 'Venda de produtos diversos',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', $promissoriaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data' => ['id', 'cliente_id', 'valor', 'data_vencimento', 'status', 'cliente']
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 201,
                'message' => 'Promissória criada com sucesso',
            ]);

        $this->assertDatabaseHas('promissorias', [
            'cliente_id' => $this->cliente->id,
            'valor' => '500.50',
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);
    }

    /**
     * Testa que promissória criada tem status padrão PENDENTE
     */
    public function test_store_creates_with_default_status_pendente(): void
    {
        $promissoriaData = [
            'cliente_id' => $this->cliente->id,
            'valor' => 300.00,
            'data_vencimento' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', $promissoriaData);

        $response->assertStatus(201);
        $promissoriaId = $response->json('data.id');
        
        $promissoria = Promissoria::find($promissoriaId);
        $this->assertEquals(PromissoriaStatus::PENDENTE, $promissoria->status);
    }

    /**
     * Testa validação ao criar promissória sem campos obrigatórios
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'status_code' => 422
            ]);
    }

    /**
     * Testa validação de cliente_id existente
     */
    public function test_store_validates_cliente_exists(): void
    {
        $promissoriaData = [
            'cliente_id' => 99999,
            'valor' => 500.00,
            'data_vencimento' => now()->addDays(15)->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', $promissoriaData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_id']);
    }

    /**
     * Testa validação de data_vencimento não pode ser no passado
     */
    public function test_store_validates_data_vencimento_not_past(): void
    {
        $promissoriaData = [
            'cliente_id' => $this->cliente->id,
            'valor' => 500.00,
            'data_vencimento' => now()->subDays(1)->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', $promissoriaData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data_vencimento']);
    }

    /**
     * Testa a exibição de uma promissória específica
     */
    public function test_show_returns_promissoria(): void
    {
        $promissoria = Promissoria::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promissorias/{$promissoria->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => ['id', 'cliente_id', 'valor', 'data_vencimento', 'status', 'cliente']
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'id' => $promissoria->id,
                    'status' => $promissoria->status->value,
                ]
            ]);
    }

    /**
     * Testa se show retorna 404 para promissória inexistente
     */
    public function test_show_returns_404_for_nonexistent_promissoria(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias/99999');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message'
            ])
            ->assertJson([
                'success' => false,
                'status_code' => 404
            ]);
    }

    /**
     * Testa a atualização de uma promissória
     */
    public function test_update_updates_promissoria(): void
    {
        $promissoria = Promissoria::factory()->create();

        $updateData = [
            'valor' => 750.00,
            'observacoes' => 'Valor atualizado',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/promissorias/{$promissoria->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória atualizada com sucesso',
            ]);

        $this->assertDatabaseHas('promissorias', [
            'id' => $promissoria->id,
            'valor' => '750.00',
        ]);
    }

    /**
     * Testa atualização de status usando Enum
     */
    public function test_update_with_status_using_enum(): void
    {
        $promissoria = Promissoria::factory()->create(['status' => PromissoriaStatus::PENDENTE->value]);

        $updateData = [
            'status' => PromissoriaStatus::PAGA->value,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/promissorias/{$promissoria->id}", $updateData);

        $response->assertStatus(200);
        $promissoria->refresh();
        $this->assertEquals(PromissoriaStatus::PAGA, $promissoria->status);
        $this->assertNotNull($promissoria->data_pagamento);
    }

    /**
     * Testa validação de status inválido
     */
    public function test_update_validates_status_using_enum(): void
    {
        $promissoria = Promissoria::factory()->create();

        $updateData = [
            'status' => 'status_invalido',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/promissorias/{$promissoria->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Testa a exclusão de uma promissória
     */
    public function test_destroy_deletes_promissoria(): void
    {
        $promissoria = Promissoria::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/promissorias/{$promissoria->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória removida com sucesso',
            ]);

        $this->assertDatabaseMissing('promissorias', ['id' => $promissoria->id]);
    }

    /**
     * Testa marcar promissória como paga
     */
    public function test_marcar_como_paga_successfully(): void
    {
        $promissoria = Promissoria::factory()->create(['status' => PromissoriaStatus::PENDENTE->value]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/marcar-como-paga");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória marcada como paga',
            ]);

        $promissoria->refresh();
        $this->assertEquals(PromissoriaStatus::PAGA, $promissoria->status);
        $this->assertNotNull($promissoria->data_pagamento);
    }

    /**
     * Testa que não pode marcar como paga uma promissória já paga
     */
    public function test_marcar_como_paga_prevents_already_paid(): void
    {
        $promissoria = Promissoria::factory()->paga()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/marcar-como-paga");

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Esta promissória já está marcada como paga.',
            ]);
    }

    /**
     * Testa resumo de vencimento
     */
    public function test_resumo_vencimento_returns_summary(): void
    {
        Promissoria::factory()->count(2)->proximaVencimento(2)->create();
        Promissoria::factory()->count(1)->vencida()->create();
        Promissoria::factory()->count(1)->paga()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias/resumo/vencimento?dias=3');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => [
                    'proximas_vencimento' => [
                        'quantidade',
                        'valor_total',
                        'dias_verificacao',
                        'data_limite',
                        'promissorias'
                    ],
                    'vencidas' => [
                        'quantidade',
                        'valor_total',
                        'promissorias'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['proximas_vencimento']['quantidade']);
        $this->assertEquals(1, $data['vencidas']['quantidade']);
    }

    /**
     * Testa que status_code está presente em todas as respostas de sucesso
     */
    public function test_all_success_responses_include_status_code(): void
    {
        $promissoria = Promissoria::factory()->create();

        // Test index
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias');
        $response->assertJsonPath('status_code', 200);

        // Test show
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promissorias/{$promissoria->id}");
        $response->assertJsonPath('status_code', 200);

        // Test store
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', [
                'cliente_id' => $this->cliente->id,
                'valor' => 100.00,
                'data_vencimento' => now()->addDays(10)->format('Y-m-d'),
            ]);
        $response->assertJsonPath('status_code', 201);

        // Test update
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/promissorias/{$promissoria->id}", ['valor' => 200.00]);
        $response->assertJsonPath('status_code', 200);
    }

    /**
     * Testa que status_code está presente em todas as respostas de erro
     */
    public function test_all_error_responses_include_status_code(): void
    {
        // Test 404
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/promissorias/99999');
        $response->assertJsonPath('status_code', 404);

        // Test 422 - validação
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/promissorias', []);
        $response->assertJsonPath('status_code', 422);
    }

    /**
     * Testa registro de pagamento parcial
     */
    public function test_registrar_pagamento_parcial_successfully(): void
    {
        $promissoria = Promissoria::factory()->create([
            'valor' => 1000.00,
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        $pagamentoData = [
            'valor_pago' => 300.00,
            'data_pagamento' => now()->format('Y-m-d'),
            'observacoes' => 'Primeiro pagamento',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/pagamento-parcial", $pagamentoData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data' => [
                    'promissoria',
                    'historico_pagamento',
                    'valor_total_pago',
                    'saldo_restante'
                ]
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 201,
                'message' => 'Pagamento parcial registrado com sucesso',
            ]);

        $promissoria->refresh();
        $this->assertEquals(300.00, (float) $promissoria->valor_total_pago);
        $this->assertEquals(700.00, (float) $promissoria->saldo_restante);
        $this->assertTrue($promissoria->temPagamentosParciais());
        $this->assertEquals(PromissoriaStatus::PENDENTE, $promissoria->status); // Ainda não está totalmente paga
    }

    /**
     * Testa registro de pagamento parcial que completa o valor total
     */
    public function test_registrar_pagamento_parcial_completa_promissoria(): void
    {
        $promissoria = Promissoria::factory()->create([
            'valor' => 500.00,
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        // Primeiro pagamento
        $promissoria->historicoPagamentos()->create([
            'valor_pago' => 200.00,
            'data_pagamento' => now()->subDays(1)->format('Y-m-d'),
        ]);

        // Segundo pagamento que completa
        $pagamentoData = [
            'valor_pago' => 300.00,
            'data_pagamento' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/pagamento-parcial", $pagamentoData);

        $response->assertStatus(201);

        $promissoria->refresh();
        $this->assertEquals(PromissoriaStatus::PAGA, $promissoria->status); // Agora está paga
        $this->assertEquals(500.00, (float) $promissoria->valor_total_pago);
        $this->assertEquals(0.00, (float) $promissoria->saldo_restante);
    }

    /**
     * Testa que não pode registrar pagamento parcial em promissória já paga
     */
    public function test_registrar_pagamento_parcial_prevents_already_paid(): void
    {
        $promissoria = Promissoria::factory()->paga()->create();

        $pagamentoData = [
            'valor_pago' => 100.00,
            'data_pagamento' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/pagamento-parcial", $pagamentoData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Não é possível registrar pagamento parcial em uma promissória já paga.',
            ]);
    }

    /**
     * Testa que não pode registrar pagamento parcial em promissória cancelada
     */
    public function test_registrar_pagamento_parcial_prevents_cancelled(): void
    {
        $promissoria = Promissoria::factory()->create([
            'status' => PromissoriaStatus::CANCELADA->value,
        ]);

        $pagamentoData = [
            'valor_pago' => 100.00,
            'data_pagamento' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/pagamento-parcial", $pagamentoData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Não é possível registrar pagamento parcial em uma promissória cancelada.',
            ]);
    }

    /**
     * Testa validação de valor excedendo saldo restante
     */
    public function test_registrar_pagamento_parcial_validates_value_exceeds_balance(): void
    {
        $promissoria = Promissoria::factory()->create([
            'valor' => 500.00,
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        // Primeiro pagamento
        $promissoria->historicoPagamentos()->create([
            'valor_pago' => 300.00,
            'data_pagamento' => now()->subDays(1)->format('Y-m-d'),
        ]);

        // Tenta pagar mais do que o saldo restante (200.00)
        $pagamentoData = [
            'valor_pago' => 250.00,
            'data_pagamento' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/pagamento-parcial", $pagamentoData);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
            'status_code' => 422,
        ]);
    }

    /**
     * Testa cancelamento de promissória
     */
    public function test_cancelar_promissoria_successfully(): void
    {
        $promissoria = Promissoria::factory()->create([
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        $cancelData = [
            'observacoes' => 'Cancelado por solicitação do cliente',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/cancelar", $cancelData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória cancelada com sucesso',
            ]);

        $promissoria->refresh();
        $this->assertEquals(PromissoriaStatus::CANCELADA, $promissoria->status);
    }

    /**
     * Testa que não pode cancelar promissória já paga
     */
    public function test_cancelar_promissoria_prevents_already_paid(): void
    {
        $promissoria = Promissoria::factory()->paga()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/cancelar", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Não é possível cancelar uma promissória já paga.',
            ]);
    }

    /**
     * Testa que não pode cancelar promissória já cancelada
     */
    public function test_cancelar_promissoria_prevents_already_cancelled(): void
    {
        $promissoria = Promissoria::factory()->create([
            'status' => PromissoriaStatus::CANCELADA->value,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/promissorias/{$promissoria->id}/cancelar", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status_code' => 422,
                'message' => 'Esta promissória já está cancelada.',
            ]);
    }

    /**
     * Testa obtenção de histórico de pagamentos
     */
    public function test_historico_pagamentos_returns_history(): void
    {
        $promissoria = Promissoria::factory()->create([
            'valor' => 1000.00,
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);

        // Cria alguns pagamentos
        $promissoria->historicoPagamentos()->create([
            'valor_pago' => 200.00,
            'data_pagamento' => now()->subDays(5)->format('Y-m-d'),
            'observacoes' => 'Primeiro pagamento',
        ]);

        $promissoria->historicoPagamentos()->create([
            'valor_pago' => 300.00,
            'data_pagamento' => now()->subDays(2)->format('Y-m-d'),
            'observacoes' => 'Segundo pagamento',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promissorias/{$promissoria->id}/historico-pagamentos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => [
                    'promissoria' => [
                        'id',
                        'cliente',
                        'valor',
                        'valor_total_pago',
                        'saldo_restante',
                        'status'
                    ],
                    'historico_pagamentos' => [
                        '*' => [
                            'id',
                            'valor_pago',
                            'data_pagamento',
                            'observacoes',
                            'created_at'
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'status_code' => 200,
            ]);

        $data = $response->json('data');
        $this->assertEquals('500.00', $data['promissoria']['valor_total_pago']);
        $this->assertEquals('500.00', $data['promissoria']['saldo_restante']);
        $this->assertCount(2, $data['historico_pagamentos']);
    }

    /**
     * Testa histórico de pagamentos vazio
     */
    public function test_historico_pagamentos_returns_empty_when_no_payments(): void
    {
        $promissoria = Promissoria::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/promissorias/{$promissoria->id}/historico-pagamentos");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('0.00', $data['promissoria']['valor_total_pago']);
        $this->assertCount(0, $data['historico_pagamentos']);
    }
}
