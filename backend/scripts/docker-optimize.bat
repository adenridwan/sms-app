@echo off
REM ===========================================
REM Script: docker-optimize.bat
REM Deskripsi: Optimasi performa aplikasi di Docker (Windows)
REM Penggunaan: scripts\docker-optimize.bat
REM ===========================================

setlocal enabledelayedexpansion

set CONTAINER=sms_php
set DB_CONTAINER=sms_postgres

echo ==========================================
echo SMS App - Docker Optimization Script
echo ==========================================
echo.

REM Cek container running
docker ps --format "{{.Names}}" | findstr /C:"%CONTAINER%" >nul 2>&1
if errorlevel 1 (
    echo Error: Container %CONTAINER% tidak berjalan!
    exit /b 1
)

echo Step 1: Clear semua cache...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan optimize:clear

echo.
echo Step 2: Reset permission cache (Spatie)...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan permission:cache-reset

echo.
echo Step 3: Rebuild optimization cache...
echo ------------------------------------------
docker exec -it %CONTAINER% php artisan optimize

echo.
echo Step 4: Analyze PostgreSQL tables...
echo ------------------------------------------
docker ps --format "{{.Names}}" | findstr /C:"%DB_CONTAINER%" >nul 2>&1
if errorlevel 1 (
    echo    Skipped - container database tidak berjalan
) else (
    docker exec -it %DB_CONTAINER% psql -U sms_user -d sms_db -c "ANALYZE;"
)

echo.
echo ==========================================
echo Optimization selesai!
echo ==========================================

endlocal
