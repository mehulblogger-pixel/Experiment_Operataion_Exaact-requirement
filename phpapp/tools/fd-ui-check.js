// ---------------------------------------------------------------------------
//  FORM DESIGNER — real-browser check.
//
//  The owner's three reports were: only two forms could be designed, there was
//  no visible way to hide or delete a field, and a field they added gave no say
//  in where it went. Server tests prove the values are stored; only a browser
//  proves the added field actually MOVES on the form it was placed in.
//
//    node tools/fd-ui-check.js [baseUrl] [user] [pass]
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
  const jsErrors = []; pg.on('pageerror', e => jsErrors.push(e.message));

  section('F1 · sign in and open the designer');
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');
  await pg.goto(BASE + '/form-designer'); await pg.waitForLoadState('networkidle');
  const cards = await pg.locator('a.card').count();
  ok(cards >= 8, 'F1a · the designer now offers ' + cards + ' forms (it offered 2)');
  const names = (await pg.locator('a.card div[style*="font-weight:700"]').allTextContents()).map(t => t.trim());
  ok(names.length === cards && names.every(n => n !== ''),
     'F1b · they are: ' + names.join(' · ').slice(0, 140));

  section('F2 · hide and delete are legible');
  await pg.goto(BASE + '/form-designer?form=requisition'); await pg.waitForLoadState('networkidle');
  const body = await pg.locator('body').innerText();
  ok(/Why is there no Delete here\?/.test(body), 'F2a · the screen explains why a standard field has no Delete');
  ok(/anything already recorded in it is kept/i.test(body), 'F2b · and says hiding keeps the data');
  const hideWord = await pg.locator('#fdForm td[data-l="Hide from the form?"] label span').first().textContent();
  ok(/Hide|Hidden/.test(hideWord), 'F2c · the hide tick box carries the word "' + hideWord.trim() + '", not just a box');
  ok(await pg.locator('#fdForm .pill', { hasText: 'Essential' }).count() > 0,
     'F2d · fields that cannot be hidden are marked Essential, not a bare dash');

  section('F3 · a new field can be told where to go');
  const secSel = pg.locator('select[name=nf_section]');
  ok(await secSel.count() === 1, 'F3a · the add form asks "Where should it go?"');
  const secs = await secSel.locator('option').allTextContents();
  ok(secs.length >= 5, 'F3b · offering this form\'s real sections: ' + secs.slice(0, 4).join(' · '));
  ok(secs.some(t => /More details/.test(t)), 'F3c · plus the old behaviour, named honestly');

  // Add a field into "Deployment" and prove it lands there on the real form.
  const LABEL = 'Gate Pass Number UAT';
  await pg.fill('input[name=nf_label]', LABEL);
  await secSel.selectOption('Deployment');
  await pg.locator('#fdAdd button[type=submit]').last().click();
  await pg.waitForLoadState('networkidle');
  ok((await pg.locator('body').innerText()).includes(LABEL), 'F3d · the field was added');

  section('F4 · and it really lands there on the form');
  await pg.goto(BASE + '/requisition-new'); await pg.waitForLoadState('networkidle');
  const placed = pg.locator('.ff[data-cf-section="Deployment"]');
  ok(await placed.count() === 1, 'F4a · the field renders and carries its section');
  const step = await placed.evaluate(el => {
    const sec = el.closest('.rq-sec[data-step]');
    return sec ? sec.getAttribute('data-step') : 'none';
  });
  ok(step === '2', 'F4b · it was MOVED into step 2 (Deployment) — it is in step ' + step);
  const heading = await placed.evaluate(el => {
    const sec = el.closest('.rq-sec[data-step]');
    const h = sec && sec.querySelector('h3');
    return h ? h.textContent.replace(/\s+/g, ' ').trim() : '';
  });
  ok(/Deployment/i.test(heading), 'F4c · under the heading "' + heading.slice(0, 40) + '"');
  const moreDetails = await pg.evaluate(() => {
    const hs = [...document.querySelectorAll('h3,h4')].filter(h => /more details/i.test(h.textContent));
    return hs.length ? getComputedStyle(hs[0]).display : 'absent';
  });
  ok(moreDetails === 'none' || moreDetails === 'absent',
     'F4d · the now-empty "More details" block is not left behind (' + moreDetails + ')');

  section('F5 · clean up');
  await pg.goto(BASE + '/form-designer?form=requisition'); await pg.waitForLoadState('networkidle');
  pg.on('dialog', d => d.accept());
  const delForm = pg.locator('form[action="/form-designer-field-del"]');
  ok(await delForm.count() >= 1, 'F5z ARMING — there is a Delete button for the field we added');
  await Promise.all([
    pg.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    delForm.last().locator('button').click(),
  ]);
  // NOT a body-text search: the success flash reads 'Removed the field "<label>"',
  // so the label is still on the page precisely BECAUSE the delete worked.
  // Check the field's own row is gone instead.
  const stillThere = await pg.locator('input[name=ef_label]').evaluateAll(
    (els, l) => els.some(e => e.value === l), LABEL);
  ok(!stillThere, 'F5a · a field you added can be deleted');
  ok(/Removed the field/.test(await pg.locator('body').innerText()), 'F5b · and the screen confirms it');

  section('F6 · nothing threw');
  ok(jsErrors.length === 0, 'F6 · no JavaScript errors' + (jsErrors.length ? ': ' + jsErrors.join(' | ') : ''));

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  if (fails.length) { console.log('FAILURES:'); fails.forEach(f => console.log('  - ' + f)); }
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('WALK CRASHED: ' + e.message); process.exit(2); });
