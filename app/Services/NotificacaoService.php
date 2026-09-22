<?php

namespace App\Services;

use App\Models\Promissoria;
use App\Models\User;
use App\Notifications\PromissoriaVencida;
use App\Notifications\PromissoriaVencimentoProximo;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Services\Contracts\NotificacaoServiceInterface;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Opera sempre sobre a empresa do contexto atual — não conhece as demais.
 *
 * Quem percorre as empresas é o comando promissorias:verificar-vencimento, que
 * estabelece o contexto de cada uma com CurrentCompany::runAs(). Chamado sem
 * contexto, este serviço falha (fail-closed) em vez de processar tudo.
 */
class NotificacaoService implements NotificacaoServiceInterface
{
    /**
     * Quantas promissórias são hidratadas por vez no processamento.
     *
     * Uma promissória com o cliente carregado ocupa cerca de 4 KB, então 500 por
     * lote custam ~2 MB — folgados dentro do memory_limit de 128 MB do container,
     * mesmo somados ao consumo base do processo. Carregar tudo de uma vez chegava a
     * 124 MB com 30 mil vencidas e estourava acima disso.
     */
    private const TAMANHO_DO_LOTE = 500;

    public function __construct(
        private PromissoriaRepositoryInterface $promissoriaRepository,
        private CurrentCompany $currentCompany
    ) {
    }

    /**
     * Usuários que devem receber as notificações: apenas os da empresa do contexto.
     *
     * Super Admin tem company_id nulo e, portanto, fica naturalmente de fora.
     *
     * @return Collection<int, User>
     */
    private function usuariosDaEmpresa(): Collection
    {
        return User::where('company_id', $this->currentCompany->id())->get();
    }

    /**
     * Notifica usuários sobre promissórias próximas do vencimento.
     * Quando $forcar = true, notifica todas as próximas (ignora flag notificado).
     */
    public function notificarPromissoriasProximasVencimento(int $dias = 3, bool $forcar = false): array
    {
        // A quantidade vem do banco: antes a coleção inteira era carregada só para
        // descobrir se havia algo a fazer, e no caminho em que tudo já estava
        // notificado ela era carregada duas vezes — uma delas apenas para contar.
        $total = $forcar
            ? $this->promissoriaRepository->contarProximasVencimento($dias)
            : $this->promissoriaRepository->contarNaoNotificadasProximasVencimento($dias);

        if ($total === 0) {
            $totalProximas = $this->promissoriaRepository->contarProximasVencimento($dias);
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

        $usuarios = $this->usuariosDaEmpresa();

        if ($usuarios->isEmpty()) {
            return [
                'sucesso' => false,
                'notificadas' => 0,
                'mensagem' => 'Nenhum usuário encontrado para enviar notificações.'
            ];
        }

        $notificadas = 0;
        $erros = [];

        $processarLote = function (Collection $lote) use ($usuarios, &$notificadas, &$erros): void {
            // Só entram aqui os ids notificados sem erro: a marcação continua valendo
            // por promissória, apenas deixa de custar uma consulta cada.
            $idsNotificados = [];

            foreach ($lote as $promissoria) {
                try {
                    foreach ($usuarios as $usuario) {
                        $usuario->notify(new PromissoriaVencimentoProximo($promissoria));
                    }

                    $idsNotificados[] = $promissoria->id;
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

            $this->promissoriaRepository->marcarComoNotificadas($idsNotificados);
        };

        if ($forcar) {
            $this->promissoriaRepository->chunkProximasVencimento($dias, self::TAMANHO_DO_LOTE, $processarLote);
        } else {
            $this->promissoriaRepository->chunkNaoNotificadasProximasVencimento($dias, self::TAMANHO_DO_LOTE, $processarLote);
        }

        return [
            'sucesso' => true,
            'notificadas' => $notificadas,
            'total' => $total,
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
        $total = $this->promissoriaRepository->contarVencidas();

        if ($total === 0) {
            return [
                'sucesso' => true,
                'notificadas' => 0,
                'mensagem' => 'Nenhuma promissória vencida encontrada para notificar.'
            ];
        }

        $usuarios = $this->usuariosDaEmpresa();

        if ($usuarios->isEmpty()) {
            return [
                'sucesso' => false,
                'notificadas' => 0,
                'mensagem' => 'Nenhum usuário encontrado para enviar notificações.'
            ];
        }

        $notificadas = 0;
        $erros = [];

        $this->promissoriaRepository->chunkVencidas(
            self::TAMANHO_DO_LOTE,
            function (Collection $lote) use ($usuarios, &$notificadas, &$erros): void {
                foreach ($lote as $promissoria) {
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
            }
        );

        return [
            'sucesso' => true,
            'notificadas' => $notificadas,
            'total' => $total,
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
