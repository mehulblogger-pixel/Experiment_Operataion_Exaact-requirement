<?php
// Phase 2 §54 — audit chain protection. The chain was tamper-EVIDENT but (a) the sanctioned retention
// purge deleted the chain head and then tripped its own verifier (a legitimate trim looked like
// tampering), and (b) a master could wipe the whole chain via reset-data with no trace. Now: the trim
// records its boundary hash so the verifier recognises it (while still catching real deletions), the
// audit wipe leaves durable evidence OUTSIDE the audit tables, and it needs a distinct typed phrase.
t_section('Phase 2 §54 — audit chain: legitimate trim vs real tampering');

$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
$cmp = file_get_contents(__DIR__ . '/../lib/compliance.php');
$rst = file_get_contents(__DIR__ . '/../lib/reset.php');
t_ok(strpos($cmp, "setting_set('audit_trim_anchor'") !== false, 'the retention trim records its boundary anchor');
t_ok(strpos($idm, "\$r['prev_hash'] === \$anchor") !== false, 'the verifier accepts the recorded trim boundary');
t_ok(strpos($rst, "'AUDIT_RESET'") !== false && strpos($rst, "setting_set('audit_reset_log'") !== false, 'wiping the audit trail leaves durable evidence + an audit event');
t_ok(strpos($rst, "'ERASE AUDIT'") !== false, 'erasing the audit trail needs a distinct typed phrase');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$saveAnchor = setting_get('audit_trim_anchor');
try {
    if (function_exists('idems_migrate')) idems_migrate();
    // The test DB already holds seeded audit rows, so measure DELTAS against a baseline
    // rather than absolute intactness.
    setting_set('audit_trim_anchor', '');           // no boundary excused during the baseline
    $base = idems_audit_verify();

    // Append 5 sealed rows onto the existing head — they chain cleanly, adding no breakage.
    for ($i = 0; $i < 5; $i++) idems_log('test_entity', $i, 'CREATE', ['field' => 'row' . $i]);
    $v1 = idems_audit_verify();
    t_eq($v1['links'], $base['links'], 'appending sealed rows adds no broken link (they chain onto the head)');
    t_eq($v1['content'], $base['content'], 'appending sealed rows adds no content break');

    // Simulate a LEGITIMATE retention purge: delete my two oldest rows (the head of my run).
    $ids = array_map('intval', array_column(ops_all("SELECT id FROM idems_audit WHERE entity='test_entity' ORDER BY id") ?: [], 'id'));
    $newHeadPrev = (string)ops_val("SELECT prev_hash FROM idems_audit WHERE id=?", [$ids[2]]);
    db()->prepare("DELETE FROM idems_audit WHERE id IN (?,?)")->execute([$ids[0], $ids[1]]);

    // WITHOUT the anchor, the purge adds one broken link — real deletions are still caught.
    setting_set('audit_trim_anchor', '');
    $vBad = idems_audit_verify();
    t_eq($vBad['links'], $base['links'] + 1, 'a purge with no recorded boundary reads as one broken link (real deletions still caught)');

    // WITH the boundary recorded (as audit_trim_old now does), the SAME purge is recognised as legit.
    setting_set('audit_trim_anchor', $newHeadPrev);
    $vGood = idems_audit_verify();
    t_eq($vGood['links'], $base['links'], 'the same purge, with its boundary recorded, adds no broken link');
    t_ok(!empty($vGood['trim_boundary']), 'the verifier marks the recognised trim boundary');

    // Real tampering is STILL caught even with an anchor set: edit a surviving row's content.
    db()->prepare("UPDATE idems_audit SET action='EDITED_BY_HAND' WHERE id=?")->execute([$ids[3]]);
    $vTamper = idems_audit_verify();
    t_eq($vTamper['content'], $base['content'] + 1, 'a content edit to a surviving row is still detected as tampering');

    // The anchor marker is a system key — it never pollutes the sealed chain itself.
    t_ok(!setting_change_class('audit_trim_anchor')['audit'], 'audit_trim_anchor is a system marker (not itself audited)');
} finally {
    setting_set('audit_trim_anchor', $saveAnchor === null ? '' : (string)$saveAnchor);
    if ($own && db()->inTransaction()) db()->rollBack();
}
