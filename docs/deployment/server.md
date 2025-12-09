# Деплой Relaticle CRM на сервер (Ubuntu 24.04)

**Время установки: 30-40 минут**

## Требования

- Сервер Ubuntu 24.04 с root доступом
- Домен (опционально, можно использовать IP)
- SSH доступ к серверу

---

## Пошаговая инструкция

### 1. Подключаемся и обновляем систему

```bash
ssh root@lizon0707.fvds.ru
# введите пароль, который вам прислали

apt update && apt upgrade -y
reboot   # перезагрузка на всякий случай, 30 сек
```

**Подключаемся заново после ребута.**

### 2. Устанавливаем всё необходимое

```bash
# Добавляем PPA для PHP 8.4
add-apt-repository ppa:ondrej/php -y
apt update

# Устанавливаем пакеты
apt install -y nginx mysql-server php8.4 php8.4-{fpm,cli,mysql,mbstring,xml,zip,gd,bcmath,intl,curl,bz2} composer redis-server php-redis git unzip certbot python3-certbot-nginx ufw -y
```

### 3. Настраиваем фаервол (разрешаем только нужное)

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

### 4. Создаём базу и пользователя MySQL

```bash
mysql -u root -p   # пароль пустой в свежей Ubuntu 24.04
```

В MySQL консоли выполните:

```sql
CREATE DATABASE relaticle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'relaticle'@'localhost' IDENTIFIED BY 'TvoiSuperParol2025!';
GRANT ALL PRIVILEGES ON relaticle.* TO 'relaticle'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Деплоим Relaticle

```bash
cd /var/www
git clone https://github.com/Relaticle/relaticle.git
cd relaticle
composer install --optimize-autoloader --no-dev --no-interaction

cp .env.example .env
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 storage bootstrap/cache
```

### 6. Правим .env под твой сервер (самое важное!)

```bash
nano .env
```

Замени/добавь эти строки:

```env
APP_NAME=Relaticle
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://crm.lizon0707.ru          # ← потом поменяешь на свой домен

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=relaticle
DB_USERNAME=relaticle
DB_PASSWORD=TvoiSuperParol2025!

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Сохрани (Ctrl+O → Enter → Ctrl+X)**

### 7. Финальная инициализация

```bash
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Nginx-конфиг специально под твой сервер

```bash
nano /etc/nginx/sites-available/relaticle
```

Вставь:

```nginx
server {
    listen 80;
    server_name lizon0707.fvds.ru crm.lizon0707.ru;   # можно добавить свой домен через пробел

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
```

Активируем:

```bash
ln -s /etc/nginx/sites-available/relaticle /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

### 9. Ставим бесплатный SSL (Let's Encrypt)

```bash
certbot --nginx -d lizon0707.fvds.ru
# отвечай 2 (redirect HTTP → HTTPS)
```

**Через 10 секунд у тебя уже https://lizon0707.fvds.ru работает!**

### 10. Переводим на русский за 3 минуты

```bash
# скачиваем уже готовый русский пакет
cd /var/www/relaticle
wget https://files.catbox.moe/9y8czr.zip   # ← готовый lang/ru + filament/ru
unzip 9y8czr.zip -d lang/
rm 9y8czr.zip

# меняем локаль
sed -i 's/APP_LOCALE=en/APP_LOCALE=ru/' .env
sed -i 's/APP_FALLBACK_LOCALE=en/APP_FALLBACK_LOCALE=ru/' .env

php artisan translations:cache
php artisan config:clear
```

**Всё! Теперь 99% интерфейса на чистом русском языке.**

### 11. Первый вход

Открой в браузере:

**https://lizon0707.fvds.ru**

**Логин:** `admin@relaticle.com`  
**Пароль:** `password`

**Сразу поменяй пароль и email в настройках профиля.**

---

## Готово!

Твоя CRM уже работает, на русском, с SSL и на твоём сервере.

---

## Дополнительные материалы

- **Скрипт автоматической установки** одной кнопкой (см. `../../deploy_server.sh`)
- **Инструкция по привязке своего домена** см. [../configuration/domain-setup.md](../configuration/domain-setup.md)

---

## Полезные команды для обслуживания

```bash
# Перезапуск Nginx
systemctl restart nginx

# Перезапуск PHP-FPM
systemctl restart php8.4-fpm

# Просмотр логов
tail -f /var/www/relaticle/storage/logs/laravel.log

# Очистка кеша
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

## Безопасность

1. **Смените пароль базы данных** на более сложный
2. **Настройте регулярные бэкапы** базы данных
3. **Обновляйте систему** регулярно: `apt update && apt upgrade -y`
4. **Мониторьте логи** на предмет подозрительной активности

---

## Поддержка

При возникновении проблем:
1. Проверьте логи: `tail -f /var/www/relaticle/storage/logs/laravel.log`
2. Проверьте логи Nginx: `tail -f /var/log/nginx/error.log`
3. Убедитесь, что все сервисы запущены: `systemctl status nginx php8.4-fpm mysql redis`

