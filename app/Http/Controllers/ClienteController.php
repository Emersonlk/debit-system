<?php

namespace App\Http\Controllers;

use App\DTOs\CreateClienteDTO;
use App\DTOs\UpdateClienteDTO;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function __construct(
        private ClienteService $clienteService
    ) {
    }

    /**
     * Lista todos os clientes com paginação
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $clientes = $this->clienteService->listar($perPage);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $clientes->items(),
            'meta' => [
                'current_page' => $clientes->currentPage(),
                'per_page' => $clientes->perPage(),
                'total' => $clientes->total(),
                'last_page' => $clientes->lastPage(),
            ]
        ], 200);
    }

    /**
     * Cria um novo cliente
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        try {
            $dto = CreateClienteDTO::fromArray($request->validated());
            $cliente = $this->clienteService->criar($dto);

            return response()->json([
                'success' => true,
                'status_code' => 201,
                'message' => 'Cliente criado com sucesso',
                'data' => $cliente
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao criar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um cliente específico
     */
    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $cliente
        ], 200);
    }

    /**
     * Atualiza um cliente existente
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        try {
            $dto = UpdateClienteDTO::fromArray($request->validated());
            $this->clienteService->atualizar($cliente, $dto);
            $cliente->refresh();

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Cliente atualizado com sucesso',
                'data' => $cliente
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao atualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um cliente
     */
    public function destroy(Cliente $cliente): JsonResponse
    {
        try {
            $this->clienteService->excluir($cliente);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Cliente removido com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Erro ao remover cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
