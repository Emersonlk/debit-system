<?php

namespace App\Services;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\PagamentoParcialDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Enums\PromissoriaStatus;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PromissoriaService
{
    public function __construct(
        private PromissoriaRepositoryInterface $promissoriaRepository
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->promissoriaRepository->paginate($filtros, $perPage);
    }

    public function buscarPorId(int $id): ?Promissoria
    {
        return $this->promissoriaRepository->find($id);
    }

    public function criar(CreatePromissoriaDTO $dto): Promissoria
    {
        return $this->promissoriaRepository->create($dto->toArray());
    }

    public function atualizar(Promissoria $promissoria, UpdatePromissoriaDTO $dto): bool
    {
        $dados = $dto->toArray();
        
        // Se marcar como paga, atualiza data_pagamento automaticamente
        $statusValue = is_string($dados['status'] ?? null) ? $dados['status'] : ($dados['status']?->value ?? null);
        if ($statusValue === PromissoriaStatus::PAGA->value && !$promissoria->data_pagamento) {
            $dados['data_pagamento'] = now();
        }

        // Se desmarcar como paga, remove data_pagamento
        if ($statusValue && $statusValue !== PromissoriaStatus::PAGA->value) {
            $dados['data_pagamento'] = null;
        }

        return $this->promissoriaRepository->update($promissoria, $dados);
    }

    public function excluir(Promissoria $promissoria): bool
    {
        return $this->promissoriaRepository->delete($promissoria);
    }

    public function marcarComoPaga(Promissoria $promissoria): bool
    {
        // Verifica se a promissória já está paga
        if ($promissoria->status === PromissoriaStatus::PAGA) {
            throw new Exception('Esta promissória já está marcada como paga.');
        }

        // Com pagamentos parciais, valor já é o saldo restante; senão, é o valor total
        $tinhaParciais = $promissoria->valor_original !== null;
        $valorARegistrar = $tinhaParciais
            ? (float) $promissoria->saldo_restante
            : (float) $promissoria->valor;

        $promissoria->marcarComoPaga();

        // Só cria registro no histórico se há valor a registrar (evita duplicar quando parciais já quitaram)
        if ($valorARegistrar > 0) {
            $promissoria->historicoPagamentos()->create([
                'valor_pago' => $valorARegistrar,
                'data_pagamento' => now()->format('Y-m-d'),
                'observacoes' => $tinhaParciais ? 'Pagamento integral (saldo)' : 'Pagamento integral',
            ]);
        }

        // Com pagamentos parciais, zera o saldo para valor_total_pago = valor_original e saldo_restante = 0
        if ($tinhaParciais) {
            $promissoria->update(['valor' => 0]);
        }

        return true;
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
     */
    public function registrarPagamentoParcial(Promissoria $promissoria, PagamentoParcialDTO $dto): HistoricoPagamento
    {
        // Verifica se a promissória está paga ou cancelada
        if ($promissoria->status === PromissoriaStatus::PAGA) {
            throw new Exception('Não é possível registrar pagamento parcial em uma promissória já paga.');
        }

        if ($promissoria->status === PromissoriaStatus::CANCELADA) {
            throw new Exception('Não é possível registrar pagamento parcial em uma promissória cancelada.');
        }

        // Saldo restante: se já teve pagamento parcial, valor já é o saldo; senão, valor original - total pago
        $saldoRestante = $promissoria->saldo_restante;

        // Valida se o valor do pagamento não excede o saldo restante
        if ($dto->valor_pago > $saldoRestante) {
            throw new Exception("O valor do pagamento (R$ " . number_format($dto->valor_pago, 2, ',', '.') . ") excede o saldo restante (R$ " . number_format($saldoRestante, 2, ',', '.') . ").");
        }

        // Na primeira vez que há pagamento parcial: guarda o valor original
        $dadosAtualizacao = [];
        if ($promissoria->valor_original === null) {
            $dadosAtualizacao['valor_original'] = (float) $promissoria->valor;
        }

        // Cria o registro de pagamento no histórico
        $historicoPagamento = $promissoria->historicoPagamentos()->create($dto->toArray());

        // Deduz o valor pago do saldo restante (campo valor passa a ser o novo saldo restante)
        $novoSaldo = $saldoRestante - $dto->valor_pago;
        $dadosAtualizacao['valor'] = max(0, $novoSaldo);

        if ($novoSaldo <= 0) {
            $dadosAtualizacao['status'] = PromissoriaStatus::PAGA;
            $dadosAtualizacao['data_pagamento'] = $dto->data_pagamento;
        }

        $promissoria->update($dadosAtualizacao);

        return $historicoPagamento->fresh(['promissoria']);
    }

    /**
     * Cancela uma promissória
     */
    public function cancelar(Promissoria $promissoria, ?string $observacoes = null): bool
    {
        // Verifica se a promissória já está paga
        if ($promissoria->status === PromissoriaStatus::PAGA) {
            throw new Exception('Não é possível cancelar uma promissória já paga.');
        }

        // Verifica se já está cancelada
        if ($promissoria->status === PromissoriaStatus::CANCELADA) {
            throw new Exception('Esta promissória já está cancelada.');
        }

        // Atualiza observações apenas com o valor enviado (não concatena com o que já existia)
        if ($observacoes !== null && $observacoes !== '') {
            $promissoria->update(['observacoes' => $observacoes]);
        }

        return $promissoria->cancelar();
    }

    /**
     * Obtém o histórico de pagamentos de uma promissória
     */
    public function obterHistoricoPagamentos(Promissoria $promissoria): Collection
    {
        return $promissoria->historicoPagamentos()->orderBy('data_pagamento', 'desc')->get();
    }
}
