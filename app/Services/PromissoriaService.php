<?php

namespace App\Services;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Enums\PromissoriaStatus;
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

        return $promissoria->marcarComoPaga();
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

        $totalProximas = $proximasVencimento->sum('valor');
        $totalVencidas = $vencidas->sum('valor');

        return [
            'proximas_vencimento' => [
                'quantidade' => $proximasVencimento->count(),
                'valor_total' => number_format($totalProximas, 2, '.', ''),
                'dias_verificacao' => $dias,
                'data_limite' => $dataLimite->format('Y-m-d'),
                'promissorias' => $proximasVencimento->map(function ($promissoria) {
                    return [
                        'id' => $promissoria->id,
                        'cliente' => $promissoria->cliente->nome,
                        'valor' => number_format($promissoria->valor, 2, '.', ''),
                        'data_vencimento' => $promissoria->data_vencimento->format('Y-m-d'),
                        'dias_restantes' => now()->diffInDays($promissoria->data_vencimento, false),
                    ];
                })
            ],
            'vencidas' => [
                'quantidade' => $vencidas->count(),
                'valor_total' => number_format($totalVencidas, 2, '.', ''),
                'promissorias' => $vencidas->map(function ($promissoria) {
                    return [
                        'id' => $promissoria->id,
                        'cliente' => $promissoria->cliente->nome,
                        'valor' => number_format($promissoria->valor, 2, '.', ''),
                        'data_vencimento' => $promissoria->data_vencimento->format('Y-m-d'),
                        'dias_vencida' => now()->diffInDays($promissoria->data_vencimento),
                    ];
                })
            ]
        ];
    }
}
