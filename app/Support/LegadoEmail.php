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

        return str_ends_with($lower, '@cadastraremail.rsk')
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
