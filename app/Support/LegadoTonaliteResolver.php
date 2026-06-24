<?php

namespace App\Support;

/**
 * Expande códigos TONALITE com placeholder (___ / __) usando o fototipo do paciente.
 * Mesma lógica de {@see \App\Services\RegrasCondicionaisEngine::resolverCodigoTonalite()}.
 */
final class LegadoTonaliteResolver
{
    private const TONS_VALIDOS = ['1', '1,5', '2', '2,5', '3', '3,5', '4', '4,5'];

    public static function isPlaceholder(string $codigo): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return false;
        }

        if (preg_match('/^TON___-G\d+$/i', $codigo)) {
            return true;
        }

        return str_contains($codigo, 'TONALITE-___-')
            || str_contains($codigo, 'TONALITE-__-');
    }

    public static function normalizarTom(?string $fototipo): ?string
    {
        if ($fototipo === null) {
            return null;
        }

        $fototipo = trim($fototipo);
        if ($fototipo === '') {
            return null;
        }

        $tom = str_replace('.', ',', $fototipo);

        if (in_array($tom, self::TONS_VALIDOS, true)) {
            return $tom;
        }

        if (in_array($tom, ['5', '6'], true)) {
            return '4,5';
        }

        return null;
    }

    public static function resolverCodigo(string $codigo, ?string $fototipo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! self::isPlaceholder($codigo)) {
            return null;
        }

        $tom = self::normalizarTom($fototipo);
        if ($tom === null) {
            return null;
        }

        if (preg_match('/^TON___-(G\d+)$/i', $codigo, $m)) {
            return 'TONALITE-'.$tom.'-'.$m[1];
        }

        if (str_contains($codigo, 'TONALITE-___-')) {
            return str_replace('TONALITE-___-', 'TONALITE-'.$tom.'-', $codigo);
        }

        if (str_contains($codigo, 'TONALITE-__-')) {
            return str_replace('TONALITE-__-', 'TONALITE-'.$tom.'-', $codigo);
        }

        return null;
    }
}
