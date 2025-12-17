#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Aggressive Cache Clearing ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan optimize:clear && php8.5 artisan icon:clear && php8.5 artisan filament:optimize-clear && rm -rf bootstrap/cache/*"

echo "=== Done ==="
