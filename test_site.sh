#!/bin/bash

# Простой скрипт для проверки сайта через curl

echo "=== Проверка доступности сайта ==="
echo ""

echo "1. HTTP (порт 80):"
curl -I http://crmvirtu.ru 2>&1 | head -n 10

echo ""
echo "2. HTTPS (порт 443):"
curl -I https://crmvirtu.ru 2>&1 | head -n 10

echo ""
echo "3. Пробуем получить страницу (первые 200 символов):"
curl -s https://crmvirtu.ru 2>&1 | head -c 200

echo ""
echo ""
echo "=== Конец проверки ==="
