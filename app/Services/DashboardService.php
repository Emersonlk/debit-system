<?php

namespace App\Services;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\HistoricoPagamento;
use App\Models\Promissoria;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\PromissoriaServiceInterface;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService implements DashboardServiceInterface
{
    private const SALDO_RESTANTE_SQL = "CASE WHEN promissorias.valor_original IS NOT NULL THEN promissorias.valor ELSE GREATEST(0, promissorias.valor - COALESCE((SELECT SUM(valor_pago) FROM historico_pagamentos WHERE promissoria_id = promissorias.id), 0)) END";

    public function __construct(
        private PromissoriaServiceInterface $promissoriaService
    ) {
    }

    public function dadosParaGraficos(int $diasResumo = 3, string $periodo = '30', ?string $dataInicio = null, ?string $dataFim = null): array
    {
        [$inicio, $fim] = $this->periodoParaDatas($periodo, $dataInicio, $dataFim);
        $cacheKey = 'dashboard.' . $diasResumo . '.' . $periodo . '.' . ($inicio?->format('Y-m-d') ?? '') . '.' . ($fim?->format('Y-m-d') ?? '');

        return Cache::remember($cacheKey, 60, function () use ($diasResumo, $inicio, $fim) {
            return [
                'clientes' => [
                    'total' => Cliente::count(),
                ],
                'promissorias' => [
                    'total' => Promissoria::count(),
                    'por_status' => $this->promissoriasPorStatus(),
                    'valores_totais' => $this->valoresTotaisPorStatus(),
                ],
                'resumo_vencimento' => $this->promissoriaService->obterResumoVencimento($diasResumo),
                'recebimentos_periodo' => $this->recebimentosPeriodo($inicio, $fim),
                'distribuicao_cliente' => $this->distribuicaoCliente(10),
                'maiores_dividas' => $this->maioresDividas(15),
                'ultimos_pagamentos' => $this->ultimosPagamentos(10),
            ];
        });
    }

    private function periodoParaDatas(string $periodo, ?string $dataInicio = null, ?string $dataFim = null): array
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

    private function promissoriasPorStatus(): array
    {
        $rows = Promissoria::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $statusKey = $row->status instanceof PromissoriaStatus ? $row->status->value : (string) $row->status;
            $counts[$statusKey] = (int) $row->total;
        }

        $result = [];
        foreach (PromissoriaStatus::valores() as $status) {
            $result[$status] = $counts[$status] ?? 0;
        }
        return $result;
    }

    private function valoresTotaisPorStatus(): array
    {
        $pendenteVencida = Promissoria::query()
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->selectRaw('status, SUM(' . self::SALDO_RESTANTE_SQL . ') as total')
            ->groupBy('status')
            ->get();

        $pagaCancelada = Promissoria::query()
            ->whereIn('status', [PromissoriaStatus::PAGA->value, PromissoriaStatus::CANCELADA->value])
            ->select('status', DB::raw('SUM(COALESCE(valor_original, valor)) as total'))
            ->groupBy('status')
            ->get();

        $result = [];
        foreach (PromissoriaStatus::valores() as $status) {
            $result[$status] = '0.00';
        }
        foreach ($pendenteVencida as $row) {
            $statusKey = $row->status instanceof PromissoriaStatus ? $row->status->value : (string) $row->status;
            $result[$statusKey] = number_format((float) $row->total, 2, '.', '');
        }
        foreach ($pagaCancelada as $row) {
            $statusKey = $row->status instanceof PromissoriaStatus ? $row->status->value : (string) $row->status;
            $result[$statusKey] = number_format((float) $row->total, 2, '.', '');
        }
        return $result;
    }

    private function recebimentosPeriodo(?Carbon $inicio, ?Carbon $fim): array
    {
        if (!$inicio || !$fim) {
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

    private function distribuicaoCliente(int $limit): array
    {
        $sql = "SELECT promissorias.cliente_id, clientes.nome, SUM(" . self::SALDO_RESTANTE_SQL . ") as valor_a_receber
                FROM promissorias
                JOIN clientes ON clientes.id = promissorias.cliente_id
                WHERE promissorias.status IN (?, ?)
                GROUP BY promissorias.cliente_id, clientes.nome
                HAVING valor_a_receber > 0
                ORDER BY valor_a_receber DESC
                LIMIT " . (int) $limit;

        $rows = DB::select($sql, [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value]);

        return array_map(fn ($row) => [
            'cliente_id' => (int) $row->cliente_id,
            'nome' => $row->nome,
            'valor_a_receber' => number_format((float) $row->valor_a_receber, 2, '.', ''),
        ], $rows);
    }

    private function maioresDividas(int $limit): array
    {
        $promissorias = Promissoria::query()
            ->with('cliente:id,nome')
            ->whereIn('status', [PromissoriaStatus::PENDENTE->value, PromissoriaStatus::VENCIDA->value])
            ->orderByRaw(self::SALDO_RESTANTE_SQL . ' DESC')
            ->limit($limit)
            ->get();

        return $promissorias->map(function (Promissoria $p) {
            return [
                'id' => $p->id,
                'cliente_id' => $p->cliente_id,
                'cliente' => $p->cliente ? $p->cliente->nome : null,
                'valor' => number_format($p->valor_original_total, 2, '.', ''),
                'saldo_restante' => number_format($p->saldo_restante, 2, '.', ''),
                'data_vencimento' => $p->data_vencimento->format('Y-m-d'),
            ];
        })->values()->toArray();
    }

    private function ultimosPagamentos(int $limit): array
    {
        $promissorias = Promissoria::query()
            ->with('cliente:id,nome')
            ->where('status', PromissoriaStatus::PAGA->value)
            ->whereNotNull('data_pagamento')
            ->orderByDesc('data_pagamento')
            ->limit($limit)
            ->get();

        return $promissorias->map(function (Promissoria $p) {
            return [
                'id' => $p->id,
                'cliente_id' => $p->cliente_id,
                'cliente' => $p->cliente ? $p->cliente->nome : null,
                'valor_total_pago' => number_format($p->valor_total_pago, 2, '.', ''),
                'data_pagamento' => $p->data_pagamento instanceof \DateTimeInterface
                    ? $p->data_pagamento->format('Y-m-d')
                    : Carbon::parse($p->data_pagamento)->format('Y-m-d'),
            ];
        })->values()->toArray();
    }
}
