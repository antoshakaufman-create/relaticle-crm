#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Checker ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no check_people_script.php $USER@$IP:$REMOTE_PATH/check_people_script.php

echo "=== Running Checker ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 check_people_script.php"
