#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Dumping Config ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no dump_config.php $USER@$IP:$REMOTE_PATH/dump_config.php
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker dump_config.php"
