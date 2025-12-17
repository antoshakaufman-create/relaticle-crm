#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking Remote File for 'mosint_status' ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "grep -c 'mosint_status' $REMOTE_PATH/app/Filament/Resources/PeopleResource.php"

echo "=== Checking Remote File for 'has_ip_org' ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "grep -c 'has_ip_org' $REMOTE_PATH/app/Filament/Resources/PeopleResource.php"

echo "=== Clearing Cache ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan optimize:clear && php8.5 artisan filament:optimize-clear"
