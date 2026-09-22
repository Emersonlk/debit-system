<?php

namespace Tests\Feature;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Notifications\PromissoriaVencida;
use App\Notifications\PromissoriaVencimentoProximo;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Services\Contracts\NotificacaoServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Processamento de notificações em lotes.
 *
 * O serviço carregava todas as vencidas de uma vez: 124 MB com 30 mil registros,
 * contra um memory_limit de 128 MB, e acima disso o processo morria sem mensagem.
 * O percurso passou a ser por lotes via chunkById — e não chunk por OFFSET, porque
 * o laço marca `notificado` nas próprias linhas que percorre e o OFFSET pularia
 * registros à medida que o conjunto encolhe.
 *
 * Esta fase é só de escala. A semântica de produto continua idêntica: vencidas
 * seguem sendo notificadas a cada execução e não são marcadas como notificadas;
 * próximas notificam uma vez; --forcar renotifica. O volume N×M permanece como
 * decisão de produto pendente.
 */
class NotificacaoEmLotesTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresa;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Company::factory()->create(['name' => 'Empresa A']);
        app(CurrentCompany::class)->set($this->empresa->id);
        $this->cliente = Cliente::factory()->create();
        User::factory()->create(['company_id' => $this->empresa->id]);
    }

    private function servico(): NotificacaoServiceInterface
    {
        return app(NotificacaoServiceInterface::class);
    }

    private function repositorio(): PromissoriaRepositoryInterface
    {
        return app(PromissoriaRepositoryInterface::class);
    }

    private function criarVencidas(int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            Promissoria::factory()->create([
                'cliente_id' => $this->cliente->id,
                'data_vencimento' => now()->subDays($i + 1)->format('Y-m-d'),
                'status' => PromissoriaStatus::VENCIDA->value,
                'notificado' => false,
            ]);
        }
    }

    private function criarProximas(int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            Promissoria::factory()->create([
                'cliente_id' => $this->cliente->id,
                'data_vencimento' => now()->addDays(2)->format('Y-m-d'),
                'status' => PromissoriaStatus::PENDENTE->value,
                'notificado' => false,
            ]);
        }
    }

    // ------------------------------------------------ percurso em lotes

    public function test_conjunto_vazio_nao_percorre_nada(): void
    {
        $lotes = 0;
        $this->repositorio()->chunkVencidas(2, function () use (&$lotes) {
            $lotes++;
        });

        $this->assertSame(0, $lotes);
    }

    public function test_conjunto_menor_que_um_lote_vem_em_um_unico_lote(): void
    {
        $this->criarVencidas(3);

        $tamanhos = [];
        $this->repositorio()->chunkVencidas(10, function (Collection $lote) use (&$tamanhos) {
            $tamanhos[] = $lote->count();
        });

        $this->assertSame([3], $tamanhos);
    }

    public function test_conjunto_maior_que_um_lote_vem_em_varios_lotes(): void
    {
        $this->criarVencidas(7);

        $tamanhos = [];
        $this->repositorio()->chunkVencidas(3, function (Collection $lote) use (&$tamanhos) {
            $tamanhos[] = $lote->count();
        });

        $this->assertSame([3, 3, 1], $tamanhos, 'Os lotes não respeitaram o tamanho pedido.');
    }

    public function test_nenhum_registro_e_pulado_nem_repetido_entre_lotes(): void
    {
        $this->criarVencidas(25);
        $esperados = Promissoria::query()->pluck('id')->sort()->values()->all();

        $vistos = [];
        $this->repositorio()->chunkVencidas(4, function (Collection $lote) use (&$vistos) {
            foreach ($lote as $promissoria) {
                $vistos[] = $promissoria->id;
            }
        });

        sort($vistos);

        $this->assertSame($esperados, $vistos, 'Algum registro foi pulado ou repetido.');
        $this->assertSame(count($vistos), count(array_unique($vistos)), 'Houve registro repetido entre lotes.');
    }

    public function test_nenhum_registro_e_pulado_quando_o_proprio_lote_altera_o_conjunto(): void
    {
        // Este é o caso que o chunk por OFFSET quebraria: marcar `notificado` remove
        // a linha do conjunto filtrado e desloca todas as seguintes.
        $this->criarProximas(20);
        $esperados = Promissoria::query()->pluck('id')->sort()->values()->all();

        $vistos = [];
        $this->repositorio()->chunkNaoNotificadasProximasVencimento(3, 4, function (Collection $lote) use (&$vistos) {
            foreach ($lote as $promissoria) {
                $vistos[] = $promissoria->id;
            }
            $this->repositorio()->marcarComoNotificadas($lote->pluck('id')->all());
        });

        sort($vistos);

        $this->assertSame($esperados, $vistos, 'Registros foram pulados enquanto o conjunto era alterado.');
        $this->assertSame(20, Promissoria::query()->where('notificado', true)->count());
    }

    public function test_percurso_em_lotes_respeita_a_empresa(): void
    {
        $this->criarVencidas(4);

        $outra = Company::factory()->create(['name' => 'Empresa B']);
        app(CurrentCompany::class)->runAs($outra, function () {
            $cliente = Cliente::factory()->create();
            Promissoria::factory()->count(5)->create([
                'cliente_id' => $cliente->id,
                'data_vencimento' => now()->subDays(3)->format('Y-m-d'),
                'status' => PromissoriaStatus::VENCIDA->value,
            ]);
        });

        $vistos = [];
        $this->repositorio()->chunkVencidas(2, function (Collection $lote) use (&$vistos) {
            foreach ($lote as $promissoria) {
                $vistos[] = $promissoria->company_id;
            }
        });

        $this->assertCount(4, $vistos, 'O percurso alcançou promissórias de outra empresa.');
        $this->assertSame([$this->empresa->id], array_values(array_unique($vistos)));
    }

    // --------------------------------------- contagens sem carregar tudo

    public function test_caminho_sem_registros_usa_contagem_no_banco(): void
    {
        $this->criarProximas(5);
        // Tudo já notificado: é o caminho que antes carregava a coleção duas vezes,
        // uma delas apenas para chamar count().
        Promissoria::query()->update(['notificado' => true]);

        $consultas = [];
        DB::listen(function ($query) use (&$consultas) {
            $consultas[] = strtolower($query->sql);
        });

        $resultado = $this->servico()->notificarPromissoriasProximasVencimento(3);

        $selectsDeColunas = array_filter(
            $consultas,
            fn (string $sql) => str_contains($sql, 'select * from `promissorias`')
        );

        $this->assertNotEmpty(
            array_filter($consultas, fn (string $sql) => str_contains($sql, 'select count(*)')),
            'A verificação deveria ser feita com count() no banco.'
        );
        $this->assertEmpty($selectsDeColunas, 'Nenhuma promissória deveria ser carregada neste caminho.');

        // Mensagem e retorno preservados.
        $this->assertSame(0, $resultado['notificadas']);
        $this->assertSame(5, $resultado['total']);
        $this->assertSame(
            'Existem 5 promissória(s) próxima(s) do vencimento (todas já notificadas anteriormente).',
            $resultado['mensagem']
        );
    }

    public function test_mensagem_de_conjunto_totalmente_vazio_preservada(): void
    {
        $resultado = $this->servico()->notificarPromissoriasProximasVencimento(3);

        $this->assertSame('Nenhuma promissória próxima do vencimento no período.', $resultado['mensagem']);
        $this->assertSame(0, $resultado['total']);

        $vencidas = $this->servico()->notificarPromissoriasVencidas();
        $this->assertSame('Nenhuma promissória vencida encontrada para notificar.', $vencidas['mensagem']);
    }

    // ------------------------------------- semântica das notificações

    public function test_todas_as_proximas_sao_notificadas_e_marcadas(): void
    {
        Notification::fake();
        $this->criarProximas(6);

        $resultado = $this->servico()->notificarPromissoriasProximasVencimento(3);

        $this->assertSame(6, $resultado['notificadas']);
        $this->assertSame(6, $resultado['total']);
        $this->assertSame('6 promissória(s) notificada(s).', $resultado['mensagem']);
        $this->assertSame(6, Promissoria::query()->where('notificado', true)->count());
        Notification::assertSentTimes(PromissoriaVencimentoProximo::class, 6);
    }

    public function test_segunda_execucao_nao_renotifica_proximas(): void
    {
        Notification::fake();
        $this->criarProximas(4);

        $this->servico()->notificarPromissoriasProximasVencimento(3);
        $segunda = $this->servico()->notificarPromissoriasProximasVencimento(3);

        $this->assertSame(0, $segunda['notificadas']);
        Notification::assertSentTimes(PromissoriaVencimentoProximo::class, 4);
    }

    public function test_forcar_renotifica_sem_alterar_a_regra(): void
    {
        Notification::fake();
        $this->criarProximas(4);

        $this->servico()->notificarPromissoriasProximasVencimento(3);
        $forcado = $this->servico()->notificarPromissoriasProximasVencimento(3, true);

        $this->assertSame(4, $forcado['notificadas']);
        Notification::assertSentTimes(PromissoriaVencimentoProximo::class, 8);
        $this->assertSame(4, Promissoria::query()->where('notificado', true)->count());
    }

    public function test_vencidas_continuam_sendo_notificadas_e_nao_sao_marcadas(): void
    {
        Notification::fake();
        $this->criarVencidas(5);

        $primeira = $this->servico()->notificarPromissoriasVencidas();
        $segunda = $this->servico()->notificarPromissoriasVencidas();

        $this->assertSame(5, $primeira['notificadas']);
        $this->assertSame(5, $segunda['notificadas'], 'Vencidas devem continuar sendo notificadas a cada execução.');
        $this->assertSame(
            0,
            Promissoria::query()->where('notificado', true)->count(),
            'Vencidas não podem passar a ser marcadas como notificadas.'
        );
        Notification::assertSentTimes(PromissoriaVencida::class, 10);
    }

    public function test_cada_promissoria_gera_uma_notificacao_por_usuario_sem_duplicar(): void
    {
        Notification::fake();
        User::factory()->create(['company_id' => $this->empresa->id]); // 2 usuários no total
        $this->criarVencidas(3);

        $resultado = $this->servico()->notificarPromissoriasVencidas();

        $this->assertSame(3, $resultado['notificadas'], 'O contador conta promissórias, não notificações.');
        // 3 promissórias x 2 usuários, sem repetição introduzida pelos lotes.
        Notification::assertSentTimes(PromissoriaVencida::class, 6);
    }

    // ------------------------------------------- múltiplos lotes reais

    public function test_volume_acima_do_tamanho_do_lote_e_processado_por_inteiro(): void
    {
        Notification::fake();

        // 600 registros atravessam mais de um lote de 500 no serviço, exercitando o
        // percurso real em vez do repositório isolado.
        $agora = now();
        $linhas = [];
        for ($i = 0; $i < 600; $i++) {
            $linhas[] = [
                'company_id' => $this->empresa->id,
                'cliente_id' => $this->cliente->id,
                'valor' => 100.00,
                'data_vencimento' => $agora->copy()->addDays(2)->format('Y-m-d'),
                'status' => PromissoriaStatus::PENDENTE->value,
                'notificado' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }
        DB::table('promissorias')->insert($linhas);

        $resultado = $this->servico()->notificarPromissoriasProximasVencimento(3);

        $this->assertSame(600, $resultado['notificadas'], 'Algum registro ficou de fora entre os lotes.');
        $this->assertSame(600, $resultado['total']);
        $this->assertSame(600, Promissoria::query()->where('notificado', true)->count());
        Notification::assertSentTimes(PromissoriaVencimentoProximo::class, 600);
    }

    public function test_marcacao_agrupada_usa_um_update_por_lote(): void
    {
        Notification::fake();
        $this->criarProximas(9);

        $updates = 0;
        DB::listen(function ($query) use (&$updates) {
            if (str_starts_with(strtolower($query->sql), 'update `promissorias`')) {
                $updates++;
            }
        });

        $this->servico()->notificarPromissoriasProximasVencimento(3);

        // Antes era um UPDATE por promissória (9); agora é um por lote — e 9
        // registros cabem em um único lote de 500.
        $this->assertSame(1, $updates, 'A marcação de notificado deveria ser agrupada por lote.');
        $this->assertSame(9, Promissoria::query()->where('notificado', true)->count());
    }
}
