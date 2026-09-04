@echo off
REM ===========================================
REM Script: docker-migrate.bat
REM Deskripsi: Jalankan migration + optimasi cache di Docker (Windows)
REM Penggunaan: scripts\docker-migrate.bat
REM ===========================================

setlocal enabledelayedexpansion

set CONTAINER=sms_php

echo ==========================================
echo SMS App - Docker Migration Script
echo ==========================================
echo.

REM Cek container running
docker ps --format "{{.Names}}" | findstr /C:"%CONTAINER%" >nul 2>&1
if errorlevel 1 (
    echo Error: Container %CONTAINER% tidak berjalan!
    echo Jalankan: cd .. ^&^& docker-compose up -d
    exit /b 1
)

echo Step 1: Cek status migration...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan migrate:status

echo.
set /p confirm="Lanjutkan migration? (y/n): "
if /i not "%confirm%"=="y" (
    echo Migration dibatalkan.
    exit /b 0
)

echo.
echo Step 2: Jalankan migration...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan migrate --force

echo.
echo Step 3: Reset permission cache (Spatie)...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan permission:cache-reset

echo.
echo Step 4: Clear application cache...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan cache:clear
docker exec -it %CONTAINER% php artisan config:clear
docker exec -it %CONTAINER% php artisan view:clear

echo.
echo Step 5: Optimize untuk production...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan config:cache
docker exec -it %CONTAINER% php artisan route:cache
docker exec -it %CONTAINER% php artisan view:cache

echo.
echo ==========================================
echo Migration selesai!
echo ==========================================

endlocal
