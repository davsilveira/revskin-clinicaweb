# Deploy Revskin na Hostinger (FileZilla + sem build/migrations no servidor)

Este guia considera que você **não** vai rodar `npm run build` nem `php artisan migrate` no servidor, e que usará **FileZilla** para enviar os arquivos. Inclui configuração de **cron** para filas (queue) e agendamento (schedule).

**URL de produção:** https://clinicaweb.revskin.com.br

---

## 0. Método rápido: comando `deploy:package`

Um único comando gera o pacote pronto para FTP (build, pastas e opcionalmente o dump SQL), **sem alterar seu `.env` local**.

1. **(Uma vez)** Copie `config/deploy.local.example` para `config/deploy.local.php`. A URL de produção já vem preenchida. Se quiser que o comando gere o `schema.sql`, preencha a chave `database` com um MySQL local ou de staging (o comando rodará as migrations nesse banco e fará o dump).
2. No terminal, na pasta do projeto:
   ```bash
   php artisan deploy:package
   ```
   Exige **Node 20+** para o build. Se já tiver rodado `npm run build` antes, use `php artisan deploy:package --no-build`.
3. Será criada a pasta **`deploy-package/`** com:
   - **`public_html/`** – enviar **todo** o conteúdo desta pasta para o **`public_html`** da Hostinger (único destino; a Hostinger não permite upload fora de `public_html`). Dentro já vêm `index.php`, `build/`, `images/` e a subpasta **`revskin/`** (app, vendor, etc.), protegida por `.htaccess`.
   - **`schema.sql`** – só aparece se você configurou `database` em `deploy.local.php`; importe no phpMyAdmin.
   - **`LEIA-ME.txt`** – resumo dos passos (FTP, .env, cron).

Depois: criar o `.env` em `public_html/revskin/.env` no servidor, configurar permissões e cron conforme a seção 6 abaixo (ou o LEIA-ME.txt).

---

## 1. O que fazer no seu computador (método manual)

### 1.1 Build dos assets (obrigatório)

Os arquivos JS/CSS precisam ser gerados **na sua máquina**. No terminal, na pasta do projeto:

```bash
APP_URL=https://clinicaweb.revskin.com.br npm run build
```

Isso gera os arquivos em **`public/build/`** (manifest + JS/CSS). Você **precisa** subir essa pasta `public/build/` para o servidor.

### 1.2 Migrations (banco já deve existir no servidor)

Como você não pode rodar `php artisan migrate` na Hostinger, há duas opções:

**Opção A – Rodar migrations da sua máquina apontando para o MySQL da Hostinger**

1. No painel Hostinger, crie um banco MySQL e anote: host, nome do banco, usuário, senha.
2. Se a Hostinger permitir **acesso remoto ao MySQL**, no seu `.env` local (só para rodar as migrations) use:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=seu_host_mysql_hostinger
   DB_PORT=3306
   DB_DATABASE=nome_do_banco
   DB_USERNAME=usuario
   DB_PASSWORD=senha
   ```

   Depois rode **uma vez**:

   ```bash
   php artisan migrate --force
   ```

3. Se a Hostinger **não** permitir acesso remoto, exporte o SQL das migrations no seu ambiente (por exemplo com SQLite), depois importe no **phpMyAdmin** da Hostinger:
   - Rode as migrations localmente (SQLite).
   - Exporte o banco (estrutura + dados iniciais que precisar).
   - No phpMyAdmin da Hostinger, importe esse SQL no banco do plano.

**Opção B – Apenas criar as tabelas manualmente**

Se preferir, você pode pegar o SQL gerado pelas migrations (por exemplo com `php artisan migrate --pretend` em outro ambiente) e colar no phpMyAdmin da Hostinger. O importante é que, no servidor, as tabelas existam e estejam alinhadas com as migrations do projeto.

---

## 2. O que subir pelo FileZilla

### 2.1 Hostinger: tudo dentro de `public_html`

A Hostinger **não permite** upload na raiz (há até o aviso DO_NOT_UPLOAD_HERE). Só se faz upload dentro de **`public_html`**.

O comando **`php artisan deploy:package`** já gera o pacote nesse formato:

- Tudo que você precisa subir fica dentro da pasta **`deploy-package/public_html/`**.
- Dentro dela: `index.php`, `build/`, `images/` e a subpasta **`revskin/`** (app, config, vendor, storage, etc.).
- A pasta `revskin/` tem um **`.htaccess`** que bloqueia acesso direto (ninguém acessa pela URL); o site só roda pelo `index.php` na raiz de `public_html`.

Estrutura no servidor (tudo dentro de `public_html`):

```text
public_html/              <- document root (único destino do FTP)
  index.php
  build/
  images/
  revskin/                <- Laravel (app, bootstrap, config, database, storage, vendor, .env, artisan...)
    .htaccess              <- Require all denied (bloqueia acesso direto)
    app/
    bootstrap/
    ...
```

**O que fazer:** enviar **todo** o conteúdo de `deploy-package/public_html/` para o **`public_html`** do seu domínio na Hostinger. Um único destino; não é necessário (nem possível) criar pasta irmã fora de `public_html`.

#### Redeploy (atualizações): preservar `storage`

Ao fazer **redeploy** (enviar uma nova versão do pacote), **não sobrescreva** a pasta `revskin/storage/` no servidor. Ela contém arquivos gerados em produção, como tabelas Karnaugh importadas. Se for sobrescrita, o download desses arquivos dará 404.

**Sugestão no FileZilla:** antes do upload, desmarque a pasta `revskin/storage/` na lista de arquivos a enviar. Assim o servidor mantém os arquivos já existentes.

Alternativa: faça backup manual de `revskin/storage/app/private/karnaugh/` antes de enviar o pacote completo.

### 2.2 Upload manual (sem o comando deploy:package)

Monte manualmente: conteúdo de `public/` em `public_html/` e o restante do app em `public_html/revskin/`, com `index.php` apontando para `__DIR__.'/revskin/'`. O comando `deploy:package` já gera tudo isso.



## 3. Arquivo `.env` no servidor

Crie um arquivo **`.env`** em **`public_html/revskin/.env`** no servidor (dentro da pasta `revskin` que está dentro de `public_html`). Use o `.env.example` como base e ajuste:

```env
APP_NAME="Revskin"
APP_ENV=production
APP_KEY=base64:xxxx   # gere com php artisan key:generate e cole aqui
APP_DEBUG=false
APP_TIMEZONE=America/Sao_Paulo
APP_URL=https://clinicaweb.revskin.com.br
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=production

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nome_do_banco
DB_USERNAME=usuario
DB_PASSWORD=senha

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Mail (ajuste com SMTP da Hostinger)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

# Infosimples (se usar)
INFOSIMPLES_ENABLED=true
```

- **APP_KEY:** no seu computador: `php artisan key:generate --show` e cole o valor.
- **DB_***: use os dados do MySQL que a Hostinger fornece (geralmente `DB_HOST=localhost` no mesmo servidor).

---

## 4. Permissões no servidor

Pelas ferramentas da Hostinger (Gerenciador de Arquivos ou FileZilla), garanta que o servidor consiga escrever em:

- `revskin/storage/` (recursivo) – ex.: 775 ou 755, conforme o que a Hostinger exigir.
- `revskin/bootstrap/cache/` (recursivo) – idem.

Sem isso, o Laravel pode dar erro 500 ao gerar cache e logs.

---

## 5. Link simbólico `storage` (arquivos públicos)

O Laravel usa `public/storage` → `storage/app/public` para arquivos públicos (ex.: assinaturas). Se você **tiver** acesso SSH na Hostinger:

```bash
cd /home/seu_usuario/public_html/revskin
php artisan storage:link
```

Isso cria `revskin/public/storage` apontando para `storage/app/public`. Como o document root é `public_html` (cópia de `public`), o link precisa estar acessível como `public_html/storage`. Se o comando criar o link dentro de `revskin/public/`, você precisará que esse `storage` exista em `public_html` – por exemplo copiando o link ou criando um link em `public_html/storage` → `../revskin/storage/app/public`. Sem SSH, veja no painel da Hostinger se há opção “Link simbólico” no gerenciador de arquivos; se não houver, arquivos em `storage/app/public` só serão acessíveis por rotas/controllers que você implementar para servir esses arquivos.

---

## 6. Cron na Hostinger (queue + schedule)

Você precisa de dois comportamentos:

1. **Processar a fila (queue)** – jobs em `database` precisam ser processados.
2. **Rodar o agendador (schedule)** – seus jobs agendados (ex.: `SyncProdutosTinyJob` às 12h e 00h) estão em `routes/console.php`.

Na Hostinger, isso é feito com **Cron Jobs** no painel.

### 6.1 Onde configurar

No painel da Hostinger: **Avançado → Cron Jobs** (ou similar). Você vai criar tarefas que rodam a cada minuto ou a cada 5 minutos.

### 6.2 Comando para o agendador (schedule)

Roda a cada minuto (obrigatório para o Laravel Schedule):

```bash
/usr/bin/php /home/seu_usuario/public_html/revskin/artisan schedule:run >> /dev/null 2>&1
```

Substitua `seu_usuario` e o caminho real do projeto. O `schedule:run` executa os jobs definidos em `routes/console.php` (incluindo os dois `SyncProdutosTinyJob`).

### 6.3 Comando para processar a fila (queue)

Como não há processo “worker” rodando 24h na shared host, use o cron para processar a fila de forma limitada a cada minuto (ou a cada 5 minutos):

```bash
/usr/bin/php /home/seu_usuario/public_html/revskin/artisan queue:work database --stop-when-empty --max-time=50
```

- `--stop-when-empty`: para quando não houver mais jobs (evita ficar rodando à toa).
- `--max-time=50`: sai após 50 segundos para não ultrapassar o limite de tempo do cron (ex.: 60 s).

**Frequência sugerida:** a cada 1 minuto, junto com o `schedule:run`, ou a cada 5 minutos se quiser reduzir carga. Exemplo de linha de cron (a cada minuto):

```text
* * * * * /usr/bin/php /home/seu_usuario/public_html/revskin/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /home/seu_usuario/public_html/revskin/artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

O caminho do PHP pode variar (ex.: `php` em vez de `/usr/bin/php`). A Hostinger costuma mostrar o caminho correto na tela de Cron Jobs.

---

## 7. Checklist rápido

- [ ] Build local com `APP_URL` de produção; pasta `public/build/` gerada.
- [ ] **Redeploy:** Não sobrescrever `revskin/storage/` no servidor (preservar tabelas Karnaugh e outros arquivos gerados).
- [ ] Migrations aplicadas (remotamente no MySQL da Hostinger ou via export/import no phpMyAdmin).
- [ ] Projeto (exceto `public`) em pasta tipo `revskin/`; conteúdo de `public/` em `public_html/`.
- [ ] `index.php` em `public_html` ajustado para `../revskin/` (ou nome da pasta).
- [ ] `.env` criado em `revskin/` com APP_KEY, DB_*, APP_URL, APP_DEBUG=false.
- [ ] Permissões em `storage/` e `bootstrap/cache/`.
- [ ] `storage:link` (se possível) ou alternativa para arquivos em `storage/app/public`.
- [ ] Cron: `schedule:run` a cada minuto e `queue:work database --stop-when-empty --max-time=50` (ex.: a cada 1 ou 5 minutos).

Depois disso, acesse `https://seudominio.com` e teste login e uma tela que use fila/agendamento para validar o cron.
