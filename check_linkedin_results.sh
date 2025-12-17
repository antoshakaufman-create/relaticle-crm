#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== LinkedIn / Employee Scan Results ==="
sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no $USER@$IP "cd $REMOTE_PATH && php8.5 artisan tinker --execute=\"echo App\Models\People::where('creation_source', 'AI_GENERATED')->get()->map(fn(\$p) => \$p->company->name . ': ' . \$p->name . ' (' . \$p->position . ')')->implode(PHP_EOL);\""
