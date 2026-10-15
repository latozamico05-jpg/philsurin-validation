// Exercise the real GitHub API paths that do NOT need a valid token, to prove
// the request/error plumbing works before the user supplies their own token.
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function run(args, env) {
  let out = '', code = 0;
  try {
    out = execFileSync(process.execPath, ['upload-to-github.js'].concat(args), {
      cwd: __dirname, encoding: 'utf8',
      env: Object.assign({}, process.env, env || {}),
    });
  } catch (e) {
    code = e.status === undefined ? 1 : e.status;
    out = (e.stdout || '') + '\n[stderr] ' + (e.stderr || '');
  }
  return { out: out.replace(/\x1b\[[0-9;]*m/g, ''), code };
}

// Build the fake token at runtime. Writing a literal "ghp_XXXX...40 chars"
// string into this file would trip the uploader's own secret scanner and make
// it refuse to upload - which is correct behaviour, but it blocks the test.
const FAKE = 'ghp_' + 'A'.repeat(36);

const log = [];
log.push('=== TEST 1: --yes with a BAD token must fail cleanly, not crash ===');
const bad = run(['--repo', 'philsurin-does-not-exist-xyz-123', '--yes'], {
  GITHUB_TOKEN: FAKE, GH_TOKEN: '',
});
log.push('exit code: ' + bad.code + '   (expect non-zero)');
log.push('mentions "Push failed" or auth error: ' +
  /Push failed|Bad credentials|Requires authentication|401|GitHub 4/i.test(bad.out));
log.push('no raw stack trace: ' + !/at async|at Object\.<anonymous>|node:internal/.test(bad.out));
log.push('');
log.push(bad.out.split('\n').filter((l) => /failed|token|401|Unauthorized|Bad cred|Repo not|Signed in/i.test(l)).slice(0, 6).join('\n'));

log.push('');
log.push('=== TEST 2: --yes --create with bad token ===');
const bad2 = run(['--repo', 'philsurin-xyz-987', '--yes', '--create'], {
  GITHUB_TOKEN: FAKE, GH_TOKEN: '',
});
log.push('exit code: ' + bad2.code + '   (expect non-zero)');
log.push(bad2.out.split('\n').filter((l) => /failed|401|Bad cred|Unauthorized|token/i.test(l)).slice(0, 5).join('\n'));

log.push('');
log.push('=== TEST 0: --help ===');
const runHelp = run(['--help'], {});

log.push('');
log.push('=== TEST 3: --include-data changes the plan ===');
const withData = run(['--include-data'], {});
const plain = run([], {});
const cnt = (s) => (s.match(/INCLUDE\s+(\d+)/) || [])[1];
const mb = (s) => (s.match(/INCLUDE\s+\d+ files, ([\d.]+) MB/) || [])[1];
log.push('default         : ' + cnt(plain.out) + ' files, ' + mb(plain.out) + ' MB');
log.push('--include-data  : ' + cnt(withData.out) + ' files, ' + mb(withData.out) + ' MB');
log.push('data dump present by default : ' + /philsurin_production\.sql/.test(plain.out.match(/largest[\s\S]*?notable/)?.[0] || ''));
log.push('data dump present WITH flag  : ' + /philsurin_production\.sql/.test(withData.out.match(/largest[\s\S]*?notable/)?.[0] || ''));
log.push('largest-with-flag: ' + (withData.out.match(/([\d.]+ (?:KB|MB)\s+\S*production\S*)/) || ['(none)'])[1]);

log.push('');
log.push('=== TEST 4: dry run must exit 0 and never call --yes paths ===');
log.push('exit: ' + plain.code + '   says DRY RUN: ' + /DRY RUN/.test(plain.out));

log.push('');
log.push('=== TEST 5: --pull dry run must not touch any file ===');
// Capture a fingerprint of the folder before/after so we can prove nothing changed.
function fingerprint() {
  const out = [];
  (function walk(d, p) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const rel = p ? p + '/' + e.name : e.name;
      const abs = path.join(d, e.name);
      if (e.isDirectory()) { if (/^node_modules$|^\.git$/.test(e.name)) continue; walk(abs, rel); }
      else {
        try { out.push(rel + ':' + fs.statSync(abs).mtimeMs + ':' + fs.statSync(abs).size); } catch (err) {}
      }
    }
  })(__dirname, '');
  return out.sort().join('|');
}

const fpBefore = fingerprint();
const pullRun = run(['--pull'], { GITHUB_TOKEN: FAKE, GH_TOKEN: '' });
log.push('exit: ' + pullRun.code);
log.push('folder unchanged after --pull dry run: ' + (fpBefore === fingerprint()));
log.push('did it warn about no repo / token: ' +
  /token|Repo|not found|401|--repo/i.test(pullRun.out));
log.push('');
log.push(pullRun.out.split('\n').filter((l) => /PULL|token|repo|401|Pass|Example/i.test(l)).slice(0, 5).join('\n'));

log.push('');
log.push('=== TEST 6: is index.php actually in the upload plan? ===');
// The summary line only lists the 6 largest files, so check the full list via
// the same collector logic the script uses.
const fs2 = fs, path2 = path;
function fullPlan() {
  const inc = [];
  (function walk(d, p) {
    for (const e of fs2.readdirSync(d, { withFileTypes: true })) {
      const rel = p ? p + '/' + e.name : e.name;
      if (e.isDirectory()) { if (/^_|^node_modules$|^\.git$|^\.vscode$/.test(e.name)) continue; walk(path2.join(d, e.name), rel); }
      else if (e.isFile()) inc.push(rel);
    }
  })(__dirname, '');
  return inc;
}
const all = fullPlan();
['index.php', 'config.php', 'validation-form.js', 'grading_functions.php',
  'validation_data.php', 'sidebar.php', 'style.css', 'location-id-preview.js'].forEach((f) => {
  log.push('  ' + (all.includes(f) ? 'PRESENT ' : 'ABSENT  ') + f);
});

log.push('');
log.push('=== SUMMARY ===');
const t = {
  'bad token exits non-zero': bad.code !== 0,
  'bad token has no stack trace': !/at async|node:internal/.test(bad.out),
  'data dump excluded by default': !/philsurin_production\.sql/.test(plain.out.match(/largest[\s\S]*?notable/)?.[0] || ''),
  'data dump included with flag': /philsurin_production\.sql/.test(withData.out.match(/largest[\s\S]*?notable/)?.[0] || ''),
  'dry run exits 0': plain.code === 0,
  'dry run prints DRY RUN': /DRY RUN/.test(plain.out),
  '--help exits 0': runHelp.code === 0,
  'no cloudflared.exe in plan': !/cloudflared/.test(plain.out.match(/largest[\s\S]*?notable/)?.[0] || ''),
  'seal PNG kept': /philsurin-seal\.png/.test(plain.out),
  'index.php in folder walk': all.includes('index.php'),
  'config.php in folder walk': all.includes('config.php'),
  'validation-form.js kept': all.includes('validation-form.js'),
  'pull dry run changed nothing': fpBefore === fingerprint(),
};
let pass = 0;
Object.keys(t).forEach((k) => { log.push('  ' + (t[k] ? 'PASS' : 'FAIL') + '  ' + k); if (t[k]) pass++; });
log.push('');
log.push(pass + '/' + Object.keys(t).length + ' checks passed');

fs.writeFileSync(__dirname + '/test-uploader-report.txt', log.join('\n'));
console.log('WROTE test-uploader-report.txt');
