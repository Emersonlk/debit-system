<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClienteRepositoryInterface
{
    /**
     * @param  int  $perPage
     * @param  string|null  $search  Busca por nome (ou parte do nome)
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator;
    public function find(int $id): ?Cliente;
    public function create(array $data): Cliente;
    public function update(Cliente $cliente, array $data): bool;
    public function delete(Cliente $cliente): bool;
    public function findByEmail(string $email): ?Cliente;
    public function findByCpf(string $cpf): ?Cliente;
}
