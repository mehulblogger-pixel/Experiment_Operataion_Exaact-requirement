'use strict';
const sequelize = require('../config/database');

const User = require('./User')(sequelize);

// Future modules (masters, partners, operations) register their models here and
// wire associations. Kept in one place so `sync()` sees everything.
const db = { sequelize, User };

module.exports = db;
