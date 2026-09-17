<?php
// A REAL separate process for the M6 concurrency tests. Attaches to the SAME
// database as the parent; deliberately does NOT use tests/bootstrap.php, which
// drops every table on the MySQL path.
//
//   php tests/_m6_worker.php <op> <id> <arg> <delay-ms> <uid>
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'm6-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string) ($argv[1] ?? ''); $id = (int) ($argv[2] ?? 0);
$arg = (string) ($argv[3] ?? ''); $delay = (int) ($argv[4] ?? 0); $uid = (int) ($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);

$out = ['op' => $op, 'ok' => false, 'msg' => '', 'code' => ''];
try {
    if ($op === 'join') {
        //  Exactly what the stage route does: gate, write, compensate.
        $c = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        $why = rexec_block_reason((int) $c['requisition_id'], 'JOIN', $id);
        if ($why !== '') { $out['msg'] = $why; $out['code'] = 'GATED'; }
        else {
            db()->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $id]);
            if (function_exists('reqf_sync')) reqf_sync((int) $c['requisition_id']);
            $rev = rexec_join_enforce_after_write($id, (string) $c['stage']);
            $out['ok'] = ($rev === ''); $out['msg'] = $rev; $out['code'] = $rev === '' ? 'JOINED' : 'REVERTED';
        }
    } elseif ($op === 'offer') {
        $o = offer_create($id, ['ctc' => 100000]);
        $out['ok'] = ((int) $o > 0); $out['code'] = $out['ok'] ? 'OFFERED' : 'REFUSED';
    } elseif ($op === 'assign') {
        $r = rasg_assign('REQ_RECRUITER', $id, $arg === '' ? null : (int) $arg, ['expect' => null, 'source' => 'm6']);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code'];
        $out['to'] = $arg === '' ? null : (int) $arg;     // what THIS process asked for
    } elseif ($op === 'material') {
        $r = hreq_get($id);
        [$ok, $msg] = hreq_save($id, ['requested_by_id' => $r['requested_by_id'],
            'requesting_department_id' => $r['requesting_department_id'],
            'hiring_department_id' => $r['hiring_department_id'], 'job_title' => $r['job_title'],
            'designation' => $r['designation'], 'quantity' => (int) $r['quantity'], 'office_id' => $r['office_id'],
            'employment_type' => $r['employment_type'], 'request_type' => $r['request_type'],
            'priority' => $r['priority'], 'grade' => $arg]);
        $out['ok'] = (bool) $ok; $out['msg'] = (string) $msg; $out['code'] = $ok ? 'CHANGED' : 'REFUSED';
    } elseif ($op === 'advance') {
        $why = rexec_cand_block_reason($id, 'ADVANCE');
        if ($why === '') { db()->prepare("UPDATE candidates SET stage='SHORTLISTED' WHERE id=?")->execute([$id]); $out['ok'] = true; $out['code'] = 'ADVANCED'; }
        else { $out['code'] = 'GATED'; $out['msg'] = $why; }
    } elseif ($op === 'deactivate') {
        db()->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
        $out['ok'] = true; $out['code'] = 'DEACTIVATED';
    } elseif ($op === 'decide') {
        [$ok, $msg] = hreq_apply_decision($id, $arg ?: 'APPROVED', 'worker', 'concurrent');
        $out['ok'] = (bool) $ok; $out['msg'] = (string) $msg; $out['code'] = $ok ? 'DECIDED' : 'REFUSED';
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
