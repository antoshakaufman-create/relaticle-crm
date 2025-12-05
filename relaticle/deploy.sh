#!/bin/bash

# Скрипт развертывания Relaticle CRM на FirstVDS
# Использование: ./deploy.sh

set -e

echo "=========================================="
echo "Развертывание Relaticle CRM"
echo "=========================================="
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Проверка, что скрипт запущен от root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Ошибка: Скрипт должен быть запущен от root${NC}"
    exit 1
fi

# Переменные
APP_DIR="/var/www/relaticle"
APP_USER="www-data"
DOMAIN="lizon0707.fvds.ru"
DB_TYPE="${DB_TYPE:-sqlite}"  # sqlite или pgsql

echo -e "${GREEN}Шаг 1: Обновление системы${NC}"
apt update && apt upgrade -y

echo -e "${GREEN}Шаг 2: Установка базовых пакетов${NC}"
apt install -y curl wget git unzip software-properties-common

echo -e "${GREEN}Шаг 3: Установка PHP 8.4${NC}"
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl \
    php8.4-sqlite3 php8.4-pgsql

echo -e "${GREEN}Шаг 4: Установка Composer${NC}"
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

echo -e "${GREEN}Шаг 5: Установка Node.js 20${NC}"
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

echo -e "${GREEN}Шаг 6: Установка Nginx${NC}"
apt install -y nginx

# Установка PostgreSQL (если выбрана)
if [ "$DB_TYPE" = "pgsql" ]; then
    echo -e "${GREEN}Шаг 7: Установка PostgreSQL${NC}"
    apt install -y postgresql postgresql-contrib
    systemctl start postgresql
    systemctl enable postgresql
fi

echo -e "${GREEN}Шаг 8: Создание директории приложения${NC}"
mkdir -p $APP_DIR
cd $APP_DIR

echo -e "${GREEN}Шаг 9: Клонирование репозитория${NC}"
# Если репозиторий уже склонирован, обновляем
if [ -d ".git" ]; then
    git pull
else
    # Здесь нужно указать URL вашего репозитория
    echo -e "${YELLOW}ВНИМАНИЕ: Нужно клонировать репозиторий вручную${NC}"
    echo "Выполните: git clone <ваш-репозиторий> $APP_DIR"
fi

echo -e "${GREEN}Шаг 10: Установка зависимостей${NC}"
composer install --no-dev --optimize-autoloader
npm ci
npm run build

echo -e "${GREEN}Шаг 11: Настройка прав доступа${NC}"
chown -R $APP_USER:$APP_USER $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

echo -e "${GREEN}Шаг 12: Настройка .env файла${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
    
    # Настройка YandexGPT
    if [ -n "$YANDEX_GPT_API_KEY" ] && [ -n "$YANDEX_FOLDER_ID" ]; then
        sed -i "s|AI_PROVIDER=.*|AI_PROVIDER=yandex|" .env
        sed -i "s|YANDEX_GPT_API_KEY=.*|YANDEX_GPT_API_KEY=$YANDEX_GPT_API_KEY|" .env
        sed -i "s|YANDEX_FOLDER_ID=.*|YANDEX_FOLDER_ID=$YANDEX_FOLDER_ID|" .env
        echo -e "${GREEN}YandexGPT ключи настроены${NC}"
    fi
    
    # Настройка базы данных
    if [ "$DB_TYPE" = "sqlite" ]; then
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=$APP_DIR/database/database.sqlite|" .env
    fi
    
    # Настройка приложения
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|APP_URL=.*|APP_URL=http://$DOMAIN|" .env
    sed -i "s|APP_LOCALE=.*|APP_LOCALE=ru|" .env
    
    # Настройка кеша и сессий
    sed -i "s|CACHE_STORE=.*|CACHE_STORE=database|" .env
    sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=database|" .env
    sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
fi

echo -e "${GREEN}Шаг 13: Настройка базы данных${NC}"
if [ "$DB_TYPE" = "sqlite" ]; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite
    chown $APP_USER:$APP_USER database/database.sqlite
    php artisan migrate --force
else
    echo -e "${YELLOW}Настройте PostgreSQL вручную и выполните: php artisan migrate --force${NC}"
fi

echo -e "${GREEN}Шаг 14: Создание симлинка storage${NC}"
php artisan storage:link

echo -e "${GREEN}Шаг 15: Очистка кеша${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${GREEN}Шаг 16: Настройка Nginx${NC}"
cat > /etc/nginx/sites-available/relaticle <<EOF
server {
    listen 80;
    server_name $DOMAIN;
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
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo -e "${GREEN}Шаг 17: Настройка PHP-FPM${NC}"
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.4/fpm/php.ini
systemctl restart php8.4-fpm

echo -e "${GREEN}Шаг 18: Настройка systemd для queue worker${NC}"
cat > /etc/systemd/system/relaticle-queue.service <<EOF
[Unit]
Description=Relaticle Queue Worker
After=network.target

[Service]
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable relaticle-queue
systemctl start relaticle-queue

echo -e "${GREEN}Шаг 19: Настройка cron${NC}"
(crontab -l 2>/dev/null; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo -e "${GREEN}Шаг 20: Создание системного администратора${NC}"
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    ADMIN_NAME="${ADMIN_NAME:-Администратор}"
    cd $APP_DIR
    php artisan sysadmin:create \
        --name="$ADMIN_NAME" \
        --email="$ADMIN_EMAIL" \
        --password="$ADMIN_PASSWORD" \
        --no-interaction
    echo -e "${GREEN}Администратор создан: $ADMIN_EMAIL${NC}"
else
    echo -e "${YELLOW}Администратор не создан. Выполните вручную:${NC}"
    echo "php artisan sysadmin:create --name=\"Администратор\" --email=\"YOUR_ADMIN_EMAIL\" --password=\"YOUR_ADMIN_PASSWORD\" --no-interaction"
fi

echo ""
echo -e "${GREEN}=========================================="
echo "Развертывание завершено!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Следующие шаги:${NC}"
echo "1. Проверьте .env файл с вашими данными"
if [ -z "$ADMIN_EMAIL" ] || [ -z "$ADMIN_PASSWORD" ]; then
    echo "2. Создайте администратора:"
    echo "   php artisan sysadmin:create --name=\"Администратор\" --email=\"YOUR_ADMIN_EMAIL\" --password=\"YOUR_ADMIN_PASSWORD\" --no-interaction"
fi
echo "3. Настройте SSL сертификат (Let's Encrypt):"
echo "   certbot --nginx -d $DOMAIN"
echo ""
echo -e "${GREEN}Приложение доступно по адресу: http://$DOMAIN${NC}"



