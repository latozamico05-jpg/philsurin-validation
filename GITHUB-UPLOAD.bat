@echo off
REM ============================================================
REM  PHILSURIN — GitHub Upload Runner
REM  Usage: Double-click this file, or run in Command Prompt
REM ============================================================

echo.
echo [PHILSURIN] ==========================================
echo [PHILSURIN] GitHub Upload Runner
echo [PHILSURIN] ==========================================
echo.
echo [1] Installing Git...
echo.
echo [INFO] This may take 1-2 minutes.
echo.
winget install Git.Git --source winget -q
if %errorlevel% neq 0 (
    echo [ERROR] Git installation failed.
    echo.
    echo [ACTION] Please install Git manually:
    echo [ACTION]   1. Go to: https://git-scm.com/download/win
    echo [ACTION]   2. Download Git-2.47.1.2-64-bit.exe
    echo [ACTION]   3. Run the installer (use all default settings)
    echo [ACTION]   4. Restart this terminal after installation
    pause
    exit /b 1
)

echo [OK] Git installed.

echo.
echo [2] Checking your GitHub username...
echo.
set /p USERNAME="  Enter your GitHub username: "
if "%USERNAME%"=="" (
    echo.
    echo [ERROR] Username is required.
    pause
    exit /b 1
)
echo [OK] Username: %USERNAME%

echo.
echo [3] Setting up GitHub repository...
set "REPO_URL=https://github.com/%USERNAME%/philsurin-validation.git"
echo [OK] Repo URL: %REPO_URL%

echo.
echo [4] Configuring Git...
git config user.name "%USERNAME%"
git config user.email "%USERNAME%@users.noreply.github.com"

echo.
echo [5] Initializing repository...
git init
echo [OK] Repository initialized.

echo.
echo [6] Adding files...
git add -A
echo [OK] Files added.

echo.
echo [7] Committing...
git commit -m "chore: permanent 24/7 startup + proxy fix"
if %errorlevel% neq 0 (
    echo [INFO] No changes to commit. Continuing...
)

echo.
echo [8] Setting remote...
git remote add origin "%REPO_URL%"
git remote set-url origin "%REPO_URL%"
echo [OK] Remote set.

echo.
echo [9] Pushing to GitHub...
git branch -M main
git push -u origin main
if %errorlevel% equ 0 (
    echo [OK] Pushed! Your code is now on GitHub.
) else (
    echo [ERROR] Push failed.
    echo [ACTION] Check your internet connection and GitHub credentials.
    pause
    exit /b 1
)

echo.
echo [PHILSURIN] ==========================================
echo [PHILSURIN] Upload Complete!
echo [PHILSURIN] ==========================================
echo.
echo [INFO] Your code is now on GitHub at:
echo [INFO]   https://github.com/%USERNAME%/philsurin-validation
echo.
echo [INFO] Next steps:
echo [INFO]   1. Create a free cloud account (Render/AWS) for 24/7 hosting
echo [INFO]   2] Run RUN-PERMANENT.bat for local auto-start
echo [INFO]   3] Use README-CLOUD.md for deployment guides
echo.
pause

