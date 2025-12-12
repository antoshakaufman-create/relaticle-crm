#!/bin/bash

# Скрипт для развёртывания с использованием sshpass
# Использование: ./deploy_with_password.sh

SCRIPT_NAME="${1:-deploy_crmvirtu.sh}"
SERVER_IP="83.220.175.224"
USER="root"
PASSWORD="Starten01!"

echo "=== Развёртывание$SCRIPT_NAME на сервер $SERVER_IP ===" 

# 1. Копирование скрипта
echo "[1/3] Копирование скрипта..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no "$SCRIPT_NAME" "$USER@$SERVER_IP:/tmp/$SCRIPT_NAME"

if [ $? -ne 0 ]; then
    echo "Ошибка копирования файла"
    exit 1
fi

# 2. Настройка прав
echo "[2/3] Настройка прав..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$USER@$SERVER_IP" "chmod +x /tmp/$SCRIPT_NAME"

# 3. Запуск
echo "[3/3] Запуск развёртывания..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$USER@$SERVER_IP" "mv /tmp/$SCRIPT_NAME /root/$SCRIPT_NAME && /root/$SCRIPT_NAME"

echo "=== Готово ==="
