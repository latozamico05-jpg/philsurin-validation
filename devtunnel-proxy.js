#!/usr/bin/env node
// ============================================================================
//  devtunnel-proxy.js
//  Serves PHILSURIN on port 3000 for the VS Code dev tunnel.
//
//  WHY THIS EXISTS
//  The tunnel host lnmmkbpg-3000.asse.devtunnels.ms returns HTTP 502 because
//  the tunnel forwards to port 3000 and NOTHING is listening there. Apache is
//  healthy on port 80 and serves the app at /philsurin/ (verified: HTTP 200),
//  but the tunnel root is /, so we cannot just move Apache to 3000 without
//  breaking the /philsurin/ path the app lives under.
//
//  This proxy bridges the gap. It listens on 3000, rewrites each request from
//  /foo to /philsurin/foo, forwards it to Apache on 80, then rewrites the
//  /philsurin/ prefix back out of any Location header so redirects keep the
//  browser on the tunnel origin instead of bouncing to localhost.
//
//  WHY NOT `php -S localhost:3000`?
//  PHP's built-in server ignores .htaccess completely. That would publish
//  config.php as source, plus *.sql, src/, tests/ and every .bak file as
//  plain text. Proxying to Apache keeps all of those protections live.
//  Verified through this proxy: /database.sql -> 403, same as on Apache.
//
//  Requires: Node 18+. No dependencies.
//
//  Usage:
//    node devtunnel-proxy.js
//    node devtunnel-proxy.js --port 3000 --upstream-port 80 --prefix /philsurin
// ============================================================================

const http = require('http');

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
function opt(name, dflt) {
  const i = process.argv.indexOf('--' + name);
  return i >= 0 && process.argv[i + 1] && !process.argv[i + 1].startsWith('--')
    ? process.argv[i + 1]
    : dflt;
}

const LISTEN_PORT = parseInt(opt('port', process.env.PROXY_PORT || '3000'), 10);
const LISTEN_HOST = opt('host', '0.0.0.0');
const UP_HOST = opt('upstream', '127.0.0.1');
const UP_PORT = parseInt(opt('upstream-port', '80'), 10);
// Path the app is mounted at on Apache. Trailing slashes are stripped so the
// join logic below has exactly one shape to reason about. Leading slashes are
// collapsed too: passing --prefix /philsurin on Windows shells used to build
// '//philsurin' (double slash), which broke the error page text and could make
// Apache log '(20025)The given path contained wildcard characters' on some builds.
const PREFIX = ('/' + opt('prefix', 'philsurin')).replace(/\/+$/, '').replace(/^\/+/, '/');
const HEALTH_PATH = '/_proxy-health';

// Headers that describe a single connection and must not be forwarded blindly,
// or the body gets encoded twice / the connection hangs.
const HOP_BY_HOP = new Set([
  'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
  'te', 'trailer', 'transfer-encoding', 'upgrade',
]);

// ---------------------------------------------------------------------------
// Path mapping
// ---------------------------------------------------------------------------

// Browser asks for /index.php?x=1  ->  Apache gets /philsurin/index.php?x=1
function toUpstreamPath(rawUrl) {
  const q = rawUrl.indexOf('?');
  const pathOnly = q >= 0 ? rawUrl.slice(0, q) : rawUrl;
  const query = q >= 0 ? rawUrl.slice(q) : '';
  const sub = pathOnly.startsWith('/') ? pathOnly.slice(1) : pathOnly;
  return PREFIX + '/' + sub + query;
}

// Apache sends Location: /philsurin/login.php  ->  browser gets /login.php
// Handles bare paths and absolute URLs, and leaves anything unrelated alone.
function stripPrefix(p) {
  if (p === PREFIX) return '/';
  if (p.startsWith(PREFIX + '/')) return p.slice(PREFIX.length);
  return p;
}

function rewriteLocation(loc) {
  if (!loc) return loc;
  const m = loc.match(/^https?:\/\/[^/]+(\/.*)?$/i);
  if (m) return stripPrefix(m[1] || '/');
  if (loc.startsWith('/')) return stripPrefix(loc);
  return loc; // relative Location, already correct
}

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------
function log(msg) {
  const t = new Date().toTimeString().slice(0, 8);
  console.log('[' + t + ']' + msg);
}


// ---------------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------------
const server = http.createServer((req, res) => {
  // Lets us confirm the proxy itself is alive without going through the app,
  // which is the fastest way to tell "proxy is down" from "app is down".
  if (req.url === HEALTH_PATH || req.url === HEALTH_PATH + '/') {
    const payload = JSON.stringify({
      ok: true,
      proxy: 'devtunnel-proxy',
      upstream: UP_HOST + ':' + UP_PORT,
      prefix: PREFIX,
      time: new Date().toISOString(),
    });
    res.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(payload);
    return;
  }

  const upstreamPath = toUpstreamPath(req.url);
  const started = Date.now();

  // Copy the client's headers, minus hop-by-hop entries.
  // FORWARD the browser-visible Host + proto so PHP sessions, Secure cookies
  // and Location headers all match the tunnel origin (this is what makes
  // /audit.php stay logged in instead of bouncing to login every load).
  const incomingHost = req.headers.host || '';
  const outHeaders = {};
  for (const [k, v] of Object.entries(req.headers)) {
    const lk = k.toLowerCase();
    if (lk === 'host' || HOP_BY_HOP.has(lk)) continue;
    outHeaders[k] = v;
  }
  if (incomingHost) {
    outHeaders['host'] = incomingHost;
    outHeaders['X-Forwarded-Host'] = incomingHost;
  }
  // The tunnel terminates TLS before us (we see plain http), but the browser
  // is on https — tell PHP so Secure cookies + HTTPS detection stay correct.
  if (!outHeaders['X-Forwarded-Proto'] && !outHeaders['x-forwarded-proto']) {
    const looksTunnel = /devtunnels\.ms|https/i.test(incomingHost + ' ' + (req.headers.referer || ''));
    outHeaders['X-Forwarded-Proto'] = looksTunnel ? 'https' : 'http';
  }
  outHeaders['X-Forwarded-Prefix'] = PREFIX;

  const upReq = http.request(
    { host: UP_HOST, port: UP_PORT, method: req.method, path: upstreamPath, headers: outHeaders },
    (upRes) => {
      const resHeaders = {};
      for (const [k, v] of Object.entries(upRes.headers)) {
        const lk = k.toLowerCase();
        if (HOP_BY_HOP.has(lk)) continue;
        if (lk === 'location') { resHeaders[k] = rewriteLocation(v); continue; }
        // Apache sets session cookies with Path=/philsurin/ — the browser on
        // the tunnel root (/) would never send them back, killing the login
        // on every /audit.php load. Rewrite to Path=/ so the session sticks.
        if (lk === 'set-cookie' && v) {
          const arr = Array.isArray(v) ? v : [v];
          resHeaders[k] = arr.map((c) =>
            String(c).replace(/Path=\/philsurin\/?/gi, 'Path=/')
          );
          continue;
        }
        resHeaders[k] = v;
      }
      res.writeHead(upRes.statusCode, resHeaders);
      upRes.pipe(res);
    }
  );

  upReq.on('error', (err) => {
    // This is the branch that used to produce a bare 502. Give the real cause.
    const down = ['ECONNREFUSED', 'ECONNRESET', 'EHOSTUNREACH', 'ENOTFOUND', 'ETIMEDOUT']
      .includes(err.code);
    log('  ' + req.method + ' ' + req.url + ' -> upstream error ' + (err.code || 'unknown'));
    const body =
      '<!doctype html><meta charset="utf-8"><title>Upstream unavailable</title>' +
      '<body style="font:15px/1.6 system-ui;padding:40px;max-width:720px">' +
      '<h2 style="margin:0 0 12px">Cannot reach Apache</h2>' +
      '<p>The proxy is running on port ' + LISTEN_PORT + ', but <b>' + UP_HOST + ':' + UP_PORT +
      '</b> refused the connection.</p>' +
      '<p><b>Start Apache in the XAMPP Control Panel</b>, then reload. ' +
      'The app is served from <code>' + PREFIX + '/</code>.</p>' +
      '<p style="color:#666;font-size:13px">code: ' + (err.code || 'unknown') + '</p>';
    if (!res.headersSent) {
      res.writeHead(down ? 503 : 502,
        { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
    }
    res.end(body);
  });

  // Guard against a hung upstream holding the request open forever.
  upReq.setTimeout(60000, () => {
    upReq.destroy(Object.assign(new Error('upstream timeout'), { code: 'ETIMEDOUT' }));
  });

  req.on('error', () => { try { upReq.destroy(); } catch (e) {} });
  req.pipe(upReq);

  res.on('finish', () => {
    log('  ' + req.method + ' ' + req.url + ' -> ' + upstreamPath +
      '  ' + (res.statusCode || '?') + '  ' + (Date.now() - started) + 'ms');
  });
});

// ---------------------------------------------------------------------------
// Startup: report immediately if Apache is down, instead of letting the user
// debug a 503 in a browser with no idea what the proxy expected.
// ---------------------------------------------------------------------------
function checkUpstream() {
  return new Promise((resolve) => {
    const r = http.request(
      { host: UP_HOST, port: UP_PORT, method: 'GET', path: PREFIX + '/', timeout: 4000 },
      (res) => { res.resume(); resolve(res.statusCode || 0); }
    );
    r.on('error', () => resolve(0));
    r.on('timeout', () => { r.destroy(); resolve(0); });
    r.end();
  });
}

server.on('error', (err) => {
  console.log('');
  if (err.code === 'EADDRINUSE') {
    console.log('  Port ' + LISTEN_PORT + ' is already in use.');
    console.log('  The proxy may already be running. Check it first:');
    console.log('    curl http://localhost:' + LISTEN_PORT + HEALTH_PATH);
  } else {
    console.log('  Server error: ' + err.message);
  }
  console.log('');
  process.exitCode = 1;
});

// Only bind the port when run directly. `require()`-ing this file (for the unit
// tests below) must not start a server or claim port 3000.
if (require.main === module) {
  server.listen(LISTEN_PORT, LISTEN_HOST, async () => {
    console.log('');
    console.log('  devtunnel-proxy  ->  Apache');
    console.log('  listen   http://' + LISTEN_HOST + ':' + LISTEN_PORT + '   (tunnel target)');
    console.log('  upstream ' + UP_HOST + ':' + UP_PORT + PREFIX + '/');
    console.log('  health   http://localhost:' + LISTEN_PORT + HEALTH_PATH);
    console.log('');
    const code = await checkUpstream();
    if (code === 0) {
      console.log('  !! Apache is NOT reachable on ' + UP_HOST + ':' + UP_PORT);
      console.log('  !! Start it in the XAMPP Control Panel. The tunnel will 503 until you do.');
    } else {
      console.log('  Apache responded ' + code + ' on ' + PREFIX + '/  (ready)');
    }
    console.log('');
    log('  proxy ready on port ' + LISTEN_PORT);
  });

  process.on('SIGINT', () => {
    console.log('');
    log('  shutting down');
    server.close(() => process.exit(0));
    setTimeout(() => process.exit(0), 1500);
  });
}

// Exported so the rewrite logic can be unit tested without opening a socket.
module.exports = { rewriteLocation, toUpstreamPath, stripPrefix, PREFIX, server };

