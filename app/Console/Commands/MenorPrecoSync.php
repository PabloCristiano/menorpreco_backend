<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MenorPrecoService;
use App\Models\MenorprecoProduto as Produto;
use App\Models\MenorprecoHistoricoPreco as HistoricoPreco;
use App\Models\MenorprecoEstabelecimento as Estabelecimento;

class MenorPrecoSync extends Command
{
    protected $signature = 'app:menor-preco-sync';

    protected $description = 'Sincroniza preços do Menor Preço usando categoria e local do cadastro';

    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // ⏱️ INICIA CONTAGEM DE TEMPO
        $tempoInicio = microtime(true);

        $this->info('🚀 Iniciando sincronização Menor Preço');
        $this->info('⏰ Horário de início: ' . now()->format('d/m/Y H:i:s'));
        $this->newLine();

        $service = app(MenorPrecoService::class);

        $totalProcessados = 0;
        $totalChamadasAPI = 0;
        $totalErros = 0;

        /**
         * 🔎 Busca GTINs distintos (independente do NCM)
         * Para cada GTIN faz UMA chamada na API que retorna TODOS os produtos daquele GTIN
         */
        $gtinsDistintos = Produto::whereNotNull('gtin')
            ->where('gtin', '<>', '')
            ->select('gtin', 'categoria', 'local')
            ->distinct()
            ->orderBy('gtin')
            ->get();

        if ($gtinsDistintos->isEmpty()) {
            $this->warn('⚠️  Nenhum produto com GTIN cadastrado');
            return;
        }

        $this->info("📊 Total de GTINs distintos: {$gtinsDistintos->count()}");
        $this->newLine();

        foreach ($gtinsDistintos as $index => $grupo) {

            $gtin      = $grupo->gtin;
            $categoria = $grupo->categoria;
            $local     = $grupo->local;

            $progresso = $index + 1;
            $this->line("🔎 [{$progresso}/{$gtinsDistintos->count()}] GTIN: {$gtin} | cat={$categoria} | local={$local}");

            try {
                // ⏱️ Tempo individual da chamada API
                $tempoAPIInicio = microtime(true);

                // 🎯 UMA única chamada por GTIN (retorna TODOS os produtos daquele GTIN)
                $response = $service->consultar(
                    termo: null,
                    gtin: $gtin,
                    local: $local,
                    categoria: $categoria
                );

                $tempoAPIFim = microtime(true);
                $tempoAPI = round($tempoAPIFim - $tempoAPIInicio, 2);
                $totalChamadasAPI++;

                if (empty($response['produtos'] ?? [])) {
                    $this->line("   ↳ Nenhum produto retornado (tempo: {$tempoAPI}s)");
                    continue;
                }

                $this->line("   ↳ {$response['total']} produtos encontrados na API (tempo: {$tempoAPI}s)");

                /**
                 * Busca TODOS os produtos monitorados com esse GTIN
                 * (podem ter NCMs diferentes)
                 */
                $produtosAlvo = Produto::where('gtin', $gtin)
                    ->where('categoria', $categoria)
                    ->where('local', $local)
                    ->get();

                $this->line("   ↳ {$produtosAlvo->count()} produtos cadastrados para monitorar");

                $salvosLote = 0;

                foreach ($response['produtos'] as $p) {

                    if (empty($p['estabelecimento'])) {
                        continue;
                    }

                    // Para cada produto da API, verifica se bate com algum produto monitorado
                    foreach ($produtosAlvo as $produto) {

                        /** 🎯 Match inteligente por GTIN + NCM */
                        if (!$service->ehProdutoAlvo($p, $produto)) {
                            continue;
                        }

                        /** ------------ ESTABELECIMENTO ------------ */
                        $est = $p['estabelecimento'];

                        $estabelecimento = Estabelecimento::updateOrCreate(
                            ['codigo' => $est['codigo']],
                            [
                                'nome_fantasia' => $est['nm_fan'] ?? null,
                                'razao_social'  => $est['nm_emp'] ?? null,
                                'bairro'        => $est['bairro'] ?? null,
                                'cidade'        => $est['mun'] ?? null,
                                'uf'            => $est['uf'] ?? null,
                                'tp_logr'       => $est['tp_logr'] ?? null,
                                'nm_logr'       => $est['nm_logr'] ?? null,
                                'nr_logr'       => $est['nr_logr'] ?? null,
                            ]
                        );

                        /** ---------------- HISTÓRICO ---------------- */
                        HistoricoPreco::updateOrCreate(
                            [
                                'produto_id'         => $produto->id,
                                'estabelecimento_id' => $estabelecimento->id,
                                'data_coleta'        => now()->toDateString(),
                            ],
                            [
                                'preco'         => $p['valor'] ?? null,
                                'preco_tabela'  => $p['valor_tabela'] ?? null,
                                'desconto'      => $p['valor_desconto'] ?? null,
                                'distancia_km'  => $p['distkm'] ?? null,
                                'datahora_nota' => $p['datahora'] ?? null,
                            ]
                        );

                        $salvosLote++;
                        $totalProcessados++;
                    }
                }

                $this->line("   ↳ ✅ {$salvosLote} registros de preços salvos");
                $this->newLine();

                // respeita API
                usleep(200_000);

            } catch (\Throwable $e) {

                $this->error("❌ Erro no GTIN: {$gtin}");
                $this->error("   ↳ {$e->getMessage()}");
                $totalErros++;

                Log::error('Erro Menor Preço Sync', [
                    'gtin'      => $gtin,
                    'categoria' => $categoria,
                    'local'     => $local,
                    'erro'      => $e->getMessage(),
                ]);

                $this->newLine();
            }
        }

        // ⏱️ FINALIZA CONTAGEM DE TEMPO
        $tempoFim = microtime(true);
        $tempoTotal = $tempoFim - $tempoInicio;

        // Formata o tempo
        $minutos = floor($tempoTotal / 60);
        $segundos = round($tempoTotal % 60, 2);

        // ═══════════════════════════════════════════════════════════
        // RESUMO FINAL
        // ═══════════════════════════════════════════════════════════
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    RESUMO DA SINCRONIZAÇÃO                ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("✅ Status: Finalizado");
        $this->info("⏰ Horário de término: " . now()->format('d/m/Y H:i:s'));
        $this->newLine();

        $this->info("📊 ESTATÍSTICAS:");
        $this->line("   • GTINs processados: {$gtinsDistintos->count()}");
        $this->line("   • Chamadas à API: {$totalChamadasAPI}");
        $this->line("   • Registros salvos: {$totalProcessados}");
        $this->line("   • Erros encontrados: {$totalErros}");
        $this->newLine();

        $this->info("⏱️  TEMPO DE EXECUÇÃO:");
        if ($minutos > 0) {
            $this->line("   • Tempo total: {$minutos}min {$segundos}s");
        } else {
            $this->line("   • Tempo total: {$segundos}s");
        }

        if ($totalChamadasAPI > 0) {
            $tempoMedioPorChamada = round($tempoTotal / $totalChamadasAPI, 2);
            $this->line("   • Tempo médio por GTIN: {$tempoMedioPorChamada}s");
        }

        if ($totalProcessados > 0) {
            $registrosPorMinuto = round(($totalProcessados / $tempoTotal) * 60, 2);
            $this->line("   • Taxa: {$registrosPorMinuto} registros/minuto");
        }

        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Log final
        Log::info('Menor Preço Sync Finalizado', [
            'total_gtins'        => $gtinsDistintos->count(),
            'total_chamadas_api' => $totalChamadasAPI,
            'total_processados'  => $totalProcessados,
            'total_erros'        => $totalErros,
            'tempo_total_seg'    => round($tempoTotal, 2),
            'tempo_formatado'    => $minutos > 0 ? "{$minutos}min {$segundos}s" : "{$segundos}s",
        ]);
    }
}
