#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying UI Updates ==="

echo "Uploading Company Model..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Models/Company.php $USER@$IP:$REMOTE_PATH/app/Models/

echo "Uploading Company Resource..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/CompanyResource.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/

echo "Clearing Cache (Optional)..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan optimize:clear"

echo "=== Done! UI Updated ==="
