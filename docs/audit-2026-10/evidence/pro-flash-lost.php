<?php
/**
 * EXAACT audit — the PROFESSIONAL portal writes messages nobody ever reads.
 *
 * lib/connect_pro.php writes $_SESSION['pro_flash'] in ten places. Among them
 * are a failed payment ("That payment is no longer available."), the error
 * branch of starting a subscription, and a failed rating report. If no view
 * renders that key, a professional whose payment fails is told NOTHING.
 *
 * This renders a real /pro screen with the flash set, and reports whether the
 * message appears in the HTML the professional would receive.
 *
 * Read-only against a throwaway database.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/pro-flash-lost.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

if (function_exists('connect_pro_migrate')) connect_pro_migrate();
$now = date('c');
db()->prepare("INSERT INTO cx_professionals (name,email,mobile,password_hash,is_active,created_at)
               VALUES ('Flash Probe','flash@pro.test','9000000001','x',1,?)")->execute([$now]);
$pid = (int)db()->lastInsertId();
$_SESSION['cxpid'] = $pid;
$me = connect_pro_user();
if (!$me) { echo "ABORTED — could not sign in.\n"; exit(1); }

// ---- who writes this key, and who reads it ------------------------------
$lib = file_get_contents($root . '/lib/connect_pro.php');
$writes = preg_match_all("/\\\$_SESSION\['pro_flash'\]\s*=/", $lib);
$readers = [];
foreach (glob($root . '/views/pro/*.php') as $v)
    if (strpos(file_get_contents($v), 'pro_flash') !== false) $readers[] = basename($v);
foreach (glob($root . '/lib/*.php') as $v) {
    $t = file_get_contents($v);
    if (preg_match("/\\\$_SESSION\['pro_flash'\]\s*(?!=)/", $t) || strpos($t, "pro_flash'] ??") !== false)
        $readers[] = 'lib/' . basename($v);
}

echo "THE PROFESSIONAL PORTAL'S FLASH CHANNEL\n";
echo str_repeat('=', 78) . "\n";
printf("Writes to \$_SESSION['pro_flash'] in lib/connect_pro.php : %d\n", $writes);
printf("Views or libs that READ it                             : %s\n\n",
       $readers ? implode(', ', array_unique($readers)) : '(none found)');

// ---- render a real screen with the flash set ----------------------------
$MSG = 'PROBE-MARKER-9f3a: That payment is no longer available.';
$_SESSION['pro_flash'] = $MSG;

ob_start();
try { connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); }
catch (Throwable $e) { echo "\n(render threw: " . $e->getMessage() . ")\n"; }
$html = (string)ob_get_clean();

printf("Rendered /pro dashboard: %d bytes of HTML\n", strlen($html));
$shown = strpos($html, 'PROBE-MARKER-9f3a') !== false;
printf("Does the professional see the message? %s\n", $shown ? 'YES' : 'NO');
$stillSet = !empty($_SESSION['pro_flash']);
printf("Is it still sitting in the session afterwards? %s\n", $stillSet ? 'YES' : 'no');

echo "\n" . str_repeat('-', 78) . "\n";
if (!$shown) {
    echo "RESULT: CONFIRMED BY EXECUTION.\n";
    echo "The message was set and the page was rendered, and it does not appear.\n";
    echo "A professional whose payment fails, whose subscription cannot start, or\n";
    echo "whose rating report is refused is told NOTHING AT ALL -- the explanation\n";
    echo "is written to the session and discarded.";
    if ($stillSet) echo "\nWorse: it is never cleared either, so it accumulates in the session.";
    echo "\n\nFor comparison, the two other portals DO render their channel:\n";
    echo "  views/portal/top.php  reads portal_flash\n";
    echo "  views/vendor/top.php  reads vendor_flash\n";
    echo "The professional portal is the one that was missed.\n";
} else {
    echo "RESULT: the message IS shown. The finding does NOT hold.\n";
}
