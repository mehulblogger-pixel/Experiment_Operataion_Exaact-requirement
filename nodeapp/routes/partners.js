'use strict';
const express = require('express');
const router = express.Router();
const { Op } = require('sequelize');
const db = require('../models');
const { flash } = require('../middleware/auth');
const { isValidGstin } = require('../lib/gst');

const PAGE = 40;

async function listView(req, res, roleField, title, subtitle) {
  const q = (req.query.q || '').trim();
  const status = (req.query.status || '').trim();
  const page = Math.max(1, parseInt(req.query.page, 10) || 1);
  const where = { [roleField]: true };
  if (status) where.status = status;
  if (q) {
    where[Op.or] = ['legalName', 'displayName', 'code', 'gstin', 'pan'].map((f) => ({
      [f]: { [Op.like]: `%${q}%` },
    }));
  }
  const { count, rows } = await db.BusinessPartner.findAndCountAll({
    where, order: [['legalName', 'ASC']], limit: PAGE, offset: (page - 1) * PAGE,
    include: [{ model: db.BusinessPartner, as: 'parent' }],
  });
  res.render('partners/list', {
    title, subtitle, roleField, rows, total: count, q, status, page,
    pages: Math.ceil(count / PAGE), choices: db.choices,
  });
}

router.get('/partners/clients', (req, res, next) =>
  listView(req, res, 'isClient', 'Client Master',
    'Customers, contacts, contracts and billing references').catch(next));

router.get('/partners/vendors', (req, res, next) =>
  listView(req, res, 'isVendor', 'Vendor Master',
    'Manufacturers, suppliers, plants and their contacts').catch(next));

router.get('/partners/new', (req, res) => {
  res.render('partners/form', {
    title: 'New Business Partner', partner: null, choices: db.choices,
    defaultRole: req.query.role || 'isClient', error: null,
  });
});

router.post('/partners/new', async (req, res) => {
  const b = req.body;
  if (b.gstin && !isValidGstin(b.gstin)) {
    return res.render('partners/form', { title: 'New Business Partner', partner: null,
      choices: db.choices, defaultRole: 'isClient',
      error: 'GSTIN should be 15 characters, e.g. 24ADUPL3517E2ZJ.' });
  }
  if (!b.isClient && !b.isVendor && !b.isSubcontractor) {
    return res.render('partners/form', { title: 'New Business Partner', partner: null,
      choices: db.choices, defaultRole: 'isClient',
      error: 'Select at least one role (Client, Vendor or Sub-contractor).' });
  }
  const bp = await db.BusinessPartner.create({
    legalName: b.legalName, displayName: b.displayName || '',
    isClient: !!b.isClient, isVendor: !!b.isVendor, isSubcontractor: !!b.isSubcontractor,
    clientType: b.clientType || '', industry: b.industry || '',
    ownershipType: b.ownershipType || '', status: b.status || 'ACTIVE',
    gstin: b.gstin || '', cin: b.cin || '', tan: b.tan || '', msmeUdyam: b.msmeUdyam || '',
    website: b.website || '', description: b.description || '',
  });
  flash(req, `${bp.name()} created (${bp.code}).`);
  res.redirect(`/partners/${bp.id}`);
});

async function loadDetail(id) {
  return db.BusinessPartner.findByPk(id, {
    include: [
      { model: db.PartnerAddress, as: 'addresses', include: [{ model: db.PartnerContact, as: 'contacts' }] },
      { model: db.PartnerContact, as: 'contacts', include: [{ model: db.PartnerAddress, as: 'address' }] },
      { model: db.PartnerRegistration, as: 'registrations' },
      { model: db.PartnerNote, as: 'notes' },
      { model: db.PartnerContract, as: 'contracts' },
      { model: db.PartnerPurchaseOrder, as: 'purchaseOrders', include: [{ model: db.POLineItem, as: 'lineItems' }] },
      { model: db.PartnerRelationship, as: 'relationships', include: [{ model: db.BusinessPartner, as: 'related' }] },
      { model: db.BusinessPartner, as: 'subsidiaries' },
      { model: db.BusinessPartner, as: 'parent' },
    ],
  });
}

router.get('/partners/:id', async (req, res, next) => {
  try {
    const p = await loadDetail(req.params.id);
    if (!p) return next();
    const timeline = [];
    if (p.createdAt) timeline.push({ date: p.createdAt, icon: '🏢', text: `Partner record created (${p.code})` });
    (p.notes || []).forEach((n) => timeline.push({ date: n.createdAt, icon: '📝', text: `Note: ${n.note.slice(0, 70)}` }));
    (p.contracts || []).forEach((c) => timeline.push({ date: c.startDate || c.createdAt, icon: '📄', text: `Contract ${c.contractNumber}` }));
    (p.purchaseOrders || []).forEach((o) => timeline.push({ date: o.startDate || o.createdAt, icon: '🧾', text: `PO ${o.poNumber || '(open)'}` }));
    timeline.sort((a, z) => new Date(z.date || 0) - new Date(a.date || 0));
    res.render('partners/detail', { title: p.name(), p, tab: req.query.tab || 'overview', choices: db.choices, timeline });
  } catch (e) { next(e); }
});

router.post('/partners/:id/edit', async (req, res, next) => {
  try {
    const p = await db.BusinessPartner.findByPk(req.params.id);
    if (!p) return next();
    const b = req.body;
    Object.assign(p, {
      legalName: b.legalName, displayName: b.displayName || '',
      isClient: !!b.isClient, isVendor: !!b.isVendor, isSubcontractor: !!b.isSubcontractor,
      clientType: b.clientType || '', industry: b.industry || '', ownershipType: b.ownershipType || '',
      status: b.status || 'ACTIVE', gstin: b.gstin || '', cin: b.cin || '', tan: b.tan || '',
      msmeUdyam: b.msmeUdyam || '', website: b.website || '', description: b.description || '',
    });
    await p.save();
    flash(req, `${p.name()} updated.`);
    res.redirect(`/partners/${p.id}`);
  } catch (e) { next(e); }
});

router.get('/partners/:id/edit', async (req, res, next) => {
  const p = await db.BusinessPartner.findByPk(req.params.id);
  if (!p) return next();
  res.render('partners/form', { title: `Edit ${p.name()}`, partner: p, choices: db.choices, defaultRole: 'isClient', error: null });
});

// Child add-actions
function addChild(model, tab, fields, extra) {
  return async (req, res, next) => {
    try {
      const p = await db.BusinessPartner.findByPk(req.params.id);
      if (!p) return next();
      const payload = { partnerId: p.id };
      fields.forEach((f) => { payload[f] = req.body[f] || null; });
      if (extra) extra(payload, req);
      await model.create(payload);
      flash(req, 'Added.');
      res.redirect(`/partners/${p.id}?tab=${tab}`);
    } catch (e) { next(e); }
  };
}

router.post('/partners/:id/address', addChild(db.PartnerAddress, 'addresses',
  ['addressType', 'label', 'line1', 'line2', 'city', 'state', 'pincode', 'country'],
  (p, req) => { p.isPrimary = !!req.body.isPrimary; }));
router.post('/partners/:id/contact', addChild(db.PartnerContact, 'contacts',
  ['name', 'designation', 'department', 'email', 'mobile', 'phone'],
  (p, req) => { p.isPrimary = !!req.body.isPrimary; }));
router.post('/partners/:id/registration', addChild(db.PartnerRegistration, 'registration',
  ['docType', 'number', 'validTo', 'notes']));
router.post('/partners/:id/note', addChild(db.PartnerNote, 'notes', ['note'],
  (p, req) => { p.authorName = req.user ? req.user.fullName() : ''; }));
router.post('/partners/:id/contract', addChild(db.PartnerContract, 'contracts',
  ['contractNumber', 'title', 'value', 'startDate', 'endDate', 'notes'],
  (p, req) => { p.isActive = req.body.isActive !== undefined; }));
router.post('/partners/:id/po', async (req, res, next) => {
  try {
    const p = await db.BusinessPartner.findByPk(req.params.id);
    if (!p) return next();
    const b = req.body;
    const po = await db.PartnerPurchaseOrder.create({
      partnerId: p.id, poNumber: b.poNumber || '', poType: b.poType || 'REGULAR',
      title: b.title || '', value: b.value || null, startDate: b.startDate || null,
      endDate: b.endDate || null, notes: b.notes || '',
    });
    flash(req, 'Purchase order added.');
    res.redirect(`/partners/po/${po.id}`);
  } catch (e) { next(e); }
});
router.post('/partners/:id/relationship', addChild(db.PartnerRelationship, 'relationships',
  ['relationType', 'relatedId', 'notes']));

router.get('/partners/po/:poId', async (req, res, next) => {
  try {
    const po = await db.PartnerPurchaseOrder.findByPk(req.params.poId, {
      include: [{ model: db.BusinessPartner, as: 'partner' },
        { model: db.PartnerContract, as: 'contract' },
        { model: db.POLineItem, as: 'lineItems' }],
    });
    if (!po) return next();
    res.render('partners/po_detail', { title: `PO ${po.poNumber}`, po, choices: db.choices });
  } catch (e) { next(e); }
});
router.post('/partners/po/:poId/item', async (req, res, next) => {
  try {
    const po = await db.PartnerPurchaseOrder.findByPk(req.params.poId);
    if (!po) return next();
    const b = req.body;
    await db.POLineItem.create({ purchaseOrderId: po.id, description: b.description,
      itemType: b.itemType || 'MANDAYS', quantity: b.quantity || 0, rate: b.rate || null,
      consumed: b.consumed || 0 });
    flash(req, 'Line item added.');
    res.redirect(`/partners/po/${po.id}`);
  } catch (e) { next(e); }
});

module.exports = router;
