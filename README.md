# Laravel + React Boilerplate

A complete starting point for Laravel + React projects with authentication, admin panel, user management, and more.

## 🚀 Tech Stack

### Backend
- **Laravel 12.x** - Modern PHP framework
- **PHP 8.2+** - Modern PHP features
- **SQLite/MySQL/PostgreSQL** - Flexible database options
- **Inertia.js** - Modern monolithic architecture

### Frontend
- **React 19** - Modern UI library
- **Inertia.js** - SPA without building an API
- **Tailwind CSS 4.x** - Utility-first CSS framework
- **Vite 7** - Fast build tool with HMR

### Development
- **Node.js LTS Iron (v20.x)** - JavaScript runtime
- **Composer** - PHP dependency manager
- **NPM** - Node package manager

## ✨ Included Features

### Authentication
- ✅ Login and Logout
- ✅ Password recovery via email
- ✅ Persistent session ("Remember me")
- ✅ Route protection

### User Management
- ✅ Full user CRUD
- ✅ Role system (admin/user)
- ✅ Account activation/deactivation
- ✅ User profile with editing

### Admin Panel
- ✅ Responsive layout with sidebar
- ✅ Initial dashboard
- ✅ Configurable navigation menu
- ✅ Topbar with user menu
- ✅ Profile editing drawer

### Export System
- ✅ Asynchronous export via jobs
- ✅ Email notification when ready
- ✅ Export history
- ✅ File download

### Reusable Components
- ✅ Drawer (slide-over panel)
- ✅ Pagination
- ✅ Toast notifications
- ✅ Input, Select, Checkbox
- ✅ MaskedInput (with react-imask)

### Infrastructure
- ✅ Ready migrations
- ✅ Example seeders
- ✅ Queue system (database)
- ✅ Email configuration
- ✅ Development scripts

## 📋 Prerequisites

Before starting, make sure you have installed:

- **PHP 8.2 or higher**
  ```bash
  php --version
  ```

- **Composer** (latest version)
  ```bash
  composer --version
  ```

- **Node.js LTS Iron (v20.x)**
  ```bash
  # If using NVM (recommended)
  nvm install lts/iron
  nvm use lts/iron
  
  # Verify
  node --version  # Should show v20.x.x
  ```

- **Git**
  ```bash
  git --version
  ```

## 🛠️ Local Development Setup

### 1. Clone the Repository

```bash
git clone <repository-url> my-project
cd my-project
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configure Database

#### Option A: SQLite (Default - Easiest)

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed
```

#### Option B: MySQL

1. Create a MySQL database
2. Edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_project
DB_USERNAME=root
DB_PASSWORD=your_password
```
3. Run migrations:
```bash
php artisan migrate
php artisan db:seed
```

### 6. Start Development Servers

**🚀 RECOMMENDED: Use the all-in-one command:**

```bash
composer dev
```

This command starts:
- ✅ Laravel server (port 9000)
- ✅ Queue worker (processes jobs)
- ✅ Log viewer (Laravel Pail)
- ✅ Vite dev server (HMR)

**Alternative: Run separately**

```bash
# Terminal 1 - Backend
php artisan serve --port=9000

# Terminal 2 - Frontend
npm run dev

# Terminal 3 - Queue Worker
php artisan queue:work
```

### 7. Access the Application

```
http://localhost:9000
```

**Test credentials:**
- **Admin:** admin@example.com / password
- **User:** user@example.com / password

## 📁 Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/AuthController.php    # Authentication
│   │   │   ├── DashboardController.php    # Dashboard
│   │   │   ├── ExportController.php       # Exports
│   │   │   ├── ProfileController.php      # Profile
│   │   │   ├── SettingsController.php     # Settings
│   │   │   └── UserController.php         # Users
│   │   └── Middleware/
│   │       ├── EnsureUserIsAdmin.php      # Admin middleware
│   │       └── HandleInertiaRequests.php  # Inertia
│   ├── Jobs/
│   │   └── ProcessExportJob.php           # Export job
│   ├── Mail/
│   │   └── ExportReadyMail.php            # Export email
│   └── Models/
│       ├── ExportRequest.php              # Export model
│       ├── Setting.php                    # Settings model
│       └── User.php                       # User model
├── database/
│   ├── migrations/                        # Migrations
│   └── seeders/                           # Seeds
├── resources/
│   ├── js/
│   │   ├── Components/                    # React components
│   │   │   ├── Drawer.jsx
│   │   │   ├── Pagination.jsx
│   │   │   ├── ProfileDrawer.jsx
│   │   │   ├── Toast.jsx
│   │   │   └── Form/
│   │   │       ├── Checkbox.jsx
│   │   │       ├── Input.jsx
│   │   │       ├── MaskedInput.jsx
│   │   │       └── Select.jsx
│   │   ├── Layouts/
│   │   │   ├── AppLayout.jsx              # Public layout
│   │   │   └── DashboardLayout.jsx        # Admin layout
│   │   └── Pages/
│   │       ├── Auth/
│   │       │   ├── ForgotPassword.jsx
│   │       │   ├── Login.jsx
│   │       │   └── ResetPassword.jsx
│   │       ├── Dashboard/Index.jsx
│   │       ├── Exports/Index.jsx
│   │       ├── Profile/Show.jsx
│   │       ├── Settings/Index.jsx
│   │       ├── Users/Index.jsx
│   │       └── Welcome.jsx
│   └── views/
│       ├── app.blade.php                  # Root template
│       └── emails/                        # Email templates
├── routes/
│   └── web.php                            # Web routes
├── scripts/
│   └── start-dev.sh                       # Development script
├── .env.example                           # Configuration example
├── composer.json                          # PHP dependencies
├── package.json                           # Node dependencies
└── vite.config.js                         # Vite configuration
```

## 🎨 Design Conventions

### Main Colors
- **Primary:** Blue (blue-600, blue-700)
- **Success:** Green (green-600)
- **Warning:** Amber (amber-600)
- **Error:** Red (red-600)
- **Background:** Gray (gray-50)

### UI Components
- **Cards:** Rounded (rounded-xl), soft shadow, border
- **Buttons:** Rounded (rounded-lg), colored shadow
- **Inputs:** 44px height, gray-50 background
- **Drawer:** 700px width, slide animation

### Layout Patterns
- **Sidebar:** Fixed, 256px width
- **Topbar:** Sticky, 64px height
- **Content:** 32px padding

## 🔧 Useful Commands

### Laravel
```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Create controller
php artisan make:controller NameController

# Create model with migration
php artisan make:model Name -m

# Process queue
php artisan queue:work
```

### NPM
```bash
# Install dependencies
npm install

# Dev server
npm run dev

# Production build
npm run build
```

## 🔄 Migração de Dados do ClinicaWeb (Sistema Legado)

O projeto inclui dois comandos Artisan para migrar dados do sistema legado ClinicaWeb. O processo é dividido em duas etapas: **extração** (gera arquivos JSON para revisão) e **importação** (persiste no banco de dados).

### Pré-requisitos

- Dump SQL do ClinicaWeb em `docs/clinicaweb/database/bkp_cw2_20251201.sql`
- Mapeamento de códigos de produtos em `docs/sanitization/mapeamento-codigos-legado-base.md`
- Produtos já importados no novo sistema (a migração **não** importa produtos)

### Etapa 1: Extração

Lê o dump SQL e gera arquivos JSON em `docs/migration/` para revisão manual antes da importação.

```bash
php artisan migration:extrair-legado
```

**Opções:**

| Opção | Padrão | Descrição |
|-------|--------|-----------|
| `--sql` | `docs/clinicaweb/database/bkp_cw2_20251201.sql` | Caminho do dump SQL |
| `--output` | `docs/migration` | Diretório de saída dos JSONs |

**Arquivos gerados:**

- `clinicas.json` — Clínicas extraídas
- `medicos.json` — Médicos com endereços (repeater)
- `users.json` — Usuários com roles mapeadas (admin/medico/secretaria)
- `pacientes.json` — Pacientes com telefones classificados (celular/fixo)
- `receitas.json` — Receitas com itens e códigos de produtos corrigidos
- `itemAquisicoesLegado.json` — Ligações CC → linha de receita (datas alinhadas a `dta_ult_aquisicao` da linha; ver observações abaixo)
- `resumo-extracao.json` — Resumo com contagens e estatísticas

**Transformações aplicadas automaticamente:**

- Roles do sistema antigo mapeadas para `admin`, `medico` ou `secretaria`
- Usuários com apenas role `ROLE_CALLCENTER` são excluídos
- Códigos de produtos corrigidos conforme mapeamento
- Datas no formato `dd/mm/yyyy` convertidas para `yyyy-mm-dd`
- Telefones classificados: números começando com `9` (após DDD) são identificados como celular
- Campos `fone1`/`fone2`/`fone3` mapeados para campo principal + repeater
- **Usuários médico:** o campo `nome` no JSON passa a seguir o **nome do cadastro de médico** (`nome_legado` em `medicos.json`) quando há um único `legado_medico_id`, para evitar grafias divergentes entre contas legado

### Etapa 2: Importação

Lê os JSONs gerados e importa no banco de dados na ordem correta de dependência.

```bash
php artisan migration:importar-legado
```

**Opções:**

| Opção | Padrão | Descrição |
|-------|--------|-----------|
| `--source` | `docs/migration` | Diretório com os JSONs |
| `--only` | *(todos)* | Importar apenas uma entidade: `clinicas`, `medicos`, `users`, `pacientes`, `receitas` |
| `--dry-run` | — | Simula a importação sem persistir dados |

**Ordem de importação:** Clínicas → Médicos → Usuários → Pacientes → Receitas

**Exemplos:**

```bash
# Simular importação completa (sem persistir)
php artisan migration:importar-legado --dry-run

# Importar apenas pacientes
php artisan migration:importar-legado --only=pacientes

# Importar tudo
php artisan migration:importar-legado
```

### Idempotência

O comando de importação é **idempotente** — pode ser executado múltiplas vezes com segurança:

- Mantém um arquivo `docs/migration/id-mapping.json` que mapeia IDs do sistema antigo para o novo
- Na re-execução, registros já importados são identificados e pulados
- Fallback por chaves naturais (CRM, CPF, email, nome) caso o mapeamento não exista

### Observações importantes

- **Acesso de usuários legados:** A maioria dos usuários antigos não possuía email. Foram criados com emails placeholder (`@legado.revskin.com.br`). Para acessar o sistema, precisam utilizar o fluxo de recuperação de senha com um email real
- **Itens de receita sem produto:** Itens cujo produto não existe no novo sistema são automaticamente ignorados (não causam erro)
- **Senhas:** As senhas originais são preservadas quando possível (bcrypt compatível)
- **Endereços de médicos:** Importados como repeater (campo dinâmico), incluindo endereço principal e endereço da clínica quando disponível
- **Telefones de pacientes:** Telefones extras são importados no repeater de telefones
- **Vários logins legado para o mesmo médico:** na importação, se já existir um `User` com `role = medico` e o mesmo `medico_id`, novos `legado_id` de médico são mapeados para esse utilizador (evita duas contas "Angela" no gerenciamento de usuários). O `id-mapping.json` guarda cada `legado_id` → o mesmo `users.id`
- **Reimportação limpa:** para corrigir dados já migrados, pode apagar receitas/pacientes/clínicas/utilizadores (respeitando FKs), remover ou regenerar `id-mapping.json` e rodar extração + importação de novo
- **Datas de aquisição na receita (coerente com o legado):** na extração, cada linha em `itemAquisicoesLegado.json` só é gerada quando existe **correspondência explícita** entre a data do registo de aquisição (CC) e `dta_ult_aquisicao` **dessa** linha de `receita_item` no dump — não há fallback para “primeira linha com o mesmo produto”. Vários candidatos com a mesma data → linha ignorada (ambíguo). Na app, o tooltip junta apenas `receita_item_aquisicoes` **da mesma linha** + `receita_itens.data_aquisicao`, **sem** cruzar outras receitas do paciente pelo mesmo produto. Após alterar a extração, é preciso **re-executar** `migration:extrair-legado` e reimportar (ou fluxo de limpeza + import) para limpar `receita_item_aquisicoes` antigos

## 🚀 Production Deployment

### 1. Server Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8.0+ or PostgreSQL
- Nginx or Apache
- Supervisor (for queues)

### 2. Manual Deploy

```bash
# Clone the repository
git clone <repository-url> /var/www/my-project
cd /var/www/my-project

# Install dependencies (no dev)
composer install --optimize-autoloader --no-dev
npm ci
npm run build

# Configure .env
cp .env.example .env
# Edit .env with your production settings

# Configure Laravel
php artisan key:generate --force
php artisan storage:link
php artisan migrate --force

# Production cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 3. Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/my-project/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 4. Supervisor Configuration (Queue)

```ini
[program:my-project-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/my-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/my-project/storage/logs/worker.log
```

### 5. Crontab (Scheduler)

```bash
* * * * * cd /var/www/my-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🔒 Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Unique and secure APP_KEY
- [ ] Strong database password
- [ ] SSL/HTTPS configured
- [ ] Firewall configured
- [ ] SESSION_SECURE_COOKIE=true
- [ ] Automatic backup configured

## 📖 How to Add New Modules

### 1. Create Model and Migration

```bash
php artisan make:model Customer -m
```

### 2. Create Controller

```bash
php artisan make:controller CustomerController
```

### 3. Add Routes

```php
// routes/web.php
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    // ...
});
```

### 4. Create React Page

```jsx
// resources/js/Pages/Customers/Index.jsx
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function CustomersIndex({ customers }) {
    return (
        <DashboardLayout>
            {/* Your content */}
        </DashboardLayout>
    );
}
```

### 5. Add to Menu

Edit `DashboardLayout.jsx` and add the item to the sidebar menu.

## 🐛 Troubleshooting

### Error: "Vite manifest not found"
```bash
npm run dev  # Keep running in another terminal
```

### Error: "Permission denied on storage"
```bash
chmod -R 775 storage bootstrap/cache
```

### Error: "Class not found"
```bash
composer dump-autoload
php artisan config:clear
```

### Emails not working
Check the email settings in `.env`. For development, use:
```env
MAIL_MAILER=log
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [React Documentation](https://react.dev)
- [Inertia.js Documentation](https://inertiajs.com)
- [Tailwind CSS Documentation](https://tailwindcss.com)

## 📝 License

MIT License

---

**Happy Coding! 🚀**
