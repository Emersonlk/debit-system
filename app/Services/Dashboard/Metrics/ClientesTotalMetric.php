<?php

namespace App\Services\Dashboard\Metrics;

use App\Models\Cliente;
use App\Services\Dashboard\DashboardMetricInterface;

class ClientesTotalMetric implements DashboardMetricInterface
{
    public function key(): string
    {
        return 'clientes';
    }

    public function getData(array $context): array
    {
        return [
            'total' => Cliente::count(),
        ];
    }
}
