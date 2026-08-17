<?php
// Picking a customer on the lead / opportunity form must flow that customer's
// primary contact (and address) in from the client master — the shared
// /partner-contact endpoint that both forms call.
t_section('client contact/address flows into the lead & opportunity forms');

db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, created_at)
    VALUES (?,?,1,'ACTIVE',?)")->execute(['EdgeCraft Industries Pvt Ltd', 'EdgeCraft', date('c')]);
$pid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, designation, email, mobile, is_primary)
    VALUES (?,?,?,?,?,1)")->execute([$pid, 'R. Shah', 'Purchase Head', 'r.shah@edge.example', '9800000000']);
db()->prepare("INSERT INTO partner_addresses (partner_id, line1, city, state, pincode, is_primary)
    VALUES (?,?,?,?,?,1)")->execute([$pid, 'Plot 5, GIDC', 'Bharuch', 'Gujarat', '392015']);

$_GET['id'] = $pid;
ob_start(); ops_partner_contact(); $d = json_decode(ob_get_clean(), true);
t_ok(is_array($d), 'the endpoint returns JSON');
t_eq($d['contact']['name']   ?? '', 'R. Shah',              'the primary contact name flows in');
t_eq($d['contact']['email']  ?? '', 'r.shah@edge.example',  'the contact e-mail flows in');
t_eq($d['contact']['mobile'] ?? '', '9800000000',           'the contact mobile flows in');
t_eq($d['address']['city']   ?? '', 'Bharuch',              'the address city flows in');
t_eq($d['address']['state']  ?? '', 'Gujarat',              'the address state flows in');

// A phone-only contact (no mobile) still yields a number.
db()->prepare("INSERT INTO business_partners (legal_name, is_client, status, created_at) VALUES ('Phone Co',1,'ACTIVE',?)")->execute([date('c')]);
$pid2 = (int) db()->lastInsertId();
db()->prepare("INSERT INTO partner_contacts (partner_id, name, email, phone, is_primary) VALUES (?,?,?,?,1)")
    ->execute([$pid2, 'K. Rao', 'k@x.example', '0792200000']);
$_GET['id'] = $pid2;
ob_start(); ops_partner_contact(); $d2 = json_decode(ob_get_clean(), true);
t_eq($d2['contact']['mobile'] ?? '', '0792200000', 'a landline falls back into the mobile slot');

// A partner with no contact returns null cleanly — no error, forms just stay blank.
db()->prepare("INSERT INTO business_partners (legal_name, is_client, status, created_at) VALUES ('Empty Co',1,'ACTIVE',?)")->execute([date('c')]);
$_GET['id'] = (int) db()->lastInsertId();
ob_start(); ops_partner_contact(); $d3 = json_decode(ob_get_clean(), true);
t_ok(is_array($d3) && array_key_exists('contact', $d3) && $d3['contact'] === null,
     'a partner with no contact returns contact=null (no error)');

unset($_GET['id']);
