<?php

namespace App\Support;

final class LegadoEmail
{
    public static function isPlaceholder(?string $email): bool
    {
        $email = trim((string) $email);
        if ($email === '') {
            return true;
        }

        $lower = strtolower($email);

        // O dump do CLW2 traz o placeholder com o domínio antigo (`@cadastraremail.com`); só
        // reconhecer o `.rsk` deixava esse endereço entrar como se fosse e-mail de verdade.
        return EmailPlaceholder::ehPlaceholder($email)
            || str_ends_with($lower, '@legado.local')
            || $lower === 'null'
            || $lower === 'n/a';
    }

    public static function usable(?string $email): ?string
    {
        $email = trim((string) $email);

        return self::isPlaceholder($email) ? null : $email;
    }
}
