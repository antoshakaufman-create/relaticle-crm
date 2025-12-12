#!/bin/bash

echo "=== Current AiImport.php file on server ===" 
cat /var/www/relaticle/app/Filament/Pages/AiImport.php

echo ""
echo "=== Last 50 lines of Laravel log ==="
tail -n 50 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Check file permissions ==="
ls -la /var/www/relaticle/app/Filament/Pages/AiImport.php
ls -la /var/www/relaticle/resources/views/filament/pages/ai-import.blade.php
