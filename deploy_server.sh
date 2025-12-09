#!/bin/bash

# Автоматический скрипт установки Relaticle CRM на Ubuntu 24.04
# Использование: ./deploy_server.sh

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
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
    error "Пожалуйста, запустите скрипт от root: sudo ./deploy_server.sh"
    exit 1
fi

info "Начинаем установку Relaticle CRM..."

# Запрашиваем параметры
read -p "Введите домен (например: lizon0707.fvds.ru): " DOMAIN
read -p "Использовать SQLite вместо MySQL? (y/n, рекомендуется y для простоты): " USE_SQLITE
if [ "$USE_SQLITE" != "y" ] && [ "$USE_SQLITE" != "Y" ]; then
    read -p "Введите пароль для базы данных MySQL: " DB_PASSWORD
    read -sp "Введите пароль root MySQL (Enter если пустой): " MYSQL_ROOT_PASSWORD
    echo
    USE_SQLITE="no"
else
    USE_SQLITE="yes"
    DB_PASSWORD=""
    MYSQL_ROOT_PASSWORD=""
fi

# Установка переменных
APP_DIR="/var/www/relaticle"
DB_NAME="relaticle"
DB_USER="relaticle"

info "Обновляем систему..."
apt update && apt upgrade -y

info "Добавляем PPA для PHP 8.4..."
add-apt-repository ppa:ondrej/php -y
apt update

info "Устанавливаем необходимые пакеты..."
if [ "$USE_SQLITE" != "yes" ]; then
    apt install -y nginx mysql-server php8.4 php8.4-{fpm,cli,mysql,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php-redis git unzip certbot python3-certbot-nginx ufw
else
    apt install -y nginx php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php-redis git unzip certbot python3-certbot-nginx ufw
fi

info "Настраиваем фаервол..."
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

if [ "$USE_SQLITE" != "yes" ]; then
    info "Настраиваем MySQL..."
    # Инициализируем MySQL если нужно
    if [ ! -d /var/lib/mysql/mysql ]; then
        info "Инициализируем MySQL..."
        mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql 2>&1 || true
    fi
    
    # Проверяем и исправляем права доступа
    chown -R mysql:mysql /var/lib/mysql 2>/dev/null || true
    chmod 755 /var/lib/mysql 2>/dev/null || true
    
    # Запускаем MySQL
    systemctl start mysql || {
        warn "MySQL не запустился автоматически, пытаемся исправить..."
        # Дополнительные попытки исправления
        rm -f /var/lib/mysql/ib_logfile* 2>/dev/null || true
        systemctl start mysql || {
            error "MySQL не удалось запустить. Попробуйте использовать SQLite (перезапустите скрипт и выберите 'y')"
            error "Или проверьте логи: journalctl -xeu mysql.service"
            exit 1
        }
    }
    
    # Ждем запуска MySQL
    sleep 5
    
    # Проверяем статус
    if ! systemctl is-active --quiet mysql; then
        error "MySQL не удалось запустить. Проверьте логи: journalctl -xeu mysql.service"
        exit 1
    fi
    
    systemctl enable mysql
    
    # Создаём базу данных и пользователя
    # На Ubuntu 24.04 после инициализации root может не иметь пароля
    mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
else
    info "Используем SQLite (не требуется настройка базы данных)..."
    DB_CONNECTION="sqlite"
fi

info "Клонируем Relaticle..."
if [ -d "$APP_DIR" ]; then
    warn "Директория $APP_DIR уже существует. Удаляем..."
    rm -rf "$APP_DIR"
fi

cd /var/www
git clone https://github.com/Relaticle/relaticle.git
cd relaticle

info "Устанавливаем зависимости Composer..."
composer install --optimize-autoloader --no-dev --no-interaction

info "Настраиваем права доступа..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 storage bootstrap/cache

info "Создаём .env файл..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Настраиваем .env
sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env

if [ "$USE_SQLITE" = "yes" ]; then
    # Настройка для SQLite
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=${APP_DIR}/database/database.sqlite|" .env
    # Создаем файл базы данных SQLite
    touch "${APP_DIR}/database/database.sqlite"
    chown www-data:www-data "${APP_DIR}/database/database.sqlite"
    chmod 664 "${APP_DIR}/database/database.sqlite"
else
    # Настройка для MySQL
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
fi

sed -i "s|REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" .env
sed -i "s|REDIS_PASSWORD=.*|REDIS_PASSWORD=null|" .env
sed -i "s|REDIS_PORT=.*|REDIS_PORT=6379|" .env

info "Генерируем APP_KEY..."
php artisan key:generate --force

info "Запускаем миграции..."
php artisan migrate --seed --force

info "Создаём символическую ссылку storage..."
php artisan storage:link

info "Публикуем Filament ассеты..."
php artisan filament:assets

info "Кешируем конфигурацию..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Создаём конфигурацию Nginx..."
cat > /etc/nginx/sites-available/relaticle <<EOF
server {
    listen 80;
    server_name ${DOMAIN} crm.${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 50M;
}
EOF

info "Активируем сайт Nginx..."
ln -sf /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

info "Проверяем конфигурацию Nginx..."
nginx -t

info "Перезагружаем Nginx..."
systemctl reload nginx

info "Настраиваем SSL сертификат..."
read -p "Установить SSL сертификат через Let's Encrypt? (y/n): " INSTALL_SSL
if [ "$INSTALL_SSL" = "y" ] || [ "$INSTALL_SSL" = "Y" ]; then
    # Запрашиваем email для Let's Encrypt (опционально)
    read -p "Введите email для Let's Encrypt (Enter для пропуска): " SSL_EMAIL
    if [ -z "$SSL_EMAIL" ]; then
        certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos --register-unsafely-without-email --redirect || {
            warn "Автоматическая установка SSL не удалась. Сертификат получен, но нужно настроить вручную."
            warn "Выполните: certbot install --cert-name ${DOMAIN}"
            warn "Или обновите конфигурацию Nginx вручную (см. SERVER_SETUP_STEPS.md)"
        }
    else
        certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos --email "$SSL_EMAIL" --redirect || {
            warn "Автоматическая установка SSL не удалась. Сертификат получен, но нужно настроить вручную."
            warn "Выполните: certbot install --cert-name ${DOMAIN}"
            warn "Или обновите конфигурацию Nginx вручную (см. SERVER_SETUP_STEPS.md)"
        }
    fi
    info "SSL сертификат установлен!"
else
    warn "SSL сертификат не установлен. Вы можете установить его позже командой: certbot --nginx -d ${DOMAIN}"
fi

info "Настраиваем русскую локаль..."
read -p "Установить русскую локаль? (y/n): " INSTALL_RU
if [ "$INSTALL_RU" = "y" ] || [ "$INSTALL_RU" = "Y" ]; then
    cd "$APP_DIR"
    if [ ! -d "lang/ru" ]; then
        info "Скачиваем русский языковой пакет..."
        wget -q https://files.catbox.moe/9y8czr.zip -O /tmp/ru_package.zip
        unzip -q /tmp/ru_package.zip -d lang/
        rm /tmp/ru_package.zip
    fi
    
    sed -i 's/APP_LOCALE=en/APP_LOCALE=ru/' .env
    sed -i 's/APP_FALLBACK_LOCALE=en/APP_FALLBACK_LOCALE=ru/' .env
    
    php artisan translations:cache
    php artisan config:clear
    info "Русская локаль установлена!"
fi

info "Проверяем статус сервисов..."
systemctl status nginx --no-pager -l
systemctl status php8.4-fpm --no-pager -l
systemctl status mysql --no-pager -l
systemctl status redis-server --no-pager -l

echo ""
info "=========================================="
info "Установка завершена успешно!"
info "=========================================="
echo ""
info "Доступ к CRM:"
if [ "$INSTALL_SSL" = "y" ] || [ "$INSTALL_SSL" = "Y" ]; then
    info "  https://${DOMAIN}"
else
    info "  http://${DOMAIN}"
fi
echo ""
info "Учётные данные для входа:"
info "  Email: admin@relaticle.com"
info "  Пароль: password"
echo ""
warn "⚠️  ВАЖНО: Сразу после первого входа смените пароль и email!"
echo ""
info "Полезные команды:"
info "  Логи: tail -f ${APP_DIR}/storage/logs/laravel.log"
info "  Очистка кеша: cd ${APP_DIR} && php artisan config:clear"
info "  Обновление: cd ${APP_DIR} && git pull && composer install --no-dev"
echo ""

