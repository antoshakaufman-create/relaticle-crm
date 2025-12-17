#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Fixing Migration Conflict ==="
# Move problematic migration out of the way
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "mv $REMOTE_PATH/database/migrations/2025_12_13_190000_add_source_to_companies.php $REMOTE_PATH/database/migrations/2025_12_13_190000_add_source_to_companies.php.bak"

echo "=== Running Migration Again ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate --force"
