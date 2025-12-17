#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Cosmetics Log ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 10 $REMOTE_PATH/cosmo_gen.log"

echo "=== Retail Log ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 10 $REMOTE_PATH/retail_gen.log"
