// Runs before every push. Stops you from deploying the placeholder or a broken paste.
// Validates src/Code.gs — the file clasp actually pushes to Google Apps Script.
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'src', 'Code.gs');
const text = fs.readFileSync(file, 'utf8');
const problems = [];
if (text.length < 500) problems.push('src/Code.gs is incomplete (' + text.length + ' characters).');
['function doGet', 'function login', 'function saveValidation', 'function setupValidationSheets', 'function ensureSetup_']
  .forEach(fn => { if (!text.includes(fn)) problems.push('Missing ' + fn + '().'); });
// The UI lives in src/page.html now — it must exist and carry the login/logout flow.
const pagePath = path.join(__dirname, 'src', 'page.html');
if (!fs.existsSync(pagePath)) {
  problems.push('Missing src/page.html.');
} else {
  const page = fs.readFileSync(pagePath, 'utf8');
  ['id="loginForm"', 'id="logoutBtn"', 'id="appView"', 'id="loginView"', 'google.script.run', '.login(', '.getVarieties(', '.saveValidation(']
    .forEach(needle => { if (!page.includes(needle)) problems.push('src/page.html is missing ' + needle + '.'); });
  const opens = (page.match(/<script>/g) || []).length;
  const closes = (page.match(/<\/script>/g) || []).length;
  if (opens !== closes) problems.push('src/page.html has unbalanced <script> tags.');
}
try { new Function(text); } catch (e) { problems.push('Syntax error: ' + e.message); }
// The manifest must travel with the script.
if (!fs.existsSync(path.join(__dirname, 'src', 'appsscript.json'))) {
  problems.push('Missing src/appsscript.json.');
}
// Keep the legacy root copy honest: warn (don't block) if the two copies diverged.
const rootCopy = path.join(__dirname, 'Code.gs');
if (fs.existsSync(rootCopy) && fs.readFileSync(rootCopy, 'utf8') !== text) {
  console.warn('Note: root Code.gs differs from src/Code.gs — src/Code.gs is the one being pushed.');
}
if (problems.length) { console.error('Check failed:\n- ' + problems.join('\n- ')); process.exit(1); }
const v = (text.match(/VERSION:\s*'([^']+)'/) || [])[1];
console.log('OK - src/Code.gs looks complete' + (v ? ' (version ' + v + ')' : '') + '.');
