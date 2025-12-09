# Обновление: Редирект на страницу входа и руссификация

## Что изменено:

1. **Редирект на страницу входа** - неавторизованные пользователи автоматически перенаправляются на страницу входа
2. **Русская локаль** - добавлена русская локаль для всех Filament панелей
3. **Настройка .env** - добавлена настройка APP_LOCALE=ru в скрипты установки

## Что нужно сделать на сервере:

### Вариант 1: Обновить код через Git (рекомендуется)

```bash
cd /var/www/relaticle

# Получите последние изменения
git pull origin main

# Очистите кеш
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Пересоздайте кеш
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Перезапустите PHP-FPM
systemctl restart php8.4-fpm
```

### Вариант 2: Обновить .env вручную

Если не хотите обновлять код через Git, можно просто обновить .env:

```bash
cd /var/www/relaticle

# Добавьте/обновите настройки локали
sed -i 's|APP_LOCALE=.*|APP_LOCALE=ru|' .env
sed -i 's|APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=ru|' .env

# Если строк нет, добавьте их
if ! grep -q "APP_LOCALE" .env; then
    echo "APP_LOCALE=ru" >> .env
fi
if ! grep -q "APP_FALLBACK_LOCALE" .env; then
    echo "APP_FALLBACK_LOCALE=ru" >> .env
fi

# Очистите кеш
php artisan config:clear
php artisan config:cache
```

## Важно: Настройка поддомена

Filament панель использует поддомен `app.crmvirtu.ru` для страницы входа. Если поддомен не настроен, редирект не будет работать.

### Настройка поддомена:

1. **В DNS** добавьте A-запись:
   - Имя: `app`
   - Тип: A
   - Значение: `83.220.175.224` (IP вашего сервера)

2. **В Nginx** добавьте конфигурацию для поддомена:

```bash
cat > /etc/nginx/sites-available/app.crmvirtu.ru << 'EOF'
server {
    listen 80;
    server_name app.crmvirtu.ru;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name app.crmvirtu.ru;

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

# Активируйте конфигурацию
ln -sf /etc/nginx/sites-available/app.crmvirtu.ru /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# Получите SSL сертификат для поддомена
certbot --nginx -d app.crmvirtu.ru --non-interactive --agree-tos --register-unsafely-without-email --redirect
```

### Альтернатива: Использовать путь вместо поддомена

Если не хотите настраивать поддомен, можно изменить конфигурацию Filament панели, чтобы использовать путь вместо поддомена. Но это требует изменения кода в `AppPanelProvider.php`.

## Проверка:

1. Откройте `https://crmvirtu.ru` - должны быть перенаправлены на страницу входа
2. Проверьте, что интерфейс на русском языке
3. Войдите с учетными данными:
   - Email: `admin@relaticle.com`
   - Пароль: `password`

## Готово!

После выполнения этих шагов:
- Неавторизованные пользователи будут автоматически перенаправляться на страницу входа
- Интерфейс будет на русском языке

