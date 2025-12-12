#!/bin/bash

echo "=== Checking ACTUAL AiImport.php file on server (lines 1-35) ==="
head -n 35 /var/www/relaticle/app/Filament/Pages/AiImport.php

echo ""
echo "=== Git status ==="
cd /var/www/relaticle
git status

echo ""
echo "=== Current git commit ==="
git log -1 --oneline

echo ""
echo "=== GitHub latest commit ==="
git fetch origin
git log origin/main -1 --oneline
