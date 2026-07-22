'use strict';
const express = require('express');
const router = express.Router();

router.get('/', (req, res) => {
  res.render('dashboard', {
    title: 'Dashboard',
    stats: { open: 0, pending: 0, overdue: 0, completed: 0 },
  });
});

module.exports = router;
