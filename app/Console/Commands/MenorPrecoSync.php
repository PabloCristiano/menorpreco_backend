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
         * 🔎 ESTRATÉGIA INTELIGENTE:
         * 
         * 1. Produtos COM GTIN: agrupa por (gtin, ncm, categoria, local)
         * 2. Produtos SEM GTIN: agrupa por (palavrachave, ncm, categoria, local)
         * 
         * Isso garante que TODOS os produtos sejam consultados, mesmo os sem GTIN
         */

        // ═════════════════════════════════════════════════════════
        // GRUPO 1: Produtos COM GTIN
        // ═════════════════════════════════════════════════════════
        $produtosComGTIN = Produto::whereNotNull('gtin')
            ->where('gtin', '<>', '')
            ->select('gtin', 'ncm', 'categoria', 'local')
            ->distinct()
            ->orderBy('gtin')
            ->orderBy('ncm')
            ->get();

        // ═════════════════════════════════════════════════════════
        // GRUPO 2: Produtos SEM GTIN (busca por palavra-chave)
        // ═════════════════════════════════════════════════════════
        $produtosSemGTIN = Produto::where(function($q) {
                $q->whereNull('gtin')
                  ->orWhere('gtin', '');
            })
            ->whereNotNull('palavrachave')
            ->where('palavrachave', '<>', '')
            ->select('palavrachave', 'ncm', 'categoria', 'local')
            ->distinct()
            ->orderBy('palavrachave')
            ->orderBy('ncm')
            ->get();

        $totalGrupos = $produtosComGTIN->count() + $produtosSemGTIN->count();

        if ($totalGrupos === 0) {
            $this->warn('⚠️  Nenhum produto cadastrado para sincronizar');
            return;
        }

        $this->info("📊 GRUPOS IDENTIFICADOS:");
        $this->line("   • Com GTIN: {$produtosComGTIN->count()}");
        $this->line("   • Sem GTIN (palavra-chave): {$produtosSemGTIN->count()}");
        $this->line("   • TOTAL: {$totalGrupos}");
        $this->newLine();

        $progresso = 0;

        // ═════════════════════════════════════════════════════════
        // PROCESSA GRUPO 1: Produtos COM GTIN
        // ═════════════════════════════════════════════════════════
        if ($produtosComGTIN->isNotEmpty()) {
            $this->info('🏷️  PROCESSANDO PRODUTOS COM GTIN');
            $this->newLine();

            foreach ($produtosComGTIN as $grupo) {
                $progresso++;

                $gtin      = $grupo->gtin;
                $ncm       = $grupo->ncm;
                $categoria = $grupo->categoria;
                $local     = $grupo->local;

                $this->line("🔎 [{$progresso}/{$totalGrupos}] GTIN: {$gtin} | NCM: {$ncm} | cat={$categoria}");

                try {
                    $tempoAPIInicio = microtime(true);

                    // 🎯 Busca por GTIN
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

                    $this->line("   ↳ {$response['total']} produtos encontrados (tempo: {$tempoAPI}s)");

                    // Busca produtos monitorados com esse GTIN + NCM
                    $produtosAlvo = Produto::where('gtin', $gtin)
                        ->where('ncm', $ncm)
                        ->where('categoria', $categoria)
                        ->where('local', $local)
                        ->get();

                    $this->line("   ↳ {$produtosAlvo->count()} produto(s) cadastrado(s)");

                    $salvosLote = $this->salvarPrecos($response, $produtosAlvo, $service);
                    
                    $totalProcessados += $salvosLote;
                    $this->line("   ↳ ✅ {$salvosLote} registro(s) salvos");
                    $this->newLine();

                    usleep(200_000); // Respeita API

                } catch (\Throwable $e) {
                    $this->tratarErro($e, "GTIN: {$gtin} | NCM: {$ncm}", $gtin, $ncm, $categoria, $local);
                    $totalErros++;
                }
            }
        }

        // ═════════════════════════════════════════════════════════
        // PROCESSA GRUPO 2: Produtos SEM GTIN (por palavra-chave)
        // ═════════════════════════════════════════════════════════
        if ($produtosSemGTIN->isNotEmpty()) {
            $this->info('🔤 PROCESSANDO PRODUTOS SEM GTIN (BUSCA POR PALAVRA-CHAVE)');
            $this->newLine();

            foreach ($produtosSemGTIN as $grupo) {
                $progresso++;

                $palavrachave = $grupo->palavrachave;
                $ncm          = $grupo->ncm;
                $categoria    = $grupo->categoria;
                $local        = $grupo->local;

                $this->line("🔎 [{$progresso}/{$totalGrupos}] TERMO: '{$palavrachave}' | NCM: {$ncm} | cat={$categoria}");

                try {
                    $tempoAPIInicio = microtime(true);

                    // 🎯 Busca por TERMO (palavra-chave)
                    $response = $service->consultar(
                        termo: $palavrachave,
                        gtin: null,
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

                    $this->line("   ↳ {$response['total']} produtos encontrados (tempo: {$tempoAPI}s)");

                    // Busca produtos monitorados com essa palavra-chave + NCM
                    $produtosAlvo = Produto::where(function($q) {
                            $q->whereNull('gtin')
                              ->orWhere('gtin', '');
                        })
                        ->where('palavrachave', $palavrachave)
                        ->where('ncm', $ncm)
                        ->where('categoria', $categoria)
                        ->where('local', $local)
                        ->get();

                    $this->line("   ↳ {$produtosAlvo->count()} produto(s) cadastrado(s)");

                    $salvosLote = $this->salvarPrecos($response, $produtosAlvo, $service);
                    
                    $totalProcessados += $salvosLote;
                    $this->line("   ↳ ✅ {$salvosLote} registro(s) salvos");
                    $this->newLine();

                    usleep(200_000); // Respeita API

                } catch (\Throwable $e) {
                    $this->tratarErro($e, "TERMO: '{$palavrachave}' | NCM: {$ncm}", null, $ncm, $categoria, $local, $palavrachave);
                    $totalErros++;
                }
            }
        }

        // ═════════════════════════════════════════════════════════
        // RESUMO FINAL
        // ═════════════════════════════════════════════════════════
        $this->exibirResumo(
            $tempoInicio,
            $totalGrupos,
            $totalChamadasAPI,
            $totalProcessados,
            $totalErros,
            $produtosComGTIN->count(),
            $produtosSemGTIN->count()
        );
    }

    /**
     * Salva os preços no banco de dados
     */
    private function salvarPrecos(array $response, $produtosAlvo, MenorPrecoService $service): int
    {
        $salvosLote = 0;

        foreach ($response['produtos'] as $p) {

            if (empty($p['estabelecimento'])) {
                continue;
            }

            foreach ($produtosAlvo as $produto) {

                /** 🎯 Match inteligente */
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
            }
        }

        return $salvosLote;
    }

    /**
     * Trata erros durante o processamento
     */
    private function tratarErro(
        \Throwable $e,
        string $descricao,
        ?string $gtin = null,
        ?string $ncm = null,
        ?int $categoria = null,
        ?string $local = null,
        ?string $palavrachave = null
    ): void {
        $this->error("❌ Erro em: {$descricao}");
        $this->error("   ↳ {$e->getMessage()}");

        Log::error('Erro Menor Preço Sync', [
            'gtin'         => $gtin,
            'ncm'          => $ncm,
            'categoria'    => $categoria,
            'local'        => $local,
            'palavrachave' => $palavrachave,
            'erro'         => $e->getMessage(),
        ]);

        $this->newLine();
    }

    /**
     * Exibe resumo final da sincronização
     */
    private function exibirResumo(
        float $tempoInicio,
        int $totalGrupos,
        int $totalChamadasAPI,
        int $totalProcessados,
        int $totalErros,
        int $qtdComGTIN,
        int $qtdSemGTIN
    ): void {
        $tempoFim = microtime(true);
        $tempoTotal = $tempoFim - $tempoInicio;

        $minutos = floor($tempoTotal / 60);
        $segundos = round($tempoTotal % 60, 2);

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    RESUMO DA SINCRONIZAÇÃO                ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("✅ Status: Finalizado");
        $this->info("⏰ Horário de término: " . now()->format('d/m/Y H:i:s'));
        $this->newLine();

        $this->info("📊 ESTATÍSTICAS:");
        $this->line("   • Grupos processados: {$totalGrupos}");
        $this->line("     └─ Com GTIN: {$qtdComGTIN}");
        $this->line("     └─ Sem GTIN (palavra-chave): {$qtdSemGTIN}");
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
            $this->line("   • Tempo médio por chamada: {$tempoMedioPorChamada}s");
        }

        if ($totalProcessados > 0) {
            $registrosPorMinuto = round(($totalProcessados / $tempoTotal) * 60, 2);
            $this->line("   • Taxa: {$registrosPorMinuto} registros/minuto");
        }

        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        Log::info('Menor Preço Sync Finalizado', [
            'total_grupos'       => $totalGrupos,
            'grupos_com_gtin'    => $qtdComGTIN,
            'grupos_sem_gtin'    => $qtdSemGTIN,
            'total_chamadas_api' => $totalChamadasAPI,
            'total_processados'  => $totalProcessados,
            'total_erros'        => $totalErros,
            'tempo_total_seg'    => round($tempoTotal, 2),
            'tempo_formatado'    => $minutos > 0 ? "{$minutos}min {$segundos}s" : "{$segundos}s",
        ]);
    }
}