<?php
// Pre-order control (part of B): a client can be put on hold or blocked, with a
// reason, so raising a quote or a call for them is warned/stopped. Guards the
// helpers: set, read, clear, and the block rule (blocks non-managers only).
t_section('client hold / blocked pre-order control');

$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name, is_client, status, created_at) VALUES ('Held Co', 1, 'ACTIVE', ?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId();

// No hold by default.
t_ok(partner_hold($cid) === null, 'a client has no hold by default');

// Put on HOLD.
t_ok(partner_hold_set($cid, 'HOLD', 'Payments overdue'), 'a client can be put on hold');
$h = partner_hold($cid);
t_ok(is_array($h) && $h['status'] === 'HOLD', 'the hold reads back as HOLD');
t_eq($h['reason'], 'Payments overdue', 'the reason reads back');
t_ok($h['label'] === PARTNER_HOLD_STATES['HOLD'], 'the label resolves');

// HOLD warns but never blocks (even a non-manager).
t_ok(partner_hold_blocks($cid) === false, 'HOLD never blocks — it only warns');

// BLOCK it.
partner_hold_set($cid, 'BLOCKED', 'Legal dispute');
t_eq(partner_hold($cid)['status'], 'BLOCKED', 'the client is now BLOCKED');
// In the test run there is no logged-in manager, so a block bites.
t_ok(partner_hold_blocks($cid) === true, 'BLOCKED stops a non-manager');

// Clear it.
partner_hold_set($cid, '', '');
t_ok(partner_hold($cid) === null, 'the hold can be cleared');
t_ok(partner_hold_blocks($cid) === false, 'a cleared client no longer blocks');

// An unknown status is treated as clear (never a silent invalid state).
partner_hold_set($cid, 'WEIRD', 'x');
t_ok(partner_hold($cid) === null, 'an invalid status clears the hold');

// A missing client id is safe.
t_ok(partner_hold(0) === null, 'a zero client id has no hold, without error');
