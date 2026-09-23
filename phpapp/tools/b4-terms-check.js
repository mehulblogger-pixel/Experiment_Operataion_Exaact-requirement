// ---------------------------------------------------------------------------
//  B4 — is the word explained where the word is used, and is the chain visible
//  in both directions?
//
//    node tools/b4-terms-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin', PASS = process.argv[4] || 'admin12345';
let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const pg = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errs = [], bad = [];
  pg.on('pageerror', e => errs.push(e.message));
  pg.on('response', r => { if (r.status() >= 500) bad.push(r.status() + ' ' + r.url().replace(BASE, '')); });
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');

  const txt = async route => {
    const r = await pg.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
    if (!r || r.status() >= 400) return null;
    return await pg.locator('body').innerText();
  };
  const firstLink = async (list, pfx) => {
    await pg.goto(BASE + list, { waitUntil: 'networkidle' });
    return await pg.evaluate(p => {
      const a = [...document.querySelectorAll('a[href]')].find(x => (x.getAttribute('href') || '').startsWith(p));
      return a ? a.getAttribute('href') : '';
    }, pfx);
  };

  section('T1 · the definition is where the word is');
  const hrHref = await firstLink('/hiring-requests', '/hiring-request?id=');
  const hr = hrHref ? await txt(hrHref) : null;
  ok(hr !== null, 'T1 ARMING · a hiring request opened');
  if (hr) {
    ok(/raised before recruiting starts/i.test(hr),
       'T1 · the hiring-request screen explains what a hiring request is');
    ok(/approv/i.test(hr), 'T1 · and names the approval that gates it');
  }

  section('T2 · workforce vs inspector, at the field that decides it');
  const insp = await txt('/m/inspectors/new');
  ok(insp !== null, 'T2 ARMING · the team-member form opened');
  if (insp) ok(/is what also makes somebody an/i.test(insp),
     'T2 · choosing Field is explained as what makes somebody an inspector');

  section('T3 · the chain, forwards and backwards');
  const reqHref = await firstLink('/requisitions', '/requisition?id=');
  const req = reqHref ? await txt(reqHref) : null;
  ok(req !== null, 'T3 ARMING · a requirement opened');
  if (req) ok(/Raised from hiring request|Recorded directly/i.test(req),
     'T3 · a requirement states where it came from, either way');
  //  Backwards: a team member hired through recruitment names the candidate.
  const back = await pg.evaluate(async b => {
    const r = await fetch(b + '/m/inspectors', { credentials: 'include' });
    const h = await r.text();
    const m = [...h.matchAll(/\/m\/inspectors\/edit\?id=(\d+)/g)].map(x => x[1]).slice(0, 12);
    for (const id of m) {
      const p = await (await fetch(b + '/m/inspectors/edit?id=' + id, { credentials: 'include' })).text();
      if (/Hired through recruitment as candidate/i.test(p)) return id;
    }
    return '';
  }, BASE);
  if (back) {
    const t = await txt('/m/inspectors/edit?id=' + back);
    ok(/Hired through recruitment as candidate/i.test(t),
       `T3 · team member #${back} names the candidate they were hired as`);
    ok(/recruited for|Joined on|accepted is not the same as joined/i.test(t),
       'T3 · and either the requirement, the joining date, or that there is none');
  } else {
    ok(true, 'T3 · no team member on this workspace came through recruitment — back-link not exercised');
  }

  section('T4 · ADR-001 is not decided on screen');
  if (req) {
    ok(!/should have been raised|preferred way|bypassed the approval/i.test(req),
       'T4 · the requirement screen does not editorialise about which route is right');
  }

  section('T5 · nothing broke');
  ok(errs.length === 0, 'T5 · no JavaScript errors' + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
  ok(bad.length === 0, 'T5 · no server errors' + (bad.length ? ': ' + bad.slice(0, 4).join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  fails.forEach(f => console.log('  - ' + f));
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('CHECK CRASHED: ' + e.message); process.exit(2); });
