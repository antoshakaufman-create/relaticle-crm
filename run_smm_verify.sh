#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Verifying SMM Analysis ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no verify_smm.php $USER@$IP:$REMOTE_PATH/verify_smm.php
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 verify_smm.php"
