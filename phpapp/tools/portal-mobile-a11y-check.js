// ---------------------------------------------------------------------------
//  The three EXTERNAL portals at PHONE WIDTH, and the accessibility basics.
//
//  CLAUDE.md: "Inspectors are phone-first in the field." A marketplace
//  PROFESSIONAL is phone-first by definition -- they are on a site, not at a
//  desk -- and a client's commercial contact is desk-first. This measures what
//  a browser actually paints at 390px rather than reasoning about the CSS,
//  because the client portal carries no @media query at all and relies on
//  clamp(), flex-wrap, auto-fit grids and scrollable tables. That CAN work
//  perfectly; the only way to know is to render it.
//
//  What it measures, all objective:
//    1. horizontal overflow  -- the page is wider than the phone, naming the
//                               widest element responsible
//    2. tap targets          -- interactive things too small to hit reliably
//    3. tiny text            -- rendered font-size under 12px
//    4. unlabelled fields    -- a control with no accessible name at all
//    5. images without alt
//    6. data tables with no header row
//    7. colour contrast -- the composited foreground against the first opaque
//       ancestor background, as the compositor does it. The algorithm is the one
//       tools/b1-contrast-check.js already uses for the staff app; that tool
//       signs in at /login as staff and can never reach these three portals,
//       which is why the portals had never been measured.
//
//  Reuses the personas and sign-in flow of tools/portal-crawl.js. Boot a
//  DEMO-S01..S04 seeded throwaway server first, exactly as that tool documents:
//
//    node tools/portal-mobile-a11y-check.js http://127.0.0.1:8841 [password]
//
//  Exit 0 = no failure-level problem. Exit 1 = at least one.
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8841';
const PW   = process.argv[3] || 'demo12345';
const W = 390, H = 844;          // iPhone 12/13/14 logical viewport

let pass = 0, fail = 0, warn = 0;
const fails = [], warns = [];
const ok = (c, m) => { if (c) { pass++; } else { fail++; fails.push(m); console.log('  FAIL  ' + m); } };
const soft = (c, m) => { if (!c) { warn++; warns.push(m); console.log('  warn  ' + m); } };

// Injected into the page. Everything here is measured from the rendered box,
// never from the stylesheet.
const PROBE = () => {
  const out = { overflow: null, small: [], tiny: [], unlabelled: [], noalt: [], headless: [], dupids: [] };
  const docW = document.documentElement.clientWidth;

  // 1. horizontal overflow, and WHICH element is widest past the edge
  const scrollW = Math.max(document.documentElement.scrollWidth, document.body.scrollWidth);
  if (scrollW > docW + 1) {
    let worst = null;
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) continue;
      const over = Math.round(r.right - docW);
      if (over > 1 && (!worst || over > worst.over)) {
        // an ancestor that scrolls horizontally on purpose is not an overflow
        let scroller = false;
        for (let p = el; p && p !== document.body; p = p.parentElement) {
          const ov = getComputedStyle(p).overflowX;
          if (ov === 'auto' || ov === 'scroll') { scroller = true; break; }
        }
        if (!scroller) worst = { over, tag: el.tagName.toLowerCase(),
                                 cls: (el.className || '').toString().slice(0, 40),
                                 text: (el.textContent || '').trim().slice(0, 40) };
      }
    }
    out.overflow = { scrollW, docW, worst };
  }

  // 2. tap targets. WCAG 2.5.8 (AA) asks 24px, but EXEMPTS a target "in a
  //    sentence or whose size is otherwise constrained by the line-height of
  //    non-target text". A link inside a paragraph of prose is therefore not a
  //    failure, and counting it as one -- which a first pass of this script did
  //    -- buries the real ones. A target counts only when it stands alone: its
  //    parent block holds no non-link text of its own.
  const standalone = el => {
    const p = el.parentElement; if (!p) return true;
    let proseLen = 0;
    for (const n of p.childNodes)
      if (n.nodeType === 3) proseLen += n.textContent.replace(/\s+/g, ' ').trim().length;
    return proseLen < 12;        // a word or two of punctuation is not a sentence
  };
  for (const el of document.querySelectorAll('a[href], button, input[type=submit], input[type=button], select, [role=button]')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;             // hidden
    const label = (el.textContent || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 34);
    const inline = el.tagName === 'A' && !standalone(el);
    if (r.height < 24) out.small.push({ h: Math.round(r.height), label, hard: !inline, inline });
    else if (r.height < 40) out.small.push({ h: Math.round(r.height), label, hard: false, inline });
  }

  // 3. tiny rendered text
  for (const el of document.querySelectorAll('body *')) {
    if (!el.firstChild || el.firstChild.nodeType !== 3) continue;   // own text only
    const t = (el.textContent || '').trim();
    if (!t) continue;
    const fs = parseFloat(getComputedStyle(el).fontSize);
    if (fs && fs < 12) out.tiny.push({ fs: Math.round(fs * 10) / 10, tag: el.tagName.toLowerCase(), text: t.slice(0, 34) });
  }

  // 4. a form control with NO accessible name by any route
  for (const el of document.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) continue;
    const id = el.getAttribute('id');
    const named = !!(el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')
      || el.getAttribute('title') || el.closest('label')
      || (id && document.querySelector('label[for="' + CSS.escape(id) + '"]'))
      || (el.getAttribute('placeholder') || '').trim());   // placeholder: weak, but a name
    if (!named) out.unlabelled.push({ name: el.getAttribute('name') || '(no name)', type: el.type || el.tagName.toLowerCase() });
  }

  // 4b. duplicate ids. A label's for= points at ONE id, so a repeated id --
  //     the thing a template inside a foreach quietly produces -- silently
  //     mislabels every field after the first. Static analysis cannot see it;
  //     the rendered page can.
  {
    const seen = new Map();
    for (const el of document.querySelectorAll('[id]')) {
      const id = el.getAttribute('id'); if (!id) continue;
      seen.set(id, (seen.get(id) || 0) + 1);
    }
    for (const [id, n] of seen) if (n > 1) out.dupids.push({ id, n });
  }

  // 5. images with no alt attribute at all
  for (const el of document.querySelectorAll('img'))
    if (!el.hasAttribute('alt')) out.noalt.push(el.getAttribute('src') || '(inline)');

  // 6. a table with data rows and no header cell
  for (const t of document.querySelectorAll('table')) {
    const rows = t.querySelectorAll('tr').length;
    if (rows > 1 && t.querySelectorAll('th').length === 0)
      out.headless.push({ rows, cls: (t.className || '').toString().slice(0, 30) });
  }
  return out;
};


// Colour contrast, measured the way the compositor composites: the foreground
// flattened over the first OPAQUE ancestor background, with opacity applied.
// Algorithm reused from tools/b1-contrast-check.js so the two agree.
const CONTRAST = () => {
  const lin = v => (v /= 255) <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  const lum = c => 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]);
  const ratio = (a, b) => { const la = lum(a), lb = lum(b);
    return Math.round(((Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)) * 100) / 100; };
  const parse = s => { const m = (s || '').match(/[\d.]+/g); return m ? m.map(Number) : null; };
  const over = (fg, bg) => { const a = fg.length > 3 ? fg[3] : 1;
    return [0, 1, 2].map(i => Math.round(fg[i] * a + bg[i] * (1 - a))); };
  // The first ancestor that actually PAINTS something behind the text.
  //
  // A background-color is not the only way to paint one: the professional
  // portal's header is a linear-gradient, which lives in background-image.
  // Reading backgroundColor alone walks straight past it and lands on the
  // near-white page background, so white-on-teal measures 1.07:1 -- invisible
  // text, which is absurd on its face and was the tell that the PROBE was
  // wrong, not the portal. (tools/b1-contrast-check.js shares this blind spot;
  // it has not bitten there because the staff chrome uses flat colours.)
  // Where a gradient is found, its colour stops are read and the WORST of them
  // is used, so the answer is the least favourable real pixel, not an average.
  const bgOf = el => {
    for (let n = el; n && n !== document.documentElement.parentNode; n = n.parentElement) {
      const cs = getComputedStyle(n);
      const img = cs.backgroundImage || '';
      if (/gradient\(/.test(img)) {
        const stops = (img.match(/rgba?\([^)]*\)/g) || []).map(parse)
          .filter(c => c && (c.length < 4 || c[3] > 0.99)).map(c => [c[0], c[1], c[2]]);
        if (stops.length) return { stops };
      }
      const c = parse(cs.backgroundColor);
      if (c && (c.length < 4 || c[3] > 0.99)) return { stops: [[c[0], c[1], c[2]]] };
    }
    return { stops: [[255, 255, 255]] };
  };
  const vis = el => { const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden'; };
  const out = [];
  const sample = (label, sel, limit) => {
    for (const el of [...document.querySelectorAll(sel)].filter(vis).slice(0, limit || 6)) {
      const cs = getComputedStyle(el);
      const fg = parse(cs.color); if (!fg) continue;
      const text = (el.textContent || '').trim();
      if (!text) continue;
      const { stops } = bgOf(el);
      const op = parseFloat(cs.opacity || '1');
      let worst = null;
      for (const bg of stops) {
        let eff = over(fg.length > 3 ? fg : [...fg, 1], bg);
        if (op < 1) eff = over([...eff, op], bg);
        const rr = ratio(eff, bg);
        if (worst === null || rr < worst) worst = rr;
      }
      const size = parseFloat(cs.fontSize), weight = parseInt(cs.fontWeight) || 400;
      // WCAG 1.4.3: large text is >=24px, or >=18.66px when bold.
      const large = size >= 24 || (size >= 18.66 && weight >= 700);
      out.push({ label, ratio: worst, size, large, stops: stops.length,
                 need: large ? 3 : 4.5, text: text.slice(0, 30) });
    }
  };
  // Client/vendor portal markup
  sample('lead text', '.plead', 3);
  sample('note text', '.pnote', 3);
  sample('empty state', '.pempty', 2);
  sample('tile label', '.ptile .l', 4);
  sample('table header', '.ptable th', 5);
  sample('footer', '.pfoot', 2);
  sample('nav link', '.pnav a', 5);
  sample('who/sign-out', '.phead .who, .phead a', 3);
  sample('form label', '.pform label, label', 5);
  sample('flash', '.pmsg', 2);
  // Professional portal markup
  sample('pro muted', '.muted', 5);
  sample('pro h2 label', '.wrap h2', 3);
  sample('pro nav link', '.nav a', 5);
  sample('pro top bar', '.top a, .top .brand', 3);
  sample('pro chip', '.chip', 4);
  sample('pro button', '.btn', 3);
  sample('pro flash', '.msg', 2);
  return out;
};

const PERSONAS = [
  { who: 'Professional (phone-first by definition)', login: '/pro/login', user: 'ajay.s02@demo.test',
    routes: ['/pro', '/pro/jobs', '/pro/applications', '/pro/bookings', '/pro/vouchers',
             '/pro/profile', '/pro/documents', '/pro/credentials', '/pro/messages',
             '/pro/reputation', '/pro/verify', '/pro/privacy', '/pro/plans', '/pro/cv'] },
  { who: 'Client, full access', login: '/portal/login', user: 'client.s01@demo.test',
    routes: ['/portal', '/portal/calls', '/portal/reports', '/portal/invoices',
             '/portal/request', '/portal/complaints', '/portal/complaint-new',
             '/portal/deputations', '/portal/issues', '/portal/hiring', '/portal/find',
             '/portal/roster', '/portal/hire', '/portal/team', '/portal/password',
             '/portal/plans', '/portal/reputation', '/portal/alerts'] },
  { who: 'Client, commercial contact only', login: '/portal/login', user: 'epc.commercial.s03@demo.test',
    routes: ['/portal', '/portal/invoices', '/portal/complaints'] },
  { who: 'Agency', login: '/portal/login', user: 'agency.s02@demo.test',
    routes: ['/portal', '/portal/roster', '/portal/hiring', '/portal/deputations'] },
];

(async () => {
  const br = await chromium.launch();
  console.log('Phone width ' + W + 'x' + H + ' — ' + BASE + '\n');

  for (const p of PERSONAS) {
    const ctx = await br.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const pg = await ctx.newPage();
    console.log('== ' + p.who + '  (' + p.user + ') ==');

    await pg.goto(BASE + p.login, { waitUntil: 'domcontentloaded' });
    await pg.fill('input[name=email]', p.user).catch(() => {});
    await pg.fill('input[name=password]', PW).catch(() => {});
    await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit], input[type=submit]')]).catch(() => {});
    if (/\/login/.test(pg.url())) { console.log('  (could not sign in — skipping this persona)\n'); await ctx.close(); continue; }

    for (const r of p.routes) {
      await pg.goto(BASE + r, { waitUntil: 'domcontentloaded' }).catch(() => {});
      if (!/#|\?/.test(r) && pg.url().replace(BASE, '').split('?')[0] !== r) {
        console.log('  skip  ' + r + ' → redirected to ' + pg.url().replace(BASE, '') + ' (not reachable for this user)');
        continue;
      }
      const res = await pg.evaluate(PROBE);

      if (res.overflow) {
        const w = res.overflow.worst;
        ok(false, r + ' fits the phone — it is ' + res.overflow.scrollW + 'px wide in a ' + res.overflow.docW
             + 'px viewport' + (w ? ', widest offender <' + w.tag + (w.cls ? ' class="' + w.cls + '"' : '')
                                   + '> ' + w.over + 'px past the edge' + (w.text ? ' ("' + w.text + '")' : '') : ''));
      } else pass++;

      const hard = res.small.filter(s => s.hard);
      ok(hard.length === 0, r + ' has no tap target under 24px (WCAG 2.5.8)'
           + (hard.length ? ' — ' + hard.length + ': ' + hard.slice(0, 3).map(s => '"' + s.label + '" ' + s.h + 'px').join(', ') : ''));
      soft(res.small.filter(s => !s.hard).length === 0,
           r + ' — ' + res.small.filter(s => !s.hard).length + ' tap target(s) between 24 and 40px');
      soft(res.tiny.length === 0,
           r + ' — text under 12px: ' + res.tiny.slice(0, 3).map(t => t.fs + 'px "' + t.text + '"').join(', '));
      ok(res.unlabelled.length === 0, r + ' labels every field'
           + (res.unlabelled.length ? ' — unnamed: ' + res.unlabelled.map(u => u.name + ' (' + u.type + ')').join(', ') : ''));
      ok(res.dupids.length === 0, r + ' has no duplicate id'
           + (res.dupids.length ? ' — ' + res.dupids.map(d => '#' + d.id + ' x' + d.n).join(', ') : ''));
      ok(res.noalt.length === 0, r + ' gives every image an alt'
           + (res.noalt.length ? ' — ' + res.noalt.length + ' without: ' + res.noalt.slice(0, 2).join(', ') : ''));
      ok(res.headless.length === 0, r + ' gives every data table a header row'
           + (res.headless.length ? ' — ' + res.headless.map(t => t.rows + ' rows, class="' + t.cls + '"').join('; ') : ''));

      const cr = await pg.evaluate(CONTRAST);
      const bad = cr.filter(c => c.ratio < c.need);
      ok(bad.length === 0, r + ' meets WCAG 1.4.3 contrast'
           + (bad.length ? ' — ' + bad.slice(0, 4).map(c => c.label + ' ' + c.ratio + ':1 (needs '
               + c.need + ', ' + c.size + 'px) "' + c.text + '"').join('; ') : ''));
    }
    console.log('');
    await ctx.close();
  }
  await br.close();

  console.log('----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed, ' + warn + ' warnings');
  if (fail) { console.log('\nFAILURES:'); fails.forEach(f => console.log('  - ' + f)); }
  process.exit(fail ? 1 : 0);
})();
