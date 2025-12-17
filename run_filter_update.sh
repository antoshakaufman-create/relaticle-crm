#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying PeopleResource with Filters ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Resources/PeopleResource.php $USER@$IP:$REMOTE_PATH/app/Filament/Resources/
echo "Done."
