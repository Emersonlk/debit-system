<?php

namespace App\Services\Contracts;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\PagamentoParcialDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PromissoriaServiceInterface
{
    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    public function buscarPorId(int $id): ?Promissoria;

    public function criar(CreatePromissoriaDTO $dto): Promissoria;

    public function atualizar(Promissoria $promissoria, UpdatePromissoriaDTO $dto): bool;

    public function excluir(Promissoria $promissoria): bool;

    public function marcarComoPaga(Promissoria $promissoria): bool;

    public function obterProximasVencimento(int $dias = 3): Collection;

    public function obterVencidas(): Collection;

    public function obterResumoVencimento(int $dias = 3): array;

    public function registrarPagamentoParcial(Promissoria $promissoria, PagamentoParcialDTO $dto): HistoricoPagamento;

    public function cancelar(Promissoria $promissoria, ?string $observacoes = null): bool;

    public function obterHistoricoPagamentos(Promissoria $promissoria): Collection;
}
