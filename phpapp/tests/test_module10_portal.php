<?php
// Module 10 — Client Portal. The portal shows the client the REAL invoices register
// (consolidated + manual invoices, honest part-payments) via an additive superset that keeps
// every legacy mirror row, and single-record fetches now scope by site like the list views.
t_section('Module 10 — portal register invoices + site scope');

$lib = file_get_contents(__DIR__ . '/../lib/portal.php');

t_ok(function_exists('portal_invoices_register'), 'portal_invoices_register() exists');
t_ok(function_exists('portal_invoices'), 'portal_invoices() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedCuid = $_SESSION['cuid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();

    // Two clients; the portal user belongs to P and must never see Q.
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('Portal Co','Portal Co Ltd',1,'ACTIVE')")->execute();
    $P = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('Other Co','Other Co Ltd',1,'ACTIVE')")->execute();
    $Q = (int)$pdo->lastInsertId();

    // Two sites for P.
    $pdo->prepare("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Site A', 1)")->execute([$P]);
    $siteA = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Site B', 0)")->execute([$P]);
    $siteB = (int)$pdo->lastInsertId();

    // A portal user with ALL sites, and one restricted to Site A.
    $mkUser = function ($sites) use ($pdo, $P) {
        $pdo->prepare("INSERT INTO client_users (partner_id, email, password_hash, is_active, site_ids) VALUES (?,?,?,1,?)")
            ->execute([$P, 'u' . $sites . '@x.test', 'x', $sites]);
        return (int)$pdo->lastInsertId();
    };
    $cuAll = $mkUser('');
    $cuA   = $mkUser((string)$siteA);

    // Register invoices for P: issued, draft (hidden), part-paid; and one for Q (never seen).
    $mkInv = function ($partner, $no, $status, $total) use ($pdo) {
        $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, status, total, invoice_date) VALUES (?,?,?,?, '2026-05-01')")
            ->execute([$no, $partner, $status, $total]);
        return (int)$pdo->lastInsertId();
    };
    $iIssued = $mkInv($P, 'PINV/1', 'ISSUED', 1000);
    $iDraft  = $mkInv($P, null,     'DRAFT',  500);
    $iPart   = $mkInv($P, 'PINV/2', 'ISSUED', 2000);
    $iOther  = $mkInv($Q, 'QINV/9', 'ISSUED', 4000);
    // Part-payment on iPart: 800 allocated (cash) → outstanding 1200.
    $pdo->prepare("INSERT INTO receipt_allocations (receipt_id, invoice_id, kind, amount) VALUES (0,?, 'CASH', 800)")->execute([$iPart]);

    $_SESSION['cuid'] = $cuAll;

    $reg = portal_invoices_register();
    $byNo = []; foreach ($reg as $r) $byNo[$r['invoice_number']] = $r;
    t_ok(isset($byNo['PINV/1']), 'an issued register invoice is shown to its client');
    t_ok(!isset($byNo['QINV/9']), 'another client\'s invoice is never returned (partner scope)');
    $draftShown = false; foreach ($reg as $r) if ((int)$r['id'] === $iDraft) $draftShown = true;
    t_ok(!$draftShown, 'a DRAFT invoice is not shown to the client');
    t_eq((int)round($byNo['PINV/1']['outstanding']), 1000, 'a fully-unpaid invoice shows its full outstanding');
    t_eq((int)round($byNo['PINV/2']['outstanding']), 1200, 'a part-paid invoice shows the real remaining balance, not the whole amount');
    t_ok((int)$byNo['PINV/2']['payment_received'] === 0, 'a part-paid invoice is not marked fully received');

    // Superset: a legacy mirror-only invoice appears; a mirror row matching a register number does not double.
    $pdo->prepare("INSERT INTO calls (client_id, call_code, status) VALUES (?, 'C-1', 'CLOSED')")->execute([$P]);
    $call = (int)$pdo->lastInsertId();
    $mkJob = function ($no, $amt) use ($pdo, $call) {
        $pdo->prepare("INSERT INTO jobs (call_id, job_code, invoice_raised, invoice_number, invoice_amount, invoice_date, payment_received) VALUES (?,?,1,?,?, '2026-04-01', 0)")
            ->execute([$call, 'J-' . $no, $no, $amt]);
    };
    $mkJob('OLD/9', 700);      // pre-Books invoice — mirror only
    $mkJob('PINV/1', 1000);    // same number as a register invoice — must dedupe

    $all = portal_invoices();
    $nums = array_column($all, 'invoice_number');
    t_ok(in_array('OLD/9', $nums, true), 'a legacy mirror-only invoice still appears (nothing a client saw disappears)');
    $countPinv1 = count(array_filter($nums, fn($n) => $n === 'PINV/1'));
    t_eq($countPinv1, 1, 'an invoice present in both register and mirror is shown once (de-duplicated)');

    // ---- site scope on single-record fetches ----
    // A call at Site B, and one at Site A.
    $pdo->prepare("INSERT INTO calls (client_id, call_code, status, site_address_id) VALUES (?, 'C-A', 'OPEN', ?)")->execute([$P, $siteA]);
    $callA = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO calls (client_id, call_code, status, site_address_id) VALUES (?, 'C-B', 'OPEN', ?)")->execute([$P, $siteB]);
    $callB = (int)$pdo->lastInsertId();

    $_SESSION['cuid'] = $cuA;                    // restricted to Site A
    t_ok(portal_call($callA) !== null, 'a site-restricted user can open a call at their own site');
    t_ok(portal_call($callB) === null, 'a site-restricted user cannot open a same-company call at another site (single-fetch now scoped)');

    $_SESSION['cuid'] = $cuAll;                  // all sites
    t_ok(portal_call($callB) !== null, 'a user with no site restriction still sees every site');
} finally {
    if ($savedCuid === null) unset($_SESSION['cuid']); else $_SESSION['cuid'] = $savedCuid;
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation (string-level) ----
t_ok(strpos($lib, "status IN ('ISSUED','PART_PAID','PAID')") !== false, 'the register read shows only issued money, never a draft');
t_ok(strpos($lib, 'books_settled') !== false, 'the portal outstanding comes from the books, not a boolean');
t_ok(strpos($lib, 'i.partner_id = ?') !== false, 'the register read keeps partner scope in the WHERE clause');
t_ok(!preg_match('/SELECT\s+i\.\*\s+FROM invoices/i', $lib), 'the portal never SELECTs * off invoices (no cost/margin/internal column leaks)');
t_ok(strpos($lib, 'd.finalized = 1') !== false, 'the finalized-only report gate is preserved');
