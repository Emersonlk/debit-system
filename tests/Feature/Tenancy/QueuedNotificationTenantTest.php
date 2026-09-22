<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PromissoriaStatus;
use App\Exceptions\TenantContextMissingException;
use App\Exceptions\TenantNotFoundException;
use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Notifications\PromissoriaVencida;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Atravessa a fila de verdade — sem Notification::fake().
 *
 * Os testes anteriores usavam fake e por isso não detectaram que as notificações
 * enfileiradas perdiam o contexto de empresa no worker. Aqui a notificação é
 * realmente serializada, gravada na tabela `jobs` e processada por `queue:work`.
 *
 * O driver de fila nos testes é `database` (o `sync` declarado no phpunit.xml é
 * inerte no Docker, porque $_SERVER traz QUEUE_CONNECTION do container).
 */
class QueuedNotificationTenantTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int|null> empresa observada durante o envio de cada notificação */
    public static array $contextoDurante = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$contextoDurante = [];

        // toMail() roda de verdade (é o que toca os models tenant-aware);
        // apenas o transporte SMTP é substituído.
        Mail::fake();

        Event::listen(NotificationSent::class, function () {
            $contexto = app(CurrentCompany::class);
            self::$contextoDurante[] = $contexto->has() ? $contexto->id() : null;
        });
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    /**
     * Cria empresa + usuário + promissória vencida e enfileira a notificação,
     * exatamente como o NotificacaoService faz.
     */
    private function enfileirarNotificacao(Company $company): void
    {
        $this->comoEmpresa($company, function () {
            $usuario = User::factory()->create(['company_id' => app(CurrentCompany::class)->id()]);

            // with('cliente') reproduz o repositório real: a relação carregada é
            // justamente o que o unserialize tenta recarregar no worker.
            $promissoria = Promissoria::with('cliente')->find(
                Promissoria::factory()->create([
                    'status' => PromissoriaStatus::PENDENTE->value,
                    'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
                ])->id
            );

            $usuario->notify(new PromissoriaVencida($promissoria));
        });
    }

    private function jobsPendentes(): int
    {
        return DB::table('jobs')->count();
    }

    private function jobsFalhados(): int
    {
        return DB::table('failed_jobs')->count();
    }

    private function processarFila(): void
    {
        $this->artisan('queue:work', ['--once' => true, '--no-interaction' => true]);
    }

    // ------------------------------------------------- Teste A: processamento real

    public function test_notificacao_enfileirada_e_processada_sem_erro_de_contexto(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->enfileirarNotificacao($empresaA);

        $this->assertSame(1, $this->jobsPendentes(), 'A notificação deve ter sido enfileirada.');

        $this->processarFila();

        $this->assertSame(0, $this->jobsPendentes(), 'O job deve ter sido consumido.');
        $this->assertSame(0, $this->jobsFalhados(), 'Nenhum job pode falhar por falta de contexto.');
        $this->assertCount(1, self::$contextoDurante);
    }

    public function test_payload_do_job_carrega_a_empresa(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->enfileirarNotificacao($empresaA);

        $payload = json_decode(DB::table('jobs')->first()->payload, true);

        $this->assertSame($empresaA->id, $payload['company_id'] ?? null);
    }

    // --------------------------------------------------- Teste B: tenant correto

    public function test_worker_processa_no_contexto_da_empresa_da_notificacao(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->enfileirarNotificacao($empresaA);

        $this->processarFila();

        $this->assertSame(
            [$empresaA->id],
            self::$contextoDurante,
            'O contexto durante o processamento deve ser o da empresa da notificação.'
        );
    }

    // ------------------------------- Teste C: dois jobs de empresas diferentes

    public function test_jobs_de_empresas_diferentes_nao_vazam_contexto_entre_si(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->enfileirarNotificacao($empresaA);
        $this->enfileirarNotificacao($empresaB);

        $this->assertSame(2, $this->jobsPendentes());

        // Ambos processados no mesmo processo, em sequência.
        $this->processarFila();
        $this->processarFila();

        $this->assertSame(0, $this->jobsFalhados());
        $this->assertSame(
            [$empresaA->id, $empresaB->id],
            self::$contextoDurante,
            'O segundo job não pode herdar o contexto do primeiro.'
        );
    }

    // ---------------------------------------------- Teste D: empresa inexistente

    public function test_job_de_empresa_inexistente_falha_explicitamente(): void
    {
        $empresa = Company::factory()->create(['name' => 'Empresa Temporária']);
        $this->enfileirarNotificacao($empresa);

        // A empresa some depois do enfileiramento.
        Company::whereKey($empresa->id)->delete();

        $this->processarFila();

        $this->assertSame(1, $this->jobsFalhados(), 'O job deve falhar, não ser processado.');
        $this->assertStringContainsString(
            TenantNotFoundException::class,
            DB::table('failed_jobs')->first()->exception
        );
        $this->assertSame([], self::$contextoDurante, 'Nada pode ter sido enviado.');
    }

    // ------------------------------------------------ Teste E: ausência de tenant

    public function test_job_sem_tenant_no_payload_falha_em_vez_de_rodar_global(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);

        [$usuario, $promissoria] = $this->comoEmpresa($empresaA, fn () => [
            User::factory()->create(['company_id' => $empresaA->id]),
            Promissoria::with('cliente')->find(
                Promissoria::factory()->create([
                    'status' => PromissoriaStatus::PENDENTE->value,
                    'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
                ])->id
            ),
        ]);

        // Enfileirado FORA de qualquer contexto: o payload não leva company_id.
        $usuario->notify(new PromissoriaVencida($promissoria));

        $payload = json_decode(DB::table('jobs')->first()->payload, true);
        $this->assertArrayNotHasKey('company_id', $payload);

        $this->processarFila();

        $this->assertSame(1, $this->jobsFalhados());
        $this->assertStringContainsString(
            TenantContextMissingException::class,
            DB::table('failed_jobs')->first()->exception
        );
        $this->assertSame([], self::$contextoDurante);
    }

    // -------------------------------------------- Teste F: contexto anterior

    public function test_contexto_anterior_e_restaurado_apos_o_job(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->enfileirarNotificacao($empresaB);

        $contexto = app(CurrentCompany::class);

        $this->comoEmpresa($empresaA, function () use ($contexto, $empresaA, $empresaB) {
            $this->assertSame($empresaA->id, $contexto->id());

            $this->processarFila();

            $this->assertSame([$empresaB->id], self::$contextoDurante);
            $this->assertSame(
                $empresaA->id,
                $contexto->id(),
                'O contexto externo deve voltar ao que era antes do job.'
            );
        });
    }

    public function test_worker_nao_deixa_contexto_residual_apos_processar(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->enfileirarNotificacao($empresaA);

        $contexto = app(CurrentCompany::class);
        $this->assertFalse($contexto->has());

        $this->processarFila();

        $this->assertFalse(
            $contexto->has(),
            'Depois do job, o worker não pode ficar com contexto de empresa pendurado.'
        );
    }

    // --------------------------------------------------------- fluxo completo

    public function test_comando_agendado_entrega_notificacoes_pela_fila(): void
    {
        $empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->comoEmpresa($empresaA, function () use ($empresaA) {
            User::factory()->create(['company_id' => $empresaA->id]);
            Promissoria::factory()->create([
                'status' => PromissoriaStatus::PENDENTE->value,
                'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
            ]);
        });
        $this->comoEmpresa($empresaB, function () use ($empresaB) {
            User::factory()->create(['company_id' => $empresaB->id]);
            Promissoria::factory()->create([
                'status' => PromissoriaStatus::PENDENTE->value,
                'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
            ]);
        });

        $this->artisan('promissorias:verificar-vencimento')->assertExitCode(0);

        $this->assertSame(2, $this->jobsPendentes(), 'Uma notificação enfileirada por empresa.');

        $this->processarFila();
        $this->processarFila();

        $this->assertSame(0, $this->jobsFalhados());
        $this->assertSame(0, $this->jobsPendentes());
        $this->assertSame([$empresaA->id, $empresaB->id], self::$contextoDurante);
    }
}
