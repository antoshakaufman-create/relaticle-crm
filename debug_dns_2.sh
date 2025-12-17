#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Debugging Connectivity ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ping -c 2 yandex.ru"
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ping -c 2 google.com"
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "curl -I https://yandex.ru/search/xml"
