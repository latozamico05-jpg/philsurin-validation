@echo off
REM ============================================================
REM  PHILSURIN - AUTO PUSH for latozamico05-jpg
REM  Double-click this file. No typing needed.
REM ============================================================
cd /d "%~dp0"

echo.
echo [PHILSURIN] Auto-push as latozamico05-jpg
echo ==========================================================
echo.

REM --- 1. Find git ---
where git >nul 2>nul
if %errorlevel% neq 0 (
  if exist "C:\Program Files\Git\bin\git.exe" set "PATH=C:\Program Files\Git\bin;%PATH%"
)
where git >nul 2>nul
if %errorlevel% neq 0 (
  echo [ERROR] Git is NOT installed.
  echo.
  echo [ACTION] Install it first:
  echo   1. Go to https://git-scm.com/download/win
  echo   2. Download 64-bit Git ^(~65 MB, NOT the 2 MB file in C:\Temp^)
  echo   3. Install with all defaults, then DOUBLE-CLICK THIS FILE AGAIN.
  echo.
  del /q C:\Temp\git-installer.exe 2>nul
  pause
  exit /b 1
)
git --version

REM --- 2. Config (your details pre-filled) ---
git config --global user.name "latozamico05-jpg"
git config --global user.email "latozamico05@gmail.com"
git config user.name "latozamico05-jpg"
git config user.email "latozamico05@gmail.com"
echo [OK] git identity set.

REM --- 3. Init if needed ---
if not exist ".git" (
  git init
  echo [OK] git init done.
) else (
  echo [OK] .git already exists.
)

REM --- 4. Add + commit ---
git add -A
git commit -m "chore: permanent 24/7 startup + proxy fix"
if %errorlevel% neq 0 echo [INFO] Nothing new to commit, continuing...

REM --- 5. Branch + remote (pre-filled) ---
git branch -M main
git remote remove origin 2>nul
git remote add origin https://github.com/latozamico05-jpg/philsurin-validation.git
echo [OK] remote = https://github.com/latozamico05-jpg/philsurin-validation.git
echo.
echo [IMPORTANT] Create the empty repo first if push says 404:
echo   https://github.com/new  -^> name: philsurin-validation -^> Create (NO readme)
echo.

REM --- 6. Push ---
git push -u origin main
if %errorlevel% equ 0 (
  echo.
  echo [SUCCESS] Live on GitHub:
  echo https://github.com/latozamico05-jpg/philsurin-validation
) else (
  echo.
  echo [FAILED] Push blocked. Most common causes:
  echo  1. Repo does not exist yet - create it at https://github.com/new
  echo  2. GitHub asks for login - use your password OR Personal Access Token:
  echo     GitHub ^> Settings ^> Developer settings ^> Personal access tokens ^> Generate
  echo  3. Then DOUBLE-CLICK THIS FILE AGAIN.
)
echo.
pause
