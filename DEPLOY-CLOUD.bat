@echo off
REM DEPLOY-CLOUD v3: push Docker + cloud-sync files to GitHub
cd /d "%~dp0"
set "PGIT=%~dp0portable-git\bin\git.exe"
if exist "%PGIT%" set "PATH=%~dp0portable-git\bin;%~dp0portable-git\cmd;%PATH%"
if exist "C:\Program Files\Git\bin\git.exe" set "PATH=C:\Program Files\Git\bin;C:\Program Files\Git\cmd;%PATH%"
where git >nul 2>nul
if %errorlevel% neq 0 (
  echo [ERROR] git not found. Run FIND-AND-FIX.bat first.
  pause
  exit /b 1
)
git --version
git branch --show-current
git remote -v
echo.
git add -f Dockerfile docker-compose.yml .env.example render.yaml DEPLOY-CLOUD.bat CLOUD-DEPLOY-STEPS.md CLOUD-SYNC-SETUP.md .gitignore cloud-sync.php sync-push.php sync-pull.php sync-both.js SYNC-NOW.bat config.php db-bootstrap.php health.php grading_functions.php roles.php .htaccess api/.htaccess
echo.
git status --short
echo.
git commit -m "feat: always-on Render cloud + 2-way sync bridge (works with PC OFF)"
echo.
echo [PUSH] --- output START ---
git push -u origin main
echo [PUSH] --- output END (exit=%errorlevel%) ---
echo.
echo Verify: https://github.com/latozamico05-jpg/philsurin-validation
pause

