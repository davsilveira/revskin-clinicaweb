# O que está pendente de push / deploy para produção — 2026-08-07

**Resumo:** há **11 commits em `main` local que nunca foram pushados**. Produção está parada no
commit `9a5dc19` (deploy de **2026-07-31 14:25 UTC**, run `30638542251`). Nada da última semana
de trabalho (importador CLW2, cadastro de paciente estrangeiro/e-mail opcional, Cortesia no RD,
produto descontinuado no oList) está no ar.

Nenhum push foi feito neste job — push em `main` dispara o deploy em produção automaticamente,
então a decisão fica com você.

---

## 1. Estado do repositório

| Item | Estado |
|---|---|
| Branch | `main`, **ahead 11 / behind 0** de `origin/main` |
| `origin/main` | `9a5dc19 fix(tiny): evitar duplicar itens no webhook e limpar DESINFLAM da 27796` |
| Local HEAD | `eae94e8 fix(migration): reconhecer placeholder de e-mail com domínio antigo do CLW2` |
| Último deploy em prod | 2026-07-31 (workflow `Deploy to Hostinger`, sucesso, 52s) |
| Stashes | nenhum |
| PRs abertos | nenhum |
| Working tree | **nada de código pendente** — só `.DS_Store`, worklogs `.wp-agent/`, `storage/logs/laravel.log` e 3 views compiladas |

O pipeline é `push em main` → GitHub Actions (`.github/workflows/deploy-hostinger.yml`):
`composer install --no-dev` → `npm ci && npm run build` → `artisan deploy:package` → rsync para a
Hostinger → `scripts/hostinger-post-deploy.sh` (que roda `artisan down`, **`migrate --force`**,
`optimize:clear`, caches, `storage:link`, `artisan up`).

## 2. Os 11 commits pendentes

| Commit | Data | Assunto |
|---|---|---|
| `eae94e8` | 08-06 | fix(migration): reconhecer placeholder de e-mail com domínio antigo do CLW2 |
| `08adfad` | 08-06 | fix(migration): importador CLW2 deixa de duplicar paciente e de reescrever itens |
| `b9c7115` | 08-05 | fix(pacientes): empilhar campos do drawer no mobile |
| `4a4a227` | 08-05 | fix(migration): skip refresh quando dump ≤ CLW3 ou diff vazio |
| `339dba6` | 08-05 | fix(tools): refetch lista do dry-run quando generated_at muda |
| `1e45ffa` | 08-05 | feat(tools): dry-run com tabs Pacientes/Receitas e diff navegável |
| `e647c38` | 08-05 | fix(tools): garantir ImportacaoClw2 no bundle Vite do DDEV |
| `6843cab` | 08-05 | fix(tools): corrigir tela em branco da Importação CLW2 |
| `2e63766` | 08-05 | feat(migration): importação incremental CLW2 por médico com UI admin |
| `5d476ce` | 08-05 | feat(sync): marcar produtos excluídos no oList como descontinuados |
| `2eda697` | 08-05 | feat: pacientes (e-mail, estrangeiro, busca) e Cortesia no RD |

68 arquivos, +8.221/−378. Agrupando por entrega:

1. **Importador incremental CLW2** (`/tools/importacao-clw2`) — controller, `LegadoIncrementalImporter`,
   `LegadoMedicoResolver`, `config/legado.php`, página React, comandos
   `migration:importar-legado-incremental` e `migration:backfill-receita-legado-id`.
2. **Paciente**: e-mail opcional, `outro_documento` (estrangeiro), busca por nome + vincular
   paciente existente, drawer responsivo, `pacientes:normalizar-emails-placeholder`.
3. **Receita Cortesia** → campo personalizado na negociação do RD Station.
4. **oList/Tiny**: produtos excluídos viram descontinuados; `tiny:importar-clientes` (carga
   inicial de contatos, retomável).

## 3. Migrations que vão rodar sozinhas no deploy

| Migration | Efeito |
|---|---|
| `2026_07_30_090000_add_outro_documento_to_pacientes_table` | `pacientes.outro_documento` (string 50, nullable, índice) |
| `2026_08_05_120000_add_cortesia_to_receitas_table` | `receitas.cortesia` (bool, default false) |
| `2026_08_05_140000_add_legado_fields_to_receitas_table` | `receitas.legado_id` (unique), `numero_origem`, `origem` (índice) |

Todas são aditivas e nullable — não há migração de dados nem risco de perda. **Atenção:** o
`hostinger-post-deploy.sh` trata falha de `migrate` como *aviso* e segue em frente
(`AVISO: migrate falhou…`), e o job do Actions termina verde mesmo assim. Depois do deploy,
confira as 3 linhas na tabela `migrations` (ou o log do step "Post-deploy") antes de considerar
concluído.

## 4. Pontos de atenção antes/depois de subir

**a) O importador CLW2 vai aparecer em prod sem nenhum dump para listar.**
`config('legado.sql_path')` aponta por padrão para `docs/clinicaweb/database`, e `/docs/` está no
`.gitignore` — os dumps `bkp_cw2_*.sql` não vão no pacote de deploy. A tela não quebra
(`listSqlDumps()` devolve lista vazia se o diretório não existe), mas só funciona se você subir o
dump por SFTP e apontar `LEGADO_SQL_PATH` no `.env` de produção. **Isso é bom:** o importador
**nunca rodou em produção**; subir o código sem o dump deixa a ferramenta inerte até você decidir.
Quando for rodar: backup do banco antes, e prefira a CLI
(`php artisan migration:importar-legado-incremental --force`) — dry-run/apply pela web rodam
síncronos, numa transação longa e sem lock entre admins, o que na Hostinger tende a estourar
timeout.

**b) Campo "Cortesia" no RD Station.** O default hardcoded é `6a721f71257c0d0020d8178e`
(`rd_cortesia_field_id`). Confirme que esse campo personalizado existe na conta de produção do RD;
caso contrário as negociações passam a mandar um campo que o RD não conhece.

**c) Comandos one-off que provavelmente você vai querer rodar depois do deploy** (nenhum roda
sozinho):
- `php artisan pacientes:normalizar-emails-placeholder` (sem `--apply` lista; com `--apply` grava)
  — conserta os ~150 pacientes com `@cadastrar_email.com`, que hoje dão 422 ao salvar em prod.
- `php artisan migration:backfill-receita-legado-id --force` — popula `legado_id`/`numero_origem`
  a partir das tags `[legado:ID|num:N]` nas anotações. Só faz sentido antes de usar o importador.
- `php artisan tiny:importar-clientes --full` — opcional, carga inicial de contatos do oList.

## 5. Testes (rodados agora, local)

`php artisan test` → **255 passaram, 1 skipped, 2 falharam**. As duas falhas **não têm relação com
os commits pendentes** (nenhum dos arquivos envolvidos foi tocado):

- `Tests\Feature\ExampleTest > the application returns a successful response` — teste de scaffold
  que bate em `/` sem `RefreshDatabase`; morre em `no such table: settings` no sqlite local. Falha
  de ambiente.
- `Tests\Feature\ToolsIntegrationJobsTest > forgetting one duplicate fingerprint…` — o teste insere
  `failed_jobs` com `failed_at` fixo em `2026-05-28/29` e consulta com filtro `days=30`. Passou a
  falhar sozinho por volta de 2026-06-28: bomba-relógio de data no teste, não regressão. Vale
  corrigir (usar `now()->subDays(...)`), mas não bloqueia o deploy.

`npm run build` local falha por incompatibilidade do binário nativo do rollup com o Node 25 desta
máquina — o CI usa Node 22 com `npm ci` limpo, então não é indicativo. Se quiser garantir antes do
push, rode o build dentro do DDEV.

## 6. Outros branches

`origin/chore/diag-conciliar-debora-angela` e `origin/diag/receita-27796-olist-duplicado` têm
commits fora do `main`, mas são só workflows de diagnóstico read-only. Nada a deployar.

## 7. Recomendação

1. Confirmar o campo Cortesia no RD de produção.
2. Backup do banco de produção.
3. `git push origin main` (dispara o deploy sozinho) e acompanhar
   `gh run watch` / `gh run list --workflow=deploy-hostinger.yml`.
4. Depois do deploy: conferir as 3 migrations aplicadas, rodar
   `pacientes:normalizar-emails-placeholder --apply` e validar as telas da seção 8 abaixo.
5. Deixar o importador CLW2 sem dump em prod até você querer rodar o piloto.

## 8. Validação manual pós-deploy (https://clinicaweb.revskin.com.br)

- **Pacientes → Novo**: buscar por nome traz candidatos; salvar sem e-mail funciona; marcar
  estrangeiro habilita "Outro documento"; no celular os campos do drawer empilham.
- **Receitas → Nova**: marcar *Cortesia*, finalizar, e conferir no RD Station que a negociação
  trouxe o campo Cortesia = "Sim".
- **Configurações → Integrações → RD Station**: campo "ID do Campo - Cortesia" visível e salvável.
- **Produtos**: um produto excluído do oList aparece como descontinuado (somente leitura) após o
  próximo `SyncProdutosTinyJob`.
- **Ferramentas → Importação CLW2** (admin): a tela abre e lista "nenhum dump" — comportamento
  esperado em prod até subir o `.sql`.
