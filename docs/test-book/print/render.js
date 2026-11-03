const { chromium } = require('playwright'); const fs = require('fs'); const path = require('path');
const dir = process.argv[2], out = process.argv[3], only = process.argv[4] || '';
(async () => {
  const man = JSON.parse(fs.readFileSync(path.join(dir, 'manifest.json'), 'utf8'));
  const b = await chromium.launch(); const ctx = await b.newContext(); const p = await ctx.newPage();
  for (const [name, label] of man) {
    if (only && !only.split(',').includes(name)) continue;
    await p.goto('file://' + path.join(dir, name + '.html'), { waitUntil: 'load', timeout: 0 });
    const foot = `<div style="font-family:'DejaVu Sans',sans-serif;font-size:7.5px;color:#64748b;width:100%;padding:0 12mm;display:flex;justify-content:space-between">
      <span>EXAACT Test Book · ${label.replace(/&/g,'&amp;')}</span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>`;
    await p.pdf({ path: path.join(out, 'EXAACT-Test-Book-' + name + '.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true,
      displayHeaderFooter: true, headerTemplate: '<span></span>', footerTemplate: foot, timeout: 0 });
    console.log('done', name);
  }
  await b.close();
})();
