// ---------------------------------------------------------------------------
//  Open every screen in the app and report the ones that break.
//
//  Why this exists: views/detail.php declared its own fdate(), which lib/ops.php
//  already declared. Every file parsed. Every test passed. The client and vendor
//  detail pages were dead, and nobody found out until a person clicked one —
//  because the tests only ever opened the screens tied to the change in hand.
//
//  This walks the whole route list as an administrator, follows the first row of
//  each register so detail and edit screens are actually rendered, and fails on
//  any page carrying a fatal, a PHP warning, or an uncaught JavaScript error.
//
//  Usage:  node tools/smoke.js [baseUrl] [user] [pass]
//  Exit 0 = every screen renders. Exit 1 = at least one is broken.
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');

const BASE = process.argv[2] || 'http://127.0.0.1:8801';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';

// Screens reached without an id. Anything needing one is discovered from the
// registers below, so the list stays honest as the app grows.
const PLAIN = [
  '/', '/calls', '/call-new', '/jobs', '/availability', '/vouchers', '/candidates',
  '/requisitions', '/attendance-recon', '/contract-overrides',
  '/inquiries', '/inquiry-new', '/quotes', '/quote-new', '/quote-external', '/crm-reports',
  '/documents', '/document-new', '/endorsements', '/endorsement-new', '/writing-assistant',
  '/phrase-library', '/learning', '/approver-map', '/idems-approval-rules', '/report-types',
  '/report-templates', '/irn-rules', '/audit-log',
  '/clients', '/vendors', '/partner-new', '/masters', '/lookups', '/custom-fields',
  '/work-norms', '/office-finance', '/cost-run', '/sbu-pl', '/mis', '/partner-import',
  '/reset-data', '/m/office-expense-heads', '/reports', '/profitability', '/invoicing',
  '/users', '/user-new', '/hierarchy', '/settings', '/access', '/terminology',
  '/ai-settings', '/templates', '/crm-templates', '/approval-rules', '/my-signature',
  '/change-password', '/my-jobs', '/boss-renew',
];

// register path -> [link pattern to follow, extra screens built from that id]
const REGISTERS = [
  ['/clients',       /^\/partner\?id=(\d+)/,  ['/partner?id=%s', '/partner?id=%s&tab=contacts', '/partner?id=%s&tab=addresses', '/partner?id=%s&tab=contracts', '/partner?id=%s&tab=purchase_orders', '/partner-edit?id=%s']],
  ['/vendors',       /^\/partner\?id=(\d+)/,  ['/partner?id=%s', '/partner?id=%s&tab=contacts', '/partner-edit?id=%s']],
  ['/calls',         /^\/call\?id=(\d+)/,     ['/call?id=%s', '/call-edit?id=%s', '/job-new?call=%s']],
  ['/jobs',          /^\/job\?id=(\d+)/,      ['/job?id=%s', '/job-edit?id=%s', '/job-close?id=%s']],
  ['/quotes',        /^\/quote\?id=(\d+)/,    ['/quote?id=%s', '/quote-edit?id=%s']],
  ['/documents',     /^\/document\?id=(\d+)/, ['/document?id=%s', '/document-fill?id=%s', '/document-evidence?id=%s', '/document-review?id=%s']],
  ['/candidates',    /^\/candidate\?id=(\d+)/,['/candidate?id=%s']],
  ['/requisitions',  /^\/requisition\?id=(\d+)/, ['/requisition?id=%s']],
  ['/vouchers',      /^\/voucher\?id=(\d+)/,  ['/voucher?id=%s']],
  ['/report-types',  /^\/report-type-edit\?id=(\d+)/, ['/report-type-edit?id=%s', '/report-builder?id=%s']],
  ['/users',         /^\/user-edit\?id=(\d+)/,['/user-edit?id=%s']],
];

const FATAL = /Cannot redeclare|program file is missing|The app hit an error|Fatal error|Parse error|Uncaught (?:Error|Exception|TypeError)|SQLSTATE|Warning: |Notice: |Deprecated: |Undefined variable|Undefined array key/i;

(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const pg = await br.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(e.message));

  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]', USER);
  await pg.fill('input[name=password]', PASS);
  await pg.click('button[type=submit]');
  await pg.waitForLoadState('networkidle');

  const broken = [];
  let checked = 0;

  async function visit(path) {
    checked++;
    jsErrors.length = 0;
    let body = '';
    try {
      const res = await pg.goto(BASE + path, { waitUntil: 'domcontentloaded' });
      body = await pg.textContent('body');
      if (res && res.status() >= 500) {
        broken.push([path, 'HTTP ' + res.status()]); return;
      }
    } catch (e) {
      broken.push([path, 'navigation failed: ' + e.message.split('\n')[0]]); return;
    }
    const m = body.match(FATAL);
    if (m) {
      // Show the sentence around the match, not the whole page.
      const at = body.indexOf(m[0]);
      broken.push([path, body.slice(Math.max(0, at - 40), at + 160).replace(/\s+/g, ' ').trim()]);
      return;
    }
    if (jsErrors.length) broken.push([path, 'JS: ' + jsErrors[0]]);
  }

  for (const p of PLAIN) await visit(p);

  for (const [reg, pattern, templates] of REGISTERS) {
    await pg.goto(BASE + reg, { waitUntil: 'domcontentloaded' });
    // Registers link two ways: a real <a href>, and rows that navigate from an
    // onclick. Both are how a person reaches the screen, so both are followed —
    // missing the second is how the report screens went unvisited.
    const hrefs = await pg.evaluate(() => {
      const out = [];
      document.querySelectorAll('a[href]').forEach(a => out.push(a.getAttribute('href')));
      document.querySelectorAll('[onclick]').forEach(el => {
        const m = (el.getAttribute('onclick') || '').match(/location\.href\s*=\s*'([^']+)'/);
        if (m) out.push(m[1]);
      });
      return out;
    });
    let id = null;
    for (const h of hrefs) { const m = h && h.match(pattern); if (m) { id = m[1]; break; } }
    if (!id) { console.log(`  (no rows in ${reg} — its detail screens were not exercised)`); continue; }
    for (const t of templates) await visit(t.replace(/%s/g, id));
  }

  console.log(`\n${checked} screens opened.`);
  if (broken.length) {
    console.log(`${broken.length} BROKEN:\n`);
    for (const [p, why] of broken) console.log(`  ${p}\n      ${why}\n`);
    await br.close();
    process.exit(1);
  }
  console.log('All screens render cleanly.');
  await br.close();
})();
