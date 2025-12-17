#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking 2gis Duplicates ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT id, name, legal_name, inn, created_at FROM companies WHERE name LIKE \"%2gis%\" OR name LIKE \"%2гис%\";'"
