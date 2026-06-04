<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MenorPrecoService;
use App\Models\ListaProduto;
use App\Models\ListaEstabelecimento;
use App\Models\MenorprecoProduto;
use App\Models\MenorprecoEstabelecimento;
use App\Models\MenorprecoHistoricoPreco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MinhaListaController extends Controller
{
    /* ===================================================================
     |  PRODUTOS DA LISTA
     |==================================================================*/

    public function produtos(Request $request): JsonResponse
    {
        $itens = $request->user()->listaProdutos()
            ->with('produto')
            ->latest()
            ->get()
            ->map(fn (ListaProduto $i) => [
                'id'         => $i->id,
                'produto_id' => $i->produto_id,
                'local'      => $i->local,
                'produto'    => $i->produto,
            ]);

        return response()->json(['status' => 'ok', 'data' => $itens]);
    }

    public function adicionarProduto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gtin'      => 'required|string|max:20',
            'ncm'       => 'required|string|max:20',
            'descricao' => 'required|string',
            'categoria' => 'nullable|integer',
            'local'     => 'nullable|string',
        ]);

        // Produto identificado pelo par (gtin, ncm)
        $produto = MenorprecoProduto::firstOrCreate(
            ['gtin' => $data['gtin'], 'ncm' => $data['ncm']],
            [
                'palavrachave' => mb_substr($data['descricao'], 0, 50),
                'descricao'    => mb_substr($data['descricao'], 0, 255),
                'volume'       => 0,
                'unidade'      => 'UN',
                'categoria'    => $data['categoria'] ?? 0,
                'local'        => $data['local'] ?? '',
            ]
        );

        $item = ListaProduto::firstOrCreate(
            ['user_id' => $request->user()->id, 'produto_id' => $produto->id],
            ['local' => $data['local'] ?? null]
        );

        return response()->json([
            'status'  => 'ok',
            'item'    => $item->load('produto'),
        ], 201);
    }

    public function removerProduto(Request $request, int $id): JsonResponse
    {
        $request->user()->listaProdutos()->where('id', $id)->delete();
        return response()->json(['status' => 'ok']);
    }

    /* ===================================================================
     |  ESTABELECIMENTOS (MERCADOS) FAVORITOS
     |==================================================================*/

    public function estabelecimentos(Request $request): JsonResponse
    {
        $itens = $request->user()->listaEstabelecimentos()
            ->with('estabelecimento')
            ->latest()
            ->get()
            ->map(fn (ListaEstabelecimento $i) => [
                'id'                 => $i->id,
                'estabelecimento_id' => $i->estabelecimento_id,
                'apelido'            => $i->apelido,
                'estabelecimento'    => $i->estabelecimento,
            ]);

        return response()->json(['status' => 'ok', 'data' => $itens]);
    }

    public function adicionarEstabelecimento(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'        => 'required',
            'nome_fantasia' => 'nullable|string',
            'razao_social'  => 'nullable|string',
            'bairro'        => 'nullable|string',
            'cidade'        => 'nullable|string',
            'uf'            => 'nullable|string',
            'tp_logr'       => 'nullable|string',
            'nm_logr'       => 'nullable|string',
            'nr_logr'       => 'nullable|string',
            'apelido'       => 'nullable|string',
        ]);

        $estabelecimento = MenorprecoEstabelecimento::firstOrCreate(
            ['codigo' => $data['codigo']],
            [
                'nome_fantasia' => $data['nome_fantasia'] ?? null,
                'razao_social'  => $data['razao_social'] ?? null,
                'bairro'        => $data['bairro'] ?? null,
                'cidade'        => $data['cidade'] ?? null,
                'uf'            => $data['uf'] ?? null,
                'tp_logr'       => $data['tp_logr'] ?? null,
                'nm_logr'       => $data['nm_logr'] ?? null,
                'nr_logr'       => $data['nr_logr'] ?? null,
            ]
        );

        $item = ListaEstabelecimento::firstOrCreate(
            ['user_id' => $request->user()->id, 'estabelecimento_id' => $estabelecimento->id],
            ['apelido' => $data['apelido'] ?? null]
        );

        return response()->json([
            'status' => 'ok',
            'item'   => $item->load('estabelecimento'),
        ], 201);
    }

    public function atualizarEstabelecimento(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['apelido' => 'nullable|string']);

        $item = $request->user()->listaEstabelecimentos()->where('id', $id)->firstOrFail();
        $item->update(['apelido' => $data['apelido'] ?? null]);

        return response()->json(['status' => 'ok', 'item' => $item->load('estabelecimento')]);
    }

    public function removerEstabelecimento(Request $request, int $id): JsonResponse
    {
        $request->user()->listaEstabelecimentos()->where('id', $id)->delete();
        return response()->json(['status' => 'ok']);
    }

    /* ===================================================================
     |  COMPARAÇÃO (matriz produtos × mercados)
     |==================================================================*/

    public function comparacao(Request $request): JsonResponse
    {
        $user = $request->user();

        $produtos = $user->listaProdutos()->with('produto')->get();
        $estabelecimentos = $user->listaEstabelecimentos()->with('estabelecimento')->get();

        $produtoIds = $produtos->pluck('produto_id')->all();
        $estIds     = $estabelecimentos->pluck('estabelecimento_id')->all();

        // Último preço de cada par (produto, estabelecimento)
        $matriz = [];
        if ($produtoIds && $estIds) {
            $historicos = MenorprecoHistoricoPreco::whereIn('produto_id', $produtoIds)
                ->whereIn('estabelecimento_id', $estIds)
                ->orderByDesc('data_coleta')
                ->get()
                ->groupBy(fn ($h) => $h->produto_id . '-' . $h->estabelecimento_id);

            foreach ($historicos as $chave => $grupo) {
                $ultimo = $grupo->first(); // mais recente (já ordenado desc)
                $matriz[$chave] = [
                    'preco'       => $ultimo->preco,
                    'data_coleta' => $ultimo->data_coleta->toDateString(),
                ];
            }
        }

        // Menor preço por produto (qual estabelecimento ganha)
        $menorPorProduto = [];
        foreach ($produtos as $lp) {
            $melhor = null;
            foreach ($estabelecimentos as $le) {
                $cel = $matriz["{$lp->produto_id}-{$le->estabelecimento_id}"] ?? null;
                if ($cel && ($melhor === null || $cel['preco'] < $melhor['preco'])) {
                    $melhor = ['estabelecimento_id' => $le->estabelecimento_id, 'preco' => $cel['preco']];
                }
            }
            if ($melhor) {
                $menorPorProduto[$lp->produto_id] = $melhor['estabelecimento_id'];
            }
        }

        return response()->json([
            'status'           => 'ok',
            'produtos'         => $produtos->map(fn ($i) => [
                'id'         => $i->id,
                'produto_id' => $i->produto_id,
                'produto'    => $i->produto,
            ]),
            'estabelecimentos' => $estabelecimentos->map(fn ($i) => [
                'id'                 => $i->id,
                'estabelecimento_id' => $i->estabelecimento_id,
                'apelido'            => $i->apelido,
                'estabelecimento'    => $i->estabelecimento,
            ]),
            'matriz'           => $matriz,
            'menor_por_produto' => $menorPorProduto,
        ]);
    }

    /* ===================================================================
     |  ATUALIZAR AGORA (consulta a API ao vivo e grava preços de hoje)
     |==================================================================*/

    public function atualizar(Request $request, MenorPrecoService $service): JsonResponse
    {
        $user = $request->user();
        $produtos = $user->listaProdutos()->with('produto')->get();

        if ($produtos->isEmpty()) {
            return response()->json(['status' => 'ok', 'atualizados' => 0, 'msg' => 'Lista vazia']);
        }

        $localPadrao = $request->query('local', config('menorpreco.local_cascavel'));
        $raio        = (int) $request->query('raio', 50);

        $atualizados = 0;

        foreach ($produtos as $lp) {
            $produto = $lp->produto;
            if (!$produto || empty($produto->gtin)) {
                continue;
            }

            $local = $lp->local ?: $localPadrao;

            $response = $service->consultar(
                gtin: $produto->gtin,
                local: $local,
                categoria: (int) $produto->categoria,
                raio: $raio,
                ordem: 1
            );

            foreach ($response['produtos'] ?? [] as $p) {
                // só grava ofertas do mesmo GTIN, com estabelecimento
                if (empty($p['estabelecimento']) || ($p['gtin'] ?? null) !== $produto->gtin) {
                    continue;
                }
                $this->salvarOferta($p, $produto->id);
                $atualizados++;
            }

            usleep(150_000); // respeita a API
        }

        return response()->json([
            'status'      => 'ok',
            'atualizados' => $atualizados,
            'data'        => now()->toDateString(),
        ]);
    }

    /**
     * Grava (ou atualiza) o estabelecimento e o preço de hoje de uma oferta.
     */
    private function salvarOferta(array $p, int $produtoId): void
    {
        $est = $p['estabelecimento'];

        $estabelecimento = MenorprecoEstabelecimento::updateOrCreate(
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

        MenorprecoHistoricoPreco::updateOrCreate(
            [
                'produto_id'         => $produtoId,
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
    }
}
