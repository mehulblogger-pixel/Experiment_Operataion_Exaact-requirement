<?php
// Module 19 — Inquiries. The un-instrumented rung of the sales funnel gets a stale-inquiry
// detector + advisor worklist, mirroring Module 03 (quote expiry) and Module 17 (cold leads).
// Read-only; never changes a status; surfaces the previously-dead assigned_to owner.
t_section('Module 19 — un-quoted / stale inquiry detection');

$adv = file_get_contents(__DIR__ . '/../lib/advisor.php');

t_ok(function_exists('inquiries_due'), 'inquiries_due() exists');
t_ok(function_exists('adv_inquiries_unquoted'), 'adv_inquiries_unquoted() exists');
t_ok(strpos($adv, "'adv_inquiries_unquoted'") !== false, 'the un-quoted-inquiry check is registered in the advisor');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('crm_ensure_schema')) crm_ensure_schema();
    $pdo = db();
    $sla = inquiry_sla_days();

    $mk = function ($no, $status, $received) use ($pdo) {
        $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, subject, sbu, status, received_date, created_at)
                       VALUES (?, 'Acme', 'Need UT', '', ?, ?, ?)")
            ->execute([$no, $status, $received, $received . 'T09:00:00']);
        return (int)$pdo->lastInsertId();
    };
    $old = date('Y-m-d', strtotime('-' . ($sla + 10) . ' days'));
    $fresh = date('Y-m-d', strtotime('-1 day'));

    $stale   = $mk('INQ-OLD',  'OPEN',   $old);
    $fres1   = $mk('INQ-NEW',  'OPEN',   $fresh);
    $quoted  = $mk('INQ-QUOT', 'QUOTED', $old);      // old but already quoted → not chased
    $dropped = $mk('INQ-DROP', 'DROPPED',$old);      // old but dropped → not chased

    $due = inquiries_due();
    $nos = array_column($due, 'inquiry_no');
    t_ok(in_array('INQ-OLD', $nos, true), 'an OPEN inquiry older than the service level is due');
    t_ok(!in_array('INQ-NEW', $nos, true), 'a fresh OPEN inquiry is not chased');
    t_ok(!in_array('INQ-QUOT', $nos, true), 'a QUOTED inquiry is never chased');
    t_ok(!in_array('INQ-DROP', $nos, true), 'a DROPPED inquiry is never chased');

    $row = null; foreach ($due as $d) if ($d['inquiry_no'] === 'INQ-OLD') $row = $d;
    t_ok($row && (int)$row['age_days'] >= $sla + 1, 'the due row carries the age in days');

    // The detector never changes a status (read-only).
    t_eq((string)ops_val("SELECT status FROM crm_inquiries WHERE id=?", [$stale]), 'OPEN', 'the detector leaves the status untouched');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$view = file_get_contents(__DIR__ . '/../views/ops/crm/inquiry_list.php');
t_ok(strpos($view, 'waiting for a quotation') !== false, 'the inquiries register shows a waiting-for-quote banner');
t_ok(strpos($view, 'Owner') !== false, 'the register surfaces the previously-dead assigned_to owner');
t_ok(strpos($adv, "'link' => '/inquiries'") !== false, 'the advisor card links to the inquiries register');
t_ok(strpos($adv, 'an inquiry is never dropped for you') !== false, 'the advice is advisory — no auto-drop');
t_ok(function_exists('inquiry_sla_days'), 'the response service level is a configurable setting');
