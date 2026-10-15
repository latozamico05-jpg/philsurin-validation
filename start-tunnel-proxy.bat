@echo off
REM ===========================================================================
REM  start-tunnel-proxy.bat
REM  Starts the port-3000 proxy that the VS Code dev tunnel forwards to.
REM
REM  Run this BEFORE opening the tunnel link, or the tunnel returns HTTP 502.
REM  Keep this window open while you use the tunnel. Ctrl+C stops the proxy.
REM ===========================================================================

cd /d "%~dp0"
title PHILSURIN devtunnel proxy - port 3000

echo.
echo   Starting proxy on port 3000 -^> Apache on port 80 ^(/philsurin/^)
echo   Tunnel: https://lnmmkbpg-3000.asse.devtunnels.ms
echo.
echo   Keep this window open. Press Ctrl+C to stop.
echo.

node "%~dp0devtunnel-proxy.js" --port 3000 --upstream-port 80 --prefix /philsurin

echo.
echo   Proxy stopped.
pause
