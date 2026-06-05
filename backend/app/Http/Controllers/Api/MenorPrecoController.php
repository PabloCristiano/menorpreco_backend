<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\MenorPrecoService;
use App\Models\MenorprecoProduto;
use App\Models\MenorprecoEstabelecimento;
use App\Models\MenorprecoHistoricoPreco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MenorPrecoController extends Controller
{
    /**
     * Lista os produtos monitorados com o menor preço atual de cada um.
     * Usado pelo dashboard do frontend.
     */
    public function produtos(Request $request): JsonResponse
    {
        $busca = $request->query('q');

        $produtos = MenorprecoProduto::query()
            ->when($busca, function ($query) use ($busca) {
                $query->where(function ($q) use ($busca) {
                    $q->where('descricao', 'like', "%{$busca}%")
                      ->orWhere('palavrachave', 'like', "%{$busca}%")
                      ->orWhere('gtin', 'like', "%{$busca}%");
                });
            })
            ->orderBy('descricao')
            ->get();

        $dados = $produtos->map(function (MenorprecoProduto $produto) {
            $ultimaData = MenorprecoHistoricoPreco::where('produto_id', $produto->id)
                ->max('data_coleta');

            $menorPreco = null;
            $totalOfertas = 0;

            if ($ultimaData) {
                $menorPreco = MenorprecoHistoricoPreco::with('estabelecimento')
                    ->where('produto_id', $produto->id)
                    ->where('data_coleta', $ultimaData)
                    ->orderBy('preco')
                    ->first();

                $totalOfertas = MenorprecoHistoricoPreco::where('produto_id', $produto->id)
                    ->where('data_coleta', $ultimaData)
                    ->count();
            }

            return [
                'produto'       => $produto,
                'ultima_coleta' => $ultimaData,
                'menor_preco'   => $menorPreco,
                'total_ofertas' => $totalOfertas,
            ];
        });

        return response()->json([
            'status' => 'ok',
            'total'  => $dados->count(),
            'data'   => $dados,
        ]);
    }

    /**
     *  Lista histórico de preços de um produto
     */
    public function historico(int $produtoId)
    {
        $produto = MenorprecoProduto::findOrFail($produtoId);

        $historico = MenorprecoHistoricoPreco::with('estabelecimento')
            ->where('produto_id', $produto->id)
            ->orderByDesc('data_coleta')
            ->orderBy('preco')
            ->get();

        return response()->json([
            'produto'   => $produto,
            'historico' => $historico
        ]);
    }

    /**
     *  Estatísticas diárias do produto
     */
    public function estatisticas(int $produtoId)
    {
        $produto = MenorprecoProduto::findOrFail($produtoId);

        $dados = MenorprecoHistoricoPreco::where('produto_id', $produto->id)
            ->selectRaw('
                data_coleta,
                MIN(preco) AS preco_min,
                MAX(preco) AS preco_max,
                AVG(preco) AS preco_medio,
                COUNT(*) AS ofertas
            ')
            ->groupBy('data_coleta')
            ->orderByDesc('data_coleta')
            ->get();

        return response()->json([
            'produto' => $produto,
            'dados'   => $dados
        ]);
    }

    /**
     * Menor preço atual (última coleta)
     */
    public function menorPrecoAtual(int $produtoId)
    {
        $produto = MenorprecoProduto::findOrFail($produtoId);

        $ultimaData = MenorprecoHistoricoPreco::where('produto_id', $produto->id)
            ->max('data_coleta');

        $menorPreco = MenorprecoHistoricoPreco::with('estabelecimento')
            ->where('produto_id', $produto->id)
            ->where('data_coleta', $ultimaData)
            ->orderBy('preco')
            ->first();

        return response()->json([
            'produto'     => $produto,
            'data'        => $ultimaData,
            'menor_preco' => $menorPreco
        ]);
    }

    /**
     * Lista as categorias relevantes para um termo (passo 1 da busca).
     */
    public function categorias(MenorPrecoService $service, Request $request): JsonResponse
    {
        $termo = $request->query('termo', '');
        $gtin  = $request->query('gtin', '');
        $local = $request->query('local', config('menorpreco.local_cascavel'));
        $raio  = (int) $request->query('raio', 50);

        if (empty($termo) && empty($gtin)) {
            return response()->json([
                'status' => 'erro',
                'msg'    => 'Informe termo ou gtin para busca',
            ], 400);
        }

        $response = $service->categorias(
            termo: $termo,
            gtin: $gtin,
            local: $local,
            raio: $raio
        );

        return response()->json([
            'status'     => 'ok',
            'termo'      => $response['termo'] ?? $termo,
            'local'      => $response['local'] ?? $local,
            'categorias' => $response['categorias'] ?? [],
        ]);
    }

    /**
     * Consulta API Menor Preço (passo 2 da busca: produtos de uma categoria).
     */
    public function consultar(MenorPrecoService $service, Request $request): JsonResponse
    {
        $termo     = $request->query('termo', '');
        $gtin      = $request->query('gtin', '');
        $local     = $request->query('local', config('menorpreco.local_cascavel'));
        $categoria = (int) $request->query('categoria', 0);
        $raio      = (int) $request->query('raio', 50);
        $offset    = (int) $request->query('offset', 0);
        $ordem     = (int) $request->query('ordem', 0);

        $response = $service->consultar(
            termo: $termo,
            gtin: $gtin,
            local: $local,
            categoria: $categoria,
            offset: $offset,
            raio: $raio,
            ordem: $ordem
        );

        return response()->json([
            'status' => 'ok',
            'data'   => $response
        ]);
    }

    /**
     *  Consulta API e salva no banco (VERSÃO CORRIGIDA)
     */
    public function consultarESalvar(
        MenorPrecoService $service,
        Request $request
    ): JsonResponse {

        $termo     = $request->query('termo');
        $gtin      = $request->query('gtin');
        $local     = $request->query('local');
        $categoria = (int) $request->query('categoria', 20);
        $offset    = (int) $request->query('offset', 0);
        $raio      = (int) $request->query('raio', 200);
        $data      = (int) $request->query('data', -1);
        $ordem     = (int) $request->query('ordem', 0);

        // Validação
        if (empty($termo) && empty($gtin)) {
            return response()->json([
                'status' => 'erro',
                'msg'    => 'Informe termo ou gtin para busca'
            ], 400);
        }

        if (empty($local)) {
            return response()->json([
                'status' => 'erro',
                'msg'    => 'Parâmetro local é obrigatório'
            ], 400);
        }

        $response = $service->consultar(
            termo: $termo,
            gtin: $gtin,
            local: $local,
            categoria: $categoria,
            offset: $offset,
            raio: $raio,
            data: $data,
            ordem: $ordem
        );

        if (
            empty($response) ||
            empty($response['produtos'])
        ) {
            return response()->json([
                'status' => 'erro',
                'msg'    => 'Nenhum produto retornado'
            ], 400);
        }

        $salvos = 0;
        $detalhes = [];

        foreach ($response['produtos'] as $p) {

            /** ---------------- PRODUTO ---------------- */
            // 🎯 Chave única melhorada para evitar duplicatas
            // Se tem GTIN: usa (gtin + ncm)
            // Se não tem GTIN: usa (ncm + descricao normalizada)
            
            $chaveProduto = [
                'ncm' => $p['ncm'] ?? null,
            ];

            $dadosProduto = [
                'palavrachave' => $termo ?? ($p['desc'] ?? null),
                'descricao'    => $p['desc'] ?? null,
                'volume'       => 0,
                'unidade'      => 'UN',
                'categoria'    => $categoria,
                'local'        => $local,
            ];

            // Se tem GTIN válido, usa como chave
            if (!empty($p['gtin'])) {
                $chaveProduto['gtin'] = $p['gtin'];
                $dadosProduto['gtin'] = $p['gtin'];
            } else {
                // Se não tem GTIN, usa descrição como parte da chave
                $chaveProduto['descricao'] = $p['desc'] ?? null;
            }

            $produto = MenorprecoProduto::firstOrCreate(
                $chaveProduto,
                $dadosProduto
            );

            /** ------------- ESTABELECIMENTO ------------ */
            if (empty($p['estabelecimento'])) {
                continue;
            }

            $est = $p['estabelecimento'];

            $estabelecimento = MenorprecoEstabelecimento::firstOrCreate(
                ['codigo' => $est['codigo']],
                [
                    'nome_fantasia' => $est['nm_fan'] ?? null,
                    'razao_social'  => $est['nm_emp'] ?? null,
                    'bairro'        => $est['bairro'] ?? null,
                    'cidade'        => $est['mun'],
                    'uf'            => $est['uf'],
                    'tp_logr'       => $est['tp_logr'] ?? null,
                    'nm_logr'       => $est['nm_logr'] ?? null,
                    'nr_logr'       => $est['nr_logr'] ?? null,
                ]
            );

            /** ---------------- HISTÓRICO ---------------- */
            MenorprecoHistoricoPreco::updateOrCreate(
                [
                    'produto_id'         => $produto->id,
                    'estabelecimento_id' => $estabelecimento->id,
                    'data_coleta'        => now()->toDateString(),
                ],
                [
                    'preco'         => $p['valor'],
                    'preco_tabela'  => $p['valor_tabela'] ?? null,
                    'desconto'      => $p['valor_desconto'] ?? null,
                    'distancia_km'  => $p['distkm'] ?? null,
                    'datahora_nota' => $p['datahora'] ?? null,
                ]
            );

            $salvos++;

            $detalhes[] = [
                'produto_id' => $produto->id,
                'gtin'       => $p['gtin'] ?? 'sem GTIN',
                'ncm'        => $p['ncm'],
                'desc'       => $p['desc'],
                'preco'      => $p['valor'],
                'estabelecimento' => $est['nm_fan'] ?? $est['nm_emp'],
            ];
        }

        return response()->json([
            'status'   => 'ok',
            'salvos'   => $salvos,
            'total'    => count($response['produtos']),
            'data'     => now()->toDateString(),
            'filtro'   => $gtin ? "gtin: {$gtin}" : "termo: {$termo}",
            'detalhes' => $detalhes,
        ]);
    }
}