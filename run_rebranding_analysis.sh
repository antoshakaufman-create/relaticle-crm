#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Analysis Logic to Production ==="

# 1. Upload Command
echo "Uploading AnalyzeContacts.php..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/AnalyzeContacts.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

echo "=== Running Analysis (This may take a while) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:analyze-contacts"

echo "=== Downloading Report ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_PATH/rebranding_report.csv ./rebranding_report.csv

echo "=== Done! Saved to ./rebranding_report.csv ==="
