#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Debug VK Scrape: teana-labs.ru ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker --execute=\"echo resolve(App\Services\VkActionService::class)->findGroup('Teana', 'https://teana-labs.ru', 'ТЕАНА', 'Москва');\""
