// ---------------------------------------------------------------------------
//  B2 — what the user actually sees and can actually reach.
//
//  The server test proves the ordering in the source and the gates in the
//  engine. Only a browser proves the thing that mattered: whether the first
//  actionable section is on screen when the page opens, rather than two
//  screenfuls down.
//
//    node tools/b2-nav-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin', PASS = process.argv[4] || 'admin12345';
let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg = await ctx.newPage();
  const errs = [], bad = [];
  pg.on('pageerror', e => errs.push(e.message));
  pg.on('response', r => { if (r.status() >= 500) bad.push(r.status() + ' ' + r.url().replace(BASE, '')); });

  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');

  section('N1 · the Command Centre leads with work, not reporting');
  await pg.goto(BASE + '/recruitment-cc', { waitUntil: 'networkidle' });
  const cc = await pg.evaluate(() => {
    const bands = [...document.querySelectorAll('.band h2')].map(e => e.textContent.trim());
    const y = t => { const h = [...document.querySelectorAll('.band h2')].find(e => e.textContent.trim().startsWith(t));
                     return h ? Math.round(h.getBoundingClientRect().top + window.scrollY) : null; };
    const c = document.querySelector('.container');
    return { bands, attention: y('Needs attention today'), analysis: y('Analysis'),
             viewport: window.innerHeight,
             links: c.querySelectorAll('a[href]').length,
             distinct: new Set([...c.querySelectorAll('a[href]')].map(a => a.getAttribute('href'))).size,
             headings: [...c.querySelectorAll('h1,h2,h3')].filter(e => e.offsetParent !== null).length,
             height: document.documentElement.scrollHeight };
  });
  ok(cc.bands.length >= 8, `N1 ARMING · ${cc.bands.length} sections on the page`);
  ok(cc.bands[0] === 'Needs attention today',
     `N1a · the first section is "${cc.bands[0]}" (before B2 it was "Hiring demand & pipeline volume")`);
  ok(cc.attention !== null && cc.attention < cc.viewport,
     `N1b · it is ABOVE THE FOLD — ${cc.attention}px, inside a ${cc.viewport}px viewport (before: 2018px)`);
  ok(cc.analysis !== null && cc.analysis > cc.attention,
     `N1c · the Analysis divider sits below it at ${cc.analysis}px`);
  console.log(`        measured: height ${cc.height}px · ${cc.headings} visible headings · `
            + `${cc.links} links · ${cc.distinct} distinct destinations`);

  section('N2 · the hiring-request register is reachable, and works');
  ok(await pg.locator('a[href="/hiring-requests"]').count() >= 1,
     'N2a · the Command Centre offers a door to the hiring-request register');
  const r = await pg.goto(BASE + '/hiring-requests', { waitUntil: 'networkidle' });
  ok(r && r.status() < 400, `N2b · and it opens (HTTP ${r ? r.status() : '?'})`);
  const body = await pg.locator('body').innerText();
  ok(!/SQLSTATE|Fatal error|Undefined/i.test(body), 'N2c · with no technical text on it');

  section('N3 · nothing that used to be reachable was removed');
  await pg.goto(BASE + '/recruitment-cc', { waitUntil: 'networkidle' });
  //  UNCONDITIONAL destinations only. /my-approvals lives inside the Approvals
  //  band, which renders only when something is actually awaiting approval --
  //  it was absent on this dataset BEFORE B2 too. Asserting it here failed the
  //  check for a section that is working correctly. The full set, conditional
  //  sections included, is pinned at source level by test_b2_navigation_cc.php
  //  (guard C1), which is the right layer for it.
  const BEFORE = ['/availability','/candidate-new','/candidates','/careers-admin','/comp-setup',
    '/departments','/doc-templates','/positions','/positions-import','/positions-org',
    '/project-costings','/recruit-approvals','/recruit-pipelines','/recruitment','/requisition-new','/requisitions'];
  const present = await pg.evaluate(list => {
    const hrefs = [...document.querySelector('.container').querySelectorAll('a[href]')].map(a => a.getAttribute('href'));
    return list.filter(x => !hrefs.some(h => h === x || h.startsWith(x + '?')));
  }, BEFORE);
  ok(present.length === 0, `N3 · all ${BEFORE.length} unconditionally-rendered pre-B2 destinations are still on the page`
     + (present.length ? ' — missing: ' + present.join(', ') : ''));

  section('N4 · both recruitment homes still open');
  for (const [name, route] of [['compact recruitment home', '/recruitment'], ['Command Centre', '/recruitment-cc'],
                               ['my work', '/my-work'], ['management board', '/command-centre']]) {
    const res = await pg.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
    ok(res && res.status() < 400, `N4 · ${name} (${route}) opens — HTTP ${res ? res.status() : 'error'}`);
  }

  section('N5 · direct-URL authorization — the menu is not the boundary');
  //  Sign out, then ask for the routes directly. The server must refuse.
  await pg.goto(BASE + '/logout', { waitUntil: 'networkidle' }).catch(() => {});
  for (const route of ['/hiring-requests', '/recruitment-cc', '/requisition-new']) {
    const res = await pg.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
    const landed = pg.url().replace(BASE, '');
    const txt = await pg.locator('body').innerText().catch(() => '');
    const refused = /login|sign in|not have access|permission/i.test(txt) || landed.startsWith('/login');
    ok(refused, `N5 · signed out, ${route} is refused (landed on ${landed})`);
  }
  //  ARMING — signed back in, the same routes work, so N5 is not passing because
  //  the routes are simply broken.
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');
  const back = await pg.goto(BASE + '/hiring-requests', { waitUntil: 'networkidle' });
  ok(back && back.status() < 400 && !pg.url().includes('/login'),
     'N5 ARMING · signed back in, the same route opens — the refusal above was authorization, not breakage');

  section('N6 · mobile — navigation and recruitment entry still work');
  for (const [w, h] of [[360, 800], [390, 844], [412, 915]]) {
    await pg.setViewportSize({ width: w, height: h });
    await pg.goto(BASE + '/recruitment-cc', { waitUntil: 'networkidle' });
    const m = await pg.evaluate(() => ({
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      firstBand: (document.querySelector('.band h2') || {}).textContent,
      hiringDoor: document.querySelectorAll('a[href="/hiring-requests"]').length,
      menuBtn: document.querySelectorAll('.side-open, [aria-label*="enu"], .side-close').length,
    }));
    ok(m.overflow <= 2, `N6 · ${w}x${h} — no horizontal overflow (${m.overflow}px)`);
    ok((m.firstBand || '').trim() === 'Needs attention today', `N6 · ${w}x${h} — still leads with the work`);
    ok(m.hiringDoor >= 1, `N6 · ${w}x${h} — the recruitment entry is reachable`);
  }
  await pg.setViewportSize({ width: 1280, height: 900 });

  section('N7 · nothing broke');
  ok(errs.length === 0, 'N7 · no JavaScript errors' + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
  ok(bad.length === 0, 'N7 · no server errors' + (bad.length ? ': ' + bad.slice(0, 4).join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  fails.forEach(f => console.log('  - ' + f));
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('CHECK CRASHED: ' + e.message); process.exit(2); });
