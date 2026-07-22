'use strict';
const { DataTypes } = require('sequelize');
const bcrypt = require('bcryptjs');

const ROLES = {
  DIRECTOR: 'Director',
  SBU_HEAD: 'SBU Head',
  BRANCH_MANAGER: 'Branch Manager',
  OPERATION_MANAGER: 'Operation Manager',
  ASST_OPERATION_MANAGER: 'Assistant Operation Manager',
  PROJECT_COORDINATOR: 'Project Coordinator',
  INSPECTION_COORDINATOR: 'Inspection Coordinator',
  INSPECTOR: 'Inspector / Engineer',
  BUSINESS_DEV_MANAGER: 'Business Development Manager',
  KEY_ACCOUNTS_MANAGER: 'Key Accounts Manager',
  MARKETING_MANAGER: 'Marketing Manager',
  MARKETING_EXECUTIVE: 'Marketing Executive',
  ACCOUNTS: 'Accounts / Back Office',
  ADMIN: 'System Admin',
};

module.exports = (sequelize) => {
  const User = sequelize.define('User', {
    username: { type: DataTypes.STRING, allowNull: false, unique: true },
    passwordHash: { type: DataTypes.STRING, allowNull: false },
    firstName: { type: DataTypes.STRING, defaultValue: '' },
    lastName: { type: DataTypes.STRING, defaultValue: '' },
    email: { type: DataTypes.STRING, defaultValue: '' },
    role: { type: DataTypes.STRING, defaultValue: 'INSPECTION_COORDINATOR' },
    phone: { type: DataTypes.STRING, defaultValue: '' },
    isSuperuser: { type: DataTypes.BOOLEAN, defaultValue: false },
    isActive: { type: DataTypes.BOOLEAN, defaultValue: true },
    homeOfficeId: { type: DataTypes.INTEGER, allowNull: true },
    sbuId: { type: DataTypes.INTEGER, allowNull: true },
    inspectorId: { type: DataTypes.INTEGER, allowNull: true },
  }, {
    tableName: 'users',
  });

  User.ROLES = ROLES;

  User.prototype.setPassword = function (raw) {
    this.passwordHash = bcrypt.hashSync(raw, 10);
  };
  User.prototype.checkPassword = function (raw) {
    return bcrypt.compareSync(raw, this.passwordHash || '');
  };
  User.prototype.fullName = function () {
    return `${this.firstName} ${this.lastName}`.trim() || this.username;
  };
  User.prototype.roleLabel = function () {
    return ROLES[this.role] || this.role;
  };
  User.prototype.canManageUsers = function () {
    return this.isSuperuser || this.role === 'ADMIN';
  };
  User.prototype.seesAllOffices = function () {
    return this.isSuperuser || ['DIRECTOR', 'SBU_HEAD', 'ADMIN'].includes(this.role);
  };

  return User;
};
