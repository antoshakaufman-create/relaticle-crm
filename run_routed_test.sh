#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Uploading Routed Search Test ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no test_yandex_search_routed.php $USER@$IP:$REMOTE_PATH/test_yandex_search_routed.php

echo "=== Running Routed Test ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker test_yandex_search_routed.php"
