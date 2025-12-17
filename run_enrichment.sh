#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Command ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/EnrichEmployees.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/EnrichEmployees.php

echo "=== Running Enrichment ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:enrich-employees"
