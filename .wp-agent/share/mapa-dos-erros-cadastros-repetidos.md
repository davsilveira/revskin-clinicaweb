# Cadastros repetidos: onde estavam os erros, o que foi feito e o que falta

**13/08/2026** · Resumo de tudo, do começo ao estado de agora.

| | Início | Agora |
|---|---|---|
| Pacientes | 1.438 | **1.327** |
| Datas de nascimento impossíveis | 69 | **0** |
| Cadastros de teste | 34 | **0** |
| Grupos de nomes repetidos | 85 | **9** |
| Pacientes envolvidos em repetição | 174 | **18** |

Nenhuma receita foi perdida no processo. Dos 9 grupos que sobraram, 6 já estão decididos (vocês
confirmaram que são pessoas diferentes) e 3 dependem de uma resposta — estão no fim deste documento.

---

## Parte 1 — Onde estavam os erros

Os cadastros repetidos não vinham de um problema só. Eram **três origens diferentes**, e é isso que
fazia o problema voltar mesmo depois de limpar.

### Erro 1 — Digitação no oList

Alguém digitou errado no cadastro do contato, lá no oList.

- **69 datas de nascimento impossíveis.** O padrão era o século trocado: `2071` no lugar de `1971`,
  `2091` no lugar de `1991`. Teve também `2218` e `9198`.
- **Contatos repetidos da mesma pessoa** — a Erika Palmer e a Doralice tinham dois contatos cada.

Por que isso gera cadastro repetido aqui: quando o contato chega do oList, o sistema procura se essa
pessoa já existe usando nome + data de nascimento. Com a data errada, ele não reconhece a pessoa e
**cria um cadastro novo em cima de um que já existia**.

*A clínica corrigiu tudo isso no oList em 12/08.*

### Erro 2 — Três defeitos no nosso sistema

**2a. O sistema aceitava a data impossível.** Recebia `2071` e gravava `2071`, espalhando o erro de
digitação para cá. Agora uma data no futuro é recusada: é melhor ficar sem data do que com data
errada, porque aí o sistema usa CPF, celular e e-mail para reconhecer a pessoa.

**2b. O celular era comparado do jeito errado.** `(21) 99806-1705` e `(21) 9806-1705` são o mesmo
telefone — um tem o nono dígito e o outro não. O sistema tratava como dois números diferentes e
criava cadastro novo. Agora a comparação usa DDD + os últimos 8 dígitos. O DDD entra de propósito:
a Hellen tem o mesmo número em dois DDDs (65 e 66) e são pessoas diferentes.

**2c. As correções feitas no oList não chegavam aqui.** Este era o mais grave, e o mais bem
escondido. A lista de contatos que o oList devolve tem nome, e-mail, telefone fixo e endereço — e
**não tem data de nascimento, nem celular, nem sexo**. Para um paciente que o sistema já conhecia,
ele se contentava com essa lista, para economizar consulta. Resultado: esses três campos entravam na
primeira importação e **nunca mais eram atualizados**.

Foi por isso que as 69 datas corrigidas no oList continuaram erradas aqui no dia seguinte. E o que
despistava: o **nome** das mesmas pessoas atualizou normalmente, então parecia que estava tudo
funcionando. Conferimos contato a contato pela API antes de mexer:

| Contato | Estava certo no oList | Continuava errado aqui |
|---|---|---|
| Adriana Lima Gürtler | 10/12/1973 | 2073-12-10 |
| Adriana Lúcia Almeida Vaz | 06/10/1969 | 2069-10-06 |
| Aline Hastenreiter Rodrigues | 19/09/1982 | 2082-09-19 |

Agora a ficha completa do contato é relida de tempos em tempos. **Não é possível reler a cada
rodada**: o oList filtra por dia, então um contato alterado hoje reaparece na lista a cada 10
minutos durante dois dias — seriam milhares de consultas à toa. A releitura acontece no máximo uma
vez a cada 6 horas por contato, então uma correção feita no oList aparece aqui em algumas horas.

### Erro 3 — Herança do sistema antigo (CLW2)

**A mesma pessoa era cadastrada uma vez para cada médico.** No sistema antigo, se a paciente passava
com dois médicos, ela tinha dois cadastros. Ao trazer para cá, viraram dois pacientes diferentes. Foi
o caso do Gerson e da Angela.

**Cadastros de teste ficaram na base**: 34 registros como `zzz-Marcelo`, `zzz Elaine`, `aaaaPaulo`.

---

## Parte 2 — O que foi feito

1. **65 cadastros vazios juntados.** Eram fichas criadas pela importação em cima de pacientes que já
   existiam, sem nenhuma receita. Em cada uma, o CPF, celular, e-mail e endereço que faltavam foram
   copiados para o cadastro antigo antes de apagar.

2. **12 cadastros com histórico juntados.** Aqui os dois lados tinham receita. Critério: fica quem
   tem a receita mais recente, com os dados consolidados antes de remover. Apareceram 4 casos que a
   busca por nome exato não pegava, porque diferiam só por acento ou pontuação.

3. **Os três defeitos do sistema corrigidos** (itens 2a, 2b e 2c acima), senão os repetidos
   voltariam na próxima sincronização.

4. **68 datas de nascimento trazidas do oList.** Depois da correção do item 2c, as correções que a
   clínica tinha feito entraram — todas, sem nenhum erro. (Eram 69 datas impossíveis; a 69ª era o
   cadastro de teste `aaaaPaulo`, que foi apagado no passo seguinte.)

5. **34 cadastros de teste removidos**, com 39 receitas de teste junto. Antes de apagar, o sistema
   conferiu que nenhum tinha pedido de verdade no oList e que nenhuma receita de paciente real tinha
   saído de uma delas.

6. **Gerson e Angela juntados**, depois da autorização de vocês. Esses dois só apareceram **por causa
   da correção**: quando os nomes passaram a sincronizar com o oList, as duas fichas ficaram com a
   grafia idêntica e a repetição saltou à vista.

Cada gravação teve backup do banco antes. Depois de tudo: nenhuma receita, item ou aquisição sem
dono, nenhum número de receita repetido, nenhum contato do oList apontando para dois pacientes.

---

## Parte 3 — O que falta, e é com vocês

Sobraram 9 grupos de nomes iguais. **Seis já estão decididos** — vocês confirmaram que são pessoas
diferentes, e por isso continuam separados:

| Nome | Por que são pessoas diferentes |
|---|---|
| Gabriella Valentim | Nascimento diferente (1982 × 1981) e celular diferente |
| Claudia Dantas | Nascimento diferente (21/04/1970 × 15/09/1971) |
| Claudia Marçal | Nascimento diferente (1965 × 1963) |
| Hellen Uliam Uriki | Mesmo número em DDDs diferentes (65 × 66), e nascimento diferente |
| Ana Cristina Gomes de Araujo | Nascimento diferente (1971 × 1983) e celular diferente |
| Theodora Ribeiro Paredes | Nascimento diferente e celular diferente |

Faltam **três decisões**. As duas primeiras são o mesmo problema: a pessoa tem **dois contatos no
oList**, e enquanto os dois existirem lá continuam dois cadastros aqui. Se eu juntar só deste lado, a
próxima sincronização recria o repetido. **Apaguem o contato duplicado no oList e me digam qual
ficou — aí eu junto o resto daqui.**

### 1. Mara Sandra Rodrigues Campos Zandona — apagar contato no oList

| Contato no oList | O que tem |
|---|---|
| `763541264` | Vazio — sem CPF, sem celular, sem nascimento |
| `756979841` | Completo — CPF 164.550.168-07, (65) 8131-6905, 08/08/1975 |

O vazio (`763541264`) é o que sobra. Nenhum dos dois tem receita aqui.

### 2. Adriana Haberkorn Lavelberg — apagar contato no oList

Esta é nova, e escapou de todas as buscas anteriores por um motivo bobo: **os dois nomes têm um
espaço duplo de diferença** (`Adriana Haberkorn Lavelberg` × `Adriana Haberkorn  Lavelberg`), então
não apareciam como nomes iguais.

É a mesma pessoa sem dúvida — mesmo nascimento (10/07/1974) e mesmo celular ((11) 99101-5705).

| Contato no oList | Cadastro aqui | O que tem |
|---|---|---|
| `765663047` | #17587 | **A receita** (30/06/2026), mas sem CPF, sem endereço e com e-mail de marcação |
| `764792740` | #17603 | Sem receita, mas com CPF 260.741.118-12, e-mail real e endereço completo |

Vale a pena resolver esta: a equipe **já está usando o #17587** no dia a dia, então o repetido está
visível para o médico agora. Sugiro manter no oList o contato `764792740`, que é o completo — mas me
confirmem, porque aqui a receita está no outro.

### 3. Patricia Pereira Lopes — precisa de uma resposta de vocês

- **#17062** — 01/01/1999, (19) 99999-9999, 1 receita
- **#17220** — 03/05/1989, (19) 99256-8301, 6 receitas

O #17062 tem cara de cadastro feito às pressas: data `01/01/1999` e telefone `99999-9999` são
preenchimento de emergência. Mas é chute meu. **É a mesma pessoa?** Se for, junto tudo no #17220.

---

## Uma coisa que vale saber daqui pra frente

**O nome de um paciente aqui só é atualizado quando o contato é editado no oList.** Não é falta de
sincronia — os cadastros estão ligados ao contato certo. É por isso que `ADRIANA C KANTOWITZ
GANDOLPHO` continua em maiúsculas: aquele contato não foi mexido. Na próxima vez que mexerem nele, a
grafia se ajusta sozinha.

Conforme mais nomes forem sincronizando, **pode aparecer mais algum par repetido** que a grafia
diferente escondia, como aconteceu com o Gerson e a Angela — e como a Adriana Haberkorn, que estava
escondida atrás de um espaço a mais no meio do nome. Passei a varrer também ignorando acento,
pontuação e espaço duplo, justamente para esses não escaparem. Se aparecer mais algum, é fácil de
resolver.

Se preferirem alinhar a grafia de todos de uma vez com o oList, dá para fazer numa passada só — é
só avisar.
