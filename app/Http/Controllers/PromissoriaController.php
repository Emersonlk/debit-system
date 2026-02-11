<?php

namespace App\Http\Controllers;

use App\DTOs\CreatePromissoriaDTO;
use App\DTOs\PagamentoParcialDTO;
use App\DTOs\UpdatePromissoriaDTO;
use App\Http\Requests\CancelarPromissoriaRequest;
use App\Http\Requests\PagamentoParcialRequest;
use App\Http\Requests\StorePromissoriaRequest;
use App\Http\Requests\UpdatePromissoriaRequest;
use App\Models\Promissoria;
use App\Services\AuditService;
use App\Services\PromissoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromissoriaController extends Controller
{
    public function __construct(
        private PromissoriaService $promissoriaService,
        private AuditService $auditService
    ) {
    }

    /**
     * Lista todas as promissórias com paginação
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promissoria::class);

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
        if ($request->filled('sort_by')) {
            $filtros['sort_by'] = $request->sort_by;
        }
        if ($request->filled('sort_order')) {
            $filtros['sort_order'] = $request->sort_order;
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

            // Log de auditoria
            $this->auditService->logCreate($promissoria, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 201,
                'message' => 'Promissória criada com sucesso',
                'data' => $promissoria->load('cliente')
            ], 201);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao criar promissória', 500, $e->getMessage());
        }
    }

    /**
     * Exibe uma promissória específica
     */
    public function show(Request $request, Promissoria $promissoria): JsonResponse
    {
        $this->authorize('view', $promissoria);

        $this->auditService->logView($promissoria, Auth::user(), $request);

        $promissoria->load(['cliente', 'historicoPagamentos']);
        $data = $promissoria->toArray();
        $data['valor_total_pago'] = number_format($promissoria->valor_total_pago, 2, '.', '');
        $data['saldo_restante'] = number_format($promissoria->saldo_restante, 2, '.', '');

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $data
        ], 200);
    }

    /**
     * Atualiza uma promissória existente
     */
    public function update(UpdatePromissoriaRequest $request, Promissoria $promissoria): JsonResponse
    {
        try {
            $oldValues = $promissoria->getAttributes();
            $dto = UpdatePromissoriaDTO::fromArray($request->validated());
            $this->promissoriaService->atualizar($promissoria, $dto);
            $promissoria->refresh();

            // Log de auditoria
            $this->auditService->logUpdate($promissoria, $oldValues, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória atualizada com sucesso',
                'data' => $promissoria->load('cliente')
            ], 200);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao atualizar promissória', 500, $e->getMessage());
        }
    }

    /**
     * Remove uma promissória
     */
    public function destroy(Request $request, Promissoria $promissoria): JsonResponse
    {
        $this->authorize('delete', $promissoria);

        try {
            // Log de auditoria antes de deletar
            $this->auditService->logDelete($promissoria, Auth::user(), $request);

            $this->promissoriaService->excluir($promissoria);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória removida com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao remover promissória', 500, $e->getMessage());
        }
    }

    /**
     * Marca uma promissória como paga
     */
    public function marcarComoPaga(Request $request, Promissoria $promissoria): JsonResponse
    {
        $this->authorize('markAsPaid', $promissoria);

        try {
            $oldValues = $promissoria->getAttributes();
            $this->promissoriaService->marcarComoPaga($promissoria);
            $promissoria->refresh();

            $this->auditService->logUpdate($promissoria, $oldValues, Auth::user(), $request);

            $promissoria->load('cliente');
            $data = $promissoria->toArray();
            $data['valor_total_pago'] = number_format($promissoria->valor_total_pago, 2, '.', '');
            $data['saldo_restante'] = number_format($promissoria->saldo_restante, 2, '.', '');

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória marcada como paga',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            $msgJaPaga = 'Esta promissória já está marcada como paga.';
            $statusCode = $e->getMessage() === $msgJaPaga ? 422 : 500;
            $response = [
                'success' => false,
                'status_code' => $statusCode,
                'message' => $statusCode === 422 ? $msgJaPaga : 'Erro ao marcar promissória como paga',
                'error' => $statusCode === 500 ? $e->getMessage() : null
            ];
            if ($statusCode === 422) {
                $response['data'] = $promissoria->load('cliente');
            }
            return $statusCode === 500
                ? $this->responseError('Erro ao marcar promissória como paga', 500, $e->getMessage())
                : response()->json($response, $statusCode);
        }
    }

    /**
     * Retorna um resumo de promissórias próximas do vencimento
     */
    public function resumoVencimento(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promissoria::class);

        $dias = (int) $request->get('dias', 3);
        $resumo = $this->promissoriaService->obterResumoVencimento($dias);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $resumo
        ], 200);
    }

    /**
     * Registra um pagamento parcial na promissória
     */
    public function registrarPagamentoParcial(PagamentoParcialRequest $request, Promissoria $promissoria): JsonResponse
    {
        try {
            $oldValues = $promissoria->getAttributes();
            $dto = PagamentoParcialDTO::fromArray($request->validated());
            $historicoPagamento = $this->promissoriaService->registrarPagamentoParcial($promissoria, $dto);
            $promissoria->refresh();

            // Log de auditoria
            $this->auditService->logUpdate($promissoria, $oldValues, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 201,
                'message' => 'Pagamento parcial registrado com sucesso',
                'data' => [
                    'promissoria' => $promissoria->load(['cliente', 'historicoPagamentos']),
                    'historico_pagamento' => $historicoPagamento,
                    'valor_total_pago' => number_format($promissoria->valor_total_pago, 2, '.', ''),
                    'saldo_restante' => number_format($promissoria->saldo_restante, 2, '.', ''),
                ]
            ], 201);
        } catch (\Exception $e) {
            $mensagensErro422 = [
                'Não é possível registrar pagamento parcial em uma promissória já paga.',
                'Não é possível registrar pagamento parcial em uma promissória cancelada.',
            ];

            // Verifica se a mensagem começa com "O valor do pagamento" (erro de valor excedendo saldo)
            $isValorExcedendoSaldo = str_starts_with($e->getMessage(), 'O valor do pagamento');

            $statusCode = in_array($e->getMessage(), $mensagensErro422) || $isValorExcedendoSaldo ? 422 : 500;

            return response()->json([
                'success' => false,
                'status_code' => $statusCode,
                'message' => $e->getMessage(),
                'error' => $statusCode === 500 ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Cancela uma promissória
     */
    public function cancelar(CancelarPromissoriaRequest $request, Promissoria $promissoria): JsonResponse
    {
        try {
            $oldValues = $promissoria->getAttributes();
            $observacoes = $request->validated()['observacoes'] ?? null;
            $this->promissoriaService->cancelar($promissoria, $observacoes);
            $promissoria->refresh();

            // Log de auditoria
            $this->auditService->logUpdate($promissoria, $oldValues, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Promissória cancelada com sucesso',
                'data' => $promissoria->load('cliente')
            ], 200);
        } catch (\Exception $e) {
            $statusCode = in_array($e->getMessage(), [
                'Não é possível cancelar uma promissória já paga.',
                'Esta promissória já está cancelada.',
            ]) ? 422 : 500;

            return response()->json([
                'success' => false,
                'status_code' => $statusCode,
                'message' => $e->getMessage(),
                'error' => $statusCode === 500 ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Obtém o histórico de pagamentos de uma promissória
     */
    public function historicoPagamentos(Promissoria $promissoria): JsonResponse
    {
        $this->authorize('view', $promissoria);

        $historico = $this->promissoriaService->obterHistoricoPagamentos($promissoria);
        $promissoria->load('cliente');

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => [
                'promissoria' => [
                    'id' => $promissoria->id,
                    'cliente' => $promissoria->cliente->nome,
                    'valor' => number_format($promissoria->valor, 2, '.', ''),
                    'valor_total_pago' => number_format($promissoria->valor_total_pago, 2, '.', ''),
                    'saldo_restante' => number_format($promissoria->saldo_restante, 2, '.', ''),
                    'status' => $promissoria->status->value,
                ],
                'historico_pagamentos' => $historico->map(function ($pagamento) {
                    return [
                        'id' => $pagamento->id,
                        'valor_pago' => number_format($pagamento->valor_pago, 2, '.', ''),
                        'data_pagamento' => $pagamento->data_pagamento->format('Y-m-d'),
                        'observacoes' => $pagamento->observacoes,
                        'created_at' => $pagamento->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            ]
        ], 200);
    }
}
