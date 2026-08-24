<?php
// Even an order won WITHOUT a quotation can carry one: "Generate quotation" creates a
// draft quote-of-record from the deal, pre-filled and linked. One is enough — the
// button greys out (and the service refuses) once the deal already has a quotation.
t_section('a direct order can generate a quotation-of-record');

opp_migrate();
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('GenQ Client','GenQ Client',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, partner_name, value, status, created_at) VALUES ('OPP-GQ1','Direct heat-exchanger order',?, 'GenQ Client', 180000, 'WON', ?)")->execute([$pid, date('c')]);
$oid = (int)$pdo->lastInsertId();

// It has no quote yet.
t_ok(opp_quotes($oid) === [], 'the direct-order deal starts with no quotation');

$r = opp_generate_quote($oid);
t_ok(empty($r['err']) && !empty($r['quote_id']), 'a quotation-of-record is generated');
$q = ops_one("SELECT quote_no, rev, is_current, client_id, subject, total_amount, status FROM quotations WHERE id=?", [(int)$r['quote_id']]);
t_ok($q && $q['status'] === 'DRAFT' && (int)$q['is_current'] === 1 && (int)$q['rev'] === 0, 'it is a current DRAFT revision');
t_ok((int)$q['client_id'] === $pid && $q['subject'] === 'Direct heat-exchanger order', 'the client and subject carry from the deal');
t_ok((float)$q['total_amount'] == 180000.0, 'the deal value pre-fills the draft total');

// It is linked to the deal.
$linked = opp_quotes($oid);
t_ok(count($linked) === 1 && (int)$linked[0]['id'] === (int)$r['quote_id'], 'the quotation is linked to the deal');

// One is enough — a second generate is refused (the button greys out in the UI).
t_ok(!empty(opp_generate_quote($oid)['err']), 'a deal that already has a quotation cannot generate a second');

// Guards: no customer, and a lost deal.
$pdo->prepare("INSERT INTO opportunities (ref, name, value, status, created_at) VALUES ('OPP-GQ2','No customer', 1, 'WON', ?)")->execute([date('c')]);
t_ok(!empty(opp_generate_quote((int)$pdo->lastInsertId())['err']), 'a deal with no customer cannot generate a quotation');
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, value, status, created_at) VALUES ('OPP-GQ3','Lost deal',?, 1, 'LOST', ?)")->execute([$pid, date('c')]);
t_ok(!empty(opp_generate_quote((int)$pdo->lastInsertId())['err']), 'a lost deal does not get a quotation');

// Wiring: route mapped, and the view shows the button (greyed once a quote exists).
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'opportunity-generate-quote'=>'leads'") !== false, 'the generate-quote route is mapped through the module gate');
$view = file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
t_ok(strpos($view, '/opportunity-generate-quote') !== false && strpos($view, '＋ Generate quotation') !== false,
    'the deal offers "Generate quotation"');
t_ok(strpos($view, 'disabled style="opacity:.5;cursor:not-allowed"') !== false && strpos($view, 'A quotation is already on this deal') !== false,
    'the button is greyed out once a quotation exists');
