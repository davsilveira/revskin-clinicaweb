<?php

namespace App\Support;

class NomePaciente
{
    /**
     * Nomes que representam a mesma pessoa: mesmo primeiro e mesmo último nome
     * ("João Silva" ≡ "João Pedro da Silva"). Nomes diferentes (mãe e filha que dividem
     * e-mail ou celular) não casam.
     *
     * Usado na conciliação do import do oList e como travessa no upsert por
     * `paciente_existente_id` — nos dois casos o risco é fundir duas pessoas distintas.
     */
    public static function compativeis(string $a, string $b): bool
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);

        if ($ta === [] || $tb === []) {
            return false;
        }

        if ($ta === $tb) {
            return true;
        }

        return $ta[0] === $tb[0] && end($ta) === end($tb);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $nome): array
    {
        $s = mb_strtolower(trim($nome));
        $s = str_replace(
            ['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'],
            $s
        );
        $s = (string) preg_replace('/[^a-z0-9 ]/', ' ', $s);

        return array_values(array_filter(
            preg_split('/\s+/', $s) ?: [],
            // "de/da/do/dos/das/e" não distinguem pessoas.
            fn ($t) => $t !== '' && ! in_array($t, ['de', 'da', 'do', 'dos', 'das', 'e'], true)
        ));
    }
}
