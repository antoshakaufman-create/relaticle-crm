#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Enum ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Enums/CreationSource.php $USER@$IP:$REMOTE_PATH/app/Enums/CreationSource.php
