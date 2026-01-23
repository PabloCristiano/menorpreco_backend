<?php

namespace App\Services;

use App\Helpers\NormalizacaoHelper;
use App\Models\MenorprecoProduto as Produto;
use Illuminate\Support\Facades\Http;

class MenorPrecoService
{
    /**
     * Consulta API Menor Preço (VERSÃO ATUALIZADA)
     */
    public function consultar(
        ?string $termo = null,
        ?string $gtin = null,
        string $local = '',
        int $categoria = 20,
        int $offset = 0,
        int $raio = 200,
        int $data = -1,
        int $ordem = 0
    ): array {
        try {
            $params = [
                'local'     => $local,
                'categoria' => $categoria,
                'offset'    => $offset,
                'raio'      => $raio,
                'data'      => $data,
                'ordem'     => $ordem,
            ];

            // Adiciona termo OU gtin (prioriza gtin)
            if (!empty($gtin)) {
                $params['gtin'] = $gtin;
            } elseif (!empty($termo)) {
                $params['termo'] = $termo;
            }

            return Http::get(
                'https://menorpreco.notaparana.pr.gov.br/api/v1/produtos',
                $params
            )->json();

        } catch (\Throwable $e) {
            return [
                'status'   => 'erro',
                'mensagem' => 'Não foi possível conectar à API Menor Preço.',
                'erro'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Decide se o produto da API corresponde
     * ao produto monitorado
     */
    public function ehProdutoAlvo(array $p, Produto $produto): bool
    {
        /** 1️⃣ Match direto por GTIN + NCM */
        if (
            !empty($produto->gtin) &&
            !empty($p['gtin']) &&
            $p['gtin'] === $produto->gtin &&
            !empty($produto->ncm) &&
            !empty($p['ncm']) &&
            $p['ncm'] === $produto->ncm
        ) {
            return true;
        }

        /** Proteções básicas */
        if (
            empty($p['desc']) ||
            empty($p['ncm']) ||
            empty($produto->ncm) ||
            empty($produto->palavrachave)
        ) {
            return false;
        }

        /** 2️⃣ Normalização */
        $descApi   = NormalizacaoHelper::texto($p['desc']);
        $palavra   = NormalizacaoHelper::texto($produto->palavrachave);

        /** 3️⃣ Volume / unidade */
        $volumeApi = NormalizacaoHelper::volume($descApi);

        $volApi = $volumeApi['volume'];
        $unApi  = $volumeApi['unidade'];

        /** 4️⃣ Comparações */
        $ncmConfere = $p['ncm'] === $produto->ncm;

        $volumeConfere = empty($produto->volume) || (
            !is_null($volApi) && (int)$volApi === (int)$produto->volume
        );

        $unidadeConfere = empty($produto->unidade) || (
            !is_null($unApi) && $unApi === $produto->unidade
        );

        $palavraConfere = str_contains($descApi, $palavra);

        return $ncmConfere
            && $volumeConfere
            && $unidadeConfere
            && $palavraConfere;
    }
}
