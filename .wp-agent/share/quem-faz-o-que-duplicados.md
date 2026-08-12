# Cadastros repetidos — quem faz o quê

Esqueça os outros documentos por enquanto. Este aqui é o único que importa para decidir.

---

## O problema, em três linhas

Sobram **89 fichas de paciente**. São 74 fichas vazias que a carga de contatos do oList criou em
07/08, mais 13 pessoas que estão gravadas em 28 fichas desde a migração de junho. As 74 vazias não
têm receita nenhuma e saem fácil. As 28 antigas têm receita **dos dois lados** — nenhuma é "morta",
nenhuma pode ser apagada; essas têm de ser juntadas.

---

## O que eu faço (praticamente tudo)

| | |
|---|---|
| Escrever a ferramenta de fusão de fichas (mover receitas, renumerar, transferir CPF/e-mail/vínculo com o oList, apagar a ficha vazia — tudo dentro de uma transação) | eu |
| Corrigir o defeito que criou as fichas vazias (telefone sem o 9 e data de nascimento com o século trocado) | eu |
| Consertar as 70 datas de nascimento erradas (`2071` no lugar de `1971`) | eu |
| Testar tudo local sobre uma cópia do banco de produção e conferir as contagens antes/depois | eu |
| Aplicar em produção, com backup imediatamente antes | eu |
| Apagar os 3 cadastros de teste "ZZZ Marcelo" e a casca do médico #930 (Giovana) | eu |
| Relatório do antes/depois | eu |

## O que só você (ou a clínica) pode fazer

São **três** coisas, e só uma delas dá trabalho:

1. **Dizer "pode ir"** nos lotes. Autorização, nada mais.
2. **Confirmar se é a mesma pessoa** em 23 casos. Isso quem sabe é quem atende — eu só vejo os dados,
   não conheço as pacientes. Já deixei a lista pronta, é só marcar MESMA ou DIFERENTE.
3. **Resolver 3 pessoas com contato repetido dentro do oList.** A API do oList deixa criar e atualizar
   contato, mas **não apagar nem fundir** — não tem como eu fazer isso de fora.

---

## Seus passos, na ordem

### Passo 1 — hoje, 1 minuto: liberar o lote sem risco

**59 das 74 fichas vazias** não precisam de conferência nenhuma: são cadastros só com nome, sem
receita, sem médico, sem telefone, sem atendimento, sem nada. Conferi em produção hoje e **nenhuma
delas foi usada** desde que apareceram.

> Me responda: **"pode fazer o lote 1"**.

Eu faço a fusão, aplico em produção e te mando as contagens antes/depois. Se algo sair diferente do
esperado, o backup volta em um comando.

### Passo 2 — quando der, ~20 minutos com alguém da clínica

Abra **[`checklist-cadastros-repetidos.md`](checklist-cadastros-repetidos.md)**. São 23 blocos, cada um
com os dois cadastros lado a lado (nascimento, CPF, celular, e-mail, quantas receitas, qual médico).
A pergunta é sempre a mesma:

> **É a mesma pessoa ou são duas pessoas diferentes?**

Marque MESMA ou DIFERENTE e me devolva. Não precisa mexer em nada no sistema — é só a resposta.

Por que preciso disso: em vários casos a data de nascimento diverge (`10/10/1982` × `25/03/1981`), o
que tanto pode ser erro de digitação quanto ser **mãe e filha com o mesmo nome**. Se eu chutar errado,
junto o histórico de duas pessoas diferentes — e isso não tem desfazer bonito.

### Passo 3 — depois que você me devolver o checklist

Eu fundo os que vieram como MESMA e deixo em paz os que vieram como DIFERENTE. Aqui entra a Giovana:
minha sugestão é **manter a ficha 17401** (é a que está em uso, com 8 receitas e 13 compras) e trazer
para ela as 4 receitas da ficha 16435, mais o CPF, o e-mail e o vínculo com o oList. Você confirma ou
me diz o contrário.

### Passo 4 — no oList, quando puder

Três pessoas que só se resolvem lá dentro (estão na Parte 3 do checklist): Erika Palmer, Doralice da
Mata Pereira e Mara Sandra Rodrigues Campos Zandona têm **dois contatos cada** no oList. Mantenha um,
apague o outro, e me avise — eu acerto o lado do ClinicaWeb depois.

### Passo 5 — pedido à clínica, sem pressa

As fichas da Giovana, da Jucineide, da Daniela Cosim e da Maria Rosineide **estão duplicadas dentro do
próprio CLW2**. Enquanto estiverem assim lá, toda importação nova alimenta os dois lados de novo.
Vale pedir para arrumarem por lá também — senão a gente limpa aqui e o problema volta.

---

## Uma coisa que continua acontecendo enquanto isso

A varredura de contatos do oList roda **a cada 10 minutos** e cria cadastro novo quando não reconhece
ninguém. Desde ontem apareceram 3 pacientes novos e nenhum é repetido, então o ritmo é baixo — mas o
mecanismo está ligado. A correção do Passo 1 (telefone e data) fecha a maior parte dessa torneira.

Se preferir, eu **desligo a criação automática** de cadastro novo por essa varredura até tudo estar
limpo: a atualização de quem já existe continua funcionando, só para de nascer ficha nova sozinha.
É reversível num setting. Me diga se quer.

---

## Resumindo

| Passo | Quem | Tempo | Depende de |
|---|---|---|---|
| 1. Liberar o lote de 59 fichas vazias | você | 1 min | nada |
| 2. Marcar MESMA/DIFERENTE em 23 casos | clínica | ~20 min | nada |
| 3. Fundir o resto | eu | — | passo 2 |
| 4. Acertar 4 contatos no oList | você/clínica | ~10 min | nada |
| 5. Pedir para arrumarem o CLW2 | clínica | — | nada |

O passo 1 e o passo 2 podem andar em paralelo. Nada disso bloqueia o uso do sistema hoje.
