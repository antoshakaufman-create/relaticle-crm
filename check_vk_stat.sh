#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking VK Link Stats ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite \"SELECT COUNT(*) FROM companies WHERE vk_url IS NULL OR vk_url = '';\""
echo "=== Checking for Literal Spaces ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite \"SELECT id, name, vk_url FROM companies WHERE vk_url LIKE '% %';\""
