# Downloads portable Git (no admin) into .\portable-git
$ErrorActionPreference = "Stop"
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$dest = Join-Path $dir "portable-git"
$zip = Join-Path $env:TEMP "portable-git.7z.exe"
$url = "https://github.com/git-for-windows/git/releases/download/v2.47.1.windows.1/PortableGit-2.47.1-64-bit.7z.exe"

if (Test-Path (Join-Path $dest "bin\git.exe")) {
  Write-Host "[OK] portable git already present."
  exit 0
}

Write-Host "[1/3] Downloading portable git (~65 MB)..."
Write-Host "      $url"
Invoke-WebRequest -Uri $url -OutFile $zip
Write-Host "[2/3] Extracting to $dest ..."
New-Item -ItemType Directory -Force -Path $dest | Out-Null
Start-Process -FilePath $zip -ArgumentList "-y -o`"$dest`"" -Wait
Write-Host "[3/3] Done. Re-run PUSH-FIX.bat"
& (Join-Path $dest "bin\git.exe") --version
