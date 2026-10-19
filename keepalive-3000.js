// keepalive-3000.js - keeps devtunnel-proxy.js alive forever + Apache/MySQL watch.
// Runs detached, checks ports 3000/80/3306 every 20s, restarts whatever died.
// Usage: node keepalive-3000.js  (started by START-3000-PERMANENT.bat / Task Scheduler)
// Logs to keepalive-3000.log in same folder.
const { spawn, execSync } = require('child_process');
const net = require('net');
const fs = require('fs');
const path = require('path');

const DIR = __dirname;
const NODE_EXE = process.execPath;
const PROXY_JS = path.join(DIR, 'devtunnel-proxy.js');
const LOG_FILE = path.join(DIR, 'keepalive-3000.log');
const PROXY_OUT = path.join(DIR, '_proxy_out.log');
const PROXY_ERR = path.join(DIR, '_proxy_err.log');
const CHECK_MS = 20000;
const HTTPD = 'C:\\xampp\\apache\\bin\\httpd.exe';
const MYSQLD = 'C:\\xampp\\mysql\\bin\\mysqld.exe';
const MY_INI = 'C:\\xampp\\mysql\\bin\\my.ini';
let child = null;

function log(msg) {
  const line = '[' + new Date().toISOString() + '] ' + msg + '\n';
  try { fs.appendFileSync(LOG_FILE, line, 'utf8'); } catch (e) {}
  console.log(line.trim());
}

function checkPort(port, host = '127.0.0.1', timeout = 2000) {
  return new Promise((resolve) => {
    const s = new net.Socket();
    let done = false;
    const finish = (open) => { if (!done) { done = true; s.destroy(); resolve(open); } };
    s.setTimeout(timeout);
    s.on('connect', () => finish(true));
    s.on('timeout', () => finish(false));
    s.on('error', () => finish(false));
    s.connect(port, host);
  });
}

function procRunning(name) {
  try {
    const out = execSync('powershell -NoProfile -Command "Get-Process ' + name + ' -ErrorAction SilentlyContinue | Select-Object -First 1 Id"', { timeout: 8000, encoding: 'utf8', windowsHide: true });
    return /Id\s*:?\s*\d+/.test(out) || /\d+/.test(out.split('\n').slice(2).join(''));
  } catch (e) { return false; }
}

function startProxy() {
  if (child && child.exitCode === null) { try { child.kill(); } catch (e) {} }
  log('starting proxy: node devtunnel-proxy.js --port 3000 --upstream-port 80 --prefix /philsurin');
  const outFd = fs.openSync(PROXY_OUT, 'a');
  const errFd = fs.openSync(PROXY_ERR, 'a');
  child = spawn(NODE_EXE, [PROXY_JS, '--port', '3000', '--upstream-port', '80', '--prefix', '/philsurin'],
    { cwd: DIR, detached: true, stdio: ['ignore', outFd, errFd], windowsHide: true });
  child.unref();
  child.on('error', (e) => log('proxy spawn error: ' + (e.message || e)));
  child.on('exit', (code) => log('proxy exited code=' + code + ' (will restart on next check)'));
  log('proxy pid=' + child.pid);
}

function startApache() {
  try {
    spawn(HTTPD, [], { detached: true, stdio: 'ignore', windowsHide: true }).unref();
    log('apache restart sent (httpd.exe)');
  } catch (e) { log('apache start fail: ' + (e.message || e)); }
}

function startMysql() {
  try {
    const args = fs.existsSync(MY_INI) ? ['--defaults-file=' + MY_INI, '--standalone', '--console'] : [];
    spawn(MYSQLD, args, { detached: true, stdio: 'ignore', windowsHide: true }).unref();
    log('mysql restart sent (mysqld.exe)');
  } catch (e) { log('mysql start fail: ' + (e.message || e)); }
}

async function tick() {
  try {
    // MySQL first: PHP needs it. Then Apache. Then proxy.
    const mysql = await checkPort(3306);
    if (!mysql) { log('port 3306 CLOSED (MySQL down) -> restarting mysqld'); startMysql(); }
    const apache = await checkPort(80);
    if (!apache) { log('port 80 CLOSED (Apache down, ECONNREFUSED) -> restarting httpd'); startApache(); }
    const proxy = await checkPort(3000);
    if (!proxy) { log('port 3000 CLOSED -> (re)starting proxy'); startProxy(); }
  } catch (e) {
    log('check error: ' + (e.message || e));
  }
}

log('keepalive-3000 started (check every ' + (CHECK_MS / 1000) + 's: 3306 mysql, 80 apache, 3000 proxy). Node=' + process.version);
tick();
setInterval(tick, CHECK_MS);

process.on('SIGINT', () => { log('keepalive stopping'); process.exit(0); });
process.on('uncaughtException', (e) => { log('uncaught: ' + (e && e.stack || e)); });
