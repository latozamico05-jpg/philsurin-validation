@echo off
REM ===========================================================================
REM  FIX-502-NOW.bat - restores https://lnmmkbpg-3000.asse.devtunnels.ms
REM  Root cause of HTTP 502: VS Code tunnel forwards to port 3000, but the
REM  devtunnel-proxy.js (3000 -> Apache 80/philsurin) was not running.
REM  This script restarts it detached and verifies both hops.
REM ===========================================================================
cd /d "%~dp0"
title PHILSURIN 502 fix - port 3000 proxy

echo.
echo   [1/3] Checking Apache on port 80...
powershell -NoProfile -Command "(Test-NetConnection -ComputerName 127.0.0.1 -Port 80 -WarningAction SilentlyContinue).TcpTestSucceeded" | findstr /I true >nul
if %errorlevel% neq 0 (
  echo   !! Apache is NOT listening on port 80.
  echo   !! Open XAMPP Control Panel and Start Apache, then re-run this file.
  echo.
  pause
  exit /b 1
)
echo   OK - Apache is up.

echo.
echo   [2/3] Starting devtunnel-proxy on port 3000...
REM Kill any stale proxy holding 3000 (ignore errors if none).
powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort 3000 -ErrorAction SilentlyContinue | ForEach-Object { try { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue } catch {} }" >nul 2>&1
timeout /t 1 /nobreak >nul

REM Start detached, append logs so you can audit later.
powershell -NoProfile -Command "Start-Process -FilePath 'node' -ArgumentList '\"%~dp0devtunnel-proxy.js\" --port 3000 --upstream-port 80 --prefix /philsurin' -WorkingDirectory '%~dp0' -WindowStyle Hidden -RedirectStandardOutput '%~dp0_proxy_out.log' -RedirectStandardError '%~dp0_proxy_err.log'"
timeout /t 3 /nobreak >nul

echo.
echo   [3/3] Verifying...
node "%~dp0_fix502_probe.js"
echo.
echo   If PORT3000_OPEN=true above, reload your tunnel URL:
echo   https://lnmmkbpg-3000.asse.devtunnels.ms
echo.
echo   Keep this check: http://localhost:3000/_proxy-health should return {"ok":true}
echo.
pause
