<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;

class DashboardPeriodResolver
{
    /**
     * Converte parâmetros de período (periodo, dataInicio, dataFim) em intervalo [inicio, fim].
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public function resolve(string $periodo, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $hoje = Carbon::today();

        if ($periodo === 'personalizado' && $dataInicio && $dataFim) {
            try {
                $inicio = Carbon::parse($dataInicio)->startOfDay();
                $fim = Carbon::parse($dataFim)->endOfDay();
                if ($inicio->lte($fim)) {
                    return [$inicio, $fim];
                }
            } catch (\Throwable) {
            }
        }

        if ($periodo === 'hoje') {
            return [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()];
        }

        if ($periodo === '7') {
            $inicio = $hoje->copy()->subDays(6)->startOfDay();
            return [$inicio, $hoje->copy()->endOfDay()];
        }

        $inicio = $hoje->copy()->subDays(29)->startOfDay();
        return [$inicio, $hoje->copy()->endOfDay()];
    }
}
