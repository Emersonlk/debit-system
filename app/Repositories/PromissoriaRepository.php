<?php

namespace App\Repositories;

use App\Enums\PromissoriaStatus;
use App\Models\Promissoria;
use App\Repositories\Contracts\PromissoriaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PromissoriaRepository implements PromissoriaRepositoryInterface
{
    public function __construct(
        private Promissoria $model
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with('cliente');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['cliente_id'])) {
            $query->where('cliente_id', $filters['cliente_id']);
        }

        if (isset($filters['vencidas'])) {
            $query->where('data_vencimento', '<', now())
                ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value]);
        }

        if (isset($filters['proximas_vencimento'])) {
            $dias = (int) ($filters['dias'] ?? 3);
            $query->whereBetween('data_vencimento', [now(), now()->addDays($dias)])
                ->where('status', PromissoriaStatus::PENDENTE->value);
        }

        return $query->orderBy('data_vencimento', 'asc')
            ->paginate($perPage);
    }

    public function find(int $id): ?Promissoria
    {
        return $this->model->with('cliente')->find($id);
    }

    public function create(array $data): Promissoria
    {
        return $this->model->create($data);
    }

    public function update(Promissoria $promissoria, array $data): bool
    {
        return $promissoria->update($data);
    }

    public function delete(Promissoria $promissoria): bool
    {
        return $promissoria->delete();
    }

    public function findProximasVencimento(int $dias = 3): Collection
    {
        return $this->model->with('cliente')
            ->where('status', PromissoriaStatus::PENDENTE->value)
            ->whereBetween('data_vencimento', [now(), now()->addDays($dias)])
            ->get();
    }

    public function findVencidas(): Collection
    {
        return $this->model->with('cliente')
            ->where('data_vencimento', '<', now())
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->get();
    }

    public function findNaoNotificadasProximasVencimento(int $dias = 3): Collection
    {
        return $this->model->with('cliente')
            ->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '>=', now()->startOfDay()) // A partir de hoje (inclusivo)
            ->where('data_vencimento', '<=', now()->addDays($dias)->endOfDay()) // Até X dias (inclusivo)
            ->where('notificado', false)
            ->get();
    }

    public function findNaoNotificadasVencidas(): Collection
    {
        // Busca promissórias vencidas que não estejam pagas
        // Não filtra por 'notificado' para continuar notificando até ser paga
        return $this->model->with('cliente')
            ->where('data_vencimento', '<', now())
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->get();
    }

    public function atualizarStatusVencidas(): int
    {
        return $this->model->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '<', now())
            ->update(['status' => PromissoriaStatus::VENCIDA->value]);
    }
}
