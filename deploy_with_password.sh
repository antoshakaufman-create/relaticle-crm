#!/bin/bash

# Fixed upload and run script
SERVER_IP="83.220.175.224"
USER="root"
PASSWORD="Starten01!"
SCRIPT_NAME="$1"

echo "=== Deploying $SCRIPT_NAME to $SERVER_IP ==="

# 1. Copy script
echo "[1/3] Copying script..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no "$SCRIPT_NAME" "$USER@$SERVER_IP:/tmp/$SCRIPT_NAME"

if [ $? -ne 0 ]; then
    echo "Failed to copy"
    exit 1
fi

# 2. Set permissions
echo "[2/3] Setting permissions..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$USER@$SERVER_IP" "chmod +x /tmp/$SCRIPT_NAME"

# 3. Run
echo "[3/3] Running..."
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$USER@$SERVER_IP" "mv /tmp/$SCRIPT_NAME /root/$SCRIPT_NAME && /root/$SCRIPT_NAME"

echo "=== Done ==="
