<?php
/**
 * EXAACT audit — the MGH Licence Server's two money-adjacent guards.
 *
 * The webhook handler is well built (HMAC over the raw body, constant-time
 * compare, verified before parsing, event allow-list). This probes the two
 * things that sit behind that door:
 *
 *   1. REPLAY. Razorpay retries, so the same payment arrives twice. The guard is
 *      a SELECT COUNT followed by an INSERT, with nothing making the pair
 *      atomic and no UNIQUE constraint on ledger.reference. Two deliveries that
 *      overlap can both pass the count. This cannot be shown deterministically
 *      without threads, so what is shown is the precondition: the schema
 *      accepts the duplicate the guard exists to prevent.
 *   2. TERM. `months` is read from the payment's notes with no upper bound, and
 *      nothing checks that the amount paid corresponds to the months granted.
 *
 * Runs the REAL lib.php against a temp store — LICENCE_STORE is set first,
 * because on the command line the library otherwise writes its database one
 * level above itself, i.e. into this repository.
 *   php docs/audit-2026-10/evidence/licence-server-probe.php
 */
$tmp = sys_get_temp_dir() . '/ls-probe-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
putenv('LICENCE_STORE=' . $tmp);
require __DIR__ . '/../../../licence-server/lib.php';

$db = ls_db();
$db->exec("INSERT INTO customers (name, created_at) VALUES ('Probe Co', '" . ls_now() . "')");
$cid = (int)$db->lastInsertId();
$iid = ls_uuid();
$db->prepare("INSERT INTO installs (customer_id, install_id, expires_on, created_at) VALUES (?,?,?,?)")
   ->execute([$cid, $iid, date('Y-m-d', strtotime('+30 days')), ls_now()]);

echo "MGH LICENCE SERVER — REPLAY AND TERM\n" . str_repeat('=', 78) . "\n";

// ---- 1. replay precondition ---------------------------------------------
echo "\n1. Does the ledger accept the same payment reference twice?\n";
$ref = 'pay_PROBE_DUPLICATE';
$before = (string)ls_install($iid)['expires_on'];
$r1 = ls_extend($iid, 12, 'PAYMENT', 1000, $ref, 'first delivery');
$refused = false;
try { $r2 = ls_extend($iid, 12, 'PAYMENT', 1000, $ref, 'second delivery (replay)'); }
catch (Throwable $e) { $refused = function_exists('ls_is_duplicate_payment') && ls_is_duplicate_payment($e); if (!$refused) throw $e; }
printf("  second delivery of the same reference: %s\n", $refused ? 'REFUSED by the database' : 'accepted');
$n  = (int)$db->query("SELECT COUNT(*) FROM ledger WHERE reference='$ref' AND kind='PAYMENT'")->fetchColumn();
$after = (string)ls_install($iid)['expires_on'];
printf("  expiry before: %s\n  after two extends on ONE reference: %s   (ledger rows for it: %d)\n", $before, $after, $n);
$idx = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND sql LIKE '%UNIQUE%' AND tbl_name='ledger'")->fetchColumn();
printf("  UNIQUE constraint on ledger.reference: %s\n", $idx ? 'yes' : 'NONE');
$replay = ($n === 2);
echo $replay
  ? "  => The schema accepts the duplicate. api.php's COUNT-then-INSERT is the only\n     guard, and it is not atomic: two overlapping deliveries can both pass it.\n"
  : "  => A duplicate reference is refused at the schema.\n";

// ---- 2. unbounded term ----------------------------------------------------
echo "\n2. Is the number of months bounded?\n";
$iid2 = ls_uuid();
$db->prepare("INSERT INTO installs (customer_id, install_id, expires_on, created_at) VALUES (?,?,?,?)")
   ->execute([$cid, $iid2, date('Y-m-d'), ls_now()]);
$r = ls_extend($iid2, 1200, 'PAYMENT', 1, 'pay_ONE_RUPEE', 'notes said months=1200');
printf("  payment of Rs 1 with notes.months = 1200 -> %s\n", !empty($r['err']) ? 'REFUSED: ' . $r['err'] : 'ACCEPTED, expiry now ' . $r['now']);
$unbounded = empty($r['err']);
$r = ls_extend($iid2, 0, 'PAYMENT', 1, 'pay_ZERO', '');
printf("  months = 0  -> %s\n", !empty($r['err']) ? 'refused (correct): ' . $r['err'] : 'ACCEPTED');

echo "\n" . str_repeat('=', 78) . "\n";
echo "Context that MUST accompany this: the webhook is signature-verified, so an\n";
echo "outsider cannot forge either case. Replay needs Razorpay's own retry to\n";
echo "overlap; the term needs whoever creates the payment link to mistype the\n";
echo "notes. These are controls against mistakes and races, not against attackers.\n";
foreach (array_merge(glob("$tmp/*") ?: [], glob("$tmp/.*") ?: []) as $f) if (is_file($f)) @unlink($f);
@rmdir($tmp);
