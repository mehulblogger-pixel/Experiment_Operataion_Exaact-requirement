<?php
// Module 28 — Internal audits (§8.8) + management review (§8.9). Overdue reminders (the only
// accreditation registers that had none), a management-review-decision → CAPA link (closing the
// asymmetry with the finding → CAPA loop), per-clause days-since/next-due, and the first coverage
// of the safety gates (independence refusal, close-block).
t_section('Module 28 — audit/review reminders + MR→CAPA + gate coverage');

$lib = file_get_contents(__DIR__ . '/../lib/audits.php');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');

t_ok(function_exists('audits_run_reminders'), 'audits_run_reminders() exists');
t_ok(function_exists('reviews_run_reminders'), 'reviews_run_reminders() exists');
t_ok(strpos($ops, "'review-action-capa'=>'audits'") !== false, 'the MR-decision→CAPA route is registered');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedEnv = getenv('QAC_EMAIL');
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('audits_migrate')) audits_migrate();
    $pdo = db();

    // ---- coverage enrichment (additive keys) ----
    $pdo->prepare("INSERT INTO internal_audits (ref, planned_on, carried_out_on, clauses, status) VALUES ('IA-1', '2026-08-01', '2026-08-01', ?, 'CLOSED')")
        ->execute([implode(',', array_slice(array_keys(audit_clause_options()), 0, 1))]);
    $firstClause = array_key_first(audit_clause_options());
    $cov = audit_coverage();
    t_ok(array_key_exists('days_since', $cov[$firstClause]) && array_key_exists('next_due', $cov[$firstClause]),
         'audit_coverage() now carries days-since and next-due per clause');
    t_ok($cov[$firstClause]['covered'] === true && $cov[$firstClause]['next_due'] !== '',
         'a recently-audited clause has a computed next-due date');

    // ---- reminders fire when overdue, with a recipient ----
    putenv('QAC_EMAIL=quality@test.local');
    t_ok(audits_run_reminders() === 1, 'the audit reminder fires when clauses are uncovered');
    t_ok(reviews_run_reminders() === 1, 'the review reminder fires when no management review has been held');

    // ---- the independence gate (§8.8.2) — first coverage ----
    if (function_exists('audit_auditor_block')) {
        $blk = audit_auditor_block('Jane', 'Jane');   // auditor == area owner
        t_ok(trim((string)$blk) !== '', 'an auditor auditing their own area is refused (§8.8.2 independence)');
        $ok = audit_auditor_block('Jane', 'Bob');
        t_ok(trim((string)$ok) === '', 'an independent auditor is allowed');
    }

    // ---- the close-block: an NC finding with no CAPA stops closure — first coverage ----
    $pdo->prepare("INSERT INTO internal_audits (ref, planned_on, carried_out_on, clauses, status, summary) VALUES ('IA-2','2026-08-10','2026-08-10','', 'REPORTED', 'found issues')")->execute();
    $aid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO audit_findings (audit_id, clause, kind, detail, capa_ref) VALUES (?, '8.7', 'NC_MAJOR', 'A major nonconformity', '')")->execute([$aid]);
    $a2 = ops_one("SELECT * FROM internal_audits WHERE id=?", [$aid]);
    t_ok(trim((string)audit_close_block($a2)) !== '', 'an audit with an unactioned nonconformity cannot be closed');

    // ---- MR decision → CAPA link column exists and is writable ----
    $pdo->prepare("INSERT INTO mgmt_reviews (held_on, status) VALUES ('2026-08-15', 'DRAFT')")->execute();
    $rid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO mr_actions (review_id, kind, decision, status, created_at) VALUES (?, 'IMPROVEMENT', 'Buy more gauges', 'OPEN', ?)")->execute([$rid, date('c')]);
    $mid = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE mr_actions SET capa_ref='CAPA-9', capa_id=42 WHERE id=?")->execute([$mid]);
    $act = ops_one("SELECT * FROM mr_actions WHERE id=?", [$mid]);
    t_ok(($act['capa_ref'] ?? '') === 'CAPA-9' && (int)($act['capa_id'] ?? 0) === 42,
         'a management-review decision can carry a linked corrective-action ref+id');
} finally {
    if ($savedEnv === false) putenv('QAC_EMAIL'); else putenv('QAC_EMAIL=' . $savedEnv);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring ----
$cron = file_get_contents(__DIR__ . '/../cron.php');
t_ok(strpos($cron, 'audits_run_reminders') !== false && strpos($cron, 'reviews_run_reminders') !== false,
     'the audit/review reminders are wired into cron');
t_ok(strpos($cron, "audit_reminder_week") !== false, 'the reminder is guarded (at most weekly), not a daily nag');
t_ok(strpos($lib, "'review-action-capa'") !== false, 'the MR-decision→CAPA handler exists');
t_ok(strpos($lib, "'source' => 'MGMT_REVIEW'") !== false, 'the MR→CAPA raises with a management-review source');
