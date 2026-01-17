<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
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
    Route::get('/promissorias/resumo/vencimento', [PromissoriaController::class, 'resumoVencimento']);
});