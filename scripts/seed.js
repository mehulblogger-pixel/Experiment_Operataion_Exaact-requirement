'use strict';
// Seed masters + business partners from data/seed_data.json. Idempotent.
// Exposed as seedAll(db) so the app can auto-seed on first boot, and runnable
// directly: `node scripts/seed.js`.
const path = require('path');
const { normalizeName } = require('../lib/gst');

async function seedAll(db) {
  const data = require(path.join(__dirname, '..', 'data', 'seed_data.json'));

  for (const code of Object.keys(data.sbus || {})) {
    await db.SBU.findOrCreate({ where: { code }, defaults: { name: data.sbus[code] } });
  }
  for (const code of data.offices || []) {
    await db.Office.findOrCreate({ where: { code }, defaults: { name: code.replace(/_/g, ' ') } });
  }
  for (const name of data.product_categories || []) {
    await db.ProductCategory.findOrCreate({ where: { name: name.slice(0, 200) } });
  }
  for (const name of data.vendor_locations || []) {
    await db.VendorLocation.findOrCreate({ where: { name: name.slice(0, 120) } });
  }
  for (const row of data.inspectors || []) {
    await db.Inspector.findOrCreate({ where: { name: row.name.slice(0, 160) },
      defaults: { discipline: (row.discipline || '').slice(0, 80) } });
  }

  const existing = await db.BusinessPartner.findAll();
  const byNorm = new Map();
  existing.forEach((bp) => byNorm.set(normalizeName(bp.legalName), bp));

  async function ensurePartner(name) {
    const key = normalizeName(name);
    if (byNorm.has(key)) return byNorm.get(key);
    const bp = await db.BusinessPartner.create({ legalName: name.slice(0, 255) });
    byNorm.set(key, bp);
    return bp;
  }

  for (const name of data.clients || []) {
    const bp = await ensurePartner(name);
    if (!bp.isClient) { bp.isClient = true; await bp.save(); }
  }
  for (const row of data.vendors || []) {
    const vname = typeof row === 'string' ? row : row.name;
    const bp = await ensurePartner(vname);
    if (!bp.isVendor) { bp.isVendor = true; await bp.save(); }
  }

  return db.BusinessPartner.count();
}

module.exports = { seedAll };

if (require.main === module) {
  require('dotenv').config();
  const db = require('../models');
  db.sequelize.sync()
    .then(() => seedAll(db))
    .then((n) => { console.log(`Seeded. ${n} partners total.`); process.exit(0); })
    .catch((e) => { console.error(e); process.exit(1); });
}
