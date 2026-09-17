@echo off
echo ==========================================
echo   SMS Absensi - Build APK
echo ==========================================
echo.

cd /d "%~dp0"

echo Cleaning previous builds...
call flutter clean

echo.
echo Getting dependencies...
call flutter pub get

echo.
echo Building release APK...
call flutter build apk --release --android-skip-build-dependency-validation

echo.
if exist "build\app\outputs\flutter-apk\app-release.apk" (
    echo ==========================================
    echo   BUILD SUCCESS!
    echo ==========================================
    echo APK location: build\app\outputs\flutter-apk\app-release.apk
    echo.

    REM Copy to root for easy access
    copy /Y "build\app\outputs\flutter-apk\app-release.apk" "sms-absensi-release.apk" >nul
    echo Also copied to: sms-absensi-release.apk
    echo.
    echo File size:
    for %%A in (sms-absensi-release.apk) do echo   %%~zA bytes (%%~nxA^)
) else (
    echo ==========================================
    echo   BUILD FAILED!
    echo ==========================================
    echo Check the error messages above.
)

echo.
pause
