#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Deduplication Command ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/DeduplicateCompanies.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

echo "=== Running Deduplication (Dry Run) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan deduplicate:companies --dry-run"

echo "=== Running Deduplication (REAL) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan deduplicate:companies"

echo "=== Verifying 2Gis Count ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT count(*) FROM companies WHERE name LIKE \"%2Gis%\" OR name LIKE \"%2gis%\";'"
