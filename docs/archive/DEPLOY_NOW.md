# 🚀 Развертывание Relaticle CRM - Готово к запуску!

## ✅ Все данные готовы

- **SSH**: 83.220.175.224, root, YOUR_ADMIN_PASSWORD
- **Домен**: lizon0707.fvds.ru
- **База данных**: SQLite
- **Администратор**: YOUR_ADMIN_EMAIL / YOUR_ADMIN_PASSWORD
- **YandexGPT**: API Key и Folder ID настроены

## 📋 Команды для развертывания

### Вариант 1: Полное автоматическое развертывание

```bash
# 1. Подключитесь к серверу
ssh root@83.220.175.224
# Пароль: YOUR_ADMIN_PASSWORD

# 2. Перейдите в директорию
cd /var/www

# 3. Клонируйте репозиторий (замените на ваш URL)
git clone <ваш-репозиторий> relaticle
cd relaticle

# 4. Запустите развертывание с автоматическим созданием администратора
chmod +x deploy.sh
ADMIN_NAME="Администратор" \
ADMIN_EMAIL="YOUR_ADMIN_EMAIL" \
ADMIN_PASSWORD="YOUR_ADMIN_PASSWORD" \
DB_TYPE=sqlite \
./deploy.sh
```

### Вариант 2: Пошаговое развертывание

```bash
# 1. Подключитесь к серверу
ssh root@83.220.175.224

# 2. Клонируйте репозиторий
cd /var/www
git clone <ваш-репозиторий> relaticle
cd relaticle

# 3. Запустите скрипт развертывания
chmod +x deploy.sh
DB_TYPE=sqlite ./deploy.sh

# 4. Настройте .env (скопируйте из DEPLOYMENT_ENV.txt)
nano .env
# Вставьте содержимое DEPLOYMENT_ENV.txt

# 5. Сгенерируйте APP_KEY
php artisan key:generate --force

# 6. Настройте базу данных
touch database/database.sqlite
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite
php artisan migrate --force

# 7. Создайте администратора
php artisan sysadmin:create \
  --name="Администратор" \
  --email="YOUR_ADMIN_EMAIL" \
  --password="YOUR_ADMIN_PASSWORD" \
  --no-interaction

# 8. Настройте SSL (опционально, но рекомендуется)
apt install -y certbot python3-certbot-nginx
certbot --nginx -d lizon0707.fvds.ru
```

## 🔍 Проверка работы

После развертывания:

1. Откройте в браузере: `http://lizon0707.fvds.ru` или `https://lizon0707.fvds.ru`
2. Войдите в панель администратора: `/sysadmin`
3. Используйте:
   - Email: `YOUR_ADMIN_EMAIL`
   - Пароль: `YOUR_ADMIN_PASSWORD`

## 📊 Проверка сервисов

```bash
# Статус сервисов
systemctl status nginx
systemctl status php8.4-fpm
systemctl status relaticle-queue

# Логи
tail -f /var/www/relaticle/storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

## ⚙️ Полезные команды

```bash
cd /var/www/relaticle

# Очистка кеша
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Перезапуск сервисов
systemctl restart nginx
systemctl restart php8.4-fpm
systemctl restart relaticle-queue

# Проверка очередей
php artisan queue:work --once
```

## 🎯 Что дальше?

После успешного развертывания:

1. ✅ Проверьте вход в систему
2. ✅ Создайте команду (Team)
3. ✅ Настройте поиск лидов через AI модули
4. ✅ Протестируйте валидацию лидов

## ⚠️ Важно

- После первого входа смените пароль администратора
- Настройте резервное копирование базы данных
- Регулярно обновляйте зависимости: `composer update` и `npm update`

---

**Готово к развертыванию!** 🚀

