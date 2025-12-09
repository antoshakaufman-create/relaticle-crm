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

## Важно: Filament панель использует путь вместо поддомена

Filament панель настроена на использование пути `/app` вместо поддомена. Это означает, что:
- Страница входа будет доступна по адресу: `https://crmvirtu.ru/app/login`
- Главная страница панели: `https://crmvirtu.ru/app`

**Никаких дополнительных настроек DNS или Nginx не требуется!** Всё работает на основном домене.

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

