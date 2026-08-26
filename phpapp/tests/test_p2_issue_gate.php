<?php
// Phase 2 §10 — the issuance readiness gate omitted vetting / report-completeness / competence /
// impartiality / blocking-NCR / client-acceptance. These are now probed (reusing the canonical
// verdicts), shown per §10 "NOT READY → show every blocker". Default posture is ADVISORY (warn) so
// issuance is never newly blocked, EXCEPT vetting when the body has ENABLED vetting; a body can
// escalate competence/impartiality/NCR to hard blocks with 'issue_gate_strict'. Non-destructive.
t_section('Phase 2 §10 — issuance readiness completeness');

t_ok(function_exists('idems_issue_readiness'), 'idems_issue_readiness() exists');
$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($idm, "'issue_gate_strict'") !== false, 'the gate exposes a configurable strict mode');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
// Capture settings we will toggle, so we can restore both DB and the in-memory cache.
$save = [];
foreach (['vetting_gate_required','issue_gate_strict','rn_require_client_acceptance'] as $k) $save[$k] = setting_get($k);
try {
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('ncr_migrate')) ncr_migrate();
    $pdo = db();
    $labels  = fn($r) => array_column($r['items'], 'label');
    $stateOf = function ($r, $label) { foreach ($r['items'] as $i) if ($i['label'] === $label) return $i['state']; return null; };
    $read = fn($id) => idems_issue_readiness(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$id]));
    $mk = function ($type = 'IC', $vet = '') use ($pdo) {
        $pdo->prepare("INSERT INTO report_docs (type_code, status, inspector_id, client_id, inspection_date, vet_status, deleted, data, created_at)
                       VALUES (?, 'APPROVED', 990004, 555, '2026-08-01', ?, 0, '{}', ?)")->execute([$type, $vet, date('c')]);
        return (int)$pdo->lastInsertId();
    };

    // The new probes are present on a normal report.
    $id = $mk();
    $r = $read($id);
    foreach (['Report completeness','Competence','Impartiality'] as $lbl)
        t_ok(in_array($lbl, $labels($r), true), "the gate now probes '$lbl'");

    // Competence / impartiality are advisory (warn or ok), never a NEW hard block by default.
    setting_set('issue_gate_strict', '');
    t_ok(in_array($stateOf($read($id), 'Competence'), ['ok','warn'], true), 'Competence is advisory by default (no new hard block)');

    // Vetting: OFF by default → no vetting probe (nothing forced).
    setting_set('vetting_gate_required', '');
    t_ok(!in_array('Technical vetting', $labels($read($id)), true), 'with vetting off, no vetting step is forced');

    // Vetting ON + not vetted → hard block (honours the body's own configured workflow).
    setting_set('vetting_gate_required', '1');
    $rv = $read($id);
    t_ok($stateOf($rv, 'Technical vetting') === 'block' && $rv['ready'] === false, 'when vetting is enabled, an unvetted report is not ready');
    // Vetted → ok.
    $pdo->prepare("UPDATE report_docs SET vet_status='VETTED' WHERE id=?")->execute([$id]);
    t_ok($stateOf($read($id), 'Technical vetting') === 'ok', 'a vetted report clears the vetting probe');
    setting_set('vetting_gate_required', '');

    // Blocking NCR: an open NCR against the report warns by default, blocks under strict.
    $pdo->prepare("INSERT INTO nonconformities (ref, status, report_doc_id, created_at) VALUES ('NCR-T-1','OPEN',?,?)")->execute([$id, date('c')]);
    setting_set('issue_gate_strict', '');
    t_eq($stateOf($read($id), 'Nonconformities'), 'warn', 'an open NCR against the report is a warning by default');
    setting_set('issue_gate_strict', '1');
    $rs = $read($id);
    t_ok($stateOf($rs, 'Nonconformities') === 'block' && $rs['ready'] === false, 'under strict mode an open NCR blocks issue');
    setting_set('issue_gate_strict', '');

    // Client acceptance on a release note when required.
    setting_set('rn_require_client_acceptance', '1');
    $rn = $mk('RN');
    t_eq($stateOf($read($rn), 'Client acceptance'), 'warn', 'a release note pending client acceptance warns');
    $pdo->prepare("UPDATE report_docs SET client_decision='ACCEPTED' WHERE id=?")->execute([$rn]);
    t_eq($stateOf($read($rn), 'Client acceptance'), 'ok', 'once the client accepts, the probe clears');
    $pdo->prepare("UPDATE report_docs SET client_decision='REJECTED' WHERE id=?")->execute([$rn]);
    t_ok($stateOf($read($rn), 'Client acceptance') === 'block' && $read($rn)['ready'] === false, 'a client-rejected release note cannot be issued');
} finally {
    // Restore settings (fixes the in-memory cache) BEFORE rolling back.
    foreach ($save as $k => $v) setting_set($k, $v === null ? '' : (string)$v);
    if ($own && db()->inTransaction()) db()->rollBack();
}
