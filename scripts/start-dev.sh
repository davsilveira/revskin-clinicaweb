#!/bin/bash

# Script para iniciar todos os serviços de desenvolvimento
# Garante que o Node.js correto está sendo usado
# Usa PHP 8.4 quando disponível via Homebrew

set -e

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Preferir PHP 8.4 quando instalado (Homebrew)
if [ -d "/opt/homebrew/opt/php@8.4/bin" ]; then
    export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
elif [ -d "/usr/local/opt/php@8.4/bin" ]; then
    export PATH="/usr/local/opt/php@8.4/bin:$PATH"
fi

echo -e "${BLUE}🚀 Iniciando Laravel Boilerplate...${NC}"
echo ""
echo -e "${GREEN}✅ PHP $(php -r 'echo PHP_VERSION;')${NC}"
echo ""

# Carregar NVM se disponível
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Usar Node.js LTS Iron (v20.x)
if command -v nvm &> /dev/null; then
    echo -e "${YELLOW}📦 Configurando Node.js...${NC}"
    nvm use lts/iron 2>/dev/null || nvm use 20 2>/dev/null || true
    echo -e "${GREEN}✅ Node.js $(node --version)${NC}"
    echo ""
fi

# Verificar versão do Node.js
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 20 ]; then
    echo -e "${YELLOW}⚠️  Node.js versão 20+ é necessária. Versão atual: $(node --version)${NC}"
    echo "Execute: nvm use lts/iron"
    exit 1
fi

# Verificar se as portas estão livres
if lsof -Pi :9090 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo -e "${YELLOW}⚠️  Porta 9090 já está em uso${NC}"
    read -p "Deseja parar o processo existente? (s/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        pkill -f "artisan serve" || true
        sleep 1
    fi
fi

if lsof -Pi :5173 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo -e "${YELLOW}⚠️  Porta 5173 já está em uso${NC}"
    read -p "Deseja parar o processo existente? (s/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        pkill -f "vite" || true
        sleep 1
    fi
fi

echo -e "${BLUE}📡 Iniciando serviços...${NC}"
echo ""
echo -e "${GREEN}✅ Servidor Laravel: http://localhost:9090${NC}"
echo -e "${GREEN}✅ Vite Dev Server: http://localhost:5173${NC}"
echo -e "${GREEN}✅ Queue Worker: Processando jobs${NC}"
echo -e "${GREEN}✅ Log Viewer: Laravel Pail${NC}"
echo -e "${GREEN}✅ Mailpit: http://localhost:8025${NC}"
echo ""
echo -e "${YELLOW}Pressione Ctrl+C para parar todos os serviços${NC}"
echo ""

# Iniciar todos os serviços com concurrently
npx concurrently \
    -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#34d399" \
    --names "server,queue,logs,vite,mail" \
    --kill-others \
    "php artisan serve --port=9090" \
    "php artisan queue:work --queue=default,exports --tries=3 --timeout=300 --max-jobs=1000" \
    "php artisan pail --timeout=0" \
    "LARAVEL_BYPASS_ENV_CHECK=1 npm run dev" \
    "mailpit"

