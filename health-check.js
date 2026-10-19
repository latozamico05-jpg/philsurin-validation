#!/usr/bin/env node
// ============================================================================
//  health-check.js  —  Verify the PHILSURIN stack is running correctly
//  --------------------------------------------------------------------------
//  Checks:
//    - Port 80   (Apache)          -> HTTP 200 on /philsurin/
//    - Port 3000 (dev tunnel proxy)-> HTTP 200 on /_proxy-health
//  --------------------------------------------------------------------------
//  Usage:  node health-check.js
// ============================================================================

const http = require('http');

function get(port, path, timeout = 3000) {
  return new Promise((resolve) => {
    const req = http.get(
      { host: '127.0.0.1', port, path, timeout },
      (res) => { res.resume(); resolve(res.statusCode); }
    );
    req.on('error', (e) => resolve('ERR:' + (e.code || e.message)));
    req.on('timeout', () => { req.destroy(); resolve('TIMEOUT'); });
  });
}

(async () => {
  console.log('============================================================');
  console.log('  PHILSURIN HEALTH CHECK');
  console.log('============================================================');
  console.log('');

  const p80  = await get(80,  '/philsurin/');
  const p3k  = await get(3000, '/_proxy-health');

  const pad = (s) => String(s).padEnd(12);
  console.log('  PORT 80   (Apache):      ' + pad(p80) + '  ' + (p80 === 200 ? 'OK' : 'DOWN'));
  console.log('  PORT 3000 (TunnelProxy): ' + pad(p3k) + '  ' + (p3k === 200 ? 'OK - 502 FIXED' : 'DOWN - 502 WILL OCCUR'));
  console.log('');

  const allOk = p80 === 200 && p3k === 200;
  if (allOk) {
    console.log('  STATUS: ALL SYSTEMS OPERATIONAL');
    console.log('  Tunnel URL: https://lnmmkbpg-3000.asse.devtunnels.ms');
    process.exit(0);
  } else {
    console.log('  STATUS: ISSUES DETECTED');
    console.log('  If port 3000 is down, run:  START.bat  (or:  node devtunnel-proxy.js)');
    process.exit(1);
  }
})();