# Diagnóstico — Mara Jaqueline (17964-0001 / 17964-0002): cancelamento e cortesia no oList

Investigação só de leitura (logs e banco de produção). **Nada foi alterado.**

| Receita | ID | Status CLW3 | Cortesia | Pedido oList |
|---|---|---|---|---|
| 17964-0001 | 28266 | cancelada | não | 992887964 (nº **3962**) |
| 17964-0002 | 28267 | finalizada | **sim** | 992888301 (nº **3963**) |

## Linha do tempo (19/10/2026, horário de Brasília, tirado do `laravel.log`)

| Hora | Evento |
|---|---|
| 17:52:46 | Receita 0001 finalizada → `CriarPedidoTinyJob` agendado |
| 17:54:03 | Pedido **3962** criado no oList (R$ 911) |
| 17:57:30 | Receita 0002 criada por duplicação (já com Cortesia), finalizada às 17:57:48 |
| 17:58:05–09 | Clique em "Cancelar" na 0001 → consulta ao oList (pedido "Em aberto", então pode cancelar) → `CancelarPedidoTinyJob` agendado |
| 17:59:03 | Pedido **3963** criado no oList **com os preços cheios** (204 + 0 + 203 + 156 + 156 + 192 = R$ 911) |
| **18:00:03** | `pedido.alterar.situacao` → **cancelado**. Resposta OK. |
| **18:00:04** | O oList manda webhook confirmando **3962 = Cancelado** |
| **18:02:51** | O oList manda webhook **3962 = Faturado** ← mudança feita no oList, não pelo CLW3 |
| 18:03:02 | O CLW3 processa o "faturado" e **marca como vendidos os 6 itens da receita CANCELADA 0001** |
| 18:29:41 | 3962 volta para "Em aberto" (no oList) |
| 18:32:11 | 3963 faturado (R$ 911, sem cortesia) |
| 18:32:41 | 3962 cancelado de novo (no oList, parece manual) |

## Problema 1 — "Não cancelou automaticamente no oList"

**A integração cancelou, sim.** Às 18:00:03, cerca de 2 min depois do clique, o CLW3 cancelou o pedido 3962, e o
próprio oList confirmou por webhook às 18:00:04. Esse atraso de ~2 min é esperado: o job espera
1 min e a fila roda pelo cron, a cada minuto.

O que aconteceu depois foi **no oList**: às 18:02:51 o pedido 3962, que já estava cancelado, passou para
**Faturado**. Nesse momento não havia nenhuma chamada do CLW3 ao oList, então o próprio oList mudou o status, por ação de alguém ou por
alguma automação dele. A explicação mais provável é que alguém da operação
já estava com o pedido 3962 aberto na tela, ou numa fila de faturamento, e faturou em cima do cancelamento.
Para quem viu o pedido faturado, parecia que o cancelamento não tinha funcionado. Depois alguém reabriu (18:29) e cancelou
na mão (18:32).

Vale perguntar à operação: *quem faturou o pedido 3962 às 18:02 e de que tela/lote?*

### Falhas reais do CLW3 que essa história mostrou

1. **O webhook "faturado" de uma receita já cancelada marca os itens como vendidos.**
   `ProcessWebhookTinyJob::handle` não confere `receita.status`. A receita 28266 continua **cancelada,
   mas com os 6 itens `vendido=1` e 6 registros em `receita_item_aquisicoes` (pedido 992887964)**.
   O cancelamento posterior (18:32) não desfez isso, porque `ReceitaCancelamentoService::cancelarReceita`
   sai logo no início quando a receita já está cancelada. Efeito: os 6 produtos contam **duas vezes** como
   adquiridos (0001 e 0002) nos relatórios de aquisição e no histórico do paciente.
2. **Não existe alerta quando o oList contraria o CLW3.** Quando chega "faturado" para uma receita cancelada no CLW3
   (ou o contrário), nada avisa ninguém. Era exatamente o caso de alertar alguém.
3. A janela de ~2 min entre o clique e o cancelamento no oList é um risco à parte: se a operação faturar nesse intervalo,
   o job encontra o pedido faturado e o cancelamento falha (`tries = 1`, sem retry). Não foi o
   que aconteceu aqui, mas é a mesma família de problema.

## Problema 2 — "Cortesia não entrou zerada no oList"

**A integração com o oList nunca tratou a cortesia.** O interruptor Cortesia (job 059a98ac, ago/2026) foi
feito **só para o RD Station**: o campo customizado "Cortesia" recebe `Sim`, e isso funcionou nesta receita
(log 17:59:03: `"cortesia":"Sim"`). Já o `CriarPedidoTinyJob` (V2 e V3) **não lê `receita.cortesia`**:
manda o `preco_venda` de cada produto e só envia `valorDesconto` quando há desconto na receita. O payload
real do pedido 3963 foi com `valor_unitario` 204 / 0 / 203 / 156 / 156 / 192.

Dentro do próprio CLW3 a cortesia também não zera nada: a receita 0002 mostra R$ 911 na listagem e nos
itens (prints do cliente), e o RD recebeu `one_time_price: 911`.

Então o problema não é uma falha da integração. **A regra "cortesia = valor zero no oList" nunca foi construída.**

## O que precisa ser decidido para corrigir (próxima rodada)

1. **Como a cortesia chega no oList?**
   - (a) itens com `valor_unitario = 0`: pedido zerado, mas perde o preço de referência; ou
   - (b) itens com preço cheio + `valorDesconto` = total (100% de desconto): mantém o valor de tabela
     visível e zera o total. **Recomendo esta**, porque o relatório no oList continua mostrando quanto foi dado de cortesia.
   - Frete numa cortesia: zera ou mantém?
2. **No CLW3**, a cortesia deve zerar o total da receita, da listagem, do PDF e do `one_time_price` no RD? Hoje mostra R$ 911.
3. **Marcar/desmarcar cortesia depois que o pedido já existe** no oList: hoje a receita finalizada não reenvia nada.
   Vai ser preciso atualizar o pedido ou exigir cancelar e refazer?
4. **Webhook em receita cancelada**: ignorar "faturado" (e registrar um alerta) quando `receita.status = cancelada`.

## Correção de dados pendente (não feita, só com aprovação)

- Receita **28266 (17964-0001)**: limpar `vendido` e apagar as 6 aquisições do pedido 992887964
  (`receita_item_aquisicoes` ids 101478–101483).
- Pedido **3963** no oList já foi **faturado com R$ 911**. Ajustar o valor no oList ou na NF é com a operação ou o
  financeiro. O CLW3 não consegue mais mudar um pedido faturado.

---

## Atualização 21/10/2026 (job af857077)

- **Dados:** a receita 28266 (cancelada) teve os 6 itens desmarcados e as aquisições 101478–101483 removidas, depois de
  conferir que o pedido 3962 está Cancelado no oList e que a 17964-0002 já tem os mesmos 6 produtos vendidos pelo pedido 3963.
- **Código (local, ainda não deployado):** cortesia = desconto de 100% no total do CLW3 e no pedido do oList
  (`valor_desconto` = soma dos itens enviados, frete mantido). O webhook do oList não marca mais vendido em receita
  cancelada. Detalhes em `job-af857077-1429-42a9-ad88-2ca29b47b2ff.md`.
