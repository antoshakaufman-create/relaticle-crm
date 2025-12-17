#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Uploading Verified Results ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no verified_candidates.json $USER@$IP:$REMOTE_PATH/verified_candidates.json
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no import_verified.php $USER@$IP:$REMOTE_PATH/import_verified.php

echo "=== Running Import ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 import_verified.php"
