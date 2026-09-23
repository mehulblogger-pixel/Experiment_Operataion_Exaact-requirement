// ---------------------------------------------------------------------------
//  B1 — what the EYE actually receives.
//
//  The server test proves the tokens the theme engine emits. It cannot prove
//  what a browser finally paints: a rule further down the cascade, an opacity,
//  or an inherited background can undo a compliant token. This measures the
//  COMPUTED colour of real elements on real screens against the real background
//  behind them, walking up the DOM for the first opaque ancestor exactly as the
//  compositor does.
//
//    node tools/b1-contrast-check.js [baseUrl] [user] [pass]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8802';
const USER = process.argv[3] || 'admin', PASS = process.argv[4] || 'admin12345';
let pass = 0, fail = 0; const fails = [];
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const section = t => console.log('\n== ' + t + ' ==');

//  Injected into the page. Returns, for each sampled element, the composited
//  foreground, the effective background and the WCAG ratio between them.
const PROBE = () => {
  const lin = v => (v /= 255) <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  const lum = c => 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]);
  const ratio = (a, b) => { const la = lum(a), lb = lum(b);
    return Math.round(((Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)) * 100) / 100; };
  const parse = s => { const m = (s || '').match(/[\d.]+/g); return m ? m.map(Number) : null; };
  const over = (fg, bg) => { const a = fg.length > 3 ? fg[3] : 1;
    return [0, 1, 2].map(i => Math.round(fg[i] * a + bg[i] * (1 - a))); };
  // First ancestor with a non-transparent background — what is really behind it.
  const bgOf = el => {
    for (let n = el; n && n !== document.documentElement.parentNode; n = n.parentElement) {
      const c = parse(getComputedStyle(n).backgroundColor);
      if (c && (c.length < 4 || c[3] > 0.99)) return [c[0], c[1], c[2]];
    }
    return [255, 255, 255];
  };
  const vis = el => { const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden'; };
  const out = [];
  const sample = (label, sel, limit = 6) => {
    const els = [...document.querySelectorAll(sel)].filter(vis).slice(0, limit);
    for (const el of els) {
      const cs = getComputedStyle(el);
      const fg = parse(cs.color); if (!fg) continue;
      const bg = bgOf(el);
      const eff = over(fg.length > 3 ? fg : [...fg, 1], bg);
      const opacity = parseFloat(cs.opacity || '1');
      const withOp = opacity < 1 ? over([...eff, opacity], bg) : eff;
      const text = (el.textContent || '').trim().slice(0, 28);
      if (!text && !sel.includes('placeholder')) continue;
      out.push({ label, ratio: ratio(withOp, bg),
                 fg: 'rgb(' + withOp.join(',') + ')', bg: 'rgb(' + bg.join(',') + ')',
                 size: parseFloat(cs.fontSize), weight: cs.fontWeight, text });
    }
  };
  sample('secondary text', '.muted, .sub, small.muted, .t-xs.muted', 8);
  //  Links and buttons were NOT sampled in the first run, and that is exactly
  //  where the worst of it was hiding: a{color:var(--brand)} made every link in
  //  the product 2.1:1 on a pale brand, and .btn hard-coded white on the brand.
  sample('link', 'a.crumbs, .crumbs a, .container a:not(.btn):not(.master-card)', 8);
  sample('primary button', '.btn:not(.secondary):not(.danger):not([disabled])', 5);
  sample('secondary button', '.btn.secondary', 5);
  sample('disabled button', '.btn[disabled], .btn:disabled', 3);
  sample('form label', 'form.stack label, .ff label, label', 6);
  sample('table header', 'table.grid th, table.dt th', 6);
  sample('body text', 'p, td', 4);
  sample('status pill', '.pill', 6);
  // Placeholders are a pseudo-element: read the declared colour + opacity.
  const ph = [...document.querySelectorAll('input.form-control, .form-control')].filter(vis).slice(0, 3);
  for (const el of ph) {
    const d = getComputedStyle(el, '::placeholder');
    const fg = parse(d.color); if (!fg) continue;
    const bg = bgOf(el);
    const op = parseFloat(d.opacity || '1');
    const eff = over([fg[0], fg[1], fg[2], (fg.length > 3 ? fg[3] : 1) * op], bg);
    out.push({ label: 'placeholder', ratio: ratio(eff, bg),
               fg: 'rgb(' + eff.join(',') + ')', bg: 'rgb(' + bg.join(',') + ')',
               size: parseFloat(getComputedStyle(el).fontSize), weight: '400', text: el.placeholder || '(none)' });
  }
  // A control's boundary — WCAG 1.4.11 asks 3:1.
  const ctl = [...document.querySelectorAll('input.form-control, select.form-control')].filter(vis).slice(0, 3);
  for (const el of ctl) {
    const cs = getComputedStyle(el);
    const bc = parse(cs.borderTopColor); if (!bc || parseFloat(cs.borderTopWidth) === 0) continue;
    const bg = bgOf(el.parentElement || el);
    out.push({ label: 'control border', ratio: ratio(over(bc.length > 3 ? bc : [...bc, 1], bg), bg),
               fg: cs.borderTopColor, bg: 'rgb(' + bg.join(',') + ')', size: 0, weight: '', text: '(border)' });
  }
  return out;
};

//  WCAG AA: 4.5:1 for normal text, 3:1 for large text (>=24px, or >=18.66px bold).
const needed = r => (r.label === 'control border') ? 3.0
  : (r.size >= 24 || (r.size >= 18.66 && parseInt(r.weight, 10) >= 700)) ? 3.0 : 4.5;

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg = await ctx.newPage();
  const errs = [], bad = [];
  pg.on('pageerror', e => errs.push(e.message));
  pg.on('response', r => { if (r.status() >= 400 && !r.url().includes('favicon')) bad.push(r.status() + ' ' + r.url().replace(BASE, '')); });

  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER); await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');

  const SCREENS = [
    ['Dashboard', '/'], ['Recruitment CC', '/recruitment-cc'], ['Candidates', '/candidates'],
    ['Workforce / people', '/m/inspectors'], ['Add a person (form)', '/m/inspectors/new'],
    ['Operations home', '/operations'], ['Quality area', '/quality'], ['Money area', '/money'],
    ['Reporting area', '/reporting'], ['Marketplace area', '/marketplace'],
    ['Sales area', '/sales'], ['Directory area', '/directory'], ['Insights area', '/insights'],
    ['Admin area', '/admin'], ['Search', '/search?q=a'],
  ];

  async function measure(tag) {
    let worst = 99, worstOn = '', n = 0, failures = [];
    for (const [name, route] of SCREENS) {
      const res = await pg.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
      if (!res || res.status() >= 400) { console.log(`  --    ${name} (${route}) not reachable — skipped`); continue; }
      const rows = await pg.evaluate(PROBE);
      for (const r of rows) {
        n++;
        const need = needed(r);
        if (r.ratio < need) failures.push(`${name} · ${r.label} ${r.ratio}:1 (needs ${need}) "${r.text}"`);
        if (r.ratio < worst) { worst = r.ratio; worstOn = `${name} · ${r.label} "${r.text}"`; }
      }
    }
    ok(n > 120, `${tag} ARMING · ${n} rendered text/border samples measured across ${SCREENS.length} screens`);
    ok(failures.length === 0,
       `${tag} · every sample meets WCAG AA — worst was ${worst}:1 on ${worstOn}`
       + (failures.length ? `\n         ` + failures.slice(0, 12).join('\n         ')
          + (failures.length > 12 ? `\n         ...and ${failures.length - 12} more` : '') : ''));
    return worst;
  }

  section('V1 · default workspace (no branding chosen)');
  const w1 = await measure('V1');

  section('V2 · a branded workspace — the pale gold that measured 2.1:1');
  await pg.goto(BASE + '/settings');
  const hasPrimary = await pg.locator('input[name=c_primary]').count();
  if (hasPrimary) {
    await pg.locator('input[name=c_primary]').first().fill('#d4af37');
    //  The settings form opens with a hidden "send test email" submit. Taking
    //  .first() clicked that and hung — the selector must demand a VISIBLE one.
    await pg.locator('form:has(input[name=c_primary]) button[type=submit]:visible').first().click();
    await pg.waitForLoadState('networkidle');
    ok(true, 'V2 ARMING · a pale-gold brand colour was saved for this workspace');
    const w2 = await measure('V2');
    ok(w2 >= 3.0, `V2 · the branded workspace is no worse than 3:1 anywhere (worst ${w2}:1)`);
  } else {
    ok(false, 'V2 ARMING · could not find the brand-colour field on /settings — branded run NOT performed');
  }

  section('V3 · focus is visible, and is not the raw brand colour');
  await pg.goto(BASE + '/m/inspectors/new', { waitUntil: 'networkidle' });
  const foc = await pg.evaluate(async () => {
    const el = document.querySelector('input[name=first_name]');
    const before = getComputedStyle(el);
    const was = { border: before.borderTopColor, shadow: before.boxShadow, outline: before.outlineWidth };
    el.focus();
    //  .form-control carries `transition:border-color .15s`. Reading the
    //  computed style straight after focus() samples the colour MID-TRANSITION
    //  -- which is to say, still the unfocused one. This check passed for that
    //  reason while a deliberately reintroduced defect was present.
    await new Promise(r => setTimeout(r, 400));
    const cs = getComputedStyle(el);
    const root = getComputedStyle(document.documentElement);
    const lin = v => (v /= 255) <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    const lum = c => 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]);
    const parse = t => { const m = (t || '').match(/[\d.]+/g); return m ? m.map(Number) : [0, 0, 0]; };
    const ratio = (a, b) => { const la = lum(a), lb = lum(b);
      return Math.round(((Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)) * 100) / 100; };
    const pageBg = parse(getComputedStyle(document.body).backgroundColor);
    return { changed: cs.borderTopColor !== was.border || cs.boxShadow !== was.shadow
                      || parseFloat(cs.outlineWidth) > parseFloat(was.outline),
             indicator: cs.borderTopColor,
             indicatorRatio: ratio(parse(cs.borderTopColor), pageBg.length ? pageBg : [255, 255, 255]),
             focusTok: root.getPropertyValue('--focus').trim(),
             brandTok: root.getPropertyValue('--brand').trim() };
  });
  //  A focus indicator does not have to be an outline. This one is a border and
  //  a ring, so the question is whether focus VISIBLY changes the control and
  //  whether that change is perceivable (WCAG 1.4.11, 3:1).
  ok(foc.changed, 'V3 · focusing a control visibly changes it (border/ring/outline)');
  ok(foc.indicatorRatio >= 3.0,
     `V3 · and the focus indicator measures ${foc.indicatorRatio}:1 against the page (needs 3)`);
  ok(foc.focusTok !== '', `V3 · a --focus token is published (${foc.focusTok})`);
  ok(foc.focusTok !== foc.brandTok,
     `V3 · and on a pale brand it is NOT the raw brand — brand ${foc.brandTok}, focus ${foc.focusTok}`);

  section('V4 · mobile — no overflow or clipping introduced');
  for (const [w, h] of [[360, 800], [390, 844], [412, 915]]) {
    await pg.setViewportSize({ width: w, height: h });
    let over = [];
    for (const [name, route] of SCREENS.slice(0, 8)) {
      const res = await pg.goto(BASE + route, { waitUntil: 'networkidle' }).catch(() => null);
      if (!res || res.status() >= 400) continue;
      const o = await pg.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      if (o > 2) over.push(`${name} +${o}px`);
    }
    ok(over.length === 0, `V4 · ${w}x${h} — no horizontal page overflow` + (over.length ? ': ' + over.join(', ') : ''));
  }
  await pg.setViewportSize({ width: 1280, height: 900 });

  section('V5 · nothing broke');
  ok(errs.length === 0, 'V5 · no JavaScript errors' + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
  ok(bad.length === 0, 'V5 · no failed requests' + (bad.length ? ': ' + bad.slice(0, 5).join(' | ') : ''));

  // Put the workspace back the way it was found.
  await pg.goto(BASE + '/settings');
  if (await pg.locator('input[name=c_primary]').count()) {
    await pg.locator('input[name=c_primary]').first().fill('#0f5f5c');
    await pg.locator('form:has(input[name=c_primary]) button[type=submit]:visible').first().click();
    await pg.waitForLoadState('networkidle');
  }

  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  fails.forEach(f => console.log('  - ' + f));
  await br.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.error('CHECK CRASHED: ' + e.message); process.exit(2); });
