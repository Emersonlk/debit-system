<?php

namespace App\Repositories;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
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

        $sortBy = $filters['sort_by'] ?? 'cliente_nome';
        $sortOrder = isset($filters['sort_order']) && strtolower($filters['sort_order']) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['cliente_nome', 'valor', 'data_vencimento'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'cliente_nome';
        }

        if ($sortBy === 'cliente_nome') {
            $query->orderBy(Cliente::select('nome')->whereColumn('clientes.id', 'promissorias.cliente_id'), $sortOrder);
        } elseif ($sortBy === 'valor') {
            // Ordenar pelo mesmo valor exibido na listagem (total: valor_original ?? valor) e forçar numérico
            $query->orderByRaw(
                'CAST(COALESCE(promissorias.valor_original, promissorias.valor) AS DECIMAL(10,2)) '
                . ($sortOrder === 'desc' ? 'DESC' : 'ASC')
            );
        } else {
            $query->orderBy('promissorias.' . $sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
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
