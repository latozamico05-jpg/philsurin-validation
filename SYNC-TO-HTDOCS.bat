@echo off
REM Copy edited files from Downloads to XAMPP htdocs so the SAME tunnel link serves them.
set SRC=%~dp0
set DST=C:\xampp\htdocs\philsurin\
if not exist "%DST%" mkdir "%DST%"
echo Copying PHILSURIN to %DST% ...
xcopy "%SRC%*.php" "%DST%" /Y /Q
xcopy "%SRC%*.js" "%DST%" /Y /Q
xcopy "%SRC%*.css" "%DST%" /Y /Q
xcopy "%SRC%*.webmanifest" "%DST%" /Y /Q
xcopy "%SRC%offline.html" "%DST%" /Y /Q
if exist "%SRC%api" xcopy "%SRC%api" "%DST%api\" /Y /Q /E /I
if exist "%SRC%assets" xcopy "%SRC%assets" "%DST%assets\" /Y /Q /E /I
echo Done. Open http://localhost/philsurin/health.php
pause
