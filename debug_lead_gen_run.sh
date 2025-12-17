#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Debug Run ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:generate-smart-leads 'Косметика' > debug_lead.log 2>&1"

echo "=== Log Output ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cat $REMOTE_PATH/debug_lead.log"
