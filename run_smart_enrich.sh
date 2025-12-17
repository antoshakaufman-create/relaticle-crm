#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Smart Enrich Command ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/SmartEnrichCompany.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/SmartEnrichCompany.php

echo "=== Running Smart Enrichment ==="
# Running without limit (it fetches ALL companies with missing INN)
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:smart-enrich"
