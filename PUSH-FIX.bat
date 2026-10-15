@echo off
REM ============================================================
REM  PHILSURIN - FIX + PUSH for latozamico05-jpg
REM  Repo: https://github.com/latozamico05-jpg/philsurin-validation.git
REM  Double-click. No typing needed.
REM ============================================================
cd /d "%~dp0"

echo.
echo [PHILSURIN] Fix + Push
echo ==========================================================
echo.

set "PGIT=%~dp0portable-git\bin\git.exe"
if exist "%PGIT%" set "PATH=%~dp0portable-git\bin;%~dp0portable-git\cmd;%PATH%"

where git >nul 2>nul
if %errorlevel% neq 0 (
  if exist "C:\Program Files\Git\bin\git.exe" set "PATH=C:\Program Files\Git\bin;%PATH%"
)
where git >nul 2>nul
if %errorlevel% neq 0 (
  echo [ERROR] Git NOT found.
  echo.
  echo OPTION A - auto download portable git, no admin:
  echo   A PowerShell window will open. Approve it once, then
  echo   DOUBLE-CLICK THIS FILE AGAIN.
  echo.
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALL-PORTABLE-GIT.ps1"
  echo.
  pause
  exit /b 1
)
git --version

git config --global user.name "latozamico05-jpg"
git config --global user.email "latozamico05@gmail.com"
git config --global init.defaultBranch main
echo [OK] identity set.

if not exist ".git\config" (
  echo [FIX] Rebuilding broken .git ...
  rmdir /s /q .git 2>nul
  git init -b main
  echo [OK] git init done.
) else (
  echo [OK] .git exists.
  git branch -M main 2>nul
)

git remote remove origin 2>nul
git remote add origin https://github.com/latozamico05-jpg/philsurin-validation.git
git remote -v
echo.

git add -A
git status --short
git commit -m "chore: permanent 24/7 startup + proxy fix"
if %errorlevel% neq 0 echo [INFO] Nothing new to commit, continuing...
echo.

echo [PUSH] Pushing main to origin ...
git push -u origin main
if %errorlevel% equ 0 (
  echo.
  echo [SUCCESS] LIVE:
  echo https://github.com/latozamico05-jpg/philsurin-validation
) else (
  echo.
  echo [FAILED] Common fixes:
  echo  1. Sign in when asked. Username: latozamico05-jpg
  echo     Password: use a Personal Access Token, NOT your login password.
  echo     Create: GitHub Settings - Developer settings - Personal access tokens - Tokens classic - Generate (check repo)
  echo  2. If it says non-fast-forward or fetch first, run: git pull origin main --allow-unrelated-histories
  echo  3. Then DOUBLE-CLICK THIS FILE AGAIN.
)
echo.
pause
