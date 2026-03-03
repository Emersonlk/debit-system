<?php

namespace App\Services\Contracts;

use App\DTOs\CreateClienteDTO;
use App\DTOs\UpdateClienteDTO;
use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClienteServiceInterface
{
    public function listar(int $perPage = 15, ?string $search = null, ?string $sortBy = null, string $sortOrder = 'asc'): LengthAwarePaginator;

    public function buscarPorId(int $id): ?Cliente;

    public function criar(CreateClienteDTO $dto): Cliente;

    public function atualizar(Cliente $cliente, UpdateClienteDTO $dto): bool;

    public function excluir(Cliente $cliente): bool;
}
