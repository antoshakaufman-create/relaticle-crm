#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Fixing Schema ==="

# 1. Rollback specific migration
echo "Rolling back previous migration..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate:rollback --path=database/migrations/2025_12_17_000000_add_dadata_light_columns_to_companies_table.php --force"

# 2. Upload updated migration
echo "Uploading fixed migration..."
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no database/migrations/2025_12_17_000000_add_dadata_light_columns_to_companies_table.php $USER@$IP:$REMOTE_PATH/database/migrations/

# 3. Run migration again
echo "Running fixed migration..."
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan migrate --path=database/migrations/2025_12_17_000000_add_dadata_light_columns_to_companies_table.php --force"

# 4. Run Enrichment (using the command we already uploaded)
echo "=== Running Enrichment ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:enrich-company-details"
