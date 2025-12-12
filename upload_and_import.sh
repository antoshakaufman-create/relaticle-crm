#!/bin/bash

# 1. Copy CSV to server
echo "=== Copying CSV to server ==="
sshpass -p "Starten01!" scp -o StrictHostKeyChecking=no "/Users/antonkaufmann/relaticle-crm-1/Клиенты-2.csv" "root@83.220.175.224:/var/www/relaticle/Клиенты-2.csv"

if [ $? -ne 0 ]; then
    echo "Failed to copy CSV"
    exit 1
fi

echo "CSV copied successfully!"

# 2. Copy import script
echo "=== Copying import script ==="
sshpass -p "Starten01!" scp -o StrictHostKeyChecking=no "/Users/antonkaufmann/relaticle-crm-1/import_clients.sh" "root@83.220.175.224:/var/www/relaticle/import_clients.sh"

# 3. Run import
echo "=== Running import ==="
sshpass -p "Starten01!" ssh -o StrictHostKeyChecking=no "root@83.220.175.224" "chmod +x /var/www/relaticle/import_clients.sh && cd /var/www/relaticle && bash import_clients.sh"

echo "=== DONE ==="
