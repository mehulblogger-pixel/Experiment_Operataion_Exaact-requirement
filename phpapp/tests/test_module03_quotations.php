<?php
// Module 03 — Quotations. Make validity real (the EXPIRED status the lifecycle defined but
// never reached), never blocking accept/revise, and close the approval-chain bypass so a
// matching multi-level approval can't be skipped by a one-click status set. All additive.
t_section('Module 03 — quotation validity + approval guard');

$lib  = file_get_contents(__DIR__ . '/../lib/crm.php');
$cron = file_get_contents(__DIR__ . '/../cron.php');
$list = file_get_contents(__DIR__ . '/../views/ops/crm/quote_list.php');

t_ok(function_exists('quote_validity'), 'quote_validity() exists');
t_ok(function_exists('crm_expire_quotes'), 'crm_expire_quotes() exists');
t_ok(function_exists('crm_quote_needs_chain'), 'crm_quote_needs_chain() exists');

// ---- quote_validity: pure, no DB ----
$within = date('c', strtotime('-5 days'));
$lapsed = date('c', strtotime('-40 days'));
t_ok(quote_validity(['status'=>'SENT','validity_days'=>30,'sent_at'=>$within])['expired'] === false,
     'a SENT quote within its 30-day validity is not expired');
t_ok(quote_validity(['status'=>'SENT','validity_days'=>30,'sent_at'=>$lapsed])['expired'] === true,
     'a SENT quote past its 30-day validity is expired');
t_ok(quote_validity(['status'=>'DRAFT','validity_days'=>30,'sent_at'=>$lapsed])['expired'] === false,
     'a DRAFT quote is never expired (nothing was sent to a client)');
t_ok(quote_validity(['status'=>'ACCEPTED','validity_days'=>30,'sent_at'=>$lapsed])['expired'] === false,
     'a closed (accepted) quote is not treated as expired');
t_ok(quote_validity(['status'=>'SENT','validity_days'=>0,'sent_at'=>$lapsed])['expired'] === false,
     'validity_days=0 is open-ended and never expires');
t_ok(quote_validity(['status'=>'SENT','validity_days'=>30,'sent_at'=>''])['expired'] === false,
     'a missing sent_at does not crash and is not expired');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    crm_ensure_schema(); if (function_exists('crm_migrate')) crm_migrate();
    $pdo = db();
    $mkQuote = function ($no, $status, $validity, $sentAt, $extra = []) use ($pdo) {
        $cols = array_merge(['quote_no'=>$no, 'rev'=>0, 'is_current'=>1, 'status'=>$status,
            'validity_days'=>$validity, 'sent_at'=>$sentAt, 'total_amount'=>5000, 'sbu'=>'', 'created_at'=>date('c')], $extra);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO quotations (" . implode(',', array_keys($cols)) . ") VALUES ($ph)")->execute(array_values($cols));
        return (int)$pdo->lastInsertId();
    };

    // A lapsed SENT quote, a fresh SENT quote, an accepted one, and a contract-linked one.
    $qLapsed = $mkQuote('Q-EXP', 'SENT', 30, $lapsed);
    $qFresh  = $mkQuote('Q-FRESH', 'SENT', 30, $within);
    $qDone   = $mkQuote('Q-WON', 'ACCEPTED', 30, $lapsed);
    $qCon    = $mkQuote('Q-CON', 'SENT', 30, $lapsed, ['contract_id'=>4321]);

    $n = crm_expire_quotes();
    $st = fn($id) => (string)ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
    t_ok($n >= 1, 'the sweep expires at least the lapsed quote');
    t_eq($st($qLapsed), 'EXPIRED', 'a lapsed SENT quote is stamped EXPIRED');
    t_eq($st($qFresh), 'SENT', 'a quote within validity is left SENT');
    t_eq($st($qDone), 'ACCEPTED', 'an accepted quote is never touched by the sweep');
    t_eq($st($qCon), 'SENT', 'a contract-linked quote is skipped (it is not "expired")');

    // Idempotent: a second run stamps nothing new.
    $n2 = crm_expire_quotes();
    t_ok($n2 === 0, 'a second sweep the same day is a no-op (idempotent)');

    // ---- approval-chain guard ----
    // No rules yet → a quote needs no chain (direct approval stays allowed).
    $qA = $mkQuote('Q-APR', 'DRAFT', 30, '', ['total_amount'=>5000, 'sbu'=>'NDT']);
    $qArow = ops_one("SELECT * FROM quotations WHERE id=?", [$qA]);
    t_ok(crm_quote_needs_chain($qArow) === false, 'with no approval rules, no chain is required');

    // A rule matching the amount → a chain IS required, and is not yet satisfied.
    $pdo->prepare("INSERT INTO quote_approval_rules (name, match_type, sbu, min_amount, max_amount, level, active, created_at) VALUES ('Big', 'ANY', '', 1000, 0, 1, 1, ?)")->execute([date('c')]);
    t_ok(crm_quote_needs_chain($qArow) === true, 'a quote over a rule threshold requires the approval chain');
    t_ok(crm_quote_chain_satisfied($qA) === false, 'a quote with no acted approvals has an unsatisfied chain');

    // A quote below the threshold does not need the chain.
    $qSmall = $mkQuote('Q-SMALL', 'DRAFT', 30, '', ['total_amount'=>500]);
    t_ok(crm_quote_needs_chain(ops_one("SELECT * FROM quotations WHERE id=?", [$qSmall])) === false,
         'a quote below every rule threshold does not require the chain');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation (string-level) ----
t_ok(strpos($cron, 'crm_expire_quotes()') !== false, 'the expiry sweep is wired into cron');
t_ok(strpos($lib, "'lost' => ['LOST'], 'expired' => ['EXPIRED']") !== false,
     'EXPIRED is split out of the "lost" analytics into its own state');
t_ok(strpos($list, "\$qs('expired')") !== false, 'the register offers an Expired view');
t_ok(strpos($lib, 'bypassing the required approval chain (master override)') !== false,
     'a master direct-approval over a required chain is logged as a bypass, not silent');
t_ok(strpos($lib, 'Accepted after validity had lapsed on') !== false,
     'accepting after expiry is recorded in the change history');
t_ok(strpos($lib, 'function quote_is_locked') !== false, 'the sent-quote immutability lock is still present');
t_ok(!preg_match('/const QUOTE_STATUS.*NEWSTATUS/s', $lib), 'no new quotation status constant was invented (EXPIRED already existed)');
