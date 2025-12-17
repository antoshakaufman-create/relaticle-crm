#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Lead Gen Command ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/GenerateSmartLeads.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/GenerateSmartLeads.php
