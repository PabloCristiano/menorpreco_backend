<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenorPrecoController;
use App\Http\Controllers\Api\MinhaListaController;

// Rotas públicas (sem autenticação)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

# Consulta ao vivo na API do Nota Paraná
Route::get('/menorpreco/categorias', [MenorPrecoController::class, 'categorias']);
Route::get('/menorpreco/consultar', [MenorPrecoController::class, 'consultar']);
Route::get('/menorpreco/salvar', [MenorPrecoController::class, 'consultarESalvar']);

# Produtos monitorados (dados salvos no banco)
Route::get('/menorpreco/produtos', [MenorPrecoController::class, 'produtos']);
Route::get('/menorpreco/produtos/{produtoId}/historico', [MenorPrecoController::class, 'historico']);
Route::get('/menorpreco/produtos/{produtoId}/estatisticas', [MenorPrecoController::class, 'estatisticas']);
Route::get('/menorpreco/produtos/{produtoId}/menor-preco', [MenorPrecoController::class, 'menorPrecoAtual']);

// Rotas protegidas (com autenticação)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    # Minha lista (por usuário): produtos, mercados e comparação
    Route::prefix('minha-lista')->group(function () {
        Route::get('/produtos',         [MinhaListaController::class, 'produtos']);
        Route::post('/produtos',        [MinhaListaController::class, 'adicionarProduto']);
        Route::delete('/produtos/{id}', [MinhaListaController::class, 'removerProduto']);

        Route::get('/estabelecimentos',         [MinhaListaController::class, 'estabelecimentos']);
        Route::post('/estabelecimentos',        [MinhaListaController::class, 'adicionarEstabelecimento']);
        Route::patch('/estabelecimentos/{id}',  [MinhaListaController::class, 'atualizarEstabelecimento']);
        Route::delete('/estabelecimentos/{id}', [MinhaListaController::class, 'removerEstabelecimento']);

        Route::get('/comparacao',  [MinhaListaController::class, 'comparacao']);
        Route::post('/atualizar',  [MinhaListaController::class, 'atualizar']);
    });
});
