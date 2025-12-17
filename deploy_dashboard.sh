#!/bin/bash
IP="83.220.175.224"
USER="root"
PASS="Starten01!"
REMOTE_PATH="/var/www/relaticle"

echo "=== Deploying Dashboard Widgets ==="
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Widgets/DashboardStatsOverview.php $USER@$IP:$REMOTE_PATH/app/Filament/Widgets/DashboardStatsOverview.php
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Widgets/SmmPerformanceChart.php $USER@$IP:$REMOTE_PATH/app/Filament/Widgets/SmmPerformanceChart.php
sshpass -p "$PASS" scp -o StrictHostKeyChecking=no app/Filament/Widgets/LeadCategoryChart.php $USER@$IP:$REMOTE_PATH/app/Filament/Widgets/LeadCategoryChart.php

echo "=== Deployment Complete ==="
echo "Check /admin URL manually."
