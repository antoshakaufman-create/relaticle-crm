# Быстрое развертывание Relaticle CRM

## ✅ Все данные готовы

- **SSH**: 83.220.175.224, root, YOUR_ADMIN_PASSWORD
- **YandexGPT API Key**: YOUR_YANDEX_GPT_API_KEY
- **Yandex Folder ID**: YOUR_YANDEX_FOLDER_ID
- **Домен**: lizon0707.fvds.ru

## 🚀 Быстрый старт

### 1. Подключитесь к серверу

```bash
ssh root@83.220.175.224
# Пароль: YOUR_ADMIN_PASSWORD
```

### 2. Подготовьте репозиторий

```bash
cd /var/www
git clone <ваш-репозиторий> relaticle
cd relaticle
```

### 3. Запустите скрипт развертывания

```bash
chmod +x deploy.sh
DB_TYPE=sqlite ./deploy.sh
```

### 4. Настройте .env

```bash
nano .env
```

Скопируйте содержимое из `DEPLOYMENT_ENV.txt` и вставьте в `.env`.

**ВАЖНО**: Сгенерируйте APP_KEY:
```bash
php artisan key:generate --force
```

### 5. Выполните миграции

```bash
touch database/database.sqlite
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite
php artisan migrate --force
```

### 6. Создайте администратора

```bash
php artisan sysadmin:create \
  --name="Администратор" \
  --email="YOUR_ADMIN_EMAIL" \
  --password="YOUR_ADMIN_PASSWORD" \
  --no-interaction
```

### 7. Настройте SSL (опционально)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d lizon0707.fvds.ru
```

## ✅ Готово!

Откройте в браузере: `http://lizon0707.fvds.ru` или `https://lizon0707.fvds.ru`

Войдите в панель администратора: `/sysadmin`

## ✅ Все данные готовы!

- **База данных**: SQLite
- **Администратор**: 
  - Имя: Администратор
  - Email: YOUR_ADMIN_EMAIL
  - Пароль: YOUR_ADMIN_PASSWORD

Можно приступать к развертыванию!



