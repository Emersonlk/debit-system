<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Contracts\NotificacaoServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;

class VerificarPromissoriasVencimento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promissorias:verificar-vencimento
                            {--dias=3 : Número de dias antes do vencimento para notificar}
                            {--forcar : Notificar todas as próximas do vencimento, mesmo já notificadas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica promissórias próximas do vencimento e envia notificações por email';

    public function __construct(
        private NotificacaoServiceInterface $notificacaoService,
        private CurrentCompany $currentCompany
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Roda sem usuário autenticado, então estabelece explicitamente o contexto de
     * cada empresa antes de acionar a lógica tenant-aware. O serviço de notificação
     * trabalha sempre sobre uma única empresa — a iteração é responsabilidade daqui.
     */
    public function handle(): int
    {
        $diasAntes = (int) $this->option('dias');
        $forcar = (bool) $this->option('forcar');
        $hoje = now()->format('Y-m-d');
        $dataLimite = now()->addDays($diasAntes);

        $this->info("Período: {$hoje} até {$dataLimite->format('Y-m-d')} (próximas do vencimento em até {$diasAntes} dias).");
        if ($forcar) {
            $this->warn('Modo --forcar: notificando todas as próximas do vencimento (incluindo já notificadas).');
        }

        // Company não é tenant-aware: é justamente o ponto de partida da iteração.
        $empresas = Company::query()->get();

        if ($empresas->isEmpty()) {
            $this->info('Nenhuma empresa cadastrada para processar.');
            $this->newLine();
            $this->info('Processo concluído!');

            return Command::SUCCESS;
        }

        $proximas = $this->acumuladorVazio();
        $vencidas = $this->acumuladorVazio();
        $statusAtualizados = 0;

        foreach ($empresas as $empresa) {
            [$resultadoProximas, $resultadoVencidas, $atualizadas] = $this->currentCompany->runAs(
                $empresa,
                function () use ($diasAntes, $forcar) {
                    return [
                        $this->notificacaoService->notificarPromissoriasProximasVencimento($diasAntes, $forcar),
                        $this->notificacaoService->notificarPromissoriasVencidas(),
                        $this->notificacaoService->atualizarStatusVencidas(),
                    ];
                }
            );

            $this->acumular($proximas, $resultadoProximas);
            $this->acumular($vencidas, $resultadoVencidas);
            $statusAtualizados += $atualizadas;
        }

        // Os totais abaixo são globais (soma de todas as empresas); nenhuma mensagem
        // identifica empresas individualmente.
        $this->info('Verificando promissórias próximas do vencimento...');
        $this->reportarFalhas($proximas);

        if ($proximas['notificadas'] === 0) {
            $this->info($proximas['total'] > 0
                ? "Existem {$proximas['total']} promissória(s) próxima(s) do vencimento (todas já notificadas anteriormente)."
                : 'Nenhuma promissória próxima do vencimento no período.');
        } else {
            $this->info("Encontradas {$proximas['total']} promissória(s) próximas do vencimento.");
            $this->info("{$proximas['notificadas']} promissória(s) notificada(s).");
            $this->reportarErros($proximas['erros'], 'Erro ao notificar promissória');
        }

        $this->newLine();

        $this->info('Verificando promissórias vencidas (todas as não pagas, inclusive já notificadas)...');
        $this->reportarFalhas($vencidas);

        if ($vencidas['total'] > 0) {
            $this->warn("⚠️ Encontradas {$vencidas['total']} promissória(s) VENCIDA(S)!");
        }
        $this->info($vencidas['notificadas'] > 0
            ? "{$vencidas['notificadas']} promissória(s) vencida(s) notificada(s)."
            : 'Nenhuma promissória vencida encontrada para notificar.');
        $this->reportarErros($vencidas['erros'], 'Erro ao notificar promissória vencida');

        $this->newLine();

        $this->info('Atualizando status de promissórias vencidas...');
        if ($statusAtualizados > 0) {
            $this->info("Atualizadas {$statusAtualizados} promissória(s) como vencidas.");
        } else {
            $this->info('Nenhuma promissória precisa ter o status atualizado.');
        }

        $this->newLine();
        $this->info('Processo concluído!');

        return Command::SUCCESS;
    }

    /**
     * @return array{notificadas: int, total: int, erros: array<int, array<string, mixed>>, falhas: array<int, string>}
     */
    private function acumuladorVazio(): array
    {
        return ['notificadas' => 0, 'total' => 0, 'erros' => [], 'falhas' => []];
    }

    /**
     * Soma o resultado de uma empresa aos totais globais do comando.
     *
     * @param  array{notificadas: int, total: int, erros: array, falhas: array}  $acumulador
     * @param  array<string, mixed>  $resultado
     */
    private function acumular(array &$acumulador, array $resultado): void
    {
        $acumulador['notificadas'] += $resultado['notificadas'] ?? 0;
        $acumulador['total'] += $resultado['total'] ?? 0;
        $acumulador['erros'] = array_merge($acumulador['erros'], $resultado['erros'] ?? []);

        if (($resultado['sucesso'] ?? true) === false && isset($resultado['mensagem'])) {
            $acumulador['falhas'][$resultado['mensagem']] = $resultado['mensagem'];
        }
    }

    /**
     * @param  array{falhas: array<string, string>}  $acumulador
     */
    private function reportarFalhas(array $acumulador): void
    {
        foreach ($acumulador['falhas'] as $mensagem) {
            $this->error($mensagem);
        }
    }

    /**
     * @param  array<int, array{promissoria_id: mixed, erro: string}>  $erros
     */
    private function reportarErros(array $erros, string $prefixo): void
    {
        foreach ($erros as $erro) {
            $this->error("{$prefixo} #{$erro['promissoria_id']}: {$erro['erro']}");
        }
    }
}
