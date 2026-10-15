#!/usr/bin/env node
// upload-to-github.js - Push this project to GitHub WITHOUT git installed.
//
// This machine has no git.exe and no .git folder, so `git push` is not an
// option here. This script talks to the GitHub REST API directly instead: it
// walks the folder, filters out the junk, uploads each file as a git blob,
// builds one tree, and commits on top of whatever is already on the branch.
// That makes it an incremental update, not just a first upload.
//
// It is a DRY RUN by default. Nothing reaches GitHub until you pass --yes.
//
// Usage:
//   node upload-to-github.js                      # dry run: show the plan
//   node upload-to-github.js --yes                # upload to an existing repo
//   node upload-to-github.js --yes --create       # create the repo, then upload
//   node upload-to-github.js --yes --public       # make it public (default private)
//   node upload-to-github.js --pull --yes         # pull FROM GitHub to this folder
//   node upload-to-github.js --repo NAME --branch main --message "..."
//   node upload-to-github.js --include-data --yes # also upload the real data dump
//   node upload-to-github.js --help               # full option list
//
// Auth: use an environment variable. Do NOT paste the token into this file.
//   PowerShell:  $env:GITHUB_TOKEN = "github_pat_..."
//   CMD:         set GITHUB_TOKEN=github_pat_...
// Create one at https://github.com/settings/tokens - scope: Contents (RW).

const fs = require('fs');
const path = require('path');

const ROOT = __dirname;
const API = 'https://api.github.com';

// ---------------------------------------------------------------------------
// What ships. Excludes mirror deploy-to-xampp.ps1 (the files that actually go
// live) and then drop the dev-only material nobody wants in a repository.
// ---------------------------------------------------------------------------
const EXCLUDE_DIRS = new Set([
  'node_modules', // 5000+ files; restored with npm install
  '.git',         // the API replaces this entirely
  '.vscode',      // personal editor settings
]);

// Any directory starting with _ is a local backup or a debugging scratch dir.
// There are 660 such files / 20.6 MB of them.
const EXCLUDE_DIR_PATTERN = /^_/;

const EXCLUDE_FILES = new Set([
  'cloudflared.exe',      // 52.8 MB tunnel binary - never belongs in a repo
  '.clasp.json',          // personal Apps Script ID; .clasp.json.example is kept
  'NEW-CREDENTIALS.txt',  // credentials-named file, even though it is empty
  'files_list.txt',       // 40.9 KB generated listing
  'files.code-workspace', // points at local C:\ paths
]);

const EXCLUDE_PATTERNS = [
  /\.bak$/i,        // export.bak.php
  /\.bak\.php$/i,   // export.php.bak
  /\.pre-restore$/i,// export.php.pre-restore
  /\.log$/i,        // deploy and test logs
  /^_/,             // scratch files: _audit.txt, _gh.js, ...
  /^shot-/i,        // screenshots of the running app
  /^v2-/i,
  /^t1\.png$/i,
  /^LIVE-PUBLISHED\.png$/i,
];

// Extensions that are part of the app or its documentation.
const KEEP_EXT = new Set([
  '.php', '.js', '.css', '.json', '.md', '.sql', '.gs', '.html',
  '.webmanifest', '.bat', '.ps1', '.txt', '.yml', '.yaml',
  '.png', '.jpg', '.jpeg', '.svg', '.ico', '.woff', '.woff2',
]);

// Dotfiles with no extension that must travel anyway.
const KEEP_EXACT = new Set(['.htaccess', '.gitignore', 'LICENSE']);

// Real trial data and grading records. The schema files (schema.sql, seed.sql,
// migrations/) describe structure and are safe to publish; these carry actual
// field data, so they are kept out unless you ask for them with --include-data.
const DATA_DUMPS = [
  'database/philsurin_production.sql',
  'philsurin_production.sql',
];

const MAX_FILE_BYTES = 25 * 1024 * 1024; // safety net; GitHub caps at 100 MB

// ---------------------------------------------------------------------------
// Args
// ---------------------------------------------------------------------------
const argv = process.argv.slice(2);
const flag = (n) => argv.includes('--' + n);
const opt = (n) => {
  const i = argv.indexOf('--' + n);
  return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : null;
};

const LIVE = flag('yes');                    // required to change anything
const DRY = !LIVE;
const MODE_PULL = flag('pull');
const CREATE_REPO = flag('create');
const FORCE_PRIVATE = !flag('public');
const OWNER = opt('owner');
const REPO_NAME = opt('repo');
const BRANCH = opt('branch') || 'main';
const MESSAGE = opt('message') || 'Update PHILSURIN via upload-to-github.js';
const TOKEN = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || opt('token');
const INCLUDE_DATA = flag('include-data');
const FORCE_PUSH = flag('force');

const c = {
  g: (s) => '\x1b[32m' + s + '\x1b[0m',
  y: (s) => '\x1b[33m' + s + '\x1b[0m',
  r: (s) => '\x1b[31m' + s + '\x1b[0m',
  b: (s) => '\x1b[1m' + s + '\x1b[0m',
  d: (s) => '\x1b[2m' + s + '\x1b[0m',
};
const fmtKB = (n) => (n < 1024 ? n + ' B' : (n / 1024).toFixed(1) + ' KB');
const fmtMB = (n) => (n / 1048576).toFixed(1) + ' MB';

// process.exit() can cut off buffered stdout when output is piped, which makes
// the whole plan silently vanish in a log file. Setting exitCode and returning
// lets Node flush first.
function stop(code) { process.exitCode = code; }

// ---------------------------------------------------------------------------
// Collect the files that should go to the repo
// ---------------------------------------------------------------------------
function shouldInclude(name, rel) {
  if (EXCLUDE_FILES.has(name)) return false;
  if (!INCLUDE_DATA && DATA_DUMPS.indexOf(rel) >= 0) return false;
  if (EXCLUDE_PATTERNS.some((re) => re.test(name))) return false;
  if (KEEP_EXACT.has(name)) return true;
  return KEEP_EXT.has(path.extname(name).toLowerCase());
}

function collect(dir, prefix, out, skipped) {
  let entries;
  try { entries = fs.readdirSync(dir, { withFileTypes: true }); } catch (e) { return; }
  entries.sort((a, b) => a.name.localeCompare(b.name));
  for (const e of entries) {
    const abs = path.join(dir, e.name);
    const rel = prefix ? prefix + '/' + e.name : e.name;
    if (e.isDirectory()) {
      if (EXCLUDE_DIRS.has(e.name) || EXCLUDE_DIR_PATTERN.test(e.name)) {
        skipped.push([rel + '/', 'backup/scratch or dev dir']);
        continue;
      }
      collect(abs, rel, out, skipped);
    } else if (e.isFile()) {
      if (!shouldInclude(e.name, rel)) {
        skipped.push([rel, DATA_DUMPS.indexOf(rel) >= 0 ? 'real trial data (--include-data to add)' : 'excluded']);
        continue;
      }
      let st;
      try { st = fs.statSync(abs); } catch (err) { continue; }
      if (st.size > MAX_FILE_BYTES) { skipped.push([rel, 'over 25 MB']); continue; }
      out.push({ rel, abs, bytes: st.size });
    }
  }
}

// Secrets must never reach a repo. Refuse rather than warn.
const SECRET_RULES = [
  [/ghp_[A-Za-z0-9]{20,}/, 'GitHub personal access token'],
  [/github_pat_[A-Za-z0-9_]{20,}/, 'GitHub fine-grained token'],
  [/AKIA[0-9A-Z]{16}/, 'AWS access key'],
  [/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/, 'private key'],
];

function scanSecrets(files) {
  const found = [];
  for (const f of files) {
    if (!/\.(php|js|json|sql|md|txt|gs|html|bat|ps1)$/i.test(f.rel)) continue;
    let text;
    try { text = fs.readFileSync(f.abs, 'utf8'); } catch (e) { continue; }
    for (const [re, label] of SECRET_RULES) {
      const m = text.match(re);
      if (m) found.push(f.rel + ' - ' + label + ': ' + m[0].slice(0, 20) + '...');
    }
  }
  return found;
}

// ---------------------------------------------------------------------------
// Minimal GitHub REST client
// ---------------------------------------------------------------------------
function headers(extra) {
  const h = {
    'Accept': 'application/vnd.github+json',
    'X-GitHub-Api-Version': '2022-11-28',
    'User-Agent': 'upload-to-github.js',
    ...(extra || {}),
  };
  if (TOKEN) h['Authorization'] = 'Bearer ' + TOKEN;
  return h;
}

async function gh(method, urlPath, body, extraHeaders) {
  const res = await fetch(API + urlPath, {
    method,
    headers: headers({ 'Content-Type': 'application/json', ...(extraHeaders || {}) }),
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await res.text();
  let json = null;
  if (text) { try { json = JSON.parse(text); } catch (e) { json = { raw: text }; } }
  if (!res.ok) {
    const msg = (json && (json.message || json.raw)) || res.statusText;
    const err = new Error('GitHub ' + res.status + ' ' + method + ' ' + urlPath + ': ' + msg);
    err.status = res.status;
    throw err;
  }
  return json || {};
}

// One blob per file. Uploading raw bytes (not base64) avoids inflating the
// payload, which is what lets the seal PNG work here.
async function createBlob(slug, file) {
  const buf = fs.readFileSync(file.abs);
  const res = await fetch(API + '/repos/' + slug + '/git/blobs', {
    method: 'POST',
    headers: headers({ 'Content-Type': 'application/octet-stream' }),
    body: buf,
  });
  if (!res.ok) {
    const t = await res.text();
    throw new Error('blob failed for ' + file.rel + ' (' + res.status + '): ' + t.slice(0, 200));
  }
  return (await res.json()).sha;
}

// Small concurrency limit so we do not trip GitHub's abuse detection.
async function pool(items, limit, worker) {
  const results = new Array(items.length);
  let i = 0;
  async function next() {
    while (i < items.length) { const idx = i++; results[idx] = await worker(items[idx], idx); }
  }
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, next));
  return results;
}

async function resolveSlug() {
  if (OWNER && REPO_NAME) return OWNER + '/' + REPO_NAME;
  const me = await gh('GET', '/user');
  if (!REPO_NAME) {
    throw new Error('Pass --repo NAME (or --owner OWNER --repo NAME). Signed in as: ' + me.login);
  }
  return me.login + '/' + REPO_NAME;
}

async function repoExists(slug) {
  try { await gh('GET', '/repos/' + slug); return true; }
  catch (e) { if (e.status === 404) return false; throw e; }
}


// ---------------------------------------------------------------------------
// PUSH: local folder -> GitHub
// ---------------------------------------------------------------------------
async function push(slug, files) {
  // 1. Find the current tip so this commit lands on top of existing history.
  let baseTree = null;
  let parentSha = null;
  let branchExists = true;
  try {
    const ref = await gh('GET', '/repos/' + slug + '/git/ref/heads/' + BRANCH);
    parentSha = ref.object.sha;
    const head = await gh('GET', '/repos/' + slug + '/git/commits/' + parentSha);
    baseTree = head.tree.sha;
  } catch (e) {
    if (e.status !== 404 && e.status !== 409) throw e;
    branchExists = false;
    // Brand new repo: nothing to build on, so start from nothing.
  }

  console.log(c.d('  base ' + BRANCH + ': ' +
    (parentSha ? parentSha.slice(0, 7) + ' (adding to existing history)' : 'empty (first commit)')));

  // 2. Upload a blob per file.
  console.log('  uploading ' + files.length + ' files...');
  let done = 0;
  const shas = await pool(files, 4, async (f) => {
    const sha = await createBlob(slug, f);
    done++;
    if (done % 25 === 0 || done === files.length) {
      process.stdout.write('\r  uploaded ' + done + '/' + files.length + '    ');
    }
    return sha;
  });
  process.stdout.write('\n');

  // 3. One tree for the whole project.
  const tree = (await gh('POST', '/repos/' + slug + '/git/trees', {
    base_tree: baseTree,
    tree: files.map((f, i) => ({
      path: f.rel.replace(/\\/g, '/'),
      mode: '100644',
      type: 'blob',
      sha: shas[i],
    })),
  })).sha;

  // 4. Commit.
  const commit = await gh('POST', '/repos/' + slug + '/git/commits', {
    message: MESSAGE,
    tree,
    parents: parentSha ? [parentSha] : [],
  });

  // 5. Point the branch at it.
  const refPath = '/repos/' + slug + '/git/refs/heads/' + BRANCH;
  if (branchExists && parentSha) {
    try {
      await gh('PATCH', refPath, { sha: commit.sha, force: FORCE_PUSH });
    } catch (e) {
      if ((e.status === 422 || e.status === 409) && !FORCE_PUSH) {
        throw new Error('Remote ' + BRANCH + ' has commits this folder does not have.\n' +
          'Run  node upload-to-github.js --pull --yes  first, or add --force to overwrite.');
      }
      throw e;
    }
  } else {
    await gh('POST', refPath, { sha: commit.sha });
  }
  return commit;
}

// ---------------------------------------------------------------------------
// PULL: GitHub -> local folder (for setting up on a fresh machine)
// ---------------------------------------------------------------------------
async function pull(slug, intoDir) {
  let treeSha;
  try {
    const ref = await gh('GET', '/repos/' + slug + '/git/ref/heads/' + BRANCH);
    const commit = await gh('GET', '/repos/' + slug + '/git/commits/' + ref.object.sha);
    treeSha = commit.tree.sha;
  } catch (e) {
    throw new Error('Branch "' + BRANCH + '" not found on ' + slug + '.');
  }
  const tree = await gh('GET', '/repos/' + slug + '/git/trees/' + treeSha + '?recursive=1');
  const blobs = tree.tree.filter((t) => t.type === 'blob');
  console.log('  ' + blobs.length + ' files on ' + BRANCH);
  let n = 0;
  await pool(blobs, 4, async (t) => {
    const dest = path.join(intoDir, t.path.replace(/\//g, path.sep));
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    const res = await fetch(API + '/repos/' + slug + '/git/blobs/' + t.sha, {
      headers: headers({ 'Accept': 'application/vnd.github.raw' }),
    });
    if (!res.ok) throw new Error('download failed: ' + t.path + ' (' + res.status + ')');
    fs.writeFileSync(dest, Buffer.from(await res.arrayBuffer()));
    n++;
    if (n % 25 === 0 || n === blobs.length) {
      process.stdout.write('\r  wrote ' + n + '/' + blobs.length + '    ');
    }
  });
  process.stdout.write('\n');
}


// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  if (flag('help') || flag('h')) {
    console.log('');
    console.log(c.b('  upload-to-github.js') + ' - publish this project to GitHub without git.');
    console.log('');
    console.log('  1. Set your token (never put it in this file):');
    console.log('       PowerShell:  $env:GITHUB_TOKEN = "github_pat_..."');
    console.log('       CMD:         set GITHUB_TOKEN=github_pat_...');
    console.log('     Create one at https://github.com/settings/tokens');
    console.log('     Scope: Contents (Read and write)');
    console.log('');
    console.log('  2. Preview (safe, sends nothing):');
    console.log('       node upload-to-github.js');
    console.log('');
    console.log('  3. Upload:');
    console.log('       node upload-to-github.js --repo NAME --yes          existing repo');
    console.log('       node upload-to-github.js --repo NAME --yes --create   new private repo');
    console.log('       node upload-to-github.js --repo NAME --yes --create --public');
    console.log('');
    console.log('  Options:');
    console.log('    --owner OWNER     repo owner (default: the token owner)');
    console.log('    --branch NAME     branch to push (default: main)');
    console.log('    --message "..."   commit message');
    console.log('    --include-data    also upload the real trial data dump');
    console.log('    --pull --yes      download FROM GitHub into this folder');
    console.log('    --force           overwrite the branch even if remote moved ahead');
    console.log('');
    console.log('  Never uploaded: node_modules, cloudflared.exe (52 MB), every _backup/');
    console.log('  and _scratch/ folder, .bak files, logs, screenshots, .clasp.json,');
    console.log('  NEW-CREDENTIALS.txt, and the real data dump (unless --include-data).');
    console.log('');
    return;
  }

  console.log('');
  console.log(c.b('  PHILSURIN -> GitHub') +
    (DRY ? c.y('   [DRY RUN - nothing will be sent]') : c.r('   [LIVE]')));
  console.log('');

  const files = [];
  const skipped = [];
  collect(ROOT, '', files, skipped);
  if (!files.some((f) => f.rel === 'upload-to-github.js')) {
    const self = path.join(ROOT, 'upload-to-github.js');
    if (fs.existsSync(self)) {
      files.push({ rel: 'upload-to-github.js', abs: self, bytes: fs.statSync(self).size });
    }
  }
  const totalBytes = files.reduce((a, f) => a + f.bytes, 0);

  console.log('  ' + c.g('INCLUDE') + '  ' + files.length + ' files, ' + fmtMB(totalBytes));
  console.log('  ' + c.d('exclude') + '  ' + skipped.length + ' items skipped');
  console.log('');

  const secrets = scanSecrets(files);
  if (secrets.length) {
    console.log(c.r('  BLOCKED - possible secrets in these files:'));
    secrets.forEach((s) => console.log('    ' + s));
    console.log('');
    console.log('  Move them to environment variables before uploading.');
    console.log('  config.php already reads DB_HOST/DB_USER/DB_PASS from the');
    console.log('  environment, so nothing needs to be hardcoded.');
    console.log('');
    stop(1);
    return;
  }

  console.log('  largest files that WILL be uploaded:');
  files.slice().sort((a, b) => b.bytes - a.bytes).slice(0, 6).forEach((f) => {
    console.log('    ' + fmtKB(f.bytes).padStart(10) + '  ' + f.rel);
  });
  console.log('');

  const notable = skipped.filter((s) =>
    s[0] === 'cloudflared.exe' || s[0].startsWith('node_modules/') || /^_[^/]*\/$/.test(s[0]));
  if (notable.length) {
    console.log('  notable exclusions:');
    notable.slice(0, 12).forEach((s) => console.log('    ' + c.d(s[0]) + '  (' + s[1] + ')'));
    console.log('');
  }

  if (!TOKEN) {
    console.log(c.r('  No GitHub token found.') + ' Set it as an environment variable:');
    console.log('');
    console.log('    PowerShell:  $env:GITHUB_TOKEN = "github_pat_..."');
    console.log('    CMD:         set GITHUB_TOKEN=github_pat_...');
    console.log('');
    console.log('  Create one at https://github.com/settings/tokens');
    console.log('  Scope needed: Contents (Read and write)');
    console.log('');
    console.log('  The plan above is exactly what would be sent.');
    console.log('');
    if (!DRY) stop(1);
    return;
  }

  let slug;
  try {
    slug = await resolveSlug();
  } catch (e) {
    console.log(c.r('  ' + e.message));
    console.log('');
    console.log('  Example:  node upload-to-github.js --repo philsurin --yes');
    console.log('');
    stop(1);
    return;
  }

  if (MODE_PULL) {
    console.log('  PULL  ' + slug + ' @ ' + BRANCH + '  ->  ' + ROOT);
    if (DRY) { console.log(c.y('  dry run: pass --yes to write files.')); console.log(''); return; }
    try {
      await pull(slug, ROOT);
      console.log(c.g('  Pulled ' + slug + ' @ ' + BRANCH));
      console.log('');
    } catch (e) { console.log(c.r('  ' + e.message)); console.log(''); stop(1); }
    return;
  }

  console.log('  PUSH  ->  ' + slug + ' @ ' + BRANCH +
    (FORCE_PRIVATE ? '  (private)' : '  (PUBLIC - anyone can read this)'));
  console.log('');

  if (DRY) {
    console.log(c.y('  Dry run complete. Nothing was sent.'));
    console.log('  Add ' + c.b('--yes') + ' to upload for real:');
    console.log('    node upload-to-github.js --yes' + (CREATE_REPO ? ' --create' : ''));
    console.log('');
    return;
  }

  const exists = await repoExists(slug);
  if (!exists) {
    if (!CREATE_REPO) {
      console.log(c.r('  Repo not found: ' + slug));
      console.log('  Add --create to make it, or fix --repo / --owner.');
      console.log('');
      stop(1);
      return;
    }
    const name = slug.split('/')[1];
    const created = await gh('POST', (OWNER ? '/repos/' + OWNER : '/user') + '/repos', {
      name,
      private: FORCE_PRIVATE,
      description: 'PHILSURIN Sugarcane Variety Validation System',
      has_issues: true,
      auto_init: false,
    });
    console.log(c.g('  Created repo  ') + created.html_url);
    console.log('');
  }

  try {
    const commit = await push(slug, files);
    console.log(c.g('  Committed  ') + commit.sha.slice(0, 7) + ' on ' + BRANCH);
    const repo = await gh('GET', '/repos/' + slug);
    console.log(c.g('  Live at    ') + repo.html_url);
    console.log('');
  } catch (e) {
    console.log(c.r('  Push failed: ' + e.message));
    console.log('');
    stop(1);
  }
}

main().catch((e) => {
  console.log('');
  console.log(c.r('  Unexpected error: ' + (e && e.message ? e.message : e)));
  console.log('');
  stop(1);
});

