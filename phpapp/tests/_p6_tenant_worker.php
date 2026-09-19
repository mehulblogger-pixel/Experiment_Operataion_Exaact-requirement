<?php
// ============================================================================
//  Phase 6 Batch 1 — the CROSS-TENANT isolation probe, in its own process.
//
//  Tenancy in this application is STRUCTURAL: one database per tenant, no
//  tenant column anywhere. That has always been argued from the architecture
//  and never tested, so invariant I15 stood at NOT ESTABLISHED. This process
//  tests it: it boots two real, separate tenant databases, creates records in
//  tenant B, then — while connected to tenant A — tries to build an identity
//  relationship out of B's record ids.
//
//  It runs in its own process because it switches the live connection, which
//  must never happen inside the shared suite run.
//
//    php tests/_p6_tenant_worker.php <A-dsn-spec> <B-dsn-spec>
//  where a spec is either  sqlite:/path/to/file  or  mysql:dbname
//  (mysql host/user/pass come from the ordinary DB_HOST/DB_USER/DB_PASS env).
//
//  Prints one JSON line. Every verdict is read back from the DATABASES, never
//  inferred from a return value.
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p6-tenant';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$specA = (string)($argv[1] ?? ''); $specB = (string)($argv[2] ?? '');
$uid   = (int)($argv[3] ?? 0);

/** Point the live connection at one tenant and build its schema. */
function tenant_use($spec) {
    [$kind, $where] = explode(':', $spec, 2);
    if ($kind === 'sqlite') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $where); }
    else { putenv('DB_DRIVER=mysql'); putenv('DB_NAME=' . $where); }
    db(true);              // drop the cached connection and bump the epoch, so
    db();                  // every run-once migration guard re-arms for THIS store
    boot();
}

$out = ['ok' => false, 'steps' => []];
try {
    // ---- tenant B: the other customer, with its own people ------------------
    tenant_use($specB);
    if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
    connect_identity_migrate();
    db()->prepare("INSERT INTO cx_professionals (name,email) VALUES ('Tenant B Person','tenantb@x.test')")->execute();
    $bPro  = (int)db()->lastInsertId();
    $bInsp = (int)team_member_create('Tenant B Person', 'FIELD', null, 'tenantb@x.test');
    db()->prepare("INSERT INTO candidates (first_name,last_name,email) VALUES ('Tenant','B','tenantb@x.test')")->execute();
    $bCand = (int)db()->lastInsertId();
    $bLinksBefore = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link");
    $out['steps']['b_pro'] = $bPro; $out['steps']['b_insp'] = $bInsp; $out['steps']['b_cand'] = $bCand;

    // ---- tenant A: a DIFFERENT customer ------------------------------------
    tenant_use($specA);
    if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
    connect_identity_migrate();
    // Empty A of any identity link so the count below is unambiguous.
    db()->exec("DELETE FROM cx_identity_link");
    $aLinksBefore = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link");

    // THE ATTACK: valid ids — in the OTHER tenant. A record id is not a passport.
    $r1 = connect_identity_link_create($bPro, $bInsp, 'manual', 'tenant-attacker');
    $r2 = connect_identity_candidate_link_create($bCand, $bPro, 'manual', 'tenant-attacker');
    $out['steps']['refused_inspector_axis'] = !$r1[0];
    $out['steps']['refused_candidate_axis'] = !$r2[0];
    $out['steps']['msg1'] = (string)$r1[1];
    $out['steps']['msg2'] = (string)$r2[1];
    $out['steps']['a_links_after'] = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link");
    $out['steps']['a_links_before'] = $aLinksBefore;
    // A must not have gained B's people either.
    $out['steps']['a_has_b_person'] = (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email='tenantb@x.test'");

    // ---- back to B: it must be untouched ------------------------------------
    tenant_use($specB);
    $out['steps']['b_links_after']  = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link");
    $out['steps']['b_links_before'] = $bLinksBefore;

    $out['ok'] = true;
} catch (Throwable $e) { $out['error'] = $e->getMessage(); }
echo json_encode($out) . "\n";
