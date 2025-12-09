# Полная переустановка Relaticle CRM на сервере

**Сервер:** lizon0707.fvds.ru (83.220.175.224)  
**Домен:** crmvirtu.ru  
**PHP:** 8.4 (требуется для проекта)

Эта инструкция полностью очистит и переустановит приложение с нуля.

---

## Шаг 1: Подключитесь к серверу

```bash
ssh root@83.220.175.224
```

---

## Шаг 2: Очистка старой установки

```bash
# Остановите Nginx
systemctl stop nginx

# Удалите старое приложение
rm -rf /var/www/relaticle

# Удалите старую конфигурацию Nginx
rm -f /etc/nginx/sites-enabled/relaticle
rm -f /etc/nginx/sites-available/relaticle

# Запустите Nginx обратно
systemctl start nginx
```

---

## Шаг 3: Установка необходимых пакетов

```bash
# Обновите систему
apt update && apt upgrade -y

# Добавьте PPA для PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update

# Установите PHP 8.4 и необходимые пакеты
apt install -y nginx php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php-redis git unzip certbot python3-certbot-nginx ufw
```

---

## Шаг 4: Настройка фаервола

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

---

## Шаг 5: Установка приложения

```bash
# Перейдите в /var/www
cd /var/www

# Клонируйте репозиторий
git clone https://github.com/Relaticle/relaticle.git
cd relaticle

# Установите зависимости
composer install --optimize-autoloader --no-dev --no-interaction

# Настройте права доступа
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache

# Создайте .env файл
cp .env.example .env
php artisan key:generate --force

# Настройте для SQLite (проще чем MySQL)
sed -i 's|DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|" .env
sed -i 's|APP_ENV=.*|APP_ENV=production|' .env
sed -i 's|APP_DEBUG=.*|APP_DEBUG=false|' .env
sed -i 's|APP_URL=.*|APP_URL=https://crmvirtu.ru|' .env

# Создайте базу данных SQLite
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# Запустите миграции
php artisan migrate --seed --force

# Настройте storage
php artisan storage:link

# Публикуйте Filament ассеты
php artisan filament:assets

# Кешируйте конфигурацию
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Шаг 6: Настройка Nginx (HTTP)

```bash
# Создайте конфигурацию Nginx
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

# Активируйте сайт
ln -sf /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Проверьте конфигурацию
nginx -t

# Перезагрузите Nginx
systemctl reload nginx
```

---

## Шаг 7: Получение SSL сертификата

```bash
# Получите SSL сертификат
certbot --nginx -d crmvirtu.ru \
  --non-interactive \
  --agree-tos \
  --register-unsafely-without-email \
  --redirect
```

**Примечание:** Если certbot не может автоматически настроить Nginx, выполните:

```bash
# Получите сертификат без автоматической настройки
certbot certonly --nginx -d crmvirtu.ru \
  --non-interactive \
  --agree-tos \
  --register-unsafely-without-email

# Затем обновите конфигурацию Nginx вручную (см. Шаг 8)
```

---

## Шаг 8: Обновление конфигурации Nginx для HTTPS

```bash
# Обновите конфигурацию для HTTPS
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

# Проверьте конфигурацию
nginx -t

# Перезагрузите Nginx
systemctl reload nginx
```

---

## Шаг 9: Финальная проверка

```bash
# Проверьте статус всех сервисов
systemctl status nginx
systemctl status php8.4-fpm

# Проверьте логи Nginx
tail -20 /var/log/nginx/error.log

# Проверьте логи приложения
tail -20 /var/www/relaticle/storage/logs/laravel.log

# Проверьте доступность сайта
curl -I https://crmvirtu.ru
```

---

## Шаг 10: Первый вход

1. Откройте в браузере: `https://crmvirtu.ru`
2. Используйте учетные данные:
   - **Email:** `admin@relaticle.com`
   - **Пароль:** `password`
3. **ВАЖНО:** Сразу после входа смените пароль и email!

---

## Решение проблем

### Проблема: PHP 8.4 не найден

```bash
# Убедитесь, что PPA добавлен
add-apt-repository ppa:ondrej/php -y
apt update

# Проверьте доступные версии PHP
apt-cache search php8.4

# Установите PHP 8.4
apt install -y php8.4 php8.4-fpm php8.4-cli
```

### Проблема: Nginx не запускается

```bash
# Проверьте конфигурацию
nginx -t

# Посмотрите логи
journalctl -xeu nginx.service
```

### Проблема: SSL сертификат не работает

```bash
# Проверьте наличие сертификата
ls -la /etc/letsencrypt/live/crmvirtu.ru/

# Проверьте права доступа
ls -la /etc/letsencrypt/live/crmvirtu.ru/
```

### Проблема: Сайт показывает ошибку 500

```bash
# Проверьте права доступа
ls -la /var/www/relaticle/storage
ls -la /var/www/relaticle/bootstrap/cache

# Исправьте права
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

# Проверьте логи
tail -50 /var/www/relaticle/storage/logs/laravel.log
```

---

## Готово!

После выполнения всех шагов ваша CRM будет полностью переустановлена и доступна по адресу `https://crmvirtu.ru` с PHP 8.4.

