#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Debugging API Cloud DNS ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "nslookup api.cloud.yandex.net"
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ping -c 2 api.cloud.yandex.net"
