<?php

namespace App\Services\Dashboard\Metrics;

use App\Enums\PromissoriaStatus;
use App\Models\Promissoria;
use App\Services\Dashboard\DashboardMetricInterface;
use App\Services\Dashboard\SaldoRestanteSql;

class MaioresDividasMetric implements DashboardMetricInterface
{
    private const LIMIT = 15;

    public function key(): string
    {
        return 'maiores_dividas';
    }

    public function getData(array $context): array
    {
        $promissorias = Promissoria::query()
            ->with('cliente:id,nome')
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->orderByRaw(SaldoRestanteSql::SQL . ' DESC')
            ->limit(self::LIMIT)
            ->get();

        return $promissorias->map(function (Promissoria $p) {
            return [
                'id' => $p->id,
                'cliente_id' => $p->cliente_id,
                'cliente' => $p->cliente?->nome,
                'valor' => number_format($p->valor_original_total, 2, '.', ''),
                'saldo_restante' => number_format($p->saldo_restante, 2, '.', ''),
                'data_vencimento' => $p->data_vencimento->format('Y-m-d'),
            ];
        })->values()->toArray();
    }
}
