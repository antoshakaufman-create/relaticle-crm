#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_FILE="/var/www/relaticle/vk_managers.xlsx"
LOCAL_FILE="vk_managers_final.xlsx"

echo "=== Checking if remote analysis is complete ==="

# Check if process is still running
if sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "ps aux | grep 'artisan app:' | grep -v grep"; then
    echo ""
    echo "⚠️  PROCESS IS STILL RUNNING ON SERVER ⚠️"
    echo "The file is not ready yet. Please try again in 5-10 minutes."
    exit 1
fi

echo "Process seems finished. Attempting download..."

sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_FILE ./$LOCAL_FILE

if [ $? -eq 0 ]; then
    echo "✅ Success! File saved to: $(pwd)/$LOCAL_FILE"
    echo "You can now open $LOCAL_FILE in Excel."
else
    echo "❌ Could not download file. Maybe it hasn't been generated yet?"
fi
