#!/bin/bash

echo "=== Server file content (AiImport.php lines 15-25) ==="
sed -n '15,25p' /var/www/relaticle/app/Filament/Pages/AiImport.php

echo ""
echo "=== Git status ==="
cd /var/www/relaticle
git status

echo ""
echo "=== Git log local vs remote ==="
git log -1 --oneline
git fetch origin
git log origin/main -1 --oneline
