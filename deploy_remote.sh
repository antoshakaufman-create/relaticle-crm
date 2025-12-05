#!/bin/bash

# Скрипт для автоматического развертывания Relaticle CRM на FirstVDS
# Запустите этот скрипт локально

set -e

echo "=========================================="
echo "🚀 Автоматическое развертывание Relaticle CRM"
echo "=========================================="
echo ""

# Конфигурация
SERVER_HOST="83.220.175.224"
SERVER_USER="root"
SERVER_PASSWORD="Starten01!"
DOMAIN="lizon0707.fvds.ru"
# REPO_URL - будет передан как аргумент или определен автоматически
REPO_URL="${1:-}"

if [ -z "$REPO_URL" ]; then
    echo "❌ Ошибка: Не указан URL репозитория"
    echo ""
    echo "Использование:"
    echo "  ./deploy_remote.sh <URL_РЕПОЗИТОРИЯ>"
    echo ""
    echo "Пример:"
    echo "  ./deploy_remote.sh https://github.com/user/relaticle.git"
    echo ""
    exit 1
fi

echo "📋 Параметры:"
echo "   Сервер: $SERVER_HOST"
echo "   Пользователь: $SERVER_USER"
echo "   Домен: $DOMAIN"
echo "   Репозиторий: $REPO_URL"
echo ""

# Создание expect скрипта для автоматического ввода пароля
cat > /tmp/deploy_expect.sh << 'EOF'
#!/usr/bin/expect -f

set SERVER_HOST [lindex $argv 0]
set SERVER_USER [lindex $argv 1]
set SERVER_PASSWORD [lindex $argv 2]
set REPO_URL [lindex $argv 3]
set DOMAIN [lindex $argv 4]
set ADMIN_EMAIL [lindex $argv 5]
set ADMIN_PASSWORD [lindex $argv 6]
set YANDEX_GPT_API_KEY [lindex $argv 7]
set YANDEX_FOLDER_ID [lindex $argv 8]

# Подключение к серверу
spawn ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_HOST

# Ожидание запроса пароля
expect "password:"
send "$SERVER_PASSWORD\r"

# Ожидание приглашения командной строки
expect "$ "

# Установка git если не установлен
send "apt update && apt install -y git\r"
set timeout 300
expect {
    "Do you want to continue?" {
        send "Y\r"
        expect "$ "
    }
    "$ " {}
    timeout {}
}
set timeout 30

# Создание директории и клонирование репозитория
send "cd /var/www\r"
expect "$ "
send "rm -rf relaticle\r"
expect "$ "
send "git clone $REPO_URL relaticle\r"
expect {
    "Cloning" {
        expect "$ "
    }
    "$ " {}
    timeout {}
}
send "cd relaticle\r"
expect "$ "

# Проверка наличия скрипта развертывания и установка переменных окружения
send "export ADMIN_NAME=\"Администратор\"\r"
expect "$ "
send "export ADMIN_EMAIL=\"$ADMIN_EMAIL\"\r"
expect "$ "
send "export ADMIN_PASSWORD=\"$ADMIN_PASSWORD\"\r"
expect "$ "
send "export YANDEX_GPT_API_KEY=\"$YANDEX_GPT_API_KEY\"\r"
expect "$ "
send "export YANDEX_FOLDER_ID=\"$YANDEX_FOLDER_ID\"\r"
expect "$ "
send "export DB_TYPE=sqlite\r"
expect "$ "

# Запуск развертывания
send "if [ -f run_deployment.sh ]; then chmod +x run_deployment.sh && ./run_deployment.sh; elif [ -f deploy.sh ]; then chmod +x deploy.sh && ./deploy.sh; else echo 'Скрипт развертывания не найден'; fi\r"

# Ожидание завершения (может занять время)
set timeout 1200
expect {
    "Развертывание завершено!" {
        puts "✅ Развертывание завершено успешно!"
    }
    timeout {
        puts "⏰ Развертывание занимает больше времени, чем ожидалось"
        puts "Проверьте статус на сервере вручную"
    }
    eof {
        puts "❌ Соединение прервано"
    }
}

# Выход
send "exit\r"
expect eof
EOF

chmod +x /tmp/deploy_expect.sh

# Проверка наличия expect
if ! command -v expect &> /dev/null; then
    echo "❌ Expect не установлен. Устанавливаю..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install expect
    else
        echo "Пожалуйста, установите expect: apt install expect"
        exit 1
    fi
fi

echo "🔗 Подключение к серверу и запуск развертывания..."
echo "Это может занять 10-15 минут..."
echo ""

# Получение переменных окружения
ADMIN_EMAIL="${ADMIN_EMAIL:-anton.kaufmann95@gmail.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Starten01!}"
YANDEX_GPT_API_KEY="${YANDEX_GPT_API_KEY:-AQVN3f76xWgppmVEMeZqPTsUpFG7UzH0CNTWg_b8}"
YANDEX_FOLDER_ID="${YANDEX_FOLDER_ID:-b1gn3qao39gb9uecn2c2}"

# Запуск expect скрипта
/tmp/deploy_expect.sh "$SERVER_HOST" "$SERVER_USER" "$SERVER_PASSWORD" "$REPO_URL" "$DOMAIN" "$ADMIN_EMAIL" "$ADMIN_PASSWORD" "$YANDEX_GPT_API_KEY" "$YANDEX_FOLDER_ID"

# Очистка
rm -f /tmp/deploy_expect.sh

echo ""
echo "=========================================="
echo "🏁 Процесс завершен"
echo "=========================================="
echo ""
echo "📋 Проверьте результат:"
echo ""
echo "1. Откройте в браузере:"
echo "   http://$DOMAIN/sysadmin"
echo ""
echo "2. Учетные данные администратора:"
echo "   Email: anton.kaufmann95@gmail.com"
echo "   Пароль: Starten01!"
echo ""
echo "3. Если развертывание не завершилось автоматически,"
echo "   подключитесь к серверу и проверьте статус:"
echo "   ssh $SERVER_USER@$SERVER_HOST"
echo "   cd /var/www/relaticle"
echo "   ./run_deployment.sh"
echo ""

