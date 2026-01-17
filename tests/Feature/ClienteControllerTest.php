<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClienteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria um usuário para autenticação
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Faz login e obtém o token
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->token = $response->json('token');
    }

    /**
     * Testa se a listagem de clientes requer autenticação
     */
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/clientes');

        $response->assertStatus(401);
    }

    /**
     * Testa a listagem de clientes com autenticação
     */
    public function test_index_returns_paginated_clientes(): void
    {
        // Cria alguns clientes
        Cliente::factory()->count(20)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/clientes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'nome', 'cpf', 'email', 'telefone', 'endereco', 'created_at', 'updated_at']
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(15, $response->json('data')); // Padrão é 15 por página
    }

    /**
     * Testa a listagem com paginação customizada
     */
    public function test_index_with_custom_per_page(): void
    {
        Cliente::factory()->count(25)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/clientes?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    /**
     * Testa a criação de um novo cliente
     */
    public function test_store_creates_new_cliente(): void
    {
        $clienteData = [
            'nome' => 'João Silva',
            'cpf' => '11144477735', // CPF válido conhecido
            'email' => 'joao@example.com',
            'telefone' => '11999999999',
            'endereco' => 'Rua Teste, 123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/clientes', $clienteData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'nome', 'cpf', 'email', 'telefone', 'endereco']
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Cliente criado com sucesso',
            ]);

        $this->assertDatabaseHas('clientes', [
            'nome' => 'João Silva',
            'email' => 'joao@example.com',
        ]);
    }

    /**
     * Testa validação ao criar cliente sem campos obrigatórios
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/clientes', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'errors'
            ])
            ->assertJson(['success' => false]);
    }

    /**
     * Testa validação de email único
     */
    public function test_store_validates_unique_email(): void
    {
        Cliente::factory()->create(['email' => 'existing@example.com']);

        $clienteData = [
            'nome' => 'João Silva',
            'cpf' => '12345678901',
            'email' => 'existing@example.com',
            'telefone' => '11999999999',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Testa validação de CPF único
     */
    public function test_store_validates_unique_cpf(): void
    {
        $cpf = '11144477735'; // CPF válido conhecido
        Cliente::factory()->create(['cpf' => $cpf]);

        $clienteData = [
            'nome' => 'João Silva',
            'cpf' => $cpf,
            'email' => 'joao@example.com',
            'telefone' => '11999999999',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    /**
     * Testa a exibição de um cliente específico
     */
    public function test_show_returns_cliente(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'nome', 'cpf', 'email', 'telefone', 'endereco']
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $cliente->id,
                    'nome' => $cliente->nome,
                    'email' => $cliente->email,
                ]
            ]);
    }

    /**
     * Testa se show retorna 404 para cliente inexistente
     */
    public function test_show_returns_404_for_nonexistent_cliente(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/clientes/99999');

        $response->assertStatus(404);
    }

    /**
     * Testa a atualização de um cliente
     */
    public function test_update_updates_cliente(): void
    {
        $cliente = Cliente::factory()->create([
            'nome' => 'João Silva',
            'email' => 'joao@example.com',
        ]);

        $updateData = [
            'nome' => 'João Silva Santos',
            'telefone' => '11988888888',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/clientes/{$cliente->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Cliente atualizado com sucesso',
            ]);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'João Silva Santos',
            'telefone' => '11988888888',
        ]);
    }

    /**
     * Testa atualização completa de um cliente
     */
    public function test_update_updates_all_fields(): void
    {
        $cliente = Cliente::factory()->create();

        $updateData = [
            'nome' => 'Maria Santos',
            'cpf' => '11144477735', // CPF válido conhecido
            'email' => 'maria@example.com',
            'telefone' => '11977777777',
            'endereco' => 'Avenida Nova, 456',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/clientes/{$cliente->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);
    }

    /**
     * Testa validação de email único na atualização
     */
    public function test_update_validates_unique_email(): void
    {
        $cliente1 = Cliente::factory()->create(['email' => 'cliente1@example.com']);
        $cliente2 = Cliente::factory()->create(['email' => 'cliente2@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/clientes/{$cliente1->id}", [
                'email' => 'cliente2@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Testa que atualização permite manter o mesmo email do próprio cliente
     */
    public function test_update_allows_same_email_for_same_cliente(): void
    {
        $cliente = Cliente::factory()->create(['email' => 'joao@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/clientes/{$cliente->id}", [
                'nome' => 'João Silva Atualizado',
                'email' => 'joao@example.com', // Mesmo email
            ]);

        $response->assertStatus(200);
    }

    /**
     * Testa a exclusão de um cliente
     */
    public function test_destroy_deletes_cliente(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Cliente removido com sucesso',
            ]);

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    /**
     * Testa se destroy retorna 404 para cliente inexistente
     */
    public function test_destroy_returns_404_for_nonexistent_cliente(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/clientes/99999');

        $response->assertStatus(404);
    }

    /**
     * Testa ordenação dos clientes (mais recentes primeiro)
     */
    public function test_index_orders_clientes_by_created_at_desc(): void
    {
        $cliente1 = Cliente::factory()->create(['created_at' => now()->subDays(2)]);
        $cliente2 = Cliente::factory()->create(['created_at' => now()->subDays(1)]);
        $cliente3 = Cliente::factory()->create(['created_at' => now()]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/clientes');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verifica que o mais recente está primeiro
        $this->assertEquals($cliente3->id, $data[0]['id']);
        $this->assertEquals($cliente2->id, $data[1]['id']);
        $this->assertEquals($cliente1->id, $data[2]['id']);
    }

    /**
     * Testa criação de cliente com campos opcionais nulos
     */
    public function test_store_creates_cliente_with_nullable_fields(): void
    {
        $clienteData = [
            'nome' => 'João Silva',
            'cpf' => '11144477735', // CPF válido conhecido
            'email' => 'joao@example.com',
            'telefone' => null,
            'endereco' => null,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/clientes', $clienteData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'nome' => 'João Silva',
            'email' => 'joao@example.com',
        ]);
    }
}
