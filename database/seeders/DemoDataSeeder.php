<?php

namespace Database\Seeders;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Dados de clientes para demonstração/testes.
     */
    private array $clientesData = [
        [
            'nome' => 'Maria Silva',
            'email' => 'maria.silva@demo.test',
            'cpf' => '52998224725',
            'telefone' => '(11) 98765-4321',
            'endereco' => ['rua' => 'Rua das Flores', 'numero' => '100', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'estado' => 'SP', 'complemento' => 'Sala 1'],
        ],
        [
            'nome' => 'João Santos',
            'email' => 'joao.santos@demo.test',
            'cpf' => '12345678909',
            'telefone' => '(21) 99876-5432',
            'endereco' => ['rua' => 'Av. Brasil', 'numero' => '500', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'estado' => 'RJ'],
        ],
        [
            'nome' => 'Ana Oliveira',
            'email' => 'ana.oliveira@demo.test',
            'cpf' => '98765432100',
            'telefone' => '(31) 99123-4567',
            'endereco' => ['rua' => 'Rua da Bahia', 'numero' => '200', 'bairro' => 'Funcionários', 'cidade' => 'Belo Horizonte', 'estado' => 'MG'],
        ],
        [
            'nome' => 'Pedro Costa',
            'email' => 'pedro.costa@demo.test',
            'cpf' => '45678912345',
            'telefone' => '(41) 99234-5678',
            'endereco' => ['rua' => 'Rua XV de Novembro', 'numero' => '300', 'bairro' => 'Centro', 'cidade' => 'Curitiba', 'estado' => 'PR'],
        ],
        [
            'nome' => 'Carla Lima',
            'email' => 'carla.lima@demo.test',
            'cpf' => '32165498712',
            'telefone' => '(51) 99345-6789',
            'endereco' => ['rua' => 'Av. Borges de Medeiros', 'numero' => '150', 'bairro' => 'Centro', 'cidade' => 'Porto Alegre', 'estado' => 'RS'],
        ],
    ];

    /**
     * Popula dados de demonstração (usuários, clientes, promissórias).
     */
    public function run(): void
    {
        $this->command->info('Iniciando DemoDataSeeder...');

        $this->call([UserSeeder::class, RolePermissionSeeder::class]);

        $clientes = $this->criarClientes();

        $clienteIds = $clientes->pluck('id')->toArray();
        HistoricoPagamento::whereHas('promissoria', function ($q) use ($clienteIds) {
            $q->whereIn('cliente_id', $clienteIds);
        })->delete();
        Promissoria::whereIn('cliente_id', $clienteIds)->delete();

        $this->criarPromissorias($clientes);

        $this->command->info('DemoDataSeeder concluído.');
        $this->command->info('Clientes: ' . $clientes->count() . ' (emails *@demo.test)');
        $this->command->info('Login: test@example.com / password123 (admin) | operador@example.com / password123 (operador)');
    }

    private function criarClientes()
    {
        $clientes = collect();

        foreach ($this->clientesData as $dados) {
            $endereco = $dados['endereco'] ?? null;
            unset($dados['endereco']);
            $cliente = Cliente::firstOrCreate(
                ['email' => $dados['email']],
                $dados
            );
            if ($endereco && is_array($endereco)) {
                $cliente->endereco()->updateOrCreate(
                    ['cliente_id' => $cliente->id],
                    $endereco
                );
            }
            $clientes->push($cliente);
        }

        $this->command->info('Clientes de demonstração: ' . $clientes->count());
        return $clientes;
    }

    private function criarPromissorias($clientes): void
    {
        $now = now();

        // --- PENDENTES (vencimento futuro distante) ---
        Promissoria::create([
            'cliente_id' => $clientes[0]->id,
            'valor' => 1500.00,
            'data_vencimento' => $now->copy()->addDays(30)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => 'Promissória pendente - vencimento em 30 dias',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        Promissoria::create([
            'cliente_id' => $clientes[1]->id,
            'valor' => 3200.50,
            'data_vencimento' => $now->copy()->addDays(15)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => 'Pendente - 15 dias',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        // --- PRÓXIMAS DO VENCIMENTO (2-3 dias) ---
        Promissoria::create([
            'cliente_id' => $clientes[0]->id,
            'valor' => 800.00,
            'data_vencimento' => $now->copy()->addDays(2)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => 'Próxima do vencimento - 2 dias',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        Promissoria::create([
            'cliente_id' => $clientes[2]->id,
            'valor' => 2100.00,
            'data_vencimento' => $now->copy()->addDays(3)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => 'Próxima do vencimento - 3 dias',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        // --- VENCIDAS ---
        Promissoria::create([
            'cliente_id' => $clientes[1]->id,
            'valor' => 950.00,
            'data_vencimento' => $now->copy()->subDays(5)->format('Y-m-d'),
            'status' => PromissoriaStatus::VENCIDA->value,
            'observacoes' => 'Promissória vencida há 5 dias',
            'notificado' => true,
            'data_pagamento' => null,
        ]);

        Promissoria::create([
            'cliente_id' => $clientes[3]->id,
            'valor' => 1700.00,
            'data_vencimento' => $now->copy()->subDays(15)->format('Y-m-d'),
            'status' => PromissoriaStatus::VENCIDA->value,
            'observacoes' => 'Vencida há 15 dias',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        // --- PAGAS ---
        Promissoria::create([
            'cliente_id' => $clientes[0]->id,
            'valor' => 500.00,
            'data_vencimento' => $now->copy()->subDays(10)->format('Y-m-d'),
            'status' => PromissoriaStatus::PAGA->value,
            'observacoes' => 'Promissória já paga',
            'notificado' => false,
            'data_pagamento' => $now->copy()->subDays(8),
        ]);

        Promissoria::create([
            'cliente_id' => $clientes[2]->id,
            'valor' => 1200.00,
            'data_vencimento' => $now->copy()->subDays(3)->format('Y-m-d'),
            'status' => PromissoriaStatus::PAGA->value,
            'observacoes' => 'Paga',
            'notificado' => false,
            'data_pagamento' => $now->copy()->subDays(1),
        ]);

        // --- CANCELADAS ---
        Promissoria::create([
            'cliente_id' => $clientes[3]->id,
            'valor' => 3000.00,
            'data_vencimento' => $now->copy()->addDays(20)->format('Y-m-d'),
            'status' => PromissoriaStatus::CANCELADA->value,
            'observacoes' => 'Cancelada para testes',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        Promissoria::create([
            'cliente_id' => $clientes[4]->id,
            'valor' => 750.00,
            'data_vencimento' => $now->copy()->addDays(7)->format('Y-m-d'),
            'status' => PromissoriaStatus::CANCELADA->value,
            'observacoes' => 'Cancelada',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        // --- COM PAGAMENTO PARCIAL ---
        $comParcial = Promissoria::create([
            'cliente_id' => $clientes[4]->id,
            'valor' => 600.00,
            'valor_original' => 1000.00,
            'data_vencimento' => $now->copy()->addDays(14)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => 'Promissória com pagamento parcial - faltam R$ 600',
            'notificado' => false,
            'data_pagamento' => null,
        ]);

        HistoricoPagamento::create([
            'promissoria_id' => $comParcial->id,
            'valor_pago' => 200.00,
            'data_pagamento' => $now->copy()->subDays(10)->format('Y-m-d'),
            'observacoes' => 'Primeira parcela',
        ]);
        HistoricoPagamento::create([
            'promissoria_id' => $comParcial->id,
            'valor_pago' => 200.00,
            'data_pagamento' => $now->copy()->subDays(5)->format('Y-m-d'),
            'observacoes' => 'Segunda parcela',
        ]);

        $this->command->info('Promissórias criadas: pendentes, próximas vencimento, vencidas, pagas, canceladas, com pagamento parcial.');
    }
}
