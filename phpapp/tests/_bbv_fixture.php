<?php
// Shared fixture for tests/test_branch_batch_vouchers_*.php (branch batch R5).
//
// The owner's worked example (27-Branch-Batch-Change-Plan.md, Part 2 R5):
// Inspector Amit (Mumbai) reports to Coordinator Neha; Neha reports to Operation
// Manager Sunil; Sunil to Branch Manager Kiran; Kiran to the Business Director.
// Plus an Ahmedabad coordinator (outside Mumbai), an office Finance person for
// Mumbai (Meera), a central Finance person (Rakesh, all offices), an Asst.
// Manager, a Master Admin and an Admin.
//
// Committed rows (the route tests run in a second process, which cannot see an
// open transaction) with unique names; bbv_cleanup() removes every one.
if (!function_exists('bbv_fixture')) {
    function bbv_as($uid) { $_SESSION['uid'] = (int) $uid; current_user(true); ua(true); }
    function bbv_nobody() { unset($_SESSION['uid']); current_user(true); ua(true); }

    function bbv_fixture() {
        $pdo = db();
        if (function_exists('vappr_migrate')) vappr_migrate();
        $tag = 'bbv' . substr(md5((string) microtime(true)), 0, 5);
        $pdo->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,0)")->execute([$tag . 'M', 'Mumbai ' . $tag, 'Mumbai']);
        $mum = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,0)")->execute([$tag . 'A', 'Ahmedabad ' . $tag, 'Ahmedabad']);
        $ahm = (int) $pdo->lastInsertId();
        // Only this fixture's Finance people count: everybody else's Finance login
        // is switched off for the test and switched back on by bbv_cleanup().
        $otherFin = array_map('intval', array_column(ops_all("SELECT id FROM users WHERE role='FINANCE' AND is_active=1") ?: [], 'id'));
        if ($otherFin) $pdo->exec("UPDATE users SET is_active=0 WHERE id IN (" . implode(',', $otherFin) . ")");

        $user = function ($key, $role, $office, $scope = '', $reportsTo = null, $ins = null) use ($pdo, $tag) {
            $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, reports_to_id, inspector_id)
                           VALUES (?,?,?,?,1,?,?,?,?)")
                ->execute([$tag . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $reportsTo, $ins]);
            return (int) $pdo->lastInsertId();
        };
        $f = ['tag' => $tag, 'mum' => $mum, 'ahm' => $ahm, 'otherFin' => $otherFin];
        $f['bd']     = $user('director', 'BUSINESS_DIRECTOR', null);
        $f['kiran']  = $user('kiran', 'BRANCH_MANAGER', $mum, '', $f['bd']);
        $f['sunil']  = $user('sunil', 'OPERATION_MANAGER', $mum, '', $f['kiran']);
        $f['neha']   = $user('neha', 'COORDINATOR', $mum, '', $f['sunil']);
        $f['co_ahm'] = $user('coahm', 'COORDINATOR', $ahm);
        $f['am']     = $user('asst', 'ASST_MANAGER', $mum);
        $f['meera']  = $user('meera', 'FINANCE', $mum, (string) $mum);   // office Finance (Mumbai only)
        $f['rakesh'] = $user('rakesh', 'FINANCE', null, 'ALL');          // central Finance
        $f['master'] = $user('master', 'MASTER_ADMIN', null);
        $f['admin']  = $user('admin', 'ADMIN', null, 'ALL');
        $f['sbu']    = $user('sbuhead', 'SBU_HEAD', null);

        $ins = function ($name, $office, $reportsTo) use ($pdo) {
            $pdo->prepare("INSERT INTO inspectors (name, home_office_id, reports_to_id, status) VALUES (?,?,?, 'ACTIVE')")->execute([$name, $office, $reportsTo]);
            return (int) $pdo->lastInsertId();
        };
        $f['amit_ins'] = $ins('Amit ' . $tag, $mum, $f['neha']);
        $f['amit']     = $user('amit', 'INSPECTOR', $mum, '', null, $f['amit_ins']);
        $f['rahul_ins'] = $ins('Rahul ' . $tag, $mum, $f['neha']);
        $f['rahul']    = $user('rahul', 'INSPECTOR', $mum, '', null, $f['rahul_ins']);
        $f['sr_ins']   = $ins('Sr ' . $tag, $mum, $f['neha']);
        $f['sr']       = $user('srinsp', 'SR_INSPECTOR', $mum, '', null, $f['sr_ins']);
        $f['lone_ins'] = $ins('Lone ' . $tag, $mum, null);             // no "Reports to" at all
        $f['lone']     = $user('lone', 'INSPECTOR', $mum, '', null, $f['lone_ins']);
        $f['vids'] = [];
        return $f;
    }

    // A voucher for one claimant, in a status, with a total (₹).
    function bbv_voucher(array &$f, $insId, $total, $status = 'DRAFT', $month = null, $office = null) {
        static $n = 0; $n++;
        $month = $month ?: sprintf('2031-%02d', ($n % 12) + 1);
        db()->prepare("INSERT INTO vouchers (inspector_id, office_id, month, status, total, created_at)
                       VALUES (?,?,?,?,?,?)")
            ->execute([(int) $insId, $office ?? $f['mum'], $month . '', $status, (float) $total, date('c')]);
        $id = (int) db()->lastInsertId();
        $f['vids'][] = $id;
        return $id;
    }
    function bbv_v($id) { return ops_one("SELECT v.*, i.name inspector_name, i.sbu FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id WHERE v.id=?", [(int) $id]); }

    // Submit as the given user, exactly as the route does.
    function bbv_submit($id, $uid) {
        bbv_as($uid);
        if (function_exists('vappr_submit')) vappr_submit(bbv_v($id));
        else db()->prepare("UPDATE vouchers SET status='SUBMITTED' WHERE id=?")->execute([(int) $id]);   // the code before this batch
        return bbv_v($id);
    }

    // Drive the REAL route in a second process (see _bbv_worker.php).
    function bbv_route($route, $method, $uid, $vid, array $post = [], array $get = []) {
        $root = dirname(__DIR__);
        $env = (getenv('DB_DRIVER') === 'mysql')
            ? 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
              . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'))
            : 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_bbv_worker.php') . ' ' . escapeshellarg($route) . ' ' . $method
             . ' ' . (int) $uid . ' ' . (int) $vid . ' ' . escapeshellarg(json_encode($post)) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>/dev/null';
        $raw = (string) shell_exec($cmd);
        foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
        return ['code' => 0, 'flash' => [], 'status' => '', 'restricted' => false, 'rendered' => false, 'raw' => substr($raw, -300)];
    }

    function bbv_cleanup(array $f) {
        $pdo = db();
        bbv_nobody();
        // Vouchers the real routes created for a fixture claimant are removed too.
        try {
            $ins = array_map('intval', array_column(ops_all("SELECT id FROM inspectors WHERE name LIKE ?", ['% ' . $f['tag']]) ?: [], 'id'));
            if ($ins) foreach (ops_all("SELECT id FROM vouchers WHERE inspector_id IN (" . implode(',', $ins) . ")") ?: [] as $r) $f['vids'][] = (int) $r['id'];
        } catch (Throwable $e) {}
        if ($f['vids']) {
            $in = implode(',', array_map('intval', array_unique($f['vids'])));
            $pdo->exec("DELETE FROM vouchers WHERE id IN ($in)");
            try { $pdo->exec("DELETE FROM voucher_approval_log WHERE voucher_id IN ($in)"); } catch (Throwable $e) {}
        }
        $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$f['tag'] . '_%']);
        $pdo->prepare("DELETE FROM inspectors WHERE name LIKE ?")->execute(['% ' . $f['tag']]);
        $pdo->prepare("DELETE FROM offices WHERE code IN (?,?)")->execute([$f['tag'] . 'M', $f['tag'] . 'A']);
        try { $pdo->exec("DELETE FROM approval_delegations WHERE entity='VOUCHER'"); } catch (Throwable $e) {}
        if ($f['otherFin']) $pdo->exec("UPDATE users SET is_active=1 WHERE id IN (" . implode(',', $f['otherFin']) . ")");
        if (defined('VAPPR_SETTING')) setting_set(VAPPR_SETTING, '');
    }
}
