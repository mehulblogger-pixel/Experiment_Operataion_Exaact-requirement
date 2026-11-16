<?php
// ============================================================================
//  Branch batch P2 (DEF-CP-003) and P3 (DEF-CP-002) — what ONE client person
//  sees in the portal.
//
//  P2: a client person limited to one site saw every deputation of the company
//      and could approve attendance at another site — the quantity billing is
//      drawn from. The site limit now applies to the deputation list, the
//      approvals list, the approve action and the "awaiting you" count.
//  P3: the Alerts feed was per company, so an accounts-only person was told
//      "Report X issued to you" and "Nonconformity Y needs your response". Each
//      person now sees only the alerts for what they may open (their portal
//      permissions and site limit), and marking read touches only those.
// ============================================================================
t_section('Branch batch P2/P3 — a client person\'s site limit on deputations; alerts only for what they may open');

portal_migrate();
if (function_exists('cvp_migrate')) cvp_migrate();
if (function_exists('pdso_migrate')) pdso_migrate();
$savedSession = $_SESSION;
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('BBP Refinery','BBP Refinery Ltd',1,'ACTIVE')")->execute();
    $P = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Jamnagar', 1)")->execute([$P]);
    $sJ = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Vadodara', 0)")->execute([$P]);
    $sV = (int) $pdo->lastInsertId();

    // A deputation (work order → job → attendance period) at each site, and one with no site recorded.
    $dep = function ($site, $code) use ($pdo, $P) {
        $pdo->prepare("INSERT INTO calls (call_code, client_id, site_address_id, inspection_type, status) VALUES (?,?,?,?, 'OPEN')")
            ->execute([$code, $P, $site, 'DEPUTATION']);
        $call = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO jobs (call_id, job_code, job_type) VALUES (?,?, 'DEPUTATION')")->execute([$call, 'JOB-' . $code]);
        $job = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO dep_att_approval (job_id, client_id, period_from, period_to, basis, billable_days, status, created_at)
                       VALUES (?,?, '2026-11-01','2026-11-07','MANDAYS',5,'SUBMITTED',?)")->execute([$job, $P, date('c')]);
        return ['call' => $call, 'job' => $job, 'ap' => (int) $pdo->lastInsertId()];
    };
    $J = $dep($sJ, 'BBP-J'); $V = $dep($sV, 'BBP-V'); $N = $dep(null, 'BBP-N');

    // Three people at the same company.
    $mkUser = function ($email, $perms, $sites) use ($pdo, $P) {
        $pdo->prepare("INSERT INTO client_users (partner_id, email, name, password_hash, is_active, must_change, perms, site_ids)
                       VALUES (?,?,?,'x',1,0,?,?)")->execute([$P, $email, $email, $perms, $sites]);
        return (int) $pdo->lastInsertId();
    };
    $cuJam   = $mkUser('bbp.jamnagar@x.test', 'deputation,deputation.approve,reports,issues', (string) $sJ);
    $cuMain  = $mkUser('bbp.main@x.test', '', '');
    $cuAcct  = $mkUser('bbp.accounts@x.test', 'invoices,complaint', '');
    $as = function ($cuid) { $_SESSION['cuid'] = $cuid; auth_bind_identity('cuid'); };

    // ---- P2 · the site limit on deputations --------------------------------
    $as($cuJam);
    $sites = portal_site_ids();
    t_eq($sites, [$sJ], 'the Jamnagar person is limited to the Jamnagar site');
    $jobs = array_map('intval', array_column(pdso_portal_deputations($P, $sites), 'id'));
    t_ok(in_array($J['job'], $jobs, true), 'they see the Jamnagar deputation');
    t_ok(!in_array($V['job'], $jobs, true), 'they do not see the Vadodara deputation (DEF-CP-003)');
    t_ok(in_array($N['job'], $jobs, true), 'a deputation with no site recorded stays visible, as on work orders');
    $aps = array_map('intval', array_column(pdso_portal_approvals($P, false, $sites), 'id'));
    t_ok(in_array($J['ap'], $aps, true) && !in_array($V['ap'], $aps, true), 'the attendance periods are limited the same way');
    t_ok(pdso_portal_approval($V['ap'], $P, $sites) === null, 'the Vadodara period does not exist for the approve action');
    t_ok(pdso_portal_approval($J['ap'], $P, $sites) !== null, 'the Jamnagar period can be approved');
    $count = 0; foreach (cvp_action_centre('CLIENT', $P, 'pcan') as $a) if (strpos($a['label'], 'attendance') !== false) $count = $a['count'];
    t_eq($count, 2, '"Awaiting you" counts only the periods they may approve (Jamnagar + no site)');

    $as($cuMain);
    t_eq(count(pdso_portal_deputations($P, portal_site_ids())), 3, 'a person with no site limit still sees every deputation');
    t_ok(pdso_portal_approval($V['ap'], $P, portal_site_ids()) !== null, 'and may approve any site\'s period');
    t_ok(pdso_portal_approval($V['ap'], $P + 100000, []) === null, 'another client never reaches the period (unchanged)');

    // ---- P3 · alerts only for what the person may open ----------------------
    // An issued report at each site, and a nonconformity visible to the client.
    $mkRep = function ($call, $irn) use ($pdo, $P) {
        $pdo->prepare("INSERT INTO report_docs (irn, client_id, call_id, finalized, deleted, title) VALUES (?,?,?,1,0,?)")
            ->execute([$irn, $P, $call, 'BBP report ' . $irn]);
        return (int) $pdo->lastInsertId();
    };
    $rJ = $mkRep($J['call'], 'BBP-IRN-J'); $rV = $mkRep($V['call'], 'BBP-IRN-V');
    $ncrVis = 'CLIENT';
    try {
        $pdo->prepare("INSERT INTO nonconformities (ref, title, partner_id, status, visibility) VALUES ('BBP-NCR-1','BBP finding',?, 'OPEN', ?)")
            ->execute([$P, function_exists('cvp_visible_codes') ? (cvp_visible_codes('CLIENT')[0] ?? 'CLIENT') : 'CLIENT']);
    } catch (Throwable $e) { $ncrVis = ''; }
    cvp_notify_sync('CLIENT', $P);
    $_SESSION = $savedSession; unset($_SESSION['cuid']);
    $company = cvp_notifications('CLIENT', $P);
    $events = array_column($company, 'event');
    t_ok(in_array('REPORT_ISSUED', $events, true), 'the company feed holds the report alerts (no person signed in: the feed as before)');

    $titles = function () use ($P) { return array_column(cvp_notifications('CLIENT', $P), 'title'); };
    $as($cuAcct);
    $t = $titles();
    t_ok(!array_filter($t, fn($x) => strpos($x, 'Report ') === 0), 'an accounts-only person is not told about reports (DEF-CP-002)');
    t_ok(!array_filter($t, fn($x) => strpos($x, 'Nonconformity ') === 0), 'nor about nonconformities');
    t_eq(cvp_notify_count('CLIENT', $P), 0, 'and the Alerts count in their menu is 0');

    $as($cuJam);
    $t = $titles();
    t_ok(in_array('Report BBP-IRN-J issued to you', $t, true), 'the Jamnagar person is told about the Jamnagar report');
    t_ok(!in_array('Report BBP-IRN-V issued to you', $t, true), 'but not about the Vadodara report they may not open');
    if ($ncrVis !== '') t_ok((bool) array_filter($t, fn($x) => strpos($x, 'Nonconformity BBP-NCR-1') === 0), 'and about the nonconformity, which they may open');
    $jamCount = cvp_notify_count('CLIENT', $P);
    t_eq($jamCount, count($t), 'their Alerts count matches what they are shown');

    $as($cuMain);
    t_ok(in_array('Report BBP-IRN-V issued to you', $titles(), true) && in_array('Report BBP-IRN-J issued to you', $titles(), true),
        'a person with every right and no site limit sees every alert');
    $mainUnread = cvp_notify_count('CLIENT', $P);

    // Marking read touches only what the person sees.
    $as($cuJam);
    cvp_notify_read_all('CLIENT', $P);
    t_eq(cvp_notify_count('CLIENT', $P), 0, '"Mark all read" clears the Jamnagar person\'s alerts');
    $as($cuMain);
    t_eq(cvp_notify_count('CLIENT', $P), $mainUnread - $jamCount, 'but leaves unread the alerts they were never shown (the Vadodara report)');
    $vId = 0; foreach (cvp_notifications('CLIENT', $P) as $n) if ($n['title'] === 'Report BBP-IRN-V issued to you') $vId = (int) $n['id'];
    $as($cuJam);
    cvp_notify_read('CLIENT', $P, $vId);
    $as($cuMain);
    t_eq((int) ops_val("SELECT is_read FROM portal_notifications WHERE id=?", [$vId]), 0, 'a person cannot mark read an alert they may not see');
} finally {
    $_SESSION = $savedSession;
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- P2 · through the real portal address (separate process) --------------
require_once __DIR__ . '/_branch_batch_portal_lib.php';
$w = $GLOBALS['__bbp_worker'];
{
    $o = $w('dep_approve_other_site');
    t_ok(!empty($o['ok']) && ($o['statusB'] ?? '') === 'SUBMITTED',
        'a forged approval of another site\'s attendance period changes nothing' . (!empty($o['error']) ? ' (' . $o['error'] . ')' : ''));
    $o = $w('dep_approve_own_site');
    t_ok(!empty($o['ok']) && ($o['statusA'] ?? '') === 'APPROVED' && ($o['statusB'] ?? '') === 'SUBMITTED',
        'approving their own site\'s period works, and only that period' . (!empty($o['error']) ? ' (' . $o['error'] . ')' : ''));
}
