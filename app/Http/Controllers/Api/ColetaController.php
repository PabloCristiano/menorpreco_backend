<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ColetaExecucao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Process\Process;

class ColetaController extends Controller
{
    /**
     * Dispara a coleta (sync) em segundo plano. Não bloqueia a requisição:
     * lança o comando artisan num processo destacado e devolve a execução
     * para a tela acompanhar via polling.
     */
    public function sincronizar(Request $request): JsonResponse
    {
        // Já existe uma coleta em andamento recente? Devolve ela (evita duplicar).
        $emAndamento = ColetaExecucao::where('status', ColetaExecucao::STATUS_RODANDO)
            ->where('iniciado_em', '>=', now()->subMinutes(30))
            ->latest('iniciado_em')
            ->first();

        if ($emAndamento) {
            return response()->json([
                'status'     => 'ok',
                'ja_rodando' => true,
                'execucao'   => $emAndamento,
            ]);
        }

        $execucao = ColetaExecucao::create([
            'gatilho'     => 'manual',
            'status'      => ColetaExecucao::STATUS_RODANDO,
            'iniciado_em' => now(),
        ]);

        $this->dispararEmBackground($execucao->id);

        return response()->json([
            'status'   => 'ok',
            'execucao' => $execucao,
        ], 202);
    }

    /**
     * Estado atual da coleta: última execução + histórico recente.
     */
    public function ultima(Request $request): JsonResponse
    {
        // Marca como interrompidas as rodadas presas em "rodando" há muito tempo.
        ColetaExecucao::where('status', ColetaExecucao::STATUS_RODANDO)
            ->where('iniciado_em', '<', now()->subMinutes(30))
            ->update([
                'status'        => ColetaExecucao::STATUS_ERRO,
                'finalizado_em' => now(),
                'mensagem_erro' => 'Execução interrompida (excedeu o tempo limite).',
            ]);

        return response()->json([
            'status'   => 'ok',
            'ultima'   => ColetaExecucao::latest('iniciado_em')->first(),
            'recentes' => ColetaExecucao::latest('iniciado_em')->limit(10)->get(),
        ]);
    }

    /**
     * Lança `php artisan app:menor-preco-sync` num processo de fundo.
     * O subshell com & faz o run() retornar imediatamente.
     */
    private function dispararEmBackground(int $execucaoId): void
    {
        $php     = PHP_BINARY;
        $artisan = base_path('artisan');
        $log     = storage_path('logs/coleta.log');

        $cmd = sprintf(
            '(%s %s app:menor-preco-sync --execucao=%d --gatilho=manual >> %s 2>&1 &)',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $execucaoId,
            escapeshellarg($log)
        );

        Process::fromShellCommandline($cmd, base_path())->run();
    }
}
