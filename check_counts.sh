#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking DB Counts ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT \"Companies (Total):\", COUNT(*) FROM companies; SELECT \"Companies (Deleted):\", COUNT(*) FROM companies WHERE deleted_at IS NOT NULL; SELECT \"People (Total):\", COUNT(*) FROM people;'"
