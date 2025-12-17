#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Starting Retail Lead Gen ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && nohup php8.5 artisan app:generate-smart-leads 'Ритейл' > retail_lead.log 2>&1 &"
