// Portal + professional (marketplace) smoke crawl — the companion to smoke.js.
//
//  smoke.js walks the INTERNAL ops app as an administrator. This walks the two
//  EXTERNAL surfaces that smoke.js never signs into — the client/agency PORTAL
//  (/portal/*) and the professional PASSPORT (/pro/*) — as each seeded demo user,
//  opening every screen that user reaches and failing if any renders a PHP error.
//
//  Boot a seeded throwaway server the same way smoke.js documents, then:
//    NODE_PATH=/path/to/node_modules node tools/portal-crawl.js http://127.0.0.1:8811
//
//  Exit 0 = every portal/pro screen renders. Exit 1 = at least one is broken.
//  Load DEMO-S01/S02/S03/S04 into the DB first, or the logins below won't exist.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://127.0.0.1:8811';
const PW = process.argv[3] || 'demo12345';

// The same failure vocabulary smoke.js uses — anything a healthy page never prints.
const FATAL = /Cannot redeclare|program file is missing|The app hit an error|Fatal error|Parse error|Uncaught (?:Error|Exception|TypeError)|SQLSTATE|Warning: |Notice: |Deprecated: |Undefined variable|Undefined array key|call to a member|Too few arguments|Argument #/i;

// One entry per seeded persona: where they sign in, and the screens they reach.
const PERSONAS = [
  { who: 'Freelancer arjun.s01', login: '/pro/login', user: 'arjun.s01@demo.test',
    routes: ['/pro', '/pro/profile', '/pro/credentials', '/pro/verify', '/pro/privacy',
             '/pro/jobs', '/pro/applications', '/pro/bookings', '/pro/documents',
             '/pro/messages', '/pro/vouchers'] },
  { who: 'Client client.s01', login: '/portal/login', user: 'client.s01@demo.test',
    routes: ['/portal', '/portal/dashboard', '/portal/hire', '/portal/hiring', '/portal/find', '/portal/talent?id=1',
             '/portal/roster', '/portal/reports', '/portal/deputations', '/portal/invoices',
             '/portal/request', '/portal/complaints', '/portal/team'] },
  { who: 'Client Technical epc.tech.s03', login: '/portal/login', user: 'epc.tech.s03@demo.test',
    routes: ['/portal/dashboard', '/portal/hiring', '/portal/reports'] },
  { who: 'Client Project epc.project.s03', login: '/portal/login', user: 'epc.project.s03@demo.test',
    routes: ['/portal/dashboard', '/portal/deputations'] },
  { who: 'Client Commercial epc.commercial.s03', login: '/portal/login', user: 'epc.commercial.s03@demo.test',
    routes: ['/portal/dashboard', '/portal/invoices'] },
  { who: 'Agency agency.s02', login: '/portal/login', user: 'agency.s02@demo.test',
    routes: ['/portal', '/portal/dashboard', '/portal/roster', '/portal/hiring',
             '/portal/deputations', '/portal/invoices'] },
  // DEMO-S04 marketplace lifecycle — the new engines (supplier types, conflict
  // flag, deployment bridge, gate pass). Load DEMO-S04 into the DB first.
  { who: 'Client S04 s04.tech', login: '/portal/login', user: 's04.tech@demo.test',
    routes: ['/portal/dashboard', '/portal/find', '/portal/find?supplier=ORG',
             '/portal/hiring', '/portal/deputations', '/portal/reports'] },
];

(async () => {
  const b = await chromium.launch();
  const broken = [];
  let n = 0;
  for (const p of PERSONAS) {
    const pg = await b.newPage();
    try {
      await pg.goto(BASE + p.login, { waitUntil: 'domcontentloaded' });
      await pg.fill('input[name=email]', p.user);
      await pg.fill('input[name=password]', PW);
      await pg.click('button[type=submit], input[type=submit]');
      await pg.waitForLoadState('domcontentloaded');
    } catch (e) { broken.push([p.who, p.login, 'LOGIN — ' + String(e).slice(0, 140)]); await pg.close(); continue; }
    for (const r of p.routes) {
      n++;
      try {
        const res = await pg.goto(BASE + r, { waitUntil: 'domcontentloaded' });
        const body = await pg.evaluate(() => document.body.innerText);
        const code = res ? res.status() : 0;
        const m = body.match(FATAL);
        if (code >= 500) broken.push([p.who, r, 'HTTP ' + code]);
        else if (m) { const at = body.indexOf(m[0]); broken.push([p.who, r, body.slice(Math.max(0, at - 30), at + 140).replace(/\s+/g, ' ').trim()]); }
      } catch (e) { broken.push([p.who, r, String(e).slice(0, 140)]); }
    }
    await pg.close();
  }
  await b.close();
  console.log(`\n${n} portal/pro screens opened across ${PERSONAS.length} personas.`);
  if (!broken.length) { console.log('All portal/pro screens render cleanly.'); return; }
  console.log(`\n${broken.length} BROKEN:\n`);
  for (const [w, r, why] of broken) console.log(`  [${w}]  ${r}\n      ${why}\n`);
  process.exit(1);
})();
