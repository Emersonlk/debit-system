<?php

namespace App\Services;

use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Dashboard\DashboardMetricInterface;
use App\Services\Dashboard\DashboardPeriodResolver;
use Illuminate\Support\Facades\Cache;

class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private DashboardPeriodResolver $periodResolver,
        private iterable $metrics
    ) {
    }

    public function dadosParaGraficos(int $diasResumo = 3, string $periodo = '30', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        [$inicio, $fim] = $this->periodResolver->resolve($periodo, $dataInicio, $dataFim);
        $cacheKey = 'dashboard.' . $diasResumo . '.' . $periodo . '.' . ($inicio?->format('Y-m-d') ?? '') . '.' . ($fim?->format('Y-m-d') ?? '');

        return Cache::remember($cacheKey, 60, function () use ($diasResumo, $inicio, $fim) {
            $context = [
                'dias_resumo' => $diasResumo,
                'inicio' => $inicio,
                'fim' => $fim,
            ];

            $data = [];
            foreach ($this->metrics as $metric) {
                if ($metric instanceof DashboardMetricInterface) {
                    $data[$metric->key()] = $metric->getData($context);
                }
            }
            return $data;
        });
    }
}
