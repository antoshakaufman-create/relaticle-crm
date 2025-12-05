#!/bin/bash

# Скрипт для создания репозитория и автоматического развертывания
# Этот скрипт поможет создать GitHub репозиторий и выполнить развертывание

set -e

echo "=========================================="
echo "🚀 Создание репозитория и развертывание"
echo "=========================================="
echo ""

# Проверка наличия Git
if ! command -v git &> /dev/null; then
    echo "❌ Git не установлен"
    exit 1
fi

# Проверка наличия GitHub CLI
if ! command -v gh &> /dev/null; then
    echo "❌ GitHub CLI не установлен"
    echo "Установите: brew install gh"
    echo "Затем авторизуйтесь: gh auth login"
    exit 1
fi

# Проверка авторизации GitHub
if ! gh auth status &> /dev/null; then
    echo "❌ Не авторизованы в GitHub CLI"
    echo "Выполните: gh auth login"
    exit 1
fi

echo "✅ Git и GitHub CLI готовы"
echo ""

# Получение информации о репозитории
read -p "Введите название репозитория (например: relaticle-crm): " REPO_NAME
if [ -z "$REPO_NAME" ]; then
    REPO_NAME="relaticle-crm"
fi

read -p "Сделать репозиторий приватным? (y/n): " PRIVATE_REPO
if [[ "$PRIVATE_REPO" =~ ^[Yy]$ ]]; then
    PRIVATE_FLAG="--private"
else
    PRIVATE_FLAG="--public"
fi

echo ""
echo "📋 Создание репозитория: $REPO_NAME"
echo "   Видимость: $([ "$PRIVATE_FLAG" = "--private" ] && echo "приватный" || echo "публичный")"
echo ""

# Создание репозитория
REPO_URL=$(gh repo create "$REPO_NAME" "$PRIVATE_FLAG" --source=. --remote=origin --push 2>/dev/null || echo "")

if [ -z "$REPO_URL" ]; then
    echo "❌ Не удалось создать репозиторий автоматически"
    echo ""
    echo "Создайте репозиторий вручную на GitHub:"
    echo "1. Перейдите на https://github.com/new"
    echo "2. Название: $REPO_NAME"
    echo "3. $([ "$PRIVATE_FLAG" = "--private" ] && echo "Сделайте приватным" || echo "Оставьте публичным")"
    echo "4. Создайте репозиторий"
    echo ""
    read -p "Введите URL созданного репозитория: " REPO_URL
    if [ -z "$REPO_URL" ]; then
        echo "❌ URL репозитория обязателен"
        exit 1
    fi

    # Добавление remote и push
    git remote add origin "$REPO_URL" 2>/dev/null || git remote set-url origin "$REPO_URL"
    git branch -M main
    git push -u origin main
fi

echo "✅ Репозиторий создан: $REPO_URL"
echo ""

# Теперь запускаем развертывание
echo "🚀 Запуск автоматического развертывания..."
echo ""

# Устанавливаем переменные окружения для развертывания
export YANDEX_GPT_API_KEY="${YANDEX_GPT_API_KEY:-YOUR_YANDEX_GPT_API_KEY}"
export YANDEX_FOLDER_ID="${YANDEX_FOLDER_ID:-YOUR_YANDEX_FOLDER_ID}"
export ADMIN_EMAIL="${ADMIN_EMAIL:-YOUR_ADMIN_EMAIL}"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-YOUR_ADMIN_PASSWORD}"

./deploy_remote.sh "$REPO_URL"

echo ""
echo "=========================================="
echo "🎉 Все готово!"
echo "=========================================="
echo ""
echo "🌐 Ваша CRM доступна по адресу:"
echo "   http://lizon0707.fvds.ru/sysadmin"
echo ""
echo "🔐 Учетные данные:"
echo "   Email: anton.kaufmann95@gmail.com"
echo "   Пароль: Starten01!"
echo ""
echo "📊 Репозиторий:"
echo "   $REPO_URL"
echo ""

