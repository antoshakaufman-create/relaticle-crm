#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying VK Updates & Clearing Cache ==="

# 1. Upload Files
echo "Uploading files..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Services/VkActionService.php $USER@$IP:$REMOTE_PATH/app/Services/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/CompanyResource/Pages/ViewCompany.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/CompanyResource/Pages/

# 2. Clear Cache
echo "Clearing caches..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan optimize:clear"
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan filament:optimize-clear"

# 3. Reload PHP-FPM (Try to reload if possible to flush OpCache completely)
# Trying generic service reload, might fail if not sudo or different service name.
# sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "service php8.5-fpm reload" 

echo "=== Deployment Complete ==="
