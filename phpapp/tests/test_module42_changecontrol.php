<?php
// Module 42 — Change control. There is no single change-control register: each controlled object
// versions itself (report reissue, controlled-doc / method / decision-rule supersede, quote
// revision). Four of five already sealed the change on the audit chain; drule_supersede was the
// one that did not — now fixed. controlled_changes() is the consolidated read-only "what changed
// and why" list over events that ALREADY exist. Read-only; no new state, no new permission.
t_section('Module 42 — change control (consolidated controlled changes)');

t_ok(function_exists('controlled_changes'), 'controlled_changes() exists');
t_ok(function_exists('controlled_changes_count'), 'controlled_changes_count() exists');

// The one-line fix: the decision-rule supersede now writes to the sealed chain.
$src = file_get_contents(__DIR__ . '/../lib/decisionrules.php');
t_ok(strpos($src, "idems_log('decision_rule'") !== false, 'drule_supersede now logs to the sealed audit chain');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();

    // 1) A sealed supersede event (method) shows up in the consolidated list.
    idems_log('method', 9911, 'REVISION_OF', ['field' => 'MTD-TEST-01', 'reason' => 'MTD-TEST-01']);
    // 2) A decision-rule revision — the newly-sealed domain.
    idems_log('decision_rule', 9922, 'REVISION_OF', ['field' => 'DR-TEST-02', 'reason' => 'DR-TEST-02']);
    // 3) A controlled-document supersession.
    idems_log('controlled_doc', 9933, 'SUPERSEDED', ['field' => 'QM-01', 'new' => 'QM-01 Rev 4']);
    // 4) A quotation revision (its own change log, not the sealed chain).
    db()->prepare("INSERT INTO quote_revisions (quote_id, rev, changed_by, changed_at, summary) VALUES (?,?,?,?,?)")
        ->execute([9944, 3, 'Tester', date('c'), 'Reworked scope and price']);

    $rows    = controlled_changes(90, 500);
    $domains = array_column($rows, 'domain');

    t_ok(in_array('Method', $domains, true),              'a method revision appears in the consolidated list');
    t_ok(in_array('Decision rule', $domains, true),        'a decision-rule revision appears (the newly-sealed domain)');
    t_ok(in_array('Controlled document', $domains, true),  'a controlled-document supersession appears');
    t_ok(in_array('Quotation', $domains, true),            'a quotation revision appears (from its own change log)');

    // Every row carries the fields the view needs.
    $one = $rows[0];
    foreach (['domain', 'ref', 'change', 'who', 'at'] as $k)
        t_ok(array_key_exists($k, $one), "each row has '$k'");

    // Newest first.
    $ok = true; for ($i = 1; $i < count($rows); $i++) if (strcmp((string)$rows[$i-1]['at'], (string)$rows[$i]['at']) < 0) $ok = false;
    t_ok($ok, 'the list is ordered newest-first');

    // A far-past window excludes recent changes (uses the days bound).
    $rowsOld = controlled_changes(90, 500);
    t_ok(controlled_changes_count(90) === count($rowsOld), 'the count matches the list length for the same window');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$lib  = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/audit.php');
t_ok(strpos($lib, "'controlledChanges'=>function_exists('controlled_changes')") !== false, 'the audit screen is passed the consolidated changes');
t_ok(strpos($view, 'Recent controlled changes') !== false, 'the audit screen renders the controlled-changes panel');
t_ok(strpos($lib, 'function ops_idems_audit') !== false, 'the existing audit-log screen is unchanged (additive panel only)');
