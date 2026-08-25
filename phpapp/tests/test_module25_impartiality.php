<?php
// Module 25 — Impartiality / conflict-of-interest verdict. A read-only verdict
// (CLEAR / REVIEW / CONFLICT) shown while choosing an inspector. CONFLICT mirrors the
// existing hard gate (a declared OPEN/UNACCEPTABLE threat), and REVIEW adds the one
// COI signal that is genuinely computable — repeated assignment (familiarity) — plus a
// due declaration. Advisory; the declared-threat block stays the one hard stop. This
// also fills the previously-missing coverage of imp_block itself.
t_section('Module 25 — impartiality verdict + the declared-threat gate');

$imp  = file_get_contents(__DIR__ . '/../lib/impartiality.php');
$form = file_get_contents(__DIR__ . '/../views/ops/job_form.php');

t_ok(function_exists('inspector_impartiality') && function_exists('inspector_impartiality_pill'),
    'the verdict helpers exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('impartiality_migrate')) impartiality_migrate();
    db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Imp Tester','ACTIVE')")->execute();
    $insp = (int)db()->lastInsertId();
    // A current declaration on file, so a missing-declaration REVIEW doesn't mask the
    // signals under test (that path is exercised separately below).
    db()->prepare("INSERT INTO impartiality_declarations (person_kind, person_id, declared_on, valid_to, has_conflicts) VALUES ('INSPECTOR', ?, ?, ?, 0)")
        ->execute([$insp, date('Y-m-d', strtotime('-1 month')), date('Y-m-d', strtotime('+11 months'))]);
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Imp Client A','Imp Client A',1,'ACTIVE')")->execute();
    $cliA = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Imp Client B','Imp Client B',1,'ACTIVE')")->execute();
    $cliB = (int)db()->lastInsertId();

    // Clean to start.
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliA])['status'] === 'CLEAR', 'no threats / no history → CLEAR');

    // ---- The existing HARD gate (fills the coverage gap) ----
    db()->prepare("INSERT INTO impartiality_threats (threat_kind, person_kind, person_id, partner_id, status, raised_on) VALUES ('FINANCIAL','INSPECTOR',?,?, 'OPEN', '2026-08-01')")->execute([$insp, $cliA]);
    t_ok(imp_block($insp, [$cliA]) !== '', 'an OPEN threat for this client blocks allocation (imp_block non-empty)');
    t_ok(imp_block($insp, [$cliB]) === '', 'the same threat does NOT block a different client');

    // The verdict reflects the block as CONFLICT for client A, CLEAR for client B.
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliA])['status'] === 'CONFLICT', 'a declared blocking threat → CONFLICT');
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliB])['status'] === 'CLEAR', 'a threat scoped to another client does not conflict here');

    // Deciding the threat (ACCEPTED, with safeguards) clears the hard gate.
    db()->prepare("UPDATE impartiality_threats SET status='ACCEPTED', safeguards='second reviewer' WHERE person_id=? AND partner_id=?")->execute([$insp, $cliA]);
    t_ok(imp_block($insp, [$cliA]) === '', 'a decided (ACCEPTED) threat no longer blocks');
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliA])['status'] !== 'CONFLICT', 'and the verdict is no longer CONFLICT');

    // A person-general threat (no partner) blocks every client.
    db()->prepare("INSERT INTO impartiality_threats (threat_kind, person_kind, person_id, partner_id, status, raised_on) VALUES ('ORGANISATIONAL','INSPECTOR',?, NULL, 'OPEN', '2026-08-01')")->execute([$insp]);
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliB])['status'] === 'CONFLICT', 'a person-general threat conflicts for any client');
    db()->prepare("DELETE FROM impartiality_threats WHERE person_id=?")->execute([$insp]);

    // ---- The NEW computed signal: repeated assignment (familiarity) → REVIEW ----
    $thr = imp_familiarity_threshold();
    // Build (threshold) jobs for this inspector on client A within 12 months.
    for ($k = 0; $k < $thr; $k++) {
        db()->prepare("INSERT INTO calls (call_code, client_id, status, created_at) VALUES (?, ?, 'OPEN', ?)")->execute(['IMPC-' . $k, $cliA, date('c')]);
        $callId = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute(['IMPJ-' . $k, $callId, $insp, date('Y-m-d', strtotime('-' . ($k * 10) . ' days')), date('c')]);
    }
    $v = inspector_impartiality($insp, ['client_id'=>$cliA]);
    t_ok($v['status'] === 'REVIEW', 'reaching the familiarity threshold on a client → REVIEW (advisory)');
    t_ok((bool)array_filter($v['reasons'], fn($r) => stripos($r['text'], 'familiar') !== false || stripos($r['text'], 'rotation') !== false),
        'the REVIEW reason names the familiarity / rotation concern');
    // A different client the inspector has not served → CLEAR.
    t_ok(inspector_impartiality($insp, ['client_id'=>$cliB])['status'] === 'CLEAR', 'familiarity is per-client — a fresh client stays CLEAR');

    // A separate inspector with NO declaration on file → REVIEW (declaration due), never CONFLICT.
    db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Imp NoDecl','ACTIVE')")->execute();
    $nodecl = (int)db()->lastInsertId();
    t_ok(inspector_impartiality($nodecl, ['client_id'=>$cliB])['status'] === 'REVIEW',
        'an inspector with no current declaration is a REVIEW (missing paperwork is a prompt, not a conflict)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// Pills + the allocation surface.
t_ok(inspector_impartiality_pill('CONFLICT')[1] === 'p-bad' && strpos(inspector_impartiality_pill('CLEAR')[0], 'Clear') !== false,
    'the pill helper maps statuses to labels and classes');
t_ok(strpos($form, 'inspector_impartiality((int)$s[\'id\'], $eligCtx)') !== false && strpos($form, 'imp-mark') !== false,
    'the allocation picker shows the impartiality verdict');

// The non-overridable declared-threat gate is unchanged (no override wired for impartiality).
t_ok(strpos($imp, 'has a declared threat to impartiality that has not been cleared') !== false,
    'the declared-threat hard-block message is preserved');
t_ok(!preg_match('/can\(\x27(impartiality|coi)\.new/', $imp), 'Module 25 introduces no new permission constant');
