<?php
// Owner, 11 Nov 2026 — "Should a recruitment requisition have a 'Post this to the
// Marketplace' button?" — "Yes if the user is subscribed and paid for market
// place or has bought any pack."
t_section('Requisition → Marketplace');
t_as_admin();
connect_market_migrate();
if (function_exists('recruit_migrate')) recruit_migrate();
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $tag = 'RQM' . getmypid();
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, created_at) VALUES ('Secret Refinery Ltd', 'Secret Refinery', ?)")->execute([date('c')]);
    $client = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO requisitions (req_code, designation, status, quantity, client_id, discipline, deploy_location, rate_basis, billing_rate, duration_months, skills, start_date, created_at)
                   VALUES (?, 'INSPECTOR', 'OPEN', 2, ?, 'Mechanical', 'Jamnagar', 'MONTHLY', 185000, 6, 'API 510, ASNT Level II', ?, ?)")
        ->execute([$tag, $client, date('Y-m-d', strtotime('+20 days')), date('c')]);
    $rq = (int) db()->lastInsertId();

    t_ok(connect_market_can(), 'ARMING · the company has the Marketplace and this person may post');
    [$ok, $msg, $id] = cx_requirement_from_requisition($rq);
    t_ok($ok && $id > 0, 'the requisition becomes a Marketplace posting: ' . $msg);
    $r = cx_requirement_get($id);
    t_eq([$r['status'], (int) $r['positions'], $r['location'], $r['work_type'], (int) $r['requisition_id']], ['DRAFT', 2, 'Jamnagar', 'long_deployment', $rq],
        'a draft to check, for 2 people at Jamnagar, a long deployment, linked to the requisition');
    t_ok(strpos($r['title'], 'Mechanical') !== false && strpos($r['description'], 'API 510') !== false, 'the role, discipline and skills are carried across: ' . $r['title']);
    $all = json_encode($r);
    t_ok(strpos($all, 'Secret Refinery') === false && strpos($all, '185000') === false && strpos($all, '185,000') === false, 'the client\'s name and the billing rate are NOT on the posting');
    t_eq((int) $r['poster_party_id'], 0, 'it is the company\'s own posting, so it does not appear in the client\'s portal');

    [$ok2, $msg2, $id2] = cx_requirement_from_requisition($rq);
    t_ok($ok2 && $id2 === $id, 'pressing it again opens the same posting instead of a duplicate: ' . $msg2);

    db()->prepare("UPDATE requisitions SET status='HIRED' WHERE id=?")->execute([$rq]);
    cx_requirement_transition($id, 'CANCELLED');
    [$ok3, $msg3] = cx_requirement_from_requisition($rq);
    t_ok(!$ok3, 'a requisition already filled cannot be posted: ' . $msg3);

    $keep = setting_get('connect_enabled', '1');
    setting_set('connect_enabled', '0');
    db()->prepare("UPDATE requisitions SET status='OPEN' WHERE id=?")->execute([$rq]);
    [$ok4, $msg4] = cx_requirement_from_requisition($rq);
    t_ok(!$ok4 && strpos($msg4, 'plan') !== false, 'with the Marketplace switched off or not paid for, nothing is posted: ' . $msg4);
    setting_set('connect_enabled', $keep);

    $view = file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
    t_ok(strpos($view, 'connect_market_can() && function_exists(\'cx_requisition_postable\')') !== false, 'the button shows only where the company has the Marketplace and the person may post');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
