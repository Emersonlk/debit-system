<?php

namespace Tests\Feature;

use App\DTOs\PagamentoParcialDTO;
use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\Company;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Services\Contracts\PromissoriaServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Integridade financeira dos fluxos de pagamento.
 *
 * Antes desta fase, registrarPagamentoParcial() lia o saldo e só depois gravava,
 * sem transação nem lock. Duas chamadas simultâneas liam o mesmo saldo e ambas
 * gravavam: numa dívida de R$ 100,00 ficavam registrados R$ 200,00 pagos. E como
 * o INSERT do histórico e o UPDATE da promissória eram independentes, uma falha
 * entre os dois deixava o pagamento registrado sem o saldo abatido — estado em que
 * os accessors passam a contradizer o próprio histórico.
 *
 * A concorrência em si foi reproduzida manualmente na auditoria, com dois
 * processos PHP e conexões distintas; aqui ela é coberta pelo efeito observável
 * (rollback e invariante de saldo) e pelo teste estrutural do final, que confirma
 * que o lock existe, está dentro da transação e vem antes das escritas.
 */
class PagamentoIntegridadeTest extends TestCase
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
    }

    private function service(): PromissoriaServiceInterface
    {
        return app(PromissoriaServiceInterface::class);
    }

    private function promissoriaDe(float $valor): Promissoria
    {
        return Promissoria::factory()->create([
            'cliente_id' => $this->cliente->id,
            'valor' => $valor,
            'valor_original' => null,
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_pagamento' => null,
        ]);
    }

    private function pagamentoDe(float $valor): PagamentoParcialDTO
    {
        return new PagamentoParcialDTO(
            valor_pago: $valor,
            data_pagamento: now()->format('Y-m-d'),
            observacoes: null,
        );
    }

    /**
     * Soma real gravada em historico_pagamentos, lida direto do banco para não
     * depender dos accessors que esta fase não toca.
     */
    private function somaDoHistorico(Promissoria $promissoria): float
    {
        return (float) DB::table('historico_pagamentos')
            ->where('promissoria_id', $promissoria->id)
            ->sum('valor_pago');
    }

    // ------------------------------------------- comportamento preservado

    public function test_pagamento_parcial_grava_historico_e_abate_o_saldo(): void
    {
        $promissoria = $this->promissoriaDe(100.00);

        $historico = $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(30.00));

        $promissoria->refresh();

        $this->assertSame('30.00', (string) $historico->valor_pago);
        $this->assertSame('70.00', (string) $promissoria->valor);
        $this->assertSame('100.00', (string) $promissoria->valor_original);
        $this->assertSame(PromissoriaStatus::PENDENTE, $promissoria->status);
        $this->assertSame(1, $promissoria->historicoPagamentos()->count());
    }

    public function test_pagamento_que_quita_marca_como_paga(): void
    {
        $promissoria = $this->promissoriaDe(100.00);

        $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(100.00));

        $promissoria->refresh();

        $this->assertSame(PromissoriaStatus::PAGA, $promissoria->status);
        $this->assertSame('0.00', (string) $promissoria->valor);
        $this->assertNotNull($promissoria->data_pagamento);
    }

    public function test_marcar_como_paga_registra_o_valor_integral(): void
    {
        $promissoria = $this->promissoriaDe(250.00);

        $this->service()->marcarComoPaga($promissoria);

        $promissoria->refresh();

        $this->assertSame(PromissoriaStatus::PAGA, $promissoria->status);
        $this->assertSame(250.00, $this->somaDoHistorico($promissoria));
        $this->assertSame(1, $promissoria->historicoPagamentos()->count());
    }

    public function test_marcar_como_paga_apos_parciais_registra_somente_o_saldo(): void
    {
        $promissoria = $this->promissoriaDe(100.00);
        $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(40.00));

        $this->service()->marcarComoPaga($promissoria->fresh());

        $promissoria->refresh();

        // 40 da parcial + 60 do saldo: o total continua sendo o valor da dívida.
        $this->assertSame(100.00, $this->somaDoHistorico($promissoria));
        $this->assertSame('0.00', (string) $promissoria->valor);
        $this->assertSame(PromissoriaStatus::PAGA, $promissoria->status);
    }

    public function test_mensagens_de_erro_permanecem_iguais(): void
    {
        // O controller decide entre 422 e 500 comparando estas strings.
        $paga = $this->promissoriaDe(50.00);
        $this->service()->marcarComoPaga($paga);

        $this->assertSame(
            'Não é possível registrar pagamento parcial em uma promissória já paga.',
            $this->mensagemDe(fn () => $this->service()->registrarPagamentoParcial($paga->fresh(), $this->pagamentoDe(10.00)))
        );
        $this->assertSame(
            'Esta promissória já está marcada como paga.',
            $this->mensagemDe(fn () => $this->service()->marcarComoPaga($paga->fresh()))
        );
        $this->assertSame(
            'Não é possível cancelar uma promissória já paga.',
            $this->mensagemDe(fn () => $this->service()->cancelar($paga->fresh()))
        );

        $pendente = $this->promissoriaDe(80.00);
        $this->assertStringStartsWith(
            'O valor do pagamento',
            $this->mensagemDe(fn () => $this->service()->registrarPagamentoParcial($pendente, $this->pagamentoDe(80.01)))
        );
    }

    private function mensagemDe(callable $callback): string
    {
        try {
            $callback();
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    // ----------------------------------------------- invariante financeiro

    public function test_soma_do_historico_bate_com_valor_original_menos_valor(): void
    {
        $promissoria = $this->promissoriaDe(300.00);

        foreach ([50.00, 70.00, 30.00] as $parcela) {
            $this->service()->registrarPagamentoParcial($promissoria->fresh(), $this->pagamentoDe($parcela));
        }

        $promissoria->refresh();

        $this->assertNotNull($promissoria->valor_original, 'valor_original precisa estar definido após a primeira parcial.');

        $esperado = (float) $promissoria->valor_original - (float) $promissoria->valor;

        $this->assertSame(150.00, $this->somaDoHistorico($promissoria));
        $this->assertSame($esperado, $this->somaDoHistorico($promissoria), 'A soma do histórico divergiu de valor_original - valor.');
        $this->assertSame('150.00', (string) $promissoria->valor);
    }

    public function test_invariante_se_mantem_quando_as_parciais_quitam_a_divida(): void
    {
        $promissoria = $this->promissoriaDe(200.00);

        $this->service()->registrarPagamentoParcial($promissoria->fresh(), $this->pagamentoDe(120.00));
        $this->service()->registrarPagamentoParcial($promissoria->fresh(), $this->pagamentoDe(80.00));

        $promissoria->refresh();

        $this->assertSame(200.00, $this->somaDoHistorico($promissoria));
        $this->assertSame(
            (float) $promissoria->valor_original - (float) $promissoria->valor,
            $this->somaDoHistorico($promissoria)
        );
        $this->assertSame(PromissoriaStatus::PAGA, $promissoria->status);
    }

    public function test_nenhum_pagamento_excede_o_saldo(): void
    {
        $promissoria = $this->promissoriaDe(100.00);
        $this->service()->registrarPagamentoParcial($promissoria->fresh(), $this->pagamentoDe(60.00));

        $this->expectException(Throwable::class);

        try {
            $this->service()->registrarPagamentoParcial($promissoria->fresh(), $this->pagamentoDe(60.00));
        } finally {
            // Mesmo com a tentativa recusada, o total pago não pode passar da dívida.
            $this->assertSame(60.00, $this->somaDoHistorico($promissoria));
        }
    }

    // -------------------------------------------------- rollback atômico

    public function test_pagamento_parcial_e_revertido_quando_o_update_da_promissoria_falha(): void
    {
        $promissoria = $this->promissoriaDe(100.00);

        // No fluxo real, o INSERT do histórico acontece antes do UPDATE da
        // promissória. Falhar no update reproduz exatamente a segunda escrita
        // quebrando depois de a primeira ter sido gravada.
        Event::listen('eloquent.updating: ' . Promissoria::class, function () {
            throw new RuntimeException('falha simulada no update da promissoria');
        });

        try {
            $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(30.00));
            $this->fail('A falha simulada deveria ter interrompido a operação.');
        } catch (Throwable $e) {
            $this->assertSame('falha simulada no update da promissoria', $e->getMessage());
        }

        $promissoria->refresh();

        $this->assertSame(0, HistoricoPagamento::count(), 'O histórico gravado antes da falha não foi revertido.');
        $this->assertSame('100.00', (string) $promissoria->valor, 'O valor da promissória foi alterado apesar da falha.');
        $this->assertNull($promissoria->valor_original);
        $this->assertSame(PromissoriaStatus::PENDENTE, $promissoria->status);
    }

    public function test_marcar_como_paga_e_revertida_quando_a_criacao_do_historico_falha(): void
    {
        $promissoria = $this->promissoriaDe(150.00);

        // Aqui a ordem é inversa: o status é atualizado antes de o histórico ser
        // criado. Falhar na criação do histórico deixa a primeira escrita pendente.
        Event::listen('eloquent.created: ' . HistoricoPagamento::class, function () {
            throw new RuntimeException('falha simulada na criacao do historico');
        });

        try {
            $this->service()->marcarComoPaga($promissoria);
            $this->fail('A falha simulada deveria ter interrompido a operação.');
        } catch (Throwable $e) {
            $this->assertSame('falha simulada na criacao do historico', $e->getMessage());
        }

        $promissoria->refresh();

        $this->assertSame(PromissoriaStatus::PENDENTE, $promissoria->status, 'O status mudou apesar da falha.');
        $this->assertNull($promissoria->data_pagamento);
        $this->assertSame(0, HistoricoPagamento::count());
        $this->assertSame('150.00', (string) $promissoria->valor);
    }

    public function test_cancelamento_e_revertido_quando_a_alteracao_falha(): void
    {
        $promissoria = $this->promissoriaDe(90.00);

        $chamadas = 0;
        Event::listen('eloquent.updating: ' . Promissoria::class, function () use (&$chamadas) {
            // Deixa passar a gravação das observações e quebra na mudança de status.
            if (++$chamadas > 1) {
                throw new RuntimeException('falha simulada no cancelamento');
            }
        });

        try {
            $this->service()->cancelar($promissoria, 'motivo do cancelamento');
            $this->fail('A falha simulada deveria ter interrompido a operação.');
        } catch (Throwable $e) {
            $this->assertSame('falha simulada no cancelamento', $e->getMessage());
        }

        $promissoria->refresh();

        $this->assertSame(PromissoriaStatus::PENDENTE, $promissoria->status);
        $this->assertNotSame('motivo do cancelamento', $promissoria->observacoes, 'A observação gravada antes da falha não foi revertida.');
    }

    // --------------------------------------------------- tenant isolation

    public function test_promissoria_de_outra_empresa_nao_e_alcancada_pelo_lock(): void
    {
        $outraEmpresa = Company::factory()->create(['name' => 'Empresa B']);

        $promissoriaAlheia = app(CurrentCompany::class)->runAs($outraEmpresa, function () {
            $cliente = Cliente::factory()->create();

            return Promissoria::factory()->create([
                'cliente_id' => $cliente->id,
                'valor' => 100.00,
                'status' => PromissoriaStatus::PENDENTE->value,
            ]);
        });

        // De volta ao contexto da Empresa A, a releitura sob lock não encontra a
        // promissória da Empresa B: o global scope continua valendo dentro da
        // transação, e o resultado é "não encontrada", não um lock cruzado.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service()->registrarPagamentoParcial($promissoriaAlheia, $this->pagamentoDe(10.00));
    }

    public function test_promissoria_removida_antes_do_lock_falha_como_inexistente(): void
    {
        $promissoria = $this->promissoriaDe(100.00);
        DB::table('historico_pagamentos')->where('promissoria_id', $promissoria->id)->delete();
        DB::table('promissorias')->where('id', $promissoria->id)->delete();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(10.00));
    }

    // ------------------------------------------------- efeitos colaterais

    public function test_pagamento_nao_dispara_notificacoes(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $promissoria = $this->promissoriaDe(100.00);
        $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(50.00));
        $this->service()->marcarComoPaga($promissoria->fresh());

        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    // ------------------------------------------ teste estrutural do lock

    public function test_o_lock_ocorre_dentro_da_transacao_e_antes_das_escritas(): void
    {
        $promissoria = $this->promissoriaDe(100.00);

        $consultas = [];
        DB::listen(function ($query) use (&$consultas) {
            $consultas[] = strtolower($query->sql);
        });

        // Se as escritas estivessem fora de uma transação, o nível seria 0.
        $nivelDuranteAEscrita = null;
        Event::listen('eloquent.created: ' . HistoricoPagamento::class, function () use (&$nivelDuranteAEscrita) {
            $nivelDuranteAEscrita = DB::transactionLevel();
        });

        $this->service()->registrarPagamentoParcial($promissoria, $this->pagamentoDe(25.00));

        $posicaoDoLock = $this->primeiraOcorrencia($consultas, 'for update');
        $posicaoDoInsert = $this->primeiraOcorrencia($consultas, 'insert into `historico_pagamentos`');
        $posicaoDoUpdate = $this->primeiraOcorrencia($consultas, 'update `promissorias`');

        $this->assertNotNull($posicaoDoLock, 'Nenhuma consulta com FOR UPDATE foi emitida.');
        $this->assertNotNull($posicaoDoInsert, 'O INSERT do histórico não foi emitido.');
        $this->assertNotNull($posicaoDoUpdate, 'O UPDATE da promissória não foi emitido.');

        $this->assertLessThan($posicaoDoInsert, $posicaoDoLock, 'O lock precisa vir antes do INSERT do histórico.');
        $this->assertLessThan($posicaoDoUpdate, $posicaoDoLock, 'O lock precisa vir antes do UPDATE da promissória.');

        $this->assertNotNull($nivelDuranteAEscrita, 'A escrita do histórico não foi observada.');
        $this->assertGreaterThan(0, $nivelDuranteAEscrita, 'As escritas precisam ocorrer dentro de uma transação.');
    }

    /**
     * @param  list<string>  $consultas
     */
    private function primeiraOcorrencia(array $consultas, string $trecho): ?int
    {
        foreach ($consultas as $posicao => $sql) {
            if (str_contains($sql, $trecho)) {
                return $posicao;
            }
        }

        return null;
    }
}
