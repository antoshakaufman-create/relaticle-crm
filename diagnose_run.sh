#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== DB Count ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT industry, COUNT(*) FROM companies WHERE creation_source = \"AI_GENERATED\" GROUP BY industry;'"

echo "=== Process Check ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ps aux | grep artisan"
