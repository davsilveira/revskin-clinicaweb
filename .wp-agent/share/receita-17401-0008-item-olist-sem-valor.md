# Receita 17401-0008 — item incluído no oList não somou valor

**Paciente:** Giovana Naccarato Ferreira de Camargo · **Receita:** 17401-0008 (id 28079, finalizada)
**Pedido oList:** nº 3646 (id 983312351), situação *Faturado* · **Data:** 11/08/2026

---

## 1. O que ocorreu

O pedido no oList tem **3 itens** (conferido na API do oList):

| Produto | Qtd | Valor |
|---|---|---|
| R0,015H3 NEODELINE – Creme da noite | 1 | R$ 51,00 |
| BIONAISSANCE – Creme de limpeza regenerador | 1 | R$ 39,00 |
| CREME INTRODUTORIO 15G | 1 | R$ 0,00 |

A receita, porém, estava com **total de R$ 51,00** — só o primeiro item.

Linha do tempo (log de produção):

| Hora (11/08) | Evento |
|---|---|
| 10:46 | webhook *faturado* → NEODELINE marcado como vendido (R$ 51,00 no total) |
| 11:09 | webhook *faturado* → **BIONAISSANCE** marcado como vendido, **mas fora do total** |
| 11:17 e 11:36 | webhooks com situação "aberto" → ignorados (esperado) |
| 11:53 | webhook *faturado* → **CREME INTRODUTORIO** marcado como vendido, **mas fora do total** |

### Causa raiz

Os dois produtos acrescentados **já estavam na receita como recomendados, porém desmarcados**
(`imprimir = 0` — a “caixinha” da linha desmarcada na tela).

Quando o webhook do oList sincroniza o pedido (`ProcessWebhookTinyJob`), para um produto que já
existe na receita ele atualizava quantidade, valor unitário, `vendido = 1` e registrava a data de
aquisição — **mas não marcava a caixinha** (`imprimir`). E o cálculo do total da receita
(`Receita::calcularTotais()`) soma **apenas os itens marcados**:

```php
$subtotal = $this->itens->where('imprimir', true)->sum('valor_total');
```

Resultado: item aparecia com o “✓ verde” de vendido e com a data de aquisição, mas com **“-” na
coluna Total** e **sem entrar no valor da receita** — exatamente o que a cliente reportou.

> Observação: quando o produto acrescentado **não existia** na receita, o sistema criava a linha
> já marcada (`imprimir = true`) e o valor entrava normalmente. Por isso o problema só aparecia
> no caso (mais comum) de o produto ter sido recomendado pelo médico e desmarcado no fechamento.

---

## 2. O que foi feito para não acontecer mais

**Corrigido e já em produção** (deploy concluído em 12/08/2026):

- `app/Jobs/ProcessWebhookTinyJob.php` — ao sincronizar o pedido do oList, todo item que está no
  pedido passa a ser **marcado** (`imprimir = 1`), pois foi efetivamente comprado. Vale tanto para
  a situação *Faturado* quanto para as intermediárias (*aprovado* / *preparando envio*). O log
  registra cada linha remarcada (`Item do pedido estava desmarcado na receita, marcando imprimir`).
- Testes automatizados novos (`tests/Feature/ProcessWebhookTinyItemDesmarcadoTest.php`):
  item desmarcado que entra no pedido volta a contar no total; item **fora** do pedido continua
  desmarcado (não infla receita); e o comando de correção conserta receitas antigas.
  Suíte completa: 271 testes passando.

### Limitações que permanecem (não são o caso desta receita)

- Webhooks com situação **"aberto"** não sincronizam itens — só *aprovado*, *preparando envio* e
  *faturado*. Se um item for incluído e o pedido nunca mudar de situação depois, ele não chega na
  receita. Na prática o pedido sempre passa por faturado, foi o que ocorreu aqui.
- Se um item for **removido** do pedido no oList depois de faturado, a receita continua com ele.
  Hoje a sincronização só acrescenta/atualiza, nunca desmarca.

---

## 3. Correção desta receita

Já aplicada em produção via comando novo `tiny:corrigir-itens-vendidos-nao-impressos`
(dry-run primeiro, depois `--force`):

```
- receita #28079 (17401-0008, finalizada) pedido=983312351 total_atual=51.00 delta_previsto=+39
    item#343478 BIONAISSANCE            qtd=1 vu=39.00
    item#343481 CREME INTRODUTORIO 15G  qtd=1 vu=0.00
Aplicado: receita #28079 (17401-0008) subtotal=90.00 total=90.00
```

**Resultado:** receita 17401-0008 agora com **R$ 90,00**, batendo com o pedido no oList
(51,00 + 39,00 + 0,00). Os três itens aparecem marcados, com data de aquisição 11/08/2026.

Uma varredura em **toda a base** encontrou apenas esses 2 itens no estado errado
(`vendido = 1` e `imprimir = 0`), ambos nessa receita. Após a correção: **0 itens**.

Conferir em: https://clinicaweb.revskin.com.br → Receitas → 17401-0008.

### Se voltar a acontecer em alguma receita antiga

```bash
php artisan tiny:corrigir-itens-vendidos-nao-impressos                      # simula (toda a base)
php artisan tiny:corrigir-itens-vendidos-nao-impressos --receita=17401-0008 # simula uma receita
php artisan tiny:corrigir-itens-vendidos-nao-impressos --force              # aplica
```

Ou pelo GitHub Actions: **Corrigir itens vendidos no oList sem imprimir** (input `modo` =
`dry-run` ou `force`). O comando só toca em item com prova de compra: `vendido = 1` **e** aquisição
registrada com o mesmo `tiny_pedido_id` da receita.
