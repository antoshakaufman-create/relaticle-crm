# Исправление версии PHP: установка PHP 8.4

## Проблема
Сервер использует PHP 8.3, а проект требует PHP 8.4.0 или выше.

Ошибка в логах:
```
Your Composer dependencies require a PHP version ">= 8.4.0". You are running 8.3.28.
fastcgi://unix:/run/php/php8.3-fpm.sock
```

## Решение

### 1. Установите PHP 8.4

```bash
# Добавьте PPA для PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update

# Установите PHP 8.4 и необходимые расширения
apt install -y php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} php8.4-redis

# Проверьте версию
php8.4 -v
```

### 2. Обновите конфигурацию Nginx

```bash
# Отредактируйте конфигурацию Nginx
nano /etc/nginx/sites-available/relaticle
```

Измените строку:
```
fastcgi_pass unix:/run/php/php8.3-fpm.sock;
```

На:
```
fastcgi_pass unix:/run/php/php8.4-fpm.sock;
```

Или выполните автоматическую замену:

```bash
sed -i 's|php8.3-fpm.sock|php8.4-fpm.sock|g' /etc/nginx/sites-available/relaticle
```

### 3. Проверьте и перезагрузите Nginx

```bash
# Проверьте конфигурацию
nginx -t

# Перезагрузите Nginx
systemctl reload nginx
```

### 4. Запустите PHP-FPM 8.4

```bash
# Запустите PHP-FPM 8.4
systemctl start php8.4-fpm

# Включите автозапуск
systemctl enable php8.4-fpm

# Проверьте статус
systemctl status php8.4-fpm
```

### 5. Остановите PHP 8.3 (опционально, но рекомендуется)

```bash
# Остановите PHP-FPM 8.3
systemctl stop php8.3-fpm

# Отключите автозапуск
systemctl disable php8.3-fpm
```

### 6. Проверьте, что все работает

```bash
# Проверьте версию PHP CLI
php -v

# Должно быть: PHP 8.4.x

# Проверьте, что PHP-FPM 8.4 работает
systemctl status php8.4-fpm

# Проверьте логи
tail -20 /var/www/relaticle/storage/logs/laravel.log
```

### 7. Очистите кеш Laravel

```bash
cd /var/www/relaticle

# Очистите все кеши
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Пересоздайте кеш
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Полная последовательность команд (копируйте и выполняйте)

```bash
# 1. Установка PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} php8.4-redis

# 2. Обновление конфигурации Nginx
sed -i 's|php8.3-fpm.sock|php8.4-fpm.sock|g' /etc/nginx/sites-available/relaticle

# 3. Проверка и перезагрузка Nginx
nginx -t
systemctl reload nginx

# 4. Запуск PHP-FPM 8.4
systemctl start php8.4-fpm
systemctl enable php8.4-fpm

# 5. Остановка PHP 8.3 (опционально)
systemctl stop php8.3-fpm
systemctl disable php8.3-fpm

# 6. Очистка кеша Laravel
cd /var/www/relaticle
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Проверка
php -v
systemctl status php8.4-fpm
```

## Ожидаемый результат

После выполнения всех шагов:
- PHP 8.4 установлен и работает
- Nginx использует PHP 8.4
- Сайт должен работать без ошибок версии PHP
- `/app/login` должна открываться

## Проверка

Откройте в браузере:
- `https://crmvirtu.ru` - должно редиректить на `/app/login`
- `https://crmvirtu.ru/app/login` - должна открываться страница входа

