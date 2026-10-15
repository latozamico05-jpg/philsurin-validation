/* PHILSURIN offline-first queue.
   Stores encodings in IndexedDB when offline, syncs to sync.php when online.
   Works on index.php (desktop) + mobile.php. No build step. */
(function () {
  'use strict';
  var DB = 'philsurin-offline-v1', STORE = 'outbox';
  var onlinePill = null;

  function idb() {
    return new Promise(function (res, rej) {
      try {
        var r = indexedDB.open(DB, 1);
        r.onupgradeneeded = function () { r.result.createObjectStore(STORE, { keyPath: 'client_id' }); };
        r.onsuccess = function () { res(r.result); };
        r.onerror = function () { rej(r.error); };
      } catch (e) { rej(e); }
    });
  }
  function tx(mode, fn) {
    return idb().then(function (db) {
      return new Promise(function (res, rej) {
        var t = db.transaction(STORE, mode), s = t.objectStore(STORE);
        var out = fn(s);
        t.oncomplete = function () { res(out && out.value); };
        t.onerror = function () { rej(t.error); };
      });
    });
  }
  function all() {
    return idb().then(function (db) {
      return new Promise(function (res, rej) {
        var q = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
        q.onsuccess = function () { res(q.result || []); };
        q.onerror = function () { rej(q.error); };
      });
    });
  }
  function put(rec) { rec.client_id = rec.client_id || ('c' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)); rec.queued_at = new Date().toISOString(); return tx('readwrite', function (s) { var r = s.put(rec); r.onsuccess = function () {}; return { value: rec }; }); }
  function del(id) { return tx('readwrite', function (s) { s.delete(id); return { value: true }; }); }
  function serializeForm(form) {
    var d = {}, fd = new FormData(form);
    fd.forEach(function (v, k) {
      var m = k.match(/^(field_[a-e]|remarks|germination|es_value|gs_value|uniformity|remarks_text)\[(\d+)\]$/);
      if (m) { d[m[1]] = d[m[1]] || {}; d[m[1]][m[2]] = v; }
      else if (!(k in d)) d[k] = v;
    });
    ['project_location','stage','variety_id','location_size','grading_date','location_id','date_planted','dr_dp','control_name'].forEach(function (k) {
      var el = form.elements.namedItem(k); if (el && !(k in d)) d[k] = el.value;
    });
    return d;
  }
  function setPill() {
    // Sync pill removed at user request: encoding page stays clean
    // (no "ONLINE - all data synced" / queued banner, no styling).
    try {
      var old = document.getElementById('philsurinSyncPill');
      if (old && old.parentNode) old.parentNode.removeChild(old);
    } catch (e) {}
    onlinePill = null;
  }
  function mountPill() {
    // Sync pill removed at user request: do not inject any banner.
    try {
      var old = document.getElementById('philsurinSyncPill');
      if (old && old.parentNode) old.parentNode.removeChild(old);
    } catch (e) {}
    onlinePill = null;
  }
  function syncNow() {
    setPill();
    return all().then(function (rows) {
      if (!rows.length || !navigator.onLine) { setPill(); return { synced: 0 }; }
      // Retry up to 3x (Vercel cold starts can 500/timeout the first hit).
      function attempt(n) {
        return fetch('sync.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ records: rows }) })
          .then(function (r) { return r.json().then(function (j) { return { http: r.status, body: j }; }); })
          .catch(function (e) { if (n > 0) return new Promise(function (res) { setTimeout(function () { res(attempt(n - 1)); }, 2000); }); throw e; });
      }
      return attempt(2)
        .then(function (out) {
          var results = (((out.body || {}).data || {}).results) || [];
          var done = [];
          results.forEach(function (x) { if (x && x.ok) done.push(del(x.client_id)); });
          return Promise.all(done).then(function () { setPill(); return { synced: done.length }; });
        })
        .catch(function () { setPill(); return { synced: 0 }; });
    });
  }
  // Cache lookup lists for offline variety dropdowns
  function cacheLookups() {
    if (!navigator.onLine) return;
    fetch('offline-data.php', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (j) try { localStorage.setItem('philsurin-lookups', JSON.stringify(j)); } catch (e) {}
    }).catch(function () {});
  }
  // Intercept desktop form when offline: queue instead of failing
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || form.tagName !== 'FORM' || form.method.toLowerCase() !== 'post') return;
    var btn = ev.submitter;
    if (!btn || btn.name !== 'save_validation') return;
    if (navigator.onLine) return; // online -> normal POST
    ev.preventDefault();
    put(serializeForm(form)).then(function (rec) {
      setPill();
      alert('OFFLINE SAVED on this device (' + rec.client_id + '). It will sync to the server automatically when internet returns.');
      try { syncNow(); } catch (e) {}
    });
  });
  window.PHILSURIN_SYNC = { queue: put, syncNow: syncNow, pending: all, serializeForm: serializeForm };
  document.addEventListener('DOMContentLoaded', function () { mountPill(); cacheLookups(); syncNow(); });
  window.addEventListener('online', syncNow);
  window.addEventListener('online', cacheLookups);
  window.addEventListener('offline', setPill);
  setInterval(function () { if (navigator.onLine) syncNow(); else setPill(); }, 15000);
})();
