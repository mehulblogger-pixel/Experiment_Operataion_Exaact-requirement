'use strict';
require('dotenv').config();

const path = require('path');
const express = require('express');
const session = require('express-session');
const morgan = require('morgan');
const methodOverride = require('method-override');
const SequelizeStore = require('connect-session-sequelize')(session.Store);

const db = require('./models');
const { attachUser, requireLogin } = require('./middleware/auth');

const app = express();

// Views
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Static
app.use('/static', express.static(path.join(__dirname, 'public')));

// Parsing / logging
app.use(express.urlencoded({ extended: true }));
app.use(methodOverride('_method'));
if (process.env.NODE_ENV !== 'production') app.use(morgan('dev'));

// Sessions (stored in the database so logins survive restarts)
const sessionStore = new SequelizeStore({ db: db.sequelize, tableName: 'sessions' });
app.use(session({
  secret: process.env.SESSION_SECRET || 'dev-secret-change-me',
  store: sessionStore,
  resave: false,
  saveUninitialized: false,
  cookie: { maxAge: 1000 * 60 * 60 * 12 },
}));

app.use(attachUser);

// Routes
app.use('/', require('./routes/auth'));
app.use('/', requireLogin, require('./routes/dashboard'));

// 404
app.use((req, res) => {
  res.status(404).render('error', { title: 'Not found', message: 'Page not found.' });
});

// Boot: connect, create tables, ensure an admin, then listen.
const PORT = process.env.PORT || 3000;

async function start() {
  await db.sequelize.authenticate();
  await sessionStore.sync();
  await db.sequelize.sync();
  await ensureAdmin();
  app.listen(PORT, () => console.log(`InspexOps running on port ${PORT}`));
}

async function ensureAdmin() {
  const username = process.env.ADMIN_USERNAME || 'admin';
  const password = process.env.ADMIN_PASSWORD || 'admin12345';
  let user = await db.User.findOne({ where: { username } });
  if (!user) {
    user = db.User.build({
      username,
      email: process.env.ADMIN_EMAIL || 'admin@example.com',
    });
  }
  user.isSuperuser = true;
  user.isActive = true;
  user.role = 'ADMIN';
  user.setPassword(password);
  await user.save();
  console.log(`Admin ready: username="${username}"`);
}

start().catch((err) => {
  console.error('Failed to start:', err);
  process.exit(1);
});

module.exports = app;
