#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Last 50 lines of Laravel Log ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 50 $REMOTE_PATH/storage/logs/laravel.log"
