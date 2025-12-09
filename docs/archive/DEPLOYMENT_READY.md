# Готовность к развертыванию Relaticle CRM

## ✅ Что уже настроено

1. **Система переведена на YandexGPT** - все зависимости от GigaChat удалены
2. **AI модули готовы** - поиск и валидация лидов через YandexGPT
3. **Сервисы валидации** - Email, Phone, Company, Social Media
4. **Интеграции с российскими источниками** - Контур.Компас, Rusprofile, Яндекс.Справочник, 2GIS
5. **Filament Resource для лидов** - полный интерфейс управления
6. **Artisan команды** - поиск и валидация лидов

## 📋 Данные для развертывания

### SSH доступ к серверу
- **Хост**: 83.220.175.224
- **Пользователь**: root
- **Пароль**: YOUR_ADMIN_PASSWORD
- **Домен**: lizon0707.fvds.ru
- **OS**: Ubuntu 24.04

### YandexGPT API ключи
- **API Key**: `YOUR_YANDEX_GPT_API_KEY`
- **Key ID**: `ajetvrtcaq19kpik8cf6`
- **Folder ID**: `YOUR_YANDEX_FOLDER_ID` ✅

### ✅ Все данные получены!

1. ✅ **Yandex Folder ID**: `YOUR_YANDEX_FOLDER_ID`
2. ✅ **База данных**: SQLite
3. ✅ **Системный администратор**:
   - Имя: Администратор
   - Email: YOUR_ADMIN_EMAIL
   - Пароль: YOUR_ADMIN_PASSWORD

## 🔧 Переменные окружения для .env

После получения Folder ID, добавьте в `.env` на сервере:

```env
# YandexGPT
AI_PROVIDER=yandex
YANDEX_GPT_API_KEY=YOUR_YANDEX_GPT_API_KEY
YANDEX_FOLDER_ID=[нужен Folder ID]

# База данных (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/relaticle/database/database.sqlite

# Или PostgreSQL
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=relaticle_prod
# DB_USERNAME=relaticle_user
# DB_PASSWORD=[пароль]

# Приложение
APP_ENV=production
APP_DEBUG=false
APP_URL=http://lizon0707.fvds.ru

# Кеш и сессии
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

## 🚀 Готово к развертыванию!

Все данные получены. См. файл **DEPLOY_NOW.md** для инструкций по развертыванию.

### Быстрый старт:

```bash
ssh root@83.220.175.224
cd /var/www
git clone <ваш-репозиторий> relaticle
cd relaticle
chmod +x deploy.sh
ADMIN_NAME="Администратор" \
ADMIN_EMAIL="YOUR_ADMIN_EMAIL" \
ADMIN_PASSWORD="YOUR_ADMIN_PASSWORD" \
DB_TYPE=sqlite \
./deploy.sh
```

## ⚠️ Важно

- **Folder ID обязателен** - без него YandexGPT не будет работать
- Для 2GB RAM рекомендуется SQLite вместо PostgreSQL
- После развертывания обязательно создайте системного администратора

