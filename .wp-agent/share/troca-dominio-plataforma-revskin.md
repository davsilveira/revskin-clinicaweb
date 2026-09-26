# Troca de domínio: `clinicaweb.revskin.com.br` → `plataforma.revskin.com.br`

**Avaliação da aplicação + passo a passo.** Criado em 2026-09-22.
**Atualizado em 2026-09-26** — troca executada; site voltou ao ar. Ver o pós-morte no fim.

> ## ✅ Status: CONCLUÍDO
> `https://plataforma.revskin.com.br` no ar (HTTP 200), assinaturas OK, crons novos rodando,
> deploy validado. **Faltam 3 coisas suas:** remover os 2 crons antigos, corrigir o 301 do
> domínio antigo (hoje aponta para o site institucional errado) e atualizar Tiny + RD Station.

---

## Resposta curta

**A aplicação em si não precisa de mudança de código.** Ela não tem o domínio embutido em
lugar nenhum que importe em runtime. O que precisa mudar é **configuração** — e há **5 pontos
fora do repositório que, se esquecidos, quebram coisas de forma silenciosa** (deploy, cron,
Tiny, RD Station, SSL).

As referências que existiam no repositório (workflow de deploy, scripts, docs) **já foram
atualizadas** neste job.

---

## O que eu verifiquei na aplicação

| Verificação | Resultado |
|---|---|
| URLs geradas por `route()` / `url()` / `asset()` | Todas derivam de `APP_URL` — testado com `APP_URL=https://plataforma.revskin.com.br`, saiu certo |
| `SESSION_DOMAIN` | `null` → cookie *host-only*. Não trava nada; os usuários só logam de novo |
| `.htaccess` (`public/` e pacote de deploy) | Não filtra `HTTP_HOST`, não tem redirect fixo |
| Tabela `settings` no banco | Nenhuma URL própria guardada (só `tiny_url_base`, que é a API do Tiny) |
| Views Blade / PDF / e-mails | Zero URL absoluta escrita à mão; tudo via `asset()` |
| URLs assinadas (`signedRoute`) | Não são usadas — nada expira por mudança de host |
| Assets do Vite | Gerados pelo manifest + `asset()`, sem host fixo |
| DNS de `revskin.com.br` | Nameservers da Hostinger (`ns1/ns2.dns-parking.com`) → o subdomínio novo é criado automaticamente pelo painel |
| `plataforma.revskin.com.br` | ✅ no ar desde 26/09/2026 (HTTP 200) |

Ou seja: `APP_URL` certo no `.env` do servidor + `config:cache` e a aplicação funciona no
domínio novo.

---

## Já alterado no repositório (este job)

| Arquivo | O quê |
|---|---|
| `.github/workflows/deploy-hostinger.yml` | `env.APP_URL` usado no `npm run build` + texto de erro |
| `scripts/enviar-dump-legado.sh` | fallback de `REMOTE_PATH` |
| `app/Console/Commands/DeployPackageCommand.php` | URL default impressa no `LEIA-ME.txt` do pacote |
| `app/Http/Controllers/TinyIntegrationController.php` | comentário com a URL de callback de produção |
| `config/deploy.local.example` e `config/deploy.local.php` | `app_url` do pacote manual |
| `DEPLOY_HOSTINGER.md` | doc inteiro + **nova §9 "Troca de domínio"** com este runbook |

Restou **zero** ocorrência de `clinicaweb.revskin.com.br` no repositório.
(Os e-mails `@revskin.com.br` e `@legado.revskin.com.br` no código são *endereços de usuário*,
não o domínio do site — foram deixados como estão, de propósito.)

---

## O que VOCÊ precisa fazer fora do repositório

Ordem recomendada:

### 0. Antes de clicar em "Alterar"
A Hostinger avisa que **os backups existentes do site serão perdidos**. Faça um backup manual:
- arquivos (ou confie no Git — o código está todo no repositório);
- **dump do MySQL** pelo phpMyAdmin. Esse é o que não dá para recuperar.

### 1. hPanel → Alterar o domínio do site
A pasta do site é renomeada de `~/domains/clinicaweb.revskin.com.br/` para
`~/domains/plataforma.revskin.com.br/`. **Confirme por SSH** (`ls ~/domains`) antes de seguir —
os passos 2 e 4 dependem desse caminho.

### 2. GitHub → variable `HOSTINGER_REMOTE_PATH`  ⚠️ **o mais fácil de esquecer**
`Settings → Secrets and variables → Actions → Variables`:

```
/home/u368085046/domains/plataforma.revskin.com.br/public_html
```

Todos os workflows (deploy + os de diagnóstico) leem essa variable — é um ponto só.
**Se não trocar: o rsync recria a pasta antiga e o deploy "passa em verde" sem publicar nada
no site.** É a falha mais traiçoeira da lista.

### 3. `.env` no servidor
Em `public_html/revskin/.env`:

```env
APP_URL=https://plataforma.revskin.com.br
```

Depois `php artisan config:cache` (ou simplesmente rode `scripts/hostinger-post-deploy.sh`,
que já faz isso).

### 4. Cron jobs  ⚠️ **quebra silenciosa**
hPanel → **Avançado → Cron Jobs**. As duas linhas têm o caminho absoluto com o domínio antigo:

```
* * * * * /usr/bin/php /home/u368085046/domains/plataforma.revskin.com.br/public_html/revskin/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /home/u368085046/domains/plataforma.revskin.com.br/public_html/revskin/artisan queue:work database --queue=default,tiny-sync,exports,rd-sync,rd-webhooks,tiny-webhooks --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Sem isso, **fila e agendamento param sem erro visível**: sync do Tiny, exportações e o
processamento dos webhooks simplesmente deixam de rodar.

### 5. SSL
hPanel → Segurança → SSL: emitir certificado para o domínio novo. Até lá, acesso dá erro de
certificado.

### 6. Tiny ERP
No painel do app/integração do Tiny:
- **Redirect URI do OAuth2** → `https://plataforma.revskin.com.br/integracoes/tiny/callback`
  — tem que bater **exatamente**, senão a reautorização falha com `invalid_redirect_uri`;
- **Webhook de pedido** → `https://plataforma.revskin.com.br/api/webhooks/tiny/pedido-finalizado`

O token atual continua valendo (não está amarrado ao domínio), mas na **próxima
reautorização** o redirect URI precisa já estar certo.

> **Verificado em 26/09/2026:** o campo no Tiny estava com `http://localhost:9090/...` — nunca
> foi apontado para produção, nem no domínio antigo. Não quebrou nada porque a produção roda na
> **API V2 (token estático)**, que não usa OAuth; e mesmo na V3 a renovação por `refresh_token`
> não envia `redirect_uri`. É uma mina dormente: estoura no dia em que alguém clicar
> "Autorizar". Detalhes em `tiny-url-redirecionamento.md`.

### 6b. RD Station — mesmo padrão
- **Redirect URI** → `https://plataforma.revskin.com.br/integracoes/rd-station/callback`

### 7. RD Station CRM
Webhook → `https://plataforma.revskin.com.br/api/webhooks/rd/crm-deal-updated`

### 8. Redirect do domínio antigo (opcional, recomendado)
Manter `clinicaweb.revskin.com.br` como subdomínio com **301** para o novo. Os médicos têm
links antigos em favoritos e e-mails (inclusive links de reset de senha ainda válidos).

---

## O que NÃO é afetado

- **E-mails `@revskin.com.br`** — ficam no site `revskin.com.br`, que é uma **entrada separada**
  no painel e não está sendo renomeado. O aviso da Hostinger ("e-mails vinculados ao domínio
  atual serão perdidos") vale para caixas `@clinicaweb.revskin.com.br`; o `MAIL_FROM_ADDRESS`
  de produção é `noreply@revskin.com.br`. ✅
- **Banco de dados e arquivos enviados** — a Hostinger renomeia a pasta, não move/apaga conteúdo.
- **Logins** — cookie é host-only; todo mundo simplesmente loga de novo no endereço novo.
- **Subdomínios** — o aviso "todos os subdomínios associados serão removidos" se refere a
  subdomínios *de* `clinicaweb.revskin.com.br`, que não existem.

---

## Checklist

- [x] Backup manual (dump do MySQL) feito
- [x] Domínio alterado no hPanel; pasta nova confirmada por SSH
- [x] **`optimize:clear` + `hostinger-post-deploy.sh`** ← *faltava na lista original; foi o que quebrou*
- [x] **Symlink `public_html/storage` refeito** ← *idem*
- [x] `HOSTINGER_REMOTE_PATH` atualizado no GitHub (conferido com `gh variable list`)
- [x] `APP_URL` no `.env` de produção + `config:cache`
- [x] Cron jobs novos criados e testados
- [ ] **Remover os 2 cron jobs antigos** (caminho `clinicaweb`)
- [x] SSL emitido
- [ ] **Tiny: redirect URI + webhook**
- [ ] **RD Station: webhook**
- [ ] **Redirect 301 do domínio antigo** — hoje aponta para `revskin.com.br` (site institucional),
      deveria apontar para `plataforma.revskin.com.br`
- [x] Push em `main` de validação: deploy verde no diretório novo, site abre, login renderiza,
      `/build/` 200, `/storage/` (assinatura do médico) 200

---

## Sobre o push destas mudanças

Push em `main` = deploy automático. As alterações deste job são seguras em qualquer ordem
(só mexem em build/docs), mas o caminho mais tranquilo é:

**trocar o domínio no hPanel → atualizar `HOSTINGER_REMOTE_PATH` → só então dar o push.**

Assim o primeiro deploy pós-troca já serve de validação de ponta a ponta.

*(Feito em 26/09/2026: commit `bebfb65`, run `36240696874`, deploy verde em 41 s no diretório novo.)*


---

# Pós-morte (2026-09-26): o 500 depois da renomeação

## O que aconteceu

Depois de renomear o domínio no hPanel, o site passou a dar **HTTP 500 em tudo**, e — o que mais
confundiu — **nada aparecia no `storage/logs/laravel.log`**. A suspeita natural foi "é uma camada
antes do Laravel" (LiteSpeed, .htaccess, PHP). Era o contrário.

## O sinal que entrega em 5 segundos

```bash
curl -sSI https://plataforma.revskin.com.br/ | grep -i "vary\|set-cookie"
```

Voltou `vary: X-Inertia` e o cookie `revskin_session`. **Uma camada antes do Laravel não emite
esses headers** — quem respondia o 500 era a própria aplicação, já bootada.

## A causa

`bootstrap/cache/config.php` guarda **caminhos absolutos** — 20 deles. Como a pasta do site foi
renomeada, todos passaram a apontar para um diretório que não existe mais:

| Config com caminho velho | Efeito |
|---|---|
| `view.paths` / `view.compiled` | `View [app] not found` → **500 em toda requisição** |
| `logging…path` | **o log ia para o diretório morto** → `laravel.log` novo vazio |

Ou seja: **o mesmo cache causava o erro e escondia o erro.** O stacktrace real estava em
`~/domains/clinicaweb.revskin.com.br/public_html/revskin/storage/logs/laravel.log`, arquivo que o
Monolog recriou sozinho no caminho antigo:

```
production.ERROR: View [app] not found.
  at .../plataforma.revskin.com.br/.../FileViewFinder.php:138
```

Note a assimetria que fecha o diagnóstico: **código rodando em `plataforma`, log escrito em
`clinicaweb`**.

Diagnóstico direto:

```bash
grep -c 'clinicaweb' bootstrap/cache/config.php    # != 0 → é isso
```

## A correção

```bash
cd ~/domains/plataforma.revskin.com.br/public_html/revskin
/opt/alt/php84/usr/bin/php artisan optimize:clear      # site volta aqui
bash scripts/hostinger-post-deploy.sh                  # re-cache + refaz symlink
```

## O segundo problema, que ainda não tinha aparecido

O symlink `public_html/storage` **também é absoluto** e continuava apontando para a pasta antiga.
O site teria voltado com **todas as assinaturas dos médicos em 404**. O `hostinger-post-deploy.sh`
refez o link — por isso vale rodar o script inteiro, não só o `optimize:clear`.

## O terceiro: a variable do GitHub não tinha sido salva

`gh variable list` mostrava ainda o caminho `clinicaweb`, com data de **2026-06-24**. A alteração
não chegou a ser gravada. Foi corrigida neste job. **Confira sempre com `gh variable list`** em
vez de confiar na tela — se ficasse errada, o deploy rodaria verde recriando a pasta antiga e sem
publicar nada.

## Crons: os novos estão certos

Executei os três à mão no servidor:

```
NOVO  schedule:run  → rodou e disparou 'integration-queues-worker'   ✅
NOVO  queue:work    → exit 0                                         ✅
ANTIGO              → Could not open input file: .../clinicaweb/...  ❌
```

E o log já registrou um pull do Tiny completo às 09:00 (8 pacientes atualizados) — cron novo
trabalhando. **Pode remover as duas linhas antigas.**

## ⚠️ O domínio antigo está redirecionando para o lugar errado

```
https://clinicaweb.revskin.com.br/  →  301  →  https://revskin.com.br/
```

Vai para o **site institucional (WordPress)**, não para a plataforma. Quem tiver link salvo ou
e-mail antigo cai no site de marketing e vai achar que o sistema saiu do ar. Vale repontar o 301
para `https://plataforma.revskin.com.br`.

## Lição para a próxima renomeação

Renomear a pasta do site quebra **tudo que guarda caminho absoluto**: o cache de config do
Laravel, o symlink de `storage` e as linhas de cron. Nenhum dos três dá erro legível — o cache dá
500 mudo, o symlink dá 404 nas imagens e o cron morre em silêncio. Faça o `optimize:clear` **antes
de testar o site**, e não depois de perder tempo procurando no LiteSpeed.
