<?php

namespace App\Services\Dashboard\Metrics;

use App\Enums\PromissoriaStatus;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Services\Dashboard\DashboardMetricInterface;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class RecebimentosPeriodoMetric implements DashboardMetricInterface
{
    public function key(): string
    {
        return 'recebimentos_periodo';
    }

    public function getData(array $context): array
    {
        $inicio = $context['inicio'] ?? null;
        $fim = $context['fim'] ?? null;

        if (!$inicio instanceof Carbon || !$fim instanceof Carbon) {
            return ['valor_total' => '0.00', 'variacao_percentual' => 0, 'serie' => []];
        }

        $porDia = HistoricoPagamento::query()
            ->whereBetween('data_pagamento', [$inicio->format('Y-m-d'), $fim->format('Y-m-d')])
            ->select('data_pagamento', DB::raw('SUM(valor_pago) as total'))
            ->groupBy('data_pagamento')
            ->orderBy('data_pagamento')
            ->pluck('total', 'data_pagamento')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $promissoriasPagasNoPeriodo = Promissoria::query()
            ->where('status', PromissoriaStatus::PAGA->value)
            ->whereNotNull('data_pagamento')
            ->whereBetween(DB::raw('DATE(data_pagamento)'), [$inicio->format('Y-m-d'), $fim->format('Y-m-d')])
            ->whereDoesntHave('historicoPagamentos')
            ->select('data_pagamento', DB::raw('COALESCE(valor_original, valor) as valor'))
            ->get();

        foreach ($promissoriasPagasNoPeriodo as $p) {
            $data = $p->data_pagamento instanceof \DateTimeInterface
                ? $p->data_pagamento->format('Y-m-d')
                : Carbon::parse($p->data_pagamento)->format('Y-m-d');
            $porDia[$data] = ($porDia[$data] ?? 0) + (float) $p->valor;
        }

        $period = CarbonPeriod::create($inicio, $fim);
        $serie = [];
        $valorTotal = 0;
        foreach ($period as $date) {
            $dataStr = $date->format('Y-m-d');
            $valor = $porDia[$dataStr] ?? 0;
            $valorTotal += $valor;
            $serie[] = ['data' => $dataStr, 'valor' => number_format($valor, 2, '.', '')];
        }

        $diasDiff = $inicio->diffInDays($fim) + 1;
        $periodoAnteriorFim = $inicio->copy()->subDay()->endOfDay();
        $periodoAnteriorInicio = $periodoAnteriorFim->copy()->subDays($diasDiff - 1)->startOfDay();
        $anteriorTotal = HistoricoPagamento::query()
            ->whereBetween('data_pagamento', [$periodoAnteriorInicio->format('Y-m-d'), $periodoAnteriorFim->format('Y-m-d')])
            ->sum('valor_pago');
        $promissoriasAnterior = Promissoria::query()
            ->where('status', PromissoriaStatus::PAGA->value)
            ->whereNotNull('data_pagamento')
            ->whereBetween(DB::raw('DATE(data_pagamento)'), [$periodoAnteriorInicio->format('Y-m-d'), $periodoAnteriorFim->format('Y-m-d')])
            ->whereDoesntHave('historicoPagamentos')
            ->sum(DB::raw('COALESCE(valor_original, valor)'));
        $anteriorTotal += (float) $promissoriasAnterior;
        $variacao = $anteriorTotal > 0
            ? round((($valorTotal - $anteriorTotal) / $anteriorTotal) * 100, 2)
            : ($valorTotal > 0 ? 100 : 0);

        return [
            'valor_total' => number_format($valorTotal, 2, '.', ''),
            'variacao_percentual' => $variacao,
            'serie' => $serie,
        ];
    }
}
