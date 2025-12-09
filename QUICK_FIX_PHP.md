# Быстрое исправление PHP 8.4 на сервере

## Проблема
Сервер использует PHP 8.3, а проект требует PHP 8.4.

## Быстрое решение (выполните все команды подряд)

```bash
# 1. Установите PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4 php8.4-{fpm,cli,sqlite3,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} php8.4-redis

# 2. Обновите конфигурацию Nginx (ВАЖНО!)
sed -i 's|php8.3-fpm.sock|php8.4-fpm.sock|g' /etc/nginx/sites-available/relaticle

# 3. Проверьте конфигурацию Nginx
nginx -t

# 4. Перезагрузите Nginx
systemctl reload nginx

# 5. Запустите PHP-FPM 8.4
systemctl start php8.4-fpm
systemctl enable php8.4-fpm

# 6. Проверьте, что PHP 8.4 работает
php8.4 -v

# 7. Очистите кеш Laravel
cd /var/www/relaticle
php8.4 artisan config:clear
php8.4 artisan cache:clear
php8.4 artisan route:clear
php8.4 artisan view:clear
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# 8. Проверьте статус
systemctl status php8.4-fpm
```

## Проверка после исправления

```bash
# Проверьте версию PHP
php8.4 -v

# Проверьте конфигурацию Nginx
grep "fastcgi_pass" /etc/nginx/sites-available/relaticle

# Должно быть: fastcgi_pass unix:/run/php/php8.4-fpm.sock;
# НЕ должно быть: fastcgi_pass unix:/run/php/php8.3-fpm.sock;

# Проверьте статус PHP-FPM
systemctl status php8.4-fpm
```

## Если проблема сохраняется

Проверьте, что PHP 8.4 действительно установлен:

```bash
# Проверьте установленные версии PHP
dpkg -l | grep php8

# Должны быть пакеты php8.4-*
```

Если PHP 8.4 не установлен, выполните установку снова:

```bash
apt install -y php8.4 php8.4-fpm php8.4-cli
```

