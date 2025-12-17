#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Peeking Log ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 20 $REMOTE_PATH/debug_lead.log"
