'use strict';
const express = require('express');
const router = express.Router();
const db = require('../models');
const { flash } = require('../middleware/auth');

router.get('/login', (req, res) => {
  if (req.user) return res.redirect('/');
  res.render('login', { title: 'Sign in', error: null });
});

router.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = await db.User.findOne({ where: { username } });
  if (!user || !user.isActive || !user.checkPassword(password)) {
    return res.render('login', { title: 'Sign in', error: 'Invalid username or password.' });
  }
  req.session.userId = user.id;
  flash(req, `Welcome, ${user.fullName()}.`);
  res.redirect('/');
});

router.post('/logout', (req, res) => {
  req.session.destroy(() => res.redirect('/login'));
});

module.exports = router;
