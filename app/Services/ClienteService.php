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

    public function listar(int $perPage = 15, ?string $search = null, ?string $sortBy = null, string $sortOrder = 'asc'): LengthAwarePaginator
    {
        return $this->clienteRepository->paginate($perPage, $search, $sortBy, $sortOrder);
    }

    public function buscarPorId(int $id): ?Cliente
    {
        return $this->clienteRepository->find($id);
    }

    public function criar(CreateClienteDTO $dto): Cliente
    {
        $cliente = $this->clienteRepository->create($dto->toArray());

        if ($dto->endereco !== null && !$dto->endereco->isEmpty()) {
            $cliente->endereco()->create($dto->endereco->toArray());
        }

        return $cliente->load('endereco');
    }

    public function atualizar(Cliente $cliente, UpdateClienteDTO $dto): bool
    {
        $this->clienteRepository->update($cliente, $dto->toArray());

        if ($dto->removerEndereco) {
            $cliente->endereco?->delete();
        } elseif ($dto->endereco !== null) {
            $dadosEndereco = $dto->endereco->toArray();
            if (!empty($dadosEndereco)) {
                $cliente->endereco()->updateOrCreate(
                    ['cliente_id' => $cliente->id],
                    $dadosEndereco
                );
            }
        }

        return true;
    }

    public function excluir(Cliente $cliente): bool
    {
        return $this->clienteRepository->delete($cliente);
    }
}
