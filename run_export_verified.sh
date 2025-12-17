#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Exporting Verified Emails ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no export_verified_emails.php $USER@$IP:$REMOTE_PATH/export_verified_emails.php
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 export_verified_emails.php"
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_PATH/mosint_candidates.json ./mosint_candidates.json
