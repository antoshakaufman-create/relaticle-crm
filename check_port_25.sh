#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"

echo "=== Checking Port 25 Outbound ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "nc -zv gmail-smtp-in.l.google.com 25; echo 'Exit Code: '$?"
