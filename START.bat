@echo off
REM ============================================================================
REM  PHILSURIN - FULL SYSTEM STARTUP (Terminal)
REM  ---------------------------------------------------------------------------
REM  Starts Apache (port 80), MySQL, and devtunnel-proxy.js on port 3000.
REM  The proxy on port 3000 bridges the dev tunnel to Apache, fixing HTTP 502.
REM
REM  Usage: double-click START.bat  OR  run from terminal:  START.bat
REM ============================================================================
setlocal EnableDelayedExpansion

cd /d "%~dp0"
set "APP_DIR=%~dp0"
set "XAMPP=C:\xampp"
set "HTTPD=%XAMPP%\apache\bin\httpd.exe"
set "MYSQLD=%XAMPP%\mysql\bin\mysqld.exe"

title PHILSURIN - System Startup
color 0A

echo.
echo ============================================================================
echo   PHILSURIN VALIDATION SYSTEM - FULL STARTUP
echo ============================================================================
echo.

echo [1/6] Checking prerequisites...
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js not found in PATH. Install Node.js 18+ and try again.
    pause
    exit /b 1
)
for /f "tokens=*" %%v in ('node --version') do set "NODE_VER=%%v"
echo [OK] Node.js %NODE_VER% found

if not exist "%HTTPD%" (
    echo [ERROR] Apache not found at %HTTPD%
    pause
    exit /b 1
)
echo [OK] Apache found: %HTTPD%

if not exist "%MYSQLD%" (
    echo [ERROR] MySQL not found at %MYSQLD%
    pause
    exit /b 1
)
echo [OK] MySQL found: %MYSQLD%

if not exist "%APP_DIR%devtunnel-proxy.js" (
    echo [ERROR] devtunnel-proxy.js not found in %APP_DIR%
    pause
    exit /b 1
)
echo [OK] devtunnel-proxy.js found
echo.

echo [2/6] Starting Apache on port 80...
net start Apache2.4 >nul 2>&1
if %errorlevel% neq 0 (
    "%HTTPD%" -k start >nul 2>&1
    if %errorlevel% neq 0 (
        echo [WARN] Apache could not be started automatically.
        echo         Open XAMPP Control Panel, click Start on Apache, then press any key.
        pause >nul
    ) else (
        echo [OK] Apache started on port 80
    )
) else (
    echo [OK] Apache service started on port 80
)
echo.

echo [3/6] Starting MySQL...
net start mysql >nul 2>&1
if %errorlevel% neq 0 (
    "%MYSQLD%" --defaults-file="%XAMPP%\mysql\bin\my.ini" --user=root >nul 2>&1 &
    if %errorlevel% neq 0 (
        echo [WARN] MySQL could not be started automatically.
        echo         Start MySQL in XAMPP Control Panel manually, then press any key.
        pause >nul
    ) else (
        echo [OK] MySQL started
    )
) else (
    echo [OK] MySQL service started
)

echo [4/6] Starting dev tunnel proxy on port 3000...
for /f "tokens=5" %%p in ('netstat -ano ^| findstr :3000 ^| findstr LISTENING') do (
    taskkill /PID %%p /F >nul 2>&1
)
start "DevTunnelProxy" /b /min node devtunnel-proxy.js --port 3000 --upstream-port 80 --prefix /philsurin > "%APP_DIR%startup-proxy.log" 2>&1
echo [OK] devtunnel-proxy launched on port 3000 (minimized window)
echo.

echo [5/6] Waiting for proxy to be ready (up to 15 seconds)...
set "READY=0"
for /L %%i in (1,1,15) do (
    curl -s -o nul -w "%{http_code}" --max-time 2 http://localhost:3000/_proxy-health > "%TEMP%\philsurin-health.txt" 2>nul
    set /p CODE= < "%TEMP%\philsurin-health.txt" 2>nul
    if "!CODE!"=="200" (
        set "READY=1"
        goto :READY_OK
    )
    timeout /t 1 >nul
)
:READY_OK
if "!READY!"=="1" (
    echo [OK] Proxy is responding on port 3000 (HTTP 200) - 502 ERROR FIXED
) else (
    echo [WARN] Proxy health check did not return 200 yet.
    echo         Check startup-proxy.log. The tunnel may 502 until both are up.
)
echo.

echo [6/6] Running final verification...
echo.
echo -----------------------------------------------------------------------------
for /f "tokens=*" %%c in ('curl -s -o nul -w "%{http_code}" --max-time 2 http://localhost/philsurin/ 2^>nul') do set "P80=%%c"
for /f "tokens=*" %%c in ('curl -s -o nul -w "%{http_code}" --max-time 2 http://localhost:3000/_proxy-health 2^>nul') do set "P3K=%%c"
echo   PORT 80  (Apache):      %P80%
echo   PORT 3000 (TunnelProxy): %P3K%
echo -----------------------------------------------------------------------------
echo.
echo ============================================================================
echo   SYSTEM IS RUNNING
echo ============================================================================
echo   Tunnel URL (public):   https://lnmmkbpg-3000.asse.devtunnels.ms
echo   Local URL (Apache):    http://localhost/philsurin/
echo   Proxy URL (port 3000): http://localhost:3000/
echo   Health check:          http://localhost:3000/_proxy-health
echo   Log file:              startup-proxy.log
echo ============================================================================
echo.
echo   The proxy on port 3000 forwards every request to Apache at /philsurin/,
echo   so the dev tunnel no longer returns HTTP 502.
echo.
echo   Keep this terminal window open. Close it to stop the proxy.
echo   (Apache and MySQL keep running as services.)
echo.
pause