// ---------------------------------------------------------------------------
//  PHASE 7 FINAL GATE — REAL BROWSER UAT.
//
//  Gate A proved the recruitment chain over HTTP: routes, forms, CSRF, sessions.
//  It could not see anything a browser sees — whether a control is actually
//  visible, reachable, enabled, or whether the page throws in JavaScript. That
//  blind spot is not theoretical: the Mark-as-joined button existed, its route
//  worked, every server test passed, and a person could not click it, because
//  it sat inside a block the screen hides once a candidate is accepted.
//
//  This walks the same chain with a real Chromium, clicking real controls.
//
//    node tools/p7-browser-uat.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8801';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/shots';

let pass = 0, fail = 0;
const fails = [];
function ok(cond, msg) { if (cond) { pass++; console.log('  ok    ' + msg); } else { fail++; fails.push(msg); console.log('  FAIL  ' + msg); } }
function section(t) { console.log('\n== ' + t + ' =='); }

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg = await ctx.newPage();
  const jsErrors = [], netFails = [];
  pg.on('pageerror', e => jsErrors.push(e.message));
  pg.on('requestfailed', r => { const u = r.url(); if (!/favicon/.test(u)) netFails.push(u + ' — ' + (r.failure()?.errorText || '')); });

  // ---- 1. sign in, as a person does -------------------------------------
  section('B1 · sign in');
  await pg.goto(BASE + '/login');
  ok(await pg.locator('input[name=username]').isVisible(), 'B1a · the sign-in form is visible');
  await pg.fill('input[name=username]', USER);
  await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]');
  await pg.waitForLoadState('networkidle');
  ok(!/\/login/.test(pg.url()), 'B1 · signing in leaves the login page (now ' + pg.url().replace(BASE, '') + ')');

  // ---- 2. first-run setup, if the workspace asks for it ------------------
  //  Submitting is scoped to THIS form. A bare button[type=submit] picks up
  //  whatever the layout happens to put first, which is how the first run of
  //  this walk "completed" setup without saving anything.
  if (/\/setup/.test(pg.url())) {
    section('B2 · first-run setup');
    ok(await pg.locator('form[action="/setup-save"]').count() > 0, 'B2a · the setup form is on the page');
    await pg.fill('input[name=app_name]', 'Browser UAT Industrial');
    for (const [sel, val] of [['input[name=grievance_name]', 'UAT Officer'], ['input[name=grievance_email]', 'uat@example.test']]) {
      if (await pg.locator(sel).count()) await pg.fill(sel, val).catch(() => {});
    }
    for (const sel of ['select[name=industry]', 'select[name=date_format]', 'select[name=fy_start_month]', 'select[name=currency_symbol]']) {
      const el = pg.locator(sel);
      if (await el.count()) { const n = await el.locator('option').count(); if (n > 1) await el.selectOption({ index: 1 }).catch(() => {}); }
    }
    await pg.locator('form[action="/setup-save"] button[type=submit]').first().click();
    await pg.waitForLoadState('networkidle');
    const flash = (await pg.textContent('body').catch(() => '')) || '';
    //  Only the FIRST-RUN wizard at exactly /setup blocks the app. /workspace/setup
    //  is an ordinary settings screen and reaching it means the wizard released.
    const still = pg.url().replace(BASE, '').split('?')[0] === '/setup';
    ok(!still, 'B2 · the first-run wizard released the app (now ' + pg.url().replace(BASE, '') + ')'
       + (still ? ' :: ' + (flash.match(/msg-error[^>]*>([^<]{0,120})/) || [,''])[1] : ''));
  }


  //  Some selects are replaced by a searchable widget (data-enh="1"), which
  //  leaves the native element hidden. A plain selectOption times out on those.
  //  Visible ones are driven as a person would; enhanced ones are set on the
  //  underlying control and told they changed. Noted because it is a real
  //  difference in how the two were exercised.
  async function pick(sel, opt) {
    const el = pg.locator(sel);
    if (!(await el.count())) return false;
    try {
      if (await el.isVisible()) { await el.selectOption(opt, { timeout: 4000 }); return true; }
    } catch (e) { /* fall through to the programmatic path */ }
    return await pg.evaluate(([s, o]) => {
      const n = document.querySelector(s); if (!n) return false;
      const opts = Array.from(n.options).filter(x => x.value !== '');
      const t = (o && o.value) ? opts.find(x => x.value === o.value) : opts[0];
      if (!t) return false;
      n.value = t.value; n.dispatchEvent(new Event('change', { bubbles: true })); return true;
    }, [sel, opt || null]);
  }


  //  The applicant screen is tabbed (Overview, Pipeline, … Recruitment …) and
  //  the stage and joining panels live on Recruitment. A person clicks that tab;
  //  so does this walk. Not clicking it is why the first run reported the stage
  //  button as "not visible" — it was on another tab, which is not a defect.
  async function tab(name) {
    const b = pg.locator('button.tabbtn', { hasText: name });
    if (!(await b.count())) return false;
    await b.first().click();
    await pg.waitForTimeout(250);
    return true;
  }

  //  A helper that REFUSES to give a false pass: a page that redirected
  //  somewhere else is not the page we asked for, whatever status it returned.
  async function open(path, label) {
    const r = await pg.goto(BASE + path, { waitUntil: 'networkidle' });
    const body = (await pg.textContent('body').catch(() => '')) || '';
    const here = pg.url().replace(BASE, '').split('?')[0];
    const want = path.split('?')[0];
    const arrived = here === want;
    ok(arrived && r && r.status() < 400 && !/Fatal error|Uncaught|SQLSTATE/i.test(body),
       label + ' (' + (r ? r.status() : '?') + (arrived ? '' : ' — REDIRECTED to ' + here) + ')');
    return arrived;
  }

  // ---- 3. the recruitment screens actually render ------------------------
  section('B3 · the recruitment screens');
  await open('/requisitions', 'B3a · the requirements register renders');
  await open('/candidates',   'B3b · the applicants register renders');
  await open('/recruitment',  'B3c · the recruitment home renders');

  // ---- 4. the requirement carries the team decision ----------------------
  section('B4 · the requirement says which team');
  await open('/requisition-new', 'B4a · the new-requirement form renders');
  const trSel = pg.locator('select[name=team_role]');
  const trCount = await trSel.count();
  ok(trCount > 0, 'B4b · the form offers a team choice');
  if (trCount) {
    ok(await trSel.isVisible(), 'B4c · …and it is VISIBLE, not merely in the markup');
    const opts = (await trSel.locator('option').allTextContents()).filter(Boolean);
    ok(opts.join('|').includes('Field') && opts.join('|').includes('Coordinator'),
       'B4 · …with the real vocabulary [' + opts.slice(0, 4).join(' / ') + ']');
    await pick('select[name=team_role]', { value: 'COORD' });
  }
  await pick('select[name=office_id]');
  await pick('select[name=designation]');
  await pick('select[name=sbu]');
  if (await pg.locator('input[name=quantity]').count()) await pg.fill('input[name=quantity]', '2');
  //  SCOPED to the requirement's own form. A bare form-button selector matched
  //  the layout's global search box first, and "saved" by landing on /search —
  //  a pass that proved nothing. The walk asserts where it actually arrived.
  await pg.locator('form#rqForm button[type=submit]').first().click();
  await pg.waitForLoadState('networkidle');
  const rqLanded = pg.url().replace(BASE, '');
  ok(/requisition\?id=\d+|requisitions/.test(rqLanded),
     'B4d · the requirement saved and landed on the requirement (' + rqLanded + ')');
  await pg.screenshot({ path: SHOTS + '/b4-requisition.png' }).catch(() => {});

  // ---- 5. an applicant, through the form ---------------------------------
  section('B5 · the applicant');
  const rqId = (pg.url().match(/id=(\d+)/) || [,''])[1];
  await open('/candidate-new' + (rqId ? '?requisition_id=' + rqId : ''), 'B5a · the new-applicant form renders');
  for (const [sel, val] of [['input[name=first_name]', 'Browser'], ['input[name=last_name]', 'Applicant'],
                            ['input[name=email]', 'browser.applicant@uat.test'], ['input[name=mobile]', '9811122233']]) {
    if (await pg.locator(sel).count()) await pg.fill(sel, val).catch(() => {});
  }
  if (rqId) await pick('select[name=requisition_id]', { value: rqId });
  await pick('select[name=sbu]');
  //  The applicant screen has TWO forms posting to /candidate-new — one is the
  //  CV upload. Scope to the one that actually holds the name fields, or the
  //  walk submits an empty upload and reports "saved" having saved nothing.
  await pg.locator('form:has(input[name=first_name]) button[type=submit]').first().click();
  await pg.waitForLoadState('networkidle');
  const candId = (pg.url().match(/id=(\d+)/) || [,''])[1];
  ok(!!candId, 'B5 · the applicant saved (candidate ' + (candId || 'NONE') + ')');

  // ---- 6. acceptance, as a recruiter does it -----------------------------
  section('B6 · acceptance');
  if (candId) {
    await open('/candidate?id=' + candId, 'B6a · the applicant screen renders');
    ok(await tab('Recruitment'), 'B6a1 · the Recruitment tab is reachable and opens');
    ok(await pg.locator('[name=make_inspector]').count() === 0,
       'B6b · there is NO "also add to Inspectors" tick — accepting IS hiring (RB-1)');
    const stg = pg.locator('select[name=to_stage]');
    ok(await stg.count() > 0 && await stg.first().isVisible().catch(() => false),
       'B6c · the stage control is VISIBLE on that tab');
    await pick('select[name=to_stage]', { value: 'ACCEPTED' });
    await pg.waitForTimeout(500);
    const teamAtAccept = pg.locator('select[name=team_role]');
    const teamShown = await teamAtAccept.count() > 0 && await teamAtAccept.first().isVisible().catch(() => false);
    ok(teamShown, 'B6d · choosing Accept REVEALS the team confirmation, visibly');
    await pg.screenshot({ path: SHOTS + '/b6-accept.png' }).catch(() => {});
    await pg.locator('form[action^="/candidate-stage"] button[type=submit]').first().click();
    await pg.waitForLoadState('networkidle');
    const after = (await pg.textContent('body').catch(() => '')) || '';
    ok(!/Fatal error|Uncaught|SQLSTATE/i.test(after), 'B6e · the acceptance produced no raw error on screen');
    ok(/Accepted/i.test(after), 'B6 · the applicant is shown as Accepted (Hired)');
  }

  // ---- 7. THE JOINED DEFECT — visual reachability ------------------------
  //  §13. The route worked and every server test passed while the button was
  //  unreachable, because it sat inside a block the screen hides once somebody
  //  is accepted. This asserts what a person can actually click.
  section('B7 · Mark as joined is REACHABLE');
  if (candId) {
    await open('/candidate?id=' + candId, 'B7a · the accepted applicant screen renders');
    await tab('Recruitment');
    const joinBtn = pg.locator('form[action^="/candidate-joined"] button[type=submit]');
    const n7 = await joinBtn.count();
    ok(n7 > 0, 'B7b · a Mark-as-joined control exists on an ACCEPTED applicant');
    ok(n7 > 0 && await joinBtn.first().isVisible().catch(() => false),
       'B7c · …and it is VISIBLE — the defect was that it was not');
    ok(n7 > 0 && await joinBtn.first().isEnabled().catch(() => false), 'B7d · …and enabled');
    await pg.screenshot({ path: SHOTS + '/b7-before-join.png' }).catch(() => {});
    if (n7 > 0) {
      await joinBtn.first().click();
      await pg.waitForLoadState('networkidle');
      const body7 = (await pg.textContent('body').catch(() => '')) || '';
      ok(/Joined on/i.test(body7), 'B7 · clicking it shows the person as JOINED');
      //  The save redirects back to the applicant; the screen reopens on its
      //  default tab, so the walk returns to Recruitment exactly as a person
      //  would. (Whether the tab should be remembered is a UX note, not a
      //  defect — the control is reachable either way.)
      const onRecruit = await tab('Recruitment');
      const undo = pg.locator('form[action^="/candidate-joined"] button[type=submit]');
      ok(await undo.count() > 0 && await undo.first().isVisible().catch(() => false),
         'B7e · …and the reversal control is visible, so a mistake can be undone');
      await pg.screenshot({ path: SHOTS + '/b7-joined.png' }).catch(() => {});
    }
  }

  // ---- 8. negative tests, as a person would meet them --------------------
  section('B8 · negative tests');
  await open('/user-new', 'B8a · the add-a-person form renders');
  const dupBody = (await pg.textContent('body').catch(() => '')) || '';
  ok(!/Fatal error|SQLSTATE|Uncaught/i.test(dupBody), 'B8b · …with no raw error on it');
  await pg.goto(BASE + '/logout', { waitUntil: 'networkidle' }).catch(() => {});
  await pg.goto(BASE + '/candidates', { waitUntil: 'networkidle' });
  const at8 = pg.url().replace(BASE, '');
  ok(/login/.test(at8), 'B8 · signed out, a protected register sends you to sign in (' + at8 + ')');
  const body8 = (await pg.textContent('body').catch(() => '')) || '';
  ok(!/Fatal error|SQLSTATE|Uncaught|stack trace/i.test(body8), 'B8c · …with no stack trace, SQL error or blank page');

  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('JS errors: ' + jsErrors.length + (jsErrors.length ? ' -> ' + jsErrors.slice(0, 3).join(' | ') : ''));
  console.log('Failed requests: ' + netFails.length + (netFails.length ? ' -> ' + netFails.slice(0, 3).join(' | ') : ''));
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  if (fails.length) { console.log('FAILURES:'); fails.forEach(f => console.log('  - ' + f)); }
  process.exit(fail || jsErrors.length ? 1 : 0);
})();
