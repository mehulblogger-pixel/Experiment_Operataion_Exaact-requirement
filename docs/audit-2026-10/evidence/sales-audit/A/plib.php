<?php
// Shared parent harness for the Sales-A lifecycle probes. Boots the REAL app on a
// throwaway SQLite DB (tests/bootstrap.php) and dispatches real routes in a
// subprocess (w.php) as a named user. Run with TMPDIR pointed at this folder.
$root = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
crm_migrate(); if (function_exists('leads_migrate')) leads_migrate(); if (function_exists('opp_migrate')) opp_migrate();
if (function_exists('pc_migrate')) pc_migrate();
$GLOBALS['W'] = __DIR__ . '/w.php';

function mkuser($username, $role, $office, $super = 0) {
    db()->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser, home_office_id, email) VALUES (?,?,?,?,1,?,?,?)")
        ->execute([$username, $username, 'Probe', $role, $super, $office, $username . '@probe.invalid']);
    return (int)db()->lastInsertId();
}
db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Probe HQ',1)")->execute();
$GLOBALS['OFF'] = $OFF = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state) VALUES ('Acme Probe Ltd','Acme Probe',1,'ACTIVE','Gujarat')")->execute();
$GLOBALS['CLIENT'] = (int)db()->lastInsertId();
$GLOBALS['U'] = [
    'ADMIN' => (int)(ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: mkuser('p_admin','MASTER_ADMIN',$OFF,1)),
    'MM'    => mkuser('p_mm',  'MARKETING_MANAGER', $OFF),
    'MM2'   => mkuser('p_mm2', 'MARKETING_MANAGER', $OFF),
    'BDM'   => mkuser('p_bdm', 'BUSINESS_DEV_MANAGER', $OFF),
    'MEX'   => mkuser('p_mex', 'MARKETING_EXECUTIVE', $OFF),
    'FIN'   => mkuser('p_fin', 'FINANCE', $OFF),
    'BM'    => mkuser('p_bm',  'BRANCH_MANAGER', $OFF),
    'BD'    => mkuser('p_bd',  'BUSINESS_DIRECTOR', $OFF),
    'COORD' => mkuser('p_coord','COORDINATOR', $OFF),
    'INSP'  => mkuser('p_insp','INSPECTOR', $OFF),
];

/** Dispatch a real route as a user in a subprocess; returns ['flash'=>[], 'exc'=>'', ...]. */
function R($who, $route, $method = 'GET', array $get = [], array $post = []) {
    $uid = is_int($who) ? $who : $GLOBALS['U'][$who];
    $cmd = 'php ' . escapeshellarg($GLOBALS['W']) . ' ' . escapeshellarg($GLOBALS['__test_db']) . ' ' . (int)$uid . ' '
         . escapeshellarg($route) . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object)$get)) . ' '
         . escapeshellarg(json_encode((object)$post)) . ' 2>&1';
    $out = shell_exec($cmd);
    $p = strrpos((string)$out, '@@RESULT@@');
    if ($p === false) return ['flash' => [], 'exc' => 'NO RESULT: ' . substr((string)$out, -400), 'fatal' => '', 'code' => 0];
    return json_decode(trim(substr($out, $p + 10)), true);
}
function show($label, $r) {
    $f = implode(' | ', $r['flash'] ?? []);
    printf("    [%s] %s%s%s%s\n", $label, $f === '' ? '(no flash)' : $f, !empty($r['view']) ? '  VIEW=' . $r['view'] : '',
        !empty($r['exc']) ? '  EXC=' . $r['exc'] : '', !empty($r['fatal']) ? '  FATAL=' . $r['fatal'] : '');
}
function q($id) { return ops_one("SELECT id,quote_no,rev,is_current,status,total_amount,subtotal,sent_at,contract_id,contract_number,inquiry_id,lead_id,client_id,client_name,subject,parent_id FROM quotations WHERE id=?", [(int)$id]); }
function qline($id) { $q = q($id); return $q ? sprintf('#%d %s r%d cur=%d %s total=%s contract=%s', $q['id'], $q['quote_no'], $q['rev'], $q['is_current'], $q['status'], $q['total_amount'], $q['contract_number'] ?: '-') : "#$id (gone)"; }
function lastid($t) { return (int)ops_val("SELECT MAX(id) FROM $t"); }
function cnt($sql, $a = []) { return (int)ops_val($sql, $a); }
function H($s) { echo "\n=== $s ===\n"; }
function quote_post($extra = []) {
    return array_merge(['client_id' => (string)$GLOBALS['CLIENT'], 'sbu' => 'INSP', 'office_id' => (string)$GLOBALS['OFF'],
        'subject' => 'Probe inspection', 'contact_name' => 'Ravi', 'contact_email' => 'r@probe.invalid', 'gst_pct' => '18',
        'validity_days' => '30', 'l_desc' => ['Inspection manday'], 'l_service' => [''], 'l_qty' => ['10'], 'l_rate' => ['5000'],
        'l_unit' => ['MANDAY'], 'l_sbu' => ['INSP']], $extra);
}
function new_quote($who = 'MM', $extra = []) {
    $before = lastid('quotations');
    $r = R($who, 'quote-new', 'POST', [], quote_post($extra));
    $id = lastid('quotations');
    return [$id > $before ? $id : 0, $r];
}
function setq($id, array $cols) {
    $set = implode(',', array_map(fn($k) => "$k=?", array_keys($cols)));
    db()->prepare("UPDATE quotations SET $set WHERE id=?")->execute(array_merge(array_values($cols), [(int)$id]));
}
/** Fire the same route as N simultaneous workers (wall-clock barrier). */
function RACE($who, $route, $method, array $get, array $post, $n = 2) {
    $uid = $GLOBALS['U'][$who]; $bar = (int)(microtime(true) * 1000) + 2500; $procs = []; $pipes = [];
    for ($i = 0; $i < $n; $i++) {
        $cmd = 'BARRIER=' . $bar . ' php ' . escapeshellarg($GLOBALS['W']) . ' ' . escapeshellarg($GLOBALS['__test_db']) . ' ' . $uid . ' '
             . escapeshellarg($route) . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object)$get)) . ' '
             . escapeshellarg(json_encode((object)$post)) . ' 2>&1';
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w']], $pp); $pipes[$i] = $pp[1];
    }
    $res = [];
    foreach ($procs as $i => $p) { $o = stream_get_contents($pipes[$i]); proc_close($p); $q = strrpos($o, '@@RESULT@@');
        $res[] = $q === false ? ['flash' => [], 'exc' => 'NO RESULT ' . substr($o, -300)] : json_decode(trim(substr($o, $q + 10)), true); }
    return $res;
}
