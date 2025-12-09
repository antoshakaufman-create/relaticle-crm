# Полная переустановка Relaticle CRM на сервере

**Сервер:** lizon0707.fvds.ru (83.220.175.224)  
**Домен:** crmvirtu.ru  
**PHP:** 8.4

Эта инструкция полностью удалит все и установит заново с правильной конфигурацией.

---

## Шаг 1: Подключитесь к серверу

```bash
ssh root@83.220.175.224
```

---

## Шаг 2: Полная очистка

```bash
# Остановите все сервисы
systemctl stop nginx
systemctl stop php8.4-fpm 2>/dev/null || true

# Удалите старое приложение
rm -rf /var/www/relaticle

# Удалите все конфигурации Nginx
rm -f /etc/nginx/sites-enabled/relaticle
rm -f /etc/nginx/sites-available/relaticle
rm -f /etc/nginx/sites-enabled/app.crmvirtu.ru 2>/dev/null || true
rm -f /etc/nginx/sites-available/app.crmvirtu.ru 2>/dev/null || true

# Удалите PHP 8.3 если установлен
apt remove --purge -y php8.3* 2>/dev/null || true
apt autoremove -y

# Запустите Nginx обратно
systemctl start nginx
```

---

## Шаг 3: Установка PHP 8.4 и необходимых пакетов

```bash
# Обновите систему
apt update && apt upgrade -y

# Добавьте PPA для PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update

# Установите PHP 8.4 и все необходимые пакеты
apt install -y nginx php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php8.4-redis git unzip certbot python3-certbot-nginx ufw nodejs npm

# Проверьте версию PHP
php8.4 -v
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

# Установите зависимости Composer
composer install --optimize-autoloader --no-dev --no-interaction

# Установите зависимости npm
npm install

# Соберите фронтенд-ассеты
npm run build

# Настройте права доступа
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache

# Создайте .env файл
cp .env.example .env
php8.4 artisan key:generate --force

# Настройте .env для SQLite и русской локали
sed -i 's|DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|" .env
sed -i 's|APP_ENV=.*|APP_ENV=production|' .env
sed -i 's|APP_DEBUG=.*|APP_DEBUG=false|' .env
sed -i 's|APP_URL=.*|APP_URL=https://crmvirtu.ru|' .env
sed -i 's|APP_LOCALE=.*|APP_LOCALE=ru|' .env
sed -i 's|APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=ru|' .env

# Создайте базу данных SQLite
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# Запустите миграции
php8.4 artisan migrate --seed --force

# Настройте storage
php8.4 artisan storage:link

# Публикуйте Filament ассеты
php8.4 artisan filament:assets

# Кешируйте конфигурацию
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
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

---

## Шаг 8: Проверка конфигурации после SSL

После установки SSL Certbot автоматически обновит конфигурацию Nginx. Проверьте:

```bash
# Проверьте конфигурацию
cat /etc/nginx/sites-available/relaticle

# Убедитесь, что используется php8.4-fpm.sock
grep "fastcgi_pass" /etc/nginx/sites-available/relaticle

# Должно быть: fastcgi_pass unix:/run/php/php8.4-fpm.sock;
```

Если Certbot не обновил конфигурацию автоматически, обновите вручную:

```bash
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

# Проверьте и перезагрузите
nginx -t
systemctl reload nginx
```

---

## Шаг 9: Проверка маршрутов Filament

```bash
cd /var/www/relaticle

# Проверьте зарегистрированные маршруты
php8.4 artisan route:list | grep "app/login"

# Должно быть:
# GET|HEAD  app/login ........................................................... filament.app.auth.login
# НЕ должно быть: app.crmvirtu.ru/login
```

Если маршруты все еще показывают `app.crmvirtu.ru`, проверьте конфигурацию:

```bash
# Проверьте конфигурацию панели
grep -nE "->path|->domain" app/Providers/Filament/AppPanelProvider.php

# Должно быть:
# 77:            ->path('app')
# НЕ должно быть: ->domain('app.crmvirtu.ru')

# Если там domain, обновите код
git pull origin main

# Очистите кеш
php8.4 artisan optimize:clear
php8.4 artisan optimize

# Проверьте снова
php8.4 artisan route:list | grep "app/login"
```

---

## Шаг 10: Финальная проверка

```bash
# Проверьте статус всех сервисов
systemctl status nginx
systemctl status php8.4-fpm

# Проверьте логи
tail -20 /var/www/relaticle/storage/logs/laravel.log
tail -20 /var/log/nginx/error.log

# Проверьте доступность сайта
curl -I https://crmvirtu.ru
```

---

## Шаг 11: Первый вход

1. Откройте в браузере: `https://crmvirtu.ru`
2. Должно произойти автоматическое перенаправление на `https://crmvirtu.ru/app/login`
3. Используйте учетные данные:
   - **Email:** `admin@relaticle.com`
   - **Пароль:** `password`
4. **ВАЖНО:** Сразу после входа смените пароль и email!

---

## Готово!

После выполнения всех шагов:
- ✅ Сайт полностью переустановлен
- ✅ Используется PHP 8.4
- ✅ Filament панель доступна по пути `/app` (не поддомен)
- ✅ Интерфейс на русском языке
- ✅ SSL сертификат установлен
- ✅ Редирект на страницу входа работает

---

## Если что-то пошло не так

### Проблема: Маршруты все еще показывают app.crmvirtu.ru

```bash
cd /var/www/relaticle
git pull origin main
php8.4 artisan optimize:clear
php8.4 artisan optimize
php8.4 artisan route:list | grep "app/login"
```

### Проблема: Ошибка 500

```bash
cd /var/www/relaticle
tail -50 storage/logs/laravel.log
```

### Проблема: Vite manifest not found

```bash
cd /var/www/relaticle
npm run build
chown -R www-data:www-data public/build
```

