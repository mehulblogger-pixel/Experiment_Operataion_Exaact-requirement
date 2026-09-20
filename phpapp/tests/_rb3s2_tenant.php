<?php
// ============================================================================
//  RB-3 Step 2 — the CROSS-TENANT probe, in its own process.
//
//  Tenancy here is STRUCTURAL: one database per tenant, no tenant column. This
//  boots two real, separate tenant databases and asks two questions that a
//  filter could never answer honestly:
//
//    X12  can tenant A's duplicate check SEE tenant B's staff?
//    X13  can an acknowledgement signed in tenant B authorise a hire in A?
//
//  It runs in its own process because it switches the live connection.
//
//    php tests/_rb3s2_tenant.php <A-spec> <B-spec> <uid>
//  where a spec is  sqlite:/path/file  or  mysql:dbname
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3s2-tenant';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$specA = (string)($argv[1] ?? ''); $specB = (string)($argv[2] ?? '');

function tenant_use($spec) {
    [$kind, $where] = explode(':', $spec, 2);
    if ($kind === 'sqlite') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $where); }
    else { putenv('DB_DRIVER=mysql'); putenv('DB_NAME=' . $where); }
    db(true); db(); boot();
}
function as_admin() {
    $u = ops_one("SELECT * FROM users WHERE is_superuser=1 ORDER BY id LIMIT 1");
    if ($u) { $_SESSION['uid'] = (int)$u['id']; current_user(true); ua(true); }
    return (int)($u['id'] ?? 0);
}
$MOB = '9765432100'; $EML = 'shared.person@tenant.test';

$out = ['ok' => false, 'steps' => []];
try {
    // ---- tenant B: another customer, whose staff share these details --------
    tenant_use($specB);
    $uidB = as_admin();
    $bInsp = (int)team_member_create('Tenant B Twin', 'FIELD', null, $EML);
    db()->prepare("UPDATE inspectors SET mobile=? WHERE id=?")->execute([$MOB, $bInsp]);
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,created_at) VALUES ('Tenant','B',?,?, 'ACCEPTED','IND',?)")
        ->execute([$EML, $MOB, date('c')]);
    $bCand = (int)db()->lastInsertId();
    $bCandRow = ops_one("SELECT * FROM candidates WHERE id=?", [$bCand]);
    $bMatch = workforce_matches($bCandRow);
    $out['steps']['b_strong'] = count(workforce_strong_matches($bMatch));
    //  A token B's recruiter could legitimately use inside B.
    $bToken = workforce_ack_issue($bCand, $bMatch, $bCandRow, $uidB);
    $out['steps']['b_token_issued'] = $bToken !== '';

    // ---- tenant A: a DIFFERENT customer, with NOBODY of that description ----
    tenant_use($specA);
    $uidA = as_admin();
    $off = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
    db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$off, $uidA]);
    //  A's own staff deliberately DO NOT share the details.
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,created_at) VALUES ('Tenant','A',?,?, 'ACCEPTED','IND',?)")
        ->execute([$EML, $MOB, date('c')]);
    $aCand = (int)db()->lastInsertId();
    $aCandRow = ops_one("SELECT * FROM candidates WHERE id=?", [$aCand]);
    $aMatch = workforce_matches($aCandRow);
    //  X12 — B's twin must be invisible here. Not filtered out: unreachable.
    $out['steps']['a_strong'] = count(workforce_strong_matches($aMatch));

    //  X13 — now give A a twin of its own, so an acknowledgement IS required,
    //  then offer B's token. It must not authorise anything.
    $aInsp = (int)team_member_create('Tenant A Twin', 'FIELD', null, $EML);
    db()->prepare("UPDATE inspectors SET mobile=? WHERE id=?")->execute([$MOB, $aInsp]);
    $aMatch2 = workforce_matches(ops_one("SELECT * FROM candidates WHERE id=?", [$aCand]));
    $out['steps']['a_strong_after'] = count(workforce_strong_matches($aMatch2));
    $rB = rcv_convert($aCand, ['dup_ack' => $bToken, 'actor_id' => $uidA]);
    $out['steps']['foreign_token_code'] = (string)($rB['code'] ?? '');
    $out['steps']['a_converted_by_foreign_token'] = (int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$aCand]);
    //  …and A's own token still works, so the refusal above was about the token
    //  and not about the gate being broken.
    $aToken = workforce_ack_issue($aCand, $aMatch2, ops_one("SELECT * FROM candidates WHERE id=?", [$aCand]), $uidA);
    $rA = rcv_convert($aCand, ['dup_ack' => $aToken, 'actor_id' => $uidA]);
    $out['steps']['own_token_code'] = (string)($rA['code'] ?? '');
    $out['ok'] = true;
} catch (Throwable $e) { $out['error'] = $e->getMessage(); }
echo json_encode($out) . "\n";
