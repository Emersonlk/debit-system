<?php

namespace App\Services;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\PagamentoParcialDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Enums\PromissoriaStatus;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use App\Services\Contracts\PromissoriaServiceInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PromissoriaService implements PromissoriaServiceInterface
{
    public function __construct(
        private PromissoriaRepositoryInterface $promissoriaRepository
    ) {
    }

    /**
     * Relê a promissória com lock de linha, para uso dentro de uma transação.
     *
     * A instância que chega do controller vem do route model binding, ou seja, foi
     * lida antes de qualquer transação existir: decidir sobre ela é decidir sobre um
     * retrato possivelmente vencido. Duas requisições simultâneas liam o mesmo saldo
     * e ambas gravavam, registrando o dobro do valor devido.
     *
     * Por isso a releitura acontece aqui, pela chave, e o resultado é o único objeto
     * que os métodos abaixo consultam e alteram. O `find()` passa pelo global scope
     * de empresa, então o lock continua restrito ao tenant do contexto.
     */
    private function bloquearParaAtualizacao(Promissoria $promissoria): Promissoria
    {
        $bloqueada = Promissoria::lockForUpdate()->find($promissoria->getKey());

        if ($bloqueada === null) {
            // A promissória existia no route model binding e sumiu antes do lock.
            throw (new ModelNotFoundException())->setModel(Promissoria::class, [$promissoria->getKey()]);
        }

        return $bloqueada;
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->promissoriaRepository->paginate($filtros, $perPage);
    }

    public function criar(CreatePromissoriaDTO $dto): Promissoria
    {
        return $this->promissoriaRepository->create($dto->toArray());
    }

    /**
     * A decisão sobre data_pagamento depende do estado atual da promissória, então a
     * leitura precisa estar protegida como nos demais fluxos. As regras de negócio
     * são as mesmas de antes — inclusive a de que este método pode sobrescrever
     * `valor` e `status` definidos por um pagamento, o que é o comportamento
     * existente e não foi alterado aqui.
     */
    public function atualizar(Promissoria $promissoria, UpdatePromissoriaDTO $dto): bool
    {
        return DB::transaction(function () use ($promissoria, $dto) {
            $bloqueada = $this->bloquearParaAtualizacao($promissoria);

            $dados = $dto->toArray();

            // Se marcar como paga, atualiza data_pagamento automaticamente
            $statusValue = is_string($dados['status'] ?? null) ? $dados['status'] : ($dados['status']?->value ?? null);
            if ($statusValue === PromissoriaStatus::PAGA->value && !$bloqueada->data_pagamento) {
                $dados['data_pagamento'] = now();
            }

            // Se desmarcar como paga, remove data_pagamento
            if ($statusValue && $statusValue !== PromissoriaStatus::PAGA->value) {
                $dados['data_pagamento'] = null;
            }

            return $this->promissoriaRepository->update($bloqueada, $dados);
        });
    }

    public function excluir(Promissoria $promissoria): bool
    {
        return $this->promissoriaRepository->delete($promissoria);
    }

    /**
     * São até três escritas (status/data, histórico e zeragem do saldo) que só fazem
     * sentido juntas: sem a transação, uma falha no meio deixava o pagamento
     * registrado sem o saldo abatido. O lock serializa chamadas simultâneas, de modo
     * que a segunda encontra o status já PAGA e é recusada em vez de criar um
     * segundo registro de pagamento integral.
     */
    public function marcarComoPaga(Promissoria $promissoria): bool
    {
        return DB::transaction(function () use ($promissoria) {
            $bloqueada = $this->bloquearParaAtualizacao($promissoria);

            // Verifica se a promissória já está paga
            if ($bloqueada->status === PromissoriaStatus::PAGA) {
                throw new Exception('Esta promissória já está marcada como paga.');
            }

            // Com pagamentos parciais, valor já é o saldo restante; senão, é o valor total
            $tinhaParciais = $bloqueada->valor_original !== null;
            $valorARegistrar = $tinhaParciais
                ? (float) $bloqueada->saldo_restante
                : (float) $bloqueada->valor;

            $bloqueada->marcarComoPaga();

            // Só cria registro no histórico se há valor a registrar (evita duplicar quando parciais já quitaram)
            if ($valorARegistrar > 0) {
                $bloqueada->historicoPagamentos()->create([
                    'valor_pago' => $valorARegistrar,
                    'data_pagamento' => now()->format('Y-m-d'),
                    'observacoes' => $tinhaParciais ? 'Pagamento integral (saldo)' : 'Pagamento integral',
                ]);
            }

            // Com pagamentos parciais, zera o saldo para valor_total_pago = valor_original e saldo_restante = 0
            if ($tinhaParciais) {
                $bloqueada->update(['valor' => 0]);
            }

            return true;
        });
    }

    public function obterProximasVencimento(int $dias = 3): Collection
    {
        return $this->promissoriaRepository->findProximasVencimento($dias);
    }

    public function obterVencidas(): Collection
    {
        return $this->promissoriaRepository->findVencidas();
    }

    public function obterResumoVencimento(int $dias = 3): array
    {
        $proximasVencimento = $this->obterProximasVencimento($dias);
        $vencidas = $this->obterVencidas();
        $dataLimite = now()->addDays($dias);

        $totalProximas = $proximasVencimento->sum(fn ($p) => $p->saldo_restante);
        $totalVencidas = $vencidas->sum(fn ($p) => $p->saldo_restante);

        return [
            'proximas_vencimento' => [
                'quantidade' => $proximasVencimento->count(),
                'valor_total' => number_format($totalProximas, 2, '.', ''),
                'dias_verificacao' => $dias,
                'data_limite' => $dataLimite->format('Y-m-d'),
                'promissorias' => $proximasVencimento->map(function ($promissoria) {
                    $hoje = now()->startOfDay();
                    $vencimento = $promissoria->data_vencimento->copy()->startOfDay();
                    return [
                        'id' => $promissoria->id,
                        'cliente_id' => $promissoria->cliente_id,
                        'cliente' => $promissoria->cliente->nome,
                        'valor' => number_format($promissoria->saldo_restante, 2, '.', ''),
                        'saldo_restante' => number_format($promissoria->saldo_restante, 2, '.', ''),
                        'data_vencimento' => $promissoria->data_vencimento->format('Y-m-d'),
                        'dias_restantes' => (int) $hoje->diffInDays($vencimento, false),
                    ];
                })
            ],
            'vencidas' => [
                'quantidade' => $vencidas->count(),
                'valor_total' => number_format($totalVencidas, 2, '.', ''),
                'promissorias' => $vencidas->map(function ($promissoria) {
                    $hoje = now()->startOfDay();
                    $vencimento = $promissoria->data_vencimento->copy()->startOfDay();
                    return [
                        'id' => $promissoria->id,
                        'cliente_id' => $promissoria->cliente_id,
                        'cliente' => $promissoria->cliente->nome,
                        'valor' => number_format($promissoria->saldo_restante, 2, '.', ''),
                        'saldo_restante' => number_format($promissoria->saldo_restante, 2, '.', ''),
                        'data_vencimento' => $promissoria->data_vencimento->format('Y-m-d'),
                        'dias_vencida' => (int) $vencimento->diffInDays($hoje),
                    ];
                })
            ]
        ];
    }

    /**
     * Registra um pagamento parcial na promissória
     *
     * Todo o trecho entre a leitura do saldo e a gravação roda sob lock: era
     * exatamente essa janela que permitia a duas requisições lerem o mesmo saldo e
     * ambas gravarem, registrando o dobro do valor devido. O histórico e a
     * atualização da promissória também passam a ser atômicos entre si.
     */
    public function registrarPagamentoParcial(Promissoria $promissoria, PagamentoParcialDTO $dto): HistoricoPagamento
    {
        return DB::transaction(function () use ($promissoria, $dto) {
            $bloqueada = $this->bloquearParaAtualizacao($promissoria);

            // Verifica se a promissória está paga ou cancelada
            if ($bloqueada->status === PromissoriaStatus::PAGA) {
                throw new Exception('Não é possível registrar pagamento parcial em uma promissória já paga.');
            }

            if ($bloqueada->status === PromissoriaStatus::CANCELADA) {
                throw new Exception('Não é possível registrar pagamento parcial em uma promissória cancelada.');
            }

            // Saldo restante: se já teve pagamento parcial, valor já é o saldo; senão, valor original - total pago
            $saldoRestante = $bloqueada->saldo_restante;

            // Valida se o valor do pagamento não excede o saldo restante
            if ($dto->valor_pago > $saldoRestante) {
                throw new Exception("O valor do pagamento (R$ " . number_format($dto->valor_pago, 2, ',', '.') . ") excede o saldo restante (R$ " . number_format($saldoRestante, 2, ',', '.') . ").");
            }

            // Na primeira vez que há pagamento parcial: guarda o valor original
            $dadosAtualizacao = [];
            if ($bloqueada->valor_original === null) {
                $dadosAtualizacao['valor_original'] = (float) $bloqueada->valor;
            }

            // Cria o registro de pagamento no histórico
            $historicoPagamento = $bloqueada->historicoPagamentos()->create($dto->toArray());

            // Deduz o valor pago do saldo restante (campo valor passa a ser o novo saldo restante)
            $novoSaldo = $saldoRestante - $dto->valor_pago;
            $dadosAtualizacao['valor'] = max(0, $novoSaldo);

            if ($novoSaldo <= 0) {
                $dadosAtualizacao['status'] = PromissoriaStatus::PAGA;
                $dadosAtualizacao['data_pagamento'] = $dto->data_pagamento;
            }

            $bloqueada->update($dadosAtualizacao);

            // O fresh() fica dentro da transação de propósito: é ele que produz o
            // objeto devolvido ao controller, e aqui enxerga a promissória já
            // atualizada. Movido para fora, leria o mesmo estado — mas numa segunda
            // conexão lógica, sem a garantia de ler o que esta transação escreveu.
            return $historicoPagamento->fresh(['promissoria']);
        });
    }

    /**
     * Cancela uma promissória
     *
     * O lock não muda as regras de cancelamento; serve para serializar o
     * cancelamento com pagamentos sobre a mesma promissória, que hoje podiam
     * acontecer em paralelo.
     */
    public function cancelar(Promissoria $promissoria, ?string $observacoes = null): bool
    {
        return DB::transaction(function () use ($promissoria, $observacoes) {
            $bloqueada = $this->bloquearParaAtualizacao($promissoria);

            // Verifica se a promissória já está paga
            if ($bloqueada->status === PromissoriaStatus::PAGA) {
                throw new Exception('Não é possível cancelar uma promissória já paga.');
            }

            // Verifica se já está cancelada
            if ($bloqueada->status === PromissoriaStatus::CANCELADA) {
                throw new Exception('Esta promissória já está cancelada.');
            }

            // Atualiza observações apenas com o valor enviado (não concatena com o que já existia)
            if ($observacoes !== null && $observacoes !== '') {
                $bloqueada->update(['observacoes' => $observacoes]);
            }

            return $bloqueada->cancelar();
        });
    }

    /**
     * Obtém o histórico de pagamentos de uma promissória
     */
    public function obterHistoricoPagamentos(Promissoria $promissoria): Collection
    {
        return $promissoria->historicoPagamentos()->orderBy('data_pagamento', 'desc')->get();
    }
}
