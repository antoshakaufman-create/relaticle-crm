#!/bin/bash

# Скрипт для удаленного запуска развертывания на crmvirtu.ru
# IP сервера: 83.220.175.224

SERVER_IP="83.220.175.224"
USER="root"
SCRIPT_NAME="deploy_crmvirtu.sh"

echo "=== Запуск удаленного развертывания на $SERVER_IP ==="
echo "Для продолжения может потребоваться ввести пароль от сервера."

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# 1. Копируем скрипт на сервер
echo "[1/3] Копирование скрипта развертывания на сервер..."
scp "$SCRIPT_DIR/$SCRIPT_NAME" $USER@$SERVER_IP:/tmp/$SCRIPT_NAME


if [ $? -ne 0 ]; then
    echo "Ошибка копирования файла. Проверьте правильность пароля и доступа."
    exit 1
fi

# 2. Делаем скрипт исполняемым
echo "[2/3] Настройка прав доступа..."
ssh $USER@$SERVER_IP "chmod +x /tmp/$SCRIPT_NAME"

# 3. Запускаем скрипт
echo "[3/3] Запуск процесса развертывания..."
echo "----------------------------------------"
ssh $USER@$SERVER_IP "mv /tmp/$SCRIPT_NAME /root/$SCRIPT_NAME && /root/$SCRIPT_NAME"

echo "----------------------------------------"
echo "=== Готово ==="
