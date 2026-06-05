<?php

namespace App\Services;

use App\Helpers\NormalizacaoHelper;
use App\Models\MenorprecoProduto as Produto;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class MenorPrecoService
{
    private const BASE_URL = 'https://menorpreco.notaparana.pr.gov.br/api/v1';

    /**
     * Cliente HTTP configurado para a API do Nota Paraná.
     *
     * Força IPv4 (evita travas de ~10s no connect via IPv6 no macOS),
     * define timeouts sãos e tenta novamente em falhas transitórias.
     */
    private function client(): PendingRequest
    {
        return Http::connectTimeout(5)
            ->timeout(30)
            ->retry(
                times: 4,
                sleepMilliseconds: 400,
                when: fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException,
                throw: false
            )
            ->withOptions(['force_ip_resolve' => 'v4']);
    }

    /**
     * Consulta API Menor Preço
     * 
     * @param string|null $termo Termo de busca (ex: "leite")
     * @param string|null $gtin Código GTIN do produto
     * @param string $local Código da localização (ex: "4104808" para Cascavel)
     * @param int $categoria Código da categoria
     * @param int $offset Paginação - offset inicial
     * @param int $raio Raio de busca em km
     * @param int $data Filtro de data (-1 para todas)
     * @param int $ordem Ordenação (0=relevância, 1=preço crescente)
     * @return array Resposta da API
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

            return $this->client()->get(
                self::BASE_URL . '/produtos',
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
     * Lista as categorias relevantes para um termo de busca.
     *
     * A API do Nota Paraná devolve, para um termo, as categorias onde ele
     * aparece, com a quantidade de produtos (qtd) — usado como relevância.
     *
     * @return array{ local?: string, termo?: string, categorias?: array }
     */
    public function categorias(
        ?string $termo = null,
        ?string $gtin = null,
        string $local = '',
        int $raio = 200
    ): array {
        try {
            $params = [
                'local' => $local,
                'raio'  => $raio,
            ];

            if (!empty($gtin)) {
                $params['gtin'] = $gtin;
            } elseif (!empty($termo)) {
                $params['termo'] = $termo;
            }

            return $this->client()->get(
                self::BASE_URL . '/categorias',
                $params
            )->json() ?? [];

        } catch (\Throwable $e) {
            return [
                'status'   => 'erro',
                'mensagem' => 'Não foi possível consultar as categorias.',
                'erro'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Decide se o produto da API corresponde ao produto monitorado
     * 
     * Estratégia de matching:
     * 1. Se ambos têm GTIN e NCM: match direto (mais confiável)
     * 2. Caso contrário: validação por NCM + palavra-chave + volume/unidade
     * 
     * @param array $p Produto retornado da API
     * @param Produto $produto Produto cadastrado no banco
     * @return bool True se o produto da API corresponde ao produto monitorado
     */
    public function ehProdutoAlvo(array $p, Produto $produto): bool
    {
        /** 1️⃣ Match direto por GTIN + NCM (mais confiável) */
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

        /** 2️⃣ Proteções básicas - dados essenciais */
        if (
            empty($p['desc']) ||
            empty($p['ncm']) ||
            empty($produto->ncm) ||
            empty($produto->palavrachave)
        ) {
            return false;
        }

        /** 3️⃣ Normalização de textos para comparação */
        $descApi = NormalizacaoHelper::texto($p['desc']);
        $palavra = NormalizacaoHelper::texto($produto->palavrachave);

        /** 4️⃣ Extrai volume e unidade da descrição da API */
        $volumeApi = NormalizacaoHelper::volume($descApi);

        $volApi = $volumeApi['volume'];
        $unApi  = $volumeApi['unidade'];

        /** 5️⃣ Validações individuais */
        
        // NCM deve ser idêntico
        $ncmConfere = $p['ncm'] === $produto->ncm;

        // Volume: se cadastrado, deve bater com o extraído da API
        $volumeConfere = empty($produto->volume) || (
            !is_null($volApi) && (int)$volApi === (int)$produto->volume
        );

        // Unidade: se cadastrada, deve bater com a extraída da API
        $unidadeConfere = empty($produto->unidade) || (
            !is_null($unApi) && $unApi === $produto->unidade
        );

        // Palavra-chave deve estar presente na descrição
        $palavraConfere = str_contains($descApi, $palavra);

        /** 6️⃣ Match final: todas as condições devem ser verdadeiras */
        return $ncmConfere
            && $volumeConfere
            && $unidadeConfere
            && $palavraConfere;
    }
}