<?php
// ============================================================================
//  Shared fixture for tests/test_branch_batch_reports_*.php (owner-approved
//  branch batch, QA-REPORTS/V51/27 — B4, B5, R2, Reporting A3, C2/0.3).
//
//  Three offices — Ahmedabad (issues and executes), Mumbai (holds the client's
//  order: the CONTRACTING office) and Pune (no part at all) — with a
//  Coordinator in each, two Ahmedabad inspectors with their own sign-ins, and a
//  work order sold by Mumbai and carried out by Ahmedabad.
//
//  Routes are driven through tests/_sales_route_worker.php: a REAL separate
//  process running the production dispatcher (module gate, verb gate and the
//  handler's own checks) as one user, because every write ends in redirect(),
//  which exits. Not a test file itself (no test_ prefix), so run.php skips it.
// ============================================================================
if (!function_exists('bbr_fixture')) {

function bbr_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) {
    $root   = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env    = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
}
function bbr_said($r) { return implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])); }
// Was the request turned away with the branch-scope refusal?
function bbr_scope_refused($r) { return stripos(bbr_said($r), 'outside your office / branch scope') !== false; }

function bbr_fixture() {
    static $fx = null;
    if ($fx !== null) return $fx;
    $pdo = db();
    $off = function ($code, $name) use ($pdo) {
        $pdo->prepare("INSERT INTO offices (code, name, city, is_ahmedabad) VALUES (?,?,?,0)")->execute([$code, $name, $name]);
        return (int) $pdo->lastInsertId();
    };
    $A = $off('BBA', 'BBR Ahmedabad'); $M = $off('BBM', 'BBR Mumbai'); $P = $off('BBP', 'BBR Pune');
    $ins = function ($name) use ($pdo) {
        $pdo->prepare("INSERT INTO inspectors (name, status, created_at) VALUES (?, 'ACTIVE', ?)")->execute([$name, date('c')]);
        return (int) $pdo->lastInsertId();
    };
    $insA = $ins('BBR Inspector Amit'); $insR = $ins('BBR Inspector Rahul');
    $user = function ($un, $first, $role, $office, $insId = null) use ($pdo) {
        $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, inspector_id)
                       VALUES (?,?,?,?,?,1,0,?,?)")->execute([$un, $first, 'Probe', '', $role, $office, $insId]);
        return (int) $pdo->lastInsertId();
    };
    $fx = [
        'A' => $A, 'M' => $M, 'P' => $P, 'insAmit' => $insA, 'insRahul' => $insR,
        'coA'   => $user('bbr_co_a', 'Neha',  'COORDINATOR', $A),
        'coM'   => $user('bbr_co_m', 'Priya', 'COORDINATOR', $M),
        'coP'   => $user('bbr_co_p', 'Pune',  'COORDINATOR', $P),
        'bmA'   => $user('bbr_bm_a', 'Kiran', 'BRANCH_MANAGER', $A),
        'amit'  => $user('bbr_ins_amit',  'Amit',  'INSPECTOR', $A, $insA),
        'rahul' => $user('bbr_ins_rahul', 'Rahul', 'INSPECTOR', $A, $insR),
    ];
    $party = function ($name, $isClient, $branch) use ($pdo) {
        $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, is_vendor, home_branch_id) VALUES (?,?,?,?,?,?)")
            ->execute([strtoupper(substr(preg_replace('/\W/', '', $name), 0, 8)), $name, $name, $isClient ? 1 : 0, $isClient ? 0 : 1, $branch]);
        return (int) $pdo->lastInsertId();
    };
    // The client and vendor belong to Ahmedabad — Mumbai's pickers do not list them.
    $fx['client'] = $party('BBR Refinery Ltd', true, $A);
    $fx['vendor'] = $party('BBR Test Vendor', false, $A);
    // Work order sold by Mumbai (contracting) and carried out by Ahmedabad (executing).
    $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, ibo_office_id, executing_office_id, contracting_office_id, sbu, status, created_at)
                   VALUES (?,?,?,?,?,?,'',?,?)")->execute(['BBR-WO-AHM', $fx['client'], $fx['vendor'], $M, $A, $M, 'OPEN', date('c')]);
    $fx['callAM'] = (int) $pdo->lastInsertId();
    // A Mumbai-only work order (Mumbai sells and executes).
    $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, ibo_office_id, executing_office_id, contracting_office_id, sbu, status, created_at)
                   VALUES (?,?,?,?,?,?,'',?,?)")->execute(['BBR-WO-MUM', $fx['client'], $fx['vendor'], $M, $M, $M, 'OPEN', date('c')]);
    $fx['callMM'] = (int) $pdo->lastInsertId();
    $t = ops_one("SELECT id, code FROM report_types WHERE active=1 ORDER BY id LIMIT 1");
    $fx['typeId'] = (int) $t['id']; $fx['typeCode'] = (string) $t['code'];
    return $fx;
}

// A report filed under the Ahmedabad office against the Mumbai-sold work order.
function bbr_report(array $over = []) {
    static $n = 0; $n++;
    $fx = bbr_fixture();
    $row = array_merge([
        'irn' => 'BBR/IR/' . getmypid() . '/' . $n, 'report_type_id' => $fx['typeId'], 'type_code' => $fx['typeCode'],
        'title' => 'BBR report ' . $n, 'client_id' => $fx['client'], 'vendor_id' => $fx['vendor'],
        'call_id' => $fx['callAM'], 'job_id' => null, 'office_id' => $fx['A'], 'sbu' => '',
        'inspector_id' => $fx['insAmit'], 'status' => 'DRAFT', 'rev' => 0, 'finalized' => 0,
        'data' => '{}', 'remarks' => '', 'location' => 'Bay 1', 'created_by' => 'Neha Probe',
        'created_at' => date('c'), 'updated_at' => date('c'),
    ], $over);
    $cols = array_keys($row);
    db()->prepare("INSERT INTO report_docs (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")
        ->execute(array_values($row));
    return (int) db()->lastInsertId();
}
function bbr_doc($id) { return ops_one("SELECT * FROM report_docs WHERE id=?", [(int) $id]); }
// The edit form exactly as the browser posts it for this report (every field it renders).
function bbr_edit_post($id, array $over = []) {
    $d = bbr_doc($id);
    $p = [];
    foreach (['report_type_id','title','inspection_date','office_id','sbu','client_id','vendor_id','po_ref','product_category',
              'project_code','project_name','drawing_no','drawing_rev','qap_rev','material_grade','standards','location',
              'inspector_id','approver_user_id','result','release_status','remarks','call_id','job_id'] as $k)
        $p[$k] = (string) ($d[$k] ?? '');
    $p['call_id'] = (string) (int) ($d['call_id'] ?? 0); $p['job_id'] = (string) (int) ($d['job_id'] ?? 0);
    return array_merge($p, $over);
}
function bbr_history($id, $action) {
    return ops_all("SELECT * FROM idems_audit WHERE entity='report_doc' AND entity_id=? AND action=? ORDER BY id", [(int) $id, $action]);
}

}
