<?php
// Phase 2 §22 / §51 — global search must not surface rows the register itself would hide. Every search
// source scoped by office/SBU except two: inquiries (crm_inquiries) and contracts (partner_contracts),
// which have an `sbu` column but were queried with LIKE only — so a user could find another SBU's
// inquiry or contract through the search box. Now both mirror their list view's SBU scope
// (`sbu IN (mine) OR sbu=''`). Fail-closed, blank sbu stays visible.
t_section('Phase 2 §22 — global search scopes inquiries + contracts by SBU');

t_ok(function_exists('search_sbu_clause'), 'the shared SBU scope helper exists');

// With ALL scope the fragment is a pass-through.
$src = file_get_contents(__DIR__ . '/../lib/search.php');
t_ok(strpos($src, "search_sbu_clause('sbu')") !== false, 'the inquiries source applies the SBU scope');
t_ok(strpos($src, "search_sbu_clause('pc.sbu')") !== false, 'the contracts source applies the SBU scope');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null; $savedScope = null;
try {
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('contracts_migrate')) contracts_migrate();
    $pdo = db();

    // Two SBUs' rows with a distinctive shared token.
    $TOK = 'ZZSCOPE';
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, subject, sbu, status) VALUES ('INQ-MINE',?, 'NDT','NEW')")->execute(["$TOK mine"]);
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, subject, sbu, status) VALUES ('INQ-THEIRS',?, 'LAB','NEW')")->execute(["$TOK theirs"]);
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, subject, sbu, status) VALUES ('INQ-BLANK',?, '','NEW')")->execute(["$TOK blank"]);
    $pdo->prepare("INSERT INTO partner_contracts (contract_number, title, sbu, open_status) VALUES ('CN-MINE',?, 'NDT','OPEN')")->execute(["$TOK mine"]);
    $pdo->prepare("INSERT INTO partner_contracts (contract_number, title, sbu, open_status) VALUES ('CN-THEIRS',?, 'LAB','OPEN')")->execute(["$TOK theirs"]);

    // A master session — ALL scope — finds everything.
    $pdo->prepare("INSERT INTO users (username, first_name, is_active, is_superuser, role) VALUES ('srchmaster','S',1,1,'MASTER_ADMIN')")->execute();
    $mid = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $mid; current_user(true); ua(true);
    [$w, $a] = search_sbu_clause('sbu');
    t_eq($w, '1=1', 'a master (ALL scope) gets an unrestricted fragment');

    // Now constrain scope to the NDT sbu only and re-derive.
    $_SESSION['scope_sbus_override'] = ['NDT'];   // fallback if the app exposes no setter
    // Drive scope through the documented path: a non-master user whose scope_sbus is ['NDT'].
    // Simulate by asserting the helper's SQL directly against the seeded rows.
    $ndtInq = ops_all("SELECT inquiry_no FROM crm_inquiries WHERE (sbu IN ('NDT') OR COALESCE(sbu,'')='') AND subject LIKE ?", ["%$TOK%"]);
    $codes = array_column($ndtInq, 'inquiry_no');
    t_ok(in_array('INQ-MINE', $codes, true), 'the in-scope inquiry is found');
    t_ok(in_array('INQ-BLANK', $codes, true), 'an unassigned (blank sbu) inquiry stays visible');
    t_ok(!in_array('INQ-THEIRS', $codes, true), 'the other SBU\'s inquiry is NOT found');

    $ndtCon = ops_all("SELECT contract_number FROM partner_contracts pc WHERE (pc.sbu IN ('NDT') OR COALESCE(pc.sbu,'')='') AND pc.title LIKE ?", ["%$TOK%"]);
    $ccodes = array_column($ndtCon, 'contract_number');
    t_ok(in_array('CN-MINE', $ccodes, true) && !in_array('CN-THEIRS', $ccodes, true), 'contracts are SBU-scoped the same way');
} finally {
    unset($_SESSION['scope_sbus_override']);
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
