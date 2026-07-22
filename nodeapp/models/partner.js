'use strict';
const { DataTypes } = require('sequelize');
const { cleanGstin, panFromGstin, stateFromGstin, shortToken } = require('../lib/gst');

const CLIENT_TYPES = {
  OWNER: 'Owner / End Client', EPC: 'EPC Contractor', PMC: 'PMC / Project Management',
  CONSULTANT: 'Consultant', MANUFACTURER: 'Manufacturer / Supplier',
  TRADER: 'Trader / Distributor', SUBVENDOR: 'Sub-vendor', OTHER: 'Other',
};
const INDUSTRIES = {
  POWER: 'Power / Transmission', RENEWABLE: 'Renewable (Solar / Wind)', OIL_GAS: 'Oil & Gas',
  WATER: 'Water & Infrastructure', MINING: 'Mining & Metals', STEEL: 'Steel', CEMENT: 'Cement',
  MANUFACTURING: 'Manufacturing', INFRA: 'Infrastructure / Construction', RAIL: 'Railways',
  CHEMICAL: 'Chemical / Fertiliser', OTHER: 'Other',
};
const OWNERSHIP = {
  PVT_LTD: 'Private Limited', PUB_LTD: 'Public Limited', LLP: 'LLP', PARTNERSHIP: 'Partnership',
  PROPRIETOR: 'Proprietorship', PSU: 'Government / PSU', TRUST: 'Trust / Society',
  MNC: 'MNC / Foreign', OTHER: 'Other',
};
const STATUSES = {
  ACTIVE: 'Active', INACTIVE: 'Inactive', ON_HOLD: 'On hold', BLACKLISTED: 'Blacklisted',
  PROSPECT: 'Prospect',
};
const ADDRESS_TYPES = {
  REGISTERED: 'Registered Office', CORPORATE: 'Corporate Office', BRANCH: 'Branch Office',
  PURCHASE: 'Purchase Office', BILLING: 'Billing Address', PLANT: 'Plant', FACTORY: 'Factory',
  WAREHOUSE: 'Warehouse', PROJECT_SITE: 'Project Site', SITE_OFFICE: 'Site Office',
};
const REG_TYPES = { GSTIN: 'GSTIN', PAN: 'PAN', TAN: 'TAN', CIN: 'CIN', MSME: 'MSME / Udyam', ISO: 'ISO Certificate', PQ: 'Pre-Qualification', OTHER: 'Other' };
const PO_TYPES = { REGULAR: 'Regular (fixed value)', OPEN: 'Open order (no PO / ARC)', ARC: 'ARC / Rate contract' };
const PO_ITEM_TYPES = { MANDAYS: 'Man-days', MONTHS: 'Months (deputation)', AUDIT_DAYS: 'Technical audit days', VISITS: 'Visits', LOT: 'Lot / Lumpsum', OTHER: 'Other' };
const REL_TYPES = { SUBSIDIARY: 'Subsidiary of', JV: 'Joint Venture with', CONSORTIUM: 'Consortium with', EPC_FOR: 'EPC Contractor for', CONSULTANT_FOR: 'Consultant for', SUPPLIER_TO: 'Supplier to', OTHER: 'Other' };

module.exports = (sequelize) => {
  const BusinessPartner = sequelize.define('BusinessPartner', {
    code: { type: DataTypes.STRING, unique: true },
    legalName: { type: DataTypes.STRING, allowNull: false },
    displayName: { type: DataTypes.STRING, defaultValue: '' },
    isClient: { type: DataTypes.BOOLEAN, defaultValue: false },
    isVendor: { type: DataTypes.BOOLEAN, defaultValue: false },
    isSubcontractor: { type: DataTypes.BOOLEAN, defaultValue: false },
    clientType: { type: DataTypes.STRING, defaultValue: '' },
    industry: { type: DataTypes.STRING, defaultValue: '' },
    ownershipType: { type: DataTypes.STRING, defaultValue: '' },
    status: { type: DataTypes.STRING, defaultValue: 'ACTIVE' },
    parentId: { type: DataTypes.INTEGER, allowNull: true },
    registeringBranchId: { type: DataTypes.INTEGER, allowNull: true },
    gstin: { type: DataTypes.STRING, defaultValue: '' },
    pan: { type: DataTypes.STRING, defaultValue: '' },
    cin: { type: DataTypes.STRING, defaultValue: '' },
    tan: { type: DataTypes.STRING, defaultValue: '' },
    msmeUdyam: { type: DataTypes.STRING, defaultValue: '' },
    state: { type: DataTypes.STRING, defaultValue: '' },
    website: { type: DataTypes.STRING, defaultValue: '' },
    description: { type: DataTypes.TEXT, defaultValue: '' },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, {
    tableName: 'business_partners',
    hooks: {
      beforeSave: async (bp) => {
        bp.gstin = cleanGstin(bp.gstin);
        if (bp.gstin) {
          if (!bp.pan) bp.pan = panFromGstin(bp.gstin);
          if (!bp.state) bp.state = stateFromGstin(bp.gstin);
        }
        bp.pan = (bp.pan || '').trim().toUpperCase();
        if (!bp.code) {
          const branch = 'GEN';
          const prefix = `${branch}-${shortToken(bp.displayName || bp.legalName)}-`;
          const last = await BusinessPartner.findOne({
            where: { code: { [sequelize.Sequelize.Op.like]: `${prefix}%` } },
            order: [['code', 'DESC']],
          });
          const seq = last ? parseInt(last.code.split('-').pop(), 10) + 1 : 1;
          bp.code = `${prefix}${String(seq).padStart(4, '0')}`;
        }
      },
    },
  });

  BusinessPartner.prototype.name = function () { return this.displayName || this.legalName; };
  BusinessPartner.prototype.rolesLabel = function () {
    const p = [];
    if (this.isClient) p.push('Client');
    if (this.isVendor) p.push('Vendor');
    if (this.isSubcontractor) p.push('Sub-contractor');
    return p.join(' / ') || '—';
  };

  const PartnerAddress = sequelize.define('PartnerAddress', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    addressType: { type: DataTypes.STRING, defaultValue: 'REGISTERED' },
    label: { type: DataTypes.STRING, defaultValue: '' },
    line1: { type: DataTypes.STRING, defaultValue: '' },
    line2: { type: DataTypes.STRING, defaultValue: '' },
    city: { type: DataTypes.STRING, defaultValue: '' },
    state: { type: DataTypes.STRING, defaultValue: '' },
    pincode: { type: DataTypes.STRING, defaultValue: '' },
    country: { type: DataTypes.STRING, defaultValue: 'India' },
    isPrimary: { type: DataTypes.BOOLEAN, defaultValue: false },
  }, { tableName: 'partner_addresses' });

  const PartnerContact = sequelize.define('PartnerContact', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    addressId: { type: DataTypes.INTEGER, allowNull: true },
    name: { type: DataTypes.STRING, allowNull: false },
    designation: { type: DataTypes.STRING, defaultValue: '' },
    department: { type: DataTypes.STRING, defaultValue: '' },
    email: { type: DataTypes.STRING, defaultValue: '' },
    mobile: { type: DataTypes.STRING, defaultValue: '' },
    phone: { type: DataTypes.STRING, defaultValue: '' },
    isPrimary: { type: DataTypes.BOOLEAN, defaultValue: false },
  }, { tableName: 'partner_contacts' });

  const PartnerRegistration = sequelize.define('PartnerRegistration', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    docType: { type: DataTypes.STRING, defaultValue: 'GSTIN' },
    number: { type: DataTypes.STRING, defaultValue: '' },
    validTo: { type: DataTypes.DATEONLY, allowNull: true },
    notes: { type: DataTypes.STRING, defaultValue: '' },
  }, { tableName: 'partner_registrations' });

  const PartnerNote = sequelize.define('PartnerNote', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    note: { type: DataTypes.TEXT, allowNull: false },
    authorName: { type: DataTypes.STRING, defaultValue: '' },
  }, { tableName: 'partner_notes' });

  const PartnerContract = sequelize.define('PartnerContract', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    contractNumber: { type: DataTypes.STRING, allowNull: false },
    title: { type: DataTypes.STRING, defaultValue: '' },
    value: { type: DataTypes.DECIMAL(16, 2), allowNull: true },
    startDate: { type: DataTypes.DATEONLY, allowNull: true },
    endDate: { type: DataTypes.DATEONLY, allowNull: true },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
    notes: { type: DataTypes.STRING, defaultValue: '' },
  }, { tableName: 'partner_contracts' });

  const PartnerPurchaseOrder = sequelize.define('PartnerPurchaseOrder', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    contractId: { type: DataTypes.INTEGER, allowNull: true },
    poNumber: { type: DataTypes.STRING, defaultValue: '' },
    poType: { type: DataTypes.STRING, defaultValue: 'REGULAR' },
    title: { type: DataTypes.STRING, defaultValue: '' },
    value: { type: DataTypes.DECIMAL(16, 2), allowNull: true },
    startDate: { type: DataTypes.DATEONLY, allowNull: true },
    endDate: { type: DataTypes.DATEONLY, allowNull: true },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
    notes: { type: DataTypes.STRING, defaultValue: '' },
  }, { tableName: 'partner_purchase_orders' });

  const POLineItem = sequelize.define('POLineItem', {
    purchaseOrderId: { type: DataTypes.INTEGER, allowNull: false },
    description: { type: DataTypes.STRING, allowNull: false },
    itemType: { type: DataTypes.STRING, defaultValue: 'MANDAYS' },
    quantity: { type: DataTypes.DECIMAL(12, 2), defaultValue: 0 },
    rate: { type: DataTypes.DECIMAL(12, 2), allowNull: true },
    consumed: { type: DataTypes.DECIMAL(12, 2), defaultValue: 0 },
  }, { tableName: 'po_line_items' });

  const PartnerRelationship = sequelize.define('PartnerRelationship', {
    partnerId: { type: DataTypes.INTEGER, allowNull: false },
    relatedId: { type: DataTypes.INTEGER, allowNull: false },
    relationType: { type: DataTypes.STRING, defaultValue: 'OTHER' },
    notes: { type: DataTypes.STRING, defaultValue: '' },
  }, { tableName: 'partner_relationships' });

  return {
    BusinessPartner, PartnerAddress, PartnerContact, PartnerRegistration, PartnerNote,
    PartnerContract, PartnerPurchaseOrder, POLineItem, PartnerRelationship,
    choices: { CLIENT_TYPES, INDUSTRIES, OWNERSHIP, STATUSES, ADDRESS_TYPES, REG_TYPES, PO_TYPES, PO_ITEM_TYPES, REL_TYPES },
  };
};
