<?php
// ============================================================================
//  The three EXTERNAL portals — permissions must be enforced at the ADDRESS,
//  and the assistant must not be a way round them.
//
//  lib/portal.php:1745 states the rule this file defends:
//    "Refuse rather than hide: hiding a link but leaving the address reachable
//     is the bug this whole file exists to avoid."
//
//  Two real defects were found by audit and are frozen here:
//    1. /portal/request had no portal_need() -- an Accounts-only contact could
//       raise work for the company by typing the address.
//    2. cvp_ai_scope_context() scoped by COMPANY only, so a contact denied
//       reports and nonconformities could read both by asking the assistant.
//
//  (t_eq is t_eq($got, $want, $msg).)
// ============================================================================
t_section('external portals — permission enforcement');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    portal_migrate();
    $now = date('c');

    // ---- 1. every route keeps the gate the fixture froze --------------------
    $fx = __DIR__ . '/fixtures/portal_routes_gated.tsv';
    $want = [];
    foreach (file($fx, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $p = explode("\t", $line);
        $want[$p[0]] = $p[1] ?? '';
    }
    t_ok(count($want) > 25, 'the portal route fixture lists the routes (' . count($want) . ')');

    $src = file_get_contents(__DIR__ . '/../lib/portal.php');
    preg_match_all("/case '(portal(?:\/[a-z0-9\-]*)?)':/", $src, $m, PREG_OFFSET_CAPTURE);
    $marks = [];
    foreach ($m[1] as $hit) $marks[] = [$hit[1], $hit[0]];
    $got = [];
    foreach ($marks as $i => [$pos, $route]) {
        $end = $marks[$i + 1][0] ?? strlen($src);
        preg_match_all("/portal_need\('([^']+)'/", substr($src, $pos, $end - $pos), $g);
        $keys = array_values(array_unique($g[1])); sort($keys);
        $got[$route] = implode(',', $keys);
    }
    foreach ($want as $route => $keys) {
        if ($keys === '') continue;   // deliberately open to any signed-in user
        t_eq($got[$route] ?? '(route gone)', $keys,
             "/$route is still gated by portal_need('" . str_replace(',', "','", $keys) . "')");
    }
    // and the one that was missing: it must never be blank again
    t_eq($got['portal/request'] ?? '', 'request',
         '/portal/request is gated — the defect that let an Accounts-only contact raise work');

    // ---- 2. the write defends itself, whichever door calls it --------------
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at)
                   VALUES ('Perm Test Ltd',1,'ACTIVE',?)")->execute([$now]);
    $pid = (int)db()->lastInsertId();

    // A FRESH user per case: portal_user() memoises on the signed-in id and
    // returns early on an empty session without clearing its memo, so
    // re-permissioning one user in place does NOT take effect.
    $signIn = function (array $perms) use ($pid, $now) {
        static $n = 0; $n++;
        if (!$perms) throw new RuntimeException('an empty perms list means ALL perms');
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,perms,created_at)
                       VALUES (?,?,?,'x',1,?,?)")
            ->execute([$pid, "pt$n@test", "PT $n", implode(',', $perms), $now]);
        return $_SESSION['cuid'] = (int)db()->lastInsertId();
    };

    $signIn(PORTAL_PRESETS['ACCOUNTS']['perms']);                 // invoices, complaint
    t_eq(pcan('request'), false, 'the ACCOUNTS preset does not carry the request right');
    $before = (int)ops_val("SELECT COUNT(*) FROM portal_requests WHERE partner_id=?", [$pid]);
    $err = portal_request_create(['subject' => 'should be refused', 'detail' => 'x']);
    t_ok($err !== '', 'portal_request_create() refuses a user without the right: ' . $err);
    t_eq((int)ops_val("SELECT COUNT(*) FROM portal_requests WHERE partner_id=?", [$pid]), $before,
         'and writes no row');

    $signIn(['request', 'invoices']);
    t_eq(pcan('request'), true, 'a user granted the right holds it');
    $err = portal_request_create(['subject' => 'should succeed', 'detail' => 'x']);
    t_eq($err, '', 'portal_request_create() still works for a user who holds the right');
    t_eq((int)ops_val("SELECT COUNT(*) FROM portal_requests WHERE partner_id=?", [$pid]), $before + 1,
         'and writes exactly one row');

    // ---- 3. every link to that address sits behind the right ---------------
    foreach (['views/portal/invoices.php', 'views/portal/calls.php',
              'views/portal/dashboard.php'] as $v) {
        $lines = explode("\n", (string)file_get_contents(__DIR__ . '/../' . $v));
        foreach ($lines as $n => $line) {
            if (strpos($line, '/portal/request') === false) continue;
            $window = implode("\n", array_slice($lines, max(0, $n - 4), 6));
            t_ok(strpos($window, "pcan('request')") !== false,
                 basename($v) . ':' . ($n + 1) . " links to /portal/request behind pcan('request')");
        }
    }

    // ---- 4. the assistant is scoped by the USER, not just the company ------
    db()->prepare("INSERT INTO report_docs (client_id,irn,title,type_code,result,release_status,
                   inspection_date,finalized,finalized_at,rev,created_at)
                   VALUES (?,?,?,?,?,?,?,1,?,1,?)")
        ->execute([$pid, 'IRN/PT/0001', 'Weld inspection', 'IR', 'FAIL', 'NOT_RELEASED',
                   '2026-09-14', $now, $now]);

    $signIn(PORTAL_PRESETS['ACCOUNTS']['perms']);                 // no reports, no issues
    t_eq(cvp_ai_scope_keys('CLIENT', 'pcan'), [],
         'an Accounts-only contact may read NO assistant section');
    $ctx = cvp_ai_scope_context('CLIENT', $pid, 'pcan');
    t_eq(strpos($ctx, 'IRN/PT/0001'), false,
         'and the assistant context holds no report of theirs — the bypass is closed');
    t_eq(strpos($ctx, 'NONCONFORMITIES'), false, 'nor a nonconformity section');
    [$ans, $aerr] = cvp_ai_answer('CLIENT', $pid, 'what were our results?', 'pcan');
    t_eq($ans, '', 'cvp_ai_answer() returns no answer for a user who may read nothing');
    // Assert WHICH guard refused. This assertion caught a real weakness: the
    // availability guard used to run first, so with no AI key configured the
    // permission guard was never reached and this test passed on the wrong
    // message -- false assurance about the very thing it exists to check.
    t_ok(strpos($aerr, 'does not include the records') !== false,
         'and it is the PERMISSION guard that refuses, not the AI-not-configured one: ' . $aerr);

    $signIn(['reports', 'invoices']);                             // reports, but NOT issues
    t_eq(cvp_ai_scope_keys('CLIENT', 'pcan'), ['reports'],
         'a reports-only contact may read the reports section alone');
    $ctx = cvp_ai_scope_context('CLIENT', $pid, 'pcan');
    t_ok(strpos($ctx, 'IRN/PT/0001') !== false, 'their own report IS in the context');
    t_eq(strpos($ctx, 'NONCONFORMITIES'), false,
         'and the nonconformity section they were denied is NOT');

    $signIn(['reports', 'issues']);
    t_eq(cvp_ai_scope_keys('CLIENT', 'pcan'), ['reports', 'issues'],
         'a contact granted both reads both');
    $ctx = cvp_ai_scope_context('CLIENT', $pid, 'pcan');
    t_ok(strpos($ctx, 'IRN/PT/0001') !== false && strpos($ctx, 'NONCONFORMITIES') !== false,
         'and the context carries both sections');

    // ---- 5. the nav never offers a dead assistant --------------------------
    $signIn(PORTAL_PRESETS['ACCOUNTS']['perms']);
    t_eq(cvp_ai_scope_keys('CLIENT', 'pcan'), [],
         'nav guard: scope_keys is empty, so views/portal/top.php omits the Assistant link');

    unset($_SESSION['cuid']);
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
