#!/bin/bash

echo "=== AiImport.php lines 16-25 on SERVER ==="
sed -n '16,25p' /var/www/relaticle/app/Filament/Pages/AiImport.php

echo ""
echo "=== Git status ==="
cd /var/www/relaticle
git log -1 --oneline

echo ""
echo "=== Latest remote commit ==="
git fetch origin 2>/dev/null
git log origin/main -1 --oneline
