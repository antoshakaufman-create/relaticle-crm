# Инструкция: Что делать дальше на сервере

**Статус:** Все изменения запушены в репозиторий  
**Сервер:** lizon0707.fvds.ru (83.220.175.224)  
**Домен:** crmvirtu.ru

---

## Вариант 1: Полная переустановка (рекомендуется)

Если на сервере сейчас "каша" и вы хотите начать с чистого листа:

### Шаг 1: Подключитесь к серверу

```bash
ssh root@83.220.175.224
```

### Шаг 2: Следуйте инструкции из CLEAN_INSTALL.md

Откройте файл `CLEAN_INSTALL.md` в репозитории и выполните все шаги по порядку.

**Краткая версия:**

```bash
# 1. Очистка
systemctl stop nginx
rm -rf /var/www/relaticle
rm -f /etc/nginx/sites-enabled/relaticle /etc/nginx/sites-available/relaticle
systemctl start nginx

# 2. Установка пакетов
apt update && apt upgrade -y
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y nginx php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php-redis git unzip certbot python3-certbot-nginx ufw

# 3. Фаервол
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# 4. Установка приложения
cd /var/www
git clone https://github.com/Relaticle/relaticle.git
cd relaticle
composer install --optimize-autoloader --no-dev --no-interaction
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache
cp .env.example .env
php artisan key:generate --force
sed -i 's|DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|" .env
sed -i 's|APP_URL=.*|APP_URL=https://crmvirtu.ru|' .env
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite
php artisan migrate --seed --force
php artisan storage:link
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Nginx (HTTP)
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
nginx -t
systemctl reload nginx

# 6. SSL
certbot --nginx -d crmvirtu.ru --non-interactive --agree-tos --register-unsafely-without-email --redirect

# 7. Если certbot не настроил автоматически, обновите конфигурацию вручную
cat > /etc/nginx/sites-available/relaticle << 'EOF'
server {
    listen 80;
    server_name crmvirtu.ru;
    return 301 https://$server_name$request_uri;
}
server {
    listen 443 ssl http2;
    server_name crmvirtu.ru;
    ssl_certificate /etc/letsencrypt/live/crmvirtu.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/crmvirtu.ru/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
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
nginx -t
systemctl reload nginx
```

---

## Вариант 2: Завершение текущей установки

Если приложение уже частично установлено, но нужно завершить настройку:

### Следуйте инструкции из SERVER_SETUP_STEPS.md

Откройте файл `SERVER_SETUP_STEPS.md` и выполните шаги, начиная с проверки текущего состояния.

**Основные шаги:**

1. Проверьте состояние приложения
2. Завершите установку (Шаг 2.5)
3. Обновите конфигурацию Nginx для HTTPS
4. Обновите APP_URL в .env

---

## Вариант 3: Использование автоматического скрипта

Если хотите использовать автоматический скрипт установки:

### Шаг 1: Загрузите скрипт на сервер

На вашем локальном Mac:

```bash
cd ~/Desktop/relaticle-crm
scp deploy_server.sh root@83.220.175.224:/root/
```

### Шаг 2: Запустите скрипт на сервере

```bash
ssh root@83.220.175.224
cd /root
chmod +x deploy_server.sh
./deploy_server.sh
```

Скрипт запросит:
- Домен: `crmvirtu.ru`
- Использовать SQLite? (y/n) - **рекомендуется выбрать `y`**
- Установить SSL? (y/n) - **выберите `y`**
- Установить русскую локаль? (y/n) - по желанию

---

## Важные замечания

### PHP 8.4

Все инструкции обновлены для использования **PHP 8.4** (требование проекта). Если PHP 8.4 не установлен, скрипт автоматически добавит PPA и установит его.

### SQLite vs MySQL

**Рекомендуется использовать SQLite** для простоты:
- Не требует настройки MySQL
- Не требует создания базы данных
- Работает "из коробки"

MySQL можно настроить позже при необходимости.

### SSL сертификат

SSL сертификат для `crmvirtu.ru` уже получен. Нужно только:
1. Обновить конфигурацию Nginx для использования сертификата
2. Настроить редирект с HTTP на HTTPS

---

## Проверка после установки

После завершения установки проверьте:

```bash
# Статус сервисов
systemctl status nginx
systemctl status php8.4-fpm

# Доступность сайта
curl -I https://crmvirtu.ru

# Логи (если есть проблемы)
tail -50 /var/www/relaticle/storage/logs/laravel.log
tail -50 /var/log/nginx/error.log
```

---

## Первый вход

1. Откройте `https://crmvirtu.ru` в браузере
2. Используйте учетные данные:
   - **Email:** `admin@relaticle.com`
   - **Пароль:** `password`
3. **ВАЖНО:** Сразу после входа смените пароль и email!

---

## Файлы с инструкциями

- **`CLEAN_INSTALL.md`** - полная переустановка с нуля
- **`SERVER_SETUP_STEPS.md`** - завершение текущей установки
- **`deploy_server.sh`** - автоматический скрипт установки
- **`docs/deployment/server.md`** - детальная инструкция по деплою на сервер

---

## Готово!

После выполнения одного из вариантов выше, ваша CRM будет доступна по адресу `https://crmvirtu.ru`.

