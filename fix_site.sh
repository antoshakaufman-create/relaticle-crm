#!/bin/bash

# Скрипт для быстрого исправления проблем на сайте crmvirtu.ru
# Использование: sudo ./fix_site.sh

set -e

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Проверка root
if [ "$EUID" -ne 0 ]; then
    error "Запустите от root: sudo ./fix_site.sh"
    exit 1
fi

APP_DIR="/var/www/relaticle"
PHP_VERSION="8.5"

info "Начинаем исправление сайта..."

# 1. Проверка существования директории
if [ ! -d "$APP_DIR" ]; then
    error "Директория приложения не найдена: $APP_DIR"
    exit 1
fi

cd "$APP_DIR"

# 2. Проверка PHP
info "Проверка PHP версии..."
if ! command -v php$PHP_VERSION &> /dev/null; then
    warn "PHP $PHP_VERSION не найден, используем дефолтный PHP"
    PHP_CMD="php"
else
    PHP_CMD="php$PHP_VERSION"
    info "Используем PHP: $($PHP_CMD -v | head -n 1)"
fi

# 3. Исправление прав доступа
info "Исправление прав доступа..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# Создание недостающих директорий
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/logs"
chmod -R 775 "$APP_DIR/storage"
chown -R www-data:www-data "$APP_DIR/storage"

# 4. Очистка всех кэшей
info "Очистка всех кэшей..."
$PHP_CMD artisan optimize:clear || warn "Не удалось выполнить optimize:clear"
$PHP_CMD artisan config:clear || warn "Не удалось очистить config"
$PHP_CMD artisan route:clear || warn "Не удалось очистить routes"
$PHP_CMD artisan view:clear || warn "Не удалось очистить views"
$PHP_CMD artisan cache:clear || warn "Не удалось очистить cache"

# 5. Пересоздание кэшей
info "Пересоздание кэшей..."
$PHP_CMD artisan config:cache
$PHP_CMD artisan route:cache
$PHP_CMD artisan view:cache
$PHP_CMD artisan filament:assets

# 6. Проверка .env файла
info "Проверка конфигурации..."
if [ ! -f ".env" ]; then
    error ".env файл не найден!"
    exit 1
fi

# Проверка APP_KEY
if ! grep -q "APP_KEY=base64" .env; then
    warn "APP_KEY не установлен, генерируем..."
    $PHP_CMD artisan key:generate
fi

# 7. Проверка базы данных
info "Проверка базы данных..."
if [ -f "database/database.sqlite" ]; then
    chown www-data:www-data database/database.sqlite
    chmod 664 database/database.sqlite
    info "Права на БД восстановлены"
fi

# 8. Миграции (осторожно)
info "Проверка миграций..."
$PHP_CMD artisan migrate --force || warn "Некоторые миграции не выполнились"

# 9. Перезапуск сервисов
info "Перезапуск PHP-FPM..."
if systemctl is-active --quiet php$PHP_VERSION-fpm; then
    systemctl restart php$PHP_VERSION-fpm
    info "PHP-FPM перезапущен"
else
    warn "Сервис php$PHP_VERSION-fpm не запущен"
    # Попробуем стандартный
    if systemctl is-active --quiet php-fpm; then
        systemctl restart php-fpm
        info "PHP-FPM (default) перезапущен"
    fi
fi

info "Перезапуск Nginx..."
if systemctl is-active --quiet nginx; then
    nginx -t && systemctl reload nginx
    info "Nginx перезагружен"
else
    error "Nginx не запущен!"
    exit 1
fi

# 10. Проверка логов
info "Последние 10 строк лога Laravel:"
tail -n 10 "$APP_DIR/storage/logs/laravel.log" 2>/dev/null || info "Лог пуст или не найден"

info "Последние 10 строк лога Nginx:"
tail -n 10 /var/log/nginx/error.log 2>/dev/null || info "Лог пуст"

# 11. Финальная проверка
info "=== Проверка статуса сервисов ==="
systemctl status php$PHP_VERSION-fpm --no-pager | head -n 5
systemctl status nginx --no-pager | head -n 5

echo ""
info "Исправление завершено!"
info "Теперь проверьте сайт: https://crmvirtu.ru"
warn "Если проблема сохраняется, проверьте логи выше"
