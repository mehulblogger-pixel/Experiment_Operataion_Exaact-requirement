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
//    setprimary  a=partner b=contact name             — partner_contact_add(), primary
//    rawprimary  a=partner b=contact name             — a raw INSERT claiming primary,
//                                                       bypassing every PHP guard there is
//    txddl       a=unused  b=marker name              — the §7 DDL-inside-a-borrowed-
//                                                       transaction invariant, behaviourally
//    guardrace   a=unused  b=unused                   — a concurrent boot installing the
//                                                       guard, for the losing-ALTER path
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
    //  'guardrace' is the one op that must NOT warm this up: it exists to race the
    //  installation itself, and warming it here would put the key back before the
    //  barrier and leave the probe with nothing to race — the "no valid subject"
    //  failure this batch keeps finding.
    if ($op !== 'guardrace' && function_exists('partner_contact_migrate')) partner_contact_migrate();
    ops_val("SELECT COUNT(*) FROM partner_contacts");
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
    } elseif ($op === 'setprimary') {
        //  The ordinary way a person is made the main contact.
        $id = partner_contact_add($a, ['name' => $b, 'is_primary' => 1]);
        $out['ok'] = $id > 0; $out['code'] = (string)$id;
    } elseif ($op === 'rawprimary') {
        //  Straight to the table, claiming to be primary, with no application
        //  code involved at all. If the rule only lives in PHP this wins.
        db()->prepare("INSERT INTO partner_contacts (partner_id,name,is_primary) VALUES (?,?,1)")->execute([$a, $b]);
        $out['ok'] = true;
    } elseif ($op === 'txddl') {
        //  §7 — the invariant, tested by BEHAVIOUR rather than by index names.
        //
        //  MariaDB commits implicitly on ANY DDL. A migration that runs DDL inside
        //  a transaction it did not open therefore commits the caller's
        //  half-written business data with it, and a later rollback cannot take it
        //  back. That is the whole danger, and it is invisible to a probe that
        //  compares index lists.
        //
        //  This runs in a FRESH PROCESS on purpose: partner_contact_migrate() keeps
        //  a static epoch marker, so in the parent it returns immediately and the
        //  guarded path is never entered — which is exactly why the first version
        //  of this probe had no subject and let mutant CM6 survive.
        try { db()->exec(db_driver() === 'sqlite'
                ? "DROP INDEX uq_pcont_primary"
                : "DROP INDEX uq_pcont_primary ON partner_contacts"); } catch (Throwable $e) {}
        try { db()->exec("ALTER TABLE partner_contacts DROP COLUMN uq_primary"); } catch (Throwable $e) {}
        $hadWork = !in_array('uq_primary', table_columns_incl_generated('partner_contacts'), true);

        db()->beginTransaction();
        db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at)
                       VALUES (?,?,?,1,'ACTIVE',?)")->execute(['TXD', $b, $b, date('c')]);
        partner_contact_migrate();                      // invited in, inside somebody else's transaction
        try { db()->rollBack(); } catch (Throwable $e) {}

        $survived = (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name=?", [$b]);
        $out['ok']   = ($survived === 0);
        $out['code'] = ($hadWork ? 'WORK' : 'NOWORK') . ':' . $survived;
        $out['msg']  = $hadWork ? 'the migration had real DDL to do'
                                : 'NO SUBJECT — the protection was still present, so nothing would have run';
    } elseif ($op === 'guardrace') {
        //  The losing side of a concurrent boot. Both processes find the key
        //  missing, both ALTER, one loses with "duplicate column" — and the loser
        //  must ASK THE SCHEMA rather than believe the error, because its column
        //  is present all the same and the index behind it still has to be built.
        //  Reports what it saw on entry, so the parent can prove the race happened.
        $missingAtEntry = !in_array('uq_primary', table_columns_incl_generated('partner_contacts'), true);
        $r = ensure_unique_generated_index('partner_contacts', 'uq_primary', PARTNER_PRIMARY_KEY_EXPR,
                                           'uq_pcont_primary', 'partner_contact_reconcile_primaries');
        $out['ok']   = ($r === 'OK');
        $out['code'] = (string)$r;
        $out['msg']  = $missingAtEntry ? 'saw the key missing on entry' : 'the key was already there on entry';
    } elseif ($op === 'rawacct') {
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at) VALUES (?,?,?,?,1,?)")
            ->execute([$a, $b, 'Raw', password_hash('x', PASSWORD_DEFAULT), date('c')]);
        $out['ok'] = true;
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
