<?php

namespace App\Http\Controllers;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Enums\PromissoriaStatus;
use App\Http\Requests\StorePromissoriaRequest;
use App\Http\Requests\UpdatePromissoriaRequest;
use App\Models\Promissoria;
use App\Services\PromissoriaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromissoriaController extends Controller
{
    public function __construct(
        private PromissoriaService $promissoriaService
    ) {
    }

    /**
     * Lista todas as promissórias com paginação
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        
        $filtros = [];
        if ($request->has('status')) {
            $filtros['status'] = $request->status;
        }
        if ($request->has('cliente_id')) {
            $filtros['cliente_id'] = $request->cliente_id;
        }
        if ($request->has('vencidas')) {
            $filtros['vencidas'] = true;
        }
        if ($request->has('proximas_vencimento')) {
            $filtros['proximas_vencimento'] = true;
            $filtros['dias'] = (int) $request->get('dias', 3);
        }

        $promissorias = $this->promissoriaService->listar($filtros, $perPage);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $promissorias->items(),
            'meta' => [
                'current_page' => $promissorias->currentPage(),
                'per_page' => $promissorias->perPage(),
                'total' => $promissorias->total(),
                'last_page' => $promissorias->lastPage(),
            ]
        ], 200);
    }

    /**
     * Cria uma nova promissória
     */
    public function store(StorePromissoriaRequest $request): JsonResponse
    {
        try {
            $dto = CreatePromissoriaDTO::fromArray($request->validated());
            $promissoria = $this->promissoriaService->criar($dto);

            return response()->json([
                'success' => true,
                'status_code' => 201,
                'message' => 'Promissória criada com sucesso',
                'data' => $promissoria->load('cliente')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao criar promissória',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma promissória específica
     */
    public function show(Promissoria $promissoria): JsonResponse
    {
        // O ModelNotFoundException será tratado automaticamente pelo exception handler
        $promissoria = $this->promissoriaService->buscarPorId($promissoria->id);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $promissoria
        ], 200);
    }

    /**
     * Atualiza uma promissória existente
     */
    public function update(UpdatePromissoriaRequest $request, Promissoria $promissoria): JsonResponse
    {
        try {
            $dto = UpdatePromissoriaDTO::fromArray($request->validated());
            $this->promissoriaService->atualizar($promissoria, $dto);
            $promissoria->refresh();

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória atualizada com sucesso',
                'data' => $promissoria->load('cliente')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao atualizar promissória',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove uma promissória
     */
    public function destroy(Promissoria $promissoria): JsonResponse
    {
        try {
            $this->promissoriaService->excluir($promissoria);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória removida com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao remover promissória',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca uma promissória como paga
     */
    public function marcarComoPaga(Promissoria $promissoria): JsonResponse
    {
        try {
            // Verifica se já está paga antes de chamar o service
            if ($promissoria->status === PromissoriaStatus::PAGA) {
                return response()->json([
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'Esta promissória já está marcada como paga.',
                    'data' => $promissoria->load('cliente')
                ], 422);
            }

            $this->promissoriaService->marcarComoPaga($promissoria);
            $promissoria->refresh();

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória marcada como paga',
                'data' => $promissoria->load('cliente')
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Esta promissória já está marcada como paga.' ? 422 : 500;
            return response()->json([
                'success' => false,
                'status_code' => $statusCode,
                'message' => $e->getMessage() === 'Esta promissória já está marcada como paga.' 
                    ? 'Esta promissória já está marcada como paga.' 
                    : 'Erro ao marcar promissória como paga',
                'error' => $e->getMessage() !== 'Esta promissória já está marcada como paga.' ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Retorna um resumo de promissórias próximas do vencimento
     */
    public function resumoVencimento(Request $request): JsonResponse
    {
        $dias = (int) $request->get('dias', 3);
        $resumo = $this->promissoriaService->obterResumoVencimento($dias);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $resumo
        ], 200);
    }
}
