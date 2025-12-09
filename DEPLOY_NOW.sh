#!/bin/bash

# Полная переустановка Relaticle CRM на сервере
# Использование: ./DEPLOY_NOW.sh

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка, что скрипт запущен от root
if [ "$EUID" -ne 0 ]; then 
    error "Пожалуйста, запустите скрипт от root: sudo ./DEPLOY_NOW.sh"
    exit 1
fi

info "Начинаем полную переустановку Relaticle CRM..."

# ============================================
# ШАГ 1: Полная очистка
# ============================================
info "Очищаем старую установку..."

systemctl stop nginx
systemctl stop php8.4-fpm 2>/dev/null || true

rm -rf /var/www/relaticle
rm -f /etc/nginx/sites-enabled/relaticle
rm -f /etc/nginx/sites-available/relaticle
rm -f /etc/nginx/sites-enabled/app.crmvirtu.ru 2>/dev/null || true
rm -f /etc/nginx/sites-available/app.crmvirtu.ru 2>/dev/null || true

# Удаляем PHP 8.3 если установлен
if dpkg -l | grep -q php8.3; then
    info "Удаляем PHP 8.3..."
    apt remove --purge -y php8.3* 2>/dev/null || true
    apt autoremove -y
fi

systemctl start nginx

# ============================================
# ШАГ 2: Установка PHP 8.4 и пакетов
# ============================================
info "Устанавливаем PHP 8.4 и необходимые пакеты..."

apt update && apt upgrade -y

if ! grep -q "ondrej/php" /etc/apt/sources.list.d/* 2>/dev/null; then
    info "Добавляем PPA для PHP 8.4..."
    add-apt-repository ppa:ondrej/php -y
    apt update
fi

info "Устанавливаем пакеты..."
apt install -y nginx php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php8.4-redis git unzip certbot python3-certbot-nginx ufw nodejs npm

# Проверяем версию PHP
PHP_VERSION=$(php8.4 -v | head -1)
info "Установлен: $PHP_VERSION"

# ============================================
# ШАГ 3: Фаервол
# ============================================
info "Настраиваем фаервол..."
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# ============================================
# ШАГ 4: Установка приложения
# ============================================
info "Устанавливаем приложение..."

cd /var/www
git clone https://github.com/Relaticle/relaticle.git
cd relaticle

info "Устанавливаем зависимости Composer..."
composer install --optimize-autoloader --no-dev --no-interaction

info "Устанавливаем зависимости npm..."
npm install

info "Собираем фронтенд-ассеты..."
npm run build

info "Настраиваем права доступа..."
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache

info "Создаем .env файл..."
cp .env.example .env
php8.4 artisan key:generate --force

info "Настраиваем .env..."
sed -i 's|DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|" .env
sed -i 's|APP_ENV=.*|APP_ENV=production|' .env
sed -i 's|APP_DEBUG=.*|APP_DEBUG=false|' .env
sed -i 's|APP_URL=.*|APP_URL=https://crmvirtu.ru|' .env
sed -i 's|APP_LOCALE=.*|APP_LOCALE=ru|' .env
sed -i 's|APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=ru|' .env

info "Создаем базу данных SQLite..."
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

info "Запускаем миграции..."
php8.4 artisan migrate --seed --force

info "Настраиваем storage..."
php8.4 artisan storage:link

info "Публикуем Filament ассеты..."
php8.4 artisan filament:assets

info "Кешируем конфигурацию..."
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# ============================================
# ШАГ 5: Настройка Nginx (HTTP)
# ============================================
info "Настраиваем Nginx..."

cat > /etc/nginx/sites-available/relaticle << 'EOF'
server {
    listen 80;
    server_name crmvirtu.ru;

    root /var/www/relaticle/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 50M;
}
EOF

ln -sf /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

info "Проверяем конфигурацию Nginx..."
if nginx -t; then
    systemctl reload nginx
    info "Nginx перезагружен"
else
    error "Ошибка в конфигурации Nginx!"
    exit 1
fi

# ============================================
# ШАГ 6: SSL сертификат
# ============================================
info "Устанавливаем SSL сертификат..."
certbot --nginx -d crmvirtu.ru \
  --non-interactive \
  --agree-tos \
  --register-unsafely-without-email \
  --redirect || {
    warn "Автоматическая установка SSL не удалась. Выполните вручную: certbot --nginx -d crmvirtu.ru"
}

# ============================================
# ШАГ 7: Проверка маршрутов
# ============================================
info "Проверяем маршруты Filament..."
cd /var/www/relaticle

# Очищаем кеш маршрутов
php8.4 artisan route:clear
php8.4 artisan route:cache

ROUTES=$(php8.4 artisan route:list | grep "app/login" || true)
if echo "$ROUTES" | grep -q "app/login"; then
    info "✓ Маршруты Filament зарегистрированы правильно"
    echo "$ROUTES" | head -1
else
    warn "Маршруты не найдены. Проверяем конфигурацию..."
    grep -nE "->path|->domain" app/Providers/Filament/AppPanelProvider.php
fi

# ============================================
# ШАГ 8: Финальная проверка
# ============================================
info "Выполняем финальную проверку..."

systemctl start php8.4-fpm
systemctl enable php8.4-fpm

info "Проверяем статус сервисов..."
systemctl status php8.4-fpm --no-pager -l | head -10
systemctl status nginx --no-pager -l | head -10

info "Проверяем конфигурацию Nginx..."
grep "fastcgi_pass" /etc/nginx/sites-available/relaticle

info ""
info "============================================"
info "Установка завершена!"
info "============================================"
info ""
info "Сайт доступен по адресу: https://crmvirtu.ru"
info "Страница входа: https://crmvirtu.ru/app/login"
info ""
info "Учетные данные:"
info "  Email: admin@relaticle.com"
info "  Пароль: password"
info ""
info "ВАЖНО: Сразу после входа смените пароль!"
info ""

