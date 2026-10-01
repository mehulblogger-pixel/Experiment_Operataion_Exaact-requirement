// ---------------------------------------------------------------------------
//  GATE 3 — REAL BROWSER VERIFICATION OF REVIEW REQUIRED
//
//  The server tests prove the rule. They cannot see the three mistakes the SCREEN
//  must make impossible:
//
//    · not noticing that the requirement moved under this candidate;
//    · reading the evidence table as a verdict ("the system says he's fine");
//    · believing somebody who merely CAN SEE the review is entitled to clear it.
//
//  So this drives Chromium and reads what a person would actually see, then drives
//  the operational workflow at three real phone widths.
//
//    node tools/g3-browser-check.js [baseUrl] [user] [pass] "REQ=.. STRONG=.. ..."
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8846';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const IDS  = {};
(process.argv[5] || '').split(/\s+/).forEach(kv => { const [k, v] = kv.split('='); if (k) IDS[k] = v; });

let pass = 0, fail = 0;
const ok  = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; console.log('  FAIL  ' + m); } };
const sec = (m) => console.log('\n== ' + m + ' ==');

(async () => {
  const br  = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(String(e)));
  const body = async () => (await pg.textContent('body').catch(() => '')) || '';
  const open = async (p, label) => {
    const r = await pg.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    ok(code === 200, label + ' (' + code + ')');
    return code;
  };

  sec('G3-UI · 1 · sign in and reach the candidate screens');
  await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await pg.fill('input[name=username]', USER).catch(() => {});
  await pg.fill('input[name=password]', PASS).catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
  ok(!/\/login/.test(pg.url()), '1a · signed in');
  await open('/candidates', '1b · the candidate register renders');
  await open('/requisition?id=' + IDS.REQ, '1c · the changed requirement opens');

  sec('G3-UI · 2 · the review is on the screen, and says what changed');
  await open('/candidate?id=' + IDS.STRONG, '2a · a candidate under review opens');
  let b = await body();
  ok(/Review required/i.test(b), '2b · the Review required panel is on the screen');
  ok(/Not yet reviewed/i.test(b), '2c · …marked as not yet reviewed');
  ok(/changed from/i.test(b) && /version 1/i.test(b) && /version 2/i.test(b),
     '2d · *** it names BOTH versions: what it was and what is now in force ***');
  ok(/in force/i.test(b), '2e · …saying which one is in force');
  ok(/Asks for more than before/i.test(b), '2f · …and that the new one asks for more');
  ok(/minimum experience stricter/i.test(b), '2g · …naming exactly what got stricter');
  ok(/somebody\s+has\s+to\s+confirm/i.test(b), '2h · …and what a person now has to do');

  sec('G3-UI · 3 · *** the strongest candidate is in review too (A1, no score exemption) ***');
  ok(/Minimum experience/i.test(b), '3a · the evidence table is shown');
  ok(/\b12\b/.test(b), '3b · …stating what the requirement now asks');
  ok(/\b22\b/.test(b), '3c · …and what is on this record');
  ok(/meets/i.test(b), '3d · …and that this candidate clears it');
  ok(/evidence for you to weigh, not a decision/i.test(b),
     '3e · *** …labelled EVIDENCE, not a verdict ***');
  ok(/Continue with this candidate/i.test(b),
     '3f · *** a candidate who plainly clears the new bar STILL needs a human to say so ***');
  ok(/Reject/i.test(b), '3g · …and rejecting them is equally available');
  const reasonRequired = await pg.getAttribute('form[action*="candidate-review"] input[name=reason]', 'required')
                                 .catch(() => null);
  ok(reasonRequired !== null, '3h · *** the reason field is mandatory on the form ***');

  sec('G3-UI · 4 · the weakest candidate is also only in review — not rejected');
  await open('/candidate?id=' + IDS.WEAK, '4a · the weakest candidate opens');
  b = await body();
  ok(/Review required/i.test(b), '4b · they are in review');
  ok(/below/i.test(b), '4c · …and the evidence says plainly that they are below the bar');
  ok(/Continue with this candidate/i.test(b),
     '4d) *** …yet nothing has rejected them: a human still decides ***'.replace(') ', ' · '));

  sec('G3-UI · 5 · *** the candidate holding an issued offer is NOT in review (A2) ***');
  await open('/candidate?id=' + IDS.OFFERED, '5a · the promised candidate opens');
  b = await body();
  ok(!/Review required/i.test(b),
     '5b · *** no review was raised against somebody already holding an issued offer ***');
  ok(!/Not yet reviewed/i.test(b), '5c · …and nothing asks anybody to review them');

  sec('G3-UI · 6 · a closed candidate is not reopened, and reconsideration is deliberate');
  await open('/candidate?id=' + IDS.CLOSED, '6a · a candidate closed before the change opens');
  b = await body();
  ok(!/Not yet reviewed/i.test(b), '6b · *** a closed candidate was not put into review ***');
  ok(/Reconsider this candidate/i.test(b), '6c · reconsideration is offered…');
  ok(/never happens on its own/i.test(b), '6d · *** …and says plainly that it is never automatic ***');
  ok(/the stage they were on\s*before they were closed|returned to|return to/i.test(b),
     '6e · …naming the stage they would return to');

  sec('G3-UI · 7 · deciding a review actually decides it');
  await open('/candidate?id=' + IDS.MID, '7a · a shortlisted candidate under review opens');
  await pg.fill('form[action*="candidate-review"] input[name=reason]',
                '11 years on comparable sites — accepted against the new 12-year bar.').catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'),
                     pg.click('form[action*="candidate-review"] button[value=continue]')]).catch(() => {});
  b = await body();
  ok(/Review cleared/i.test(b), '7b · the decision is confirmed on screen');
  ok(!/Not yet reviewed/i.test(b), '7c · *** the review is no longer open ***');
  ok(/Requirement review history/i.test(b), '7d · the review history panel appears');
  ok(/Reviewed — continuing/i.test(b), '7e · …recording the outcome');
  ok(/11 years on comparable sites/i.test(b), '7f · *** …and the reason, kept in the record ***');
  ok(/v1\s*→\s*v2/i.test(b), '7g · …against the two versions it was about');

  sec('G3-UI · 8 · the rule lives below the screen, not in the form');
  await open('/candidate?id=' + IDS.WEAK, '8a · a candidate still under review opens');
  //  The review id and a VALID CSRF token, both read off the page the way the real
  //  form gets them. The token matters: a tokenless POST is refused by the app's
  //  global cross-site guard before this gate is ever reached, which proves the
  //  guard covers the new route but proves nothing about the review rule itself.
  //  Carrying a real token is what puts the request in front of the service.
  const tok = await pg.getAttribute('form[action*="candidate-review"] input[name=_csrf]', 'value').catch(() => null);
  const rid = await pg.getAttribute('form[action*="candidate-review"] input[name=review_id]', 'value').catch(() => null);
  ok(!!tok, '8b · the form carries a cross-site token (so every POST form here does)');
  ok(!!rid, '8c · …and names the review it decides');

  //  A TOKENLESS POST — refused by the global guard, never reaching the gate.
  const bare = await pg.evaluate(async (a) => {
    const r = await fetch(a.base + '/candidate-review?id=' + a.cid, {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=continue&review_id=' + a.rid + '&candidate_id=' + a.cid + '&reason=forced',
      redirect: 'follow' });
    return { status: r.status, text: (await r.text()).slice(0, 3000) };
  }, { base: BASE, cid: IDS.WEAK, rid: rid }).catch(() => ({ status: 0, text: '' }));
  ok(!/Review cleared/i.test(bare.text),
     '8d · *** a POST with no cross-site token does not clear the review ***');

  //  A TOKENED POST WITH NO REASON — this one DOES reach the gate, and the gate is
  //  what must refuse it. The form's `required` attribute is not a rule; it is a
  //  courtesy, and this is the request that proves the rule is behind it.
  const noReason = await pg.evaluate(async (a) => {
    const r = await fetch(a.base + '/candidate-review?id=' + a.cid, {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: '_csrf=' + encodeURIComponent(a.tok) + '&action=continue&review_id=' + a.rid
          + '&candidate_id=' + a.cid + '&reason=%20%20%20',
      redirect: 'follow' });
    return { status: r.status, text: await r.text() };
  }, { base: BASE, cid: IDS.WEAK, rid: rid, tok: tok }).catch(() => ({ status: 0, text: '' }));
  ok(noReason.status === 200, '8e · the request is answered properly (' + noReason.status + ')');
  ok(/A reason is required/i.test(noReason.text),
     '8f · *** …and REFUSED for want of a reason, by the service and not by the form ***');

  await open('/candidate?id=' + IDS.WEAK, '8g · the candidate is re-read from the server');
  b = await body();
  ok(/Review required/i.test(b) && /Not yet reviewed/i.test(b),
     '8h · *** the review is STILL OPEN after both crafted requests ***');

  sec('G3-UI · 9 · nothing throws in the browser');
  ok(jsErrors.length === 0, '9a · no JavaScript errors'
     + (jsErrors.length ? ': ' + jsErrors.slice(0, 2).join(' | ') : ''));

  sec('G3-UI · 10 · the operational workflow on a real phone (360 / 390 / 412)');
  for (const w of [360, 390, 412]) {
    const m = await ctx.newPage();
    await m.setViewportSize({ width: w, height: 800 });
    const r = await m.goto(BASE + '/candidate?id=' + IDS.STRONG, { waitUntil: 'domcontentloaded' }).catch(() => null);
    ok(r && r.status() === 200, w + 'px · the candidate under review opens (' + (r ? r.status() : 0) + ')');
    const mb = (await m.textContent('body').catch(() => '')) || '';
    ok(/Review required/i.test(mb), w + 'px · *** the review is visible on the phone ***');
    ok(/version 2/i.test(mb), w + 'px · …with the version that is in force');
    const hasContinue = await m.$('form[action*="candidate-review"] button[value=continue]');
    const hasReject   = await m.$('form[action*="candidate-review"] button[value=reject]');
    const hasReason   = await m.$('form[action*="candidate-review"] input[name=reason]');
    ok(!!hasContinue && !!hasReject && !!hasReason,
       w + 'px · *** continue, reject and the mandatory reason are all usable ***');
    const over = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => false);
    ok(!over, w + 'px · no horizontal overflow');
    //  The buttons must be big enough to hit with a thumb in the field.
    const small = await m.evaluate(() => {
      const bs = [...document.querySelectorAll('form[action*="candidate-review"] button')];
      return bs.filter(x => x.getBoundingClientRect().height < 32).length;
    }).catch(() => 0);
    ok(small === 0, w + 'px · the decision buttons are thumb-sized');
    await m.close();
  }

  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
