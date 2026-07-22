'use strict';
const sequelize = require('../config/database');

const User = require('./User')(sequelize);
const masters = require('./masters')(sequelize);
const partner = require('./partner')(sequelize);

const {
  BusinessPartner, PartnerAddress, PartnerContact, PartnerRegistration, PartnerNote,
  PartnerContract, PartnerPurchaseOrder, POLineItem, PartnerRelationship, choices,
} = partner;

// --- Associations ---
BusinessPartner.hasMany(PartnerAddress, { foreignKey: 'partnerId', as: 'addresses' });
BusinessPartner.hasMany(PartnerContact, { foreignKey: 'partnerId', as: 'contacts' });
BusinessPartner.hasMany(PartnerRegistration, { foreignKey: 'partnerId', as: 'registrations' });
BusinessPartner.hasMany(PartnerNote, { foreignKey: 'partnerId', as: 'notes' });
BusinessPartner.hasMany(PartnerContract, { foreignKey: 'partnerId', as: 'contracts' });
BusinessPartner.hasMany(PartnerPurchaseOrder, { foreignKey: 'partnerId', as: 'purchaseOrders' });
BusinessPartner.hasMany(PartnerRelationship, { foreignKey: 'partnerId', as: 'relationships' });
BusinessPartner.hasMany(BusinessPartner, { foreignKey: 'parentId', as: 'subsidiaries' });
BusinessPartner.belongsTo(BusinessPartner, { foreignKey: 'parentId', as: 'parent' });

PartnerAddress.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerAddress.hasMany(PartnerContact, { foreignKey: 'addressId', as: 'contacts' });
PartnerContact.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerContact.belongsTo(PartnerAddress, { foreignKey: 'addressId', as: 'address' });
PartnerRegistration.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerNote.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerContract.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerPurchaseOrder.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerPurchaseOrder.belongsTo(PartnerContract, { foreignKey: 'contractId', as: 'contract' });
PartnerPurchaseOrder.hasMany(POLineItem, { foreignKey: 'purchaseOrderId', as: 'lineItems' });
POLineItem.belongsTo(PartnerPurchaseOrder, { foreignKey: 'purchaseOrderId', as: 'purchaseOrder' });
PartnerRelationship.belongsTo(BusinessPartner, { foreignKey: 'partnerId', as: 'partner' });
PartnerRelationship.belongsTo(BusinessPartner, { foreignKey: 'relatedId', as: 'related' });

const db = {
  sequelize, User, ...masters,
  BusinessPartner, PartnerAddress, PartnerContact, PartnerRegistration, PartnerNote,
  PartnerContract, PartnerPurchaseOrder, POLineItem, PartnerRelationship, choices,
};

module.exports = db;
