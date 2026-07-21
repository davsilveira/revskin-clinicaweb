# Job 8423ddcb — Investigação: Pedido ERP 3524 / Receita CLW 17555-0001 (sem correção)

## Goal
Investigar por que, ao finalizar o pedido de venda no ERP com status **Aprovado**, a receita no ClinicaWeb não refletiu a troca de produtos (item retirado ainda aparece na emissão; item incluído não aparece). **Somente report — nenhuma correção aplicada.**

## O que mudou
Nada no código de aplicação. Apenas este worklog.

## Evidências (ERP ao vivo via API Tiny v2 deste ambiente)

| Campo | Valor |
|---|---|
| Número do pedido | **3524** |
| ID Tiny (`idVendaTiny`) | **979133300** |
| Situação | **Aprovado** |
| Data | 15/07/2026 |
| Observações | `ClinicaWeb \| Receita #17555-0001 \| Ayme de Oliveira` |

Itens atuais no ERP:

| tiny_id produto | Código | Qtd | VU |
|---|---|---|---|
| 889820540 | SYNCHRON | 1 | 203 |
| 889820454 | BIONAISSANCE | 1 | 156 |
| 901060649 | R0,0015H12 NEODELINE | 1 | 204 |
| 889822324 | TONALITE 2 30G | 1 | 192 |
| 942166503 | CREME INTRODUTORIO 15G | 1 | 0 |
| 940648075 | W-APOSTILA DO PACIENTE | 1 | 0 |

## Limitação deste ambiente (CLW)

- A receita **17555-0001** e o `tiny_pedido_id` **979133300** **não existem** no MySQL local do `revski-main`.
- Nesta base, a sequência `1755x` para em `17553-0001`; não há médico “Ayme” local.
- Portanto **não foi possível comparar os `receita_itens` atuais do CLW** neste worktree. A análise do lado ClinicaWeb é por código + catálogo local + estado do ERP.

## Como o sync ERP → CLW funciona

Fluxo: webhook Tiny → `WebhookTinyController@pedidoFinalizado` → `ProcessWebhookTinyJob` (fila `tiny-webhooks`).

Para situação **string** `aprovado` (API v2 / webhook `situacao_pedido`):

1. **Não** marca itens como vendidos (verde).
2. Chama `sincronizarPrecosItensDoPedido` → `aplicarMergeItensDoPedidoTiny(..., marcarVendido: false)`.
3. Busca o pedido na API, atualiza qtd/preço das linhas que batem por `produto.tiny_id`, e **insere** linhas novas se o produto existir no catálogo CLW com o mesmo `tiny_id`.

Classificação confirmada no código:

| Payload `situacao` | Ação do job |
|---|---|
| `aprovado` / `Aprovado` | **MERGE sem marcar vendido** |
| `3` (código V3 “Aprovado”) | **CANCELAR receita** (bug latente — ver abaixo) |
| `faturado` / `enviado` / etc. | MERGE **com** `vendido=true` + aquisição |

## Causas raiz prováveis do sintoma reportado

### 1) Produto retirado no ERP continua na emissão da receita — comportamento do merge

Em `ProcessWebhookTinyJob::aplicarMergeItensDoPedidoTiny`, quando um item da receita **não** está no pedido Tiny:

```php
if ($matchIndex === null) {
    continue; // não remove, não desmarca imprimir, não altera nada
}
```

Ou seja: **mesmo com webhook de “Aprovado” processado com sucesso, itens removidos no ERP nunca saem da receita / PDF.** Isso explica diretamente “ainda consta o produto que foi retirado” na emissão.

### 2) Produto incluído no ERP pode não entrar na receita — falha de vínculo `tiny_id`

O merge só cria linha nova com:

```php
Produto::where('tiny_id', $row['tiny_id'])->first();
```

Catálogo local vs itens do pedido 3524:

| Produto no ERP | tiny_id ERP | No CLW local? |
|---|---|---|
| SYNCHRON / BIONAISSANCE / NEODELINE / TONALITE 2 / CREME INTRODUTORIO | ok | Sim, `tiny_id` preenchido |
| **APOSTILA DO PACIENTE** | **940648075** | Produto existe (`id=17551`, código `w-Apostila Paciente`) com **`tiny_id = null`** |

Se o item incluído no ERP foi a **Apostila** (ou qualquer outro sem `tiny_id` no CLW), o job **registraria warning** `Linha do pedido sem produto local com mesmo tiny_id` e **não inseriria** a linha — exatamente “não consta o que foi incluído”.

### 3) “Aprovado” não pinta itens de vendido

Mesmo após merge bem-sucedido, status **Aprovado** **não** seta `vendido` / aquisição. Só situações faturadas (`faturado`, `enviado`, `entregue`, `pronto_envio`, `atendido`). Se a expectativa era ver a receita “atualizada” em verde, Aprovado sozinho não faz isso por desenho atual (`1292954`).

### 4) Dependência de webhook + fila (não verificável aqui)

- Sync só ocorre se produção receber o webhook e a fila `tiny-webhooks` processar o job.
- Webhook de **situação** (`situacao_pedido`) dispara na mudança para Aprovado.
- Webhook de **pedido** (`atualizacao_pedido`) pode disparar em alteração de itens — se não estiver cadastrado no Tiny, editar itens **depois** de já estar Aprovado pode não notificar o CLW.
- Neste env: `APP_URL` local, sem logs de produção para 3524/17555; `failed_jobs` local sem ocorrências relevantes.

### 5) Bug latente (não é o caminho V2 típico deste caso)

`$situacoesCanceladasInt = [2, 3, 4]` trata códigos V3 **3=Aprovado** e **4=Preparando envio** como cancelamento. Conta deste env usa **API v2** (strings); para `aprovado` textual o caminho correto é merge. Ainda assim é uma armadilha se algum payload mandar `3` numérico.

## Arquivos relevantes (leitura apenas)

- `app/Jobs/ProcessWebhookTinyJob.php` — classificação de situação + merge de itens
- `app/Http/Controllers/WebhookTinyController.php` — parse do webhook
- `app/Services/TinyErpClient.php` — `isSituacaoPedidoFaturada` / labels V3
- `app/Jobs/CriarPedidoTinyJob.php` — criação do pedido e obs `ClinicaWeb | Receita #…`
- `tests/Unit/TinyErpSituacaoPedidoTest.php` — confirma que `aprovado` **não** é faturada

## Como validar em produção (manual)

URL produção: https://clinicaweb.revskin.com.br  
URL local deste env: https://revski-main.ddev.site:33177 (receita **ausente** aqui)

1. Admin/Call Center: abrir receita **17555-0001**.
2. Confirmar se `tiny_pedido_id` = `979133300`.
3. Comparar itens da receita (e PDF/emissão) com a lista do ERP acima.
4. Em **Produtos**, abrir “APOSTILA DO PACIENTE” / `w-Apostila Paciente` e ver se `tiny_id` está vazio (candidato forte do item incluído que não veio).
5. Nos logs de produção, buscar:
   - `Tiny ERP: Processando webhook` + `979133300` / `aprovado`
   - `Merge de itens do pedido concluído`
   - `Linha do pedido sem produto local com mesmo tiny_id`
   - `Receita não encontrada para pedido`
6. No Tiny: Configurações → E-commerce → Integrações → Webhook — confirmar URL apontando para `https://clinicaweb.revskin.com.br/api/webhooks/tiny/pedido-finalizado` (situação **e**, se desejado, notificação de pedidos).

Screenshots: Playwright não usado (investigação sem UI mutável neste env; receita inexistente localmente).

## Conclusão

O sintoma é **coerente com o código atual**, não necessariamente com “webhook morto”:

1. **Retirada no ERP não remove linha da receita/PDF** — o merge ignora itens ausentes no pedido.
2. **Inclusão no ERP só entra se o produto CLW tiver o mesmo `tiny_id`** — a Apostila do pedido 3524 está no ERP com `940648075`, mas no catálogo local está **sem** `tiny_id`.
3. Status **Aprovado** só faz merge de preços/itens (sem marcar vendido); não é o mesmo efeito de “faturado/enviado”.

**Nenhuma correção foi feita.** Próximos passos sugeridos (fora deste job): em produção confirmar itens da 17555-0001 + logs do webhook; corrigir merge para remover/desmarcar itens fora do pedido; vincular `tiny_id` da Apostila (e qualquer outro item novo); eventualmente tratar `aprovado` numérico sem cancelar.
