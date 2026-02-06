<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PromissoriaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Rotas protegidas com token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('clientes', ClienteController::class);
    Route::apiResource('promissorias', PromissoriaController::class);
    
    // Rotas adicionais para promissórias
    Route::post('/promissorias/{promissoria}/marcar-como-paga', [PromissoriaController::class, 'marcarComoPaga']);
    Route::post('/promissorias/{promissoria}/pagamento-parcial', [PromissoriaController::class, 'registrarPagamentoParcial']);
    Route::post('/promissorias/{promissoria}/cancelar', [PromissoriaController::class, 'cancelar']);
    Route::get('/promissorias/{promissoria}/historico-pagamentos', [PromissoriaController::class, 'historicoPagamentos']);
    Route::get('/promissorias/resumo/vencimento', [PromissoriaController::class, 'resumoVencimento']);

    // Rotas de permissões
    Route::get('/permissoes/minhas', [PermissionController::class, 'minhasPermissoes']);
    Route::get('/permissoes/roles', [PermissionController::class, 'roles']);
    Route::get('/permissoes/permissoes', [PermissionController::class, 'permissoes']);
    Route::get('/permissoes/usuarios', [PermissionController::class, 'usuarios']);
    Route::get('/permissoes/usuarios/{usuario}', [PermissionController::class, 'usuario']);
    Route::post('/permissoes/usuarios/{usuario}/role', [PermissionController::class, 'atribuirRole']);
    Route::delete('/permissoes/usuarios/{usuario}/role', [PermissionController::class, 'removerRole']);
    Route::post('/permissoes/usuarios/{usuario}/permissao', [PermissionController::class, 'atribuirPermissao']);
    Route::delete('/permissoes/usuarios/{usuario}/permissao', [PermissionController::class, 'removerPermissao']);
});