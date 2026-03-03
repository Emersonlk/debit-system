<?php

namespace App\Services\Dashboard\Metrics;

use App\Enums\PromissoriaStatus;
use App\Models\Promissoria;
use App\Services\Dashboard\DashboardMetricInterface;
use Carbon\Carbon;

class UltimosPagamentosMetric implements DashboardMetricInterface
{
    private const LIMIT = 10;

    public function key(): string
    {
        return 'ultimos_pagamentos';
    }

    public function getData(array $context): array
    {
        $promissorias = Promissoria::query()
            ->with('cliente:id,nome')
            ->where('status', PromissoriaStatus::PAGA->value)
            ->whereNotNull('data_pagamento')
            ->orderByDesc('data_pagamento')
            ->limit(self::LIMIT)
            ->get();

        return $promissorias->map(function (Promissoria $p) {
            return [
                'id' => $p->id,
                'cliente_id' => $p->cliente_id,
                'cliente' => $p->cliente?->nome,
                'valor_total_pago' => number_format($p->valor_total_pago, 2, '.', ''),
                'data_pagamento' => $p->data_pagamento instanceof \DateTimeInterface
                    ? $p->data_pagamento->format('Y-m-d')
                    : Carbon::parse($p->data_pagamento)->format('Y-m-d'),
            ];
        })->values()->toArray();
    }
}
