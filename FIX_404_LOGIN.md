# Исправление ошибки 404 на /app/login

## Проблема
Ошибка 404 при попытке открыть `/app/login` означает, что Filament не зарегистрировал маршруты для панели.

## Решение

### 1. Убедитесь, что код обновлен

```bash
cd /var/www/relaticle
git pull origin main
```

### 2. Проверьте, что используется path, а не domain

```bash
cd /var/www/relaticle
grep -nE "->path|->domain" app/Providers/Filament/AppPanelProvider.php
```

Должно быть:
```
77:            ->path('app')
```

НЕ должно быть:
```
->domain('app.crmvirtu.ru')
```

### 3. Очистите ВСЕ кеши (критически важно!)

```bash
cd /var/www/relaticle

# Очистите все кеши
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Удалите файлы кеша вручную
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/views/*

# Пересоздайте кеш
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Проверьте зарегистрированные маршруты

```bash
cd /var/www/relaticle
php artisan route:list | grep app
```

Должны быть маршруты вида:
```
filament.app.login
filament.app.register
```

### 5. Если маршруты не найдены, проверьте провайдеры

```bash
cd /var/www/relaticle
grep "AppPanelProvider" bootstrap/providers.php
```

Должно быть:
```
App\Providers\Filament\AppPanelProvider::class,
```

### 6. Перезапустите PHP-FPM

```bash
systemctl restart php8.4-fpm
```

### 7. Проверьте логи

```bash
tail -50 /var/www/relaticle/storage/logs/laravel.log
tail -50 /var/log/nginx/error.log
```

### 8. Если проблема сохраняется - перезапустите приложение

```bash
cd /var/www/relaticle

# Очистите автозагрузчик Composer
composer dump-autoload

# Очистите все кеши снова
php artisan optimize:clear

# Пересоздайте кеш
php artisan optimize

# Перезапустите PHP-FPM
systemctl restart php8.4-fpm
```

## Проверка

После выполнения всех шагов проверьте:

```bash
# Проверьте маршруты
php artisan route:list | grep "filament.app"

# Должны быть маршруты:
# GET|HEAD  app/login ........................................................... filament.app.auth.login
# GET|HEAD  app/register ....................................................... filament.app.auth.register
```

## Ожидаемый результат

После выполнения всех шагов:
- `https://crmvirtu.ru/app/login` должна открываться без ошибки 404
- Страница входа должна отображаться на русском языке

