<?php

namespace App\Support;

/**
 * Chave de comparação de telefone entre a base e o oList.
 *
 * O oList guarda o mesmo celular de formas diferentes — `(48) 99907-2096` aqui e `(48) 9907-2096`
 * lá, porque o nono dígito entrou em 2016 e cadastro antigo nunca foi atualizado. Comparar dígito a
 * dígito faz o sync não reconhecer o paciente e criar um cadastro repetido.
 *
 * A chave é **DDD + os últimos 8 dígitos**. Os 8 finais absorvem o nono dígito; o DDD continua
 * valendo porque a base tem homônimas com o mesmo número em estados diferentes — `Hellen Uliam
 * Uriki` aparece com `(66) 99907-5482` e `(65) 99907-5482` e são duas pessoas. Sem o DDD elas
 * virariam uma.
 */
class TelefonePaciente
{
    public static function chave(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) $valor);

        // 55 na frente de 12 ou 13 dígitos é código do país. Em 10 ou 11 dígitos, 55 é o DDD do
        // interior do Rio Grande do Sul e fica.
        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }

        if (strlen($d) < 10) {
            return null;
        }

        return substr($d, 0, 2).substr($d, -8);
    }

    /** Os 8 dígitos finais, que é o que dá para procurar no banco sem depender da formatação. */
    public static function ultimos8(?string $valor): ?string
    {
        $chave = self::chave($valor);

        return $chave === null ? null : substr($chave, -8);
    }

    public static function iguais(?string $a, ?string $b): bool
    {
        $ca = self::chave($a);

        return $ca !== null && $ca === self::chave($b);
    }
}
