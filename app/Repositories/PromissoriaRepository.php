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
        $query = $this->model->with('cliente');

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
            ->orderBy('data_vencimento', 'asc')
            ->get();
    }

    public function findVencidas(): Collection
    {
        return $this->queryVencidas()->get();
    }

    /**
     * Base das vencidas, compartilhada pela versão que devolve a coleção inteira
     * (dashboard) e pela que percorre em lotes (processamento de notificações).
     */
    private function queryVencidas(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->model->newQuery()
            ->with('cliente')
            ->whereDate('data_vencimento', '<', now()->startOfDay())
            ->whereNotIn('status', [PromissoriaStatus::PAGA->value, PromissoriaStatus::CANCELADA->value]);
    }

    private function queryProximasVencimento(int $dias): \Illuminate\Database\Eloquent\Builder
    {
        return $this->model->newQuery()
            ->with('cliente')
            ->where('status', PromissoriaStatus::PENDENTE->value)
            ->where('data_vencimento', '>=', now()->format('Y-m-d'))
            ->where('data_vencimento', '<=', now()->addDays($dias)->format('Y-m-d'));
    }

    // --------------------------------------------------- contagens no banco

    public function contarVencidas(): int
    {
        return $this->queryVencidas()->count();
    }

    public function contarProximasVencimento(int $dias = 3): int
    {
        return $this->queryProximasVencimento($dias)->count();
    }

    public function contarNaoNotificadasProximasVencimento(int $dias = 3): int
    {
        return $this->queryProximasVencimento($dias)->where('notificado', false)->count();
    }

    // ------------------------------------------------- percurso em lotes
    //
    // chunkById e não chunk: o processamento marca `notificado` nas próprias linhas
    // que está percorrendo, e o chunk por OFFSET encolheria o conjunto sob os pés,
    // pulando registros silenciosamente. chunkById avança pela chave primária, então
    // cada linha é visitada exatamente uma vez mesmo com o conjunto mudando.
    //
    // O global scope de empresa continua valendo em cada consulta de lote, portanto o
    // percurso é tenant-aware como as versões que devolvem a coleção.

    public function chunkVencidas(int $tamanho, callable $callback): void
    {
        $this->queryVencidas()->chunkById($tamanho, $callback);
    }

    public function chunkProximasVencimento(int $dias, int $tamanho, callable $callback): void
    {
        $this->queryProximasVencimento($dias)->chunkById($tamanho, $callback);
    }

    public function chunkNaoNotificadasProximasVencimento(int $dias, int $tamanho, callable $callback): void
    {
        $this->queryProximasVencimento($dias)->where('notificado', false)->chunkById($tamanho, $callback);
    }

    /**
     * Marca um conjunto de promissórias como notificadas numa única consulta.
     *
     * Substitui o UPDATE por registro que o serviço fazia dentro do laço — eram
     * 2.000 consultas para 2.000 promissórias. A semântica não muda: só entram aqui
     * os ids cuja notificação foi enviada sem erro.
     *
     * @param  list<int>  $ids
     */
    public function marcarComoNotificadas(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->model->newQuery()->whereKey($ids)->update(['notificado' => true]);
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
            ->orderBy('data_vencimento', 'asc')
            ->get();
    }

    public function atualizarStatusVencidas(): int
    {
        return $this->model->where('status', PromissoriaStatus::PENDENTE->value)
            ->whereDate('data_vencimento', '<', now()->startOfDay())
            ->update(['status' => PromissoriaStatus::VENCIDA->value]);
    }
}
