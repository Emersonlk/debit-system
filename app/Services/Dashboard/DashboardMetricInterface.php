<?php

namespace App\Services\Dashboard;

interface DashboardMetricInterface
{
    /**
     * Chave do bloco no array retornado por dadosParaGraficos (ex: 'clientes', 'promissorias').
     */
    public function key(): string;

    /**
     * Calcula os dados da métrica.
     *
     * @param  array{dias_resumo: int, inicio: \Carbon\Carbon|null, fim: \Carbon\Carbon|null}  $context
     * @return mixed
     */
    public function getData(array $context): mixed;
}
