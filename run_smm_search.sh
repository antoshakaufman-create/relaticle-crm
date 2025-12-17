#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying SMM Lead Search ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/FindSmmLeads.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

echo "=== Running Search ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:find-smm-leads"

echo "=== Downloading Report ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_PATH/smm_leads.csv ./smm_leads.csv

echo "=== Done! ==="
