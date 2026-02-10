<?php

namespace App\Repositories\Contracts;

use App\Models\Promissoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PromissoriaRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function find(int $id): ?Promissoria;
    public function create(array $data): Promissoria;
    public function update(Promissoria $promissoria, array $data): bool;
    public function delete(Promissoria $promissoria): bool;
    public function findProximasVencimento(int $dias = 3): Collection;
    public function findVencidas(): Collection;
    public function findNaoNotificadasProximasVencimento(int $dias = 3): Collection;
    public function atualizarStatusVencidas(): int;
}
