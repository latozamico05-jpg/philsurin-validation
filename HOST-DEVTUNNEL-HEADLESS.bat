@echo off
REM ===========================================================================
REM  HOST-DEVTUNNEL-HEADLESS.bat
REM  Runs the tunnel detached at Windows logon via Task Scheduler.
REM  Started by task "PHILSURIN-devtunnel". Do NOT double-click normally -
REM  use HOST-DEVTUNNEL-PERMANENT.bat for an interactive window instead.
REM  Restarts the host if it ever exits (network drop, relay hiccup).
REM ===========================================================================
cd /d "%~dp0"
set TUNNEL_ID=lnmmkbpg
set DEVTUNNEL_EXE=%~dp0devtunnel.exe
if not exist "%DEVTUNNEL_EXE%" set DEVTUNNEL_EXE=devtunnel
set LOG=%~dp0_devtunnel_host.log

:loop
echo [%date% %time%] starting tunnel host >> "%LOG%" 2>&1
REM make sure backend is alive first
call "%~dp0START-3000-PERMANENT.bat" >> "%LOG%" 2>&1
REM foreground host - when it exits we wait 10s and restart
"%DEVTUNNEL_EXE%" host %TUNNEL_ID% >> "%LOG%" 2>&1
echo [%date% %time%] host exited code=%errorlevel%, restarting in 10s >> "%LOG%" 2>&1
timeout /t 10 /nobreak >nul
goto loop
