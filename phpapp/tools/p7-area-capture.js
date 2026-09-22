const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2]; const fs = require('fs');
(async () => {
  const br = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const pg = await (await br.newContext({ viewport:{width:1440,height:950} })).newPage();
  await pg.goto(BASE + '/login');
  await pg.fill('input[name=username]','admin'); await pg.fill('input[name=password]','admin12345');
  await pg.click('button[type=submit]'); await pg.waitForLoadState('networkidle');
  const AREAS = ['/operations','/recruitment-cc','/marketplace','/money','/reporting','/directory','/insights','/admin','/quality'];
  const out = {};
  for (const a of AREAS) {
    await pg.goto(BASE + a, { waitUntil:'networkidle' });
    out[a] = await pg.evaluate(() => ({
      h1: (document.querySelector('h1')||{}).innerText||'',
      tiles: Array.from(document.querySelectorAll('main a[href^="/"]'))
        .map(x => ({ t:(x.innerText||'').trim().replace(/\s+/g,' ').slice(0,46), h:x.getAttribute('href') }))
        .filter(x => x.t && !/^(✕|«|☰|←|🔍)/.test(x.t))
    }));
  }
  fs.writeFileSync('/tmp/claude-0/areas.json', JSON.stringify(out,null,1));
  for (const [a,v] of Object.entries(out)) {
    console.log('\n### ' + a + '  —  ' + v.h1);
    const seen = new Set();
    v.tiles.filter(t=>{ if(seen.has(t.h))return false; seen.add(t.h); return true; }).slice(0,16)
      .forEach(t => console.log('   ' + t.t.padEnd(44) + t.h));
  }
  await br.close();
})();
