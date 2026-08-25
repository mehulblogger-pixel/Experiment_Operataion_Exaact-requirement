<?php
// Module 08 — Report Release / Issue. A read-only "Ready to issue" panel that
// previews the SAME gates the finalize handler enforces (approval complete,
// issuer != approver, QA critical, instrument/signer pack), so an issuer sees
// what is blocking before pressing Finalize. No control is changed.
t_section('Module 08 — issue-readiness preview (additive, controls preserved)');

$idm  = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/doc_detail.php');

t_ok(function_exists('idems_issue_readiness'), 'idems_issue_readiness() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $mk = function ($status) {
        db()->prepare("INSERT INTO report_docs (type_code, status, inspector_id, deleted, created_at) VALUES ('IC',?,990004,0,?)")->execute([$status, date('c')]);
        return ops_one("SELECT * FROM report_docs WHERE id=?", [(int)db()->lastInsertId()]);
    };
    $labels = fn($r) => array_column($r['items'], 'label');
    $stateOf = function ($r, $label) { foreach ($r['items'] as $i) if ($i['label'] === $label) return $i['state']; return null; };

    // No approval chain → Approval is a WARN (submit first), overall not blocked by it alone.
    $noChain = idems_issue_readiness($mk('APPROVED'));
    t_ok($stateOf($noChain, 'Approval') === 'warn', 'with no approval chain, Approval reads as a warning (submit for approval first)');

    // A report still UNDER_REVIEW with a chain → Approval BLOCKS, so not ready.
    $ur = $mk('UNDER_REVIEW');
    db()->prepare("INSERT INTO report_approvals (report_doc_id, level, status, created_at) VALUES (?,1,'PENDING',?)")->execute([(int)$ur['id'], date('c')]);
    $urR = idems_issue_readiness(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$ur['id']]));
    t_ok($stateOf($urR, 'Approval') === 'block' && $urR['ready'] === false, 'an unapproved report with a chain is not ready to issue');

    // Fully APPROVED with an approved chain → Approval is OK.
    $ap = $mk('APPROVED');
    db()->prepare("INSERT INTO report_approvals (report_doc_id, level, status, acted_by, acted_at, created_at) VALUES (?,1,'APPROVED','Sunil',?,?)")->execute([(int)$ap['id'], date('c'), date('c')]);
    $apR = idems_issue_readiness(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$ap['id']]));
    t_ok($stateOf($apR, 'Approval') === 'ok', 'a fully-approved report shows Approval as done');

    // The panel always probes the QA and instrument/authorisation gates (fail-open — never fatal).
    t_ok(in_array('Quality check', $labels($apR), true) && in_array('Instruments & authorisation', $labels($apR), true),
        'the readiness preview always covers the quality and instrument/authorisation gates');

    // Overall ready flag is false when any item is a block, true when none are.
    $anyBlock = (bool)array_filter($apR['items'], fn($i) => $i['state'] === 'block');
    t_ok($apR['ready'] === !$anyBlock, 'the overall ready flag reflects whether any gate blocks');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The panel is read-only and shown only at the issue decision point.
t_ok(strpos($view, "Module 08:") !== false && strpos($view, "idems_issue_readiness(\$doc)") !== false,
    'the report screen renders the issue-readiness panel');
t_ok(strpos($view, "\$rSt === 'APPROVED' && (is_master() || can('idems.finalize'))") !== false,
    'the panel shows only for an approved, not-yet-finalized report to a finalizer');
t_ok(strpos($view, 'new revision') !== false, 'the immutability/revision clarity copy is present');

// Controls preserved: the finalize handler still carries every guard (unchanged).
t_ok(strpos($idm, "You approved this ' . Tl('report') . ', so it must be finalised") !== false, 'issuer != approver guard intact');
t_ok(strpos($idm, "must be fully approved through its approval chain") !== false, 'approval-complete gate intact');
t_ok(strpos($idm, "QA_CRITICAL_OVERRIDE") !== false, 'QA critical gate + audited override intact');

// No new permission.
t_ok(!preg_match('/can\(\x27idems\.(issue|release|readiness)\x27/', $idm), 'Module 08 introduces no new permission constant');
