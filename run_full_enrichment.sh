#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Full Enrichment Logic ==="

# 1. Upload Migration
echo "Uploading Migration..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no database/migrations/2025_12_17_000000_add_dadata_light_columns_to_companies_table.php $USER@$IP:$REMOTE_PATH/database/migrations/

# 2. Upload Command
echo "Uploading Command..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/EnrichCompanyDetails.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/

# 3. Run Migration
echo "=== Running Database Migration ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate --path=database/migrations/2025_12_17_000000_add_dadata_light_columns_to_companies_table.php --force"

# 4. Run Enrichment
echo "=== Running Enrichment ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:enrich-company-details"

echo "=== Done! Database Enriched ==="
