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

        $this->info('🚀 Iniciando sincronização Menor Preço');

        $service = app(MenorPrecoService::class);

        $totalProcessados = 0;

        /**
         * 🔎 Combinações distintas de termo + categoria + local
         */
        Produto::whereNotNull('palavrachave')
            ->where('palavrachave', '<>', '')
            ->select('palavrachave', 'categoria', 'local')
            ->distinct()
            ->orderBy('palavrachave')
            ->chunk(20, function ($grupos) use (
                $service,
                &$totalProcessados
            ) {

                $this->line("📦 Lote ({$grupos->count()})");

                foreach ($grupos as $grupo) {

                    $termo     = $grupo->palavrachave;
                    $categoria = $grupo->categoria;
                    $local     = $grupo->local;

                    $this->line("🔎 {$termo} | cat={$categoria} | local={$local}");

                    try {
                        $response = $service->consultar(
                            $termo,
                            $local,
                            $categoria
                        );

                        if (empty($response['produtos'] ?? [])) {
                            $this->line('   ↳ Nenhum produto retornado');
                            continue;
                        }

                        /** Produtos monitorados desse grupo */
                        $produtosAlvo = Produto::where('palavrachave', $termo)
                            ->where('categoria', $categoria)
                            ->where('local', $local)
                            ->get();

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

                                $totalProcessados++;
                            }
                        }

                        // respeita API
                        usleep(200_000);

                    } catch (\Throwable $e) {

                        $this->error("❌ Erro em {$termo}");

                        Log::error('Erro Menor Preço Sync', [
                            'termo'     => $termo,
                            'categoria' => $categoria,
                            'local'     => $local,
                            'erro'      => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("✅ Sincronização finalizada | Registros processados: {$totalProcessados}");
    }
}
