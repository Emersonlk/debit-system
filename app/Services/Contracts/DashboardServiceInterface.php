<?php

namespace App\Services\Contracts;

interface DashboardServiceInterface
{
    public function dadosParaGraficos(int $diasResumo = 3, string $periodo = '30', ?string $dataInicio = null, ?string $dataFim = null): array;
}
