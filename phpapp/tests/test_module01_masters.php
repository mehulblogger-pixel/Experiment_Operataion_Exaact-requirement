<?php
// Module 01 — Masters (the editable-lookup engine). Add a "where-used" usage counter so an admin
// can see whether a dropdown value is safe to remove, plus dangling-value and duplicate-code
// detectors in the integrity framework. Read-only; no access change; delete is warned, not blocked.
t_section('Module 01 — lookup usage counter + integrity detectors');

$lib  = file_get_contents(__DIR__ . '/../lib/lookups.php');
$view = file_get_contents(__DIR__ . '/../views/ops/lookup_values.php');

t_ok(function_exists('lk_value_usage'), 'lk_value_usage() exists');
t_ok(function_exists('lk_dangling_total'), 'lk_dangling_total() exists');
t_ok(function_exists('lk_usage_map'), 'lk_usage_map() exists');

// The usage map is curated to 1:1 code columns.
$map = lk_usage_map();
t_ok(isset($map['quote_status']['table']) && $map['quote_status']['table'] === 'quotations', 'quote_status maps to quotations.status');
t_ok(isset($map['lead_source']['col']) && $map['lead_source']['col'] === 'source', 'lead_source maps to leads.source');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('crm_ensure_schema')) crm_ensure_schema();
    $pdo = db();

    // Two quotes at status SENT, one at DRAFT.
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status) VALUES ('QU-1',0,1,'SENT')")->execute();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status) VALUES ('QU-2',0,1,'SENT')")->execute();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status) VALUES ('QU-3',0,1,'DRAFT')")->execute();

    t_eq(lk_value_usage('quote_status', 'SENT'), 2, 'usage counts the records at that value');
    t_eq(lk_value_usage('quote_status', 'DRAFT'), 1, 'usage counts a different value independently');
    t_ok(lk_value_usage('not_a_tracked_list', 'X') === null, 'an untracked list returns null (unknown), never a misleading 0');

    // A record storing a code no dropdown recognises → dangling.
    $before = lk_dangling_total();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status) VALUES ('QU-X',0,1,'BOGUS_STATUS')")->execute();
    $after = lk_dangling_total();
    t_ok($after === $before + 1, 'a stored code that exists in no option is counted as dangling');

    // Duplicate-code detector (via the integrity framework).
    if (function_exists('lk_ensure_schema')) lk_ensure_schema();
    $pdo->prepare("INSERT INTO lookup_types (type_key, label) VALUES ('mod01_test','Test')")->execute();
    $tid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO lookup_values (type_id, code, label, active) VALUES (?, 'DUP', 'One', 1)")->execute([$tid]);
    $pdo->prepare("INSERT INTO lookup_values (type_id, code, label, active) VALUES (?, 'DUP', 'Two', 1)")->execute([$tid]);
    $checks = integrity_checks();
    $byKey = []; foreach ($checks as $c) $byKey[$c['key']] = $c;
    t_ok(isset($byKey['lk_dupe_code']), 'a duplicate-code integrity check was added');
    t_ok($byKey['lk_dupe_code']['ok'] === false && (int)$byKey['lk_dupe_code']['found'] >= 1, 'the duplicate code is detected');
    t_ok(isset($byKey['lk_dangling']), 'a dangling-value integrity check was added');
    t_ok((int)$byKey['lk_dangling']['found'] >= 1, 'the dangling code is detected by the integrity check');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
t_ok(strpos($view, 'Used by') !== false, 'the value editor shows a used-by-N column');
t_ok(strpos($view, 'Remove anyway') !== false, 'delete warns when the value is still in use (advisory — not blocked)');
t_ok(strpos($lib, 'function lk_options_or') !== false, 'the values-if-present-else-const engine is unchanged');
