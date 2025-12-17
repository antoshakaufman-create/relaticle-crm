#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying VK Service Updates ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Services/VkActionService.php $USER@$IP:$REMOTE_PATH/app/Services/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/CompanyResource/Pages/ViewCompany.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/CompanyResource/Pages/

echo "=== Done! ==="
