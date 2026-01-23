<?php

namespace App\Services;

use App\Helpers\NormalizacaoHelper;
use App\Models\MenorprecoProduto as Produto;
use Illuminate\Support\Facades\Http;

class MenorPrecoService
{
    /**
     * Consulta API Menor Preço
     */
    public function consultar(string $termo, string $local, int $categoria): array
    {
        try {
            return Http::get(
                'https://menorpreco.notaparana.pr.gov.br/api/v1/produtos',
                [
                    'local'     => $local,
                    'termo'     => $termo,
                    'categoria' => $categoria,
                    'offset'    => 0,
                    'raio'      => 200,
                    'data'      => -1,
                    'ordem'     => 0,
                ]
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
        /** 1️⃣ Match direto por GTIN */
        if (
            !empty($produto->gtin) &&
            !empty($p['gtin']) &&
            $p['gtin'] === $produto->gtin
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
