#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Killing Old Processes ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "pkill -f 'artisan app:generate-smart-leads'"

echo "=== Starting Cosmetics Gen ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && nohup php8.5 artisan app:generate-smart-leads 'Косметика' > cosmo_gen.log 2>&1 &"

echo "=== Starting Retail Gen ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && nohup php8.5 artisan app:generate-smart-leads 'Ритейл' > retail_gen.log 2>&1 &"

echo "=== Started! ==="
