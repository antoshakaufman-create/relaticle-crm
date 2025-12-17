#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Uploading Debug Script ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no debug_ural.php $USER@$IP:$REMOTE_PATH/debug_ural.php

echo "=== Running Debug Script ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker debug_ural.php"
