<?php
// Email the client when a report is issued (open item P2). The notification
// carries a link and a verification code — never the report or any finding.
t_section('client notified on issue (P2)');

// A client, a portal user for them, and an issued report.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Adani Ports', 'Adani Ports Ltd', 1, date('c')]);
$cid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO client_users (partner_id, email, name, is_active, perms, created_at)
    VALUES (?,?,?,?,?,?)")->execute([$cid, 'qa@adani.example', 'QA Desk', 1, '', date('c')]);

db()->prepare("INSERT INTO report_docs (irn, client_id, status, finalized, remarks, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?)")->execute(['TIR/26/2001', $cid, 'ISSUED', 1, 'SECRET-FINDING-XYZ', date('c'), date('c')]);
$id = (int)db()->lastInsertId();
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);

// Recipients: the active portal user with report access.
$to = idems_client_notify_emails($cid);
t_ok(in_array('qa@adani.example', $to, true), 'an active portal user with report access is a recipient');

// Notify — records one intended e-mail (mail is disabled in tests; ops_mail
// still logs it, which is the honest behaviour on a box with no SMTP).
$before = (int)ops_val("SELECT COUNT(*) FROM email_log WHERE kind='report_issued'");
$n = idems_notify_client_issued($doc);
t_ok($n === 1, 'exactly one recipient is notified');
$after = (int)ops_val("SELECT COUNT(*) FROM email_log WHERE kind='report_issued'");
t_ok($after === $before + 1, 'the notification is written to the mail log');

// The e-mail names the report but leaks nothing confidential.
$mail = ops_one("SELECT * FROM email_log WHERE kind='report_issued' ORDER BY id DESC LIMIT 1");
t_ok(strpos((string)$mail['subject'], 'TIR/26/2001') !== false, 'the subject names the report reference');
t_ok(strpos((string)$mail['body'], 'SECRET-FINDING-XYZ') === false, 'the body never carries the findings');
t_eq($mail['to_addr'], 'qa@adani.example', 'it goes to the client, not internally');

// A portal user restricted away from reports is not told.
db()->prepare("INSERT INTO client_users (partner_id, email, name, is_active, perms, created_at)
    VALUES (?,?,?,?,?,?)")->execute([$cid, 'accounts@adani.example', 'Accounts', 1, 'invoices', date('c')]);
$to2 = idems_client_notify_emails($cid);
t_ok(!in_array('accounts@adani.example', $to2, true), 'a portal user without report access is not notified');

// Fallback: no portal users -> the primary partner contact.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Small Fab', 'Small Fab Co', 1, date('c')]);
$cid2 = (int)db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, is_primary) VALUES (?,?,?,?)")
    ->execute([$cid2, 'Owner', 'owner@smallfab.example', 1]);
$to3 = idems_client_notify_emails($cid2);
t_ok($to3 === ['owner@smallfab.example'], 'with no portal users, the primary contact is used');

// The feature switch turns it off.
setting_set('notify_client_on_issue', '0');
t_ok(idems_notify_client_issued($doc) === 0, 'switching the setting off sends nothing');
setting_set('notify_client_on_issue', '1');

// A report with no client is a no-op, never an error.
db()->prepare("INSERT INTO report_docs (irn, status, finalized, created_at, updated_at) VALUES (?,?,?,?,?)")
    ->execute(['TIR/26/2002', 'ISSUED', 1, date('c'), date('c')]);
$orphan = ops_one("SELECT * FROM report_docs WHERE irn='TIR/26/2002'");
t_ok(idems_notify_client_issued($orphan) === 0, 'a report with no client notifies nobody, without error');
