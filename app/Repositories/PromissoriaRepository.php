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
        $query = $this->model->with(['cliente', 'historicoPagamentos']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['cliente_id'])) {
            $query->where('cliente_id', $filters['cliente_id']);
        }

        if (isset($filters['vencidas'])) {
            $query->whereDate('data_vencimento', '<', now()->startOfDay())
                ->whereNotIn('status', [PromissoriaStatus::PAGA->value, PromissoriaStatus::CANCELADA->value]);
        }

        if (isset($filters['proximas_vencimento'])) {
            $dias = (int) ($filters['dias'] ?? 3);
            $hojeStr = now()->format('Y-m-d');
            $limiteStr = now()->addDays($dias)->format('Y-m-d');
            $query->where('data_vencimento', '>=', $hojeStr)
                ->where('data_vencimento', '<=', $limiteStr)
                ->where('status', PromissoriaStatus::PENDENTE->value);
        }

        return $query->orderBy('data_vencimento', 'asc')
            ->paginate($perPage);
    }

    public function find(int $id): ?Promissoria
    {
        return $this->model->with(['cliente', 'historicoPagamentos'])->find($id);
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
        $hojeStr = now()->format('Y-m-d');
        $limiteStr = now()->addDays($dias)->format('Y-m-d');
        return $this->model->with('cliente')
            ->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '>=', $hojeStr)
            ->where('data_vencimento', '<=', $limiteStr)
            ->get();
    }

    public function findVencidas(): Collection
    {
        $hoje = now()->startOfDay();
        return $this->model->with('cliente')
            ->whereDate('data_vencimento', '<', $hoje)
            ->whereNotIn('status', [PromissoriaStatus::PAGA->value, PromissoriaStatus::CANCELADA->value])
            ->get();
    }

    public function findNaoNotificadasProximasVencimento(int $dias = 3): Collection
    {
        $hojeStr = now()->format('Y-m-d');
        $limiteStr = now()->addDays($dias)->format('Y-m-d');
        return $this->model->with('cliente')
            ->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '>=', $hojeStr)
            ->where('data_vencimento', '<=', $limiteStr)
            ->where('notificado', false)
            ->get();
    }

    public function atualizarStatusVencidas(): int
    {
        return $this->model->where('status', PromissoriaStatus::PENDENTE->value)
            ->whereDate('data_vencimento', '<', now()->startOfDay())
            ->update(['status' => PromissoriaStatus::VENCIDA->value]);
    }
}
