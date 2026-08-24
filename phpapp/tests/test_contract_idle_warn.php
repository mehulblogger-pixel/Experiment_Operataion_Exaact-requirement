<?php
// A contract that goes quiet is auto-closed after ~60 idle days. That close used
// to be the FIRST anyone heard of it. Now the same idle rule is surfaced early:
// a heads-up email ~2 weeks before, an on-screen banner, and the once-per-window
// guard so it does not nag every morning.
t_section('a contract warns before it auto-closes for inactivity');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    $today = date('Y-m-d');
    $mk = function ($no, $idleDays) {
        $start = date('Y-m-d', strtotime("-$idleDays days"));
        db()->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, open_status, is_active, start_date)
                       VALUES (0,?,?, 'OPEN', 1, ?)")
           ->execute([$no, 'Idle test', $start]);
        return ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)db()->lastInsertId()]);
    };

    // 50 idle days → default close at +60 → 10 days out → inside the 14-day window.
    $due = $mk('IDLE/DUE/1', 50);
    $st  = contract_idle_status($due);
    t_ok(!empty($st['due']) && (int)$st['days_left'] === 10, 'a contract 10 days from idle-close is flagged as due');
    t_ok($st['close_on'] === date('Y-m-d', strtotime('+10 days')), 'the projected close date is last-activity + idle-days');

    // Recently active → not due.
    $fresh = $mk('IDLE/FRESH/1', 5);
    t_ok(empty(contract_idle_status($fresh)['due']), 'a recently-active contract is not flagged');

    // Already past the close point → that is the auto-closer\'s job, not the warning.
    $over = $mk('IDLE/OVER/1', 80);
    $sover = contract_idle_status($over);
    t_ok(!empty($sover['due']) && (int)$sover['days_left'] < 0, 'a contract past its idle-close shows negative days left');

    // A CLOSED / inactive contract is never flagged.
    db()->prepare("UPDATE partner_contracts SET open_status='CLOSED', is_active=0 WHERE id=?")->execute([(int)$due['id']]);
    t_ok(empty(contract_idle_status(ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$due['id']]))['due']),
        'a closed contract is not flagged as going idle');
    db()->prepare("UPDATE partner_contracts SET open_status='OPEN', is_active=1 WHERE id=?")->execute([(int)$due['id']]);

    // The sweep warns the due one, stamps idle_notified to the activity date it is
    // based on, and does NOT re-warn the same window on a second run.
    contracts_idle_warn($today);
    $stamp = ops_val("SELECT idle_notified FROM partner_contracts WHERE id=?", [(int)$due['id']]);
    t_ok((string)$stamp === (string)$st['last'], 'the warned contract is stamped with its last-activity date');
    contracts_idle_warn($today);
    $stamp2 = ops_val("SELECT idle_notified FROM partner_contracts WHERE id=?", [(int)$due['id']]);
    t_ok((string)$stamp2 === (string)$st['last'], 'a second sweep does not re-warn the same window (fires once)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// Wiring: the cron runs the warning sweep, and Contract 360 shows the banner.
$cron = file_get_contents(__DIR__ . '/../cron.php');
t_ok(strpos($cron, 'contracts_idle_warn()') !== false, 'the daily cron runs the idle-warning sweep');
$view = file_get_contents(__DIR__ . '/../views/ops/contract_detail.php');
t_ok(strpos($view, 'contract_idle_status($c)') !== false && strpos($view, 'Going idle') !== false,
    'Contract 360 shows the going-idle banner');
