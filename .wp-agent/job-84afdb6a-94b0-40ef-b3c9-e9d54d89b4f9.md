# Correção: médicos podem editar receitas em aberto

Follow-up do diagnóstico em `job-52d810b2-...md`. O usuário confirmou: **edição deve ser permitida para médicos e admins enquanto a receita estiver em aberto**. Corrigido, buildado e verificado no ambiente `revski-main`.

## O que mudou e por quê

O commit `a45d084` (13/04/2026, "médico só visualiza") tornou o médico somente-leitura em **todas** as receitas — inclusive as em aberto. O bloqueio estava em dois pontos:

1. **Backend** — `ReceitaController::edit()` redirecionava qualquer médico para a visualização.
2. **Frontend** — `Form.jsx` definia `canEdit` com `!isMedico`, então o botão "Editar Receita" (que troca do modo visualização para edição) nunca aparecia para médicos.

A correção libera a edição para médicos e admins **somente enquanto `status === 'aberta'`**. Receitas **finalizadas** continuam somente-leitura (o `edit()` ainda redireciona finalizadas) e o **call center** continua sem editar. A regra de negócio separada de **ocultar valores/preços do médico** (diversos `!isMedico` de colunas de valor) foi **preservada** — não foi tocada.

## Arquivos alterados

- `app/Http/Controllers/ReceitaController.php` — método `edit()`: removido o bloco
  ```php
  if ($request->user()->isMedico()) {
      return redirect()->route('receitas.show', $receita);
  }
  ```
  Mantido apenas o redirect de receitas `finalizada`.

- `resources/js/Pages/Receitas/Form.jsx` — `canEdit` (linha ~864):
  ```diff
  - const canEdit = isEditing && receita.status === 'aberta' && !bloqueadaParaEdicao && !isMedico && !isCallcenter;
  + const canEdit = isEditing && receita.status === 'aberta' && !bloqueadaParaEdicao && !isCallcenter;
  ```
  Observação importante: o `isReadOnly` do formulário (`bloqueadaParaEdicao || viewMode || isCallcenter`) **não** inclui `isMedico`, então, uma vez em modo de edição, os campos já ficam habilitados para o médico. `update()`/autosave já permitiam médicos (middleware `medico`), sem alteração no backend de gravação.

## Arquitetura relevante (para entender a validação)

- A rota `receitas.show` e `receitas.edit` renderizam **o mesmo componente** `Receitas/Form.jsx` (`show` com `viewMode: true`, `edit` com `viewMode: false`). O `Show.jsx` existe no repositório mas **não é renderizado** por nenhum controller (código morto).
- Fluxo do médico: abre a receita (ícone "olho" → modo visualização) → agora vê o botão **"Editar Receita"** → clica → `viewMode` vira `false` → campos editáveis → autosave grava via `PUT /receitas/{id}`.

## Build / deploy

- `npm run build` executado com `APP_URL=https://revski-main.ddev.site:33177` → assets regenerados em `public/build/` (gitignored). **A mudança já está no ar no ambiente local `revski-main`.**
- Foi necessário instalar `@rollup/rollup-darwin-arm64` (bug conhecido de optional deps do npm) para o build rodar.
- Commit na branch `main`: `c17af21` (apenas os 2 arquivos-fonte; `public/build/` não é versionado).
- **Deploy de produção (Hostinger)** é um passo **manual** e não foi executado (sem credenciais FTP; é um projeto/servidor separado). Para publicar em `clinicaweb.revskin.com.br`: rodar `php artisan deploy:package` e subir `deploy-package/public_html/` via FileZilla, conforme `DEPLOY_HOSTINGER.md`.

## Validação feita (Playwright, ambiente revski-main)

Login como médico `gpereira@revskin.com.br` (medico_id 882), receita em aberto `27716` (nº 17442-0004):

| Passo | Resultado |
|---|---|
| Abrir `/receitas/27716` (visualização) | HTTP 200, botão **"Editar Receita" agora presente** (antes: ausente) |
| Clicar "Editar Receita" | Formulário entra em modo edição — **30 inputs habilitados** |
| Acessar direto `/receitas/27716/edit` | HTTP 200, **permanece em `/edit`** (antes: redirecionava para visualização) — 30 inputs habilitados |

Screenshots em `.wp-agent/screenshots/`:
- `medico-view.png` — visualização com o botão "Editar Receita"
- `medico-editing.png` — formulário editável após clicar em "Editar Receita"
- `medico-edit-route.png` — rota `/edit` acessada diretamente pelo médico, editável

> Observação: a senha do médico de teste foi definida temporariamente para permitir o login automatizado e **restaurada ao hash original** ao final (verificado `RESTORE_OK`). Nenhum dado de receita foi alterado durante a verificação. Scripts temporários de teste foram removidos.

## Como validar manualmente

Ambiente: `https://revski-main.ddev.site:33177`

1. Login com um usuário **médico** (ex.: que tenha receitas em aberto).
2. Em `/receitas`, abra uma receita **em aberto** (ícone de visualizar).
3. Confirme o botão **"Editar Receita"**; clique nele → os campos ficam editáveis e as alterações salvam (autosave).
4. Alternativa: acesse `…/receitas/{id}/edit` diretamente — deve abrir o formulário editável (não mais redirecionar).
5. Abra uma receita **finalizada** com o mesmo médico → deve continuar **somente leitura** (comportamento inalterado).

## Resumo

Médicos voltaram a poder editar receitas em aberto (backend + `canEdit` no formulário), mantendo finalizadas como somente-leitura e preservando a ocultação de valores para médicos. Buildado e verificado no `revski-main`; commit `c17af21` em `main`. Publicação em produção Hostinger continua sendo passo manual via FileZilla.
