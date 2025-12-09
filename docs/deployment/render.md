# Инструкция по деплою Relaticle на Render

## Быстрый старт

### 1. Подготовка репозитория

Убедитесь, что все изменения закоммичены и запушены в GitHub:

```bash
git add .
git commit -m "Prepare for Render deployment"
git push origin main
```

### 2. Создание аккаунта на Render

1. Зайдите на [render.com](https://render.com)
2. Зарегистрируйтесь или войдите
3. Подключите ваш GitHub аккаунт

### 3. Деплой через Blueprint

1. В Dashboard Render нажмите **"New +"** → **"Blueprint"**
2. Выберите ваш репозиторий `relaticle`
3. Render автоматически обнаружит `render.yaml` и создаст все сервисы:
   - Web Service (Laravel приложение)
   - PostgreSQL Database

### 4. Настройка переменных окружения

После создания сервисов, настройте переменные вручную в Dashboard:

#### В Web Service → Environment:

1. **APP_URL** - URL вашего сервиса (будет доступен после деплоя)
   - Формат: `https://relaticle-crm.onrender.com`
   - ⚠️ Установите ПОСЛЕ первого деплоя, когда узнаете URL

2. **MAIL_*** (опционально, для отправки email):
   - `MAIL_HOST` - SMTP сервер
   - `MAIL_USERNAME` - Email для отправки
   - `MAIL_PASSWORD` - Пароль
   - `MAIL_FROM_ADDRESS` - Email отправителя

### 5. Первый запуск и настройка

После успешного деплоя:

1. **Создайте системного администратора:**
   - Откройте Shell в Render Dashboard (Web Service → Shell)
   - Выполните:
   ```bash
   php artisan sysadmin:create --name="Администратор" --email="admin@yourdomain.com" --password="your-secure-password" --no-interaction
   ```

2. **Проверьте работу:**
   - Откройте URL вашего сервиса
   - Войдите в панель администратора: `https://your-app.onrender.com/sysadmin`

### 6. Настройка очередей (опционально)

Для обработки фоновых задач создайте Background Worker:

1. В Dashboard → **"New +"** → **"Background Worker"**
2. Подключите к тому же репозиторию
3. Start Command: `php artisan queue:work --tries=3`
4. Используйте те же переменные окружения, что и Web Service

## Стоимость

**Минимальный вариант (Starter):**
- Web Service: $7/мес
- PostgreSQL: $7/мес
- **Итого: $14/мес**

**Рекомендуемый для production (Standard):**
- Web Service: $25/мес
- PostgreSQL: $20/мес
- **Итого: $45/мес**

## Полезные команды

### Через Shell в Render Dashboard:

```bash
# Проверка статуса миграций
php artisan migrate:status

# Запуск миграций
php artisan migrate --force

# Создание системного администратора
php artisan sysadmin:create

# Очистка кеша
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Просмотр логов
tail -f storage/logs/laravel.log
```

## Важные замечания

1. **APP_URL** должен быть установлен правильно для работы CSRF защиты
2. После первого деплоя обязательно создайте системного администратора
3. Для production рекомендуется использовать Standard план
4. Настройте резервное копирование базы данных в Render Dashboard

## Решение проблем

### Ошибка "Class Redis not found"
- Убедитесь, что `CACHE_STORE=database` в переменных окружения

### Ошибка миграций
- Проверьте подключение к базе данных
- Убедитесь, что все переменные DB_* установлены правильно

### Проблемы с сессиями
- Убедитесь, что `SESSION_DRIVER=database`
- Проверьте, что таблица `sessions` создана

## Поддержка

Если возникли проблемы:
1. Проверьте логи в Render Dashboard
2. Используйте Shell для отладки
3. Проверьте переменные окружения

