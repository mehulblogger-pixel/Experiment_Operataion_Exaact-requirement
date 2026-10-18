<?php
/**
 * EXAACT audit — the VENDOR portal, same two questions as the client portal.
 *
 * The client-portal probe (portal-perm-enforcement.php) found one permission
 * offered and not enforced, and the assistant probe
 * (portal-assistant-scope.php) found the assistant reading records its user had
 * been refused. The fix for the second was written to cover BOTH portals. This
 * script is the execution that checks it actually does, rather than taking the
 * fix's word for it -- the vendor side had only ever been reasoned about.
 *
 * Four vendor permissions (VENDOR_PERMS): reports, issues, qualification,
 * market.apply.
 *
 * Note while reading the output: as on the client side, an EMPTY stored
 * permission list means ALL permissions (lib/cvp.php:161), so a probe user must
 * always be given an explicit, non-empty list -- and cvp_vendor_user()
 * memoises on the signed-in id, so each case needs a FRESH user, not a
 * re-permissioned one.
 *
 * Read-only against a throwaway database.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/portal-perm-enforcement-vendor.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

if (function_exists('cvp_migrate')) cvp_migrate();
$now = date('c');

db()->prepare("INSERT INTO business_partners (legal_name,is_subcontractor,status,created_at)
               VALUES ('Probe Vendor Pvt Ltd',1,'ACTIVE',?)")->execute([$now]);
$vid = (int)db()->lastInsertId();

function vendor_signin(array $perms, $vid, $now) {
    if (!$perms) throw new RuntimeException('an empty perms list means ALL perms');
    static $n = 0; $n++;
    db()->prepare("INSERT INTO vendor_users (vendor_id,email,name,password_hash,is_active,perms,created_at)
                   VALUES (?,?,?,'x',1,?,?)")
        ->execute([(int)$vid, "pv$n@vendor.test", "PV $n", implode(',', $perms), $now]);
    return $_SESSION['vuid'] = (int)db()->lastInsertId();
}

// ---- the vendor router, cut into its arms ---------------------------------
//  NOTE, and it cost a wrong result before it was caught: the dollar in
//  `$route` must reach PCRE *escaped*. Written "\$route" inside a
//  double-quoted PHP string, PHP collapses it to `$route` and PCRE reads the
//  `$` as an end-of-line anchor, so the `if ($route === …)` dispatch form never
//  matches at all. Only the `case` arms are then found, the last of them runs
//  to the end of the file, and it is credited with every gate below it -- which
//  is how an earlier run of this script reported `issues` as being enforced at
//  `/vendor/password`. It needs "\\$route" here, or a single-quoted pattern.
$src = file_get_contents($root . '/lib/cvp.php');
$arms = []; $marks = [];
preg_match_all('/(?:case|if\s*\(\s*\$route\s*===)\s*\x27(vendor(?:\/[a-z0-9\-]*)?)\x27/',
               $src, $m, PREG_OFFSET_CAPTURE);
foreach ($m[1] as $hit) $marks[] = [$hit[1], $hit[0]];
foreach ($marks as $i => [$pos, $route]) {
    $end = $marks[$i + 1][0] ?? strlen($src);
    $arms[$route] = substr($src, $pos, $end - $pos);
}
printf("Router arms found: %d  (13 expected -- fewer means the dispatch regex\n"
     . "missed a form, and the last arm is silently swallowing the gates below it)\n\n",
       count($arms));
// The last arm runs to the end of the file, so any gate after the final route
// mark would be credited to it. Say so rather than let it pass unseen.
$lastPos = $marks ? $marks[count($marks) - 1][0] : 0;
$tail = substr($src, $lastPos);
$tailNeeds = preg_match_all('/cvp_vendor_need\(\x27([^\x27]+)\x27/', $tail, $tg);
if ($tailNeeds > 1)
    printf("CAUTION: %d gate(s) sit after the last route mark (%s) and are credited\n"
         . "to it by construction. Verify them by hand before trusting those rows.\n\n",
           $tailNeeds, implode(', ', array_unique($tg[1])));

echo "VENDOR PORTAL — IS EACH PERMISSION ENFORCED AT THE ADDRESS?\n";
echo str_repeat('=', 78) . "\n";
printf("%-16s %-26s %-10s %s\n", 'PERMISSION', 'ROUTE', 'vcan SAYS', 'GATE WIRED IN?');

$unenforced = [];
foreach (array_keys(VENDOR_PERMS) as $key) {
    $others = array_values(array_diff(array_keys(VENDOR_PERMS), [$key]));
    vendor_signin($others, $vid, $now);
    $denied = !vcan($key);

    $gated = $shown = [];
    foreach ($arms as $route => $body) {
        if (strpos($body, "cvp_vendor_need('$key'") !== false) $gated[] = $route;
        elseif (strpos($body, "vcan('$key'") !== false)        $shown[] = $route;
    }
    foreach ($gated as $route)
        printf("%-16s %-26s %-10s %s\n", $key, '/' . $route,
               $denied ? 'DENIED' : '** ALLOWED **', 'yes — cvp_vendor_need()');
    foreach ($shown as $route) {
        $hasAnyGate = strpos($arms[$route], "cvp_vendor_need('") !== false;
        printf("%-16s %-26s %-10s %s\n", $key, '/' . $route,
               $denied ? 'DENIED' : '** ALLOWED **',
               $hasAnyGate ? 'draws a control only (route gated by another key)'
                           : '** NO cvp_vendor_need() IN THIS HANDLER **');
        if ($denied && !$hasAnyGate) $unenforced[] = [$key, $route];
    }
    if (!$gated && !$shown)
        printf("%-16s %-26s %-10s %s\n", $key, '(no route of its own)',
               $denied ? 'DENIED' : '** ALLOWED **', 'checked inline where used');
}
echo str_repeat('=', 78) . "\n";
echo $unenforced
    ? "PERMISSIONS OFFERED BUT NOT ENFORCED: " . count($unenforced) . "\n"
      . implode('', array_map(fn($u) => "   '$u[0]' — vcan() withholds it, /$u[1] has no gate\n", $unenforced))
    : "Every vendor permission that names a route is gated at that route.\n";

// ---- the assistant, on the vendor side ------------------------------------
echo "\nTHE VENDOR ASSISTANT — is it bound by the vendor user's permissions?\n";
echo str_repeat('-', 78) . "\n";

// A finalised report shared with this vendor: what a vendor user denied
// 'reports' must not reach by asking the assistant instead.
db()->prepare("INSERT INTO report_docs (vendor_id,irn,title,type_code,result,release_status,
               inspection_date,finalized,finalized_at,vendor_visible,rev,created_at)
               VALUES (?,?,?,?,?,?,?,1,?,1,1,?)")
    ->execute([$vid, 'IRN/VPROBE/0001', 'Casting radiography', 'IR', 'FAIL',
               'NOT_RELEASED', '2026-09-11', $now, $now]);

// A vendor user who may see their qualification status and nothing else.
vendor_signin(['qualification'], $vid, $now);
printf("Signed in with     : %s\n", implode(', ', cvp_vendor_perms()));
foreach (['reports' => '/vendor/reports', 'issues' => '/vendor/issues'] as $k => $route) {
    printf("   vcan('%-9s) = %-5s  -> %s %s\n", $k . "'", vcan($k) ? 'true' : 'false',
           $route, vcan($k) ? '(allowed)' : 'REFUSED by cvp_vendor_need()');
}
if (vcan('reports') || vcan('issues')) {
    echo "\nABORTED — the probe user holds reports or issues. No conclusion drawn.\n";
    exit(1);
}

$ctx = cvp_ai_scope_context('VENDOR', $vid, 'vcan');
echo "\nWhat cvp_ai_scope_context('VENDOR', …) hands the assistant:\n";
echo str_repeat('-', 78) . "\n" . ($ctx === '' ? '(nothing)' : $ctx) . "\n" . str_repeat('-', 78) . "\n";
printf("cvp_ai_scope_keys('VENDOR','vcan') = [%s]\n", implode(', ', cvp_ai_scope_keys('VENDOR', 'vcan')));
[$ans, $aerr] = cvp_ai_answer('VENDOR', $vid, 'what were our results?', 'vcan');
printf("cvp_ai_answer() -> answer=%s  message=%s\n", $ans === '' ? "''" : '(an answer!)', $aerr);

$leak = strpos($ctx, 'IRN/VPROBE/0001') !== false || strpos($ctx, 'NONCONFORMITIES') !== false;
echo $leak
    ? "\nRESULT: LEAK. A vendor user denied reports and nonconformities is still\n"
      . "handed them by the assistant. The client-side fix does NOT cover the vendor side.\n"
    : "\nRESULT: CLOSED, precondition asserted. A vendor user denied reports and\n"
      . "nonconformities is handed neither, and is told why by the permission guard.\n"
      . "The fix does cover the vendor side -- now established by execution, not by\n"
      . "assuming the shared function behaves the same for both audiences.\n";

// And the control case: a vendor user who IS granted reports still gets them.
vendor_signin(['reports', 'qualification'], $vid, $now);
$ctx2 = cvp_ai_scope_context('VENDOR', $vid, 'vcan');
printf("\nControl — a vendor user granted 'reports': own report present? %s · nonconformity section? %s\n",
       strpos($ctx2, 'IRN/VPROBE/0001') !== false ? 'yes (correct)' : 'NO — the fix over-blocks',
       strpos($ctx2, 'NONCONFORMITIES') !== false ? 'yes — WRONG, they were not granted issues' : 'no (correct)');
