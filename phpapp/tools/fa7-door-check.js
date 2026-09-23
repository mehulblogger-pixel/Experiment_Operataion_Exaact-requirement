// ---------------------------------------------------------------------------
//  F-A7-1 — what the PERSON sees at the Masters "add a person" door.
//
//  The server test proves the wiring. Only a browser proves the experience,
//  and the experience was the defect: the duplicate was always refused, but
//  the refusal arrived as SQLSTATE[23000] on screen.
//
//    node tools/fa7-door-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin', PASS = process.argv[4] || 'admin12345';
const STAMP = Date.now().toString().slice(-6);
const EMAIL = `fa7.ui.${STAMP}@example.com`, LAST = `Verma FA7 ${STAMP}`;
let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const pg = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errs = []; pg.on('pageerror', e => errs.push(e.message));
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');

  // SCOPED to this form. A bare button[type=submit] picks up the layout's
  // search box — the false pass that has bitten this session twice already.
  async function addPerson() {
    await pg.goto(BASE + '/m/inspectors/new'); await pg.waitForLoadState('networkidle');
    const f = pg.locator('form[action="/m/inspectors/new"]');
    if (await f.count() !== 1) return { err: 'the add form was not on the page' };
    await f.locator('input[name=first_name]').fill('Arun');
    await f.locator('input[name=last_name]').fill(LAST);
    await f.locator('input[name=email]').fill(EMAIL);
    await f.locator('button[type=submit]').first().click();
    await pg.waitForLoadState('networkidle');
    const body = await pg.locator('body').innerText();
    return { url: pg.url().replace(BASE, ''), body };
  }

  section('U1 · the first person is added normally');
  let r = await addPerson();
  ok(!r.err && /m\/inspectors\/edit/.test(r.url), 'U1a · saved and opened the record (' + r.url + ')');
  ok(!/SQLSTATE|Integrity constraint|Fatal error/i.test(r.body), 'U1b · no technical text');

  section('U2 · the SAME person again — the defect');
  r = await addPerson();
  const sqlstate = /SQLSTATE|Integrity constraint violation|1062 Duplicate entry/i.test(r.body);
  ok(!sqlstate, 'U2a · NO raw database error is shown (this was the defect)');
  ok(/may already be on your team/i.test(r.body),
     'U2b · the user is told in words that this person may already be on the team');
  ok(new RegExp(LAST.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i').test(r.body),
     'U2c · and NAMED — the same courtesy the other two doors give');
  ok(await pg.locator('input[name=dup_ack]').count() === 1,
     'U2d · an acknowledgement tick is offered, so a real second engagement is not blocked');

  section('U3 · the typed values survive the refusal');
  const kept = await pg.locator('form[action="/m/inspectors/new"] input[name=email]').inputValue().catch(() => '');
  ok(kept === EMAIL, 'U3a · the e-mail typed is still in the form (no re-typing) — "' + kept + '"');
  const firstKept = await pg.locator('form[action="/m/inspectors/new"] input[name=first_name]').inputValue().catch(() => '');
  ok(firstKept === 'Arun', 'U3b · and the first name');
  ok(await pg.locator('form[action="/m/inspectors/new"]').count() === 1,
     'U3c · it re-posts to /new, not /edit?id=0');

  //  U3d/e/f — THE PART U3c ALONE DID NOT PROVE.
  //  The MAIN form posting to /new says nothing about the rest of the page.
  //  The signature pad, the certificate register and the Super-Admin
  //  allowances form are separate <form>s, and each was still keyed off
  //  "$ins is truthy" — which a refused add satisfies. They would have
  //  rendered three forms posting to /m/inspectors/edit?id=0 and a KYC link
  //  to /identity?i=0, on a page for a person who does not exist yet.
  const zeroForms = await pg.locator('form[action*="id=0"]').count();
  ok(zeroForms === 0, 'U3d · NO form on the refused page posts to a record id of 0 (found ' + zeroForms + ')');
  const zeroLinks = await pg.locator('a[href*="?i=0"], a[href*="id=0"]').count();
  ok(zeroLinks === 0, 'U3e · and no link points at record 0 (found ' + zeroLinks + ')');
  //  ...and the opposite error: the add-only section must NOT have vanished.
  ok(/First certificate/i.test(r.body),
     'U3f · the add-only "First certificate" section is still offered — a refused add is still an add');

  section('U4 · acknowledging proceeds — and does not merge');
  //  A person ticks the box ON THE REFUSED PAGE and presses the button again —
  //  they do not start the form over. Calling addPerson({ack:true}) navigated
  //  to a FRESH /new, where the tick does not exist yet (it appears only after
  //  a refusal), so nothing was ticked and the add was refused a second time.
  //  That was the test being wrong, not the app.
  const refused = pg.locator('form[action="/m/inspectors/new"]');
  await refused.locator('input[name=dup_ack]').check();
  await refused.locator('button[type=submit]').first().click();
  await pg.waitForLoadState('networkidle');
  r = { url: pg.url().replace(BASE, ''), body: await pg.locator('body').innerText() };
  ok(!r.err && /m\/inspectors\/edit/.test(r.url), 'U4a · an acknowledged second engagement is allowed (' + r.url + ')');
  ok(!/SQLSTATE|Integrity constraint/i.test(r.body), 'U4b · still no technical text');
  await pg.goto(BASE + '/m/inspectors?q=' + encodeURIComponent(LAST)); await pg.waitForLoadState('networkidle');
  const listed = (await pg.locator('body').innerText().catch(() => '')).split(LAST).length - 1;
  ok(listed >= 2, 'U4c · TWO separate records exist — acknowledging is not merging (found ' + listed + ')');

  section('U5 · nothing threw');
  ok(errs.length === 0, 'U5 · no JavaScript errors' + (errs.length ? ': ' + errs.join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  fails.forEach(f => console.log('  - ' + f));
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('WALK CRASHED: ' + e.message); process.exit(2); });
