<?php
// ============================================================================
//  The owner's rule D-01 (20 Oct 2026): "Sales executive, sales manager and
//  anyone who is assigned to do the job" work leads and deals.
//
//  Before it, only the Master Admin, Admin and Business Director could open
//  Leads, Opportunities or Pipelines; all four sales roles were refused, and
//  Finance — the one role that registers contracts — was refused the door that
//  registers one for a deal won without a quotation (Sales audit SA-11). The
//  Operation Manager, whom the matrix makes an endorser, could not endorse a
//  contract opening because the route sat behind the Quotes module (SA-20).
//  Each is driven through the real dispatcher as the real role.
// ============================================================================
t_section('Sales — D-01: the people who sell can open the pipeline');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('D01 Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};

// ---- the defaults ---------------------------------------------------------------
foreach (SALES_ROLES as $r) {
    $p = role_perms($r);
    t_ok(in_array('mod.leads.view', $p, true) && in_array(perm_verb_key('leads', 'add'), $p, true), "$r opens Leads and can add a lead");
    t_ok(!in_array(perm_verb_key('leads', 'delete'), $p, true), "…$r cannot delete one (that stays with management)");
}
foreach (['INSPECTOR', 'COORDINATOR', 'FINANCE'] as $r)
    t_ok(!in_array('mod.leads.view', role_perms($r), true), "$r is not given Leads by this rule (only the people who sell, or whoever the company assigns)");

// ---- the screens, as each role ----------------------------------------------------
$ME  = $mk('d01_me',  'MARKETING_EXECUTIVE');
$MM  = $mk('d01_mm',  'MARKETING_MANAGER');
$INS = $mk('d01_ins', 'INSPECTOR');
$FIN = $mk('d01_fin', 'FINANCE');
$OM  = $mk('d01_om',  'OPERATION_MANAGER');
foreach (['leads' => '+ New lead', 'opportunities' => 'New opportunity'] as $route => $mark) {
    $r = $drive($ME, $route, 'GET', [], [], [$mark]);
    t_ok($r['done'] === 'return' && !empty($r['has'][$mark]), "a Marketing Executive opens /$route (" . ($r['error'] ?? 'ok') . ')');
    $r = $drive($INS, $route, 'GET', [], [], [$mark]);
    t_ok(empty($r['has'][$mark]), "control: an inspector is still refused /$route");
}
$before = (int) ops_val("SELECT COUNT(*) FROM leads");
$drive($ME, 'lead-new', 'POST', [], ['company_name' => 'D01 Prospect Pvt Ltd', 'contact_name' => 'Asha']);
t_eq((int) ops_val("SELECT COUNT(*) FROM leads"), $before + 1, 'a Marketing Executive records a new lead');

// ---- configuring the pipelines stays company setup -----------------------------------
$r = $drive($MM, 'pipeline-save', 'POST', [], ['name' => 'D01 changed funnel']);
t_ok(strpos(implode(' ', array_map(fn($f) => $f['text'], $r['flash'] ?? [])), 'cannot change the pipelines') !== false
     || (int) ops_val("SELECT COUNT(*) FROM pipelines WHERE name='D01 changed funnel'") === 0,
     'working leads does not carry the right to change the company\'s pipeline stages');

// ---- the two contract doors ------------------------------------------------------------
$r = $drive($FIN, 'opportunity-contract', 'POST', [], ['id' => 0]);
t_ok(!empty($r['flash']), 'Finance reaches the "register the contract for a won deal" handler (it is no longer behind the Leads module)');
$r = $drive($INS, 'opportunity-contract', 'POST', [], ['id' => 0]);
t_ok(empty($r['flash']), 'control: an inspector is still turned away at the door');
$r = $drive($OM, 'contract-open', 'POST', ['id' => 0], ['do' => 'endorse']);
t_ok($r['done'] !== 'threw', 'the Operation Manager reaches the contract-opening handler, whose own endorse/approve checks decide');
t_ok(!in_array('mod.quotes.view', role_perms('OPERATION_MANAGER'), true) && in_array('mod.calls.view', role_perms('OPERATION_MANAGER'), true),
     '…through the work-order module it already has, not by being given Quotes');

// ---- a grid an administrator already saved gets the rule once ------------------------------
$ra0 = setting_get('role_access', ''); $ap0 = setting_get('access_owner_grants_applied', '');
try {
    setting_set('role_access', json_encode(['MARKETING_EXECUTIVE' => ['mod.quotes.view'], 'INSPECTOR' => ['mod.idems.view']]));
    setting_set('access_owner_grants_applied', 'Q-B1-2026-10-19,Q-G-2026-10-19,Q-K-2026-10-19');
    t_eq(access_apply_owner_grants(true), ['D-01-2026-10-20'], 'only the new rule is applied to a company that already had the older ones');
    $st = json_decode(setting_get('role_access', ''), true);
    t_ok(in_array('mod.leads.view', $st['MARKETING_EXECUTIVE'], true), 'the saved Marketing Executive grid now opens Leads');
    t_eq($st['INSPECTOR'], ['mod.idems.view'], 'the saved Inspector grid is untouched');
} finally {
    setting_set('role_access', $ra0); setting_set('access_owner_grants_applied', $ap0);
}
