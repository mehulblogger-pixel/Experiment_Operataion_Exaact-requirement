<?php
// Module 37 — Global search. Add the missing spine entities (opportunities, invoices) as new
// permission-gated, office+SBU-scoped sources — a correctly-scoped new path that finds records by
// their own reference, loosening nothing. First executable coverage of the search registry.
t_section('Module 37 — search covers opportunities + invoices');

$lib = file_get_contents(__DIR__ . '/../lib/search.php');

t_ok(strpos($lib, "\$add('opportunities'") !== false, 'an opportunities search source was added');
t_ok(strpos($lib, "scope_clause('o.office_id', 'o.sbu')") !== false, 'the opportunities source is office+SBU scoped');
t_ok(strpos($lib, "\$add('invoices'") !== false, 'an invoices search source was added');
t_ok(strpos($lib, "scope_clause('i.office_id', 'i.sbu')") !== false, 'the invoices source is office+SBU scoped');
t_ok(strpos($lib, "'/opportunity?id='") !== false && strpos($lib, "'/invoice?id='") !== false, 'both deep-link to the record');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('opp_migrate')) opp_migrate();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();

    // A master user, so every permission-gated source is present.
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES ('srchmaster','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);

    $pdo->prepare("INSERT INTO opportunities (ref, name, partner_name, status, value) VALUES ('OPP-SRCH-1','Big deal','Acme','OPEN', 5000)")->execute();
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_name, status, total) VALUES ('INVSRCH/7','Acme','ISSUED', 1200)")->execute();

    $src = search_sources();
    t_ok(isset($src['opportunities']) && isset($src['invoices']), 'both new sources are present for a privileged user');

    $oRes = $src['opportunities']['run']('OPP-SRCH-1', 10);
    t_ok(count($oRes) >= 1 && strpos($oRes[0]['url'], '/opportunity?id=') === 0, 'search finds the opportunity by its OPP ref and deep-links to it');
    $iRes = $src['invoices']['run']('INVSRCH/7', 10);
    t_ok(count($iRes) >= 1 && strpos($iRes[0]['url'], '/invoice?id=') === 0, 'search finds the invoice by its number and deep-links to it');

    // Permission gating (not query-then-filter): a guest with no finance permission gets NO invoices source.
    unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true);
    $guest = search_sources();
    t_ok(!isset($guest['invoices']), 'a user without finance permission never gets the invoices source (gated, not filtered)');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
t_ok(strpos($lib, "if (!\$can) return;") !== false, 'the permission gate (never query a source you cannot see) is intact');
t_ok(strpos($lib, "\$add('quotes'") !== false && strpos($lib, "\$add('reports'") !== false, 'existing sources are preserved (additive)');
