@echo off
REM ============================================================
REM  PHILSURIN — Set Up Permanent 24/7 Startup (Windows)
REM  Run this script once to configure auto-start.
REM ============================================================

setlocal enabledelayedexpansion

echo [PHILSURIN] Configuring permanent 24/7 startup...
echo.

set "APP_DIR=%~dp0"
echo [PHILSURIN] App directory: %APP_DIR%

REM --- Method A: Startup Folder ---
set "STARTUP_PATH=%USERPROFILE%\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup"
if not exist "%STARTUP_PATH%" mkdir "%STARTUP_PATH%"

echo [PHILSURIN] Method A: Adding to Startup folder...
copy /Y "%APP_DIR%RUN-PERMANENT.bat" "%STARTUP_PATH%" >nul 2>&1
if exist "%STARTUP_PATH%RUN-PERMANENT.bat" (
    echo [OK] Added RUN-PERMANENT.bat to Startup folder
) else (
    echo [WARN] Could not add to Startup folder
)

REM --- Method B: Registry Run Key ---
echo [PHILSURIN] Method B: Adding to Registry Run key...
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "Philsurin247" /t REG_SZ /d "\"%APP_DIR%RUN-PERMANENT.bat\"" /f >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Added to Registry Run key
) else (
    echo [WARN] Could not add to Registry
)

echo.
echo [PHILSURIN] ===========================
echo [PHILSURIN]  Setup complete!
echo [PHILSURIN]  1. Restart your computer to test auto-start
echo [PHILSURIN]  2. Verify: http://localhost:80 (Apache)
echo [PHILSURIN]  3. Verify: http://localhost:3000 (Proxy)
echo [PHILSURIN]  4. Verify: lnmmkbpg-3000.asse.devtunnels.ms (Tunnel)
echo [PHILSURIN] ===========================
echo.
pause
