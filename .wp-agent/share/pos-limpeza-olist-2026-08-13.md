# Depois da limpeza no oList — o que faltava era do nosso lado

**13/08/2026** · Sim, faltava. Havia um bug nosso que engolia exatamente as correções que o cliente
fez. Já está corrigido e aplicado em produção.

| | Antes | Agora |
|---|---|---|
| Nascimentos impossíveis | 69 | **0** |
| Cadastros de teste | 34 | **0** |
| Pacientes | 1.363 | 1.329 |

---

## O achado principal: as datas corrigidas não estavam chegando

O cliente corrigiu as 69 datas no oList ontem à noite. Nenhuma tinha chegado na ClinicaWeb — e o
mais enganoso é que o **nome das mesmas pessoas** sincronizou normalmente na mesma rodada, o que dava
a impressão de que o sync estava funcionando.

A causa: a lista de contatos do oList devolve só isto —

```
id, codigo, nome, fantasia, tipo_pessoa, cpf_cnpj, endereco, numero,
complemento, bairro, cep, cidade, uf, email, fone, situacao, data_criacao
```

Não tem **data de nascimento**, não tem **celular**, não tem **sexo**. Para um paciente que já
conhecíamos, o sync se contentava com essa lista, para economizar uma chamada de API. Resultado:
esses três campos só entravam na primeira importação do contato e nunca mais eram relidos. Corrigir
no oList não adiantava nada.

Conferi contato a contato pela própria API antes de mexer:

| Contato | No oList | Estava aqui |
|---|---|---|
| Adriana Lima Gürtler | 10/12/1973 | 2073-12-10 |
| Adriana Lúcia Almeida Vaz | 06/10/1969 | 2069-10-06 |
| Aline Hastenreiter Rodrigues | 19/09/1982 | 2082-09-19 |

**O que mudou:** agora a ficha completa do contato é relida periodicamente (a cada 6 horas por
contato). Não dá para reler a cada rodada porque o filtro do oList é por dia — um contato alterado
hoje reaparece na lista a cada 10 minutos durante dois dias, e seriam milhares de chamadas à toa.

Além da correção, rodei a releitura na hora: **68 de 68 datas** vieram do oList e foram gravadas,
sem nenhum erro. O que o cliente digitou lá está aqui.

## A grafia dos nomes: já resolveu sozinho

Esse ponto do cliente estava certo na observação, mas a lista que enviei foi gerada **antes** das
edições dele. Quando ele editou os contatos, os nomes sincronizaram:

| Na lista que enviei | Agora na ClinicaWeb |
|---|---|
| ADRIANA VAZ | Adriana Lúcia Almeida Vaz |
| ALINE RODRIGUES | Aline Hastenreiter Rodrigues |
| ANDREA NEVES | Andrea Reis de Almeida Neves |

Vale explicar a regra, porque ela ainda vale para os outros: **o nome daqui só é atualizado quando
o contato é editado no oList.** Não é falta de sincronia — os cadastros estão ligados ao contato
certo. É por isso que `ADRIANA C KANTOWITZ GANDOLPHO` continua em maiúsculas: aquele contato não foi
editado. Na próxima vez que mexerem nele, o nome se ajusta.

Se preferirem alinhar todos de uma vez com a grafia do oList, dá para fazer — é uma passada só, me
avisem.

## Cadastros de teste

Removidos os 34 (`zzz-Marcelo`, `zzz Elaine`, `aaaaPaulo` e companhia) com 39 receitas em cascata.
Antes de apagar, o sistema conferiu que nenhum tinha pedido de verdade no oList e que nenhuma receita
de paciente real tinha saído de uma delas. Backup do banco tirado antes.

Um detalhe: o `zzzz Marcelo` (#16957) tinha contato no oList (758324971). Se esse contato ainda
existir lá e alguém editar, o cadastro volta para cá. Vale conferir se ele saiu junto com os outros
de teste.

---

## O que ainda depende de vocês

### 1. Duas duplicatas novas — só preciso de um "pode juntar"

Estas apareceram **por causa** da sincronia de nomes: os dois cadastros passaram a ter a grafia do
oList e ficaram idênticos, o que antes escondia a repetição.

**Gerson Jacob Delazeri**
- #16727 — 24/10/1974, (55) 99960-5411, com CPF, 1 receita
- #16972 — 24/10/1974, (55) 99960-5411, sem CPF, 1 receita

**Angela Maria Rosin Saad**
- #17222 — 31/12/1971, (11) 99344-0486, sem CPF, 3 receitas
- #17389 — 31/12/1971, (11) 99344-0486, com CPF, 0 receitas

Nos dois casos a data de nascimento e o celular batem inteiros, DDD incluído — pelo critério que
vocês mesmos usaram da última vez, é a mesma pessoa. Não juntei por conta própria porque decidir
quem é quem é de vocês. Um "pode" e eu faço.

Pode aparecer mais um caso assim conforme os nomes forem sincronizando.

### 2. Mara Sandra Rodrigues Campos Zandona

Continua com dois contatos no oList (763541264 e 756979841). É a Parte 3, que ficou pendente.

### 3. Patricia Pereira Lopes

Segue sem resposta:
- #17062 — 01/01/1999, (19) 99999-9999, 1 receita
- #17220 — 03/05/1989, (19) 99256-8301, 6 receitas

O #17062 tem cara de cadastro apressado. Se for a mesma pessoa, junto no #17220.

### 4. Nada a fazer

Os 6 que vocês marcaram como pessoas diferentes seguem separados, como combinado: Gabriella
Valentim, Claudia Dantas, Claudia Marçal, Hellen Uliam Uriki, Ana Cristina Gomes de Araujo e
Theodora Ribeiro Paredes.

---

## Conferência

Depois de tudo aplicado: nenhuma receita, item ou aquisição órfã, nenhum `tiny_id` repetido, nenhum
job de integração falho nas últimas 24 horas e nenhum cadastro novo criado desde a limpeza — ou
seja, as duplicatas não voltaram.
