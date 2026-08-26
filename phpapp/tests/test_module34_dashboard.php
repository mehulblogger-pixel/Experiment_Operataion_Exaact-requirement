<?php
// Module 34 — Dashboards / Command Centre. Many modules already COMPUTE a due/overdue/expiring
// count (leads_due, inquiries_due, contract expiry, expired quotes, AR overdue, cert lapsed), but
// each lived only on its own register screen — so a desk user's home showed open totals, never what
// was DUE. attention_summary() fans those existing canonical counts into one permission-gated band.
// Read-only; reuses helpers (no fresh SQL for a count that already has one).
t_section('Module 34 — home "needs attention" aggregation');

t_ok(function_exists('attention_summary'),        'attention_summary() exists');
t_ok(function_exists('contracts_expiring_count'), 'contracts_expiring_count() (canonical count) exists');
t_ok(function_exists('quotes_expired_count'),     'quotes_expired_count() (canonical count) exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();

    // contracts_expiring_count: an OPEN contract inside the warning window is counted; a far-future
    // one and a closed one are not.
    $warn = function_exists('contract_warn_days') ? contract_warn_days() : 30;
    $mkC = function ($end, $open = 'OPEN') use ($pdo) {
        $pdo->prepare("INSERT INTO partner_contracts (contract_number, open_status, end_date) VALUES (?,?,?)")
            ->execute(['C-'.substr(md5($end.$open.mt_rand()),0,6), $open, $end]);
    };
    $base = contracts_expiring_count();
    $mkC(date('Y-m-d', strtotime('+'.max(1,$warn-1).' days')));      // expiring — counted
    $mkC(date('Y-m-d', strtotime('+400 days')));                     // far future — not
    $mkC(date('Y-m-d', strtotime('+'.max(1,$warn-1).' days')), 'CLOSED'); // closed — not
    t_eq(contracts_expiring_count(), $base + 1, 'only an OPEN contract inside the warning window is counted as expiring');

    // quotes_expired_count: a current EXPIRED quote counts; a superseded one does not.
    $b2 = quotes_expired_count();
    $pdo->prepare("INSERT INTO quotations (quote_no, status, is_current) VALUES ('QX-1','EXPIRED',1)")->execute();
    $pdo->prepare("INSERT INTO quotations (quote_no, status, is_current) VALUES ('QX-2','EXPIRED',0)")->execute();
    t_eq(quotes_expired_count(), $b2 + 1, 'only a current EXPIRED quotation is counted (superseded revisions excluded)');

    // attention_summary shape: a list of gated tiles, each with the fields the band renders.
    $a = attention_summary();
    t_ok(is_array($a), 'attention_summary() returns a list');
    foreach ($a as $item) {
        foreach (['key','label','n','url','sev','value'] as $k) t_ok(array_key_exists($k, $item), "each attention tile has '$k'");
        t_ok($item['url'] !== '' , 'every attention tile links somewhere (no dead-end metric)');
        t_ok(((int)$item['n'] > 0) || ($item['value'] !== null), 'a tile only shows when it has something outstanding');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$ops  = file_get_contents(__DIR__ . '/../lib/ops.php');
$view = file_get_contents(__DIR__ . '/../views/dashboard.php');
t_ok(strpos($view, 'attention_summary()') !== false, 'the home dashboard builds the needs-attention band');
t_ok(strpos($view, 'echo $secAttention;') !== false, 'the band is rendered in the role-ordered output');
t_ok(strpos($view, 'Needs attention') !== false, 'the band has a heading');
// The band reuses canonical helpers rather than duplicating a count with new SQL.
t_ok(strpos($ops, 'leads_due_count()') !== false && strpos($ops, 'inquiries_due_count()') !== false,
    'attention_summary reuses the existing leads/inquiries due-counts');
t_ok(strpos($ops, 'competence_training_watch_counts()') !== false, 'it reuses the training watch counts');
// The existing personal-tasks aggregator is untouched and separate.
t_ok(strpos($ops, 'function ops_pending_tasks') !== false, 'the existing personal pending-tasks aggregator is preserved');
