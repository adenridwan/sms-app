@echo off
REM Task Scheduler tidak selalu mewarisi PATH sesi interaktif (mis. PHP dari
REM Laragon) — pakai path lengkap ke php.exe supaya tidak silently gagal.
cd /d "%~dp0.."
"C:\laragon\bin\php\php-8.4.23-Win32-vs17-x64\php.exe" artisan backup:run >> storage\logs\backup-task.log 2>&1
