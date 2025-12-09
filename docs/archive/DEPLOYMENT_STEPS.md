# Пошаговая инструкция по развертыванию Relaticle CRM

## Подготовка

### Данные для развертывания:
- **SSH**: 83.220.175.224, пользователь: root, пароль: YOUR_ADMIN_PASSWORD
- **Домен**: lizon0707.fvds.ru
- **YandexGPT API Key**: YOUR_YANDEX_GPT_API_KEY
- **Yandex Folder ID**: YOUR_YANDEX_FOLDER_ID

## Шаг 1: Подключение к серверу

```bash
ssh root@83.220.175.224
# Пароль: YOUR_ADMIN_PASSWORD
```

## Шаг 2: Загрузка скрипта развертывания

На вашем локальном компьютере:

```bash
# Если репозиторий уже на GitHub, склонируйте его на сервер
# Или загрузите файлы через scp
```

На сервере:

```bash
# Создайте директорию для приложения
mkdir -p /var/www/relaticle
cd /var/www/relaticle

# Клонируйте репозиторий (замените на ваш URL)
git clone <ваш-репозиторий> .

# Или загрузите файлы через scp с локального компьютера
```

## Шаг 3: Запуск скрипта развертывания

```bash
cd /var/www/relaticle
chmod +x deploy.sh
./deploy.sh
```

Скрипт автоматически:
- Установит PHP 8.4, Composer, Node.js, Nginx
- Установит зависимости
- Настроит базу данных (SQLite по умолчанию)
- Настроит Nginx
- Настроит systemd сервисы

## Шаг 4: Настройка .env файла

```bash
cd /var/www/relaticle
nano .env
```

Скопируйте содержимое из `DEPLOYMENT_ENV.txt` и вставьте в `.env`.

**ВАЖНО**: Замените значения:
- `APP_KEY` - будет сгенерирован автоматически при первом запуске
- Если используете PostgreSQL - настройте `DB_*` переменные

## Шаг 5: Выполнение миграций

```bash
cd /var/www/relaticle

# Для SQLite
touch database/database.sqlite
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite
php artisan migrate --force

# Для PostgreSQL (если выбрали)
# php artisan migrate --force
```

## Шаг 6: Создание системного администратора

```bash
cd /var/www/relaticle
php artisan sysadmin:create \
  --name="Администратор" \
  --email="YOUR_ADMIN_EMAIL" \
  --password="YOUR_ADMIN_PASSWORD" \
  --no-interaction
```

## Шаг 7: Настройка SSL (опционально, но рекомендуется)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d lizon0707.fvds.ru
```

## Шаг 8: Проверка работы

1. Откройте в браузере: `http://lizon0707.fvds.ru` или `https://lizon0707.fvds.ru`
2. Войдите в панель администратора: `/sysadmin`
3. Используйте созданные учетные данные

## Полезные команды

### Проверка статуса сервисов
```bash
systemctl status nginx
systemctl status php8.4-fpm
systemctl status relaticle-queue
```

### Просмотр логов
```bash
tail -f /var/www/relaticle/storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

### Перезапуск сервисов
```bash
systemctl restart nginx
systemctl restart php8.4-fpm
systemctl restart relaticle-queue
```

### Очистка кеша
```bash
cd /var/www/relaticle
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Решение проблем

### Ошибка "Class Redis not found"
Убедитесь, что в `.env` установлено:
```
CACHE_STORE=database
```

### Ошибка миграций
Проверьте права доступа к базе данных:
```bash
chown -R www-data:www-data /var/www/relaticle/database
```

### Проблемы с сессиями
Убедитесь, что таблица `sessions` создана:
```bash
php artisan migrate --force
```

## Следующие шаги после развертывания

1. Настройте резервное копирование базы данных
2. Настройте мониторинг (опционально)
3. Добавьте пользователей в команду
4. Настройте поиск лидов через AI модули



