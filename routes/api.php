<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenorPrecoController;

// Rotas públicas (sem autenticação)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

# Rota pública para listar blocos de conteúdo
Route::get('/menorpreco/consultar', [MenorPrecoController::class, 'consultar']);
Route::get('/menorpreco/salvar', [MenorPrecoController::class, 'consultarESalvar']);

// Rotas protegidas (com autenticação)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
