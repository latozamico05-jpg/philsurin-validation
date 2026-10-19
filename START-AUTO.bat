@echo off
REM ============================================================================
REM  PHILSURIN — ZERO-CLICK AUTO START (no buttons, no prompts)
REM  Double-click once OR boots with Windows (after INSTALL-AUTOSTART.bat).
REM  Starts Apache :80, MySQL, proxy :3000, then opens health page.
REM ============================================================================
setlocal
cd /d "%~dp0"
title PHILSURIN Auto-Start
where node >nul 2>nul || (echo [ERROR] Node.js 18+ required & timeout /t 8 & exit /b 1)
REM -- Apache + MySQL via services (silent, no pause) --
net start Apache2.4 >nul 2>&1
"C:\xampp\apache\bin\httpd.exe" -k start >nul 2>&1
net start mysql >nul 2>&1
REM -- Proxy on 3000 (single instance, hidden) --
start "PHILSURIN-Proxy" /min cmd /c "node \"%~dp0auto-start.js\" >> \"%~dp0startup-proxy.log\" 2>&1"
REM -- Wait up to 20s for proxy health, then open browser automatically --
for /L %%i in (1,1,20) do (
  curl -s -o nul --max-time 2 http://localhost:3000/_proxy-health 2>nul && goto :UP
  timeout /t 1 >nul
)
:UP
start "" http://localhost:3000/
exit /b 0
