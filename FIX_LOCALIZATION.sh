#!/bin/bash

# Скрипт для исправления ошибки 500, связанной с русской локализацией
# Использование: ./FIX_LOCALIZATION.sh

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Проверка, что скрипт запущен от root
if [ "$EUID" -ne 0 ]; then
    error "Пожалуйста, запустите скрипт от root: sudo ./FIX_LOCALIZATION.sh"
    exit 1
fi

APP_DIR="/var/www/relaticle"
cd "$APP_DIR"

info "Начинаем диагностику и исправление проблемы с локализацией..."

# ============================================
# ШАГ 1: Диагностика
# ============================================
info "ШАГ 1: Проверяем логи ошибок..."

if [ -f "storage/logs/laravel.log" ]; then
    info "Последние ошибки из лога:"
    tail -100 storage/logs/laravel.log | grep -A 10 "ERROR\|Exception" | head -50 || warn "Ошибок в логе не найдено"
else
    warn "Файл лога не найден"
fi

# ============================================
# ШАГ 2: Очистка всех кешей
# ============================================
info "ШАГ 2: Очищаем все кеши..."

php8.4 artisan optimize:clear
php8.4 artisan config:clear
php8.4 artisan route:clear
php8.4 artisan view:clear

# Удаляем файлы кеша вручную
rm -rf bootstrap/cache/*.php 2>/dev/null || true
rm -rf storage/framework/cache/data/* 2>/dev/null || true

info "Кеши очищены"

# ============================================
# ШАГ 3: Временное переключение на английский
# ============================================
info "ШАГ 3: Временно переключаемся на английский язык для диагностики..."

# Сохраняем текущую локаль
CURRENT_LOCALE=$(grep "^APP_LOCALE=" .env | cut -d '=' -f2 || echo "ru")
info "Текущая локаль: $CURRENT_LOCALE"

# Переключаемся на английский
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=en|' .env
sed -i 's|^APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=en|' .env

# Пересоздаем кеши
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# Перезапускаем PHP-FPM
systemctl restart php8.4-fpm

info "Переключено на английский язык. Проверьте сайт в браузере."
info "Если ошибка 500 исчезла - проблема точно в локализации."
read -p "Нажмите Enter после проверки сайта..."

# ============================================
# ШАГ 4: Исправление локализации
# ============================================
info "ШАГ 4: Исправляем локализацию..."

# Проверяем наличие переводов Filament
if php8.4 artisan list | grep -q "filament:translations"; then
    info "Публикуем переводы Filament..."
    php8.4 artisan filament:translations || warn "Команда filament:translations не доступна"
else
    warn "Команда filament:translations не найдена"
fi

# Проверяем файлы переводов
if [ ! -f "lang/ru/resources.php" ]; then
    error "Файл lang/ru/resources.php не найден!"
    exit 1
fi

info "Файлы переводов проверены"

# ============================================
# ШАГ 5: Восстановление русского языка
# ============================================
info "ШАГ 5: Возвращаем русский язык..."

# Возвращаем русский
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=ru|' .env
sed -i 's|^APP_FALLBACK_LOCALE=.*|APP_FALLBACK_LOCALE=ru|' .env

# Очищаем кеши снова
php8.4 artisan optimize:clear
php8.4 artisan config:clear
php8.4 artisan route:clear
php8.4 artisan view:clear

# Пересоздаем кеши
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

# Перезапускаем PHP-FPM
systemctl restart php8.4-fpm

info "Русский язык восстановлен"

# ============================================
# Финальная проверка
# ============================================
info "Проверяем конфигурацию..."
grep "^APP_LOCALE=" .env
grep "^APP_FALLBACK_LOCALE=" .env

info ""
info "============================================"
info "Исправление завершено!"
info "============================================"
info ""
info "Проверьте сайт в браузере: https://crmvirtu.ru/app/login"
info "Если ошибка 500 все еще возникает, проверьте логи:"
info "  tail -50 $APP_DIR/storage/logs/laravel.log"
info ""

