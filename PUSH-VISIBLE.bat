@echo off
REM PUSH-VISIBLE v1 - double-click this, login in THIS window (no hang)
cd /d "%~dp0"
set GITEXE=portable-git\bin\git.exe
if not exist "%GITEXE%" set "GITEXE=C:\Program Files\Git\bin\git.exe"
if not exist "%GITEXE%" set GITEXE=git
"%GITEXE%" --version
"%GITEXE%" config user.name "latozamico05-jpg"
"%GITEXE%" config user.email "latozamico05@gmail.com"
"%GITEXE%" branch -M main
"%GITEXE%" remote remove origin 2>nul
"%GITEXE%" remote add origin https://github.com/latozamico05-jpg/philsurin-validation.git
echo.
echo [LOG] local commits:
"%GITEXE%" log --oneline -5
echo.
echo [PUSH] Type your username + TOKEN when asked.
echo Username: latozamico05-jpg
echo Password: TOKEN from https://github.com/settings/tokens (classic, check repo)
echo.
set GIT_TERMINAL_PROMPT=1
"%GITEXE%" push -u origin main
echo.
echo [DONE] exit=%errorlevel%
echo Verify: https://github.com/latozamico05-jpg/philsurin-validation
pause
