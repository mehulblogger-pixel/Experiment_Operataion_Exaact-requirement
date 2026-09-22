// ---------------------------------------------------------------------------
//  REQUIREMENT FORM — real-browser check of the five-step rebuild.
//
//  Server tests prove the engine. They cannot see whether a control is visible,
//  reachable or enabled, nor whether the page throws in JavaScript. That blind
//  spot has bitten this codebase before: the Mark-as-joined button existed, its
//  route worked, every server test passed, and nobody could click it.
//
//    node tools/rqform-ui-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const SHOTS = process.env.SHOTS || '/tmp/rqv-shots';

let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg = await ctx.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(e.message));

  // A page that redirected somewhere else is not the page we asked for. The
  // first run of an earlier walk "saved" a requirement onto /search this way.
  async function open(path) {
    await pg.goto(BASE + path);
    await pg.waitForLoadState('networkidle');
    return pg.url().includes(path.split('?')[0]);
  }

  section('U1 · sign in and reach the form');
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER);
  await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]');
  await pg.waitForLoadState('networkidle');
  ok(!/\/login/.test(pg.url()), 'U1a · signed in');

  // First run only. On a workspace already set up this never appears, and on a
  // half-set-up one the field may be absent — either way, do not crash the walk.
  if (/\/setup/.test(pg.url()) && await pg.locator('input[name=app_name]').count()) {
    await pg.fill('input[name=app_name]', 'RQ Form UAT');
    for (const s of ['input[name=grievance_name]', 'input[name=grievance_email]'])
      if (await pg.locator(s).count()) await pg.fill(s, 'uat@example.test').catch(() => {});
    for (const s of ['select[name=industry]', 'select[name=date_format]', 'select[name=fy_start_month]', 'select[name=currency_symbol]']) {
      const el = pg.locator(s);
      if (await el.count() && await el.locator('option').count() > 1) await el.selectOption({ index: 1 }).catch(() => {});
    }
    await pg.locator('form[action="/setup-save"] button[type=submit]').first().click();
    await pg.waitForLoadState('networkidle');
  }
  ok(await open('/requisition-new'), 'U1b · the New requirement form opens');

  section('U2 · the five steps, and only one of them at a time');
  ok(await pg.locator('.rq-steps button').count() === 5, 'U2a · five steps are on the rail');
  const vis = async n => pg.locator(`.rq-sec[data-step="${n}"]`).first().isVisible();
  ok(await vis(1), 'U2b · step 1 is showing');
  ok(!(await vis(2)) && !(await vis(4)), 'U2c · steps 2 and 4 are not — the page is no longer one long scroll');
  const count1 = (await pg.locator('#rq_count').textContent()).trim();
  ok(/Step 1 of 5/.test(count1), 'U2d · progress reads "' + count1.slice(0, 34) + '…"');
  const w1 = await pg.locator('#rq_bar_i').evaluate(e => e.style.width);
  ok(w1 === '20%', 'U2e · the progress bar shows one fifth (' + w1 + ')');

  section('U3 · the primary action is reachable from step 1');
  ok(await pg.locator('#rq_save').isVisible(), 'U3a · Save is visible without leaving step 1');
  const savePos = await pg.locator('#rq_save').boundingBox();
  ok(savePos && savePos.y < 900, 'U3b · and it is on screen, not below 60 fields (y=' + Math.round(savePos.y) + ')');
  ok(await pg.locator('#rq_prev').isHidden(), 'U3c · Back is hidden on the first step');

  section('U4 · discipline comes from the master the people register uses');
  const trOpts = await pg.locator('#rq_trade option').count();
  ok(trOpts >= 10, 'U4a · the discipline list is populated (' + trOpts + ' options incl. "Something else")');
  const skDisabled = await pg.locator('#rq_skill').isDisabled();
  ok(skDisabled, 'U4b · speciality is disabled until a discipline is chosen');

  // Pick a real discipline and prove the speciality list narrows to ITS children.
  const weldVal = await pg.locator('#rq_trade option', { hasText: 'Welding' }).first().getAttribute('value');
  await pg.selectOption('#rq_trade', weldVal);
  await pg.waitForTimeout(150);
  const skTexts = await pg.locator('#rq_skill option').allTextContents();
  ok(skTexts.length > 2, 'U4c · choosing Welding fills the speciality list (' + (skTexts.length - 2) + ' specialities)');
  ok(skTexts.some(t => /CSWIP|AWS|Welder|WPS/i.test(t)), 'U4d · and they are WELDING specialities: ' + skTexts.slice(1, 4).join(' · '));
  ok(!skTexts.some(t => /Transformer|Concrete|Analyser/i.test(t)), 'U4e · not another discipline\'s — no Transformers or Concrete here');

  // Switching discipline must re-narrow, not append.
  const elecVal = await pg.locator('#rq_trade option', { hasText: 'Electrical' }).first().getAttribute('value');
  await pg.selectOption('#rq_trade', elecVal);
  await pg.waitForTimeout(150);
  const skTexts2 = await pg.locator('#rq_skill option').allTextContents();
  ok(!skTexts2.some(t => /CSWIP|WPS/i.test(t)), 'U4f · switching to Electrical clears the welding specialities');
  ok(skTexts2.some(t => /Cable|Transformer|Switchgear|Motor/i.test(t)), 'U4g · and offers electrical ones: ' + skTexts2.slice(1, 4).join(' · '));

  section('U5 · "Something else" still lets a person type');
  await pg.selectOption('#rq_trade', '0');
  await pg.waitForTimeout(120);
  ok(await pg.locator('#rq_disc_free').isVisible(), 'U5a · choosing "Something else" reveals the free-text box');
  ok(await pg.locator('#rq_disc_free input[name=discipline]').isEditable(), 'U5b · and it can be typed into');
  await pg.selectOption('#rq_trade', weldVal);
  await pg.waitForTimeout(120);
  ok(await pg.locator('#rq_disc_free').isHidden(), 'U5c · picking a real discipline hides it again');

  section('U6 · certificates as chips');
  // The certificate field lives behind this step's "More detail".
  const more = pg.locator('.rq-sec[data-step="1"] button', { hasText: 'More detail' });
  if (await more.count()) { await more.first().click(); await pg.waitForTimeout(120); }
  const certN = await pg.locator('#rq_cert_pick option').count();
  ok(certN >= 20, 'U6a · the certificate master is offered (' + (certN - 1) + ' certificates)');
  const firstCert = await pg.locator('#rq_cert_pick option').nth(1).getAttribute('value');
  await pg.selectOption('#rq_cert_pick', firstCert);
  await pg.waitForTimeout(120);
  ok(await pg.locator('#rq_cert_chips .chip').count() === 1, 'U6b · choosing one adds a chip');
  const stored = await pg.locator('#rq_skills_val').inputValue();
  ok(stored === firstCert, 'U6c · and it is stored in the same `skills` field as before: "' + stored.slice(0, 40) + '"');
  await pg.locator('#rq_cert_chips .chip button').first().click();
  await pg.waitForTimeout(100);
  ok(await pg.locator('#rq_cert_chips .chip').count() === 0, 'U6d · the × removes it');
  ok(await pg.locator('#rq_skills_val').inputValue() === '', 'U6e · and clears the stored value');
  await pg.selectOption('#rq_cert_pick', firstCert);
  await pg.waitForTimeout(100);

  section('U7 · stepping forward and back');
  await pg.click('#rq_next'); await pg.waitForTimeout(250);
  ok(await vis(2), 'U7a · Next reaches step 2');
  ok(!(await vis(1)), 'U7b · and step 1 is put away');
  ok(await pg.locator('#rq_prev').isVisible(), 'U7c · Back appears');
  await pg.click('#rq_prev'); await pg.waitForTimeout(250);
  ok(await vis(1), 'U7d · Back returns to step 1');
  await pg.locator('.rq-steps button[data-step="5"]').click(); await pg.waitForTimeout(200);
  ok(await vis(5), 'U7e · the rail jumps straight to a step');
  await pg.locator('.rq-steps button[data-step="1"]').click(); await pg.waitForTimeout(200);

  section('U8 · save from step 1, and the words follow the link');
  await pg.selectOption('#rq_trade', weldVal); await pg.waitForTimeout(150);
  const skPick = await pg.locator('#rq_skill option').nth(1).getAttribute('value');
  await pg.selectOption('#rq_skill', skPick);
  const skLabel = (await pg.locator('#rq_skill option').nth(1).textContent()).trim();
  const desigN = await pg.locator('select[name=designation] option').count();
  if (desigN > 1) await pg.selectOption('select[name=designation]', { index: 1 }).catch(() => {});
  await pg.fill('#rq_qty', '2');
  await pg.locator('#rq_save').click();
  await pg.waitForLoadState('networkidle');
  ok(!/requisition-new/.test(pg.url()), 'U8a · saving from step 1 leaves the form (now ' + pg.url().replace(BASE, '') + ')');
  const body = await pg.locator('body').innerText();
  ok(/Welding/i.test(body), 'U8b · the saved requirement shows the DISCIPLINE IN WORDS, not an id');
  ok(body.includes(skLabel) || /Welding/i.test(body), 'U8c · and the speciality "' + skLabel + '"');

  section('U9 · a phone, at 390x844');
  const m = await br.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const mp = await m.newPage();
  await mp.goto(BASE + '/login');
  await mp.fill('input[name=username]', USER); await mp.fill('input[name=password]', PASS);
  await mp.click('button[type=submit]'); await mp.waitForLoadState('networkidle');
  await mp.goto(BASE + '/requisition-new'); await mp.waitForLoadState('networkidle');
  // Measured on a page this work never touched as well as on the form, because
  // the 6px that showed up here first was app-wide: the top bar's eight
  // controls would not fit 390px and would not wrap. Holding only the form to
  // zero would have passed while every other page still slid sideways.
  await mp.goto(BASE + '/requisitions'); await mp.waitForLoadState('networkidle');
  const base = await mp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  await mp.goto(BASE + '/requisition-new'); await mp.waitForLoadState('networkidle');
  const over = await mp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  ok(over <= 1, 'U9a · no sideways scrolling on this form (' + over + 'px)');
  ok(base <= 1, 'U9a2 · nor on a page this work never touched (' + base + 'px) — the top bar now wraps');
  const fs = await mp.locator('.rq-sec[data-step="1"] label').first().evaluate(e => parseFloat(getComputedStyle(e).fontSize));
  ok(fs >= 16, 'U9b · field labels are ' + fs + 'px — the blueprint floor is 16');
  const ctl = await mp.locator('.rq-sec[data-step="1"] select.form-control').first().boundingBox();
  ok(ctl && ctl.height >= 44, 'U9c · controls are ' + Math.round(ctl.height) + 'px tall — tappable with gloves');
  const sv = await mp.locator('#rq_save').boundingBox();
  ok(sv && sv.y < 844, 'U9d · Save is on screen without scrolling (y=' + Math.round(sv.y) + ')');
  const chk = await mp.locator('.rq-chk label').first();
  await mp.locator('.rq-steps button[data-step="3"]').click(); await mp.waitForTimeout(200);
  const cb = await chk.boundingBox();
  ok(cb && cb.height >= 44, 'U9e · compliance tick rows are ' + Math.round(cb.height) + 'px — not a 16px box');
  require('fs').mkdirSync(SHOTS, { recursive: true });
  await mp.locator('.rq-steps button[data-step="1"]').click(); await mp.waitForTimeout(200);
  await mp.screenshot({ path: SHOTS + '/phone-step1.png', fullPage: true });
  await mp.locator('.rq-steps button[data-step="2"]').click(); await mp.waitForTimeout(200);
  await mp.screenshot({ path: SHOTS + '/phone-step2.png', fullPage: true });
  await mp.locator('.rq-steps button[data-step="4"]').click(); await mp.waitForTimeout(200);
  await mp.screenshot({ path: SHOTS + '/phone-step4.png', fullPage: true });
  console.log('  ..    screenshots in ' + SHOTS);

  section('U10 · nothing threw');
  ok(jsErrors.length === 0, 'U10 · no JavaScript errors' + (jsErrors.length ? ': ' + jsErrors.join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  if (fails.length) { console.log('FAILURES:'); fails.forEach(f => console.log('  - ' + f)); }
  await br.close();
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('WALK CRASHED: ' + e.message); process.exit(2); });
