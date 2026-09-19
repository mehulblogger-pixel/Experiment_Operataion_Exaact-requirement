<?php
// ============================================================================
//  A REAL separate process for the Phase 6 Batch 3 organisation probes.
//  Attaches to the SAME database as the parent; never uses tests/bootstrap.php.
//
//    php tests/_p6b3_worker.php <op> <a> <b> <target-epoch-ms> <uid>
//
//  Ops
//    join        a=unused b=json{name,email,gstin?}  — connect_org_register()
//    invite      a=partner b=email                   — portal_invite()
//    vinvite     a=vendor  b=email                   — the vendor-portal invite
//    rawacct     a=partner b=email                   — a raw INSERT, no guard at all
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p6b3';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $a = (int)($argv[2] ?? 0); $b = (string)($argv[3] ?? '');
$target = (float)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

// Pay the one-time per-process costs BEFORE the barrier (the Phase 4 rule).
try {
    db();
    if (function_exists('connect_org_migrate')) connect_org_migrate();
    if (function_exists('portal_migrate')) portal_migrate();
    if (function_exists('cvp_migrate')) cvp_migrate();
    if (function_exists('find_duplicate_partner')) find_duplicate_partner('warm', '', '', '', 0);
    ops_val("SELECT COUNT(*) FROM business_partners");
    ops_val("SELECT COUNT(*) FROM client_users");
} catch (Throwable $e) {}
if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$out = ['op' => $op, 'ok' => false, 'code' => '', 'msg' => ''];
try {
    if ($op === 'join') {
        $in = json_decode($b, true) ?: [];
        $r = connect_org_register([
            'name' => (string)($in['name'] ?? ''), 'org_type' => 'COMPANY',
            'contact_email' => (string)($in['email'] ?? ''), 'contact_name' => 'Worker',
            'gstin' => (string)($in['gstin'] ?? ''), 'password' => 'password123']);
        $out['ok'] = (bool)$r[0]; $out['msg'] = (string)$r[1];
        $out['code'] = is_array($r[2] ?? null) ? (string)($r[2]['code'] ?? '') : '';
    } elseif ($op === 'invite') {
        $r = function_exists('portal_invite') ? portal_invite($a, $b, 'Worker', 0) : ['err' => 'absent'];
        $out['ok'] = empty($r['err']); $out['msg'] = (string)($r['err'] ?? '');
    } elseif ($op === 'rawacct') {
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at) VALUES (?,?,?,?,1,?)")
            ->execute([$a, $b, 'Raw', password_hash('x', PASSWORD_DEFAULT), date('c')]);
        $out['ok'] = true;
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
