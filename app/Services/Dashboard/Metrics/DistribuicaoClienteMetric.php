<?php

namespace App\Services\Dashboard\Metrics;

use App\Enums\PromissoriaStatus;
use App\Services\Dashboard\DashboardMetricInterface;
use App\Services\Dashboard\SaldoRestanteSql;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;

class DistribuicaoClienteMetric implements DashboardMetricInterface
{
    private const LIMIT = 10;

    public function __construct(
        private CurrentCompany $currentCompany
    ) {
    }

    public function key(): string
    {
        return 'distribuicao_cliente';
    }

    public function getData(array $context): array
    {
        // Esta é a única métrica em SQL bruto: o global scope do Eloquent não a alcança,
        // então o filtro por empresa é explícito e acontece na própria consulta.
        $sql = "SELECT promissorias.cliente_id, clientes.nome, SUM(" . SaldoRestanteSql::SQL . ") as valor_a_receber
                FROM promissorias
                JOIN clientes ON clientes.id = promissorias.cliente_id
                WHERE promissorias.company_id = ?
                  AND promissorias.status IN (?, ?)
                GROUP BY promissorias.cliente_id, clientes.nome
                HAVING valor_a_receber > 0
                ORDER BY valor_a_receber DESC
                LIMIT " . self::LIMIT;

        $rows = DB::select($sql, [
            $this->currentCompany->id(),
            PromissoriaStatus::PENDENTE->value,
            PromissoriaStatus::VENCIDA->value,
        ]);

        return array_map(fn ($row) => [
            'cliente_id' => (int) $row->cliente_id,
            'nome' => $row->nome,
            'valor_a_receber' => number_format((float) $row->valor_a_receber, 2, '.', ''),
        ], $rows);
    }
}
