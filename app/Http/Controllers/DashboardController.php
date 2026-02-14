<?php

namespace App\Http\Controllers;

use App\Models\Promissoria;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promissoria::class);

        $dias = (int) $request->get('dias', 3);
        $periodo = $request->get('periodo', '30');
        $dataInicio = $request->get('data_inicio');
        $dataFim = $request->get('data_fim');
        $dados = $this->dashboardService->dadosParaGraficos($dias, $periodo, $dataInicio, $dataFim);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $dados,
        ], 200);
    }
}
