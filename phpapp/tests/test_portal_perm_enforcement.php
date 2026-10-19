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

    // Split on BOTH dispatch forms. Splitting on `case` alone misses the three
    // sign-in routes, and -- the part that actually bites -- leaves the LAST
    // case arm running to the end of the file, so every gate below it is
    // credited to that one route. A sibling probe reported `issues` as enforced
    // at `/vendor/password` for exactly that reason. The dollar in $route has to
    // reach PCRE escaped; in a double-quoted PHP string that needs "\\$route",
    // or a single-quoted pattern as used here.
    $src = file_get_contents(__DIR__ . '/../lib/portal.php');
    preg_match_all('/(?:case|if\s*\(\s*\$route\s*===)\s*\x27(portal(?:\/[a-z0-9\-]*)?)\x27/',
                   $src, $m, PREG_OFFSET_CAPTURE);
    $marks = [];
    foreach ($m[1] as $hit) $marks[] = [$hit[1], $hit[0]];
    t_eq(count($marks), count($want),
         'the split finds every route the fixture lists (' . count($marks) . ') — fewer means a '
         . 'dispatch form was missed and the last arm is swallowing the gates below it');

    // The last arm runs to the end of the file, so a gate added BELOW the router
    // would be silently credited to it and this whole test would then be
    // asserting the wrong thing. The honest check is not a fixed count -- the
    // last route may legitimately gain or lose a gate of its own -- but that the
    // gates in that tail are EXACTLY the ones the fixture records for it.
    [$lastPos, $lastRoute] = $marks[count($marks) - 1];
    preg_match_all('/portal_need\(\x27([^\x27]+)\x27/', substr($src, $lastPos), $tg);
    $tailKeys = array_values(array_unique($tg[1])); sort($tailKeys);
    t_eq(implode(',', $tailKeys), $want[$lastRoute] ?? '',
         "nothing is gated below the router: the keys at or after the last route mark "
         . "(/$lastRoute) are exactly what the fixture records for it");
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
        $_SESSION['cuid'] = (int)db()->lastInsertId(); auth_bind_identity('cuid'); return $_SESSION['cuid'];
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

    // ---- 6. the VENDOR portal — the same two questions ---------------------
    // The assistant fix was written once, for a function both portals share.
    // That is a reason to test both, not a reason to test one: a shared
    // function can still behave differently per audience, because each takes a
    // different permission test and reads a different column.
    t_section('external portals — the vendor portal');
    unset($_SESSION['cuid']);

    $vfx = __DIR__ . '/fixtures/vendor_routes_gated.tsv';
    $vwant = [];
    foreach (file($vfx, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $pp = explode("\t", $line);
        $vwant[$pp[0]] = $pp[1] ?? '';
    }
    $vsrc = file_get_contents(__DIR__ . '/../lib/cvp.php');
    preg_match_all('/(?:case|if\s*\(\s*\$route\s*===)\s*\x27(vendor(?:\/[a-z0-9\-]*)?)\x27/',
                   $vsrc, $vm, PREG_OFFSET_CAPTURE);
    $vmarks = [];
    foreach ($vm[1] as $hit) $vmarks[] = [$hit[1], $hit[0]];
    t_eq(count($vmarks), count($vwant),
         'the vendor split finds every route the fixture lists (' . count($vmarks) . ')');
    $vgot = [];
    foreach ($vmarks as $i => [$pos, $route]) {
        $end = $vmarks[$i + 1][0] ?? strlen($vsrc);
        preg_match_all('/cvp_vendor_need\(\x27([^\x27]+)\x27/', substr($vsrc, $pos, $end - $pos), $g);
        $keys = array_values(array_unique($g[1])); sort($keys);
        $vgot[$route] = implode(',', $keys);
    }
    foreach ($vwant as $route => $keys) {
        if ($keys === '') continue;
        t_eq($vgot[$route] ?? '(route gone)', $keys,
             "/$route is still gated by cvp_vendor_need('" . str_replace(',', "','", $keys) . "')");
    }

    db()->prepare("INSERT INTO business_partners (legal_name,is_subcontractor,status,created_at)
                   VALUES ('Perm Test Vendor',1,'ACTIVE',?)")->execute([$now]);
    $vid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO report_docs (vendor_id,irn,title,type_code,result,release_status,
                   inspection_date,finalized,finalized_at,vendor_visible,rev,created_at)
                   VALUES (?,?,?,?,?,?,?,1,?,1,1,?)")
        ->execute([$vid, 'IRN/VPT/0001', 'Casting radiography', 'IR', 'FAIL',
                   'NOT_RELEASED', '2026-09-11', $now, $now]);

    // cvp_vendor_user() memoises on the signed-in id exactly as portal_user()
    // does, so each case needs a FRESH vendor user here too.
    $vSignIn = function (array $perms) use ($vid, $now) {
        static $n = 0; $n++;
        if (!$perms) throw new RuntimeException('an empty perms list means ALL perms');
        db()->prepare("INSERT INTO vendor_users (vendor_id,email,name,password_hash,is_active,perms,created_at)
                       VALUES (?,?,?,'x',1,?,?)")
            ->execute([$vid, "vpt$n@test", "VPT $n", implode(',', $perms), $now]);
        $_SESSION['vuid'] = (int)db()->lastInsertId(); auth_bind_identity('vuid'); return $_SESSION['vuid'];
    };

    $vSignIn(['qualification']);                     // neither reports nor issues
    t_ok(!vcan('reports') && !vcan('issues'),
         'a vendor user on qualification-only holds neither reports nor issues');
    t_eq(cvp_ai_scope_keys('VENDOR', 'vcan'), [], 'so they may read NO assistant section');
    $vctx = cvp_ai_scope_context('VENDOR', $vid, 'vcan');
    t_eq(strpos($vctx, 'IRN/VPT/0001'), false,
         'and the vendor assistant context holds no report of theirs');
    [$vans, $verr] = cvp_ai_answer('VENDOR', $vid, 'what were our results?', 'vcan');
    t_eq($vans, '', 'cvp_ai_answer() returns no answer on the vendor side either');
    t_ok(strpos($verr, 'does not include the records') !== false,
         'and it is the PERMISSION guard that refuses, not the AI-not-configured one');

    $vSignIn(['reports', 'qualification']);          // reports, but NOT issues
    t_eq(cvp_ai_scope_keys('VENDOR', 'vcan'), ['reports'],
         'a vendor granted reports reads the reports section alone');
    $vctx = cvp_ai_scope_context('VENDOR', $vid, 'vcan');
    t_ok(strpos($vctx, 'IRN/VPT/0001') !== false,
         'their own shared report IS in the context — the fix does not over-block');
    t_eq(strpos($vctx, 'NONCONFORMITIES'), false,
         'and the nonconformity section they were denied is NOT');

    unset($_SESSION['vuid']);
    unset($_SESSION['cuid']);
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
