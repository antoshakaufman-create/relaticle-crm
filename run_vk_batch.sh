#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying VK Finder Command ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/FindMissingVkLinks.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

echo "=== Running VK Finder ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan vk:find-missing"
