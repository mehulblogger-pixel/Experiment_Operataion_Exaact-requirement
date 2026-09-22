// Capture the REAL navigation, screen titles, form fields and screenshots, so
// the business playbook names what exists rather than what I remember.
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8801';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/uatshots';
const fs = require('fs');

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await br.newContext({ viewport: { width: 1440, height: 950 } });
  const pg = await ctx.newPage();
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', 'admin');
  await pg.fill('input[name=password]', 'admin12345');
  await pg.click('button[type=submit]');
  await pg.waitForLoadState('networkidle');
  if (/\/setup/.test(pg.url())) {
    await pg.fill('input[name=app_name]', 'EXAACT UAT Demo');
    for (const s of ['input[name=grievance_name]', 'input[name=grievance_email]'])
      if (await pg.locator(s).count()) await pg.fill(s, s.includes('email') ? 'uat@example.test' : 'UAT Officer').catch(()=>{});
    for (const s of ['select[name=industry]','select[name=date_format]','select[name=fy_start_month]','select[name=currency_symbol]']) {
      const e = pg.locator(s); if (await e.count() && await e.locator('option').count() > 1) await e.selectOption({index:1}).catch(()=>{});
    }
    await pg.locator('form[action="/setup-save"] button[type=submit]').first().click();
    await pg.waitForLoadState('networkidle');
  }
  const out = { nav: [], screens: [] };

  //  THE REAL LEFT-HAND NAVIGATION, as an administrator sees it.
  await pg.goto(BASE + '/', { waitUntil: 'networkidle' });
  out.nav = await pg.evaluate(() => Array.from(document.querySelectorAll('.side-nav a[href^="/"]'))
    .map(a => ({ label: (a.innerText||'').trim().replace(/\s+/g,' '), href: a.getAttribute('href') }))
    .filter(x => x.label));
  await pg.screenshot({ path: SHOTS + '/01-dashboard.png' });

  //  Key screens: real <h1>/title, and every labelled form field.
  const SCREENS = [
    ['/', '01-dashboard'], ['/recruitment','02-recruitment'], ['/requisitions','03-requisitions'],
    ['/requisition-new','04-requisition-new'], ['/candidates','05-candidates'],
    ['/candidate-new','06-candidate-new'], ['/inspectors','07-team'], ['/calls','08-calls'],
    ['/jobs','09-jobs'], ['/invoices','10-invoices'], ['/reports','11-reports'],
    ['/users','12-users'], ['/masters','13-masters'], ['/audit-log','14-audit'],
    ['/hiring-requests','15-hiring-requests'],
  ];
  for (const [path, shot] of SCREENS) {
    try {
      const r = await pg.goto(BASE + path, { waitUntil: 'networkidle', timeout: 20000 });
      const landed = pg.url().replace(BASE,'').split('?')[0];
      const info = await pg.evaluate(() => ({
        h1: (document.querySelector('h1')||{}).innerText || '',
        tabs: Array.from(document.querySelectorAll('button.tabbtn')).map(b=>b.innerText.trim()),
        fields: Array.from(document.querySelectorAll('input[name],select[name],textarea[name]'))
          .filter(e => e.type !== 'hidden')
          .map(e => { const l = e.closest('.ff,div,label'); const t = l ? (l.querySelector('label')||{}).innerText : '';
                      return { name: e.getAttribute('name'), tag: e.tagName.toLowerCase(), label: (t||'').trim().replace(/\s+/g,' ').slice(0,60) }; })
          .slice(0, 26),
        buttons: Array.from(document.querySelectorAll('button,a.btn')).map(b=>b.innerText.trim()).filter(Boolean).slice(0,14)
      }));
      out.screens.push({ path, landed, status: r?r.status():0, ...info });
      await pg.screenshot({ path: SHOTS + '/' + shot + '.png' });
    } catch (e) { out.screens.push({ path, error: String(e).slice(0,80) }); }
  }
  fs.writeFileSync('/tmp/claude-0/uat-capture.json', JSON.stringify(out, null, 1));
  console.log('nav entries: ' + out.nav.length);
  out.nav.forEach(n => console.log('  ' + n.label + '  ->  ' + n.href));
  await br.close();
})();
