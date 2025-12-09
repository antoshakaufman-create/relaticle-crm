#!/bin/bash

# Скрипт развертывания для crmvirtu.ru (PHP 8.5)
# Использование: sudo ./deploy_crmvirtu.sh

set -e

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Проверка root
if [ "$EUID" -ne 0 ]; then
    error "Запустите от root: sudo ./deploy_crmvirtu.sh"
    exit 1
fi

DOMAIN="crmvirtu.ru"
APP_DIR="/var/www/relaticle"
PHP_VERSION="8.5"

info "Начинаем развертывание на $DOMAIN..."

# 1. Обновление PHP до 8.5
info "Обновление PHP до версии $PHP_VERSION..."
apt update
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php$PHP_VERSION php$PHP_VERSION-{fpm,cli,common,mysql,zip,gd,mbstring,curl,xml,bcmath,intl,sqlite3,redis}

# Проверка установки PHP
if ! command -v php$PHP_VERSION &> /dev/null; then
    error "PHP $PHP_VERSION не удалось установить. Проверьте поддержку в PPA."
    exit 1
fi

info "Версия PHP: $(php$PHP_VERSION -v | head -n 1)"

# 2. Обновление кода
info "Обновление кода..."

# WIPE FOR FRESH INSTALL (User Request - Clean State)
if [ -d "$APP_DIR" ]; then
    warn "УДАЛЕНИЕ ТЕКУЩЕЙ ПАПКИ (Fresh Install)..."
    rm -rf "$APP_DIR"
fi

info "Клонирование репозитория..."
git clone https://github.com/antoshakaufman-create/relaticle-crm.git "$APP_DIR"

if [ ! -d "$APP_DIR" ]; then
    error "Папка приложения не создана после клонирования!"
    exit 1
fi

cd "$APP_DIR"
info "Содержимое папки после клонирования:"
ls -la

# FIX: Handle nested structure (relaticle/relaticle)
if [ -d "relaticle" ] && [ -f "relaticle/composer.json" ]; then
    warn "Обнаружена вложенность (relaticle/relaticle). Переносим файлы в корень..."
    
    # Use rsync to merge directories and files safely
    if ! command -v rsync &> /dev/null; then
        apt install -y rsync
    fi
    
    # 1. Rename conflicting directory to temp name to avoid "./relaticle" vs "./relaticle/relaticle" collision
    mv relaticle relaticle_temp
    
    # 2. Sync content from temp to root
    rsync -a relaticle_temp/ ./
    
    # 3. Remove the temp subdir
    rm -rf relaticle_temp
    
    info "Структура исправлена."
    ls -la
fi

if [ ! -f "composer.json" ]; then
    error "composer.json не найден! Клонирование прошло некорректно или репозиторий пуст."
    exit 1
fi

info "Pull последних изменений (на всякий случай)..."
git pull origin main

# 3. Установка зависимостей
info "Установка зависимостей Composer..."
php$PHP_VERSION /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction

info "Установка зависимостей NPM и сборка..."
npm install
npm run build

# 4. Настройка прав
info "Настройка прав доступа..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 5. База данных и миграции
info "Миграция базы данных..."
# Предполагаем, что .env уже настроен, но проверим/обновим ключи если нужно
if [ ! -f .env ]; then
    warn ".env файл не найден! Копируем из .env.example..."
    cp .env.example .env
    php$PHP_VERSION artisan key:generate
    
    # Автоматическая настройка .env
    info "Настройка переменных окружения..."
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    sed -i "s|APP_LOCALE=.*|APP_LOCALE=ru|" .env
    
    # Настройка базы данных (SQLite для простоты)
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=$APP_DIR/database/database.sqlite|" .env
    
    # Создаем базу данных если её нет
    if [ ! -f "$APP_DIR/database/database.sqlite" ]; then
        touch "$APP_DIR/database/database.sqlite"
    fi
    chown www-data:www-data "$APP_DIR/database/database.sqlite"
    
    warn "ВАЖНО: Отредактируйте .env и укажите YANDEX ключи!"
fi

php$PHP_VERSION artisan migrate --force

php$PHP_VERSION artisan migrate --force

# Create/Update Admin User (Bypass Email Verification)
info "Создание админа/обход почты..."
php$PHP_VERSION create_admin.php
php$PHP_VERSION artisan config:cache
php$PHP_VERSION artisan route:cache
php$PHP_VERSION artisan view:cache
php$PHP_VERSION artisan filament:assets

# 7. Настройка Nginx
info "Обновление конфигурации Nginx..."
cat > /etc/nginx/sites-available/relaticle <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 8. Перезапуск сервисов
info "Перезапуск сервисов..."
systemctl restart php$PHP_VERSION-fpm

echo "----------------------------------------"
echo "[DEBUG] Checking Filament Resources:"
ls -la "$APP_DIR/app/Filament/Resources"
echo "----------------------------------------"

if command -v supervisorctl &> /dev/null; then
    supervisorctl restart all
fi

# 9. Настройка SSL (Certbot)
info "Настройка SSL сертификата..."
if ! command -v certbot &> /dev/null; then
    info "Установка Certbot..."
    apt install -y certbot python3-certbot-nginx
fi

# Запускаем certbot для настройки Nginx
# --redirect: делать редирект с HTTP на HTTPS
# --non-interactive: не задавать вопросов
certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN --redirect || warn "Не удалось автоматически настроить SSL via Certbot. Проверьте логи."

info "Развертывание завершено! Сайт должен быть доступен по адресу https://$DOMAIN"
warn "Не забудьте настроить Yandex API ключи в .env файле, если они еще не настроены."
