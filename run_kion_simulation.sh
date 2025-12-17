#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Reloading PHP-FPM (Best Effort) ==="
# Try common service names
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "service php8.5-fpm reload || service php8.3-fpm reload || systemctl reload php8.5-fpm"

echo "=== Uploading Simulation Script ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no simulate_kion_button.php $USER@$IP:$REMOTE_PATH/simulate_kion_button.php

echo "=== Running Simulation ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker simulate_kion_button.php"
