# job 4eb4ebf1 — mapa dos erros para o cliente

Pedido: explicar em lista os casos, o que foi feito e por quê, e o que falta. O cliente quer entender
**onde estão os erros**. Nenhuma alteração de código ou de dado neste job — levantamento e
documentação. Só que o levantamento achou uma duplicata que tinha escapado de tudo.

## Estado conferido em produção

Rodei os diags read-only antes de escrever, para o documento não sair com número de memória:

| | |
|---|---|
| pacientes | 1.327 |
| receitas | 1.993 |
| nascimento impossível | 0 |
| cadastros de teste | 0 |
| órfãos / `tiny_id` repetido / jobs falhos 24h | 0 |

A correção do job 494c6de8 segue de pé e agora dá para ver o efeito da validade funcionando:
`com_detalhe_lido` foi 85 → 160 → **296** ao longo do dia, mas `detalhe_lido_1h=1` — ou seja, o pull
está relendo as fichas aos poucos em vez de gastar chamada com o mesmo contato a cada 10 minutos,
que era exatamente o objetivo.

## O achado: Adriana Haberkorn Lavelberg

O diag novo (`diag-pos-limpeza-olist.yml`) agrupa por `mb_strtolower(trim())` e dava **8** grupos de
homônimos. Para o documento eu queria comparar com o censo inicial (85 grupos / 174 pacientes), que
usa normalização agressiva — maiúscula, acento, pontuação e espaço colapsado. Rodei o censo antigo de
novo para o número ser comparável e ele deu **9**.

A diferença é real: `Adriana Haberkorn Lavelberg` (#17587) e `Adriana Haberkorn  Lavelberg` (#17603)
diferem por um **espaço duplo**, que o `trim()` não colapsa. Mesma pessoa sem dúvida — nascimento
10/07/1974 e celular (11) 99101-5705 idênticos.

Não fundi, porque é caso de Parte 3: são **dois contatos diferentes no oList** (765663047 e
764792740), então juntar só deste lado deixa um contato órfão que recria o cadastro na próxima
edição. Mesma situação da Mara Sandra. Pedi no doc que apaguem um lá e digam qual ficou.

Detalhe que torna esta mais urgente que a Mara Sandra: o #17587 **já tem receita e vínculo** (o
censo acusou `novos_ja_usados=1`), ou seja, a equipe está usando o cadastro no dia a dia e o médico
vê o repetido agora. E os dados estão cruzados — a receita está no #17587, o CPF/e-mail/endereço
estão no #17603.

Lição para a próxima varredura: usar sempre a normalização agressiva. O diag simples esconde par que
difere por espaço duplo.

## Pendências consolidadas (3, todas com a clínica)

1. **Mara Sandra Rodrigues Campos Zandona** — apagar no oList o contato vazio `763541264`.
2. **Adriana Haberkorn Lavelberg** — apagar no oList um dos dois (`765663047` ou `764792740`) e
   avisar qual ficou.
3. **Patricia Pereira Lopes** — decidir se #17062 e #17220 são a mesma pessoa.

Os 6 grupos que a clínica marcou como pessoas diferentes seguem separados. Conferi as justificativas
uma a uma contra os dados antes de escrever a tabela do doc: para a Claudia Dantas eu ia repetir
"DDD diferente", mas os dois números são o mesmo com formatação diferente ((21) 99901-3012 ×
(55) 21999-0130) — o que separa mesmo é o nascimento. Corrigido no doc.

## Entregue

`.wp-agent/share/mapa-dos-erros-cadastros-repetidos.md` — os erros divididos por origem (digitação no
oList, 3 defeitos nossos, herança do CLW2), o que foi feito em cada etapa e as 3 pendências.
