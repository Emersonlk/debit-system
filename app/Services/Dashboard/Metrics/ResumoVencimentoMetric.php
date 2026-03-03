<?php

namespace App\Services\Dashboard\Metrics;

use App\Services\Contracts\PromissoriaServiceInterface;
use App\Services\Dashboard\DashboardMetricInterface;

class ResumoVencimentoMetric implements DashboardMetricInterface
{
    public function __construct(
        private PromissoriaServiceInterface $promissoriaService
    ) {
    }

    public function key(): string
    {
        return 'resumo_vencimento';
    }

    public function getData(array $context): array
    {
        $diasResumo = $context['dias_resumo'] ?? 3;
        return $this->promissoriaService->obterResumoVencimento($diasResumo);
    }
}
