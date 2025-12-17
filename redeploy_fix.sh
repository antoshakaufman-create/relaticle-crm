#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Fix ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Services/LeadGeneration/EmailDiscoveryService.php $USER@$IP:$REMOTE_PATH/app/Services/LeadGeneration/EmailDiscoveryService.php

echo "=== Running Enrichment (Attempt 2) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:enrich-emails"
