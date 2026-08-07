# Subida para produção — EXECUTADA em 2026-08-07

Tudo o que estava pendente foi para produção e as quatro ações pedidas rodaram lá.
`main` local e `origin/main` estão iguais; produção roda o mesmo commit.

---

## 1. Deploy

- Push de **18 commits** (11 pendentes + 7 deste trabalho). Deploy `31221079288`, **sucesso**.
- As **3 migrations aplicaram** (conferi no log, porque o post-deploy engole falha de migrate):
  `add_outro_documento_to_pacientes`, `add_cortesia_to_receitas`, `add_legado_fields_to_receitas`.
- Segundo deploy (`31224313411`, sucesso) no fim, com as melhorias do workflow.
- Site: `HTTP 200` em https://clinicaweb.revskin.com.br antes e depois.

**Dump versionado:** você pediu para versionar, então o dump foi para
`database/legado/bkp_cw2_20260806.sql` — `database/` vai inteiro no pacote (mesma solução já
usada para `database/mapeamento-codigos-legado-base.md`), e `config('legado.sql_path')` aponta
para lá. Confirmei no servidor: 13.946.268 bytes, mesmo tamanho do original. São ~14 MB de dado
de paciente no histórico do git — decisão sua, registrada aqui. Guarde só o dump em uso.

## 2. Achado sério: a Giovana estava duplicada em produção

Antes de aplicar, o dry-run recusou dois tokens. Ao diagnosticar (etapa `diag-medicos`, só
leitura), apareceu isto:

| | `medico #930` | `medico #944` |
|---|---|---|
| nome_legado | Giovana Naccarato Ferreira de Camargo | *(vazio)* |
| CRM | 283163 | 283163 |
| CPF | 43618075820 | *(vazio)* |
| receitas / vínculos | **0 / 0** | **71 / 55** |
| usuário | *(nenhum)* | giovana.naccarato@revskin.com.br |

O **#930 é a casca do legado, órfã**; o registro vivo é o **#944**. Se eu tivesse importado pelo
CPF (o token mais "confiável"), as receitas dela teriam ido para um médico que ninguém enxerga.
Importei pelo e-mail do usuário, e o mapeamento saiu para o #944. **Vale limpar esse par #930
depois** — é um dos pares legado+shell já conhecidos.

Outras correções de token: a Bhertha é `bhertha@revskin.com.br` (com H; o que você passou não
existe) e o CPF dela está **vazio** em prod. O Luiz Fernando é `freitasdr@uol.com.br`.

## 3. Importação CLW2 — 7 médicos

Mapeamento conferido no dry-run e repetido igual no apply, **todos por CRM**:

```
✅ #879 Larissa P. C. Fonseca            ↔ CLW2 #27
✅ #909 Daniela Balizardo Baptista       ↔ CLW2 #977
✅ #881 Sullege Suzuki                   ↔ CLW2 #102
✅ #891 Bhertha Miyuki Tamura            ↔ CLW2 #504
✅ #925 Maria Figueiredo Almeida         ↔ CLW2 #1118
✅ #944 Giovana Naccarato F. de Camargo  ↔ CLW2 #1228
✅ #878 Luiz Fernando S. Paula Freitas   ↔ CLW2 #26
❌ #949 Juliana Maitto Isper      — sem correspondente no dump
❌ #947 Anna Carolina Quinteiro M. — sem correspondente no dump
❌ #946 Luana Leite Castilho Dias  — sem correspondente no dump
❌ #945 Ayme de Oliveira           — sem correspondente no dump
```

Os quatro ❌ confirmam em produção o que eu tinha achado no ensaio: **elas não existem como
médicas no CLW2**, só como pacientes. Não há histórico para importar.

Antes disso rodei o **backfill obrigatório** (`migration:backfill-receita-legado-id --force`):
1.897 receitas ganharam `legado_id`. Sem ele a âncora não existe e o reimport duplicaria paciente.
9 receitas ficaram de fora por conflito — são duplicatas conhecidas que já dividiam a mesma tag
`[legado:…]` (#27765, #27766, #27767, #27775, #27776, #27777, #27792, #27793, #27796).

Resultado do apply: **17 pacientes novos, 4 merges, 137 receitas novas, 0 sinais/conflitos**.

## 4. Carga de clientes do oList

Varredura completa, 14/14 páginas, em duas passadas (~21 min de relógio, limite de ~25 req/min):

| | novos | conciliados | atualizados | ignorados |
|---|---|---|---|---|
| passada 1 (pág. 1–7) | 169 | 487 | 21 | 36 |
| passada 2 (pág. 8–14) | 115 | 441 | 23 | 51 |
| **total** | **284** | **928** | **44** | **87** |

A conciliação evitou 928 duplicatas: 728 por celular + nome, 107 por e-mail + nascimento, 41 por
nascimento + nome, 36 por CPF, 16 por e-mail único + nome. 73 dos importados têm homônimo sem
dado em comum para confirmar — o médico decide na busca por nome, que mostra os dois lado a lado.

## 5. Normalização de e-mails — nada a fazer

`pacientes:normalizar-emails-placeholder` respondeu **"Nenhum e-mail de marcação para
normalizar"**. O comando procura exatamente `@cadastraremail.com` e `@cadastrar_email.com`, que
eram os domínios quebrados; em produção não sobrou nenhum. Não é falso negativo — é o próprio
critério do comando. Os ~150 de que falávamos já tinham sido resolvidos (a carga do oList
reescreve o placeholder no domínio bom).

## 6. Limpeza dos cadastros de teste

35 candidatos em produção; **apaguei 34, e 49 receitas em cascata**. Nenhum falso positivo tipo
"Nicodemos"/"Modesto" apareceu na lista.

**Deixei um de fora de propósito: `#17326 Paulo Teste RVK2`** — tem 16 receitas e **8 aquisições
com pedido real no oList**. O comando bloqueia esse caso justamente por ser "coisa autêntica"
(gerou transação no ERP). Se quiser removê-lo mesmo assim, é
`--ids=17326 --force --ignorar-alertas` — mas os pedidos continuam no oList.

Cinco dos apagados tinham contato no oList, que **não** é apagado de lá: ids 17556, 17558, 17561,
17831, 17870. Remova pelo oList se quiser.

Curiosidade útil: o `#17831 Paulo Teste RVK2` (0 receitas) foi criado **hoje pela própria carga do
oList** — a base do oList também tem contatos de teste.

## 7. Invariantes — tudo fecha exatamente

| | antes | depois | conta |
|---|---|---|---|
| pacientes | 1.165 | **1.432** | +17 CLW2 +284 oList −34 limpeza ✅ |
| receitas | 1.936 | **2.024** | +137 CLW2 −49 limpeza ✅ |
| receita_itens | 22.190 | 22.885 | |
| aquisições | 7.142 | 7.126 | −16, todas de receita de teste |
| **aquisições com pedido no oList** | **53** | **53** | **nenhum pedido real perdido** ✅ |
| medico_paciente | 1.166 | 1.154 | +19 do import, −31 da limpeza |
| receitas com legado_id | 1.897 | 1.988 | |
| órfãos (itens / aquisições / receitas sem paciente) | 0 / 0 / 0 | **0 / 0 / 0** | ✅ |

## 8. Como validar à mão

https://clinicaweb.revskin.com.br — entrar como admin e:

1. **Pacientes** → buscar "Bhertha" ou abrir a lista de uma das 7 médicas: devem aparecer os
   pacientes trazidos do CLW2, com as receitas antigas no histórico.
2. Entrar como uma das médicas (ex.: `sullege@revskin.com.br`) e conferir que ela enxerga os
   pacientes liberados.
3. **Ferramentas → Importação CLW2**: a tela lista `bkp_cw2_20260806.sql`. Um novo dry-run tem
   que dar quase tudo zero (só volta o lixo de teste que apaguei — ver abaixo).
4. Buscar "teste" em Pacientes: deve sobrar só o `Paulo Teste RVK2` (#17326).

Tudo isso também roda pelo Actions, workflow **"Migração CLW2 / limpeza (PRODUÇÃO)"**, uma etapa
por execução: `contagens`, `diag-medicos`, `import-dry-run`, `limpeza-listar` etc.

## 9. O que ficou pendente para você

1. **`medico #930` (Giovana) órfão** — casca do legado com 0 receitas e 0 vínculos; vale remover
   ou conciliar. Provavelmente há outros pares assim.
2. **`#17326 Paulo Teste RVK2`** — decidir se apaga apesar dos 8 pedidos no oList.
3. **Contatos de teste no oList** (ids 17556, 17558, 17561, 17831, 17870) — limpar por lá.
4. **Campo "Cortesia" no RD Station de produção** — o default hardcoded é
   `6a721f71257c0d0020d8178e`; continua sem confirmação de que existe na conta.
5. **Reimportar o CLW2 traz o lixo de teste de volta** (o `xxxteste` e companhia moram no dump).
   Depois de qualquer reimport, rodar `limpeza-listar` / `limpeza-apagar` de novo.
6. **O SSH da Hostinger é intermitente** vindo do runner: várias execuções morreram em
   `dial tcp: i/o timeout` sem nem conectar. Subi o timeout para 120s e mesmo assim acontece —
   é só repetir a etapa; todas são idempotentes ou retomáveis.
