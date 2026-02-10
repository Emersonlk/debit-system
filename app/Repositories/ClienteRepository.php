<?php

namespace App\Repositories;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClienteRepository implements ClienteRepositoryInterface
{
    public function __construct(
        private Cliente $model
    ) {
    }

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->model->query()->with('endereco');

        if ($search !== null && trim($search) !== '') {
            $query->where('nome', 'like', '%' . trim($search) . '%')->orderBy('nome', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?Cliente
    {
        return $this->model->with('endereco')->find($id);
    }

    public function create(array $data): Cliente
    {
        return $this->model->create($data);
    }

    public function update(Cliente $cliente, array $data): bool
    {
        return $cliente->update($data);
    }

    public function delete(Cliente $cliente): bool
    {
        return $cliente->delete();
    }

    public function findByEmail(string $email): ?Cliente
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByCpf(string $cpf): ?Cliente
    {
        return $this->model->where('cpf', $cpf)->first();
    }
}
