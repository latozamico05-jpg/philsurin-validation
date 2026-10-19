@echo off
REM SYNC-NOW.bat — double-click to mirror PC <-> cloud both ways.
REM Needs CLOUD_URL + SYNC_KEY set once (see CLOUD-SYNC-SETUP.md step 3).
cd /d "%~dp0"
echo.
echo  ===== PHILSURIN cloud sync (both ways) =====
node "%~dp0sync-both.js"
echo.
pause
