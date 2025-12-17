#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Migration ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no database/migrations/2025_12_16_233000_add_smm_date_to_companies.php $USER@$IP:$REMOTE_PATH/database/migrations/

echo "=== Running Migration ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate --force"
