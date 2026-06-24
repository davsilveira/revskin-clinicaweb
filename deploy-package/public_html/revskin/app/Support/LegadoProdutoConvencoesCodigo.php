<?php

namespace App\Support;

/**
 * Converte códigos legado (hífen + sufixo G30/G50) para o formato do catálogo Tiny atual (espaços + 30G/50G).
 * Complementa {@see LegadoCodigoProdutoMapeamento} — usado quando não há linha explícita no markdown.
 */
final class LegadoProdutoConvencoesCodigo
{
    /**
     * @return list<string>
     */
    public static function variantes(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        $vars = [];

        if (preg_match('/^TONALITE-(.+)-(G\d+)$/i', $codigo, $m)) {
            $tom = $m[1];
            if ($tom !== '___' && ! str_contains(strtoupper($tom), 'MEZZOTONO')) {
                $vars[] = 'TONALITE '.$tom.' '.self::sufixoTamanhoCatalogo($m[2]);
            }
        }

        if (preg_match('/^DEMERANE-ULTRA-(G\d+)$/i', $codigo, $m)) {
            $vars[] = 'DEMERANE '.self::sufixoTamanhoCatalogo($m[1]);
        }

        if (preg_match('/^HYDRAMINCE-DYNAMISEE-(\d+)$/i', $codigo, $m)) {
            $vars[] = 'DYNAMISEE '.$m[1];
        }

        if (preg_match('/^zzz?_?HYDRAMINCE-DYNAMISEE-(\d+)$/i', $codigo, $m)) {
            $vars[] = 'DYNAMISEE '.$m[1];
        }

        if (strcasecmp($codigo, 'HYDRAMINCE-SYNCHRON') === 0) {
            $vars[] = 'SYNCHRON';
        }

        if (preg_match('/^NOITE-HIPOALERGENICO-LUMI-HYDRAVELT$/i', $codigo)) {
            $vars[] = 'NOITE LUMI HYDRAVELT';
        }

        if (preg_match('/^NOITE-HIPOALERGENICO-RETINOL-LUMI-HYDRAVELT$/i', $codigo)) {
            $vars[] = 'NOITE RETINOL HYDRAVELT';
        }

        if (preg_match('/^NOITE-HIPOALERGENICO-RETINOL-LUMI-DYN3$/i', $codigo)) {
            $vars[] = 'NOITE RETINOL HYDRAVELT PLUS';
        }

        if (preg_match('/^NOITE-HIPOALERGENICO-R0,(\d+(?:,\d+)?)-HYDRAVELT$/i', $codigo, $m)) {
            $vars[] = 'NOITE R0,'.$m[1].' HYDRAVELT';
        }

        if (preg_match('/^NOITE-HIPOALERGENICO-R0,(\d+(?:,\d+)?)-DYN3$/i', $codigo, $m)) {
            $vars[] = 'R0,'.$m[1].'H6-DYN3';
        }

        if (preg_match('/^NOITE-GLICOLICO-3-GESTANTE$/i', $codigo)) {
            $vars[] = 'NOITE GLICOLICO GESTANTE';
        }

        if (preg_match('/^(?:z-)?CORPO-CREME$/i', $codigo)) {
            $vars[] = 'HYDRAESSENTIAL';
        }

        if (strcasecmp($codigo, 'MAOS-DIA') === 0) {
            $vars[] = 'DIA MAOS';
        }

        if (preg_match('/^MAOS-NOITE-(R0,[0-9.]+H\d+)-DYN3$/i', $codigo, $m)) {
            $vars[] = 'NOITE MAOS '.$m[1];
        }

        if (preg_match('/^CLAREADOR-HIPOALERGENICO-SERUM-(\d+)$/i', $codigo, $m)) {
            $vars[] = 'CLAREADOR HYDRAVELT '.$m[1];
        }

        if (strcasecmp($codigo, 'IVERMECTINA-20G') === 0) {
            $vars[] = 'IVERMECTINA ROSACEA';
        }

        if (strcasecmp($codigo, 'METRONIDAZOL-15G') === 0) {
            $vars[] = 'METRONIDAZOL ROSACEA 15G';
        }

        if (strcasecmp($codigo, 'ESTRIAS-CREME') === 0) {
            $vars[] = 'ESTRIAS';
        }

        if (preg_match('/^W-HYALU-GEL-HYDRAVELT$/i', $codigo)) {
            $vars[] = 'HYALU GEL';
        }

        if (strcasecmp($codigo, 'BIONAISSANCE-SENSITIVE') === 0) {
            $vars[] = 'BIONAISSANCE 100G';
        }

        if (preg_match('/^AZELAICO-IVERMECTINA-30G$/i', $codigo)) {
            $vars[] = 'AZELAICO 30G';
        }

        if (preg_match('/^CLINDAMICINA-PEROXIDO$/i', $codigo)) {
            $vars[] = 'DESINFLAM CLINDA 30G';
        }

        return array_values(array_unique($vars));
    }

    private static function sufixoTamanhoCatalogo(string $gSuffix): string
    {
        $n = preg_replace('/\D/', '', $gSuffix);

        return $n !== '' ? $n.'G' : strtoupper($gSuffix);
    }
}
