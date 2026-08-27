// Slice P3 — offline sync-state vocabulary. Loads the real assets/js/offline.js
// inside a stubbed browser sandbox (no server, no DOM) and asserts the pure
// banner-decision function exposes the five uniform states correctly.
//
//   node tests/js/sync_state.test.mjs
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.join(__dirname, '../../assets/js/offline.js'), 'utf8');

const noop = () => {};
const win = {};
win.addEventListener = noop;
const sandbox = {
  window: win,
  document: { addEventListener: noop, createElement: () => ({ style: {} }), querySelectorAll: () => [], body: { appendChild: noop }, hidden: false },
  navigator: { onLine: true },        // no serviceWorker key → registration is skipped
  localStorage: { getItem: () => null, setItem: noop, removeItem: noop },
  location: { pathname: '/x', search: '', reload: noop },
  setTimeout: noop, clearTimeout: noop,
  fetch: () => Promise.resolve({ ok: true }),
  URLSearchParams, FormData: function () { this.forEach = noop; }, Event: function () {},
};
vm.createContext(sandbox);
vm.runInContext(code, sandbox);

const f = win.idemsSyncState;
let count = 0, pass = 0;
function ok(cond, msg) { count++; if (cond) { pass++; console.log('  ok    ' + msg); } else { console.log('  FAIL  ' + msg); process.exitCode = 1; } }

ok(typeof f === 'function', 'offline.js exposes window.idemsSyncState');

// Saved locally (offline, nothing queued yet)
const off0 = f({ online: false, queued: 0 });
ok(off0.key === 'OFFLINE' && /saved on this device/i.test(off0.text), 'offline → "saved on this device"');
// Pending sync surfaced while offline with a queue
ok(/pending sync/i.test(f({ online: false, queued: 2 }).text), 'offline with a queue mentions pending sync');
// Pending sync (online, queued, not yet syncing)
ok(f({ online: true, queued: 3, syncing: false }).key === 'PENDING', 'online + queued + not syncing → PENDING');
// Syncing
ok(f({ online: true, queued: 3, syncing: true }).key === 'SYNCING', 'online + syncing → SYNCING');
// Synced
ok(f({ online: true, queued: 0, justSynced: true }).key === 'SYNCED', 'nothing queued + justSynced → SYNCED');
// Sync failed (with a retry affordance)
const fail = f({ online: true, queued: 1, failed: true });
ok(fail.key === 'FAILED' && fail.retry === true, 'failed while online → FAILED with a Retry affordance');
// Failed only matters when something is actually queued
ok(f({ online: true, queued: 0, failed: true }).key === 'IDLE', 'failed but empty queue → nothing to show');
// Idle
ok(f({ online: true, queued: 0 }).show === false, 'online, nothing pending → banner hidden');
// Singular vs plural wording
ok(/1 item pending/.test(f({ online: true, queued: 1 }).text), 'singular wording for one item');
ok(/2 items pending/.test(f({ online: true, queued: 2 }).text), 'plural wording for many items');

console.log('\n  ' + pass + '/' + count + ' sync-state assertions passed');
if (pass !== count) process.exitCode = 1;
