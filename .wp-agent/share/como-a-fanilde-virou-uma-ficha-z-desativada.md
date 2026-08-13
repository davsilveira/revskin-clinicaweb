# Como a Fanilde virou uma ficha `z-` desativada — passo a passo

Duas perguntas, respondidas com o dump do ClinicaWeb (06/08) e o código da carga na mão.

## 1. Todas as ocorrências dela estavam desativadas no ClinicaWeb? Não — são 5, e uma está ativa

| ClinicaWeb | nome como está lá | ativo | CPF | nascimento | criada em |
| --- | --- | --- | --- | --- | --- |
| #240 | `Fanilde Pirro Viana Paquer ` | não | — | 25/08/1988 | 24/04/2025 |
| #573 | `z-Fanilde Pirro Viana Paquer` | não | 850.146.051-68 | 09/06/1978 | 23/06/2025 |
| #577 | `zzzFANILDE PIRRO VIANA PAQUER` | não | 850.146.051-68 | 09/06/1978 | 24/06/2025 |
| #579 | `zzzFanilde Paquer` | não | — | 09/06/1978 | 24/06/2025 |
| **#594** | **`Fanilde Pirro Viana Paquer`** | **sim** | 850.146.051-68 | 09/06/1978 | 26/06/2025 |

Quatro desativadas e uma ativa: a #594, a última criada, é a ficha viva — a que vocês abrem no
ClinicaWeb e a que aparece nas fotos. Note o padrão: em três dias (23 a 26/06/2025) a equipe criou e
descartou três cadastros até chegar num limpo, sempre renomeando o descartado com `z`/`zzz` para
afundar na lista e desmarcando "ativo". A #240 é mais antiga e tem nascimento e celular diferentes
(25/08/1988, celular `(99) 99999-9999`) — cadastro provisório.

Três dessas linhas dividem o mesmo CPF: #573, #577 e #594. É esse CPF que decide tudo no passo
seguinte.

## 2. Como o script escolheu qual importar? Ele não escolheu — quem chega primeiro cria a ficha

A carga (`ImportarDadosLegado::importarPacientes`) percorre as linhas **na ordem do arquivo, que é a
ordem do id** (conferido: as 1.264 linhas do dump saem em ordem crescente). Para cada linha:

1. já importei essa linha antes? (mapeamento legado → ficha nova)
2. senão, **se tem CPF: procura ficha com esse CPF** e usa a que achar;
3. senão, procura ficha com **nome exatamente igual** (e mesmo médico);
4. **achou?** grava só o mapeamento e **passa para a próxima — não compara nada, não copia nada**;
5. **não achou?** cria a ficha, e aí sim copia os campos da linha, inclusive `ativo`.

O `ativo` só é lido no momento de **criar**. Não existe nenhuma regra que prefira a linha ativa, nem
que compare a linha nova com a ficha existente. Rodando isso nas cinco linhas dela:

| ordem | linha | o que aconteceu |
| --- | --- | --- |
| 1º | #240 (inativa, sem CPF) | nada existia → **criou a ficha #16552** com `ativo=0` |
| 2º | #573 (inativa, CPF) | #16552 não tem CPF e o nome `z-…` não é igual → **criou a ficha #16742** com o nome `z-Fanilde…` e `ativo=0` |
| 3º | #577 (inativa, mesmo CPF) | casou por CPF na #16742 → **pulada**; ficou só o mapeamento |
| 4º | #579 (inativa, sem CPF) | nome não bate com nada → linha sem ficha em produção |
| 5º | **#594 (ATIVA, mesmo CPF)** | casou por CPF na #16742 → **pulada**; o nome limpo e o `ativo=1` foram descartados |

Ou seja: a ficha nasceu da **#573** só porque ela chegou primeiro entre as três que têm aquele CPF.
Se a ordem fosse outra — ou se houvesse uma regra preferindo a linha ativa — a ficha teria nascido
certa. Não houve escolha; houve ordem de chegada.

## E por que as receitas da ficha boa foram para a ficha errada

O passo 4 grava o mapeamento "linha do ClinicaWeb → ficha daqui" mesmo quando pula a linha. Depois,
ao importar as receitas, cada receita segue o mapeamento da sua linha. Resultado, na ficha #16742:

| receita no ClinicaWeb | veio da linha | onde foi parar |
| --- | --- | --- |
| #625 | #573 (`z-`, inativa) | ficha #16742 |
| #634, #636, #637, #638 | #577 (`zzz`, inativa) | ficha #16742 |
| **#658, #1566, #2259** | **#594 (ativa)** | ficha #16742 |

A #2259 é a receita nº 3 de **11/06/2026** das fotos. Ela veio da ficha ativa e foi guardada na ficha
que estava com nome `z-` e desativada — que é a que a busca da Dra Sullege esconde. As duas coisas ao
mesmo tempo: o histórico certo, no cadastro errado.

## E por que as importações seguintes não corrigiram

Duas regras, cada uma sensata sozinha:

- **`ativo` não é copiado em atualização** — arquivar aqui é decisão de vocês, e um arquivo do
  ClinicaWeb não deve reativar quem vocês arquivaram. Só é copiado ao criar a ficha.
- **o nome só é sobrescrito se a linha do ClinicaWeb for mais recente que a ficha daqui** — e a #594
  nunca foi editada lá (só tem data de inclusão, 26/06/2025), então o nome limpo contava como
  desatualizado.

Fora isso, o merge só preenche campo vazio, e o nome não estava vazio. Foi assim que o `z-` e o
"desativado" sobreviveram a todas as rodadas.

## O que mudou

O importador agora faz o que faltava nessa exata situação: quando a linha está **ativa** no
ClinicaWeb e cai numa ficha arquivada aqui, ele **reativa a ficha, reativa o vínculo do médico e
passa a usar o nome da linha ativa**. Linha arquivada no ClinicaWeb continua arquivada aqui.

Somado às outras duas camadas (aviso amarelo na conferência da importação; ficha arquivada aparecendo
marcada na busca por nome, com "Selecionar" que reativa), a mesma sequência de fatos não produz mais
uma paciente invisível.

Em produção, a ficha #16742 está ativa e visível para a Dra Sullege — e ela já emitiu receita nova
nela hoje (16742-0011, finalizada). A #16552 (aquele cadastro provisório de 2025, nascimento
25/08/1988) segue arquivada de propósito: está arquivada no ClinicaWeb também.
