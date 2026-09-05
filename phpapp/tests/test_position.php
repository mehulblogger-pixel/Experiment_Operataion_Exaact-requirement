<?php
// Phase 3 — Position master, org-chart & manpower-plan validation (§12–§14).
t_section('position master & manpower validation (Phase 3)');

position_migrate();

// --- Schema is additive ---
$cols = array_map(fn($r) => $r['name'] ?? $r[1] ?? '', ops_all("PRAGMA table_info(requisitions)"));
t_ok(in_array('position_id', $cols, true), 'requisitions gains an additive position_id column');

// --- CRUD ---
$ceo = position_save(0, ['name' => 'Head of Finance', 'code' => 'FIN-HEAD', 'department' => 'Finance', 'grade' => 'VP', 'sanctioned_headcount' => 1, 'occupied_headcount' => 1]);
$acc = position_save(0, ['name' => 'Accounts Executive', 'code' => 'ACC-EXE', 'department' => 'Finance', 'grade' => 'SENIOR',
    'reports_to_id' => $ceo, 'sanctioned_headcount' => 5, 'occupied_headcount' => 3, 'budgeted_headcount' => 4]);
t_ok($ceo > 0 && $acc > 0, 'positions are created');
$p = position_get($acc);
t_eq((int)$p['sanctioned_headcount'], 5, 'sanctioned headcount is stored');
t_eq(position_vacant($p), 2, 'vacant = sanctioned − occupied');
t_eq((int)$p['reports_to_id'], $ceo, 'a reporting line is stored');

// --- Manpower check: the five cases (§14) ---
// A no-budget position so the vacancy path (Case B) can be reached.
$nb = position_save(0, ['name' => 'Field Officer', 'department' => 'Ops', 'sanctioned_headcount' => 5, 'occupied_headcount' => 3, 'budgeted_headcount' => 0]);
$nbP = position_get($nb);
t_eq(position_manpower_check(null, 1, 'NEW')['case'], 'C', 'Case C — no position linked → new-position approval');
t_eq(position_manpower_check($p, 1, 'NEW')['case'], 'A', 'Case A — a vacancy exists → proceed');
t_eq(position_manpower_check($nbP, 3, 'NEW')['case'], 'B', 'Case B — only 2 vacant but 3 asked → escalate');
$full = position_get($ceo);
t_eq(position_manpower_check($full, 1, 'NEW')['case'], 'B', 'Case B — sanctioned headcount full → escalate');
t_eq(position_manpower_check($p, 2, 'REPLACEMENT')['case'], 'D', 'Case D — replacement → link the outgoing employee');
// occupied 3 + qty 2 = 5 > budget 4 → Case E (finance) takes priority over vacancy
t_eq(position_manpower_check($p, 2, 'NEW')['case'], 'E', 'Case E — exceeds budgeted headcount → financial approval');

// --- Org tree ---
$tree = positions_tree();
$byId = [];
foreach ($tree as $n) $byId[(int)$n['pos']['id']] = $n['depth'];
t_eq($byId[$ceo] ?? -1, 0, 'the head sits at the top of the tree (depth 0)');
t_eq($byId[$acc] ?? -1, 1, 'a report sits one level down (depth 1)');

// --- Requisition link drives the check ---
db()->prepare("INSERT INTO requisitions (req_code,designation,req_type,position_id,status,created_at) VALUES ('SRF-P',?,?,?,'OPEN',?)")
    ->execute(['Accounts Executive', 'NEW', $acc, date('c')]);
$req = ops_one("SELECT * FROM requisitions WHERE req_code='SRF-P'");
$linked = position_get((int)$req['position_id']);
t_eq(position_manpower_check($linked, (int)($req['quantity'] ?? 1), $req['req_type'])['case'], 'A',
    'a requisition linked to a position with a vacancy validates as Case A');

// --- Disable keeps data (never delete) ---
position_set_active($acc, false);
t_ok(count(positions_all(true)) < count(positions_all(false)), 'disabling hides a position but never deletes it');
