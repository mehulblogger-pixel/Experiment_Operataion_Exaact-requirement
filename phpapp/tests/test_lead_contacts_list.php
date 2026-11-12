<?php
// Owner, 12 Nov 2026 — "Log contact shall log every contact made in the lead
// history with its details as a list. At present no such information is
// available." The contacts were saved (History tab) but the Contacts tab held
// only the form, and the time taken and the next step agreed were never shown.
t_section('Lead — every contact logged, as a list, with its details');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    leads_migrate();
    $pdo->prepare("INSERT INTO leads (ref, company_name, contact_name, requirement, value, status, created_at, updated_at) VALUES (?, 'LCL Pumps', 'Ravi Shah', 'UT', 1000, 'OPEN', ?, ?)")
        ->execute(['LCL-' . getmypid(), date('c'), date('c')]);
    $lid = (int)$pdo->lastInsertId();
    act_log('LEAD', $lid, 'SYSTEM', 'Lead created', ['auto' => 1]);
    $yday = date('Y-m-d', strtotime('-1 day')); $tmr = date('Y-m-d', strtotime('+1 day'));
    t_ok(!empty(lead_log_contact($lid, ['method' => 'CALL', 'direction' => 'OUT', 'occurred_on' => $yday, 'with_whom' => 'Ravi Shah', 'note' => 'Asked for rates', 'outcome' => 'Interested', 'time_spent' => 15])['ok']), 'a call is logged');
    t_ok(!empty(lead_log_contact($lid, ['method' => 'EMAIL', 'direction' => 'IN', 'with_whom' => 'Ravi Shah', 'note' => 'Sent the drawings', 'time_spent' => 90,
        'next_action' => 'Send the quotation', 'next_action_on' => $tmr])['ok']), 'an e-mail with a next step is logged');
    $c = lead_contacts($lid);
    t_eq(count($c), 2, 'the Contacts list holds both contacts — and not the system\'s own "Lead created" entry');
    t_eq([$c[0]['kind'], $c[1]['kind']], ['EMAIL', 'CALL'], 'newest first');
    t_eq([$c[0]['direction'], $c[0]['with_whom'], $c[0]['body'], (int)$c[0]['duration_mins']], ['IN', 'Ravi Shah', 'Sent the drawings', 90], 'with how, which way, who, what was said and the time it took');
    t_eq([$c[0]['next_step'], $c[0]['next_step_on']], ['Send the quotation', $tmr], 'and the next step agreed on THAT contact');
    t_eq([$c[1]['next_step'], $c[1]['outcome']], ['', 'Interested'], 'the earlier contact keeps its own details (no next step was set on it)');
    t_eq((string)lead_row($lid)['next_action'], 'Send the quotation', 'the lead\'s follow-up still carries the latest next step');
    t_eq([lead_minutes_words(15), lead_minutes_words(90), lead_minutes_words(240)], ['15 minutes', '1 hour 30 minutes', 'Half a day'], 'time is said in words');
    $view = (string)file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
    t_ok(strpos($view, 'class="ct-list"') !== false && strpos($view, '＋ Log a contact') !== false, 'the Contacts tab lists the contacts, with "＋ Log a contact" above them');
    t_ok(strpos((string)file_get_contents(__DIR__ . '/../lib/leads.php'), "'#lead=contacts'") !== false, 'after saving, the person lands on that list');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
