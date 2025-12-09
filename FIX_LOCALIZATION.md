# Инструкция по исправлению ошибки 500 из-за русской локализации

## Проблема
При загрузке страниц (companies и др.) возникает ошибка 500, вероятно связанная с русской локализацией.

## Решение

### Вариант 1: Автоматический скрипт

Загрузите скрипт на сервер и выполните:

```bash
# На локальном компьютере
scp FIX_LOCALIZATION.sh root@83.220.175.224:/root/

# На сервере
ssh root@83.220.175.224
chmod +x /root/FIX_LOCALIZATION.sh
/root/FIX_LOCALIZATION.sh
```

### Вариант 2: Ручное выполнение

Выполните команды на сервере по порядку:

#### ШАГ 1: Диагностика

```bash
cd /var/www/relaticle

# Проверьте полный стек ошибки
tail -100 storage/logs/laravel.log | grep -A 10 "ERROR\|Exception" | head -50
```

#### ШАГ 2: Очистка всех кешей

```bash
cd /var/www/relaticle

# Очистите все кеши Laravel
php8.4 artisan optimize:clear
php8.4 artisan config:clear
php8.4 artisan route:clear
php8.4 artisan view:clear

# Удалите файлы кеша вручную
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/data/*
```

#### ШАГ 3: Временное переключение на английский

```bash
cd /var/www/relaticle

# Сохраните текущую локаль (для справки)
grep "^APP_LOCALE=" .env

# Переключите на английский
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=en|' .env
sed -i 's|^APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=en|' .env

# Пересоздайте кеши
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# Перезапустите PHP-FPM
systemctl restart php8.4-fpm
```

**Проверьте сайт в браузере:**
- Откройте `https://crmvirtu.ru/app/login`
- Попробуйте войти и открыть страницу companies
- Если ошибка 500 исчезла - проблема точно в локализации

#### ШАГ 4: Исправление локализации

Если ошибка исчезла при английском языке, проблема в русской локализации:

```bash
cd /var/www/relaticle

# Попробуйте опубликовать переводы Filament (если команда доступна)
php8.4 artisan filament:translations 2>/dev/null || echo "Команда не доступна"

# Проверьте наличие файлов переводов
ls -la lang/ru/
```

#### ШАГ 5: Восстановление русского языка

```bash
cd /var/www/relaticle

# Верните русский язык
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=ru|' .env
sed -i 's|^APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=ru|' .env

# Очистите кеши снова
php8.4 artisan optimize:clear
php8.4 artisan config:clear
php8.4 artisan route:clear
php8.4 artisan view:clear

# Пересоздайте кеши
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# Перезапустите PHP-FPM
systemctl restart php8.4-fpm
```

## Проверка результата

После выполнения всех шагов:

1. Откройте сайт: `https://crmvirtu.ru/app/login`
2. Войдите с учетными данными:
   - Email: `anton.kaufmann95@gmail.com`
   - Пароль: `Start1!`
3. Попробуйте открыть страницу companies
4. Если ошибка 500 все еще возникает, проверьте логи:

```bash
tail -50 /var/www/relaticle/storage/logs/laravel.log
```

## Дополнительная диагностика

Если проблема сохраняется, проверьте:

1. **Права доступа:**
```bash
chown -R www-data:www-data /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/storage
```

2. **Наличие всех файлов переводов:**
```bash
ls -la /var/www/relaticle/lang/ru/
```

3. **Конфигурацию Filament:**
```bash
grep -n "locale\|locale" /var/www/relaticle/app/Providers/Filament/AppPanelProvider.php
```

## Если проблема не решена

Если после всех шагов ошибка 500 сохраняется:

1. Проверьте полный стек ошибки в логах
2. Убедитесь, что все файлы переводов на месте
3. Проверьте, что Filament правильно настроен для русского языка
4. Возможно, нужно установить пакет переводов Filament для русского языка

