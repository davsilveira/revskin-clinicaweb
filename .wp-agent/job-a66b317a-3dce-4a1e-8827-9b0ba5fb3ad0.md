# Follow-up – Confirmação do comportamento do TONALITE (placeholder __ + fototipo)

**Job:** a66b317a-3dce-4a1e-8827-9b0ba5fb3ad0 (segue 3429e564 / a0129a17)
**Data:** 2026-07-15
**Ambiente:** revski-main. **Nenhum código alterado — só verificação.**

## Contexto
Você reforçou que o TONALITE é resolvido em runtime pelo fototipo, usa `__` como placeholder no Karnaugh, e que o oList/base local têm todos os tonalités. Confirmo abaixo com dados reais que isso **foi considerado** e não muda a conclusão.

## Verificação (somente leitura)

**1. Base local tem todos os tonalités (como você disse):** 8 fototipos × 2 tamanhos, todos ativos:
`TONALITE 1 30G`, `TONALITE 1,5 30G` … `TONALITE 4,5 30G` (e os `50G`). Todos `ativo=1`.
(Também existe o produto-template `TONALITE-___-G30` "Nº ___", que é outro placeholder de catálogo, não usado aqui.)

**2. Template gravado no Karnaugh (por tabela):**
- V16 (id 13, **a que o assistente usa**): `TONALITE-__-30G`  → cat "BB Cream Tonalité"
- Tabelas 11 e 12 (não usadas): `TONALITE-__-G30` (ordem `G30` invertida)

**3. Resolução runtime do motor (`resolverCodigoTonalite`) para cada fototipo, na V16:**

| fototipo | resolvido pelo motor | casa exato hoje? | casa com normalização (hífen→espaço)? |
|---|---|---|---|
| 1 | `TONALITE-1-30G` | ❌ não | ✅ `TONALITE 1 30G` |
| 1.5 | `TONALITE-1,5-30G` | ❌ | ✅ `TONALITE 1,5 30G` |
| 2 | `TONALITE-2-30G` | ❌ | ✅ `TONALITE 2 30G` |
| 2.5 | `TONALITE-2,5-30G` | ❌ | ✅ `TONALITE 2,5 30G` |
| 3 | `TONALITE-3-30G` | ❌ | ✅ `TONALITE 3 30G` |
| 3.5 | `TONALITE-3,5-30G` | ❌ | ✅ `TONALITE 3,5 30G` |
| 4 | `TONALITE-4-30G` | ❌ | ✅ `TONALITE 4 30G` |
| 4.5 | `TONALITE-4,5-30G` | ❌ | ✅ `TONALITE 4,5 30G` |

## Conclusão (inalterada)
- Os produtos TONALITE **existem** na base local e no oList — o problema **não** é ausência de produto.
- O motor resolve o `__` corretamente pelo fototipo, mas gera o código com **hífen** (`TONALITE-2,5-30G`), enquanto o produto está com **espaço** (`TONALITE 2,5 30G`). Por isso, no estado atual, o TONALITE **não casa em nenhum dos 8 fototipos** e some da receita — é o mesmo bug de separador dos demais itens.
- Com a **normalização de separador no motor (Frente A)**, os 8 fototipos casam de forma única e correta.
- Isto reforça por que a **task de dados (Frente B) sozinha não resolve o TONALITE**: o template continua `__` e a resolução acontece em runtime; quem precisa normalizar é o motor. Ou seja, para o TONALITE a Frente A é obrigatória.

Nada a corrigir no diagnóstico anterior — apenas confirmado com evidência.
