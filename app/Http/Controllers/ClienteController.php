<?php

namespace App\Http\Controllers;

use App\DTOs\CreateClienteDTO;
use App\DTOs\UpdateClienteDTO;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\ClienteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClienteController extends Controller
{
    public function __construct(
        private ClienteServiceInterface $clienteService,
        private AuditServiceInterface $auditService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by');
        $sortOrder = $request->get('sort_order', 'asc');
        $clientes = $this->clienteService->listar($perPage, $search, $sortBy, $sortOrder);

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

    public function store(StoreClienteRequest $request): JsonResponse
    {
        try {
            $dto = CreateClienteDTO::fromArray($request->validated());
            $cliente = $this->clienteService->criar($dto);
            $this->auditService->logCreate($cliente, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 201,
                'message' => 'Cliente criado com sucesso',
                'data' => $cliente->load('endereco')
            ], 201);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao criar cliente', 500, $e->getMessage());
        }
    }

    public function show(Request $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->auditService->logView($cliente, Auth::user(), $request);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => $cliente->load('endereco')
        ], 200);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        try {
            $oldValues = $cliente->getAttributes();
            $dto = UpdateClienteDTO::fromArray($request->validated());
            $this->clienteService->atualizar($cliente, $dto);
            $cliente->refresh();
            $this->auditService->logUpdate($cliente, $oldValues, Auth::user(), $request);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Cliente atualizado com sucesso',
                'data' => $cliente->load('endereco')
            ], 200);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao atualizar cliente', 500, $e->getMessage());
        }
    }

    public function destroy(Request $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('delete', $cliente);

        if ($cliente->promissorias()->exists()) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => 'Não é possível excluir um cliente que possui promissórias. Cancele ou remova as promissórias antes.',
            ], 422);
        }

        try {
            $this->auditService->logDelete($cliente, Auth::user(), $request);

            $this->clienteService->excluir($cliente);

            return response()->json([
                'success' => true,
                'status_code' => 200,
                'message' => 'Cliente removido com sucesso'
            ], 200);
        } catch (\Exception $e) {
            return $this->responseError('Erro ao remover cliente', 500, $e->getMessage());
        }
    }
}
