# deploy-to-xampp.ps1 — ONE-WAY sync: working copy -> live XAMPP site.
# Run this after every edit in this folder so the live site at
# C:\xampp\htdocs\philsurin always matches. This is what stops the "my fix
# disappeared / the bug came back" problem: one canonical copy, one command.
#
# Usage:  powershell -ExecutionPolicy Bypass -File deploy-to-xampp.ps1
$src = 'c:\Users\Hp\Downloads\files'
$dst = 'C:\xampp\htdocs\philsurin'

if (-not (Test-Path $dst)) { throw "Live site not found: $dst" }

# Root app files: PHP pages, JS, CSS, web manifest, Apache .htaccess.
# Underscore files (_*.php, backups, test harnesses) are excluded.
robocopy $src $dst *.php *.js *.css *.webmanifest .htaccess /XF '_*' cloudflared.exe /NFL /NDL /NJH /NJS /NP
if ($LASTEXITCODE -ge 8) { throw "Root sync failed (robocopy code $LASTEXITCODE)" }

# API router + its rewrite rules.
robocopy (Join-Path $src 'api') (Join-Path $dst 'api') *.* /XF '_*' /NFL /NDL /NJH /NJS /NP
if ($LASTEXITCODE -ge 8) { throw "api/ sync failed (robocopy code $LASTEXITCODE)" }

# Static assets (seal image, icons).
if (Test-Path (Join-Path $src 'assets')) {
    robocopy (Join-Path $src 'assets') (Join-Path $dst 'assets') *.* /NFL /NDL /NJH /NJS /NP
    if ($LASTEXITCODE -ge 8) { throw "assets/ sync failed (robocopy code $LASTEXITCODE)" }
}

Write-Host "OK - working copy deployed to $dst" -ForegroundColor Green
Write-Host "Refresh the app in the browser (F5) once so the new service worker takes over."
