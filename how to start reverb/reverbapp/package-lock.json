'use strict';
/**
 * Reverb Launcher + WebSocket Proxy for cPanel Passenger (Node.js)
 * Domain: ws.submit-now.online  ->  Reverb @ 127.0.0.1:8080
 *
 * শুধু নিচের CONFIG অংশ আপনার সার্ভার অনুযায়ী ঠিক করুন।
 */
const http = require('http');
const net = require('net');
const fs = require('fs');
const path = require('path');
const { spawn, execFileSync } = require('child_process');

// ===================== CONFIG =====================
const os = require('os');
const HOME = os.homedir();
const LARAVEL_CANDIDATES = [
  process.env.LARAVEL_PATH,
  path.join(HOME, 'application.submit-now.online'),
  path.join(HOME, 'public_html/application.submit-now.online'),
  path.join(HOME, 'public_html'),
  path.join(HOME, 'application'),
  path.join(HOME, 'laravel'),
].filter(Boolean);
function findLaravel() {
  for (const p of LARAVEL_CANDIDATES) {
    if (fs.existsSync(path.join(p, 'artisan'))) return p;
  }
  return null;
}
const REVERB_HOST = '127.0.0.1';
const REVERB_PORT = parseInt(process.env.REVERB_PORT || '8080', 10);
const PHP_CANDIDATES = [
  process.env.PHP_BIN,
  '/opt/cpanel/ea-php83/root/usr/bin/php', // artisan --version দিয়ে যাচাই করা (Laravel 12.39.0)
  '/opt/cpanel/ea-php82/root/usr/bin/php',
  '/usr/local/bin/php',
  '/opt/alt/php83/usr/bin/php',
  '/usr/bin/php', // cgi-fcgi হলে নিচে বাদ পড়বে
].filter(Boolean);
const LOG_FILE = process.env.REVERB_LOG || path.join(__dirname, 'reverb-launcher.log');
const LOCK_FILE = path.join(__dirname, 'reverb.pid');
// ==================================================

function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  try { fs.appendFileSync(LOG_FILE, line); } catch (_) {}
}

function findPhp() {
  for (const p of PHP_CANDIDATES) {
    try {
      if (!fs.existsSync(p)) continue;
      const out = execFileSync(p, ['-r', 'echo PHP_VERSION . "|" . php_sapi_name();'], { timeout: 5000 })
        .toString().trim();
      const [v, sapi] = out.split('|');
      const [major, minor] = v.split('.').map(Number);
      const okVersion = major > 8 || (major === 8 && minor >= 2); // Reverb-এর জন্য ৮.২+
      if (sapi === 'cli' && okVersion) return { bin: p, version: v, sapi };
      log(`PHP বাদ: ${p} (version ${v}, sapi ${sapi})`);
    } catch (e) {
      log(`PHP পরীক্ষা ব্যর্থ: ${p} - ${e.message}`);
    }
  }
  return null;
}

function isPortOpen(port, host = REVERB_HOST, timeout = 800) {
  return new Promise((resolve) => {
    const s = net.connect({ port, host });
    const done = (ok) => { s.destroy(); resolve(ok); };
    s.setTimeout(timeout);
    s.once('connect', () => done(true));
    s.once('timeout', () => done(false));
    s.once('error', () => done(false));
  });
}

function pidAlive(pid) {
  try { process.kill(pid, 0); return true; } catch (_) { return false; }
}

let starting = null; // একসাথে একাধিক স্টার্ট আটকানোর জন্য

async function ensureReverb() {
  if (await isPortOpen(REVERB_PORT)) return { running: true, started: false };
  if (starting) return starting;

  starting = (async () => {
    try {
      // লক ফাইলে থাকা পুরোনো PID এখনো জীবিত থাকলে নতুন প্রসেস চালাব না
      if (fs.existsSync(LOCK_FILE)) {
        const old = parseInt(fs.readFileSync(LOCK_FILE, 'utf8'), 10);
        if (old && pidAlive(old)) {
          log(`পুরোনো Reverb PID ${old} জীবিত, পোর্টের জন্য অপেক্ষা`);
          for (let i = 0; i < 10; i++) {
            if (await isPortOpen(REVERB_PORT)) return { running: true, started: false };
            await new Promise((r) => setTimeout(r, 500));
          }
        }
      }

      const php = findPhp();
      if (!php) {
        log('PHP 8.2 binary পাওয়া যায়নি');
        return { running: false, error: 'php_not_found' };
      }
      const LARAVEL_PATH = findLaravel();
      if (!LARAVEL_PATH) {
        log(`artisan পাওয়া যায়নি। যাচাই করা পথ: ${LARAVEL_CANDIDATES.join(', ')}`);
        return { running: false, error: 'artisan_not_found' };
      }

      const out = fs.openSync(LOG_FILE, 'a');
      const child = spawn(
        php.bin,
        ['artisan', 'reverb:start', `--host=${REVERB_HOST}`, `--port=${REVERB_PORT}`],
        { cwd: LARAVEL_PATH, detached: true, stdio: ['ignore', out, out] }
      );
      child.unref();
      fs.writeFileSync(LOCK_FILE, String(child.pid));
      log(`Reverb চালু হয়েছে: PID ${child.pid}, PHP ${php.version} (${php.bin})`);

      child.on('exit', (code) => log(`Reverb বন্ধ হয়েছে, exit code ${code}`));

      for (let i = 0; i < 20; i++) {
        if (await isPortOpen(REVERB_PORT)) return { running: true, started: true, pid: child.pid };
        await new Promise((r) => setTimeout(r, 500));
      }
      log('Reverb স্টার্ট হয়েছে কিন্তু পোর্ট খোলেনি (১০ সেকেন্ড)');
      return { running: false, error: 'port_timeout' };
    } catch (e) {
      log(`ensureReverb ত্রুটি: ${e.stack || e.message}`);
      return { running: false, error: e.message };
    } finally {
      starting = null;
    }
  })();
  return starting;
}

const server = http.createServer(async (req, res) => {
  if (req.url === '/__diag') {
    const php = findPhp();
    const report = {
      node: process.version,
      home: HOME,
      appDir: __dirname,
      laravelFound: findLaravel(),
      laravelChecked: LARAVEL_CANDIDATES,
      php: php || null,
      phpChecked: PHP_CANDIDATES.map((p) => ({ path: p, exists: fs.existsSync(p) })),
      reverbPortOpen: await isPortOpen(REVERB_PORT),
      lastLog: (() => {
        try { return fs.readFileSync(LOG_FILE, 'utf8').split('\n').slice(-25); } catch (_) { return []; }
      })(),
    };
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    return res.end(JSON.stringify(report, null, 2));
  }

  if (req.url === '/__health') {
    const st = await ensureReverb();
    res.writeHead(st.running ? 200 : 503, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ app: 'reverb-launcher', node: process.version, reverb: st }));
  }

  const st = await ensureReverb();
  if (!st.running) {
    res.writeHead(503, { 'Content-Type': 'text/plain' });
    return res.end('Reverb is not running');
  }

  const proxyReq = http.request(
    { host: REVERB_HOST, port: REVERB_PORT, method: req.method, path: req.url, headers: req.headers },
    (proxyRes) => {
      res.writeHead(proxyRes.statusCode, proxyRes.headers);
      proxyRes.pipe(res);
    }
  );
  proxyReq.on('error', (e) => {
    log(`HTTP proxy ত্রুটি: ${e.message}`);
    if (!res.headersSent) res.writeHead(502);
    res.end('Bad gateway');
  });
  req.pipe(proxyReq);
});

// WebSocket (wss -> ws://127.0.0.1:8080)
server.on('upgrade', async (req, socket, head) => {
  const st = await ensureReverb();
  if (!st.running) {
    socket.end('HTTP/1.1 503 Service Unavailable\r\n\r\n');
    return;
  }
  const upstream = net.connect(REVERB_PORT, REVERB_HOST, () => {
    let raw = `${req.method} ${req.url} HTTP/1.1\r\n`;
    for (let i = 0; i < req.rawHeaders.length; i += 2) {
      raw += `${req.rawHeaders[i]}: ${req.rawHeaders[i + 1]}\r\n`;
    }
    raw += '\r\n';
    upstream.write(raw);
    if (head && head.length) upstream.write(head);
    socket.pipe(upstream);
    upstream.pipe(socket);
  });
  upstream.on('error', (e) => { log(`WS upstream ত্রুটি: ${e.message}`); socket.destroy(); });
  socket.on('error', () => upstream.destroy());
  socket.on('close', () => upstream.destroy());
});

// Passenger সংযোগ
if (typeof PhusionPassenger !== 'undefined') {
  PhusionPassenger.configure({ autoInstall: false });
  server.listen('passenger');
} else {
  server.listen(3000, () => console.log('Local test: http://127.0.0.1:3000/__health'));
}

ensureReverb().then((r) => log(`স্টার্টআপ চেক: ${JSON.stringify(r)}`));
process.on('uncaughtException', (e) => log(`uncaughtException: ${e.stack || e.message}`));
