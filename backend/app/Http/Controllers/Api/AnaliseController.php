<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenorprecoProduto;
use App\Models\MenorprecoEstabelecimento;
use App\Models\MenorprecoHistoricoPreco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnaliseController extends Controller
{
    /**
     * Visão geral: KPIs, cobertura e rankings de maiores altas/baixas
     * no período (em dias).
     */
    public function resumo(Request $request): JsonResponse
    {
        $dias   = $this->dias($request);
        $inicio = now()->subDays($dias)->toDateString();

        // Série do menor preço por produto/dia no período
        $porProduto = MenorprecoHistoricoPreco::where('data_coleta', '>=', $inicio)
            ->selectRaw('produto_id, data_coleta, MIN(preco) AS min_preco')
            ->groupBy('produto_id', 'data_coleta')
            ->orderBy('data_coleta')
            ->get()
            ->groupBy('produto_id');

        $variacoes = [];
        foreach ($porProduto as $produtoId => $serie) {
            if ($serie->count() < 2) {
                continue; // precisa de ao menos 2 pontos para haver variação
            }
            $inicial = (float) $serie->first()->min_preco;
            $atual   = (float) $serie->last()->min_preco;
            if ($inicial <= 0) {
                continue;
            }
            $variacoes[] = [
                'produto_id'    => (int) $produtoId,
                'preco_inicial' => $inicial,
                'preco_atual'   => $atual,
                'variacao_abs'  => round($atual - $inicial, 2),
                'variacao_pct'  => round((($atual - $inicial) / $inicial) * 100, 2),
            ];
        }

        // Anexa dados do produto
        $produtos = MenorprecoProduto::whereIn('id', array_column($variacoes, 'produto_id'))
            ->get()
            ->keyBy('id');
        foreach ($variacoes as &$v) {
            $v['produto'] = $produtos[$v['produto_id']] ?? null;
        }
        unset($v);

        $colecao = collect($variacoes);
        $cobertura = MenorprecoHistoricoPreco::where('data_coleta', '>=', $inicio)
            ->selectRaw('MIN(data_coleta) AS inicio, MAX(data_coleta) AS fim, COUNT(DISTINCT data_coleta) AS dias_com_dados')
            ->first();

        return response()->json([
            'status'  => 'ok',
            'periodo' => [
                'dias'           => $dias,
                'inicio'         => $cobertura->inicio,
                'fim'            => $cobertura->fim,
                'dias_com_dados' => (int) $cobertura->dias_com_dados,
            ],
            'totais' => [
                'produtos'         => MenorprecoProduto::count(),
                'estabelecimentos' => MenorprecoEstabelecimento::count(),
                'registros'        => MenorprecoHistoricoPreco::where('data_coleta', '>=', $inicio)->count(),
                'produtos_com_variacao' => $colecao->count(),
            ],
            'variacao_media_pct' => $colecao->count() ? round($colecao->avg('variacao_pct'), 2) : 0,
            'maiores_altas'      => $colecao->sortByDesc('variacao_pct')->take(5)->values(),
            'maiores_baixas'     => $colecao->sortBy('variacao_pct')->take(5)->values(),
        ]);
    }

    /**
     * Análise detalhada de um produto: série diária (mín/média/máx) e
     * resumo de variação no período.
     */
    public function produto(Request $request, int $id): JsonResponse
    {
        $dias    = $this->dias($request);
        $inicio  = now()->subDays($dias)->toDateString();
        $produto = MenorprecoProduto::findOrFail($id);

        $serie = MenorprecoHistoricoPreco::where('produto_id', $id)
            ->where('data_coleta', '>=', $inicio)
            ->selectRaw('data_coleta, MIN(preco) AS min, AVG(preco) AS media, MAX(preco) AS max, COUNT(*) AS ofertas')
            ->groupBy('data_coleta')
            ->orderBy('data_coleta')
            ->get();

        $resumo = null;
        if ($serie->count() >= 1) {
            $inicial = (float) $serie->first()->min;
            $atual   = (float) $serie->last()->min;
            $pct     = $inicial > 0 ? (($atual - $inicial) / $inicial) * 100 : 0;

            $resumo = [
                'preco_inicial' => $inicial,
                'preco_atual'   => $atual,
                'variacao_abs'  => round($atual - $inicial, 2),
                'variacao_pct'  => round($pct, 2),
                'min_periodo'   => (float) $serie->min('min'),
                'max_periodo'   => (float) $serie->max('max'),
                'tendencia'     => $pct > 1 ? 'subiu' : ($pct < -1 ? 'baixou' : 'estavel'),
                'dias_com_dados' => $serie->count(),
            ];
        }

        return response()->json([
            'status'  => 'ok',
            'produto' => $produto,
            'dias'    => $dias,
            'serie'   => $serie,
            'resumo'  => $resumo,
        ]);
    }

    private function dias(Request $request): int
    {
        $dias = (int) $request->query('dias', 30);
        return in_array($dias, [7, 30, 90, 180], true) ? $dias : 30;
    }
}
