# Migração legado ClinicaWeb → RevSkin

## Por que ainda aparecem produtos «Legado» na lista?

São **registros em `produtos`** criados na importação anterior. O re-link troca `receita_itens` para o SKU ativo; `arquivar-legado-orfao` marca os stubs órfãos com `ativo=0`.

No admin, o filtro **Descontinuados (pendentes)** lista só `legado_somente_leitura` **e** `ativo=1` — os 44 já mapeados/arquivados **não** aparecem mais (use catálogo normal ou busca pelo código Tiny).

## Fluxo recomendado (banco atual, sem reimportar tudo)

```bash
# 1. Validar mapa
php artisan produtos:validar-mapeamento-legado

# 2. Corrigir linhas de receita que ainda apontam para stub legado
php artisan migration:relink-receita-produtos        # dry-run
php artisan migration:relink-receita-produtos --fix

# 3. Desativar stubs legado sem nenhuma receita referenciando (limpa catálogo ativo)
php artisan produtos:arquivar-legado-orfao           # dry-run
php artisan produtos:arquivar-legado-orfao --fix
```

## Reimportar tudo?

Só se precisar **zerar** pacientes/receitas/médicos e reconstruir a partir do JSON legado.

```bash
php artisan migration:exportar-backup-reimport
php artisan migration:limpar-dados-reimport --apos-backup --confirm=REIMPORT
# opcional: --incluir-produtos (apaga catálogo; exige novo sync Tiny)

php artisan migration:extrair-legado
php artisan migration:importar-legado
php artisan migration:relink-receita-produtos --fix
php artisan produtos:arquivar-legado-orfao --fix
```

A importação **exige** `database/mapeamento-codigos-legado-base.md` e **não cria stub** para códigos já mapeados no markdown.

## Prevenção em importações futuras

- Manter `database/mapeamento-codigos-legado-base.md` versionado no git.
- Sincronizar catálogo Tiny **antes** de `migration:importar-legado`.
- Conferir no resumo: `stubs legado criados` deve ser **0** ou só os 5 sem equivalente (Apostila, SUICO, Tonalite `___`, Mezzotono).
