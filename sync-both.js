// sync-both.js — runs push then pull against LOCAL Apache (no new terminal needed).
// Usage: node sync-both.js   (reads CLOUD_URL/SYNC_KEY from env or sync-state.json/sync.key)
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const DIR = __dirname;
function runPhp(file) {
  try {
    const out = execFileSync('php', [path.join(DIR, file)], { timeout: 90000, encoding: 'utf8', windowsHide: true });
    console.log('=== ' + file + ' ===\n' + out);
    return true;
  } catch (e) {
    console.log('=== ' + file + ' FAILED ===\n' + ((e.stdout || '') + (e.stderr || '') + e.message).slice(0, 2000));
    return false;
  }
}
const stateFile = path.join(DIR, 'sync-state.json');
let state = {};
try { state = JSON.parse(fs.readFileSync(stateFile, 'utf8')); } catch (e) {}
if (!process.env.CLOUD_URL && !state.cloud_url) {
  console.log('NOT-CONFIGURED: set CLOUD_URL + SYNC_KEY first. See CLOUD-SYNC-SETUP.md');
  process.exit(2);
}
const okPush = runPhp('sync-push.php');
const okPull = runPhp('sync-pull.php');
console.log(okPush && okPull ? 'SYNC-OK' : 'SYNC-PARTIAL-OR-FAILED');
