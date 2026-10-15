/* PHILSURIN SUGARCANE VARIETY VALIDATION WEB APPLICATION * VERSION: '1.3.0' */
var SPREADSHEET_ID = '1SQzIIhA0c91e9PyaXFIVehTVOjK8hbIRam9l_gAwlhg';
var SHEETS = { USERS: 'Users', VARIETIES: 'Varieties', VALIDATIONS: 'Validations' };
// Shared secret so only your localhost PHP app can push/pull via the JSON API.
// Change this to any random string and use the SAME value in appsscript_bridge.php.
var SYNC_KEY = 'philsurin-local-2026';

function doGet(e) {
  e = e || {};
  var action = String((e.parameter && e.parameter.action) || '').toLowerCase();
  // JSON API branch (used by localhost PHP + status badge).
  if (action === 'ping' || action === 'varieties' || action === 'validations') {
    return jsonOut_(apiGet_(action, e.parameter || {}));
  }
  return HtmlService.createHtmlOutputFromFile('page')
    .setTitle('PHILSURIN Variety Validation');
}

// Plain HTTP POST endpoint so localhost PHP (curl) can push/pull JSON.
// google.script.run stays for the web UI; doPost is for server-to-server sync.
function doPost(e) {
  try {
    var body = {};
    if (e && e.postData && e.postData.contents) {
      body = JSON.parse(e.postData.contents);
    }
    return jsonOut_(apiPost_(body));
  } catch (err) {
    return jsonOut_({ ok: false, message: String((err && err.message) || err) });
  }
}

function jsonOut_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function checkKey_(key) {
  if (!SYNC_KEY || String(key || '') !== SYNC_KEY) {
    throw new Error('Invalid sync key.');
  }
}

function apiGet_(action, params) {
  if (action === 'ping') {
    return { ok: true, app: 'PHILSURIN Variety Validation', time: new Date().toISOString() };
  }
  checkKey_(params.key);
  ensureSetup_();
  if (action === 'varieties') {
    return { ok: true, varieties: getVarieties() };
  }
  if (action === 'validations') {
    var limit = Math.min(500, Math.max(1, parseInt(params.limit || '100', 10) || 100));
    var s = getSpreadsheet_().getSheetByName(SHEETS.VALIDATIONS);
    var rows = s.getDataRange().getValues().slice(1).filter(function (r) { return r[0]; });
    var out = rows.slice(-limit).map(function (r) {
      return { timestamp: r[0], username: r[1], variety: r[2], result: r[3], notes: r[4] };
    });
    return { ok: true, count: rows.length, validations: out };
  }
  return { ok: false, message: 'Unknown action.' };
}

function apiPost_(body) {
  var action = String(body.action || '').toLowerCase();
  checkKey_(body.key);
  ensureSetup_();
  if (action === 'save') {
    var res = saveValidation(body.username, body.variety, body.result, body.notes);
    return { ok: true, message: res.message };
  }
  if (action === 'save_batch') {
    var rows = body.rows || [];
    if (!rows.length) throw new Error('No rows to sync.');
    var saved = 0;
    rows.forEach(function (r) {
      saveValidation(r.username || body.username, r.variety, r.result, r.notes);
      saved++;
    });
    return { ok: true, saved: saved, message: 'Synced ' + saved + ' row(s) to Sheet.' };
  }
  return { ok: false, message: 'Unknown action: ' + action };
}

function getSpreadsheet_() {
  try {
    var active = SpreadsheetApp.getActive();
    if (active) return active;
  } catch (e) {}
  return SpreadsheetApp.openById(SPREADSHEET_ID);
}

function ensureSetup_() {
  var ss = getSpreadsheet_();
  var u = ss.getSheetByName(SHEETS.USERS);
  var v = ss.getSheetByName(SHEETS.VARIETIES);
  var x = ss.getSheetByName(SHEETS.VALIDATIONS);
  if (!u || !v || !x || u.getLastRow() < 2 || v.getLastRow() < 2) {
    setupValidationSheets();
  }
}

function getOrCreateSheet_(ss, n, h) {
  var s = ss.getSheetByName(n) || ss.insertSheet(n);
  if (s.getLastRow() === 0) s.getRange(1, 1, 1, h.length).setValues([h]);
  return s;
}

function setupValidationSheets() {
  var ss = getSpreadsheet_();
  var u = getOrCreateSheet_(ss, SHEETS.USERS, ['Username', 'Password', 'Role', 'Active']);
  var v = getOrCreateSheet_(ss, SHEETS.VARIETIES, ['Code', 'Name', 'Description']);
  getOrCreateSheet_(ss, SHEETS.VALIDATIONS, ['Timestamp', 'Username', 'Variety', 'Result', 'Notes']);
  if (u.getLastRow() < 2) {
    u.getRange(2, 1, 2, 4).setValues([
      ['admin', 'admin123', 'admin', true],
      ['viewer', 'viewer123', 'viewer', true]
    ]);
  }
  if (v.getLastRow() < 2) {
    var a = [];
    for (var i = 1; i <= 19; i++) {
      a.push(['PS-' + ('0' + i).slice(-2), 'Sugarcane Variety ' + i, '']);
    }
    v.getRange(2, 1, a.length, 3).setValues(a);
  }
  return { ok: true, message: 'Sheets are ready.' };
}

function login(username, password) {
  ensureSetup_();
  username = String(username || '').trim();
  password = String(password || '');
  if (!username || !password) return { ok: false, message: 'Enter your username and password.' };
  var s = getSpreadsheet_().getSheetByName(SHEETS.USERS);
  if (!s) throw new Error('Run setupValidationSheets first.');
  var a = s.getDataRange().getValues();
  for (var i = 1; i < a.length; i++) {
    var active = a[i][3];
    var isActive = (active !== false && String(active).toLowerCase() !== 'false' && String(active).toLowerCase() !== 'no' && active !== '');
    if (String(a[i][0]).trim() === username && String(a[i][1]) === password && isActive) {
      return { ok: true, username: String(a[i][0]), role: String(a[i][2] || 'viewer') };
    }
  }
  return { ok: false, message: 'Invalid username or password.' };
}

function logout() {
  return { ok: true, message: 'Signed out.' };
}

function getVarieties() {
  ensureSetup_();
  var s = getSpreadsheet_().getSheetByName(SHEETS.VARIETIES);
  if (!s) throw new Error('Run setupValidationSheets first.');
  return s.getDataRange().getValues().slice(1).filter(function (r) { return r[0]; });
}

function saveValidation(username, variety, result, notes) {
  ensureSetup_();
  username = String(username || '').trim();
  if (!username) throw new Error('You are signed out. Please log in again.');
  if (!variety || !result) throw new Error('Variety and result are required.');
  var s = getSpreadsheet_().getSheetByName(SHEETS.VALIDATIONS);
  if (!s) throw new Error('Run setupValidationSheets first.');
  s.appendRow([new Date(), username, variety, result, notes || '']);
  return { ok: true, message: 'Validation saved.' };
}
