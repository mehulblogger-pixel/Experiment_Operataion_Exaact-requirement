<?php
// ============================================================================
//  Batch 2 (owner, 17 Nov 2026 — plan 29, point 12). The remaining places a
//  client person limited to some sites ("Which sites" on Directory → Client
//  portal → Change) was still shown the whole company:
//
//    · the portal assistant's report list (cvp_ai_scope_context),
//    · the "reports to accept or reject" count (cvp_action_centre),
//    · the client dashboard's deputation and report tiles (connect_client_dash).
//
//  Each now follows the same site rule as the lists they describe
//  (portal_site_sql / pdso_portal_site_sql: the work order's site; a work order
//  with no site, or a report with no work order, stays in). A person with no
//  site limit sees every site, as before. In-process, inside a transaction that
//  is rolled back.
// ============================================================================
t_section('Batch 2 — a client person\'s site limit reaches the assistant, the "to accept or reject" count and the dashboard tiles (point 12)');

portal_migrate();
if (function_exists('cvp_migrate')) cvp_migrate();
if (function_exists('pdso_migrate')) pdso_migrate();
$savedSession = $_SESSION;
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $q = function ($sql, $a = []) use ($pdo) { $pdo->prepare($sql)->execute($a); return (int)$pdo->lastInsertId(); };
    $P  = $q("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('B2S Refinery','B2S Refinery Ltd',1,'ACTIVE')");
    $sJ = $q("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Jamnagar', 1)", [$P]);
    $sV = $q("INSERT INTO partner_addresses (partner_id, label, is_primary) VALUES (?, 'Vadodara', 0)", [$P]);

    // A work order + an active deputation + attendance periods + an issued report at each site,
    // and the same with no site recorded.
    $site = function ($sid, $code) use ($q, $P) {
        $call = $q("INSERT INTO calls (call_code, client_id, site_address_id, inspection_type, status) VALUES (?,?,?, 'DEPUTATION', 'OPEN')", [$code, $P, $sid]);
        $job  = $q("INSERT INTO jobs (call_id, job_code, job_type, dep_status) VALUES (?,?, 'DEPUTATION', 'ACTIVE')", [$call, 'JOB-' . $code]);
        foreach (['SUBMITTED', 'CLIENT_REVIEW'] as $st)
            $q("INSERT INTO dep_att_approval (job_id, client_id, period_from, period_to, basis, billable_days, status, created_at)
                VALUES (?,?, '2026-11-01','2026-11-07','MANDAYS',5,?,?)", [$job, $P, $st, date('c')]);
        $q("INSERT INTO report_docs (irn, client_id, call_id, finalized, deleted, title, rev) VALUES (?,?,?,1,0,?,0)", ['B2S-IRN-' . $code, $P, $call, 'B2S report ' . $code]);
        return $call;
    };
    $site($sJ, 'J'); $site($sV, 'V'); $site(null, 'N');

    $mkUser = function ($email, $sites, $preset = '') use ($q, $P) {
        return $q("INSERT INTO client_users (partner_id, email, name, password_hash, is_active, must_change, perms, site_ids, role_preset)
                   VALUES (?,?,?,'x',1,0,?,?,?)",
                  [$P, $email, $email, 'reports,reports.decide,issues,deputation,deputation.approve,market.post', $sites, $preset]);
    };
    $cuJam  = $mkUser('b2s.jamnagar@x.test', (string)$sJ, 'PROJECT');
    $cuMain = $mkUser('b2s.main@x.test', '', 'PROJECT');
    $as = function ($cuid) { $_SESSION['cuid'] = $cuid; auth_bind_identity('cuid'); };
    $yes = fn($k) => true;

    // ---- the portal assistant's report list --------------------------------------------------
    $as($cuJam);
    $ctx = cvp_ai_scope_context('CLIENT', $P, $yes);
    t_ok(strpos($ctx, 'B2S-IRN-J') !== false, 'the assistant reads the Jamnagar person\'s Jamnagar report');
    t_ok(strpos($ctx, 'B2S-IRN-V') === false, 'but not the Vadodara report outside their site limit (point 12)');
    t_ok(strpos($ctx, 'B2S-IRN-N') !== false, 'a report whose work order has no site stays in, as on the reports list');
    $listed = array_column(portal_reports(500), 'irn');
    $inCtx = array_values(array_filter(['B2S-IRN-J', 'B2S-IRN-V', 'B2S-IRN-N'], fn($i) => strpos($ctx, $i) !== false));
    $inList = array_values(array_filter(['B2S-IRN-J', 'B2S-IRN-V', 'B2S-IRN-N'], fn($i) => in_array($i, $listed, true)));
    t_eq($inCtx, $inList, 'the assistant reads exactly the reports the person\'s own report list shows');

    // ---- "reports to accept or reject" --------------------------------------------------------
    $count = function () use ($P) {
        foreach (cvp_action_centre('CLIENT', $P, 'pcan') as $a) if (strpos($a['label'], 'to accept or reject') !== false) return (int)$a['count'];
        return 0;
    };
    t_eq($count(), 2, '"reports to accept or reject" counts only the reports within the site limit (Jamnagar + no site)');

    // ---- the client dashboard tiles ------------------------------------------------------------
    $tile = function (array $tiles, $label) { foreach ($tiles as $t) if ($t['label'] === $label) return $t['value']; return null; };
    $proj = connect_client_dash($P, 'project', [$sJ]);
    t_eq($tile($proj, 'Active Projects'), 2, 'Active Projects counts the deputations within the site limit (point 12)');
    t_eq($tile($proj, 'Pending Site Actions'), 2, 'Pending Site Actions counts only those sites\' attendance periods');
    t_eq($tile(connect_client_dash($P, 'commercial', [$sJ]), 'Pending Client Approval'), 2, 'Pending Client Approval likewise');
    t_eq($tile(connect_client_dash($P, 'admin', [$sJ]), 'Reports Issued'), 2, 'Reports Issued counts the reports within the site limit');
    t_eq($tile(connect_client_dash($P, 'project'), 'Active Projects'), 3, 'no site limit (the default) = every site, as before');

    // Drawn for the signed-in person, from their own record.
    $draw = function ($cuid) use ($P) {
        $u = ops_one("SELECT * FROM client_users WHERE id=?", [$cuid]);
        ob_start(); connect_client_dash_render($P, $u); $h = (string)ob_get_clean();
        return preg_match('#<div class="ccd-val">(\d+)</div>\s*<div class="ccd-lab">Active Projects</div>#', $h, $m) ? (int)$m[1] : -1;
    };
    t_eq($draw($cuJam), 2, 'the dashboard drawn for the Jamnagar person shows their sites\' count');
    t_eq($draw($cuMain), 3, 'the dashboard drawn for a person with no site limit shows every site');

    $as($cuMain);
    t_eq($count(), 3, 'a person with no site limit is asked to decide every report');
    $ctx = cvp_ai_scope_context('CLIENT', $P, $yes);
    t_ok(strpos($ctx, 'B2S-IRN-V') !== false && strpos($ctx, 'B2S-IRN-J') !== false, '…and the assistant reads every site\'s reports');
} finally {
    $_SESSION = $savedSession;
    if ($own && db()->inTransaction()) db()->rollBack();
}
