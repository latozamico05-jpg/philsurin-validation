# Downloads the portable MinGit (a minimal Git build) and verifies its SHA-256
# before extracting, so a truncated download cannot be mistaken for a working git.
$url = 'https://github.com/git-for-windows/git/releases/download/v2.55.0.windows.5/MinGit-2.55.0.5-64-bit.zip'
$out = 'C:\Users\hp\Downloads\files\MinGit.zip'
$expected = '56d7b226b7693196cfc71fef26568f536c4a021ab6c37ff2db4287bed908e96e'
for ($i = 1; $i -le 4; $i++) {
    try {
        Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing -ErrorAction Stop
        $actual = (Get-FileHash -Path $out -Algorithm SHA256).Hash.ToLower()
        if ($actual -eq $expected) {
            Write-Host "GIT_DOWNLOAD_OK $actual"
            exit 0
        }
        Write-Host "GIT_DOWNLOAD_MISMATCH try=$i actual=$actual"
        if (Test-Path $out) { Remove-Item $out -Force }
    } catch {
        Write-Host "GIT_DOWNLOAD_ERROR try=$i $($Exception.Message)"
        if (Test-Path $out) { Remove-Item $out -Force }
    }
}
Write-Host 'GIT_DOWNLOAD_FAILED'
exit 1
