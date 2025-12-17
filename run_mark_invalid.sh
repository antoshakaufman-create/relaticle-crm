#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Invalid Marker Script ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no mark_invalid_mosint.php $USER@$IP:$REMOTE_PATH/
# Data file mosint_rich_data.json should already be there from previous step.

echo "=== Running Mark Invalid on Server ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 mark_invalid_mosint.php"
