<?php
// ============================================================================
//  Reopening — the owner's answer of 20 Oct 2026 (D-22, "Yes"): a manager may
//  reopen a lost lead or a closed deal, with a reason, and it is logged.
//  Before: a lost lead could not be reopened, yet could be converted straight
//  into a customer; a won or lost deal could not be reopened at all.
//  "A manager" is someone who already holds sales-manager authority — whoever
//  configures the pipelines, or approves quotations; no permission is added.
// ============================================================================
t_section('Sales — a manager reopens a lost lead or a closed deal, with a reason');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
leads_migrate(); opp_migrate();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('RO Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$ME = $mk('ro_me', 'MARKETING_EXECUTIVE');   // works leads; not a manager
$MM = $mk('ro_mm', 'MARKETING_MANAGER');     // approves quotations: a sales manager
t_as_admin();
$kindStage = function ($pipe, $kind) { foreach (pipeline_stages($pipe) as $s) if ($s['kind'] === $kind) return $s; return null; };

// ---- a lost lead ---------------------------------------------------------------------------
$lead = lead_create(['company_name' => 'RO Valves', 'contact_name' => 'Ravi', 'contact_email' => 'ravi@ro.test', 'office_id' => $off]);
$lid = (int) $lead['id'];
$lrow = fn() => ops_one("SELECT * FROM leads WHERE id=?", [$lid]);
$pipe = (int) $lrow()['pipeline_id'];
$open = pipeline_stages($pipe);
$second = null; foreach ($open as $s) if ($s['kind'] === 'OPEN' && (int)$s['id'] !== (int)$lrow()['stage_id']) { $second = $s; break; }
lead_move($lid, (int)$second['id']);
lead_move($lid, (int)$kindStage($pipe, 'LOST')['id'], ['lost_reason' => 'PRICE_HIGH', 'lost_note' => 'Too dear']);
t_eq((string) $lrow()['status'], 'LOST', 'arranged: a lead lost from its second stage');
$r = $drive($MM, 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]);
t_eq((string) $lrow()['status'], 'LOST', 'a lost lead cannot be converted straight into a customer: ' . $said($r));
$drive($ME, 'lead-reopen', 'POST', [], ['id' => $lid, 'reason' => 'They called back']);
t_eq((string) $lrow()['status'], 'LOST', 'a sales executive cannot reopen it — a manager does');
$drive($MM, 'lead-reopen', 'POST', [], ['id' => $lid, 'reason' => '']);
t_eq((string) $lrow()['status'], 'LOST', 'reopening needs a reason');
$r = $drive($MM, 'lead-reopen', 'POST', [], ['id' => $lid, 'reason' => 'They called back — budget approved']);
t_eq([(string) $lrow()['status'], (int) $lrow()['stage_id']], ['OPEN', (int) $second['id']], 'a manager reopens it, back at the stage it was lost from: ' . $said($r));
t_eq((string) $lrow()['lost_reason'], '', 'it no longer counts as lost');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND subject LIKE ?", [$lid, '%reopened by%budget approved%']), 1,
     'the reopening, its reason and who did it are written in its history');
$pg = $drive($MM, 'lead', 'GET', ['id' => $lid], [], ['Reopen this lead']);
t_ok(empty($pg['has']['Reopen this lead']), 'an open lead offers no Reopen');

// ---- a lost deal, and a won one ------------------------------------------------------------------
$o = opp_create(['name' => 'RO resident engineer', 'partner_name' => 'RO Valves', 'value' => 100000, 'office_id' => $off]);
$oid = (int) $o['id'];
$orow = fn() => ops_one("SELECT * FROM opportunities WHERE id=?", [$oid]);
$opipe = (int) $orow()['pipeline_id'];
opp_move($oid, (int)$kindStage($opipe, 'LOST')['id'], ['lost_reason' => 'PRICE'], true);
t_eq((string) $orow()['status'], 'LOST', 'arranged: a lost deal');
$pg = $drive($MM, 'opportunity', 'GET', ['id' => $oid], [], ['Reopen this deal']);
t_ok(!empty($pg['has']['Reopen this deal']), 'the manager is offered Reopen on it');
$drive($ME, 'opportunity-reopen', 'POST', [], ['id' => $oid, 'reason' => 'x']);
t_eq((string) $orow()['status'], 'LOST', 'not by a sales executive');
$drive($MM, 'opportunity-reopen', 'POST', [], ['id' => $oid, 'reason' => 'Revised scope requested']);
t_eq([(string) $orow()['status'], (string) $orow()['lost_reason']], ['OPEN', ''], 'a manager reopens it, with a reason');
opp_move($oid, (int)$kindStage($opipe, 'WON')['id'], [], true);
t_eq((string) $orow()['status'], 'WON', 'arranged: the deal is won');
$drive($MM, 'opportunity-reopen', 'POST', [], ['id' => $oid, 'reason' => 'Customer withdrew the PO before we raised the order']);
t_eq([(string) $orow()['status'], (string) $orow()['won_by']], ['OPEN', ''], 'a won deal not yet an order can be reopened');
opp_move($oid, (int)$kindStage($opipe, 'WON')['id'], [], true);
$pdo->prepare("UPDATE opportunities SET sent_to_accounts_at=? WHERE id=?")->execute([date('c'), $oid]);
$r = $drive($MM, 'opportunity-reopen', 'POST', [], ['id' => $oid, 'reason' => 'Try']);
t_eq((string) $orow()['status'], 'WON', 'a won deal already handed on as an order or contract is not reopened: ' . $said($r));
