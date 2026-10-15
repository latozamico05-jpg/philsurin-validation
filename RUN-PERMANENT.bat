@echo off
REM ============================================================
REM  PHILSURIN — Permanent 24/7 Runner
REM  This script starts the complete stack:
REM    Apache (PHP/MySQL) + Node.js proxy + Dev tunnel (Cloudflare)
REM  Run this at startup or double-click to start 24/7.
REM ============================================================
echo.
echo [PHILSURIN] Starting 24/7 stack...
echo [PHILSURIN] Step 1/3: Verifying prerequisites...
echo.

REM --- Check prerequisites ---
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js not found. Install from https://nodejs.org
    pause
    exit /b 1
)

where mysql >nul 2>nul
if %errorlevel% neq 0 (
    echo [WARN] MySQL not found — database may not work.
)

echo [PHILSURIN] Step 2/3: Starting Apache (port 80)...
start "Apache" /MIN "" "C:\xampp\apache\apache.exe"
timeout /t 3 /nobreak >nul

echo [PHILSURIN] Step 3/3: Starting Node.js proxy (port 3000)...
cd /d "%~dp0"
start "Node Proxy" /MIN "" "node" "devtunnel-proxy.js"
timeout /t 2 /nobreak >nul

echo [PHILSURIN] Step 4/4: Starting Cloudflare tunnel...
if exist "C:\Program Files\cloudflared\cloudflared.exe" (
    start "Cloudflare Tunnel" /MIN "" "C:\Program Files\cloudflared\cloudflared.exe"
) else if exist "cloudflared.exe" (
    start "Cloudflare Tunnel" /MIN "" "cloudflared.exe"
) else (
    echo [WARN] cloudflared.exe not found — tunnel will not run.
    echo [PHILSURIN]   Download from https://github.com/cloudflare/cloudflared/releases
    echo [PHILSURIN]   Place it in this folder, then re-run this script.
)

echo.
echo [PHILSURIN] ===========================
echo [PHILSURIN]  Stack starting...
echo [PHILSURIN]  Apache  : http://localhost:80 (port 80)
echo [PHILSURIN]  Proxy   : http://localhost:3000 (port 3000)
echo [PHILSURIN]  Tunnel  : lnmmkbpg-3000.asse.devtunnels.ms
echo [PHILSURIN] ===========================
echo.
pause
