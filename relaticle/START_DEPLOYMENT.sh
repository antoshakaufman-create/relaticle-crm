#!/bin/bash

# Финальный скрипт для запуска развертывания
# Этот скрипт нужно запустить на сервере FirstVDS

set -e

echo "=========================================="
echo "🚀 Relaticle CRM - Развертывание"
echo "=========================================="
echo ""

# Данные для развертывания
export SSH_HOST="83.220.175.224"
export SSH_USER="root"
export DOMAIN="lizon0707.fvds.ru"
export DB_TYPE="sqlite"
export ADMIN_NAME="Администратор"
export ADMIN_EMAIL="YOUR_ADMIN_EMAIL"
export ADMIN_PASSWORD="YOUR_ADMIN_PASSWORD"
export YANDEX_GPT_API_KEY="YOUR_YANDEX_GPT_API_KEY"
export YANDEX_FOLDER_ID="YOUR_YANDEX_FOLDER_ID"

echo "📋 Параметры развертывания:"
echo "   - Сервер: $SSH_HOST"
echo "   - Домен: $DOMAIN"
echo "   - База данных: $DB_TYPE"
echo "   - Администратор: $ADMIN_EMAIL"
echo ""

read -p "Продолжить развертывание? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Развертывание отменено."
    exit 1
fi

echo ""
echo "=========================================="
echo "📝 Инструкции для развертывания:"
echo "=========================================="
echo ""
echo "1. Подключитесь к серверу:"
echo "   ssh $SSH_USER@$SSH_HOST"
echo "   Пароль: YOUR_ADMIN_PASSWORD"
echo ""
echo "2. Выполните на сервере:"
echo "   cd /var/www"
echo "   git clone <ваш-репозиторий> relaticle"
echo "   cd relaticle"
echo "   chmod +x deploy.sh"
echo ""
echo "3. Запустите развертывание:"
echo "   ADMIN_NAME=\"$ADMIN_NAME\" \\"
echo "   ADMIN_EMAIL=\"$ADMIN_EMAIL\" \\"
echo "   ADMIN_PASSWORD=\"$ADMIN_PASSWORD\" \\"
echo "   YANDEX_GPT_API_KEY=\"$YANDEX_GPT_API_KEY\" \\"
echo "   YANDEX_FOLDER_ID=\"$YANDEX_FOLDER_ID\" \\"
echo "   DB_TYPE=$DB_TYPE \\"
echo "   ./deploy.sh"
echo ""
echo "4. После завершения откройте:"
echo "   http://$DOMAIN/sysadmin"
echo ""
echo "=========================================="
echo "✅ Готово! Следуйте инструкциям выше."
echo "=========================================="

