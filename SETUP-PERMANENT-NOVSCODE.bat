@echo off
REM ===========================================================================
REM  SETUP-PERMANENT-NOVSCODE.bat - run ONCE as Administrator (or normal user).
REM  Sets up fully-automatic hosting that survives reboots + works with
REM  VS Code CLOSED:
REM    1. Downloads devtunnel.exe into this folder (if missing)
REM    2. Registers Task Scheduler task "PHILSURIN-port3000" (backend keepalive)
REM    3. Registers Task Scheduler task "PHILSURIN-devtunnel" (tunnel host)
REM    4. Opens the login flow if needed
REM  After this, your links work 24/7 whenever the PC is on:
REM    https://lnmmkbpg-80.asse.devtunnels.ms    (Private - login required)
REM    https://lnmmkbpg-3000.asse.devtunnels.ms  (Public)
REM ===========================================================================
cd /d "%~dp0"
title PHILSURIN permanent setup - no VS Code needed

set TUNNEL_ID=lnmmkbpg

echo.
echo ================= PHILSURIN PERMANENT SETUP =================
echo  This replaces the VS Code Ports tab with a standalone host
echo  so ports 80 + 3000 stay forwarded even when VS Code is OFF.
echo =============================================================
echo.

REM --- 1. devtunnel.exe ---
if exist "%~dp0devtunnel.exe" (
  echo [OK] devtunnel.exe already present.
) else (
  where devtunnel >nul 2>&1
  if %errorlevel% equ 0 (
    echo [OK] devtunnel found in PATH.
  ) else (
    echo [1/4] Downloading devtunnel.exe (Windows x64) ...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -Uri 'https://aka.ms/TunnelsCliDownload/win-x64' -OutFile '%~dp0devtunnel.exe' -UseBasicParsing; Write-Host '[OK] downloaded' } catch { Write-Host ('[ERROR] download failed: ' + $_.Exception.Message); exit 1 }"
    if not exist "%~dp0devtunnel.exe" (
      echo.
      echo [ERROR] Could not download devtunnel.exe.
      echo Try manually:  winget install Microsoft.devtunnel
      echo Then re-run this setup.
      pause
      exit /b 1
    )
  )
)

REM --- pick exe ---
set DEVTUNNEL_EXE=%~dp0devtunnel.exe
if not exist "%DEVTUNNEL_EXE%" set DEVTUNNEL_EXE=devtunnel

REM --- 2. node proxy keepalive task (backend) ---
echo.
echo [2/4] Registering backend keepalive task "PHILSURIN-port3000" ...
schtasks /create /tn "PHILSURIN-port3000" /tr "\"%~dp0START-3000-PERMANENT.bat\"" /sc onlogon /rl limited /f >nul 2>&1
if %errorlevel% equ 0 ( echo [OK] PHILSURIN-port3000 registered. ) else ( echo [WARN] could not register - run as Administrator and retry. )

REM --- 3. tunnel host task ---
echo.
echo [3/4] Registering tunnel task "PHILSURIN-devtunnel" ...
schtasks /create /tn "PHILSURIN-devtunnel" /tr "\"%~dp0HOST-DEVTUNNEL-HEADLESS.bat\"" /sc onlogon /rl limited /f >nul 2>&1
if %errorlevel% equ 0 ( echo [OK] PHILSURIN-devtunnel registered. ) else ( echo [WARN] could not register - run as Administrator and retry. )

REM --- 4. login + first-time port config ---
echo.
echo [4/4] Login + port config (one time only) ...
"%DEVTUNNEL_EXE%" user show >nul 2>&1
if %errorlevel% neq 0 (
  echo A browser will open - login with the SAME account you use in VS Code.
  "%DEVTUNNEL_EXE%" user login
)
"%DEVTUNNEL_EXE%" port create %TUNNEL_ID% -p 80 --protocol http --description "Apache PHP" >nul 2>&1
"%DEVTUNNEL_EXE%" port create %TUNNEL_ID% -p 3000 --protocol http --description "Node proxy" >nul 2>&1
"%DEVTUNNEL_EXE%" access create %TUNNEL_ID% --port 3000 --anonymous >nul 2>&1
echo.
echo Current ports:
"%DEVTUNNEL_EXE%" port list %TUNNEL_ID%

echo.
echo ================= DONE =================
echo  1. Reboot to test: both Tasks run at logon automatically.
echo  2. Or go live NOW: double-click HOST-DEVTUNNEL-PERMANENT.bat
echo  3. Links (VS Code can be CLOSED):
echo       https://lnmmkbpg-80.asse.devtunnels.ms
echo       https://lnmmkbpg-3000.asse.devtunnels.ms
echo ========================================
echo.
pause
