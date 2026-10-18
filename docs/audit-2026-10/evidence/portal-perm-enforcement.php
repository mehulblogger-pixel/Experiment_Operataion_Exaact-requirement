<?php
/**
 * EXAACT audit — is every CLIENT PORTAL permission actually ENFORCED?
 *
 * lib/portal.php states its own rule at line 1745:
 *
 *     "Refuse rather than hide: hiding a link but leaving the address
 *      reachable is the bug this whole file exists to avoid."
 *
 * This script tests that rule route by route, and then tries the write.
 *
 * ---------------------------------------------------------------------------
 *  CORRECTED 2026-10-18, BEFORE PUBLICATION. The first version of this script
 *  printed "CONFIRMED BY EXECUTION" for a finding that it had not established,
 *  for two separate defects of my own:
 *
 *    1. It re-permissioned ONE user between probes. portal_user() memoises on
 *       the signed-in id (lib/portal.php:145) and returns early when the
 *       session id is empty -- WITHOUT updating its memo key -- so the
 *       cache-clearing trick of blanking the session silently failed and the
 *       user never actually lost a right. Every row printed "allowed".
 *    2. Its conclusion fired on "a row was created" alone, never asserting its
 *       own precondition that the permission was withheld. So it announced a
 *       permission bypass performed by a user who held the permission.
 *
 *  Both are fixed below: a FRESH portal user per probe (a new id is a
 *  guaranteed cache miss), and a conclusion that refuses to draw itself unless
 *  pcan() has first confirmed the right is denied. Recorded here rather than
 *  edited away, because the same two mistakes are easy to repeat.
 *
 *  Note while reading the output: an EMPTY stored permission list means ALL
 *  permissions (lib/portal.php:1707), so a probe user must always be given an
 *  explicit, non-empty list.
 * ---------------------------------------------------------------------------
 *
 * Read-only against a throwaway database. Creates nothing outside it.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/portal-perm-enforcement.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

portal_migrate();
$now = date('c');

db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at)
               VALUES ('Probe Client Ltd',1,'ACTIVE',?)")->execute([$now]);
$pid = (int)db()->lastInsertId();

/** A NEW portal user holding exactly $keys, signed in. A fresh id defeats the
 *  memo in portal_user(); re-permissioning one user does not. */
function probe_user_with(array $keys, $pid, $now) {
    if (!$keys) throw new RuntimeException('empty list means ALL perms — refusing');
    static $n = 0; $n++;
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,perms,created_at)
                   VALUES (?,?,?,'x',1,?,?)")
        ->execute([(int)$pid, "probe$n@client.test", "Probe $n", implode(',', $keys), $now]);
    $_SESSION['cuid'] = (int)db()->lastInsertId();
    return $_SESSION['cuid'];
}

// --- the router, cut into its case arms ------------------------------------
$src = file_get_contents($root . '/lib/portal.php');
$arms = []; $marks = [];
preg_match_all("/case '(portal(?:\/[a-z0-9\-]*)?)':/", $src, $m, PREG_OFFSET_CAPTURE);
foreach ($m[1] as $hit) $marks[] = [$hit[1], $hit[0]];
foreach ($marks as $i => [$pos, $route]) {
    $end = $marks[$i + 1][0] ?? strlen($src);
    $arms[$route] = substr($src, $pos, $end - $pos);
}

echo "CLIENT PORTAL — IS EACH PERMISSION ENFORCED AT THE ADDRESS?\n";
echo str_repeat('=', 78) . "\n";
printf("%-19s %-26s %-10s %s\n", 'PERMISSION', 'ROUTE', 'pcan SAYS', 'GATE WIRED IN?');

$unenforced = [];
foreach (array_keys(PORTAL_PERMS) as $key) {
    // Sign in as a user holding every right EXCEPT this one, and ask the gate.
    $others = array_values(array_diff(array_keys(PORTAL_PERMS), [$key]));
    probe_user_with($others, $pid, $now);
    $denied = !pcan($key);

    // A route BEARS this permission if its arm calls portal_need('<key>') --
    // the gate. A bare pcan('<key>') decides whether to DRAW a control and is
    // not a gate, so it is reported separately rather than counted as a hole.
    $gated = $shown = [];
    foreach ($arms as $route => $body) {
        if (strpos($body, "portal_need('$key'") !== false)      $gated[] = $route;
        elseif (strpos($body, "pcan('$key'") !== false)          $shown[] = $route;
    }
    $own = 'portal/' . str_replace('.', '-', $key);
    if (!$gated && !$shown && isset($arms[$own])) $shown[] = $own;   // named, ungated

    foreach ($gated as $route)
        printf("%-19s %-26s %-10s %s\n", $key, '/' . $route,
               $denied ? 'DENIED' : '** ALLOWED **', 'yes — portal_need()');
    foreach ($shown as $route) {
        $hasAnyGate = (bool)preg_match("/portal_need\('/", $arms[$route]);
        $note = $hasAnyGate
            ? 'draws a control only (route gated by another key)'
            : '** NO portal_need() ANYWHERE IN THIS HANDLER **';
        printf("%-19s %-26s %-10s %s\n", $key, '/' . $route,
               $denied ? 'DENIED' : '** ALLOWED **', $note);
        if ($denied && !$hasAnyGate) $unenforced[] = [$key, $route];
    }
    if (!$gated && !$shown)
        printf("%-19s %-26s %-10s %s\n", $key, '(no route of its own)',
               $denied ? 'DENIED' : '** ALLOWED **', 'checked inline where used');
}

echo str_repeat('=', 78) . "\n";
if (!$unenforced) echo "Every permission that names a route is gated at that route.\n";
else {
    echo "PERMISSIONS THE APPLICATION OFFERS BUT DOES NOT ENFORCE: " . count($unenforced) . "\n";
    foreach ($unenforced as [$k, $r])
        echo "   '$k' — pcan() withholds it, /$r has no gate at all\n";
}

// --- the write itself, with its precondition asserted first ----------------
echo "\nEXECUTING the write a withheld user should not be able to make\n";
echo str_repeat('-', 78) . "\n";
probe_user_with(PORTAL_PRESETS['ACCOUNTS']['perms'], $pid, $now);   // invoices, complaint
printf("Preset under test  : ACCOUNTS — \"%s\"\n", PORTAL_PRESETS['ACCOUNTS']['label']);
printf("Rights it carries  : %s\n", implode(', ', portal_perms()));
$granted = pcan('request');
printf("pcan('request')    : %s\n", $granted ? '** TRUE — precondition FAILED **' : 'FALSE — not granted');

if ($granted) {
    echo "\nABORTED. The probe user holds 'request', so nothing this script could\n";
    echo "do next would demonstrate a bypass. No conclusion is drawn.\n";
    exit(1);
}

$before = (int)ops_one("SELECT COUNT(*) c FROM portal_requests WHERE partner_id=?", [$pid])['c'];
$err = portal_request_create([
    'subject' => 'AUDIT PROBE — raised by a user without the request right',
    'detail'  => 'Created by docs/audit-2026-10/evidence/portal-perm-enforcement.php',
    'site'    => 'Probe site',
]);
$after = (int)ops_one("SELECT COUNT(*) c FROM portal_requests WHERE partner_id=?", [$pid])['c'];
printf("portal_request_create() returned: %s\n", $err === '' ? "'' (success)" : "'$err'");
printf("rows in portal_requests: %d -> %d\n", $before, $after);

if ($after > $before && $err === '') {
    echo "\nRESULT: CONFIRMED BY EXECUTION, precondition asserted.\n";
    echo "A portal user on the ACCOUNTS preset — invoices and complaints only,\n";
    echo "with pcan('request') answering FALSE — created a job request. The\n";
    echo "navigation link is correctly hidden (views/portal/top.php:26); the\n";
    echo "ADDRESS is not defended. This is the exact bug lib/portal.php:1745\n";
    echo "says the file exists to avoid.\n";
} else {
    echo "\nRESULT: the write was refused. The finding does NOT hold.\n";
}

// Every view that LINKS to /portal/request must put that link behind
// pcan('request') -- otherwise a user without the right is invited, in prose,
// to a door that will refuse them. Checked by looking for the guard in the
// lines around each link rather than on the link's own line, because the
// conditional usually opens a line or two above it.
echo "\nViews that link to /portal/request, and whether the link is guarded:\n";
$unguarded = 0;
foreach (['views/portal/invoices.php', 'views/portal/calls.php',
          'views/portal/dashboard.php', 'views/portal/top.php'] as $v) {
    $t = @file_get_contents($root . '/' . $v); if ($t === false) continue;
    $lines = explode("\n", $t);
    foreach ($lines as $n => $line) {
        if (strpos($line, '/portal/request') === false) continue;
        $window = implode("\n", array_slice($lines, max(0, $n - 4), 6));
        $guarded = strpos($window, "pcan('request')") !== false;
        if (!$guarded) $unguarded++;
        printf("   %-28s :%-4d %s\n", basename($v), $n + 1,
               $guarded ? 'guarded by pcan(\'request\')' : '** UNGUARDED **');
    }
}
printf("\nUnguarded invitations: %d%s\n", $unguarded,
       $unguarded ? '  <= a withheld user is invited to a door that refuses them' : '  (none)');
