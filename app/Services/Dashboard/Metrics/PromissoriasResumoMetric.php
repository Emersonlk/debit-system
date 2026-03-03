<?php

namespace App\Services\Dashboard\Metrics;

use App\Enums\PromissoriaStatus;
use App\Models\Promissoria;
use App\Services\Dashboard\DashboardMetricInterface;
use App\Services\Dashboard\SaldoRestanteSql;
use Illuminate\Support\Facades\DB;

class PromissoriasResumoMetric implements DashboardMetricInterface
{
    public function key(): string
    {
        return 'promissorias';
    }

    public function getData(array $context): array
    {
        return [
            'total' => Promissoria::count(),
            'por_status' => $this->promissoriasPorStatus(),
            'valores_totais' => $this->valoresTotaisPorStatus(),
        ];
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
            ->selectRaw('status, SUM(' . SaldoRestanteSql::SQL . ') as total')
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
}
