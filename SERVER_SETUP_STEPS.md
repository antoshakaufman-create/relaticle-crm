# Пошаговая инструкция для завершения установки на сервере

**Сервер:** lizon0707.fvds.ru (83.220.175.224)  
**Домен:** crmvirtu.ru  
**Статус:** SSL сертификат получен, домен настроен

---

## Шаг 1: Подключитесь к серверу

На вашем локальном Mac выполните:

```bash
ssh root@83.220.175.224
# или
ssh root@lizon0707.fvds.ru
```

Введите пароль при запросе.

---

## Шаг 2: Проверьте текущее состояние

```bash
# Проверьте статус Nginx
systemctl status nginx

# Проверьте конфигурацию Nginx
cat /etc/nginx/sites-available/relaticle

# Проверьте, существует ли приложение
ls -la /var/www/relaticle
```

---

## Шаг 2.5: Завершите установку приложения (если нужно)

Если приложение не установлено или установка не завершена, выполните:

```bash
# Перейдите в директорию приложения
cd /var/www/relaticle

# Проверьте, что директория существует и содержит файлы
ls -la

# Если директория пустая или не существует, клонируйте репозиторий
if [ ! -f "artisan" ]; then
    echo "Приложение не установлено. Устанавливаем..."
    
    # Если директория существует но пустая, удалите её
    if [ -d "/var/www/relaticle" ] && [ -z "$(ls -A /var/www/relaticle)" ]; then
        rm -rf /var/www/relaticle
    fi
    
    # Клонируйте репозиторий
    cd /var/www
    git clone https://github.com/Relaticle/relaticle.git
    cd relaticle
fi

# Установите зависимости Composer
composer install --optimize-autoloader --no-dev --no-interaction

# Настройте права доступа
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache

# Создайте .env файл если его нет
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# Настройте .env для SQLite (проще чем MySQL)
sed -i 's|DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|" .env

# Создайте файл базы данных SQLite
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# Запустите миграции
php artisan migrate --seed --force

# Создайте символическую ссылку storage
php artisan storage:link

# Публикуйте Filament ассеты
php artisan filament:assets

# Кешируйте конфигурацию
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Шаг 3: Обновите конфигурацию Nginx для HTTPS

**Примечание:** SSL сертификат для `crmvirtu.ru` уже получен. Теперь нужно настроить Nginx для использования этого сертификата.

```bash
# Создайте резервную копию
cp /etc/nginx/sites-available/relaticle /etc/nginx/sites-available/relaticle.backup

# Обновите конфигурацию
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
```

---

## Шаг 4: Проверьте и перезагрузите Nginx

```bash
# Проверьте конфигурацию на ошибки
nginx -t

# Если проверка прошла успешно, перезагрузите Nginx
systemctl reload nginx

# Проверьте статус
systemctl status nginx
```

---

## Шаг 5: Обновите APP_URL в .env

```bash
cd /var/www/relaticle

# Проверьте, что .env файл существует
if [ ! -f .env ]; then
    echo "ОШИБКА: .env файл не найден! Выполните Шаг 2.5 для завершения установки."
    exit 1
fi

# Обновите APP_URL на правильный домен
sed -i 's|APP_URL=.*|APP_URL=https://crmvirtu.ru|' .env

# Очистите кеш
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Пересоздайте кеш
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Шаг 6: Проверьте доступность сайта

На вашем локальном Mac откройте в браузере:

- `https://crmvirtu.ru`

Сайт должен работать с SSL сертификатом.

---

## Шаг 7: Первый вход в систему

1. Откройте `https://crmvirtu.ru` в браузере
2. Используйте учетные данные:
   - **Email:** `admin@relaticle.com`
   - **Пароль:** `password`
3. **ВАЖНО:** Сразу после входа смените пароль и email в настройках профиля!

---

## Решение проблем

### Проблема: .env файл не найден или artisan не найден

Это означает, что приложение не установлено. Выполните **Шаг 2.5** выше для завершения установки.

### Проблема: Nginx не запускается

```bash
# Проверьте логи
journalctl -xeu nginx.service

# Проверьте конфигурацию
nginx -t -c /etc/nginx/nginx.conf
```

### Проблема: SSL сертификат не работает

```bash
# Проверьте наличие сертификата
ls -la /etc/letsencrypt/live/crmvirtu.ru/

# Проверьте права доступа
ls -la /etc/letsencrypt/live/crmvirtu.ru/
```

### Проблема: Сайт не открывается

```bash
# Проверьте статус всех сервисов
systemctl status nginx
systemctl status php8.4-fpm

# Проверьте логи приложения
tail -50 /var/www/relaticle/storage/logs/laravel.log

# Проверьте логи Nginx
tail -50 /var/log/nginx/error.log
```

### Проблема: Ошибка 502 Bad Gateway

```bash
# Проверьте PHP-FPM
systemctl status php8.4-fpm

# Перезапустите PHP-FPM
systemctl restart php8.4-fpm

# Проверьте права доступа
ls -la /var/www/relaticle/storage
ls -la /var/www/relaticle/bootstrap/cache
```

---

## Полезные команды для обслуживания

```bash
# Перезапуск всех сервисов
systemctl restart nginx php8.4-fpm

# Просмотр логов в реальном времени
tail -f /var/www/relaticle/storage/logs/laravel.log

# Очистка кеша Laravel
cd /var/www/relaticle
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Обновление приложения
cd /var/www/relaticle
git pull
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Проверка безопасности

1. **Смените пароль администратора** сразу после первого входа
2. **Настройте регулярные бэкапы** базы данных
3. **Обновляйте систему** регулярно: `apt update && apt upgrade -y`
4. **Мониторьте логи** на предмет подозрительной активности

---

## Готово!

После выполнения всех шагов ваша CRM будет доступна по адресу `https://crmvirtu.ru` с работающим SSL сертификатом.
