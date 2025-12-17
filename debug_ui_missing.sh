#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== 1. Checking Data for UniPro ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite \"SELECT id, name, legal_name, inn FROM companies WHERE name LIKE '%УниПро%';\""

echo "=== 2. Checking File on Server ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && grep 'Legal Details' app/Filament/Resources/CompanyResource.php"
