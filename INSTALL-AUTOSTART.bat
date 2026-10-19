@echo off
REM ============================================================================
REM  INSTALL-AUTOSTART.bat — run ONCE as Administrator.
REM  Registers PHILSURIN to start with Windows (no click ever again).
REM  Creates: 1) Startup folder shortcut  2) Scheduled Task (logon, highest)
REM ============================================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0"
net session >nul 2>&1 || (echo [ERROR] Right-click -^> Run as administrator & pause & exit /b 1)

set "SRC=%~dp0START-AUTO.bat"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\PHILSURIN-AutoStart.lnk"

echo [1/2] Startup-folder shortcut...
powershell -NoProfile -Command "$w=New-Object -ComObject WScript.Shell;$s=$w.CreateShortcut($env:APPDATA+'\Microsoft\Windows\Start Menu\Programs\Startup\PHILSURIN-AutoStart.lnk');$s.TargetPath='%SRC%';$s.WorkingDirectory='%~dp0';$s.WindowStyle=7;$s.Description='PHILSURIN zero-click autostart';$s.Save()"
echo        -^> %STARTUP%

echo [2/2] Scheduled task (logon, highest privileges)...
schtasks /create /tn "PHILSURIN AutoStart" /tr "\"%SRC%\"" /sc onlogon /rl highest /f
echo.
echo ============================================================================
echo   DONE. PHILSURIN now starts with Windows. Ports 80 + 3000 auto-start.
echo   Restart to test, or run START-AUTO.bat now.
echo ============================================================================
pause
