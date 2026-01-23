<?php

namespace App\Helpers;

class NormalizacaoHelper
{
    /**
     * Normaliza texto:
     * - maiúsculo
     * - sem acento
     * - sem caracteres especiais
     */
    public static function texto(string $text): string
    {
        $text = mb_strtoupper($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^A-Z0-9 ]/', '', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Extrai volume e unidade do texto
     * Ex: 500G, 1L, 900 ML
     */
    public static function volume(string $text): array
    {
        if (preg_match('/(\d+)\s*(G|GR|KG|ML|L)/', $text, $m)) {

            $volume  = (int) $m[1];
            $unidade = $m[2];

            // Normalização de unidades
            if ($unidade === 'GR') {
                $unidade = 'G';
            }

            if ($unidade === 'KG') {
                $volume  = $volume * 1000;
                $unidade = 'G';
            }

            if ($unidade === 'L') {
                $volume  = $volume * 1000;
                $unidade = 'ML';
            }

            return [
                'volume'  => $volume,
                'unidade' => $unidade,
            ];
        }

        return [
            'volume'  => null,
            'unidade' => null,
        ];
    }
}
