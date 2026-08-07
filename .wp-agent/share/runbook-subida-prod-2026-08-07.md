# Runbook — subida para produção (2026-08-07)

**Nada foi pushado nem tocado em produção neste job.** O que segue é o ensaio local do
importador com o dump novo, um bug de duplicação que apareceu no ensaio e foi corrigido, e a
ordem exata das operações em prod.

Local agora está **14 commits à frente** de `origin/main` (eram 11; +3 deste job).

---

## 1. Resultado do ensaio: 7 dos 11 médicos têm dado no CLW2

O dump novo (`bkp_cw2_20260806.sql`, 6/8 19:49, 13 MB) tem **78 médicos**. Os quatro abaixo
**não estão entre eles** — aparecem no dump só como *pacientes*, nunca como prescritores:

| Médica(o) pedida(o) | Situação no dump CLW2 |
|---|---|
| Juliana Maitto Ispero | só como paciente (`JULIANA MAITTO DRA.`, legado #1483) |
| Anna Carolina Quintero Martinez | só como paciente (legado #1266) |
| Luana Leite Castilho Dias | só como paciente (legado #1265, `Luluhcastilho@gmail.com`) |
| Ayme de Oliveira | só como paciente (legado #1234) |

Ou seja: **não há nada para importar** para essas quatro. Se forem selecionadas junto com as
outras, o mapeamento mostra "Sem correspondente no dump CLW2" e elas são ignoradas; se forem
selecionadas **sozinhas**, o comando aborta com "Nenhum dos médicos selecionados casou com o
dump". Nenhum dos dois casos é erro de verdade — é ausência de histórico.

As sete restantes casaram e foram de fato importadas no ensaio local:

| CLW3 (local) | CLW2 | casou por |
|---|---|---|
| #878 Luiz Fernando S. Paula Freitas | #26 | CRM |
| #879 Larissa P. C. Fonseca | #27 | CRM |
| #881 Sullege Suzuki | #102 | CPF |
| #891 Bhertha Miyuki Tamura | #504 | CPF |
| #909 Daniela Balizardo Baptista | #977 | CPF |
| #925 Maria Figueiredo Almeida | #1118 | CPF |
| #930 Giovana Naccarato Ferreira de Camargo | #1228 | CPF |

> Os ids CLW3 acima são do **banco local**, que é mais antigo que produção (as quatro médicas
> que faltam existem em prod e não aqui). Em produção confira o mapeamento na saída do dry-run
> antes de aplicar — prod tem pares de usuário legado + shell, e o token de e-mail pode resolver
> para o `Medico` errado.

## 2. Bug encontrado e corrigido: reimport duplicava paciente

Primeira rodada do apply: 15 pacientes novos, 125 receitas. **Segunda rodada criou mais 4
pacientes** — Aline Pinho Mariath, GUSTAVO RABELLO, Priscila Azuaga e xxxteste. Não era no-op,
e a cada nova rodada criaria de novo.

Causa: paciente sem CPF, sem código, sem nascimento válido e sem celular não é reencontrado por
nenhuma heurística; como existe homônimo no CLW3, o conciliador desiste **de propósito** e cria
um cadastro novo. Como nada no cadastro guarda o id do CLW2, isso se repetia toda execução.
(No caso da Aline o telefone até existia, mas em `telefone1`, e o conciliador só olhava
`celular` do lado do legado.)

Correção (`5cd6371`): a **receita** guarda o id do CLW2 (`receitas.legado_id`) e todo paciente
importado tem receita — o filtro exige isso. Então a receita já importada prova qual cadastro
do CLW3 corresponde ao paciente do dump. O importador monta `legado_paciente_id => paciente_id`
uma vez por execução e consulta essa âncora antes de qualquer heurística.

Efeito no mesmo ensaio, do zero (snapshot restaurado):

| | antes da correção | depois |
|---|---|---|
| pacientes_novos | 15 | **8** |
| pacientes_merge | 7 | 6 |
| pacientes_needs_review | 13 | **1** |
| sinais/conflitos | 11 | **2** |
| receitas_novas | 125 | 125 |
| 2ª rodada | +4 pacientes | **no-op total** |

Invariantes depois do apply corrigido: aquisições seguem em **7.089** (zero perda), as **1.923**
receitas e **21.969** itens pré-existentes ficam **byte a byte idênticos** (checksum igual,
inclusive `updated_at`), zero órfão em itens/aquisições/receitas, e nenhum nome duplicado criado.

> **Isto torna `migration:backfill-receita-legado-id --force` obrigatório em produção**, e
> **antes** da importação: lá as receitas da carga inicial só têm a tag `[legado:ID|num:N]` nas
> anotações; sem o backfill a âncora fica vazia e a duplicação volta.

## 3. Como o dump chega em produção

`docs/` está no `.gitignore`, então o dump nunca entra no pacote de deploy — de propósito, são
13 MB de dado de paciente que não devem ir para o git. Criei `scripts/enviar-dump-legado.sh`:
manda o `.sql` por rsync para `revskin/storage/app/legado/`, que fica dentro da pasta protegida
por `.htaccess` (`Require all denied`) e é **excluída do rsync do deploy** — então o arquivo
sobrevive aos deploys seguintes.

```bash
HOSTINGER_HOST=<host> HOSTINGER_USER=<usuario> HOSTINGER_PORT=<porta> \
  scripts/enviar-dump-legado.sh docs/clinicaweb/database/bkp_cw2_20260806.sql
```

Host, usuário e porta são os mesmos secrets do deploy (não consigo lê-los — secret do GitHub não
é legível). A chave `~/.ssh/revskin_hostinger` já está nesta máquina. Depois acrescente no
`.env` de produção (o script imprime a linha pronta) e rode `config:cache`:

```
LEGADO_SQL_PATH=/home/u368085046/domains/clinicaweb.revskin.com.br/public_html/revskin/storage/app/legado
```

## 4. Ordem das operações em produção

PHP na Hostinger: `/opt/alt/php84/usr/bin/php`. App em
`…/public_html/revskin`. Backup já feito (você confirmou).

```bash
# 1) Deploy — push em main dispara o Actions sozinho
git push origin main && gh run watch

# 2) Conferir que as 3 migrations entraram (o post-deploy engole falha de migrate!)
php artisan migrate:status | tail -5
#    esperado: add_outro_documento_to_pacientes, add_cortesia_to_receitas,
#              add_legado_fields_to_receitas

# 3) OBRIGATÓRIO antes de importar: popular receitas.legado_id
php artisan migration:backfill-receita-legado-id --dry-run   # confere o volume
php artisan migration:backfill-receita-legado-id --force

# 4) Dump no servidor + LEGADO_SQL_PATH no .env + php artisan config:cache  (seção 3)

# 5) Importação CLW2 — dry-run primeiro, CONFERINDO o mapeamento médico a médico
php artisan migration:importar-legado-incremental \
  --sql=storage/app/legado/bkp_cw2_20260806.sql \
  --medicos=larissa.fonseca@revskin.com.br,dbalizardo@revskin.com.br,sullege@revskin.com.br,bertha@revskin.com.br,mfigueiredo@revskin.com.br,<giovana>,<luiz-fernando> \
  --dry-run
# só depois, com a mesma seleção:
php artisan migration:importar-legado-incremental --sql=... --medicos=... --force

# 6) Carga de clientes do oList (retomável; ~25 req/min, roda quantas vezes precisar)
php artisan tiny:importar-clientes --full --budget=200

# 7) Normalizar os e-mails de marcação quebrados (~150 cadastros que hoje dão 422 ao salvar)
php artisan pacientes:normalizar-emails-placeholder            # lista
php artisan pacientes:normalizar-emails-placeholder --apply    # grava

# 8) Limpeza dos cadastros de teste — POR ÚLTIMO (ver seção 5)
php artisan pacientes:remover-teste                            # lista de sugestão
php artisan pacientes:remover-teste --ids=<ids conferidos> --force
```

Por que essa ordem:
- **backfill antes da importação**: sem `receitas.legado_id` a âncora não existe e o reimport
  duplica paciente (seção 2).
- **oList depois do CLW2**: a conciliação do pull do oList é a mais completa (CPF, e-mail +
  nascimento, celular + nome), então é ela que deve absorver o que o CLW2 acabou de criar.
- **limpeza por último**: importação recria cadastro de teste.

Rode a importação pela CLI e em janela de baixo movimento: dry-run e apply são síncronos, numa
transação longa e sem lock entre admins. Pela tela (Ferramentas → Importação CLW2) tende a
estourar timeout na Hostinger.

## 5. Limpeza dos cadastros de teste

Comando novo: `pacientes:remover-teste`. A busca por "test/teste/demo" no nome é **sugestão,
nunca critério de exclusão** — "Luciana Nicodemos Salles" casa com `demo` e "Maria Modesto
Prestes" casa com `test`. Por isso:

- sem argumento: só lista os candidatos, com nº de receitas, se tem contato no oList e alertas;
- `--force` **sem `--ids` é recusado** — a lista tem que ser conferida por gente;
- antes de apagar, bloqueia quem tem sinal de dado autêntico: aquisição com pedido no oList, ou
  receita de outro paciente derivada desta (use `--ignorar-alertas` se for teste mesmo);
- apaga por query builder, sem acordar o `PacienteObserver` — não dispara sync para o oList;
- o banco cascateia de `pacientes` para receitas, itens, aquisições, vínculos, telefones e
  atendimentos; `receita_origem_id` de terceiros vira NULL.

Ensaio local: 34 candidatos, **34 pacientes e 55 receitas removidos**, aquisições de 7.089 →
7.060, zero órfão. O padrão dos nomes é o do CLW2 — `zzz teste …`, `xxxteste`, `ZZ TESTE`,
`Teste Lapidare T5`, `teste_ ?????` — mais os do próprio CLW3 (`Paciente de Demonstração`,
`Ale Ale` com e-mail `ale@ale.test`, `Teste Dra Juliana Probe …`).

Dois avisos:
1. **Contatos correspondentes no oList/RD Station não são apagados** — o comando avisa quais ids
   tinham integração; remova por lá se quiser.
2. **Reimportar o CLW2 traz o lixo de volta.** Medido aqui: depois de apagar os 34, um novo
   dry-run do mesmo dump quer criar **24 pacientes e 41 receitas** — os cadastros de teste que
   vivem no CLW2 e pertencem a esses 7 médicos. Não é regressão da âncora (ela mora na receita,
   que foi apagada junto). Ou seja: a limpeza é a **última etapa de cada médico liberado**, e
   basta repetir o comando depois de um reimport. Se preferir, dá para ensinar o importador a
   pular esses nomes e listá-los no report em vez de criar — não fiz porque muda o comportamento
   de "importar fiel ao dump".

Não removi médico nenhum. Em produção provavelmente também existem usuários/médicos de teste
(no banco local há `Darvin Teste Médico`, `simios@test.test`, `demo.medico1/2@exemplo.test`,
`John Doe`) — se quiser limpar isso é outro comando, me avise.

## 6. Testes

`php artisan test`: **261 passando, 1 skipped, 0 falhas**. Além dos 4 testes novos (regressão da
âncora + 3 do comando de limpeza), consertei dois testes que já estavam vermelhos e não tinham
relação com nada disto: `ToolsIntegrationJobsTest` (data fixa de maio com filtro de 30 dias,
falhava sozinho desde o fim de junho) e `ExampleTest` (faltava `RefreshDatabase`).

## 7. O que ainda depende de você

1. **Rodar o dry-run em produção para os 7 e conferir o mapeamento** — os ids de médico em prod
   são diferentes dos daqui, e há pares legado+shell que podem resolver para o `Medico` errado.
2. **Host/usuário/porta da Hostinger** para o script do dump (secrets que não consigo ler).
3. **Confirmar o campo "Cortesia" no RD Station de produção** — o default hardcoded é
   `6a721f71257c0d0020d8178e`.
4. **Aprovar a lista de ids da limpeza** em produção (a lista de lá não é a mesma daqui).
5. Decidir se quer o push agora: `git push origin main` publica direto.
