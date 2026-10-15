# PHILSURIN — Install Git & Upload to GitHub
# Run this PowerShell script to install Git and push to GitHub

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PHILSURIN - GitHub Upload Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Install Git
Write-Host "[1] Installing Git..." -ForegroundColor Yellow
$installer = "C:\Temp\git-installer.exe"
if (Test-Path $installer) {
    Write-Host "   Running Git installer..." -ForegroundColor Gray
    Start-Process $installer -ArgumentList "/SILENT","/NORESTART" -Wait
} else {
    Write-Host "   Downloading Git installer..." -ForegroundColor Gray
    Invoke-WebRequest -Uri "https://github.com/git-for-windows/git/releases/download/v2.55.0.windows.5/Git-2.55.0.5-64-bit.exe" -OutFile "C:\Temp\git-installer.exe"
    Start-Process "C:\Temp\git-installer.exe" -ArgumentList "/SILENT","/NORESTART" -Wait
}

# Step 2: Check if Git is installed
Write-Host "[2] Checking Git installation..." -ForegroundColor Yellow
if (Get-Command git -ErrorAction SilentlyContinue) {
    Write-Host "   Git found: $(git --version)" -ForegroundColor Green
} else {
    Write-Host "   ERROR: Git not found" -ForegroundColor Red
    Write-Host "   Please install Git manually: https://git-scm.com/download/win"
    pause
    exit
}

# Step 3: Get GitHub username
Write-Host "[3] Enter your GitHub username:" -ForegroundColor Yellow
$username = Read-Host "  Username"
if ([string]::IsNullOrWhiteSpace($username)) {
    Write-Host "   ERROR: Username required" -ForegroundColor Red
    pause
    exit
}

# Step 4: Initialize repo
Write-Host "[4] Initializing repository..." -ForegroundColor Yellow
git init

# Step 5: Configure user
Write-Host "[5] Configuring Git..." -ForegroundColor Yellow
git config user.name $username
git config user.email "$username@users.noreply.github.com"

# Step 6: Add files
Write-Host "[6] Adding files..." -ForegroundColor Yellow
git add -A

# Step 7: Commit
Write-Host "[7] Committing..." -ForegroundColor Yellow
git commit -m "chore: permanent 24/7 startup + proxy fix"

# Step 8: Set remote
Write-Host "[8] Setting remote repository..." -ForegroundColor Yellow
$repoUrl = "https://github.com/$username/philsurin-validation.git"
git remote add origin $repoUrl
git remote set-url origin $repoUrl

# Step 9: Push
Write-Host "[9] Pushing to GitHub..." -ForegroundColor Yellow
git branch -M main
git push -u origin main

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Upload Complete!" -ForegroundColor Green
Write-Host "  Your code is now on GitHub at:" -ForegroundColor Green
Write-Host "  https://github.com/$username/philsurin-validation" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. Create a free cloud account (Render/AWS) for 24/7 hosting"
Write-Host "  2. Run RUN-PERMANENT.bat for local auto-start"
Write-Host "  3. Use README-CLOUD.md for deployment guides"
Write-Host ""
pause
