<?php
// The pipeline's missing forward step: create a quotation straight from an
// opportunity (you could previously only attach an existing one). It must carry
// the customer and the working value, land as a DRAFT, and link back to the deal.
t_section('create a quotation from an opportunity');

opp_migrate();
db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, created_at)
    VALUES (?,?,1,'ACTIVE',?)")->execute(['EdgeCraft Industries Pvt Ltd', 'EdgeCraft', date('c')]);
$pid = (int) db()->lastInsertId();

$r = opp_create(['name' => 'Annual vessel inspection contract', 'partner_id' => $pid, 'value' => 250000]);
t_ok(!empty($r['id']), 'an opportunity is opened');
$oid = (int) $r['id'];

$q = opp_create_quote($oid);
t_ok(!empty($q['id']) && empty($q['err']), 'a quotation is created from the deal');
$quote = ops_one("SELECT * FROM quotations WHERE id=?", [(int)$q['id']]);
t_ok(is_array($quote), 'the quotation row exists');
t_eq((int)$quote['client_id'], $pid, 'it carries the customer from the deal');
t_eq((string)$quote['status'], 'DRAFT', 'it starts as a draft');
t_eq((float)$quote['subtotal'], 250000.0, 'it carries the working value across');
t_ok(strpos((string)$quote['subject'], 'Annual vessel inspection') !== false, 'the deal name becomes the subject');

// It is linked back to the opportunity, so the forecast stays joined.
$linked = opp_quotes($oid);
t_ok(count($linked) === 1 && (int)$linked[0]['id'] === (int)$q['id'], 'the quotation is attached to the deal');

// Owner, 13 Nov 2026 (bounce list 2.1): a deal with only a customer NAME may quote —
// as a lead always could. It used to be refused ("a quotation needs a client on the
// master"), and the only way round was to mark the lead won before anything was sold.
$r2 = opp_create(['name' => 'Loose lead', 'partner_name' => 'Someone not on the master yet']);
$q2 = opp_create_quote((int)$r2['id']);
t_ok(empty($q2['err']) && !empty($q2['id']), 'a deal with only a customer name gets its quotation' . (!empty($q2['err']) ? ': ' . $q2['err'] : ''));
$qq2 = ops_one("SELECT client_id, client_name, status FROM quotations WHERE id=?", [(int)($q2['id'] ?? 0)]);
t_eq([(int)($qq2['client_id'] ?: 0), (string)($qq2['client_name'] ?? ''), (string)($qq2['status'] ?? '')], [0, 'Someone not on the master yet', 'DRAFT'],
    '…addressed to the typed name, with no customer record yet (it is created when the quotation is accepted)');
// A deal with neither a customer nor a name has nobody to address it to.
db()->prepare("INSERT INTO opportunities (ref, name, partner_name, status, created_at) VALUES (?, 'Nameless deal', '', 'OPEN', ?)")->execute(['OCQ-NN-' . getmypid(), date('c')]);
$q3 = opp_create_quote((int)db()->lastInsertId());
t_ok(!empty($q3['err']), 'a deal with no customer name at all is still refused, not fatal');
