#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Process List ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ps aux | grep artisan"

echo "=== Cosmetics Log (Last 50 lines) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 50 $REMOTE_PATH/cosmo_gen.log"

echo "=== Retail Log (Last 50 lines) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "tail -n 50 $REMOTE_PATH/retail_gen.log"
