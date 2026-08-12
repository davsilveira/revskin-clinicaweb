# Cadastros duplicados — o que aconteceu de verdade

**Data:** 12/08/2026 · **Conferido em produção hoje** (censo read-only, execução
[31624612314](https://github.com/davsilveira/revskin-clinicaweb/actions/runs/31624612314)) e reproduzido
localmente sobre o dump de prod de 11/08. **Nada foi alterado** — nem em prod, nem no oList.

---

## Resposta curta

**Não foi a importação do CLW2.** São **dois problemas diferentes** que o cliente está vendo na
mesma tela e juntando num só relato:

| | O que é | Quantos | Causa | O que fazer |
|---|---|---|---|---|
| **A** | Cadastro novo, vazio, criado pela **carga de contatos do oList** de 07/08 | **74** | O contato do oList não tinha nenhum dado em comum com o cadastro que já existia aqui | Fundir no cadastro bom e apagar a casca — **nenhum tem receita** |
| **B** | Duas fichas **antigas**, as duas com receita, da migração original de 16/06 | **13 grupos / 28 fichas** | No CLW2 a ficha do paciente é **por médico**: quem se consultou com dois médicos tem duas fichas | Fundir de verdade (mesclar históricos) — **nenhuma é "morta"** |

A importação incremental do CLW2 (07/08, 18:57) criou **17 pacientes e nenhum homônimo**. Está limpa.
O que ela fez foi *acender* o problema B: gravou 4 receitas novas na ficha #17401 da Giovana, que é
justamente a ficha duplicada — então a duplicidade, que já existia desde junho, passou a aparecer na
lista de quem usa o sistema todo dia.

---

## O caso da Giovana, que é o do print

Existem **quatro** registros com o nome dela, em dois pares que não têm nada a ver um com o outro:

**Como médica:**

| | `médico #930` | `médico #944` |
|---|---|---|
| nome_legado | Giovana Naccarato Ferreira de Camargo | *(vazio)* |
| CRM / CPF | 283163 / 43618075820 | 283163 / *(vazio)* |
| receitas / vínculos | **0 / 0** | **97 / 58** |
| usuário | — | giovana.naccarato@revskin.com.br |

O **#930 é casca do legado e está morto** — é esse o "cadastro morto" que já estava anotado como
pendência desde a subida de 07/08. Pode ser removido.

**Como paciente — e aqui está o problema:**

| | `paciente #16435` | `paciente #17401` |
|---|---|---|
| médico | #878 Luiz Fernando S. Paula Freitas | #944 Giovana (ela mesma) |
| receitas | **4** (2025) | **8** (2026, incl. a 17401-0008 de 11/08) |
| aquisições | **5** | **13** |
| CPF / e-mail | 436.180.758-20 / giovanaccacamargo@gmail.com | — / — |
| contato no oList | 762351809 | — |

**Nenhuma das duas é morta.** As duas têm receita e compra. Apagar qualquer uma perde histórico
real — inclusive as 5 ou 13 aquisições, que são as compras dela.

É por isso que a ficha #17401 aparece "sem os dados cadastrais do ERP" e a atendente teve de digitar
tudo de novo: o CPF e o e-mail moram na #16435, e a #17401 é a que está em uso.

**Por que existem duas:** no CLW2 o paciente pertence a um médico. A Giovana se consultou com o
Dr. Luiz Fernando **e** consigo mesma, então ela tem duas fichas lá; a migração de junho trouxe uma
ficha do CLW3 para cada ficha do CLW2. O mesmo padrão explica os outros 12 grupos — e não é
coincidência que vários deles sejam médicas que também se tratam na clínica: Priscilla Nunes Ortiz,
Roberta de Andrade Paula Saldanha e Viviane Kohatsu Noda estão exatamente na mesma situação.

---

## Problema A — as 74 cascas do oList

Todas criadas entre **19:03 e 19:28 de 07/08**, na varredura de contatos do oList. Perfil idêntico:
nome + `tiny_id`, e **nada mais**.

**Estão vazias, confirmado em produção hoje:**

| Checagem | Resultado |
|---|---|
| receitas | **0** |
| vínculos com médico | **0** |
| aquisições | **0** |
| telefones (`paciente_telefones`) | **0** |
| atendimentos de call center | **0** |
| contato no RD Station | **0** |
| alguma passou a ser usada desde 11/08 | **0** |

Ou seja: dá para removê-las sem perder nada. Mas **remover sozinho não resolve** — ver a ressalva
mais abaixo.

**Por que a conciliação não pegou** (a regra exige CPF, ou e-mail, ou celular, ou data de nascimento
batendo — nome igual sozinho nunca funde, de propósito, para não juntar dois "João da Silva"):

| Motivo | Casos |
|---|---|
| O contato do oList só tinha o nome — nenhum dado para comparar | **48** |
| Celular sem o 9 (`(65) 9983-4083` no oList × `(65) 99983-4083` aqui) | **11** |
| Celular realmente diferente entre os dois cadastros | **10** |
| Data de nascimento com o século trocado (`2071` no lugar de `1971`) | **2** |
| Outro | **1** |
| Duplicata dentro do próprio oList (dois contatos, nenhum cadastro antigo) | **2** |

As duas primeiras linhas são **defeito nosso e dá para corrigir**: comparar telefone só pelos 8
últimos dígitos resolveria 11 casos de uma vez, e recusar data de nascimento no futuro resolveria
mais 2. As outras são divergência real de cadastro, que precisa de olho humano.

**Detalhe importante: 17 dessas cascas trazem CPF que o cadastro bom não tem, 11 trazem e-mail de
verdade e 2 trazem a data de nascimento.** Não é lixo puro — apagar direto joga fora dado do ERP.
Por isso a recomendação é fundir, não deletar.

Lista completa com o "manter × apagar" de cada um: [`duplicados-cascas-olist.csv`](duplicados-cascas-olist.csv).

**13 delas eu não fundiria automaticamente**, porque a data de nascimento diverge entre as duas
fichas (pode ser mãe e filha com o mesmo nome) ou porque os dois lados têm contato no oList:

`17743` Jucineide · `17712` Gabriella Valentim · `17631` Angelica Trento Carvalho ·
`17664` Claudia Dantas · `17665` Claudia Marçal · `17727` Hellen Uliam Uriki ·
`17698` Erika Palmer · `17685` Doralice da Mata Pereira · `17621` Ana Cristina Gomes de Araujo ·
`17627` Andrea Cristina da Silva · `17702` Fabiane de Castro Tamberi ·
`17655` Carolina Canto de Macedo Villar · `17874` Theodora Ribeiro Paredes

Mais duas onde o **cadastro a manter é ambíguo** porque o antigo já era duplicado: **Jucineide**
(#16488 com 12 receitas e #16615 com 1) e **Daniela Cosim da Silva** (#16569 e #16570, uma receita
cada).

---

## A ressalva que mais importa: apagar sozinho não segura

A varredura do oList roda **a cada 10 minutos** (`routes/console.php`) e cria cadastro novo por
padrão. Se apagarmos a casca **sem levar o `tiny_id` para a ficha boa**, basta alguém editar aquele
contato no oList para o cadastro vazio nascer de novo — com outro id, e o problema volta.

Então a ordem correta em cada caso é:

1. levar da casca para a ficha boa o que ela tem a mais (CPF, e-mail de verdade, `tiny_id`);
2. **só então** apagar a casca.

Nos 2 casos em que os dois lados já têm `tiny_id` (Erika Palmer, Doralice da Mata Pereira) o oList
tem mesmo dois contatos da mesma pessoa — esses precisam ser resolvidos **lá**, não aqui.

---

## Problema B — os 13 grupos antigos

Detalhe de cada um em [`duplicados-preexistentes-clw2.csv`](duplicados-preexistentes-clw2.csv).

| Paciente | Fichas | Receitas | Médicos |
|---|---|---|---|
| Giovana Naccarato Ferreira de Camargo | 16435 / 17401 | 4 / 8 | Luiz Fernando / a própria Giovana |
| Maria Otilia Teixeira Abali | 16653 / 16673 | 4 / 1 | Paula Chicralla / Larissa |
| Viviane Kohatsu Noda | 16683 / 17187 | 2 / 1 | Larissa / a própria Viviane |
| Roberta de Andrade Paula Saldanha | 16693 / 16781 / 17338 | 1 / 1 / 1 | Eloisa / Larissa / a própria Roberta |
| Mariah Pedrosa Lorentzen | 16776 / 17375 | 1 / 1 | Paula Chicralla / Eloisa |
| Priscilla Nunes Ortiz | 17039 / 17192 | 1 / 1 | Larissa / a própria Priscilla |
| Patricia Pereira Lopes | 17062 / 17220 | 1 / 6 | John Doe / Luiz Fernando |
| Jucineide Auxiliadora Ribeiro Leite | 16488 / 16615 | 12 / 1 | Sullege (as duas) |
| Daniela Cosim da Silva | 16569 / 16570 | 1 / 1 | Glaucia (as duas) |
| Maria Rosineide Ferreira Rocha | 16566 / 16568 | 1 / 1 | Glaucia (as duas) |
| ZZZ Marcelo | 16610 / 16646 / 17107 | 1 / 1 / 1 | teste |
| ZZZ Marcelo1 | 16611 / 16648 | 2 / 1 | teste |
| ZZZ Marcelo3 | 16617 / 16640 | 2 / 1 | teste |

Os sete primeiros são o padrão "um médico, uma ficha" do CLW2: a mesma pessoa se consultou com dois
médicos e ganhou duas fichas. Os três seguintes (Jucineide, Daniela, Maria Rosineide) são de outro
tipo — **as duas fichas são do mesmo médico** e o nome vem duplicado já no CLW2, com um caractere a
mais no fim: `Jucineide Auxiliadora Ribeiro Leite_`, `Daniela Cosim da Silva.`,
`Maria Rosineide Ferreira Rocha.`. Foi digitação repetida lá atrás, e a migração copiou fielmente os
dois. Os três "ZZZ Marcelo" são cadastro de teste e podem ir embora junto com as receitas.

Vale registrar: enquanto essas fichas existirem em duplicidade **no CLW2**, qualquer reimportação
incremental continua alimentando os dois lados. Corrigir só aqui não impede a divergência de voltar
a crescer.

**Duas armadilhas na fusão, que valem para os dois problemas:**

- **O número da receita embute o id do paciente** (`17401-0008`). Mover uma receita de ficha exige
  renumerar, senão a receita `16435-0001` fica pendurada num paciente `17401`. O importador do CLW2
  já tem essa lógica de renumeração; a ferramenta de fusão precisa reaproveitá-la.
- **`receitas` e `medico_paciente` apagam em cascata** (`ON DELETE CASCADE`). Apagar a ficha errada
  leva junto receita, item e aquisição, sem aviso. Toda fusão tem de mover primeiro e apagar depois,
  dentro de uma transação.

---

## Integridade — a base está sã

Conferido em produção hoje:

| | |
|---|---|
| itens de receita órfãos | **0** |
| aquisições órfãs | **0** |
| receitas sem paciente | **0** |
| `tiny_id` repetido em dois pacientes | **0** |
| datas de nascimento no futuro | **70** ⚠️ (63 vêm da migração de junho, 7 da carga do oList) |

As 70 datas no futuro são dado ruim de origem — no dump do CLW2 a data vem como texto de 2 dígitos
(`'13/04/71'`) e em algum ponto o `71` virou `2071` em vez de `1971`. Não quebra nada, mas atrapalha
justamente a conciliação por data de nascimento e aparece errado na tela.

---

## O que eu preciso que você decida

1. **Fundir ou só apagar as 74 cascas?** Recomendo fundir (leva CPF/e-mail/`tiny_id` para a ficha
   boa e evita que voltem). São 59 automáticas + 13 para revisar + 2 para resolver no oList.
2. **Os 10 grupos antigos de gente real** — confirmar com a clínica que são mesmo a mesma pessoa
   antes de qualquer fusão. Sugiro começar pela Giovana, que é o caso que o cliente já viu.
3. **Na Giovana, qual ficha fica?** Minha sugestão é **manter a #17401** (é a que está em uso, tem 8
   receitas e 13 aquisições), mover para ela as 4 receitas e o vínculo com o Dr. Luiz Fernando da
   #16435, e copiar CPF, e-mail e `tiny_id`. Move-se menos coisa e renumera-se menos.
4. **Os 3 "ZZZ Marcelo"** — posso apagar junto com as receitas de teste?
5. **`médico #930`** (casca da Giovana, 0 receitas / 0 vínculos) — posso remover?
6. **As fichas duplicadas no próprio CLW2** (Jucineide, Daniela, Maria Rosineide e as fichas da
   Giovana) — vale pedir à clínica que resolva lá também, senão a próxima importação recria a
   divergência.

## Passos, quando você aprovar

Ainda **não existe** ferramenta de fusão de paciente no sistema (só `pacientes:remover-teste`, que
apaga em cascata e não serve aqui). Então a sequência seria:

1. Escrever o comando `pacientes:fundir --manter=X --apagar=Y [--dry-run]`, que dentro de uma
   transação move receitas (renumerando), vínculos, telefones e atendimentos, completa os campos
   vazios da ficha que fica, transfere o `tiny_id` e só então apaga a outra — com as invariantes
   conferidas antes e depois.
2. Corrigir a conciliação do oList: comparar telefone pelos 8 últimos dígitos e recusar data de
   nascimento no futuro. Isso para a sangria dos 13 casos mais óbvios.
3. Dump de prod → rodar tudo local → conferir as contagens → só então aplicar em produção, com
   backup imediatamente antes.
4. Rodar a limpeza das 74 cascas, depois os grupos antigos aprovados um a um.

Enquanto isso a varredura do oList continua rodando a cada 10 minutos, então cadastro novo repetido
pode aparecer. Desde 11/08 apareceram 3 pacientes novos e **nenhum** é homônimo, então o ritmo é
baixo — mas o mecanismo continua ligado.

---

## Como conferir à mão

Produção: https://clinicaweb.revskin.com.br → **Pacientes** → buscar `giova`, `kantowitz`,
`jucineide`, `roberta de andrade`. As mesmas telas estão reproduzidas nas capturas
`6a7b0022-*` em `.wp-agent/screenshots/`, tiradas do dump de prod de 11/08 rodando local.

O censo em produção pode ser repetido quando quiser, sem risco: Actions → **"Diag pacientes
duplicados (PRODUÇÃO, read-only)"**.
