<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Diretório dos dumps SQL do ClinicaWeb (CLW2)
    |--------------------------------------------------------------------------
    | A UI lista os .sql desta pasta. Sem upload — o arquivo vai junto no deploy.
    |
    | `database/legado/` é versionado de propósito: `docs/` está no .gitignore e não entra no
    | pacote, então um dump ali nunca chegaria em produção. Mesma solução já usada para
    | `database/mapeamento-codigos-legado-base.md`. Guarde só o dump em uso — são ~14 MB cada.
    */
    'sql_path' => env('LEGADO_SQL_PATH', base_path('database/legado')),
];
