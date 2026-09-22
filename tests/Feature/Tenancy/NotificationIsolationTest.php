<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PromissoriaStatus;
use App\Exceptions\TenantContextMissingException;
use App\Models\Company;
use App\Models\Promissoria;
use App\Models\User;
use App\Notifications\PromissoriaVencida;
use App\Notifications\PromissoriaVencimentoProximo;
use App\Services\Contracts\NotificacaoServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * As notificações são enviadas por e-mail (via() => ['mail']); não existe tabela
 * `notifications`. Por isso o isolamento é verificado pelo destinatário de cada
 * notificação e pela promissória que ela carrega.
 */
class NotificationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $empresaA;

    private Company $empresaB;

    private User $adminA;

    private User $operadorA;

    private User $adminB;

    private User $operadorB;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'operador']);

        $this->empresaA = Company::factory()->create(['name' => 'Empresa A']);
        $this->empresaB = Company::factory()->create(['name' => 'Empresa B']);

        $this->adminA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->adminA->assignRole('admin');
        $this->operadorA = User::factory()->create(['company_id' => $this->empresaA->id]);
        $this->operadorA->assignRole('operador');

        $this->adminB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->adminB->assignRole('admin');
        $this->operadorB = User::factory()->create(['company_id' => $this->empresaB->id]);
        $this->operadorB->assignRole('operador');
    }

    private function comoEmpresa(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    private function criarProximaVencimento(Company $company, int $dias = 2): Promissoria
    {
        return $this->comoEmpresa($company, fn () => Promissoria::factory()->create([
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_vencimento' => now()->addDays($dias)->format('Y-m-d'),
            'notificado' => false,
        ]));
    }

    private function criarVencida(Company $company): Promissoria
    {
        return $this->comoEmpresa($company, fn () => Promissoria::factory()->create([
            'status' => PromissoriaStatus::PENDENTE->value,
            'data_vencimento' => now()->subDays(5)->format('Y-m-d'),
        ]));
    }

    private function servico(): NotificacaoServiceInterface
    {
        return app(NotificacaoServiceInterface::class);
    }

    // ------------------------------------------ Cenário 1: notificações isoladas

    public function test_notificacao_de_a_chega_apenas_aos_usuarios_de_a(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        $this->comoEmpresa(
            $this->empresaA,
            fn () => $this->servico()->notificarPromissoriasProximasVencimento(3)
        );

        Notification::assertSentTo($this->adminA, PromissoriaVencimentoProximo::class);
        Notification::assertSentTo($this->operadorA, PromissoriaVencimentoProximo::class);
        Notification::assertNotSentTo($this->adminB, PromissoriaVencimentoProximo::class);
        Notification::assertNotSentTo($this->operadorB, PromissoriaVencimentoProximo::class);
    }

    public function test_notificacao_de_b_chega_apenas_aos_usuarios_de_b(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaB);

        $this->comoEmpresa(
            $this->empresaB,
            fn () => $this->servico()->notificarPromissoriasProximasVencimento(3)
        );

        Notification::assertSentTo($this->adminB, PromissoriaVencimentoProximo::class);
        Notification::assertSentTo($this->operadorB, PromissoriaVencimentoProximo::class);
        Notification::assertNotSentTo($this->adminA, PromissoriaVencimentoProximo::class);
        Notification::assertNotSentTo($this->operadorA, PromissoriaVencimentoProximo::class);
    }

    public function test_notificacao_de_vencida_tambem_respeita_a_empresa(): void
    {
        Notification::fake();
        $this->criarVencida($this->empresaA);

        $this->comoEmpresa($this->empresaA, fn () => $this->servico()->notificarPromissoriasVencidas());

        Notification::assertSentTo($this->adminA, PromissoriaVencida::class);
        Notification::assertSentTo($this->operadorA, PromissoriaVencida::class);
        Notification::assertNotSentTo($this->adminB, PromissoriaVencida::class);
        Notification::assertNotSentTo($this->operadorB, PromissoriaVencida::class);
    }

    // ------------------------------------------- Cenário 2: sem vazamento

    public function test_usuario_de_b_nunca_recebe_promissoria_de_a(): void
    {
        Notification::fake();

        $promissoriaA = $this->criarProximaVencimento($this->empresaA);
        $promissoriaB = $this->criarProximaVencimento($this->empresaB);

        $this->comoEmpresa($this->empresaA, fn () => $this->servico()->notificarPromissoriasProximasVencimento(3));
        $this->comoEmpresa($this->empresaB, fn () => $this->servico()->notificarPromissoriasProximasVencimento(3));

        // Cada usuário só pode ter recebido a promissória da própria empresa.
        Notification::assertSentTo(
            $this->adminA,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaA->id
        );
        Notification::assertSentTo(
            $this->adminB,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaB->id
        );

        // E nunca a da outra.
        Notification::assertNotSentTo(
            $this->adminB,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaA->id
        );
        Notification::assertNotSentTo(
            $this->adminA,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaB->id
        );
    }

    // ------------------------------- Cenário 3: scheduler percorre as empresas

    public function test_comando_processa_todas_as_empresas_sem_usuario_autenticado(): void
    {
        Notification::fake();

        $promissoriaA = $this->criarProximaVencimento($this->empresaA);
        $promissoriaB = $this->criarProximaVencimento($this->empresaB);

        $this->assertGuest();

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])
            ->assertExitCode(0);

        Notification::assertSentTo(
            $this->adminA,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaA->id
        );
        Notification::assertSentTo(
            $this->adminB,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaB->id
        );
        Notification::assertNotSentTo(
            $this->adminA,
            PromissoriaVencimentoProximo::class,
            fn (PromissoriaVencimentoProximo $n) => $n->promissoria->id === $promissoriaB->id
        );
    }

    public function test_totais_do_comando_somam_todas_as_empresas(): void
    {
        Notification::fake();

        $this->criarProximaVencimento($this->empresaA);
        $this->criarProximaVencimento($this->empresaB);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])
            ->expectsOutputToContain('Encontradas 2 promissória(s) próximas do vencimento.')
            ->expectsOutputToContain('2 promissória(s) notificada(s).')
            ->assertExitCode(0);
    }

    // ----------------------------------------- Cenário 4: status de vencimento

    public function test_comando_atualiza_status_sem_uma_empresa_afetar_a_outra(): void
    {
        Notification::fake();

        $vencidaA = $this->criarVencida($this->empresaA);
        $vencidaB = $this->criarVencida($this->empresaB);

        $this->artisan('promissorias:verificar-vencimento')->assertExitCode(0);

        $this->assertSame(
            PromissoriaStatus::VENCIDA,
            $this->comoEmpresa($this->empresaA, fn () => Promissoria::find($vencidaA->id))->status
        );
        $this->assertSame(
            PromissoriaStatus::VENCIDA,
            $this->comoEmpresa($this->empresaB, fn () => Promissoria::find($vencidaB->id))->status
        );
    }

    public function test_execucao_de_uma_empresa_nao_altera_dados_da_outra(): void
    {
        Notification::fake();

        $proximaA = $this->criarProximaVencimento($this->empresaA);
        $proximaB = $this->criarProximaVencimento($this->empresaB);

        // Só a empresa A é processada.
        $this->comoEmpresa($this->empresaA, fn () => $this->servico()->notificarPromissoriasProximasVencimento(3));

        $this->assertTrue(
            (bool) $this->comoEmpresa($this->empresaA, fn () => Promissoria::find($proximaA->id))->notificado
        );
        $this->assertFalse(
            (bool) $this->comoEmpresa($this->empresaB, fn () => Promissoria::find($proximaB->id))->notificado,
            'A promissória da empresa B não pode ser marcada como notificada.'
        );
        Notification::assertNotSentTo($this->adminB, PromissoriaVencimentoProximo::class);
    }

    // -------------------------------------- Cenário 5: não duplicar notificação

    public function test_segunda_execucao_nao_renotifica_proximas_do_vencimento(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])->assertExitCode(0);
        Notification::assertSentToTimes($this->adminA, PromissoriaVencimentoProximo::class, 1);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])
            ->expectsOutputToContain('todas já notificadas anteriormente')
            ->assertExitCode(0);

        Notification::assertSentToTimes($this->adminA, PromissoriaVencimentoProximo::class, 1);
    }

    public function test_forcar_renotifica_preservando_a_regra_atual(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])->assertExitCode(0);
        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3, '--forcar' => true])->assertExitCode(0);

        Notification::assertSentToTimes($this->adminA, PromissoriaVencimentoProximo::class, 2);
    }

    // --------------------------------------------- Cenário 6: empresa sem dados

    public function test_empresa_sem_promissorias_e_processada_sem_erro(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])
            ->assertExitCode(0);

        Notification::assertSentTo($this->adminA, PromissoriaVencimentoProximo::class);
        Notification::assertNothingSentTo($this->adminB);
        Notification::assertNothingSentTo($this->operadorB);
    }

    public function test_empresa_sem_usuarios_nao_impede_as_demais(): void
    {
        Notification::fake();

        $empresaSemUsuarios = Company::factory()->create(['name' => 'Empresa Sem Usuários']);
        $this->criarProximaVencimento($empresaSemUsuarios);
        $this->criarProximaVencimento($this->empresaA);

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])
            ->assertExitCode(0);

        Notification::assertSentTo($this->adminA, PromissoriaVencimentoProximo::class);
    }

    // ------------------------------------------------- Cenário 7: sem empresas

    public function test_comando_termina_limpo_quando_nao_ha_empresas(): void
    {
        Notification::fake();

        User::query()->delete();
        Company::query()->delete();

        $this->artisan('promissorias:verificar-vencimento')
            ->expectsOutputToContain('Nenhuma empresa cadastrada para processar.')
            ->expectsOutputToContain('Processo concluído!')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    // --------------------------------------------- Cenário 8: fail-closed

    public function test_servico_sem_contexto_falha_em_vez_de_processar_tudo(): void
    {
        $this->criarProximaVencimento($this->empresaA);

        $this->expectException(TenantContextMissingException::class);

        $this->servico()->notificarPromissoriasProximasVencimento(3);
    }

    public function test_servico_de_vencidas_sem_contexto_falha(): void
    {
        $this->criarVencida($this->empresaA);

        $this->expectException(TenantContextMissingException::class);

        $this->servico()->notificarPromissoriasVencidas();
    }

    public function test_atualizar_status_sem_contexto_falha(): void
    {
        $this->criarVencida($this->empresaA);

        $this->expectException(TenantContextMissingException::class);

        $this->servico()->atualizarStatusVencidas();
    }

    public function test_servico_sem_contexto_nao_envia_nenhuma_notificacao(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        try {
            $this->servico()->notificarPromissoriasProximasVencimento(3);
            $this->fail('Deveria ter falhado por falta de contexto.');
        } catch (TenantContextMissingException) {
            // esperado
        }

        Notification::assertNothingSent();
    }

    // ------------------------------------- Estado entre empresas / contexto

    public function test_contexto_e_restaurado_apos_a_execucao_do_comando(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);

        $contexto = app(CurrentCompany::class);
        $this->assertFalse($contexto->has(), 'O teste começa sem contexto.');

        $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])->assertExitCode(0);

        $this->assertFalse(
            $contexto->has(),
            'O comando não pode deixar contexto de empresa vazado após terminar.'
        );
    }

    public function test_contexto_anterior_e_preservado_ao_redor_do_comando(): void
    {
        Notification::fake();
        $this->criarProximaVencimento($this->empresaA);
        $this->criarProximaVencimento($this->empresaB);

        $contexto = app(CurrentCompany::class);

        $this->comoEmpresa($this->empresaA, function () use ($contexto) {
            $this->assertSame($this->empresaA->id, $contexto->id());

            $this->artisan('promissorias:verificar-vencimento', ['--dias' => 3])->assertExitCode(0);

            $this->assertSame(
                $this->empresaA->id,
                $contexto->id(),
                'Depois de percorrer as empresas, o contexto externo deve voltar ao que era.'
            );
        });
    }
}
