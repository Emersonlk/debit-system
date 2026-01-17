<?php

namespace App\Console\Commands;

use App\Services\NotificacaoService;
use Illuminate\Console\Command;

class VerificarPromissoriasVencimento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promissorias:verificar-vencimento {--dias=3 : Número de dias antes do vencimento para notificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica promissórias próximas do vencimento e envia notificações por email';

    public function __construct(
        private NotificacaoService $notificacaoService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diasAntes = (int) $this->option('dias');
        $dataLimite = now()->addDays($diasAntes);

        $this->info("Verificando promissórias que vencem até {$dataLimite->format('d/m/Y')}...");

        // Notifica promissórias próximas do vencimento
        $this->info("Verificando promissórias próximas do vencimento...");
        $resultadoProximas = $this->notificacaoService->notificarPromissoriasProximasVencimento($diasAntes);

        if ($resultadoProximas['sucesso']) {
            if ($resultadoProximas['notificadas'] === 0) {
                $this->info($resultadoProximas['mensagem']);
            } else {
                $this->info("Encontradas {$resultadoProximas['total']} promissória(s) próximas do vencimento.");
                $this->info($resultadoProximas['mensagem']);
                
                if (!empty($resultadoProximas['erros'])) {
                    foreach ($resultadoProximas['erros'] as $erro) {
                        $this->error("Erro ao notificar promissória #{$erro['promissoria_id']}: {$erro['erro']}");
                    }
                }
            }
        } else {
            $this->error($resultadoProximas['mensagem']);
        }

        $this->newLine();

        // Notifica promissórias vencidas
        $this->info("Verificando promissórias vencidas...");
        $resultadoVencidas = $this->notificacaoService->notificarPromissoriasVencidas();

        if ($resultadoVencidas['sucesso']) {
            if ($resultadoVencidas['notificadas'] === 0) {
                $this->info($resultadoVencidas['mensagem']);
            } else {
                $this->warn("⚠️ Encontradas {$resultadoVencidas['total']} promissória(s) VENCIDA(S)!");
                $this->info($resultadoVencidas['mensagem']);
                
                if (!empty($resultadoVencidas['erros'])) {
                    foreach ($resultadoVencidas['erros'] as $erro) {
                        $this->error("Erro ao notificar promissória vencida #{$erro['promissoria_id']}: {$erro['erro']}");
                    }
                }
            }
        } else {
            $this->error($resultadoVencidas['mensagem']);
        }

        $this->newLine();

        // Atualiza status de promissórias vencidas
        $this->info("Atualizando status de promissórias vencidas...");
        $vencidas = $this->notificacaoService->atualizarStatusVencidas();

        if ($vencidas > 0) {
            $this->info("Atualizadas {$vencidas} promissória(s) como vencidas.");
        } else {
            $this->info("Nenhuma promissória precisa ter o status atualizado.");
        }

        $this->newLine();
        $this->info("Processo concluído!");

        return Command::SUCCESS;
    }
}
