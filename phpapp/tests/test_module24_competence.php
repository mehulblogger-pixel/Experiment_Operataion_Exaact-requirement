<?php
// Module 24 — Competence eligibility verdict. A single per-(inspector × job) verdict
// (ELIGIBLE / EXPIRING / CHECK / BLOCKED) shown while choosing an inspector. It MIRRORS
// the existing hard gate (a lapsed mandatory cert blocks the save) and adds advisory
// signals (expiring cert, wrong discipline, out-of-SBU) that never block. This also
// fills the previously-missing behavioural coverage of the competence gate.
t_section('Module 24 — inspector eligibility verdict + gate coverage');

$comp = file_get_contents(__DIR__ . '/../lib/competence.php');
$form = file_get_contents(__DIR__ . '/../views/ops/job_form.php');

t_ok(function_exists('inspector_eligibility') && function_exists('inspector_eligibility_pill'),
    'the verdict helpers exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    // A clean inspector: a trade, an SBU scope, no certs.
    db()->prepare("INSERT INTO inspectors (name, status, trade_id, sbu, sbus) VALUES ('Elig Clean','ACTIVE',7,'IND','IND,MECH')")->execute();
    $clean = (int)db()->lastInsertId();
    t_ok(inspector_eligibility($clean, [])['status'] === 'ELIGIBLE', 'an inspector with nothing against them is ELIGIBLE');

    // Matching discipline + in-scope SBU → still ELIGIBLE.
    t_ok(inspector_eligibility($clean, ['req_trade_id'=>7, 'sbu'=>'IND'])['status'] === 'ELIGIBLE',
        'a matching discipline and in-scope SBU stays ELIGIBLE');

    // Wrong discipline → CHECK (advisory, never a block).
    t_ok(inspector_eligibility($clean, ['req_trade_id'=>9])['status'] === 'CHECK',
        'a different discipline is an advisory CHECK, not a block');
    // Out-of-SBU → CHECK.
    t_ok(inspector_eligibility($clean, ['sbu'=>'OIL'])['status'] === 'CHECK',
        'work outside their SBU scope is an advisory CHECK');

    // A MANDATORY certificate lapsed on the work date → BLOCKED (mirrors the real gate).
    db()->prepare("INSERT INTO inspectors (name, status, trade_id) VALUES ('Elig Lapsed','ACTIVE',7)")->execute();
    $lapsed = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, is_mandatory, valid_to, status) VALUES (?, 'BGAS', 1, '2020-01-01', 'VALID')")->execute([$lapsed]);
    $v = inspector_eligibility($lapsed, ['on_date'=>'2026-08-20']);
    t_ok($v['status'] === 'BLOCKED', 'a lapsed MANDATORY certificate blocks eligibility on the work date');
    // The real gate agrees: competence_lapsed returns the lapsed cert.
    t_ok(count((array)competence_lapsed($lapsed, '2026-08-20')) === 1,
        'the underlying hard gate (competence_lapsed) also flags the lapsed mandatory cert');

    // A NON-mandatory lapsed cert must NOT block (must match the gate exactly).
    db()->prepare("INSERT INTO inspectors (name, status, trade_id) VALUES ('Elig NonMand','ACTIVE',7)")->execute();
    $nonmand = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, is_mandatory, valid_to) VALUES (?, 'Optional course', 0, '2020-01-01')")->execute([$nonmand]);
    t_ok(inspector_eligibility($nonmand, ['on_date'=>'2026-08-20'])['status'] !== 'BLOCKED',
        'a lapsed NON-mandatory certificate does not block (only mandatory certs gate)');
    t_ok(count((array)competence_lapsed($nonmand, '2026-08-20')) === 0,
        'the gate ignores non-mandatory certs too');

    // An expiring (not yet lapsed) mandatory cert → EXPIRING (advisory).
    db()->prepare("INSERT INTO inspectors (name, status, trade_id) VALUES ('Elig Expiring','ACTIVE',7)")->execute();
    $exp = (int)db()->lastInsertId();
    $soon = date('Y-m-d', strtotime('+20 days'));
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, is_mandatory, valid_to) VALUES (?, 'CSWIP', 1, ?)")->execute([$exp, $soon]);
    t_ok(inspector_eligibility($exp, [])['status'] === 'EXPIRING', 'a soon-to-expire certificate is EXPIRING (advisory)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The verdict pill maps each status to a label + class.
t_ok(inspector_eligibility_pill('BLOCKED')[1] === 'p-bad' && strpos(inspector_eligibility_pill('ELIGIBLE')[0], 'Eligible') !== false,
    'the pill helper maps statuses to labels and classes');

// The picker shows the verdict while choosing (not just as a save error), and hides nobody.
t_ok(strpos($form, 'inspector_eligibility((int)$s[\'id\'], $eligCtx)') !== false
  && strpos($form, 'elig-mark') !== false,
    'the suggested-inspector chips show the eligibility verdict');

// The existing hard gate, override authority and enforcement toggle are untouched.
t_ok(strpos($comp, 'function competence_can_override()') !== false
  && strpos($comp, "can('mod.jobs.edit') && (is_admin_level() || is_master())") !== false,
    'the override authority is unchanged');
t_ok(!preg_match('/can\(\x27(competence|eligibility)\./', $comp), 'Module 24 introduces no new permission constant');
