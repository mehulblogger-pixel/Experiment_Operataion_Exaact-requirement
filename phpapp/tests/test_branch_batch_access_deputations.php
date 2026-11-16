<?php
// ============================================================================
//  Branch batch B3 — DEF-OPS-037 (owner, 16 Nov 2026 —
//  QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md): the deputation register and
//  its dashboard (pdso_deputations()) listed every branch's deputations. A
//  deputation is a job, so it now follows the work-order office rule: the
//  office carrying it out or the office that contracted it may list it; a
//  person with no branch lists none; a run with nobody signed in is unscoped.
// ============================================================================
require_once __DIR__ . '/_branch_batch_helpers.php';
$prevSession = $_SESSION ?? [];
try {
    if (function_exists('pdso_migrate')) pdso_migrate();
    $A = bb_office('DepAlpha'); $M = bb_office('DepMumbai'); $P = bb_office('DepPune');
    $t = strtoupper(bb_tag());
    $mk = function ($code, $exec, $ibo) {
        $c = bb_call($code, $exec, $ibo);
        db()->prepare("UPDATE calls SET inspection_type='DEPUTATION' WHERE id=?")->execute([$c]);
        $j = bb_job($code . '-J', $c, $exec);
        db()->prepare("UPDATE jobs SET job_type='DEPUTATION' WHERE id=?")->execute([$j]);
        return $j;
    };
    $jA   = $mk("$t-DA", $A, $A);      // Alpha's own deputation
    $jM   = $mk("$t-DM", $M, $M);      // Mumbai's own deputation
    $jFwd = $mk("$t-DF", $M, $A);      // sold by Alpha, carried out by Mumbai
    $ids = function () { return array_map(fn($r) => (int) $r['id'], pdso_deputations()); };

    t_section('B3 — the deputation register follows the work-order office rule (DEF-OPS-037)');

    bb_as(bb_user('COORDINATOR', $M));
    $got = $ids();
    t_ok(in_array($jM, $got, true), 'a Mumbai coordinator lists Mumbai\'s deputation');
    t_ok(in_array($jFwd, $got, true), 'and the one Mumbai carries out for Alpha');
    t_ok(!in_array($jA, $got, true), 'but NOT Alpha\'s own deputation');

    bb_as(bb_user('COORDINATOR', $A));
    $got = $ids();
    t_ok(in_array($jA, $got, true) && in_array($jFwd, $got, true), 'an Alpha coordinator lists its own and the one it contracted out');
    t_ok(!in_array($jM, $got, true), 'but NOT Mumbai\'s own deputation');

    bb_as(bb_user('COORDINATOR', $P));
    $got = $ids();
    t_ok(!in_array($jA, $got, true) && !in_array($jM, $got, true) && !in_array($jFwd, $got, true), 'a Pune coordinator lists none of them');

    bb_as(bb_user('COORDINATOR', null, ''));
    $got = $ids();
    t_ok(!in_array($jA, $got, true) && !in_array($jM, $got, true) && !in_array($jFwd, $got, true), 'a coordinator with no branch lists none');

    bb_as(bb_user('ADMIN', null));
    $got = $ids();
    t_ok(in_array($jA, $got, true) && in_array($jM, $got, true) && in_array($jFwd, $got, true), 'an Admin lists all three');

    bb_as_nobody();
    $got = $ids();
    t_ok(in_array($jA, $got, true) && in_array($jM, $got, true), 'a run with nobody signed in (cron, export) stays unscoped');
} finally {
    bb_cleanup();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
