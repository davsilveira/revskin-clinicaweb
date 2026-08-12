# Job 51fdbc50 — Item incluído no oList não somou valor na receita 17401-0008

**Relatório para o cliente:** [`.wp-agent/share/receita-17401-0008-item-olist-sem-valor.md`](share/receita-17401-0008-item-olist-sem-valor.md)

## Diagnóstico (prod)

Receita **17401-0008** (id 28079, finalizada, paciente Giovana Naccarato) / pedido oList **3646**
(id 983312351, *Faturado*). O pedido tem 3 itens (NEODELINE 51,00 + BIONAISSANCE 39,00 +
CREME INTRODUTORIO 0,00), mas a receita estava com total **R$ 51,00**.

Causa raiz: em `ProcessWebhookTinyJob::aplicarMergeItensDoPedidoTiny()`, quando o produto do pedido
**já existia** na receita, o merge atualizava `quantidade`/`valor_unitario`/`vendido` e criava a
aquisição, mas **não mexia em `imprimir`**. Os dois itens acrescentados no oList estavam na receita
como recomendados desmarcados (`imprimir=0`), e `Receita::calcularTotais()` soma só
`itens->where('imprimir', true)` → valor ficava de fora. (Linha nova, criada pelo próprio merge,
já nascia com `imprimir=true` — por isso o bug só aparecia nesse cenário.)

Log de prod confirma os merges de 11/08 às 10:46 (NEODELINE), 11:09 (BIONAISSANCE) e 11:53
(CREME INTRODUTORIO), todos com `marcar_vendido=true`.

## Mudanças

| Arquivo | O quê |
|---|---|
| `app/Jobs/ProcessWebhookTinyJob.php` | Item presente no pedido do oList passa a ser marcado (`imprimir = true`); log por linha remarcada + contador `itens_marcados_imprimir` no log final do merge |
| `app/Console/Commands/CorrigirItensVendidosNaoImpressos.php` | **Novo.** `tiny:corrigir-itens-vendidos-nao-impressos` — dry-run por padrão, `--force` aplica, `--receita=` restringe. Só toca em item com `vendido=1` **e** aquisição do mesmo `tiny_pedido_id` da receita; recalcula os totais |
| `tests/Feature/ProcessWebhookTinyItemDesmarcadoTest.php` | **Novo.** 3 testes: item desmarcado no pedido volta a contar (51 → 90); item fora do pedido continua desmarcado; comando conserta receita já afetada (e respeita o dry-run) |
| `.github/workflows/corrigir-itens-vendidos-nao-impressos.yml` | **Novo.** Roda o comando em prod via SSH (input `modo`: dry-run/force, `receita` opcional) |
| `.github/workflows/diag-receita-17401-0008.yml` | **Novo.** Diagnóstico read-only da receita/pedido (pode ser apagado depois) |

Commits: `c6da078` (workflow de diag), `353ca29` (fix + comando + testes + workflow).
Deploy em prod: run **31611198263** — sucesso.

## Correção aplicada em produção

`tiny:corrigir-itens-vendidos-nao-impressos --receita=17401-0008` (dry-run run 31611306078,
force run 31611367328):

```
Itens a corrigir: 2 em 1 receita(s)
- receita #28079 (17401-0008, finalizada) pedido=983312351 total_atual=51.00 delta_previsto=+39
    item#343478 BIONAISSANCE            qtd=1 vu=39.00 vt=39.00
    item#343481 CREME INTRODUTORIO 15G  qtd=1 vu=0.00  vt=0.00
Aplicado: receita #28079 (17401-0008) subtotal=90.00 total=90.00
POS|itens_vendido_sem_imprimir=0     <- toda a base
```

Varredura na base inteira: só esses 2 itens estavam no estado errado; após a correção, zero.

## Como validar à mão

1. Prod: https://clinicaweb.revskin.com.br → **Receitas** → buscar **17401-0008** (paciente Giovana
   Naccarato Ferreira de Camargo). As linhas **BIONAISSANCE** e **CREME INTRODUTORIO 15G** devem
   estar com a caixinha marcada, ✓ verde de vendido, data de aquisição 11/08/2026 e o total da
   receita em **R$ 90,00** (antes: R$ 51,00, ver imagem anexada no chamado).
2. Regressão do fluxo: incluir um produto num pedido do oList que já esteja na receita como
   recomendado **desmarcado** e faturar o pedido → o item deve ficar marcado e o valor entrar no
   total. O log de prod registra `Tiny ERP: Item do pedido estava desmarcado na receita, marcando imprimir`.
3. Local: `ddev exec -d /var/www/html php artisan test --filter ProcessWebhookTiny`
   (5 testes). Suíte completa: 271 passando, 1 skipped.

Sem screenshots automatizadas: a receita só existe em produção e exigiria login real; o “antes”
está na imagem anexada ao chamado e o “depois” no output do workflow acima.

## Pendências / limitações conhecidas

- Webhook com situação **"aberto"** não sincroniza itens (só *aprovado*, *preparando envio*,
  *faturado*). Item incluído num pedido que nunca mude de situação depois não chega na receita.
- **Remoção** de item no oList não desmarca nada na receita — a sincronização só acrescenta/atualiza.
- `.github/workflows/diag-receita-17401-0008.yml` é pontual e pode ser removido.
