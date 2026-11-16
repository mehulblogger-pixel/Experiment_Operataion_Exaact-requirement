<?php
// ============================================================================
//  A REAL separate process for the branch-batch portal tests (P1, P4, P5).
//
//  Why a worker: the screens under test are drawn by the PRODUCTION view() —
//  the defect (DEF-DIR-005 / DEF-QUA-010) lives in how view() runs the page
//  frame, so a stub or a local imitation of view() could never show it — and
//  the staff portal handlers end in redirect(), which exits. A separate process
//  can do both, and prints what it saw as one JSON line.
//
//  It attaches to the SAME database as the parent (env), sets up its own
//  made-up records, and deletes them again on the way out. It never uses
//  tests/bootstrap.php, which drops every table on the MySQL path.
//
//    php tests/_branch_batch_portal_worker.php <mode>
//
//  Modes: probe, perms_get, perms_post, perms_post_mismatch, scope, reinvite, hold
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'bb-portal-worker';
$_SERVER['REQUEST_URI'] = '/portal-users'; $_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $mm);
foreach ($mm[1] as $rel) require_once $root . $rel;

// The production view(), verbatim (as tests/_my_work_view_worker.php loads it).
$i = strpos($idx, 'function view($name, $vars = []) {');
$fn = substr($idx, $i, strpos($idx, "\n}\n", $i) - $i + 3);
$tmp = $root . '/.viewprobe_bbp_' . getmypid() . '.php';
file_put_contents($tmp, "<?php\n" . $fn);
require $tmp; @unlink($tmp);
if (function_exists('portal_migrate')) portal_migrate();
if (function_exists('cvp_migrate')) cvp_migrate();
setting_set('portal_enabled', '1');

$mode = (string)($argv[1] ?? '');
$tag  = 'bbp' . getmypid();
$GLOBALS['__out'] = ['mode' => $mode, 'ok' => false];
$GLOBALS['__made'] = ['users' => [], 'client_users' => [], 'business_partners' => [], 'offices' => [],
                      'portal_requests' => [], 'partner_addresses' => []];
$GLOBALS['__mail'] = [];
$GLOBALS['__mail_capture'] = function ($to, $subject, $body, $kind) { $GLOBALS['__mail'][] = compact('to', 'subject', 'body', 'kind'); };
$GLOBALS['__probe'] = '';

// Whatever happens — a redirect() exits — report and clean up.
register_shutdown_function(function () {
    $html = (string)ob_get_clean();
    $o = $GLOBALS['__out'];
    $fatal = error_get_last();
    if ($fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))
        $o['error'] = ($o['error'] ?? '') . ' fatal: ' . $fatal['message'] . ' @' . $fatal['line'];
    $o['ok'] = empty($o['error']);           // a redirect() exits before the end of the mode
    $o['html']  = $o['html'] ?? $html;
    $o['flash'] = $_SESSION['flash'] ?? [];
    $o['sessionLink'] = $_SESSION['portal_invite_link'] ?? null;
    $o['mail']  = $GLOBALS['__mail'];
    $after = $GLOBALS['__after'] ?? null;
    if (is_callable($after)) { try { $o = $after($o); } catch (Throwable $e) { $o['afterError'] = $e->getMessage(); } }
    if ($GLOBALS['__probe'] !== '') @unlink($GLOBALS['__probe']);
    foreach (array_reverse($GLOBALS['__made'], true) as $t => $ids)
        foreach ($ids as $id) { try { db()->prepare("DELETE FROM $t WHERE id=?")->execute([(int)$id]); } catch (Throwable $e) {} }
    try { db()->prepare("DELETE FROM calls WHERE notes LIKE ?")->execute(['%' . 'bbp-hold-' . '%']); } catch (Throwable $e) {}
    echo "\n" . json_encode($o) . "\n";
});
ob_start();

$mk = function ($table, array $row) {
    $cols = array_keys($row);
    db()->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")
        ->execute(array_values($row));
    $id = isset($row['id']) ? (int)$row['id'] : (int)db()->lastInsertId();
    $GLOBALS['__made'][$table][] = $id;
    return $id;
};
// A free id both in users and in client_users, so a staff member and a portal
// user can share a number — the condition DEF-DIR-005 needed.
$sharedId = function () {
    $a = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM users");
    $b = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM client_users");
    return max($a, $b) + 1000 + (getmypid() % 500);
};
$signInStaff = function ($id) {
    $_SESSION['uid'] = (int)$id; current_user(true); if (function_exists('ua')) ua(true);
};
$offA = $mk('offices', ['name' => 'BBP Office A ' . $tag, 'code' => 'BBA']);
$offB = $mk('offices', ['name' => 'BBP Office B ' . $tag, 'code' => 'BBB']);
$clientX = $mk('business_partners', ['display_name' => 'BBP Client X ' . $tag, 'legal_name' => 'BBP Client X', 'is_client' => 1, 'home_branch_id' => $offA, 'status' => 'ACTIVE']);
$clientY = $mk('business_partners', ['display_name' => 'BBP Client Y ' . $tag, 'legal_name' => 'BBP Client Y', 'is_client' => 1, 'home_branch_id' => $offA, 'status' => 'ACTIVE']);
$clientB = $mk('business_partners', ['display_name' => 'BBP Client B ' . $tag, 'legal_name' => 'BBP Client B', 'is_client' => 1, 'home_branch_id' => $offB, 'status' => 'ACTIVE']);
// The target: a portal user of client X; another office's client person; and
// the clash: a portal user of client Y whose id is the staff member's id. The
// shared id is chosen after the numbered inserts, so it is above all of them.
$target = $mk('client_users', ['partner_id' => $clientX, 'email' => $tag . '.target@example.test', 'name' => 'BBP Target',
    'password_hash' => 'h', 'is_active' => 1, 'must_change' => 0, 'perms' => '', 'access_expires' => '']);
$other = $mk('client_users', ['partner_id' => $clientB, 'email' => $tag . '.otheroffice@example.test', 'name' => 'BBP Other Office',
    'password_hash' => 'h', 'is_active' => 1, 'must_change' => 0, 'perms' => '', 'access_expires' => '']);
$staffId = $sharedId();
$mk('users', ['id' => $staffId, 'username' => $tag . '_coord', 'first_name' => 'Coord', 'last_name' => 'Probe',
    'email' => $tag . '.coord@example.test', 'role' => 'COORDINATOR', 'is_active' => 1, 'is_superuser' => 0,
    'home_office_id' => $offA]);
$clash = $mk('client_users', ['id' => $staffId, 'partner_id' => $clientY, 'email' => $tag . '.clash@example.test', 'name' => 'BBP Clash',
    'password_hash' => 'h', 'is_active' => 1, 'must_change' => 0, 'perms' => '', 'access_expires' => '']);
$signInStaff($staffId);
$GLOBALS['__out'] += ['staffId' => $staffId, 'target' => $target, 'clash' => $clash, 'other' => $other,
                      'staffEmail' => $tag . '.coord@example.test', 'tag' => $tag];
$row = fn($id) => ops_one("SELECT * FROM client_users WHERE id=?", [(int)$id]);

try {
    if ($mode === 'probe') {
        // Every name the frame sets for itself, passed in by a "handler". The
        // probe screen prints what it received — the frame must not change any.
        $GLOBALS['__probe'] = $root . '/views/.bbp_probe_' . getmypid() . '.php';
        file_put_contents($GLOBALS['__probe'], '<?php echo "<<<PROBE" . json_encode(['
            . '"u" => $u ?? null, "office" => $office ?? null, "m" => $m ?? null, "isInsp" => $isInsp ?? null,'
            . '"navOn" => is_string($navOn ?? null) ? $navOn : "?", "grp" => is_string($grp ?? null) ? $grp : "?",'
            . '"endgrp" => is_string($endgrp ?? null) ? $endgrp : "?", "grpN" => $grpN ?? null, "curGrp" => $curGrp ?? null,'
            . '"lg" => $lg ?? null, "embed" => $embed ?? null, "viewFile" => $viewFile ?? null, "cur" => $cur ?? null,'
            . '"__sb" => $__sb ?? null, "__trail" => $__trail ?? null, "__back" => $__back ?? null]) . "PROBE>>>"; ?>');
        $name = '.bbp_probe_' . getmypid();
        $vars = ['u' => 'VIEW-U', 'office' => 'VIEW-OFFICE', 'm' => 'VIEW-M', 'isInsp' => 'VIEW-ISINSP',
                 'navOn' => 'VIEW-NAVON', 'grp' => 'VIEW-GRP', 'endgrp' => 'VIEW-ENDGRP', 'grpN' => 'VIEW-GRPN',
                 'curGrp' => 'VIEW-CURGRP', 'lg' => 'VIEW-LG', 'embed' => 'VIEW-EMBED', 'viewFile' => 'VIEW-VIEWFILE',
                 '__sb' => 'VIEW-SB', '__trail' => 'VIEW-TRAIL', '__back' => 'VIEW-BACK'];
        $grab = function ($html) {
            return preg_match('/<<<PROBE(.*?)PROBE>>>/s', $html, $x) ? json_decode($x[1], true) : null;
        };
        flash('A message waiting to be shown', 'success');   // the flash loop must run
        ob_start(); view($name, $vars); $full = (string)ob_get_clean();
        $_GET['embed'] = '1';
        flash('Another message', 'success');
        ob_start(); view($name, $vars); $emb = (string)ob_get_clean();
        unset($_GET['embed']);
        ob_start(); view($name, []); $bare = (string)ob_get_clean();
        ob_start(); view($name, ['cur' => 'VIEW-CUR']); $own = (string)ob_get_clean();
        $GLOBALS['__out'] += ['full' => $grab($full), 'embedded' => $grab($emb), 'bare' => $grab($bare), 'ownCur' => $grab($own),
            'fullHasFlash' => strpos($full, 'A message waiting to be shown') !== false,
            'fullHasOffice' => strpos($full, 'BBP Office A ' . $tag) !== false,
            'fullHasShell' => strpos($full, 'class="side"') !== false || strpos($full, 'sb-logo') !== false];
        $GLOBALS['__out']['html'] = '';
    } elseif ($mode === 'method') {
        // DEF-QUA-010 — the method page drawn right after a save (a flash waiting).
        [$mid, $err] = method_save(['title' => 'BBP method ' . $tag]);
        $GLOBALS['__out']['methodErr'] = $err;
        $m = method_get($mid);
        $GLOBALS['__out']['methodCode'] = (string)($m['method_code'] ?? '');
        $GLOBALS['__out']['methodId'] = (int)$mid;
        flash('Method added as a draft. Validate it, then set it Current.');
        $_SERVER['REQUEST_URI'] = '/method?id=' . $mid;
        ob_start(); view('ops/method_detail', ['m' => $m, 'canEdit' => true, 'supersedes' => null, 'supersededBy' => null, 'cvals' => []]);
        $GLOBALS['__out']['detailHtml'] = (string)ob_get_clean();
        flash('Method updated.');
        ob_start(); view('ops/method_form', ['m' => $m, 'categories' => [], 'cfields' => [], 'cvals' => []]);
        $GLOBALS['__out']['formHtml'] = (string)ob_get_clean();
        try { db()->prepare("DELETE FROM methods WHERE id=?")->execute([(int)$mid]); } catch (Throwable $e) {}
        $GLOBALS['__out']['html'] = '';
    } elseif ($mode === 'audit') {
        // The audit log's office filter, drawn by a person whose own office is A
        // while the filter asks for office B.
        db()->prepare("UPDATE users SET role='MASTER_ADMIN' WHERE id=?")->execute([$staffId]);
        $signInStaff($staffId);
        $_GET = ['office' => (string)$offB]; $_SERVER['REQUEST_URI'] = '/audit-log?office=' . $offB;
        $GLOBALS['__out']['offB'] = $offB;
        ops_idems_audit('GET');
        $GLOBALS['__out']['html'] = (string)ob_get_contents();
    } elseif ($mode === 'perms_get') {
        $_GET['id'] = (string)$target; $_SERVER['REQUEST_URI'] = '/portal-user-perms?id=' . $target;
        ops_portal_admin('portal-user-perms', 'GET');
        $GLOBALS['__out']['html'] = (string)ob_get_contents();
    } elseif ($mode === 'perms_get_other') {
        $_GET['id'] = (string)$other; $_SERVER['REQUEST_URI'] = '/portal-user-perms?id=' . $other;
        ops_portal_admin('portal-user-perms', 'GET');
        $GLOBALS['__out']['html'] = (string)ob_get_contents();
        $GLOBALS['__out']['status'] = http_response_code();
    } elseif ($mode === 'perms_post' || $mode === 'perms_post_mismatch') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string)$target, 'partner_id' => (string)($mode === 'perms_post' ? $clientX : $clientY),
                  'p_invoices' => '1', 'p_complaint' => '1', 'role_preset' => 'ACCOUNTS', 'access_expires' => '2026-12-21'];
        $GLOBALS['__after'] = function ($o) use ($row, $target, $clash) {
            $o['targetAfter'] = $row($target); $o['clashAfter'] = $row($clash); return $o;
        };
        ops_portal_admin('portal-user-perms', 'POST');
    } elseif ($mode === 'scope') {
        ops_portal_admin('portal-users', 'GET');
        $GLOBALS['__out']['html'] = (string)ob_get_contents();
        $GLOBALS['__out']['registerEmails'] = array_column(portal_users_all(), 'email');
        ob_clean();
        // Withdraw and "New link" on the other office's client person.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string)$other];
        $GLOBALS['__after'] = function ($o) use ($row, $other) { $o['otherAfter'] = $row($other); return $o; };
        try { ops_portal_admin('portal-user-toggle', 'POST'); } catch (Throwable $e) {}
    } elseif ($mode === 'scope_wide') {
        // A company-wide role (Admin) sees every client's portal people.
        db()->prepare("UPDATE users SET role='ADMIN' WHERE id=?")->execute([$staffId]);
        $signInStaff($staffId);
        $GLOBALS['__out']['registerEmails'] = array_column(portal_users_all(), 'email');
        $GLOBALS['__out']['html'] = '';
    } elseif ($mode === 'scope_reinvite') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string)$other];
        $GLOBALS['__after'] = function ($o) use ($row, $other) { $o['otherAfter'] = $row($other); return $o; };
        ops_portal_admin('portal-user-reinvite', 'POST');
    } elseif ($mode === 'reinvite') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string)$target];
        $GLOBALS['__after'] = function ($o) use ($row, $target) {
            $o['targetAfter'] = $row($target);
            $o['logBodies'] = array_column(ops_all("SELECT body FROM email_log WHERE to_addr=?", [(string)$o['targetAfter']['email']]), 'body');
            // And the register page drawn straight afterwards, as staff would see it.
            $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
            ob_start(); ops_portal_admin('portal-users', 'GET'); $o['registerHtml'] = (string)ob_get_clean();
            return $o;
        };
        ops_portal_admin('portal-user-reinvite', 'POST');
    } elseif ($mode === 'dep_approve_other_site' || $mode === 'dep_approve_own_site') {
        // DEF-CP-003 through the real portal address: a person limited to site A
        // approves an attendance period at site A, or (forged) at site B.
        if (function_exists('pdso_migrate')) pdso_migrate();
        $sA = $mk('partner_addresses', ['partner_id' => $clientX, 'label' => 'BBP Site A', 'is_primary' => 1]);
        $sB = $mk('partner_addresses', ['partner_id' => $clientX, 'label' => 'BBP Site B', 'is_primary' => 0]);
        $mkDep = function ($site) use ($clientX, $tag) {
            db()->prepare("INSERT INTO calls (call_code, client_id, site_address_id, inspection_type, status, notes) VALUES (?,?,?,?,?,?)")
                ->execute(['BBP-' . $tag . '-' . $site, $clientX, $site, 'DEPUTATION', 'OPEN', 'bbp-hold-' . $tag]);
            $call = (int)db()->lastInsertId();
            db()->prepare("INSERT INTO jobs (call_id, job_code, job_type) VALUES (?,?, 'DEPUTATION')")->execute([$call, 'JOB-BBP-' . $site]);
            $job = (int)db()->lastInsertId();
            db()->prepare("INSERT INTO dep_att_approval (job_id, client_id, period_from, period_to, basis, billable_days, status, created_at)
                           VALUES (?,?, '2026-11-01','2026-11-07','MANDAYS',5,'SUBMITTED',?)")->execute([$job, $clientX, date('c')]);
            return [$job, (int)db()->lastInsertId()];
        };
        [$jobA, $apA] = $mkDep($sA); [$jobB, $apB] = $mkDep($sB);
        $GLOBALS['__cleanup_jobs'] = [$jobA, $jobB]; $GLOBALS['__cleanup_ap'] = [$apA, $apB];
        db()->prepare("UPDATE client_users SET site_ids=?, perms='deputation,deputation.approve' WHERE id=?")->execute([(string)$sA, $target]);
        unset($_SESSION['uid']); current_user(true);
        $_SESSION['cuid'] = $target; auth_bind_identity('cuid');
        $ap = $mode === 'dep_approve_own_site' ? $apA : $apB;
        $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/portal/dep-approve';
        $_POST = ['id' => (string)$ap, 'decision' => 'APPROVED', 'client_rep' => 'BBP rep', '_csrf' => csrf_token()];
        $GLOBALS['__after'] = function ($o) use ($apA, $apB) {
            $o['statusA'] = (string)ops_val("SELECT status FROM dep_att_approval WHERE id=?", [$apA]);
            $o['statusB'] = (string)ops_val("SELECT status FROM dep_att_approval WHERE id=?", [$apB]);
            foreach ($GLOBALS['__cleanup_ap'] as $id) db()->prepare("DELETE FROM dep_att_approval WHERE id=?")->execute([$id]);
            foreach ($GLOBALS['__cleanup_jobs'] as $id) db()->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
            return $o;
        };
        portal_route('portal/dep-approve', 'POST');
    } elseif ($mode === 'hold') {
        db()->prepare("UPDATE business_partners SET hold_status='HOLD', hold_reason='BBP unpaid' WHERE id=?")->execute([$clientX]);
        $req = $mk('portal_requests', ['partner_id' => $clientX, 'client_user_id' => $target, 'subject' => 'bbp-hold-' . $tag,
            'detail' => 'Please inspect bbp-hold-' . $tag, 'site' => 'Works', 'wanted_on' => '2026-12-01', 'contact_name' => 'X',
            'contact_phone' => '1', 'status' => 'NEW', 'created_at' => date('c')]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string)$req, 'status' => 'CONVERTED', 'reply' => 'ok'];
        $GLOBALS['__after'] = function ($o) use ($req, $tag) {
            $o['reqAfter'] = ops_one("SELECT * FROM portal_requests WHERE id=?", [$req]);
            $o['calls'] = (int)ops_val("SELECT COUNT(*) FROM calls WHERE notes LIKE ?", ['%bbp-hold-' . $tag . '%']);
            return $o;
        };
        ops_portal_admin('portal-request', 'POST');
    } else {
        throw new RuntimeException('unknown mode');
    }
} catch (Throwable $e) {
    $GLOBALS['__out']['error'] = get_class($e) . ': ' . $e->getMessage();
}
