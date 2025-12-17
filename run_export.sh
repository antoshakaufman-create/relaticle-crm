#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Export Script ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no export_candidates.php $USER@$IP:$REMOTE_PATH/export_candidates.php

echo "=== Running Export ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 export_candidates.php"

echo "=== Downloading Candidates ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_PATH/candidates.json ./candidates.json
