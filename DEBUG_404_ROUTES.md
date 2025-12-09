# Диагностика ошибки 404 на /app/login

## Проблема
Маршрут `/app/login` возвращает ошибку 404, хотя Filament панель должна его регистрировать.

## Диагностика на сервере

Выполните эти команды для диагностики:

### 1. Проверьте зарегистрированные маршруты

```bash
cd /var/www/relaticle
php8.4 artisan route:list | grep -i "app\|login\|filament"
```

Должны быть маршруты вида:
```
GET|HEAD  app/login ........................................................... filament.app.auth.login
GET|HEAD  app/register ....................................................... filament.app.auth.register
```

### 2. Если маршрутов нет, проверьте провайдеры

```bash
cd /var/www/relaticle
grep "AppPanelProvider" bootstrap/providers.php
```

Должно быть:
```
App\Providers\Filament\AppPanelProvider::class,
```

### 3. Проверьте конфигурацию панели

```bash
cd /var/www/relaticle
grep -nE "->path|->domain" app/Providers/Filament/AppPanelProvider.php
```

Должно быть:
```
77:            ->path('app')
```

### 4. Очистите ВСЕ кеши полностью

```bash
cd /var/www/relaticle

# Очистите все кеши
php8.4 artisan config:clear
php8.4 artisan cache:clear
php8.4 artisan route:clear
php8.4 artisan view:clear

# Удалите файлы кеша вручную
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/views/*

# Очистите автозагрузчик Composer
composer dump-autoload

# Пересоздайте кеш
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
```

### 5. Проверьте, что Filament может найти панель

```bash
cd /var/www/relaticle
php8.4 artisan tinker --execute="echo \Filament\Facades\Filament::getPanel('app') ? 'Panel found' : 'Panel not found';"
```

### 6. Проверьте логи на ошибки

```bash
tail -100 /var/www/relaticle/storage/logs/laravel.log | grep -i "error\|exception\|fatal" | tail -20
```

## Решение: Принудительная перерегистрация маршрутов

Если маршруты все еще не появляются, попробуйте:

```bash
cd /var/www/relaticle

# 1. Полностью очистите все
php8.4 artisan optimize:clear

# 2. Переустановите зависимости (на всякий случай)
composer dump-autoload

# 3. Пересоздайте оптимизацию
php8.4 artisan optimize

# 4. Проверьте маршруты снова
php8.4 artisan route:list | grep "app"
```

## Альтернативное решение: Проверьте, что панель действительно использует path

Если проблема сохраняется, возможно Filament все еще пытается использовать domain. Проверьте:

```bash
cd /var/www/relaticle
php8.4 artisan tinker --execute="
\$panel = \Filament\Facades\Filament::getPanel('app');
echo 'Path: ' . \$panel->getPath() . PHP_EOL;
echo 'Domain: ' . (\$panel->getDomain() ?? 'null') . PHP_EOL;
echo 'Login URL: ' . \$panel->getLoginUrl() . PHP_EOL;
"
```

Должно показать:
```
Path: app
Domain: null
Login URL: /app/login
```

## Если ничего не помогает

Попробуйте временно изменить путь панели на что-то другое, чтобы проверить, работает ли вообще регистрация маршрутов:

```bash
cd /var/www/relaticle
nano app/Providers/Filament/AppPanelProvider.php
```

Измените:
```php
->path('app')
```

На:
```php
->path('admin')
```

Затем:
```bash
php8.4 artisan route:clear
php8.4 artisan route:cache
php8.4 artisan route:list | grep "admin"
```

Если маршруты появятся с путем `admin`, значит проблема в конкретном пути `app`. Возможно, он конфликтует с чем-то другим.

