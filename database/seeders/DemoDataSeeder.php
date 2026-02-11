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
     * Dados de clientes para demonstração/testes (dashboard).
     */
    private array $clientesData = [
        ['nome' => 'Maria Silva', 'email' => 'maria.silva@demo.test', 'cpf' => '52998224725', 'telefone' => '(11) 98765-4321', 'endereco' => ['rua' => 'Rua das Flores', 'numero' => '100', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'estado' => 'SP']],
        ['nome' => 'João Santos', 'email' => 'joao.santos@demo.test', 'cpf' => '12345678909', 'telefone' => '(21) 99876-5432', 'endereco' => ['rua' => 'Av. Brasil', 'numero' => '500', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'estado' => 'RJ']],
        ['nome' => 'Ana Oliveira', 'email' => 'ana.oliveira@demo.test', 'cpf' => '98765432100', 'telefone' => '(31) 99123-4567', 'endereco' => ['rua' => 'Rua da Bahia', 'numero' => '200', 'bairro' => 'Funcionários', 'cidade' => 'Belo Horizonte', 'estado' => 'MG']],
        ['nome' => 'Pedro Costa', 'email' => 'pedro.costa@demo.test', 'cpf' => '45678912345', 'telefone' => '(41) 99234-5678', 'endereco' => ['rua' => 'Rua XV de Novembro', 'numero' => '300', 'bairro' => 'Centro', 'cidade' => 'Curitiba', 'estado' => 'PR']],
        ['nome' => 'Carla Lima', 'email' => 'carla.lima@demo.test', 'cpf' => '32165498712', 'telefone' => '(51) 99345-6789', 'endereco' => ['rua' => 'Av. Borges de Medeiros', 'numero' => '150', 'bairro' => 'Centro', 'cidade' => 'Porto Alegre', 'estado' => 'RS']],
        ['nome' => 'Roberto Almeida', 'email' => 'roberto.almeida@demo.test', 'cpf' => '11122233344', 'telefone' => '(11) 97777-1111', 'endereco' => ['rua' => 'Rua Augusta', 'numero' => '2000', 'bairro' => 'Consolação', 'cidade' => 'São Paulo', 'estado' => 'SP']],
        ['nome' => 'Fernanda Souza', 'email' => 'fernanda.souza@demo.test', 'cpf' => '55566677788', 'telefone' => '(21) 96666-2222', 'endereco' => ['rua' => 'Rua do Ouvidor', 'numero' => '50', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'estado' => 'RJ']],
        ['nome' => 'Lucas Martins', 'email' => 'lucas.martins@demo.test', 'cpf' => '99988877766', 'telefone' => '(31) 95555-3333', 'endereco' => ['rua' => 'Av. Afonso Pena', 'numero' => '1000', 'bairro' => 'Centro', 'cidade' => 'Belo Horizonte', 'estado' => 'MG']],
        ['nome' => 'Juliana Ribeiro', 'email' => 'juliana.ribeiro@demo.test', 'cpf' => '44433322211', 'telefone' => '(41) 94444-4444', 'endereco' => ['rua' => 'Rua Conselheiro Laurindo', 'numero' => '400', 'bairro' => 'Centro', 'cidade' => 'Curitiba', 'estado' => 'PR']],
        ['nome' => 'Marcos Pereira', 'email' => 'marcos.pereira@demo.test', 'cpf' => '77788899900', 'telefone' => '(51) 93333-5555', 'endereco' => ['rua' => 'Rua dos Andradas', 'numero' => '800', 'bairro' => 'Centro', 'cidade' => 'Porto Alegre', 'estado' => 'RS']],
        ['nome' => 'Patrícia Ferreira', 'email' => 'patricia.ferreira@demo.test', 'cpf' => '22233344455', 'telefone' => '(11) 92222-6666', 'endereco' => ['rua' => 'Av. Paulista', 'numero' => '1500', 'bairro' => 'Bela Vista', 'cidade' => 'São Paulo', 'estado' => 'SP']],
        ['nome' => 'Ricardo Nascimento', 'email' => 'ricardo.nascimento@demo.test', 'cpf' => '66655544433', 'telefone' => '(21) 91111-7777', 'endereco' => ['rua' => 'Av. Nossa Senhora de Copacabana', 'numero' => '600', 'bairro' => 'Copacabana', 'cidade' => 'Rio de Janeiro', 'estado' => 'RJ']],
        ['nome' => 'Amanda Cardoso', 'email' => 'amanda.cardoso@demo.test', 'cpf' => '33344455566', 'telefone' => '(31) 90000-8888', 'endereco' => ['rua' => 'Rua da Bahia', 'numero' => '1200', 'bairro' => 'Centro', 'cidade' => 'Belo Horizonte', 'estado' => 'MG']],
        ['nome' => 'Bruno Carvalho', 'email' => 'bruno.carvalho@demo.test', 'cpf' => '88899900011', 'telefone' => '(41) 98888-9999', 'endereco' => ['rua' => 'Rua Marechal Deodoro', 'numero' => '600', 'bairro' => 'Centro', 'cidade' => 'Curitiba', 'estado' => 'PR']],
        ['nome' => 'Camila Rodrigues', 'email' => 'camila.rodrigues@demo.test', 'cpf' => '12121212121', 'telefone' => '(51) 97777-0000', 'endereco' => ['rua' => 'Av. Independência', 'numero' => '300', 'bairro' => 'Centro', 'cidade' => 'Porto Alegre', 'estado' => 'RS']],
        ['nome' => 'Diego Barbosa', 'email' => 'diego.barbosa@demo.test', 'cpf' => '34343434343', 'telefone' => '(11) 96666-1111', 'endereco' => ['rua' => 'Rua Oscar Freire', 'numero' => '900', 'bairro' => 'Pinheiros', 'cidade' => 'São Paulo', 'estado' => 'SP']],
        ['nome' => 'Elaine Gomes', 'email' => 'elaine.gomes@demo.test', 'cpf' => '56565656565', 'telefone' => '(21) 95555-2222', 'endereco' => ['rua' => 'Rua do Catete', 'numero' => '200', 'bairro' => 'Catete', 'cidade' => 'Rio de Janeiro', 'estado' => 'RJ']],
        ['nome' => 'Fábio Henrique', 'email' => 'fabio.henrique@demo.test', 'cpf' => '78787878787', 'telefone' => '(31) 94444-3333', 'endereco' => ['rua' => 'Av. Getúlio Vargas', 'numero' => '500', 'bairro' => 'Funcionários', 'cidade' => 'Belo Horizonte', 'estado' => 'MG']],
        ['nome' => 'Gabriela Lopes', 'email' => 'gabriela.lopes@demo.test', 'cpf' => '90909090909', 'telefone' => '(41) 93333-4444', 'endereco' => ['rua' => 'Rua Voluntários da Pátria', 'numero' => '700', 'bairro' => 'Batel', 'cidade' => 'Curitiba', 'estado' => 'PR']],
        ['nome' => 'Henrique Mendes', 'email' => 'henrique.mendes@demo.test', 'cpf' => '10101010101', 'telefone' => '(51) 92222-5555', 'endereco' => ['rua' => 'Rua dos Estados', 'numero' => '250', 'bairro' => 'Centro', 'cidade' => 'Porto Alegre', 'estado' => 'RS']],
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
        $total = 0;

        // Definições: [índice_cliente, valor, dias_vencimento (+futuro/-passado), status, dias_data_pagamento (só para paga, null senão)]
        $defs = [
            // --- PENDENTES (vencimento futuro: 7 a 90 dias) ---
            [0, 1500.00, 30, PromissoriaStatus::PENDENTE, null],
            [1, 3200.50, 15, PromissoriaStatus::PENDENTE, null],
            [2, 800.00, 45, PromissoriaStatus::PENDENTE, null],
            [3, 2100.00, 60, PromissoriaStatus::PENDENTE, null],
            [4, 950.00, 90, PromissoriaStatus::PENDENTE, null],
            [5, 1200.00, 7, PromissoriaStatus::PENDENTE, null],
            [6, 2800.00, 21, PromissoriaStatus::PENDENTE, null],
            [7, 450.00, 14, PromissoriaStatus::PENDENTE, null],
            [8, 1750.00, 45, PromissoriaStatus::PENDENTE, null],
            [9, 3100.00, 30, PromissoriaStatus::PENDENTE, null],
            [10, 600.00, 60, PromissoriaStatus::PENDENTE, null],
            [11, 1900.00, 21, PromissoriaStatus::PENDENTE, null],
            [12, 2200.00, 14, PromissoriaStatus::PENDENTE, null],
            [13, 750.00, 7, PromissoriaStatus::PENDENTE, null],
            [14, 4100.00, 90, PromissoriaStatus::PENDENTE, null],
            [15, 1350.00, 30, PromissoriaStatus::PENDENTE, null],
            [16, 2680.00, 45, PromissoriaStatus::PENDENTE, null],
            [17, 520.00, 21, PromissoriaStatus::PENDENTE, null],
            [18, 3800.00, 60, PromissoriaStatus::PENDENTE, null],
            [19, 890.00, 14, PromissoriaStatus::PENDENTE, null],
            // --- PRÓXIMAS DO VENCIMENTO (1, 2 ou 3 dias) ---
            [0, 800.00, 2, PromissoriaStatus::PENDENTE, null],
            [1, 1100.00, 3, PromissoriaStatus::PENDENTE, null],
            [2, 450.00, 1, PromissoriaStatus::PENDENTE, null],
            [3, 1650.00, 2, PromissoriaStatus::PENDENTE, null],
            [4, 720.00, 3, PromissoriaStatus::PENDENTE, null],
            [5, 2300.00, 1, PromissoriaStatus::PENDENTE, null],
            [6, 980.00, 2, PromissoriaStatus::PENDENTE, null],
            [7, 540.00, 3, PromissoriaStatus::PENDENTE, null],
            [8, 1890.00, 1, PromissoriaStatus::PENDENTE, null],
            [9, 620.00, 2, PromissoriaStatus::PENDENTE, null],
            [10, 1450.00, 3, PromissoriaStatus::PENDENTE, null],
            [11, 2100.00, 1, PromissoriaStatus::PENDENTE, null],
            [12, 380.00, 2, PromissoriaStatus::PENDENTE, null],
            [13, 2900.00, 3, PromissoriaStatus::PENDENTE, null],
            [14, 870.00, 1, PromissoriaStatus::PENDENTE, null],
            // --- VENCIDAS (1 a 60 dias atrás) ---
            [0, 950.00, -5, PromissoriaStatus::VENCIDA, null],
            [1, 1700.00, -15, PromissoriaStatus::VENCIDA, null],
            [2, 500.00, -1, PromissoriaStatus::VENCIDA, null],
            [3, 3200.00, -10, PromissoriaStatus::VENCIDA, null],
            [4, 1180.00, -3, PromissoriaStatus::VENCIDA, null],
            [5, 2400.00, -20, PromissoriaStatus::VENCIDA, null],
            [6, 660.00, -7, PromissoriaStatus::VENCIDA, null],
            [7, 1950.00, -30, PromissoriaStatus::VENCIDA, null],
            [8, 420.00, -2, PromissoriaStatus::VENCIDA, null],
            [9, 2780.00, -45, PromissoriaStatus::VENCIDA, null],
            [10, 830.00, -12, PromissoriaStatus::VENCIDA, null],
            [11, 1500.00, -8, PromissoriaStatus::VENCIDA, null],
            [12, 3900.00, -60, PromissoriaStatus::VENCIDA, null],
            [13, 570.00, -4, PromissoriaStatus::VENCIDA, null],
            [14, 2100.00, -25, PromissoriaStatus::VENCIDA, null],
            [15, 720.00, -6, PromissoriaStatus::VENCIDA, null],
            [16, 1850.00, -18, PromissoriaStatus::VENCIDA, null],
            [17, 1340.00, -9, PromissoriaStatus::VENCIDA, null],
            [18, 620.00, -14, PromissoriaStatus::VENCIDA, null],
            [19, 3400.00, -40, PromissoriaStatus::VENCIDA, null],
            // --- PAGAS (várias datas no passado) ---
            [0, 500.00, -10, PromissoriaStatus::PAGA, -8],
            [1, 1200.00, -3, PromissoriaStatus::PAGA, -1],
            [2, 2100.00, -25, PromissoriaStatus::PAGA, -24],
            [3, 750.00, -5, PromissoriaStatus::PAGA, -4],
            [4, 1600.00, -30, PromissoriaStatus::PAGA, -28],
            [5, 380.00, -2, PromissoriaStatus::PAGA, -1],
            [6, 2900.00, -15, PromissoriaStatus::PAGA, -12],
            [7, 920.00, -7, PromissoriaStatus::PAGA, -5],
            [8, 1450.00, -20, PromissoriaStatus::PAGA, -18],
            [9, 550.00, -4, PromissoriaStatus::PAGA, -2],
            [10, 2200.00, -45, PromissoriaStatus::PAGA, -43],
            [11, 680.00, -8, PromissoriaStatus::PAGA, -6],
            [12, 3100.00, -60, PromissoriaStatus::PAGA, -58],
            [13, 410.00, -1, PromissoriaStatus::PAGA, 0],
            [14, 1780.00, -12, PromissoriaStatus::PAGA, -10],
            [15, 2340.00, -35, PromissoriaStatus::PAGA, -33],
            [16, 890.00, -6, PromissoriaStatus::PAGA, -4],
            [17, 1520.00, -22, PromissoriaStatus::PAGA, -20],
            [18, 670.00, -9, PromissoriaStatus::PAGA, -7],
            [19, 1980.00, -50, PromissoriaStatus::PAGA, -48],
            // --- CANCELADAS ---
            [0, 3000.00, 20, PromissoriaStatus::CANCELADA, null],
            [2, 750.00, 7, PromissoriaStatus::CANCELADA, null],
            [5, 1200.00, 14, PromissoriaStatus::CANCELADA, null],
            [8, 450.00, 5, PromissoriaStatus::CANCELADA, null],
            [12, 2800.00, 30, PromissoriaStatus::CANCELADA, null],
        ];

        foreach ($defs as $d) {
            $clienteIdx = $d[0] % $clientes->count();
            $valor = $d[1];
            $dias = $d[2];
            $status = $d[3];
            $diasPagamento = $d[4];

            $dataVenc = $dias >= 0
                ? $now->copy()->addDays($dias)->format('Y-m-d')
                : $now->copy()->subDays(abs($dias))->format('Y-m-d');

            $dataPagamento = null;
            if ($diasPagamento !== null) {
                $dataPagamento = $now->copy()->addDays($diasPagamento);
            }

            Promissoria::create([
                'cliente_id' => $clientes[$clienteIdx]->id,
                'valor' => $valor,
                'data_vencimento' => $dataVenc,
                'status' => $status->value,
                'observacoes' => null,
                'notificado' => $status === PromissoriaStatus::VENCIDA && (abs($dias) % 2 === 0),
                'data_pagamento' => $dataPagamento,
            ]);
            $total++;
        }

        // --- COM PAGAMENTO PARCIAL (pendentes com saldo) ---
        $parciais = [
            [4, 600.00, 1000.00, 14, [200, 200]],
            [7, 850.00, 1500.00, 21, [350, 300]],
            [11, 1200.00, 2000.00, 7, [400, 400]],
            [15, 450.00, 900.00, 30, [450]],
        ];
        foreach ($parciais as $p) {
            $clienteIdx = $p[0] % $clientes->count();
            $valorRestante = $p[1];
            $valorOriginal = $p[2];
            $diasVenc = $p[3];
            $pagamentos = $p[4];

            $prom = Promissoria::create([
                'cliente_id' => $clientes[$clienteIdx]->id,
                'valor' => $valorRestante,
                'valor_original' => $valorOriginal,
                'data_vencimento' => $now->copy()->addDays($diasVenc)->format('Y-m-d'),
                'status' => PromissoriaStatus::PENDENTE->value,
                'observacoes' => 'Pagamento parcial - saldo restante',
                'notificado' => false,
                'data_pagamento' => null,
            ]);
            $total++;
            $diaBase = 10;
            foreach ($pagamentos as $i => $val) {
                HistoricoPagamento::create([
                    'promissoria_id' => $prom->id,
                    'valor_pago' => $val,
                    'data_pagamento' => $now->copy()->subDays($diaBase - $i * 3)->format('Y-m-d'),
                    'observacoes' => 'Parcela ' . ($i + 1),
                ]);
            }
        }

        $this->command->info('Promissórias criadas: ' . $total . ' (pendentes, próximas vencimento, vencidas, pagas, canceladas, com pagamento parcial).');
    }
}
