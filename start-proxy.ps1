# start-proxy.ps1 - starts the dev tunnel proxy detached (no console), then reports if
# port 3000 is reachable. Leaves the proxy running in the background.
$kp = New-Object System.Diagnostics.ProcessStartInfo
$kp.FileName = 'C:\Program Files\nodejs\node.exe'
$kp.Arguments = '"C:\Users\hp\Downloads\files\devtunnel-proxy.js" --port 3000 --upstream-port 80 --prefix /philsurin'
$kp.UseShellExecute = $false
$kp.CreateNoWindow = $true
$kp.RedirectStandardInput = $true
$kp.RedirectStandardOutput = $true
$kp.RedirectStandardError = $true
$p = [System.Diagnostics.Process]::Start($kp)
Start-Sleep -Seconds 2
try {
    $c = New-Object System.Net.Sockets.TcpClient
    if ($c.Connect('127.0.0.1', 3000)) { 'PORT3000_OPEN' }
    else { 'PORT3000_CLOSED' }
} catch { 'PORT3000_ERROR: ' + $_.Exception.Message }
