#!/bin/bash

echo "🚀 Memulai Deployment Laravel Filament di Port 8585..."

git pull origin main

docker-compose up -d --build

docker exec filament_v4_app php artisan migrate --force
docker exec filament_v4_app php artisan optimize

echo "✅ Deployment Berhasil! Akses di http://ip-vps-kamu:8585"