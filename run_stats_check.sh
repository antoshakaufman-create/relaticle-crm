#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Stats Check ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no check_enrich_stats.php $USER@$IP:$REMOTE_PATH/check_enrich_stats.php

echo "=== Running Stats Check ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 check_enrich_stats.php"
