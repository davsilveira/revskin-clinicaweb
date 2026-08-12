# Cadastros repetidos — resolvido em produção

**12/08/2026** · Produção saiu de **1.438** para **1.361** pacientes: **77 cadastros repetidos a menos**.
Nenhuma receita, aquisição ou vínculo de médico se perdeu.

---

## O que foi feito

### Lote 1 — 65 cadastros vazios (as "cascas")

Cadastros que o sync do oList criou em cima de quem já existia, sem nenhuma receita e sem nenhum
médico ligado. 58 eram óbvios; 7 você confirmou no checklist (Jucineide, Angélica, Erika Palmer,
Doralice, Andrea Cristina, Fabiane, Carolina Canto).

Em cada um, o que o cadastro novo tinha de a mais — CPF, celular, e-mail, endereço, o vínculo com o
contato do oList — foi copiado para o cadastro antigo antes de apagar. Nada foi sobrescrito: onde o
cadastro antigo já tinha um dado, o dado antigo ficou.

Isso resolveu exatamente o que você pediu no item 1: *"colocar o CPF, celular e email que faltam no
cadastro antigo e deletar o que tem 0 receitas"*.

**As datas de nascimento erradas não foram copiadas.** A Jucineide vinha com 2071, a Angélica com
2091, a Andrea com 2074 e a Fabiane com 2089 — como você apontou. As quatro ficaram com a data certa
que já estava aqui.

### Parte 2 — 12 fusões com histórico dos dois lados

Aqui os dois cadastros tinham receita, então foi fusão de verdade. O critério foi o que você pediu:
**fica quem tem a receita mais recente**, e os dados que faltavam nele vieram do outro antes de apagar.

| Paciente | Ficou | Saiu | Receitas na ficha agora |
|---|---|---|---|
| Giovana Naccarato Ferreira de Camargo | #17401 | #16435 | 12 |
| Maria Otilia Teixeira Abali | #16653 | #16673 | 5 |
| Mariah Pedrosa Lorentzen | #17375 | #16776 | 2 |
| Priscilla Nunes Ortiz | #17192 | #17039 | 2 |
| Roberta de Andrade Paula Saldanha | #17338 | #16693 e #16781 | 3 |
| Viviane Kohatsu Noda | #16683 | #17187 | 3 |
| Carla Janaína Zavislak | #16656 | #16445 | 2 |
| Jucineide Auxiliadora Ribeiro Leite | #16488 | #16615 | 13 |
| Maria Rosineide Ferreira Rocha | #16568 | #16566 | 2 |
| Daniela Cosim da Silva | #16569 | #16570 e #17674 | 2 |

Quatro desses casos (Carla, Jucineide, Maria Rosineide, Daniela) estavam escondidos: os nomes
diferiam só por um ponto, um acento ou um `_` no fim, então não apareciam como repetidos na busca.

**Os médicos continuam vendo os mesmos pacientes.** Quando os dois cadastros eram vistos por médicos
diferentes, a ficha que sobrou ficou ligada aos dois. Conferi um a um contra o banco de antes.

**O número da receita não mudou.** As 4 receitas antigas da Giovana continuam `16435-0001` a
`16435-0004` dentro da ficha `17401`. Fica estranho de ver, e é de propósito: esse número vai para o
oList como número do pedido e sai impresso na receita que o paciente tem na mão. Renumerar quebraria
a referência de pedidos que já existem.

### As duas portas que criavam os repetidos foram fechadas

Sem isso o problema voltaria no próximo sync.

1. **Celular sem o nono dígito.** O sistema comparava dígito a dígito, e o oList guarda
   `(48) 9907-2096` onde aqui está `(48) 99907-2096` — era o seu item 12. Agora a comparação é por
   DDD + os 8 últimos dígitos. O DDD continua valendo porque a Hellen Uliam Uriki tem o mesmo número
   em dois DDDs e são duas pessoas, como você viu.
2. **Nascimento impossível.** Uma data como 2071 ou 2091 derrubava o reconhecimento por
   nascimento + nome e o sync criava ficha nova. Agora data futura é descartada em vez de gravada —
   sem data o sistema ainda reconhece por CPF, telefone e e-mail.

### O que ficou separado

Os 6 que você marcou como pessoas diferentes seguem como dois cadastros, sem nenhuma alteração:
Gabriella Valentim, Claudia Dantas, Claudia Marçal, Hellen Uliam Uriki, Ana Cristina Gomes de
Araujo e Theodora Ribeiro Paredes.

---

## O que ainda depende de vocês

Está tudo em **`correcoes-olist-pendentes.md`**, com as listas prontas.

### 1. Apagar 2 contatos repetidos no oList

Erika Palmer e Doralice da Mata Pereira têm dois contatos cada no oList. Aqui já ficou um cadastro
só, mas o contato sobrando pode recriar a ficha se alguém editar por lá.

- Erika Palmer → apagar o contato **763422147** (manter o 760424854)
- Doralice da Mata Pereira → apagar o contato **763324513** (manter o 760478358)

E a Mara Sandra Rodrigues Campos Zandona, que é a Parte 3 que você já pediu para eles.

### 2. Corrigir 69 datas de nascimento no oList

São erros de digitação no ano: `2073` no lugar de `1973`, e por aí. Tem até um `9198`. A lista traz
o valor de hoje e o provável ao lado.

Precisa ser corrigido **no oList**, não aqui: o oList é a fonte, e o próximo sync desfaria a correção
feita deste lado.

### 3. Decidir sobre 33 cadastros de teste

`zzz-Marcelo`, `zzz Elaine`, `zz-Marcelo Antonio`, `aaaaPaulo` e companhia. Não são pacientes, mas
têm receita pendurada, então não apago por conta própria. Se você confirmar, removo com a lista na
mão.

### 4. Uma dúvida: Patricia Pereira Lopes

Dois cadastros, e não dá para decidir daqui:

- **#17062** — nascimento 01/01/1999, telefone (19) 99999-9999, 1 receita
- **#17220** — nascimento 03/05/1989, telefone (19) 99256-8301, 6 receitas

O #17062 tem cara de cadastro apressado (data e telefone de preenchimento automático). Se for a mesma
pessoa, junto no #17220.

---

## Se der algo errado

Cada aplicação em produção tirou um backup do banco antes de encostar em qualquer coisa:

- antes do lote 1 — `backup-antes-fusao-20260812-191249.sql.gz`
- antes da Parte 2 — gerado no mesmo padrão, na home do servidor

O relatório de integridade rodou antes e depois das duas: receitas, itens, aquisições, vínculos e
atendimentos ficaram com a contagem idêntica, e nenhum registro ficou órfão.
