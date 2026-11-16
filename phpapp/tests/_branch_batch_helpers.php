<?php
// Shared fixtures for tests/test_branch_batch_access_*.php (owner's branch batch,
// 16 Nov 2026 — QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md). Not a test file:
// the runner only picks up test_*.php. Every helper creates real, committed rows
// (the route drives run in a separate process that cannot see a transaction) and
// records them in $GLOBALS['__bb_keep'] so bb_cleanup() removes them again.
if (!function_exists('bb_office')) {
    $GLOBALS['__bb_keep'] = $GLOBALS['__bb_keep'] ?? ['offices' => [], 'users' => [], 'calls' => [], 'jobs' => [], 'partners' => [], 'other' => []];

    function bb_tag() { static $t = null; return $t ?? ($t = 'bb' . getmypid() . substr(bin2hex(random_bytes(2)), 0, 3)); }

    function bb_office($name) {
        db()->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([bb_tag() . ' ' . $name]);
        $id = (int) db()->lastInsertId(); $GLOBALS['__bb_keep']['offices'][] = $id; return $id;
    }
    function bb_user($role, $home = null, $scope = '', $extra = []) {
        $un = bb_tag() . '_' . strtolower($role) . '_' . bin2hex(random_bytes(2));
        db()->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, scope_offices, permissions)
                       VALUES (?,?,?,?,?,?,0,?,?,?)")
            ->execute([$un, ucfirst(strtolower($role)), 'Bb', '', $role, (int) ($extra['active'] ?? 1), $home, $scope, (string) ($extra['permissions'] ?? '')]);
        $id = (int) db()->lastInsertId(); $GLOBALS['__bb_keep']['users'][] = $id; return $id;
    }
    function bb_partner($home = null) {
        db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, home_branch_id) VALUES (?,?,1,'ACTIVE',?)")
            ->execute([bb_tag() . ' Client', bb_tag() . ' Client', $home]);
        $id = (int) db()->lastInsertId(); $GLOBALS['__bb_keep']['partners'][] = $id; return $id;
    }
    function bb_call($code, $exec, $ibo, $extra = []) {
        db()->prepare("INSERT INTO calls (call_code, status, executing_office_id, ibo_office_id, client_id, inspection_required_date, call_received_date, created_at)
                       VALUES (?,'OPEN',?,?,?,?,?,?)")
            ->execute([$code, $exec, $ibo, $extra['client_id'] ?? null, $extra['required'] ?? date('Y-m-d', strtotime('+5 days')), date('Y-m-d'), date('c')]);
        $id = (int) db()->lastInsertId(); $GLOBALS['__bb_keep']['calls'][] = $id; return $id;
    }
    function bb_job($code, $callId, $exec, $extra = []) {
        db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$code, $callId, $exec, $extra['inspector_id'] ?? null, $extra['scheduled'] ?? '', date('c')]);
        $id = (int) db()->lastInsertId(); $GLOBALS['__bb_keep']['jobs'][] = $id; return $id;
    }
    function bb_as($uid) { $_SESSION['uid'] = (int) $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); }
    function bb_as_nobody() { $_SESSION['uid'] = 0; unset($_SESSION['uid_ws']); current_user(true); ua(true); }

    // Drive ONE route as ONE user through the production dispatcher, in its own
    // process (every write ends in redirect(), which exits). Returns the worker's
    // JSON: flash messages, how it ended, and which needles the page contained.
    function bb_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) {
        $root = dirname(__DIR__);
        $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
        $env = $engine === 'sqlite'
            ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
            : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
              . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
        $tok = 'bbcsrf' . str_repeat('0', 20);
        if ($method === 'POST') $post['_csrf'] = $tok;
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
             . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
             . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode(['csrf' => $tok]));
        $raw = (string) shell_exec($cmd . ' 2>&1');
        foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
        return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
    }
    function bb_said(array $r) {
        return implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? (is_string($f) ? $f : '')), $r['flash'] ?? []))
             . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');
    }

    function bb_cleanup() {
        $k = &$GLOBALS['__bb_keep'];
        $del = function ($table, $ids) { foreach ($ids as $i) { try { db()->prepare("DELETE FROM $table WHERE id=?")->execute([(int) $i]); } catch (Throwable $e) {} } };
        foreach ($k['other'] as [$table, $id]) $del($table, [$id]);
        $del('jobs', $k['jobs']); $del('calls', $k['calls']); $del('business_partners', $k['partners']);
        $del('users', $k['users']); $del('offices', $k['offices']);
        $k = ['offices' => [], 'users' => [], 'calls' => [], 'jobs' => [], 'partners' => [], 'other' => []];
    }
}
