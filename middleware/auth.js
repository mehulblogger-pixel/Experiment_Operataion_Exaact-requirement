'use strict';
const db = require('../models');

// Load the logged-in user onto req/res for every request.
async function attachUser(req, res, next) {
  res.locals.currentUser = null;
  res.locals.messages = req.session.messages || [];
  req.session.messages = [];
  if (req.session.userId) {
    const user = await db.User.findByPk(req.session.userId);
    if (user && user.isActive) {
      req.user = user;
      res.locals.currentUser = user;
    }
  }
  next();
}

function requireLogin(req, res, next) {
  if (!req.user) return res.redirect('/login');
  next();
}

function requireManageUsers(req, res, next) {
  if (!req.user || !req.user.canManageUsers()) {
    return res.status(403).render('error', {
      title: 'Forbidden', message: 'You do not have access to this page.',
    });
  }
  next();
}

// Flash-style message helper.
function flash(req, text, tag = 'success') {
  req.session.messages = req.session.messages || [];
  req.session.messages.push({ text, tag });
}

module.exports = { attachUser, requireLogin, requireManageUsers, flash };
