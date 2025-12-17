#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Schema Update & Re-Importer ==="

# 1. Upload Migration
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no database/migrations/2025_12_17_151952_add_osint_fields_to_people_table.php $USER@$IP:$REMOTE_PATH/database/migrations/

# 2. Upload Model & Resource
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Models/People.php $USER@$IP:$REMOTE_PATH/app/Models/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/PeopleResource.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/

# 3. Upload Importer & Data
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no reimport_osint.php $USER@$IP:$REMOTE_PATH/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no mosint_rich_data.json $USER@$IP:$REMOTE_PATH/

echo "=== Running Migration & Import on Server ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate --force && php8.5 reimport_osint.php"
