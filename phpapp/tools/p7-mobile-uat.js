// ---------------------------------------------------------------------------
//  PHASE 7 FINAL GATE — MOBILE UAT for the EXAACT Connect front door.
//
//  Measures what the §20 rules actually ask for, at the three named viewports:
//  no horizontal overflow, touch targets big enough to hit, the primary action
//  reachable without hunting for it, and no text clipped or overlapping.
//
//    node tools/p7-mobile-uat.js [baseUrl] [path]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8801';
const PATH = process.argv[3] || '/connect';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/shots';
const VIEWPORTS = [[360, 800], [390, 844], [412, 915]];

let pass = 0, fail = 0; const fails = [];
function ok(c, m) { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } }

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  for (const [w, h] of VIEWPORTS) {
    const ctx = await br.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const pg = await ctx.newPage();
    const jsErrors = []; pg.on('pageerror', e => jsErrors.push(e.message));
    console.log('\n== ' + w + ' x ' + h + ' ==');
    const r = await pg.goto(BASE + PATH, { waitUntil: 'networkidle' });
    ok(r && r.status() < 400, w + ' · the page loads (' + (r ? r.status() : '?') + ')');

    //  1. horizontal overflow — the single most common mobile defect
    const ov = await pg.evaluate(() => ({
      doc: document.documentElement.scrollWidth, win: window.innerWidth,
      wide: Array.from(document.querySelectorAll('body *'))
        .filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && (r.right > window.innerWidth + 1 || r.left < -1); })
        .slice(0, 5).map(e => (e.tagName + '.' + (e.className || '').toString().split(' ')[0]).slice(0, 40))
    }));
    ok(ov.doc <= ov.win + 1, w + ' · NO horizontal overflow (page ' + ov.doc + 'px in a ' + ov.win + 'px window'
       + (ov.wide.length ? '; widest: ' + ov.wide.join(', ') : '') + ')');

    //  2. touch targets — 44px is the floor for something you must hit
    const small = await pg.evaluate(() => Array.from(document.querySelectorAll('a,button,input[type=submit],select,[role=button]'))
      .map(e => ({ r: e.getBoundingClientRect(), t: (e.innerText || e.value || e.getAttribute('aria-label') || '').trim().slice(0, 28), tag: e.tagName }))
      .filter(x => x.r.width > 0 && x.r.height > 0 && x.r.height < 44 && x.t)
      .slice(0, 6).map(x => x.t + ' [' + Math.round(x.r.height) + 'px]'));
    ok(small.length === 0, w + ' · every interactive control is at least 44px tall'
       + (small.length ? ' — under: ' + small.join(', ') : ''));

    //  3. the primary action must not be buried below a hero
    const cta = await pg.evaluate(() => {
      const el = Array.from(document.querySelectorAll('a,button')).find(e => /post a requirement|post requirement/i.test(e.innerText || ''));
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { top: Math.round(r.top + window.scrollY), h: Math.round(r.height), vh: window.innerHeight };
    });
    ok(!!cta, w + ' · the primary call to action exists');
    if (cta) ok(cta.top < cta.vh * 1.5,
      w + ' · …and is reachable without hunting (' + cta.top + 'px down, viewport ' + cta.vh + 'px)');

    //  4. nothing clipped or overflowing its own box
    const clipped = await pg.evaluate(() => Array.from(document.querySelectorAll('h1,h2,h3,p,button,a,label'))
      .filter(e => { const s = getComputedStyle(e); return e.scrollWidth > e.clientWidth + 2 && s.overflow !== 'visible' && s.textOverflow !== 'ellipsis'; })
      .slice(0, 5).map(e => (e.innerText || '').trim().slice(0, 30)));
    ok(clipped.length === 0, w + ' · no clipped text' + (clipped.length ? ' — ' + clipped.join(' | ') : ''));

    ok(jsErrors.length === 0, w + ' · no JavaScript errors' + (jsErrors.length ? ' — ' + jsErrors[0] : ''));
    await pg.screenshot({ path: SHOTS + '/mobile-' + w + '.png', fullPage: true });
    await ctx.close();
  }
  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  if (fails.length) { console.log('FAILURES:'); fails.forEach(f => console.log('  - ' + f)); }
  process.exit(fail ? 1 : 0);
})();
