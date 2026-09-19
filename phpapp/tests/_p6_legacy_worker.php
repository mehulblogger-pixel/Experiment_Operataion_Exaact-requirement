<?php
// ============================================================================
//  Phase 6 · Batch 1 — the LEGACY DUPLICATE probe, in its own database.
//
//  A live install may already carry two live rows describing the same person. A
//  UNIQUE index cannot be built over that, and failing the boot for data we
//  found there would be worse than the gap. The required behaviour is: skip
//  that one index, keep BOTH rows exactly as they are, report them — and build
//  the index on a later boot once a person has resolved it.
//
//  It runs in its own process and its own database because it must plant the
//  duplicates BEFORE the migration has ever run.
//
//    php tests/_p6_legacy_worker.php <spec>      spec = sqlite:/path | mysql:dbname
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p6-legacy';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

[$kind, $where] = explode(':', (string)($argv[1] ?? 'sqlite:'), 2);
if ($kind === 'sqlite') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $where); }
else { putenv('DB_DRIVER=mysql'); putenv('DB_NAME=' . $where); }

$out = ['ok' => false];
try {
    db(true); db(); boot();
    $suid = (int)(ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: 0);
    if ($suid) { $_SESSION['uid'] = $suid; current_user(true); ua(true); }

    // Undo Batch 1's protection so this database looks like one that pre-dates it.
    foreach (CXID_UNIQUE as $col => $ixName) { try { db()->exec("DROP INDEX $ixName ON cx_identity_link"); } catch (Throwable $e) {} }
    foreach (CXID_UNIQUE as $col => $ixName) { try { db()->exec("DROP INDEX $ixName"); } catch (Throwable $e) {} }

    db()->prepare("INSERT INTO cx_professionals (name,email) VALUES ('Legacy Person','legacy@x.test')")->execute();
    $pro = (int)db()->lastInsertId();
    $insp1 = (int)team_member_create('Legacy Person', 'FIELD', null, 'legacy@x.test');
    $insp2 = (int)team_member_create('Legacy Person (again)', 'FIELD', null, 'legacy@x.test');
    // TWO live inspector-axis rows for ONE professional — exactly what U1 forbids
    // and exactly what a database written before U1 existed may contain.
    foreach ([$insp1, $insp2] as $i)
        db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,candidate_id,method,status,linked_by,linked_at,uq_pro_insp,uq_insp,uq_cand)
                       VALUES (?,?,0,'legacy','LINKED','legacy',?,?,?,NULL)")
            ->execute([$pro, $i, date('c'), $pro, $i]);
    $out['planted'] = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$pro]);

    // Re-run the migration exactly as a deploy would.
    db(true); db();
    connect_identity_migrate();

    $out['rows_after']  = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$pro]);
    $out['dups']        = count(connect_identity_duplicates());
    $out['index_built'] = in_array('ux_cx_idlink_pro_insp', array_map('strval', idx_names()), true);
    // The OTHER two indexes must still have been built — one axis's mess must not
    // leave the others unprotected.
    $out['other_built'] = in_array('ux_cx_idlink_cand', array_map('strval', idx_names()), true);
    $out['booted']      = true;

    // Resolve it the way a person would — by unlinking one — then migrate again.
    $victim = (int)ops_val("SELECT MAX(id) FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$pro]);
    connect_identity_unlink($victim, 'legacy-probe');
    db(true); db(); connect_identity_migrate();
    $out['dups_after_fix']  = count(connect_identity_duplicates());
    $out['index_after_fix'] = in_array('ux_cx_idlink_pro_insp', array_map('strval', idx_names()), true);
    $out['ok'] = true;
} catch (Throwable $e) { $out['error'] = $e->getMessage(); }
echo json_encode($out) . "\n";

function idx_names() {
    try {
        if ((string)db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite')
            return array_column(ops_all("SELECT name FROM sqlite_master WHERE type='index'") ?: [], 'name');
        return array_column(ops_all("SELECT DISTINCT index_name AS name FROM information_schema.statistics
                                     WHERE table_schema=DATABASE() AND table_name='cx_identity_link'") ?: [], 'name');
    } catch (Throwable $e) { return []; }
}
