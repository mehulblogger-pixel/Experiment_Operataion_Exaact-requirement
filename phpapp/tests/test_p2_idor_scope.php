<?php
// Phase 2 §51 — cross-office IDOR. Office/SBU scope was enforced on LIST queries (scope_clause) but
// DROPPED on single-record / PDF / file reads, so a branch-scoped user could open ANY id (job,
// report, report-PDF, invoice, endorsement file, check-in photo) by changing the URL. scope_allows()
// is the scalar twin of scope_clause(); the fetch-by-id handlers now fail-closed with it. Masters and
// ALL-scope roles are unaffected.
t_section('Phase 2 §51 — fetch-by-id is office/SBU scoped (IDOR closed)');

t_ok(function_exists('scope_allows'), 'scope_allows() exists (scalar twin of scope_clause)');

$pdo = db();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    // Two offices; A is the user's home, B is another branch. (Neither is Ahmedabad, so the
    // null-office = Ahmedabad rule doesn't confuse the test.)
    $pdo->prepare("INSERT INTO offices (name, code) VALUES ('IDOR-A','IDA')")->execute(); $A = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO offices (name, code) VALUES ('IDOR-B','IDB')")->execute(); $B = (int)$pdo->lastInsertId();

    // A branch-scoped coordinator whose scope is office A only.
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id) VALUES ('idor_coord','Coord','COORDINATOR',1,?)")->execute([$A]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); ua(true);

    $off = scope_offices();
    t_ok(is_array($off) && in_array($A, array_map('intval', $off), true) && !in_array($B, array_map('intval', $off), true),
        'the coordinator is scoped to office A only (' . json_encode($off) . ')');
    t_ok(!is_master(), 'the coordinator is not a master');

    // The scalar guard: own office allowed, other office denied.
    t_ok(scope_allows($A) === true,  'a record in the user\'s own office is allowed');
    t_ok(scope_allows($B) === false, 'a record in another office is DENIED (the IDOR that was open)');
    // A record with no office falls back to Ahmedabad (matches scope_clause); since A is not
    // Ahmedabad, a null office is not the user's office → denied for this scoped user.
    t_ok(scope_allows(null) === false, 'a null-office record resolves to Ahmedabad (consistent with the list layer)');

    // SBU scope, when the user is SBU-limited, also filters (coordinator is SBU=ALL, so simulate a
    // restricted set via a role that scopes SBU — here we just confirm office+matching SBU passes).
    t_ok(scope_allows($A, 'NDT') === true, 'own office + any SBU passes when the user is not SBU-limited');

    // A master sees everything.
    $pdo->prepare("INSERT INTO users (username, role, is_active, is_superuser) VALUES ('idor_master','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); ua(true);
    t_ok(scope_allows($B) === true && scope_allows(null) === true, 'a master is never scope-restricted');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); ua(true);
}

// ---- the six fetch-by-id handlers now carry the guard ----
$ops   = file_get_contents(__DIR__ . '/../lib/ops.php');
$idems = file_get_contents(__DIR__ . '/../lib/idems.php');
$trust = file_get_contents(__DIR__ . '/../lib/trust.php');
$books = file_get_contents(__DIR__ . '/../lib/booksui.php');
t_ok(substr_count($ops . $idems . $trust . $books, 'scope_allows(') >= 6, 'the fetch-by-id handlers call scope_allows() (>=6 sites)');
t_ok(strpos($ops, "scope_allows(\$job['executing_office_id']") !== false, '/job detail is scoped');
t_ok(strpos($idems, "scope_allows(\$doc['office_id']") !== false, '/document + /document-pdf are scoped');
t_ok(strpos($idems, "scope_allows(\$f['e_office']") !== false, '/endorsement-file is scoped');
t_ok(strpos($trust, 'scope_allows(') !== false, '/checkin-photo is scoped (the long-flagged IDOR)');
t_ok(strpos($books, 'scope_allows((int)$inv[\'office_id\'])') !== false, '/invoice + /invoice-print are scoped');
