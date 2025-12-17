#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Verification Script ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no verify_dashboard_widgets.php $USER@$IP:$REMOTE_PATH/verify_dashboard_widgets.php

echo "=== Running Verification ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 verify_dashboard_widgets.php"
