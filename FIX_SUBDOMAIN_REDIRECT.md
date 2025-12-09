# Исправление редиректа на поддомен

## Проблема
Сайт все еще редиректит на `https://app.crmvirtu.ru/login` вместо `https://crmvirtu.ru/app/login`

## Причина
На сервере не обновлен код или не очищен кеш конфигурации Filament.

## Решение

### 1. Обновите код на сервере

```bash
cd /var/www/relaticle
git pull origin main
```

### 2. Очистите ВСЕ кеши Laravel

```bash
cd /var/www/relaticle

# Очистите все кеши
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Удалите файлы кеша вручную (на всякий случай)
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/views/*

# Пересоздайте кеш
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Перезапустите PHP-FPM

```bash
systemctl restart php8.4-fpm
```

### 4. Проверьте конфигурацию Filament

Убедитесь, что в файле `app/Providers/Filament/AppPanelProvider.php` используется `->path('app')` а не `->domain()`:

```bash
cd /var/www/relaticle
grep -nE "->path|->domain" app/Providers/Filament/AppPanelProvider.php
```

Или проще:
```bash
grep "path\|domain" app/Providers/Filament/AppPanelProvider.php
```

Должно быть:
```
77:            ->path('app')
```

НЕ должно быть:
```
->domain('app.crmvirtu.ru')
```

### 5. Если проблема сохраняется

Проверьте, что в `.env` нет настроек, которые могут влиять на URL:

```bash
cd /var/www/relaticle
grep -i "APP_URL\|DOMAIN" .env
```

`APP_URL` должен быть:
```
APP_URL=https://crmvirtu.ru
```

### 6. Проверьте логи

```bash
tail -50 /var/www/relaticle/storage/logs/laravel.log
tail -50 /var/log/nginx/error.log
```

## Ожидаемый результат

После выполнения всех шагов:
- Открытие `https://crmvirtu.ru` должно редиректить на `https://crmvirtu.ru/app/login`
- НЕ должно быть редиректа на `app.crmvirtu.ru`

