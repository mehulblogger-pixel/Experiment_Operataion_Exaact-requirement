<?php
// Module 17 — Leads. The top of the funnel gets a cold-lead / overdue-follow-up detector, finally
// reading the next_action_on field that was stored but never queried. Plus the first coverage of
// the lead lifecycle guards (WON forces convert, LOST needs a reason).
t_section('Module 17 — cold-lead detection + lifecycle guards');

$adv = file_get_contents(__DIR__ . '/../lib/advisor.php');

t_ok(function_exists('leads_due'), 'leads_due() exists');
t_ok(function_exists('adv_cold_leads'), 'adv_cold_leads() exists');
t_ok(strpos($adv, "'adv_cold_leads'") !== false, 'the cold-lead check is registered in the advisor');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('leads_migrate')) leads_migrate();
    $pdo = db();

    // A pipeline + a stage with a 3-day SLA.
    $pdo->prepare("INSERT INTO pipelines (name, is_default) VALUES ('Test PL', 1)")->execute();
    $pl = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id, name, kind, seq, probability, sla_days) VALUES (?, 'Contacted', 'OPEN', 1, 20, 3)")->execute([$pl]);
    $stg = (int)$pdo->lastInsertId();

    $mkLead = function ($ref, $stageSince, $nextOn, $status = 'OPEN') use ($pdo, $pl, $stg) {
        $pdo->prepare("INSERT INTO leads (ref, pipeline_id, stage_id, company_name, status, stage_since, next_action_on, next_action, value, created_at)
                       VALUES (?,?,?,?,?,?,?, 'call them', 1000, ?)")
            ->execute([$ref, $pl, $stg, 'Co ' . $ref, $status, $stageSince, $nextOn, $stageSince]);
        return (int)$pdo->lastInsertId();
    };

    // (a) stalled: in stage 10 days, SLA is 3.
    $stale = $mkLead('L-STALE', date('Y-m-d', strtotime('-10 days')), '');
    // (b) overdue follow-up: fresh in stage, but next_action_on was yesterday.
    $overdue = $mkLead('L-DUE', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));
    // (c) healthy: fresh, follow-up in the future.
    $ok = $mkLead('L-OK', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+5 days')));
    // (d) converted lead past SLA — must NOT be chased (not open).
    $conv = $mkLead('L-CONV', date('Y-m-d', strtotime('-30 days')), '', 'CONVERTED');

    $due = leads_due();
    $refs = array_column($due, 'ref');
    t_ok(in_array('L-STALE', $refs, true), 'a lead past its stage service level is due');
    t_ok(in_array('L-DUE', $refs, true), 'a lead with an overdue follow-up date is due (next_action_on is finally read)');
    t_ok(!in_array('L-OK', $refs, true), 'a fresh lead with a future follow-up is not chased');
    t_ok(!in_array('L-CONV', $refs, true), 'a converted lead is never chased');

    // The reasons are human-readable.
    $stalRow = null; foreach ($due as $d) if ($d['ref'] === 'L-STALE') $stalRow = $d;
    t_ok($stalRow && !empty($stalRow['due_reasons']) && strpos($stalRow['due_reasons'][0], 'service level') !== false,
         'the due reason names the stage service level');

    // ---- lifecycle guards (first coverage) ----
    if (function_exists('lead_move')) {
        // A WON-kind stage must force the conversion flow, not a plain tick.
        $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id, name, kind, seq, probability, sla_days) VALUES (?, 'Won', 'WON', 9, 100, 0)")->execute([$pl]);
        $wonStage = (int)$pdo->lastInsertId();
        $res = lead_move($ok, $wonStage, []);
        t_ok(!empty($res['convert']),
             'moving a lead into a WON stage returns the convert sentinel, not a silent win');
        // A LOST-kind stage needs a reason.
        $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id, name, kind, seq, probability, sla_days) VALUES (?, 'Lost', 'LOST', 10, 0, 0)")->execute([$pl]);
        $lostStage = (int)$pdo->lastInsertId();
        $res2 = lead_move($ok, $lostStage, []);   // no lost_reason
        t_ok(trim((string)($res2['err'] ?? '')) !== '',
             'moving a lead to a LOST stage without a reason is refused');
        $res3 = lead_move($ok, $lostStage, ['lost_reason' => 'NO_RESPONSE']);
        t_ok(!empty($res3['ok']), 'a LOST move with a reason is accepted');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$view = file_get_contents(__DIR__ . '/../views/ops/leads.php');
t_ok(strpos($view, 'Need attention now') !== false, 'the leads register shows a needs-attention tile');
t_ok(strpos($adv, "'link' => '/leads'") !== false, 'the advisor card links to the leads register');
t_ok(strpos($adv, 'a lead is never closed for you') !== false, 'the cold-lead advice is advisory — nothing is auto-closed');
