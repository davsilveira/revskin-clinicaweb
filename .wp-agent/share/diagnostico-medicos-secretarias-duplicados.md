# Diagnóstico: cadastros de médicos e secretárias duplicados

**Data:** 2026-07-21 · **Ambiente:** `revski-main` (dados reais medidos)

## Resposta curta
Sim, dá para explicar e dá para limpar com segurança. Os "médicos duplicados" são
**14 casos** em que a **mesma pessoa tem duas contas de usuário**: uma da 1ª
importação (e-mail `@legado.revskin.com.br`, que segura os dados do médico) e outra da
2ª rodada (e-mail limpo `@revskin.com.br`, "Dra. X", **vazia**). A 2ª foi criada sem
reaproveitar a 1ª, então as duas ficaram. As shells vazias podem ser removidas; o
médico fica com **uma** conta e o e-mail limpo.

## Números (medidos agora)
- **184 usuários**: 11 admin · 96 médico · 77 secretária.
- **41 e-mails** com `@legado.revskin.com.br` (resquício da 1ª importação).
- **14 médicos duplicados** = par **legado (com os dados)** + **shell vazia (sem
  `medico_id`)**. Ex.: `arodrigues@legado.revskin.com.br` (Aline, com dados) e
  `arodrigues@revskin.com.br` ("Dra. Aline", vazia).
- **As 14 shells têm 0 dependências** (nenhum paciente, receita ou vínculo) → seguras
  para remover.
- **27 contas legado sem par** (12 médico + 11 secretária + 4 admin) = as que **estão
  em uso** → **não** remover.
- **11 secretárias genéricas** "Secretaria Administrativa 1..11" (uma por clínica) →
  **existem no dump legado mais recente** (`bkp_cw2_20260610.sql`) → **mantidas**.
- 0 CRMs duplicados · 0 médicos vazios · 0 e-mails idênticos · ninguém é médico e
  secretária ao mesmo tempo.

## Por que ainda entra duplicado
A 1ª importação criou os usuários com `@legado.revskin.com.br` + o registro do médico.
Depois o script foi ajustado para e-mail sem "legado" e **recriou** os médicos ("Dra.
X") **sem checar** se já existiam — gerando a 2ª conta vazia. Faltou a deduplicação.

## Como limpar (com segurança) — decisões já fechadas
Um comando idempotente `usuarios:auditar-duplicados-legado`, **dry-run por padrão**,
que eu disparo por workflow após o deploy (mesmo padrão do
`medicos:reestabelecer-pacientes`):
1. **14 pares médico → manter e-mail limpo `@revskin.com.br`.** Consolidar em uma
   conta com o e-mail limpo + os dados; apagar a shell vazia (revalidando 0
   dependências antes).
2. **27 contas legado sem par → limpar o sufixo `@legado`.** Renomear
   `xxx@legado.revskin.com.br` → `xxx@revskin.com.br` (12 médico + 11 secretária + 4
   admin), com checagem de colisão. Senha inalterada; só muda o e-mail de login.
3. **11 secretárias genéricas → manter.** **Verifiquei no dump legado mais recente**
   (`bkp_cw2_20260610.sql`, 10/jun): as 11 existem na tabela `user` do legado, logo
   são legítimas — nenhuma ação.

## Prevenção
Deduplicar por e-mail/CRM na criação de médico/usuário (reutilizar se já existe) e
descontinuar o padrão `@legado.revskin.com.br` após a limpeza.

## Status das decisões
Todas fechadas pelo cliente (manter e-mail limpo · limpar sufixo `@legado` das 27 ·
manter as 11 genéricas por existirem no legado). Sem pendências.

> Observação: a imagem anexada nesta solicitação (tela do AffiliateWP/Payouts) não
> tem relação com o tema — parece anexo trocado. Este diagnóstico veio dos dados do
> sistema.
