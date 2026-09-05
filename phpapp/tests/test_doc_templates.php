<?php
// Phase 5.1B — configurable Document Studio (offer/appointment/other letters).
t_section('document studio (Phase 5.1B)');

doc_tpl_migrate();
recruit_offer_migrate();

// Seeded templates exist.
t_ok(doc_tpl_by_code('OFFER') !== null, 'a default offer-letter template is seeded');
t_ok(doc_tpl_by_code('APPOINTMENT') !== null, 'a default appointment-letter template is seeded');

// A candidate with a salary structure + an offer (for token values).
db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,designation,mobile,email,stage,created_at) VALUES ('CAN-DOC','Ravi','Sharma','Accountant','+91 98250 11111','ravi@example.com','OFFERED',?)")->execute([date('c')]);
$cid = (int)db()->lastInsertId();
sal_save($cid, ['c_BASIC' => 400000, 'c_SPECIAL' => 100000]);
offer_create($cid, ['joining_date' => '2026-10-01', 'offer_terms' => 'Standard terms']);
$cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);

// Token map auto-fills from the data we hold.
$map = doc_token_map($cand);
t_ok(strpos($map['name'], 'Ravi') !== false, 'the name token is filled from the candidate');
t_eq($map['position'], 'Accountant', 'the position token is filled');
t_eq($map['phone'], '+91 98250 11111', 'the phone token is filled from mobile');
t_ok($map['ctc'] !== '', 'the CTC token is filled from the salary structure');
t_ok(strpos($map['salary_table'], 'Total CTC') !== false, 'the salary_table token renders the structure');
t_eq($map['address'], '', 'a field we do not hold (address) is empty');

// Rendering a template auto-fills and HIGHLIGHTS what is missing.
$tpl = ['body' => "Dear {name}, position {position}, CTC {ctc}, address {address}. {salary_table}"];
$r = doc_render_template($tpl, $cand);
t_ok(strpos($r['html'], 'Ravi') !== false, 'the rendered letter contains the candidate name');
t_ok(strpos($r['html'], 'Total CTC') !== false, 'the rendered letter embeds the salary table');
t_ok(isset($r['missing']['address']), 'a missing field is reported');
t_ok(strpos($r['html'], 'address missing') !== false, 'the missing field is highlighted in the letter');

// An unknown token is flagged, not silently dropped.
$r2 = doc_render_template(['body' => 'Hello {not_a_token}'], $cand);
t_ok(isset($r2['missing']['not_a_token']), 'an unknown token is flagged');

// CRUD: add + disable (never delete).
$id = doc_tpl_save(0, ['code' => 'NDA', 'name' => 'NDA', 'doc_type' => 'OTHER', 'body' => 'By {name} on {date}.']);
t_ok($id > 0, 'a new template can be added');
$before = count(doc_tpl_all(true));
doc_tpl_set_active($id, false);
t_ok(count(doc_tpl_all(true)) < $before, 'a disabled template is hidden but not deleted');

// The offer letter uses the configurable OFFER template when issued.
$oid = offer_current($cid)['id'];
offer_submit($oid); offer_approve($oid); offer_issue($oid);
$o = offer_get($oid);
t_ok(strpos((string)$o['letter_html'], 'Ravi') !== false, 'the issued offer letter is built from the template with the candidate name');
t_ok(strpos((string)$o['letter_html'], 'Offer of employment') !== false || strpos((string)$o['letter_html'], 'pleased to offer') !== false, 'the issued letter uses the configured offer template');
