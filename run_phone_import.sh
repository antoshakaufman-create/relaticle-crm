#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Phone Discovery Data & Importer ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no import_phones.php $USER@$IP:$REMOTE_PATH/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no phone_results.json $USER@$IP:$REMOTE_PATH/

echo "=== Running Import on Server ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 import_phones.php"
