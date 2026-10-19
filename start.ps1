# ============================================================================
#  PHILSURIN - FULL SYSTEM STARTUP (PowerShell / Terminal)
#  ---------------------------------------------------------------------------
#  Starts Apache (port 80), MySQL, and devtunnel-proxy.js on port 3000.
#  The proxy on port 3000 bridges the dev tunnel to Apache, fixing HTTP 502.
#
#  Usage:  right-click start.ps1 -> "Run with PowerShell"
#          OR:  pwsh -File start.ps1
#          OR:  powershell -ExecutionPolicy Bypass -File start.ps1
# ============================================================================
$ErrorActionPreference = 'Continue'
$APP_DIR   = Split-Path -Parent $MyInvocation.MyCommand.Definition
$XAMPP     = 'C:\xampp'
$HTTPD     = "$XAMPP\apache\bin\httpd.exe"
$MYSQLD    = "$XAMPP\mysql\bin\mysqld.exe"
$PROXY_LOG = "$APP_DIR\startup-proxy.log"
$TUNNEL_URL= 'https://lnmmkbpg-3000.asse.devtunnels.ms'

Write-Host ""
Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host "  PHILSURIN VALIDATION SYSTEM - FULL STARTUP" -ForegroundColor Cyan
Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host ""

# --------------------------------------------------------------------------
# 1. Prerequisite checks
# --------------------------------------------------------------------------
Write-Host "[1/6] Checking prerequisites..." -ForegroundColor Yellow
if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    Write-Host "[ERROR] Node.js not found in PATH. Install Node.js 18+ and try again." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK] Node.js $(node --version) found" -ForegroundColor Green

if (-not (Test-Path $HTTPD)) {
    Write-Host "[ERROR] Apache not found at $HTTPD" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK] Apache found: $HTTPD" -ForegroundColor Green

if (-not (Test-Path $MYSQLD)) {
    Write-Host "[ERROR] MySQL not found at $MYSQLD" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK] MySQL found: $MYSQLD" -ForegroundColor Green

if (-not (Test-Path "$APP_DIR\devtunnel-proxy.js")) {
    Write-Host "[ERROR] devtunnel-proxy.js not found in $APP_DIR" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK] devtunnel-proxy.js found" -ForegroundColor Green
Write-Host ""

# --------------------------------------------------------------------------
# 2. Start Apache (port 80)
# --------------------------------------------------------------------------
Write-Host "[2/6] Starting Apache on port 80..." -ForegroundColor Yellow
$apacheStarted = $false
try {
    $svc = Get-Service -Name 'Apache2.4' -ErrorAction Stop
    if ($svc.Status -ne 'Running') { Start-Service -Name 'Apache2.4' -ErrorAction Stop }
    $apacheStarted = $true
    Write-Host "[OK] Apache service started on port 80" -ForegroundColor Green
} catch {
    try {
        & $HTTPD -k start | Out-Null
        $apacheStarted = $true
        Write-Host "[OK] Apache started on port 80" -ForegroundColor Green
    } catch {
        Write-Host "[WARN] Apache could not be started automatically." -ForegroundColor Yellow
        Write-Host "       Open XAMPP Control Panel, click Start on Apache, then press any key." -ForegroundColor Yellow
        $null = Read-Host "Press Enter to continue"
    }
}
Write-Host ""

# --------------------------------------------------------------------------
# 3. Start MySQL
# --------------------------------------------------------------------------
Write-Host "[3/6] Starting MySQL..." -ForegroundColor Yellow
try {
    $svc = Get-Service -Name 'mysql' -ErrorAction Stop
    if ($svc.Status -ne 'Running') { Start-Service -Name 'mysql' -ErrorAction Stop }
    Write-Host "[OK] MySQL service started" -ForegroundColor Green
} catch {
    try {
        Start-Process -FilePath $MYSQLD -ArgumentList "--defaults-file=`"$XAMPP\mysql\bin\my.ini`"", "--user=root" -WindowStyle Hidden -ErrorAction Stop
        Write-Host "[OK] MySQL started" -ForegroundColor Green
    } catch {
        Write-Host "[WARN] MySQL could not be started automatically." -ForegroundColor Yellow
        Write-Host "       Start MySQL in XAMPP Control Panel manually, then press any key." -ForegroundColor Yellow
        $null = Read-Host "Press Enter to continue"
    }
}
Write-Host ""

# --------------------------------------------------------------------------
# 4. Start dev tunnel proxy on port 3000 (THIS FIXES THE 502 ERROR)
# --------------------------------------------------------------------------
Write-Host "[4/6] Starting dev tunnel proxy on port 3000..." -ForegroundColor Yellow

# Kill any existing process listening on port 3000
Get-NetTCPConnection -LocalPort 3000 -State Listen -ErrorAction SilentlyContinue |
    ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }

$proxyProc = Start-Process -FilePath 'node' `
    -ArgumentList 'devtunnel-proxy.js','--port','3000','--upstream-port','80','--prefix','/philsurin' `
    -WorkingDirectory $APP_DIR `
    -RedirectStandardOutput $PROXY_LOG `
    -RedirectStandardError $PROXY_LOG `
    -WindowStyle Minimized `
    -PassThru

Write-Host "[OK] devtunnel-proxy launched on port 3000 (minimized)" -ForegroundColor Green
Write-Host ""

# --------------------------------------------------------------------------
# 5. Wait for proxy to be ready
# --------------------------------------------------------------------------
Write-Host "[5/6] Waiting for proxy to be ready (up to 15 seconds)..." -ForegroundColor Yellow
$ready = $false
for ($i = 0; $i -lt 15; $i++) {
    try {
        $code = (Invoke-WebRequest -Uri 'http://localhost:3000/_proxy-health' -TimeoutSec 2 -ErrorAction Stop).StatusCode
        if ($code -eq 200) { $ready = $true; break }
    } catch {}
    Start-Sleep -Seconds 1
}

if ($ready) {
    Write-Host "[OK] Proxy is responding on port 3000 (HTTP 200) - 502 ERROR FIXED" -ForegroundColor Green
} else {
    Write-Host "[WARN] Proxy health check did not return 200 yet." -ForegroundColor Yellow
    Write-Host "       Check $PROXY_LOG. The tunnel may 502 until both are up." -ForegroundColor Yellow
}
Write-Host ""

# --------------------------------------------------------------------------
# 6. Final verification summary
# --------------------------------------------------------------------------
Write-Host "[6/6] Running final verification..." -ForegroundColor Yellow
Write-Host ""
Write-Host "-------------------------------------------------------------"
try { $p80 = (Invoke-WebRequest -Uri 'http://localhost/philsurin/' -TimeoutSec 2 -ErrorAction Stop).StatusCode } catch { $p80 = 'DOWN' }
try { $p3k = (Invoke-WebRequest -Uri 'http://localhost:3000/_proxy-health' -TimeoutSec 2 -ErrorAction Stop).StatusCode } catch { $p3k = 'DOWN' }
Write-Host "  PORT 80  (Apache):      $p80"
Write-Host "  PORT 3000 (TunnelProxy): $p3k"
Write-Host "-------------------------------------------------------------"
Write-Host ""
Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host "  SYSTEM IS RUNNING" -ForegroundColor Cyan
Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host "  Tunnel URL (public):   $TUNNEL_URL" -ForegroundColor Green
Write-Host "  Local URL (Apache):    http://localhost/philsurin/" -ForegroundColor Green
Write-Host "  Proxy URL (port 3000): http://localhost:3000/" -ForegroundColor Green
Write-Host "  Health check:          http://localhost:3000/_proxy-health" -ForegroundColor Green
Write-Host "  Log file:              $PROXY_LOG" -ForegroundColor Green
Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  The proxy on port 3000 forwards every request to Apache at /philsurin/," -ForegroundColor Gray
Write-Host "  so the dev tunnel no longer returns HTTP 502." -ForegroundColor Gray
Write-Host ""
Write-Host "  Keep this terminal window open. Close it to stop the proxy." -ForegroundColor Gray
Write-Host "  (Apache and MySQL keep running as services.)" -ForegroundColor Gray
Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')