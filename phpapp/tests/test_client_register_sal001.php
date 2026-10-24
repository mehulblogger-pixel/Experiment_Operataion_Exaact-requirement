<?php
// Test book SAL-001 (owner, 20 Oct 2026), the client register:
//  · a refused save must keep what was typed ("all details vanish");
//  · a status other than Active needs its reason, shown on the record;
//  · a contact whose mobile / e-mail another client already has is FLAGGED;
//  · the client page trails through Directory → Client register, not "Home › Partner";
//  · an administrator can move a client to another office; the code is kept.
t_section('Client register — SAL-001: typed values kept, reasons, duplicate contacts, trail, office');

// Reason for a non-Active status
t_eq(partner_status_reason_problem('ACTIVE', ''), '', 'Active needs no reason');
t_eq(partner_status_reason_problem('PROSPECT', ''), '', 'Prospect needs no reason');
t_ok(partner_status_reason_problem('ON_HOLD', '') !== '', 'On hold without a reason is refused');
t_ok(partner_status_reason_problem('BLACKLISTED', ' ') !== '', 'Blacklisted with only spaces is refused');
t_ok(partner_status_reason_problem('REJECTED', '') !== '', "a company's own extra status (e.g. Rejected) needs one too");
t_eq(partner_status_reason_problem('ON_HOLD', 'Credit review'), '', 'with a reason it is accepted');

// Duplicate contact flag
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,is_client,status,created_at) VALUES (?,?,?,?,?)")->execute(['T-SAL1-A', 'SAL1 Alpha Ltd', 1, 'ACTIVE', date('c')]);
    $a = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,is_client,status,created_at) VALUES (?,?,?,?,?)")->execute(['T-SAL1-B', 'SAL1 Beta Ltd', 1, 'ACTIVE', date('c')]);
    $b = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_contacts (partner_id,name,email,mobile,is_primary) VALUES (?,?,?,?,1)")->execute([$a, 'Ravi Shah', 'Ravi@Alpha.example', '+91 98250 11111']);
    $hits = partner_contact_clash('98250-11111', '', $b);
    t_ok(count($hits) === 1 && strpos($hits[0], 'SAL1 Alpha Ltd') !== false && strpos($hits[0], 'same mobile') !== false, 'the same mobile written differently (+91 …, dashes) is found on the other client');
    $hits = partner_contact_clash('', 'ravi@alpha.example', $b);
    t_ok(count($hits) === 1 && strpos($hits[0], 'same e-mail') !== false, 'the same e-mail in other letter case is found');
    t_eq(partner_contact_clash('98250 11111', '', $a), [], "a client's own contact is not a clash with itself");
    t_eq(partner_contact_clash('12345', '', $b), [], 'a short number is not compared');
    t_ok(strpos(partner_contact_clash_msg(partner_contact_clash('9825011111', '', $b)), 'Please check') === 0, 'the flag is a "please check", never a refusal');
} finally { if ($own) $pdo->rollBack(); }

// Trail for the shared client/vendor page
nav_under('partner', 'clients');
$t = nav_trail('partner', 'TB Refinery Ltd');
$labels = array_column($t, 'label');
// (The Directory step itself depends on who is signed in; the live screen,
// checked over HTTP as the admin, reads Home › Directory › Client register › …)
t_ok(in_array('/clients', array_column($t, 'url'), true) && end($labels) === 'TB Refinery Ltd', 'the client page trails through the client register to the client: ' . implode(' › ', $labels));
t_ok(!in_array('Partner', $labels, true), '…and never "Partner"');
t_eq(nav_back_info('partner')['url'], '/clients', 'Back goes to the client register');
nav_under('partner-edit', 'clients', ['label' => 'TB Refinery Ltd', 'url' => '/partner?id=7']);
t_eq(nav_back_info('partner-edit')['url'], '/partner?id=7', 'Back from Edit goes to the client itself');

// Source checks: the handlers pass what was typed, the office can move
$idx = file_get_contents(__DIR__ . '/../index.php'); $form = file_get_contents(__DIR__ . '/../views/form.php');
t_ok(strpos($idx, "'old' => \$_POST") !== false && strpos($form, '$d    = array_merge(') !== false, 'a refused save re-shows the form with what was typed');
t_ok(strpos($idx, 'PARTNER_OFFICE_MOVED') !== false && strpos($form, 'name="home_branch_id"') !== false, 'an administrator can move a client to another office, and it is logged');
