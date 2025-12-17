#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking Export Command ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ls -l $REMOTE_PATH/app/Console/Commands/ExportVkAnalysis.php"
