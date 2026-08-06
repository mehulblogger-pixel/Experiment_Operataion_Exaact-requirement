<?php
// Portal accounts linked to the contacts on the client record (open item P3).
t_section('portal account <-> contact link (P3)');

portal_migrate();

// A client with two saved contacts.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Reliance', 'Reliance Industries', 1, date('c')]);
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, designation, is_primary)
    VALUES (?,?,?,?,?)")->execute([$pid, 'Asha Rao', 'asha@ril.example', 'QA Lead', 1]);
$c1 = (int)db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, designation, is_primary)
    VALUES (?,?,?,?,?)")->execute([$pid, 'Vikram Shah', 'vikram@ril.example', 'Purchasing', 0]);

// Invite by picking a saved contact: name + email come from the contact, and
// the account is linked to it.
$r = portal_invite(0, '', '', $c1);
t_ok(empty($r['err']) && !empty($r['id']), 'inviting by contact succeeds');
$cu = ops_one("SELECT * FROM client_users WHERE id=?", [(int)$r['id']]);
t_eq((int)$cu['contact_id'], $c1, 'the account is linked to the chosen contact');
t_eq($cu['email'], 'asha@ril.example', 'the e-mail is taken from the contact');
t_eq($cu['name'], 'Asha Rao', 'the name is taken from the contact');
t_eq((int)$cu['partner_id'], $pid, 'the company is taken from the contact');

// Inviting a typed address that matches a saved contact links it automatically.
$r2 = portal_invite($pid, 'vikram@ril.example', 'V. Shah');
t_ok(empty($r2['err']), 'inviting a matching typed address succeeds');
$cu2 = ops_one("SELECT * FROM client_users WHERE id=?", [(int)$r2['id']]);
t_ok((int)$cu2['contact_id'] > 0, 'a typed address matching a saved contact is auto-linked');

// A typed address with no matching contact is accepted, just not linked.
$r3 = portal_invite($pid, 'stranger@ril.example', 'New Person');
t_ok(empty($r3['err']), 'a non-matching typed address still works');
$cu3 = ops_one("SELECT * FROM client_users WHERE id=?", [(int)$r3['id']]);
t_ok(empty($cu3['contact_id']), 'an address with no matching contact is left unlinked');

// A contact with no e-mail cannot be turned into an account.
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, is_primary) VALUES (?,?,?,?)")
    ->execute([$pid, 'No Email', '', 0]);
$cNo = (int)db()->lastInsertId();
$r4 = portal_invite(0, '', '', $cNo);
t_ok(!empty($r4['err']), 'a contact without an e-mail is refused, with a reason');

// The migrate backfill links a pre-existing unlinked account by matching e-mail.
db()->prepare("INSERT INTO client_users (partner_id, contact_id, email, name, is_active, created_at)
    VALUES (?,?,?,?,?,?)")->execute([$pid, null, 'asha@ril.example', 'Old Row', 1, date('c')]);
// (asha already has an account above; use a fresh partner+contact to isolate)
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Tata', 'Tata Steel', 1, date('c')]);
$pid2 = (int)db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, is_primary) VALUES (?,?,?,?)")
    ->execute([$pid2, 'Meera', 'meera@tata.example', 1]);
$mc = (int)db()->lastInsertId();
db()->prepare("INSERT INTO client_users (partner_id, contact_id, email, name, is_active, created_at)
    VALUES (?,?,?,?,?,?)")->execute([$pid2, null, 'meera@tata.example', 'Meera', 1, date('c')]);
$legacyId = (int)db()->lastInsertId();
portal_backfill_contact_links();
t_eq((int)ops_one("SELECT contact_id FROM client_users WHERE id=?", [$legacyId])['contact_id'], $mc,
    'the backfill links a pre-existing account to its matching contact');

// The picker map only offers client contacts that have an e-mail.
$map = portal_contacts_by_partner();
$emails = array_column($map[$pid] ?? [], 'email');
t_ok(in_array('asha@ril.example', $emails, true) && !in_array('', $emails, true),
    'the picker offers contacts with an e-mail and omits those without');
