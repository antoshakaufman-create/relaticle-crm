#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Checking SMM Stats for Processed Companies ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && sqlite3 database/database.sqlite 'SELECT id, name, vk_url, smm_analysis, er_score, posts_per_month FROM companies WHERE vk_url IS NOT NULL ORDER BY smm_analysis_date DESC LIMIT 5;'"
