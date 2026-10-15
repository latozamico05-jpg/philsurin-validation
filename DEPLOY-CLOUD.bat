@echo off
REM DEPLOY-CLOUD v2: push Docker files to GitHub (visible errors, no silent fail)
cd /d "%~dp0"
set "PGIT=%~dp0portable-git\bin\git.exe"
if exist "%PGIT%" set "PATH=%~dp0portable-git\bin;%~dp0portable-git\cmd;%PATH%"
where git >nul 2>nul
if %errorlevel% neq 0 (
  if exist "C:\Program Files\Git\bin\git.exe" set "PATH=C:\Program Files\Git\bin;C:\Program Files\Git\cmd;%PATH%"
)
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
echo [FILES] Must exist, else push is pointless:
if not exist Dockerfile echo MISSING Dockerfile
if not exist docker-compose.yml echo MISSING docker-compose.yml
if not exist render.yaml echo MISSING render.yaml
if not exist .env.example echo MISSING .env.example
echo.
echo [STATUS] before add:
git status --short
echo.
echo [ADD]:
git add -f Dockerfile docker-compose.yml .env.example render.yaml DEPLOY-CLOUD.bat CLOUD-DEPLOY-STEPS.md .gitignore
echo.
echo [STATUS] staged for commit:
git status --short
echo.
echo [COMMIT]:
git commit -m "feat: add Dockerfile, compose, render blueprint for 24/7 cloud hosting"
echo.
echo [PUSH] --- output START ---
git push -u origin main
echo [PUSH] --- output END (exit=%errorlevel%) ---
echo.
echo If 401/403 above: login user latozamico05-jpg, password = Token from
echo https://github.com/settings/tokens (classic, scope: repo). Then re-run.
echo Verify: https://github.com/latozamico05-jpg/philsurin-validation
pause
