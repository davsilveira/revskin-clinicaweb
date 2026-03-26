<?php

namespace App\Support;

/**
 * Lê a tabela "Código Legado → Código Base" em {@see docs/sanitization/mapeamento-codigos-legado-base.md}.
 */
final class LegadoCodigoProdutoMapeamento
{
    /**
     * @return array<string, string>
     */
    public static function fromMarkdownFile(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return [];
        }

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $map = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            $cells = self::splitMarkdownTableRow($line);
            if (count($cells) < 2) {
                continue;
            }

            $legado = trim($cells[0]);
            $base = trim($cells[1]);

            if ($legado === '' || str_contains($legado, '---')) {
                continue;
            }

            $legadoLower = mb_strtolower($legado);
            if (str_contains($legadoLower, 'código legado') || str_contains($legadoLower, 'codigo legado')) {
                continue;
            }

            $map[$legado] = $base;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $map
     */
    public static function paraBase(string $codigoLegado, array $map): string
    {
        return $map[$codigoLegado] ?? $codigoLegado;
    }

    /**
     * @return list<string>
     */
    private static function splitMarkdownTableRow(string $line): array
    {
        $t = trim($line);
        if (str_starts_with($t, '|')) {
            $t = substr($t, 1);
        }
        if (str_ends_with($t, '|')) {
            $t = substr($t, 0, -1);
        }

        return array_map(trim(...), explode('|', $t));
    }
}
