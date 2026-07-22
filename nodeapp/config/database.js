'use strict';
const path = require('path');
const { Sequelize } = require('sequelize');

/*
 * Database connection.
 * Production (MilesWeb): set DB_NAME / DB_USER / DB_PASS (MySQL from the panel's
 * "Databases" section). Development: with nothing set, it uses a local SQLite
 * file so the app runs with zero configuration.
 */
let sequelize;

if (process.env.DB_NAME) {
  sequelize = new Sequelize(
    process.env.DB_NAME,
    process.env.DB_USER,
    process.env.DB_PASS,
    {
      host: process.env.DB_HOST || 'localhost',
      port: process.env.DB_PORT || 3306,
      dialect: 'mysql',
      logging: false,
      define: { charset: 'utf8mb4' },
    }
  );
} else {
  sequelize = new Sequelize({
    dialect: 'sqlite',
    storage: process.env.SQLITE_PATH || path.join(__dirname, '..', 'data.sqlite'),
    logging: false,
  });
}

module.exports = sequelize;
