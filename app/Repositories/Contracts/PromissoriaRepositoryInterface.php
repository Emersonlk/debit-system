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

    // Contagens feitas no banco, para quem só precisa do número: carregar a coleção
    // inteira apenas para chamar count() custava dezenas de MB no caminho em que não
    // havia nada a notificar.
    public function contarVencidas(): int;
    public function contarProximasVencimento(int $dias = 3): int;
    public function contarNaoNotificadasProximasVencimento(int $dias = 3): int;

    /**
     * Percursos em lotes, para processamento que não cabe em memória.
     *
     * As versões find* continuam existindo e são as usadas pelo dashboard, que
     * devolve a lista inteira na resposta.
     *
     * @param  callable(Collection<int, Promissoria>): (bool|void)  $callback
     */
    public function chunkVencidas(int $tamanho, callable $callback): void;

    /** @param  callable(Collection<int, Promissoria>): (bool|void)  $callback */
    public function chunkProximasVencimento(int $dias, int $tamanho, callable $callback): void;

    /** @param  callable(Collection<int, Promissoria>): (bool|void)  $callback */
    public function chunkNaoNotificadasProximasVencimento(int $dias, int $tamanho, callable $callback): void;

    /** @param  list<int>  $ids */
    public function marcarComoNotificadas(array $ids): int;
}
