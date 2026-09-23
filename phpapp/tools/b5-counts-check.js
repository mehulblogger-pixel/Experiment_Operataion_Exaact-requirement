// ---------------------------------------------------------------------------
//  B5 — do the badges actually appear, on a workspace that has data?
//
//  The server test proves the wiring and the gates. Only a browser proves the
//  badge is painted, and only a POPULATED workspace can tell a wired tile from
//  an unwired one -- an empty one shows nothing either way, which is exactly
//  how the original audit came to read "0 tiles carry a count".
//
//    node tools/b5-counts-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin', PASS = process.argv[4] || 'admin12345';
let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

const AREAS = ['sales', 'quality', 'reporting', 'money', 'marketplace', 'directory', 'insights', 'admin'];

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const pg = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errs = [], bad = [];
  pg.on('pageerror', e => errs.push(e.message));
  pg.on('response', r => { if (r.status() >= 500) bad.push(r.status() + ' ' + r.url().replace(BASE, '')); });
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');

  section('C1 · badges on a populated workspace');
  let tiles = 0, badged = 0, zeros = 0; const seen = [];
  for (const a of AREAS) {
    const r = await pg.goto(BASE + '/' + a, { waitUntil: 'networkidle' }).catch(() => null);
    if (!r || r.status() >= 400) { console.log(`  --    /${a} not reachable — skipped`); continue; }
    //  The markup is area_home.php's own: an .op-tile per destination, with an
    //  .op-badge carrying the count beside the .op-t label. An earlier version
    //  of this check guessed .master-card / .tile / .qcard and found 0 tiles --
    //  it was looking for a component this screen does not use.
    const m = await pg.evaluate(() => {
      const out = { tiles: 0, badged: 0, zeros: 0, labels: [] };
      document.querySelectorAll('.op-tile').forEach(c => {
        out.tiles++;
        const badge = c.querySelector('.op-badge');
        if (!badge) return;
        out.badged++;
        const v = parseInt((badge.textContent || '').replace(/\D+/g, ''), 10);
        if (v === 0) out.zeros++;
        const label = (c.querySelector('.op-t')?.textContent || '').trim();
        out.labels.push(label.slice(0, 40) + '  [' + (badge.className.replace('op-badge', '').trim() || 'info') + ']');
      });
      return out;
    });
    tiles += m.tiles; badged += m.badged; zeros += m.zeros;
    m.labels.forEach(l => seen.push(a + ' · ' + l));
  }
  ok(tiles > 50, `C1 ARMING · ${tiles} tiles rendered across ${AREAS.length} area homes`);
  ok(badged > 0, `C1 · ${badged} of them carry a count badge`);
  ok(zeros === 0, `C1 · and none of them shows a literal 0 (${zeros} found)`);
  seen.slice(0, 20).forEach(s => console.log('        ' + s));

  section('C2 · the area homes still load cleanly and quickly');
  let slowest = 0, slowestArea = '';
  for (const a of AREAS) {
    const t0 = Date.now();
    const r = await pg.goto(BASE + '/' + a, { waitUntil: 'networkidle' }).catch(() => null);
    const ms = Date.now() - t0;
    if (!r || r.status() >= 400) continue;
    if (ms > slowest) { slowest = ms; slowestArea = a; }
    const body = await pg.locator('body').innerText();
    ok(!/SQLSTATE|Fatal error|Undefined/i.test(body), `C2 · /${a} shows no technical text`);
  }
  ok(slowest < 6000, `C2 · the slowest area home was /${slowestArea} at ${slowest}ms — counting did not make navigation slow`);

  section('C3 · mobile');
  for (const [w, h] of [[360, 800], [390, 844]]) {
    await pg.setViewportSize({ width: w, height: h });
    await pg.goto(BASE + '/quality', { waitUntil: 'networkidle' });
    const o = await pg.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    ok(o <= 2, `C3 · ${w}x${h} — no horizontal overflow (${o}px)`);
  }
  await pg.setViewportSize({ width: 1280, height: 900 });

  section('C4 · nothing broke');
  ok(errs.length === 0, 'C4 · no JavaScript errors' + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
  ok(bad.length === 0, 'C4 · no server errors' + (bad.length ? ': ' + bad.slice(0, 4).join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  fails.forEach(f => console.log('  - ' + f));
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('CHECK CRASHED: ' + e.message); process.exit(2); });
