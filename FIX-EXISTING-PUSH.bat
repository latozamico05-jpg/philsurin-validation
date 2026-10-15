@echo off
REM ============================================================
REM  FIX: push EXISTING repo to latozamico05-jpg/philsurin-validation
REM  DO NOT use the "echo # philsurin-validation >> README.md" block
REM  Your README.md already exists - overwriting it would break it.
REM  This runs the CORRECT 2nd block from GitHub + fixes.
REM ============================================================
cd /d "%~dp0"
echo.
echo [PHILSURIN] Push EXISTING repo - fix
echo ==========================================================

REM --- find git (portable first, then installed) ---
set "PGIT=%~dp0portable-git\bin\git.exe"
if exist "%PGIT%" set "PATH=%~dp0portable-git\bin;%~dp0portable-git\cmd;%PATH%"
where git >nul 2>nul
if %errorlevel% neq 0 (
  if exist "C:\Program Files\Git\bin\git.exe" set "PATH=C:\Program Files\Git\bin;C:\Program Files\Git\cmd;%PATH%"
)
where git >nul 2>nul
if %errorlevel% neq 0 (
  echo [ERROR] git not found.
  echo Running portable-git installer, then re-run this file...
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALL-PORTABLE-GIT.ps1"
  pause
  exit /b 1
)
git --version

REM --- identity from your account ---
git config --global user.name "latozamico05-jpg"
git config --global user.email "latozamico05@gmail.com"
git config --global init.defaultBranch main
git config user.name "latozamico05-jpg"
git config user.email "latozamico05@gmail.com"

REM --- init only if needed (existing repo: keep files) ---
if not exist ".git\config" (
  echo [FIX] no .git - running: git init -b main
  git init -b main
) else (
  echo [OK] .git exists - keeping your files
  git branch -M main
)

REM --- ...or push an existing repository from the command line ---
REM git remote add origin https://github.com/latozamico05-jpg/philsurin-validation.git
REM git branch -M main
REM git push -u origin main
git remote remove origin 2>nul
git remote add origin https://github.com/latozamico05-jpg/philsurin-validation.git
git branch -M main
git remote -v

REM --- add + commit all existing files ---
git add -A
git status --short
git commit -m "first commit - philsurin validation system"
if %errorlevel% neq 0 echo [INFO] nothing new to commit, continuing to push...

echo.
echo [PUSH] git push -u origin main
git push -u origin main
if %errorlevel% equ 0 (
  echo.
  echo [SUCCESS] LIVE: https://github.com/latozamico05-jpg/philsurin-validation
) else (
  echo.
  echo [FAILED] If login popup appears:
  echo   Username: latozamico05-jpg
  echo   Password: use Personal Access Token, NOT password.
  echo   Make at: GitHub ^> Settings ^> Developer settings ^> Personal access tokens ^> Tokens classic ^> Generate ^(check repo^)
  echo If error is non-fast-forward / fetch first:
  echo   git pull origin main --allow-unrelated-histories
  echo Then run this file again.
)
echo.
pause
