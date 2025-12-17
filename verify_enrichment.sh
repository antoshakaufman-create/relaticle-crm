#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Verifying Data ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT id, name, legal_name, inn, management_name, status, address_line_1 FROM companies WHERE inn IS NOT NULL LIMIT 5;'"
