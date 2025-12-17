#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking Data for 353 ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT legal_name, management_name, address_line_1, status FROM companies WHERE id=353;'"
