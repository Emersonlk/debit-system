<?php

namespace App\Services;

use App\DTOs\CreateClienteDTO;
use App\DTOs\UpdateClienteDTO;
use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClienteService
{
    public function __construct(
        private ClienteRepositoryInterface $clienteRepository
    ) {
    }

    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->clienteRepository->paginate($perPage);
    }

    public function buscarPorId(int $id): ?Cliente
    {
        return $this->clienteRepository->find($id);
    }

    public function criar(CreateClienteDTO $dto): Cliente
    {
        return $this->clienteRepository->create($dto->toArray());
    }

    public function atualizar(Cliente $cliente, UpdateClienteDTO $dto): bool
    {
        return $this->clienteRepository->update($cliente, $dto->toArray());
    }

    public function excluir(Cliente $cliente): bool
    {
        return $this->clienteRepository->delete($cliente);
    }
}
