@echo off
REM ===========================================================================
REM  START-3000-PERMANENT.bat
REM  Permanent fix: starts devtunnel-proxy (port 3000) + keepalive watcher.
REM  - Runs hidden (no console window to accidentally close)
REM  - Keepalive restarts proxy if it ever dies -> tunnel stops 502ing
REM  - Safe to double-click multiple times (single instance)
REM  Called at login via Task Scheduler entry "PHILSURIN-port3000".
REM ===========================================================================
cd /d "%~dp0"

REM --- single instance: quit if keepalive already running ---
powershell -NoProfile -Command "$me=(Get-CimInstance Win32_Process -Filter \"ProcessId=$PID\").ParentProcessId; $found=Get-CimInstance Win32_Process -Filter \"Name='node.exe'\" | Where-Object { $_.CommandLine -like '*keepalive-3000.js*' -and $_.ProcessId -ne $PID }; if($found){ exit 0 } else { exit 1 }" >nul 2>&1
if %errorlevel% equ 0 (
  echo Keepalive already running - nothing to do.
  exit /b 0
)

REM --- ensure Apache is up (start directly, no UAC popup; skipped if 80 answers) ---
powershell -NoProfile -Command "(Test-NetConnection -ComputerName 127.0.0.1 -Port 80 -WarningAction SilentlyContinue).TcpTestSucceeded" | findstr /I true >nul
if %errorlevel% neq 0 (
  echo Starting Apache...
  start "PHILSURIN-Apache" /MIN "C:\xampp\apache\bin\httpd.exe" >nul 2>&1
  timeout /t 4 /nobreak >nul
)

REM --- start keepalive hidden (it starts the proxy itself) ---
powershell -NoProfile -Command "Start-Process -FilePath 'C:\Program Files\nodejs\node.exe' -ArgumentList '\"%~dp0keepalive-3000.js\"' -WorkingDirectory '%~dp0' -WindowStyle Hidden" >nul 2>&1
timeout /t 3 /nobreak >nul

REM --- verify ---
node "%~dp0_fix502_probe.js"
