#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Syncing Logic to Production ==="

# 1. Upload modified Service
echo "Uploading VkActionService.php..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Services/VkActionService.php $USER@$IP:$REMOTE_PATH/app/Services/

# 2. Upload new Commands
echo "Uploading Commands..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/EnrichVkLinks.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/ExportVkManagers.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/ExportVkAnalysis.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

echo "=== Running Enrichment (Find Links) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:enrich-vk-links"

echo "=== Running Export (Find Managers) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:export-vk-managers"

echo "=== Downloading Report ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no $USER@$IP:$REMOTE_PATH/vk_managers.xlsx ./remote_vk_managers_prod.xlsx

echo "=== Done! Saved to ./remote_vk_managers_prod.xlsx ==="
