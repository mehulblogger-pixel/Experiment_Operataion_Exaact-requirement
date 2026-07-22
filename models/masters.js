'use strict';
const { DataTypes } = require('sequelize');

// Configurable master/dropdown data.
module.exports = (sequelize) => {
  const Office = sequelize.define('Office', {
    code: { type: DataTypes.STRING, allowNull: false, unique: true },
    name: { type: DataTypes.STRING, defaultValue: '' },
    city: { type: DataTypes.STRING, defaultValue: '' },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'offices' });

  const SBU = sequelize.define('SBU', {
    code: { type: DataTypes.STRING, allowNull: false, unique: true },
    name: { type: DataTypes.STRING, defaultValue: '' },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'sbus' });

  const JobType = sequelize.define('JobType', {
    name: { type: DataTypes.STRING, allowNull: false },
    parentId: { type: DataTypes.INTEGER, allowNull: true },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'job_types' });

  const ProductCategory = sequelize.define('ProductCategory', {
    name: { type: DataTypes.STRING, allowNull: false },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'product_categories' });

  const VendorLocation = sequelize.define('VendorLocation', {
    name: { type: DataTypes.STRING, allowNull: false },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'vendor_locations' });

  const PaymentTerm = sequelize.define('PaymentTerm', {
    name: { type: DataTypes.STRING, allowNull: false },
    isAdvance: { type: DataTypes.BOOLEAN, defaultValue: false },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'payment_terms' });

  const ReportFormat = sequelize.define('ReportFormat', {
    name: { type: DataTypes.STRING, allowNull: false },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'report_formats' });

  const Inspector = sequelize.define('Inspector', {
    name: { type: DataTypes.STRING, allowNull: false },
    inspectorType: { type: DataTypes.STRING, defaultValue: 'EMPLOYEE' },
    discipline: { type: DataTypes.STRING, defaultValue: '' },
    email: { type: DataTypes.STRING, defaultValue: '' },
    phone: { type: DataTypes.STRING, defaultValue: '' },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
  }, { tableName: 'inspectors' });

  return { Office, SBU, JobType, ProductCategory, VendorLocation, PaymentTerm, ReportFormat, Inspector };
};
