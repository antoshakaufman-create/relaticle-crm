#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Verification Tool ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Console/Commands/VerifyEmail.php $USER@$IP:$REMOTE_PATH/app/Console/Commands/VerifyEmail.php

echo "=== Test 1: Real Email (Ralf Ringer) ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:verify-email andrey.berezhnoy@ralf.ru"

echo -e "\n=== Test 2: Fake Email ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan app:verify-email fake_user_12345@ralf.ru"
