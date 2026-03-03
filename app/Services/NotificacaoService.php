<?php

namespace App\Services;

use App\Models\Promissoria;
use App\Models\User;
use App\Notifications\PromissoriaVencida;
use App\Notifications\PromissoriaVencimentoProximo;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Services\Contracts\NotificacaoServiceInterface;
use Illuminate\Support\Facades\Log;

class NotificacaoService implements NotificacaoServiceInterface
{
    public function __construct(
        private PromissoriaRepositoryInterface $promissoriaRepository
    ) {
    }

    /**
     * Notifica usuários sobre promissórias próximas do vencimento.
     * Quando $forcar = true, notifica todas as próximas (ignora flag notificado).
     */
    public function notificarPromissoriasProximasVencimento(int $dias = 3, bool $forcar = false): array
    {
        $promissorias = $forcar
            ? $this->promissoriaRepository->findProximasVencimento($dias)
            : $this->promissoriaRepository->findNaoNotificadasProximasVencimento($dias);

        if ($promissorias->isEmpty()) {
            $totalProximas = $this->promissoriaRepository->findProximasVencimento($dias)->count();
            $mensagem = $totalProximas > 0
                ? "Existem {$totalProximas} promissória(s) próxima(s) do vencimento (todas já notificadas anteriormente)."
                : 'Nenhuma promissória próxima do vencimento no período.';
            return [
                'sucesso' => true,
                'notificadas' => 0,
                'total' => $totalProximas,
                'mensagem' => $mensagem
            ];
        }

        $usuarios = User::all();

        if ($usuarios->isEmpty()) {
            return [
                'sucesso' => false,
                'notificadas' => 0,
                'mensagem' => 'Nenhum usuário encontrado para enviar notificações.'
            ];
        }

        $notificadas = 0;
        $erros = [];

        foreach ($promissorias as $promissoria) {
            try {
                foreach ($usuarios as $usuario) {
                    $usuario->notify(new PromissoriaVencimentoProximo($promissoria));
                }

                $this->promissoriaRepository->update($promissoria, ['notificado' => true]);
                $notificadas++;
            } catch (\Exception $e) {
                $erros[] = [
                    'promissoria_id' => $promissoria->id,
                    'erro' => $e->getMessage()
                ];
                Log::error("Erro ao notificar promissória #{$promissoria->id}", [
                    'erro' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [
            'sucesso' => true,
            'notificadas' => $notificadas,
            'total' => $promissorias->count(),
            'erros' => $erros,
            'mensagem' => "{$notificadas} promissória(s) notificada(s)."
        ];
    }

    /**
     * Notifica usuários sobre promissórias vencidas (não pagas).
     * Sempre traz TODAS as vencidas e envia email, mesmo que já tenham sido notificadas antes,
     * até que a promissória seja paga.
     */
    public function notificarPromissoriasVencidas(): array
    {
        $promissorias = $this->promissoriaRepository->findVencidas();

        if ($promissorias->isEmpty()) {
            return [
                'sucesso' => true,
                'notificadas' => 0,
                'mensagem' => 'Nenhuma promissória vencida encontrada para notificar.'
            ];
        }

        $usuarios = User::all();

        if ($usuarios->isEmpty()) {
            return [
                'sucesso' => false,
                'notificadas' => 0,
                'mensagem' => 'Nenhum usuário encontrado para enviar notificações.'
            ];
        }

        $notificadas = 0;
        $erros = [];

        foreach ($promissorias as $promissoria) {
            try {
                foreach ($usuarios as $usuario) {
                    $usuario->notify(new PromissoriaVencida($promissoria));
                }

                // Para promissórias vencidas, NÃO marca como notificado
                // Isso permite continuar notificando até que seja marcada como paga
                $notificadas++;
            } catch (\Exception $e) {
                $erros[] = [
                    'promissoria_id' => $promissoria->id,
                    'erro' => $e->getMessage()
                ];
                Log::error("Erro ao notificar promissória vencida #{$promissoria->id}", [
                    'erro' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [
            'sucesso' => true,
            'notificadas' => $notificadas,
            'total' => $promissorias->count(),
            'erros' => $erros,
            'mensagem' => "{$notificadas} promissória(s) vencida(s) notificada(s)."
        ];
    }

    /**
     * Atualiza status de promissórias vencidas
     */
    public function atualizarStatusVencidas(): int
    {
        return $this->promissoriaRepository->atualizarStatusVencidas();
    }
}
