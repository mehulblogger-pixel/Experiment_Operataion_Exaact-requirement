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

// Screens reached without an id. This list is a floor, not the whole crawl:
// everything on the administrator's sidebar is added to it at run time (see
// navPaths below). It had to be, because seven modules were shipped between
// August and now — equipment, competence, impartiality, complaints, corrective
// actions, internal audits, management review — and not one of them was ever
// opened by this crawl, because nobody remembered to add it here. A test list
// that has to be maintained by hand is a test list that quietly stops testing.
//
// Anything needing an id is discovered from the registers below.
const PLAIN = [
  '/', '/calls', '/call-new', '/jobs', '/availability', '/vouchers', '/candidates',
  '/requisitions', '/attendance-recon', '/contract-overrides',
  '/inquiries', '/inquiry-new', '/quotes', '/quote-new', '/quote-external', '/crm-reports',
  '/documents', '/document-new', '/endorsements', '/endorsement-new', '/writing-assistant',
  '/phrase-library', '/learning', '/approver-map', '/idems-approval-rules', '/report-types',
  '/report-templates', '/irn-rules', '/audit-log',
  '/clients', '/vendors', '/partner-new', '/masters', '/lookups', '/custom-fields',
  '/work-norms', '/office-finance', '/cost-run', '/sbu-pl', '/mis', '/partner-import', '/duplicates',
  '/reset-data', '/m/office-expense-heads', '/reports', '/profitability', '/invoicing',
  '/users', '/user-new', '/hierarchy', '/settings', '/access', '/terminology',
  '/ai-settings', '/templates', '/crm-templates', '/approval-rules', '/my-signature',
  '/change-password', '/my-jobs', '/boss-renew',
  // Global search: the empty box, a term that matches several registers, and a
  // term narrowed to one. 'lim' hits the many "... LIMITED" customers, so the
  // crawl stays on the results page instead of being redirected to a lone hit.
  '/search', '/search?q=lim', '/search?q=lim&in=partners',
  // The three registers using the shared table component, each exercised with a
  // sort and a page size — the URL is the whole of the component's input, so a
  // plain load would miss everything that made it worth building.
  '/leads?v=list', '/leads?v=list&sort=value&dir=asc&per=25',
  '/activities', '/activities?sort=what&dir=desc&per=25&page=1',
  '/ncr', '/ncr?f=all&sort=severity&dir=desc&per=50',
  // A sort key no screen declares must be ignored, not fatal.
  '/activities?sort=not-a-column&dir=desc',
  // The books: the register, the handover from operations, money in, and a new
  // invoice and receipt form. The ledger and detail screens come from REGISTERS.
  '/invoices', '/invoices?f=draft', '/invoices?f=paid', '/invoices?f=all',
  '/to-bill', '/receipts', '/receipts?f=unallocated', '/invoice-new', '/receipt-new',
  // The thread, and the report of where it is cut.
  '/flow-gaps',
  // Opportunities: the deal, kept apart from the quotation.
  '/opportunities', '/opportunities?v=list',
  '/opportunities?v=list&sort=weighted&dir=desc', '/opportunity-new',
  // Customer 360 — the assembly. Reached from the customer list below too, but
  // named here so it is crawled even when the list happens to be empty.
];

// register path -> [link pattern to follow, extra screens built from that id]
const REGISTERS = [
  ['/clients',       /^\/customer\?id=(\d+)/, ['/customer?id=%s']],
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
  ['/complaints',    /^\/complaint\?id=(\d+)/, ['/complaint?id=%s']],
  ['/capa',          /^\/capa-item\?id=(\d+)/, ['/capa-item?id=%s']],
  ['/internal-audits', /^\/internal-audit\?id=(\d+)/, ['/internal-audit?id=%s']],
  ['/management-reviews', /^\/management-review\?id=(\d+)/, ['/management-review?id=%s']],
  ['/equipment',     /^\/equip-edit\?id=(\d+)/, ['/equip-edit?id=%s']],
  ['/leads?v=list',  /^\/lead\?id=(\d+)/,   ['/lead?id=%s']],
  ['/ncr?f=all',     /^\/ncr-item\?id=(\d+)/, ['/ncr-item?id=%s']],
  ['/invoices?f=all', /^\/invoice\?id=(\d+)/, ['/invoice?id=%s']],
  ['/receipts',      /^\/receipt\?id=(\d+)/, ['/receipt?id=%s', '/trace?kind=RECEIPT&id=%s']],
  ['/flow-gaps',     /^\/job\?id=(\d+)/,     ['/trace?kind=JOB&id=%s']],
  ['/opportunities?v=list', /^\/opportunity\?id=(\d+)/, ['/opportunity?id=%s', '/trace?kind=OPPORTUNITY&id=%s']],
  ['/invoices?f=all', /^\/ledger\?id=(\d+)/,  ['/ledger?id=%s', '/ledger?id=%s&from=2026-01-01&to=2026-12-31']],
];

const FATAL = /Cannot redeclare|program file is missing|The app hit an error|Fatal error|Parse error|Uncaught (?:Error|Exception|TypeError)|SQLSTATE|Warning: |Notice: |Deprecated: |Undefined variable|Undefined array key/i;

// Screens whose JOB is to display error text. The failure log under §7.11 shows
// the wording of faults the application recorded about itself, so scanning its
// body for that wording says "broken" about a page that is working perfectly.
// They are still fetched, and still fail on HTTP 500 or a JavaScript error —
// only the prose scan is skipped, and only here.
const QUOTES_ERRORS = ['/data-control', '/audit-log', '/incidents'];

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

  // Everything the sidebar offers an administrator, plus the hand-written floor
  // above. A module with a menu entry is now crawled the moment it ships.
  const navPaths = await pg.evaluate(() =>
    Array.from(document.querySelectorAll('.side-nav a[href^="/"]'))
         .map(a => a.getAttribute('href'))
         .filter(h => h && !h.includes('#')));
  const TO_VISIT = Array.from(new Set(PLAIN.concat(navPaths)));
  const added = TO_VISIT.length - PLAIN.length;
  if (added > 0) console.log(`  (${added} screen(s) picked up from the sidebar that the fixed list did not name)`);

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
    const m = QUOTES_ERRORS.some(q => path === q || path.startsWith(q + '?')) ? null : body.match(FATAL);
    if (m) {
      // Show the sentence around the match, not the whole page.
      const at = body.indexOf(m[0]);
      broken.push([path, body.slice(Math.max(0, at - 40), at + 160).replace(/\s+/g, ' ').trim()]);
      return;
    }
    if (jsErrors.length) broken.push([path, 'JS: ' + jsErrors[0]]);
  }

  for (const p of TO_VISIT) await visit(p);

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
