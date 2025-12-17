#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Killing Old Processes ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "pkill -f 'artisan app:generate-smart-leads'"

echo "=== Running Interactive Debug (Cosmetics) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && timeout 60s php8.5 artisan app:generate-smart-leads 'Косметика'"
