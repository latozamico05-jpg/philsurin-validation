#!/usr/bin/env node
// ============================================================================
//  auto-start.js — PHILSURIN ZERO-CLICK STARTUP
//  ---------------------------------------------------------------------------
//  Run automatically by .vscode/tasks.json on folderOpen (no button click).
//  Also run manually:   node auto-start.js
//
//  What it does:
//    1. Starts Apache (port 80) — tries service, httpd.exe, then XAMPP.
//    2. Starts MySQL            — tries service, then mysqld directly.
//    3. Starts devtunnel-proxy.js on port 3000 (kills stale copy first).
//    4. Waits until BOTH ports answer HTTP 200, prints URLs.
//    5. Keeps running (isBackground task) so VS Code Ports panel sees 3000.
//
//  Requires: Node 18+, Windows + XAMPP at C:\xampp (override via env).
// ============================================================================

const http = require('http');
const { spawn, execSync } = require('child_process');
const path = require('path');

const XAMPP = process.env.XAMPP_DIR || 'C:\\xampp';
const PROXY_PORT = parseInt(process.env.PROXY_PORT || '3000', 10);
const UP_PORT = parseInt(process.env.UPSTREAM_PORT || '80', 10);
const PREFIX = process.env.APP_PREFIX || '/philsurin';
const ROOT = __dirname;

const log = (m) => console.log(`  ${m}`);
const ok = (m) => console.log(`  [OK] ${m}`);
const warn = (m) => console.log(`  [WARN] ${m}`);

function sh(cmd) {
  try { execSync(cmd, { stdio: 'ignore', shell: true, timeout: 15000 }); return true; }
  catch { return false; }
}

function portOpen(port) {
  return new Promise((resolve) => {
    const req = http.get({ host: '127.0.0.1', port, path: '/', timeout: 2500 }, (res) => {
      res.resume(); resolve(true);
    });
    req.on('error', () => resolve(false));
    req.on('timeout', () => { req.destroy(); resolve(false); });
  });
}

function pathOk(port, p) {
  return new Promise((resolve) => {
    const req = http.get({ host: '127.0.0.1', port, path: p, timeout: 3000 }, (res) => {
      res.resume(); resolve(res.statusCode);
    });
    req.on('error', () => resolve(0));
    req.on('timeout', () => { req.destroy(); resolve(0); });
  });
}

async function ensureApache() {
  log('[1/4] Apache (port 80)...');
  if (await portOpen(80)) return ok('Apache already listening on port 80');
  if (sh('net start Apache2.4')) return ok('Apache service started');
  if (sh(`"${XAMPP}\\apache\\bin\\httpd.exe" -k start`)) return ok('Apache started via httpd.exe');
  warn(`Apache not auto-startable. Open XAMPP Control Panel -> Start Apache (need Admin once).`);
}

async function ensureMySQL() {
  log('[2/4] MySQL...');
  if (sh('net start mysql')) return ok('MySQL service started');
  // mysqld direct launch as fallback (detached, minimal flags)
  try {
    spawn(`"${XAMPP}\\mysql\\bin\\mysqld.exe"`,
      [`--defaults-file=${XAMPP}\\mysql\\bin\\my.ini`],
      { shell: true, detached: true, stdio: 'ignore' }).unref();
    ok('MySQL launch attempted (mysqld direct)');
  } catch { warn('MySQL needs manual start in XAMPP Control Panel.'); }
}

function ensureProxy() {
  log('[3/4] Tunnel proxy (port 3000)...');
  // If 3000 is already owned (old proxy), reuse it — devtunnel-proxy.js
  // reports EADDRINUSE cleanly, so we just spawn; no fragile taskkill needed.
  const child = spawn('node', [path.join(ROOT, 'devtunnel-proxy.js'),
    '--port', String(PROXY_PORT), '--upstream-port', String(UP_PORT), '--prefix', PREFIX],
    { cwd: ROOT, stdio: 'inherit', shell: true, detached: false });
  child.on('error', (e) => warn('proxy spawn failed: ' + e.message));
  ok(`proxy launched on port ${PROXY_PORT} -> Apache :${UP_PORT}${PREFIX}/`);
}

async function waitReady() {
  log('[4/4] Waiting for ports (up to 30s)...');
  for (let i = 1; i <= 30; i++) {
    const a = await pathOk(80, PREFIX + '/');
    const p = await pathOk(PROXY_PORT, '/_proxy-health');
    // audit.php auto-connect check: 200/302 = reachable via proxy, 0 = down
    const audit = await pathOk(PROXY_PORT, '/audit.php');
    if (a === 200 && p === 200) {
      console.log('');
      console.log('  ============================================================');
      console.log('   PHILSURIN RUNNING — NO BUTTON NEEDED');
      console.log('  ============================================================');
      console.log(`   Local:   http://localhost${PREFIX}/`);
      console.log(`   Proxy:   http://localhost:${PROXY_PORT}/`);
      console.log(`   Health:  http://localhost:${PROXY_PORT}/_proxy-health`);
      console.log('   Tunnel:  https://lnmmkbpg-3000.asse.devtunnels.ms');
      console.log(`   Audit:   https://lnmmkbpg-3000.asse.devtunnels.ms/audit.php  (${audit === 0 ? 'PROXY NOT READY' : 'AUTO-CONNECT HTTP ' + audit + ' — login once, stays connected'})`);
      console.log('  ============================================================');
      console.log('   Ports 80 + 3000 are UP. VS Code "Ports" tab auto-forwards');
      console.log('   them (see .vscode/settings.json) — no "Forward a Port" click.');
      console.log('');
      return;
    }
    await new Promise((r) => setTimeout(r, 1000));
  }
  warn('Timed out waiting for 200s. Run: node health-check.js — start Apache in XAMPP, then reload.');
}

(async () => {
  console.log('');
  console.log('  PHILSURIN AUTO-START (zero-click)');
  console.log('');
  await ensureApache();
  await ensureMySQL();
  ensureProxy();
  await waitReady();
  // Keep the task alive so VS Code sees the port owner; proxy child holds 3000.
  setInterval(() => {}, 60000);
})();
