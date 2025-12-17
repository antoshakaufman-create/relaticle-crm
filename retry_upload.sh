#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Retrying Upload ViewCompany.php ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/CompanyResource/Pages/ViewCompany.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/CompanyResource/Pages/

echo "Verify Upload..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && grep 'Legal Details' app/Filament/Resources/CompanyResource/Pages/ViewCompany.php"
