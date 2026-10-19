@echo off
REM ===========================================================================
REM  HOST-DEVTUNNEL-PERMANENT.bat
REM  Permanent replacement for VS Code Ports tab - works with VS Code CLOSED.
REM
REM  Your screenshot shows VS Code Dev Tunnels:
REM    Port 80   Private -> https://lnmmkbpg-80.asse.devtunnels.ms
REM    Port 3000 Public  -> https://lnmmkbpg-3000.asse.devtunnels.ms
REM  Tunnel ID = lnmmkbpg, cluster = asse
REM
REM  VS Code owns that relay, so it DIES when VS Code closes (by design).
REM  This script hosts the SAME tunnel ID with the standalone devtunnel CLI,
REM  so the SAME URLs stay alive with no VS Code running.
REM
REM  Run this once to go live. Keep window open. Ctrl+C to stop.
REM  For auto-start at boot, run SETUP-PERMANENT-NOVSCODE.bat once.
REM ===========================================================================
cd /d "%~dp0"
title PHILSURIN permanent tunnel - lnmmkbpg (no VS Code needed)

set TUNNEL_ID=lnmmkbpg
set DEVTUNNEL_EXE=%~dp0devtunnel.exe

REM --- locate devtunnel CLI ---
if not exist "%DEVTUNNEL_EXE%" (
  where devtunnel >nul 2>nul
  if %errorlevel% equ 0 (
    set DEVTUNNEL_EXE=devtunnel
  ) else (
    echo.
    echo [ERROR] devtunnel.exe not found.
    echo [FIX] Run SETUP-PERMANENT-NOVSCODE.bat once - it downloads devtunnel.exe
    echo [FIX] Or: winget install Microsoft.devtunnel
    echo.
    pause
    exit /b 1
  )
)

echo.
echo [1/4] Backend check - Apache:80 + Proxy:3000 ...
call "%~dp0START-3000-PERMANENT.bat"
echo.

echo [2/4] Login check ...
"%DEVTUNNEL_EXE%" user show >nul 2>&1
if %errorlevel% neq 0 (
  echo.
  echo [LOGIN NEEDED - one time only]
  echo A browser window will open. Login with the SAME Microsoft/GitHub
  echo account you use in VS Code, then re-run this script.
  echo.
  "%DEVTUNNEL_EXE%" user login
  if %errorlevel% neq 0 (
    echo [ERROR] login failed or cancelled.
    pause
    exit /b 1
  )
) else (
  echo [OK] already logged in.
)

echo.
echo [3/4] Configuring persistent tunnel "%TUNNEL_ID%" (ports 80 Private, 3000 Public) ...
REM These are idempotent - errors if already exists are safe to ignore.
"%DEVTUNNEL_EXE%" port create %TUNNEL_ID% -p 80 --protocol http --description "Apache PHP" >nul 2>&1
"%DEVTUNNEL_EXE%" port create %TUNNEL_ID% -p 3000 --protocol http --description "Node proxy" >nul 2>&1
REM Port 3000 = Public (matches your screenshot Visibility=Public)
"%DEVTUNNEL_EXE%" access create %TUNNEL_ID% --port 3000 --anonymous >nul 2>&1
REM Port 80 stays Private (owner-only, matches screenshot) - do NOT add anonymous to it.
echo [OK] ports configured. Verify with: devtunnel port list %TUNNEL_ID%
"%DEVTUNNEL_EXE%" port list %TUNNEL_ID%

echo.
echo [4/4] Hosting tunnel "%TUNNEL_ID%" - SAME URLs as VS Code, NO VS Code needed ...
echo   80   -^> https://%TUNNEL_ID%-80.asse.devtunnels.ms  (Private)
echo   3000 -^> https://%TUNNEL_ID%-3000.asse.devtunnels.ms (Public)
echo.
echo Keep this window OPEN. Closing it stops the tunnel.
echo.

"%DEVTUNNEL_EXE%" host %TUNNEL_ID%

echo.
echo Tunnel stopped (window closed, PC slept, or network dropped).
echo Just re-run this file to go live again.
pause
