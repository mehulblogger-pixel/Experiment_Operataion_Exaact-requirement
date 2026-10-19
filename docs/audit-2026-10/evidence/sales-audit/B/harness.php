<?php
// Shared harness for sales-B probes. Boots the real app on a throwaway SQLite DB
// (tests/bootstrap.php) and lets a probe drive REAL route handlers through
// ops_dispatch() (module gate included) in a forked child, so redirect()/exit
// inside a handler does not end the probe. Never touches production.
$root = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
if (function_exists('pc_migrate')) pc_migrate();

function mkuser($username, $role, $super = 0) {
    db()->prepare("INSERT INTO users (username,first_name,last_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,?,1)")
        ->execute([$username, ucfirst($username), 'Probe', '', $role, $super]);
    $id = (int) db()->lastInsertId();
    db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([probe_office(), $id]);
    return $id;
}
function probe_office() {
    static $o = null; if ($o !== null) return $o;
    $o = (int) (ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 0);
    if (!$o) { db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Probe HQ',1)")->execute(); $o = (int) db()->lastInsertId(); }
    return $o;
}
function as_user($uid) { $_SESSION = ['uid' => $uid]; current_user(true); ua(true); }

/**
 * Drive one route as one user. Returns ['flash'=>[...], 'out'=>string, 'exit'=>'redirect|return'].
 */
function route($uid, $route, $method = 'GET', array $get = [], array $post = []) {
    $tmp = tempnam(sys_get_temp_dir(), 'salesB');
    db(true);                                    // drop the parent's connection before forking
    $pid = pcntl_fork();
    if ($pid === 0) {
        $GLOBALS['__test_db'] = '';              // do NOT let the bootstrap shutdown unlink the DB
        db(true);
        $_SESSION = ['uid' => $uid]; current_user(true); ua(true);
        $_GET = $get; $_POST = $post; $_REQUEST = array_merge($get, $post);
        $_SERVER['REQUEST_METHOD'] = $method;
        $GLOBALS['__route_done'] = false;
        register_shutdown_function(function () use ($tmp) {
            $out = ob_get_level() ? ob_get_clean() : '';
            file_put_contents($tmp, json_encode(['flash' => $_SESSION['flash'] ?? [],
                'exit' => $GLOBALS['__route_done'] ? 'return' : 'redirect/exit',
                'out' => mb_substr(strip_tags((string) $out), 0, 400)]));
        });
        ob_start();
        try { ops_dispatch($route, $method); }
        catch (Throwable $e) { $_SESSION['flash'][] = ['text' => 'THREW: ' . $e->getMessage(), 'tag' => 'exception']; }
        $GLOBALS['__route_done'] = true;
        exit(0);
    }
    pcntl_waitpid($pid, $st);
    db(true);                                    // reconnect fresh in the parent
    $r = json_decode((string) @file_get_contents($tmp), true) ?: ['flash' => [], 'exit' => '?', 'out' => ''];
    @unlink($tmp);
    return $r;
}
function flashes($r) { return implode(' | ', array_map(fn($f) => '[' . $f['tag'] . '] ' . $f['text'], $r['flash'] ?? [])); }
function qrow($id) { return ops_one("SELECT id,quote_no,rev,status,subtotal,gst_pct,gst_amount,total_amount,sent_at,owner_id,created_by FROM quotations WHERE id=?", [$id]); }
function steps($id) { return ops_all("SELECT id,level,approver_role,approver_user_id,status,acted_by FROM quote_approvals WHERE quote_id=? ORDER BY level,id", [$id]); }
function show($label, $v) { echo str_pad($label, 70) . ' => ' . (is_string($v) ? $v : json_encode($v)) . "\n"; }

/** Make a quote through the real quote-new handler; returns its id. */
function new_quote($uid, array $lines, array $hdr = []) {
    $post = array_merge(['client_name' => 'Probe Client Pvt Ltd', 'subject' => 'Probe', 'sbu' => '', 'gst_pct' => '18',
        'validity_days' => '30', 'currency' => 'INR', 'advance_pct' => '0', 'office_id' => (string) probe_office()], $hdr);
    foreach ($lines as $l) {
        $post['l_desc'][] = $l[0]; $post['l_qty'][] = $l[1]; $post['l_rate'][] = $l[2];
        $post['l_unit'][] = $l[3] ?? 'MANDAY'; $post['l_service'][] = ''; $post['l_sbu'][] = '';
        $post['l_location'][] = $l[4] ?? ''; $post['l_order'][] = 'LINE';
    }
    $before = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM quotations");
    $r = route($uid, 'quote-new', 'POST', [], $post);
    $id = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM quotations");
    if ($id <= $before) { echo "  (quote-new refused: " . flashes($r) . ")\n"; return 0; }
    return $id;
}
function status($uid, $qid, $to, array $extra = []) { return route($uid, 'quote-status', 'POST', ['id' => $qid], array_merge(['to' => $to], $extra)); }
function approve_step($uid, $stepId, $decision = 'approve', $remarks = '') { return route($uid, 'quote-approve', 'POST', [], ['step' => $stepId, 'decision' => $decision, 'remarks' => $remarks]); }
function rule($name, $min, $max, $level, $role = '', $uid = null, $sbu = '', $active = 1) {
    db()->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$name, $sbu !== '' ? 'Business Unit' : 'ANY', $sbu, $min, $max, $level, $role, $uid, $active, date('c')]);
    return (int) db()->lastInsertId();
}
