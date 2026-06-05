<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenorprecoEstabelecimento;
use App\Models\MenorprecoHistoricoPreco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EstabelecimentoController extends Controller
{
    /**
     * Lista todos os estabelecimentos da base (catálogo), com filtro,
     * contagem de produtos coletados e marca de quais já estão na lista
     * de mercados do usuário.
     */
    public function index(Request $request): JsonResponse
    {
        $q      = trim((string) $request->query('q', ''));
        $cidade = trim((string) $request->query('cidade', ''));

        // Quantos produtos distintos já foram coletados em cada estabelecimento
        $contagens = MenorprecoHistoricoPreco::selectRaw('estabelecimento_id, COUNT(DISTINCT produto_id) AS total')
            ->groupBy('estabelecimento_id')
            ->pluck('total', 'estabelecimento_id');

        // Mercados que já estão na lista do usuário (id do vínculo)
        $favoritos = $request->user()->listaEstabelecimentos()
            ->pluck('id', 'estabelecimento_id');

        $pagina = MenorprecoEstabelecimento::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nome_fantasia', 'like', "%{$q}%")
                        ->orWhere('razao_social', 'like', "%{$q}%")
                        ->orWhere('bairro', 'like', "%{$q}%");
                });
            })
            ->when($cidade !== '', fn ($query) => $query->where('cidade', $cidade))
            ->orderByRaw('COALESCE(NULLIF(nome_fantasia, ""), razao_social) asc')
            ->paginate(40);

        $itens = collect($pagina->items())->map(fn (MenorprecoEstabelecimento $e) => [
            'id'             => $e->id,
            'codigo'         => $e->codigo,
            'nome_fantasia'  => $e->nome_fantasia,
            'razao_social'   => $e->razao_social,
            'bairro'         => $e->bairro,
            'cidade'         => $e->cidade,
            'uf'             => $e->uf,
            'tp_logr'        => $e->tp_logr,
            'nm_logr'        => $e->nm_logr,
            'nr_logr'        => $e->nr_logr,
            'total_produtos' => (int) ($contagens[$e->id] ?? 0),
            'lista_id'       => $favoritos[$e->id] ?? null, // != null => já é favorito
        ]);

        return response()->json([
            'status'       => 'ok',
            'data'         => $itens,
            'pagina_atual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total'        => $pagina->total(),
        ]);
    }
}
