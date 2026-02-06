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
            $hojeStr = now()->format('Y-m-d');
            $query->where('data_vencimento', '<', $hojeStr)
                ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value]);
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
        $hojeStr = now()->format('Y-m-d');
        return $this->model->with('cliente')
            ->where('data_vencimento', '<', $hojeStr)
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
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

    public function findNaoNotificadasVencidas(): Collection
    {
        $hojeStr = now()->format('Y-m-d');
        return $this->model->with('cliente')
            ->where('data_vencimento', '<', $hojeStr)
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->get();
    }

    public function atualizarStatusVencidas(): int
    {
        $hojeStr = now()->format('Y-m-d');
        return $this->model->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '<', $hojeStr)
            ->update(['status' => PromissoriaStatus::VENCIDA->value]);
    }
}
