<?php
// ============================================================================
//  EXAACT Recruitment — Position master, Org-chart & Manpower-plan validation
//  (Phase 3; brief §12–§14). Additive and non-destructive.
//
//  A Position is a first-class master (name/code, department, business unit,
//  grade, level, reporting line, office, HOD, and the sanctioned / occupied /
//  budgeted headcount). Positions reference one another via `reports_to_id`,
//  giving a reporting hierarchy (org-chart). A Staff Requisition can be linked
//  to a position, and is then validated against the manpower plan — the SRF is
//  never silently bypassed: it is told which of cases A–E it is in (§14).
// ============================================================================

// ---- Schema (additive) -----------------------------------------------------
function position_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS positions (
            id $pk,
            code VARCHAR(40) DEFAULT '',
            name VARCHAR(160) DEFAULT '',
            department VARCHAR(160) DEFAULT '',
            sbu VARCHAR(120) DEFAULT '',
            grade VARCHAR(80) DEFAULT '',
            level VARCHAR(60) DEFAULT '',
            reports_to_id INT NULL,
            office_id INT NULL,
            hod_name VARCHAR(160) DEFAULT '',
            sanctioned_headcount INT DEFAULT 0,
            occupied_headcount INT DEFAULT 0,
            budgeted_headcount INT DEFAULT 0,
            active INT DEFAULT 1,
            created_at VARCHAR(30) DEFAULT ''
        )");
        if (function_exists('act_index')) act_index('positions', 'idx_pos_rep', '(reports_to_id)');
        // Link a requisition to a position (additive; empty = not linked).
        ensure_column('requisitions', 'position_id', 'INT NULL');
    } catch (Throwable $e) { /* never break boot */ }
}

// ---- Reads -----------------------------------------------------------------
function positions_all($activeOnly = true) {
    position_migrate();
    $w = $activeOnly ? "WHERE active=1" : "";
    return ops_all("SELECT * FROM positions $w ORDER BY department, name, id");
}
function position_get($id) {
    position_migrate();
    return ops_one("SELECT * FROM positions WHERE id=?", [(int)$id]) ?: null;
}
function position_vacant($p) {
    return max(0, (int)($p['sanctioned_headcount'] ?? 0) - (int)($p['occupied_headcount'] ?? 0));
}

// The reporting tree: [ ['pos'=>row, 'depth'=>n], … ] in display order.
function positions_tree() {
    $all = positions_all(true);
    $byParent = [];
    foreach ($all as $p) $byParent[(int)$p['reports_to_id']][] = $p;
    $out = [];
    $walk = function ($parentId, $depth) use (&$walk, &$out, $byParent) {
        foreach ($byParent[$parentId] ?? [] as $p) {
            $out[] = ['pos' => $p, 'depth' => $depth];
            $walk((int)$p['id'], $depth + 1);
        }
    };
    $walk(0, 0);                       // roots = reports_to_id 0/null
    // Any positions whose parent is inactive/missing but not a root — surface them.
    $seen = array_column(array_column($out, 'pos'), 'id');
    foreach ($all as $p) if (!in_array($p['id'], $seen)) $out[] = ['pos' => $p, 'depth' => 0];
    return $out;
}

// ---- Writes ----------------------------------------------------------------
function position_save($id, $post) {
    position_migrate();
    $cols = ['code','name','department','sbu','grade','level','hod_name'];
    $vals = []; foreach ($cols as $c) $vals[$c] = trim((string)($post[$c] ?? ''));
    $reports = (int)($post['reports_to_id'] ?? 0) ?: null;
    $office  = (int)($post['office_id'] ?? 0) ?: null;
    $sanc = max(0, (int)($post['sanctioned_headcount'] ?? 0));
    $occ  = max(0, (int)($post['occupied_headcount'] ?? 0));
    $bud  = max(0, (int)($post['budgeted_headcount'] ?? 0));
    if ((int)$id > 0) {
        // A position cannot report to itself.
        if ($reports === (int)$id) $reports = null;
        db()->prepare("UPDATE positions SET code=?,name=?,department=?,sbu=?,grade=?,level=?,hod_name=?,
                       reports_to_id=?,office_id=?,sanctioned_headcount=?,occupied_headcount=?,budgeted_headcount=? WHERE id=?")
            ->execute([...array_values($vals), $reports, $office, $sanc, $occ, $bud, (int)$id]);
        return (int)$id;
    }
    $now = function_exists('now_iso') ? now_iso() : date('c');
    db()->prepare("INSERT INTO positions (code,name,department,sbu,grade,level,hod_name,reports_to_id,office_id,
                   sanctioned_headcount,occupied_headcount,budgeted_headcount,active,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)")
        ->execute([...array_values($vals), $reports, $office, $sanc, $occ, $bud, $now]);
    return (int)db()->lastInsertId();
}
function position_set_active($id, $on) {
    position_migrate();
    db()->prepare("UPDATE positions SET active=? WHERE id=?")->execute([$on ? 1 : 0, (int)$id]);
}

// ============================================================================
//  Import an existing org chart / organogram (paste or CSV) → positions.
//  Reuses the position master; builds the reporting tree from a "Reports to"
//  column resolved by code or by name. Two passes: create/update all rows, then
//  link parents — so order in the file does not matter.
// ============================================================================
const POS_IMPORT_HEADS = [
    'name' => 'name', 'code' => 'code', 'department' => 'department', 'dept' => 'department',
    'grade' => 'grade', 'reports to' => 'reports_to', 'reports_to' => 'reports_to', 'manager' => 'reports_to',
    'reporting to' => 'reports_to', 'sanctioned' => 'sanctioned', 'sanctioned headcount' => 'sanctioned',
    'occupied' => 'occupied', 'occupied headcount' => 'occupied', 'hod' => 'hod',
];
// Turn pasted text (tab- or comma-separated, optional header) into row dicts.
function positions_import_parse($text) {
    $text = trim((string)$text); if ($text === '') return [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $rows = []; $map = null; $order = ['name','code','department','grade','reports_to','sanctioned','occupied'];
    foreach ($lines as $ln) {
        if (trim($ln) === '') continue;
        $delim = (strpos($ln, "\t") !== false) ? "\t" : ',';
        $cells = array_map(fn($c) => trim($c, " \t\"'"), explode($delim, $ln));
        // Header row? (first non-empty line whose cells are known header names)
        if ($map === null) {
            $lc = array_map('strtolower', $cells);
            $known = 0; foreach ($lc as $c) if (isset(POS_IMPORT_HEADS[$c])) $known++;
            if ($known >= 2) { $map = []; foreach ($lc as $i => $c) if (isset(POS_IMPORT_HEADS[$c])) $map[POS_IMPORT_HEADS[$c]] = $i; continue; }
            $map = false;   // no header — fall back to fixed column order
        }
        $get = function ($field) use ($cells, $map, $order) {
            if (is_array($map)) return isset($map[$field]) ? ($cells[$map[$field]] ?? '') : '';
            $idx = array_search($field, $order, true); return $idx !== false ? ($cells[$idx] ?? '') : '';
        };
        $name = trim((string)$get('name'));
        if ($name === '') continue;
        $rows[] = [
            'name' => $name, 'code' => trim((string)$get('code')), 'department' => trim((string)$get('department')),
            'grade' => trim((string)$get('grade')), 'reports_to' => trim((string)$get('reports_to')),
            'hod' => trim((string)$get('hod')),
            'sanctioned' => (int)preg_replace('/\D+/', '', (string)$get('sanctioned')),
            'occupied' => (int)preg_replace('/\D+/', '', (string)$get('occupied')),
        ];
    }
    return $rows;
}
// Apply parsed rows. Returns ['created','updated','linked','unresolved'=>[names]].
function positions_import_apply($rows) {
    position_migrate();
    $res = ['created' => 0, 'updated' => 0, 'linked' => 0, 'unresolved' => []];
    if (!$rows) return $res;
    $byCode = []; $byName = [];
    foreach (positions_all(false) as $p) {
        if (trim((string)$p['code']) !== '') $byCode[strtolower(trim($p['code']))] = (int)$p['id'];
        $byName[strtolower(trim($p['name']))] = (int)$p['id'];
    }
    // Pass 1 — create / update every row (no parent links yet).
    $rowId = [];
    foreach ($rows as $i => $r) {
        $existing = 0;
        if ($r['code'] !== '' && isset($byCode[strtolower($r['code'])])) $existing = $byCode[strtolower($r['code'])];
        elseif (isset($byName[strtolower($r['name'])])) $existing = $byName[strtolower($r['name'])];
        $post = ['code' => $r['code'], 'name' => $r['name'], 'department' => $r['department'],
                 'grade' => $r['grade'], 'hod_name' => $r['hod'],
                 'sanctioned_headcount' => $r['sanctioned'], 'occupied_headcount' => $r['occupied'],
                 'budgeted_headcount' => $r['sanctioned']];
        $id = position_save($existing, $post);
        if ($existing) $res['updated']++; else $res['created']++;
        $rowId[$i] = $id;
        if ($r['code'] !== '') $byCode[strtolower($r['code'])] = $id;
        $byName[strtolower($r['name'])] = $id;
    }
    // Pass 2 — link "reports to" by code or name.
    foreach ($rows as $i => $r) {
        $rt = trim((string)$r['reports_to']); if ($rt === '') continue;
        $pid = $byCode[strtolower($rt)] ?? $byName[strtolower($rt)] ?? 0;
        if (!$pid || $pid === $rowId[$i]) { if (!$pid) $res['unresolved'][] = $r['name'] . ' → ' . $rt; continue; }
        db()->prepare("UPDATE positions SET reports_to_id=? WHERE id=?")->execute([$pid, $rowId[$i]]);
        $res['linked']++;
    }
    return $res;
}

// ============================================================================
//  Manpower-plan validation (§14) — never a silent bypass.
// ============================================================================
function position_manpower_check($pos, $qty, $reqType = 'NEW') {
    $qty = max(1, (int)$qty);
    if (!$pos) return ['case' => 'C', 'tone' => 'warn', 'title' => 'New position',
        'msg' => 'This requisition is not linked to a position in the master — a new-position approval is required before hiring.'];

    $sanc = (int)($pos['sanctioned_headcount'] ?? 0);
    $occ  = (int)($pos['occupied_headcount'] ?? 0);
    $bud  = (int)($pos['budgeted_headcount'] ?? 0);
    $vacant = max(0, $sanc - $occ);

    // Case D — a replacement fills a seat being vacated; headcount is unchanged.
    if (strtoupper((string)$reqType) === 'REPLACEMENT')
        return ['case' => 'D', 'tone' => 'info', 'title' => 'Replacement',
            'msg' => 'Replacement for a departed employee — link the outgoing employee. Sanctioned headcount is unchanged.'];

    // Case E — over the budgeted headcount → financial approval.
    if ($bud > 0 && ($occ + $qty) > $bud)
        return ['case' => 'E', 'tone' => 'bad', 'title' => 'Over budgeted headcount',
            'msg' => "Filling $qty would take this position to " . ($occ + $qty) . " against a budget of $bud — financial approval is required."];

    // Case B — sanctioned headcount full, or not enough vacancy → escalate.
    if ($vacant <= 0)
        return ['case' => 'B', 'tone' => 'bad', 'title' => 'Sanctioned headcount full',
            'msg' => "All $sanc sanctioned seat(s) are occupied. Raising this requisition needs escalation/approval."];
    if ($vacant < $qty)
        return ['case' => 'B', 'tone' => 'warn', 'title' => 'Not enough vacancy',
            'msg' => "Only $vacant of $qty requested seat(s) are vacant — the excess needs escalation/approval."];

    // Case A — proceed.
    return ['case' => 'A', 'tone' => 'ok', 'title' => 'Vacancy available',
        'msg' => "$vacant vacancy(ies) available against $sanc sanctioned. Proceed."];
}

// ============================================================================
//  Admin screen — Position master (list + form), gated is_coordinator_level()
// ============================================================================
function ops_positions($route, $method) {
    position_migrate();

    if ($route === 'positions-org') {
        ops_require(can('mod.hiring.view'), 'You cannot view the org chart.');
        view('ops/positions_org', ['tree' => positions_tree()]);
        return true;
    }

    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage positions.');

    if ($route === 'positions-import') {
        $result = null; $preview = null; $raw = '';
        if ($method === 'POST') {
            $raw = (string)($_POST['data'] ?? '');
            if ($raw === '' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
                $raw = (string)@file_get_contents($_FILES['file']['tmp_name']);
            $rows = positions_import_parse($raw);
            if ((string)($_POST['do'] ?? '') === 'apply') {
                $result = positions_import_apply($rows);
                flash("Org chart imported — {$result['created']} created, {$result['updated']} updated, {$result['linked']} linked.");
                redirect('/positions-org'); return true;
            }
            $preview = $rows;   // "Preview" — show what was parsed before committing
        }
        view('ops/positions_import', ['preview' => $preview, 'raw' => $raw]);
        return true;
    }

    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'save') { $id = position_save((int)($_POST['id'] ?? 0), $_POST); flash('Position saved.'); redirect('/positions?id=' . $id); return true; }
        if ($do === 'toggle') { $p = position_get((int)($_POST['id'] ?? 0)); if ($p) position_set_active($p['id'], (int)$p['active'] === 0); flash('Position updated.'); redirect('/positions'); return true; }
    }

    $selId = (int)($_GET['id'] ?? 0);
    $sel = $selId ? position_get($selId) : null;
    view('ops/positions', [
        'positions' => positions_all(false),
        'sel'       => $sel,
        'offices'   => function_exists('offices_list') ? offices_list() : [],
    ]);
    return true;
}

// Set / clear the position a requisition is raised against (from its detail).
function ops_requisition_position($route, $method) {
    position_migrate();
    ops_require(is_coordinator_level(), 'Only coordinators / administrators can set the position.');
    $id = (int)($_GET['id'] ?? 0);
    $req = ops_one("SELECT * FROM requisitions WHERE id=?", [$id]);
    if (!$req) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        $pid = (int)($_POST['position_id'] ?? 0) ?: null;
        db()->prepare("UPDATE requisitions SET position_id=? WHERE id=?")->execute([$pid, $id]);
        flash($pid ? 'Position linked — the manpower check is updated.' : 'Position cleared.');
    }
    redirect('/requisition?id=' . $id);
    return true;
}

// ---- The manpower-validation + position panel on a requisition detail ------
function position_requisition_panel($req) {
    if (!is_array($req) || empty($req['id'])) return;
    position_migrate();
    $pos = !empty($req['position_id']) ? position_get((int)$req['position_id']) : null;
    $qty = (int)($req['quantity'] ?? 1) ?: 1;
    $chk = position_manpower_check($pos, $qty, (string)($req['req_type'] ?? 'NEW'));
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $toneCol = ['ok' => 'var(--green,#16a34a)', 'warn' => 'var(--amber,#d97706)', 'bad' => 'var(--red,#dc2626)', 'info' => 'var(--brand,#1e40af)'][$chk['tone']] ?? '#64748b';
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $positions = positions_all(true);
    ?>
    <div class="panel" style="border-left:4px solid <?= $toneCol ?>;padding:13px 16px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:baseline">
        <b style="font-size:13.5px">Manpower plan — Case <?= $e($chk['case']) ?>: <span style="color:<?= $toneCol ?>"><?= $e($chk['title']) ?></span></b>
        <span class="muted" style="font-size:12px"><?= $pos ? 'Position: ' . $e($pos['name']) . ($pos['code'] ? ' (' . $e($pos['code']) . ')' : '') : 'No position linked' ?></span>
      </div>
      <div style="font-size:12.5px;margin-top:5px"><?= $e($chk['msg']) ?></div>
      <?php if ($pos): ?>
      <div class="muted" style="font-size:12px;margin-top:6px">
        Sanctioned <b><?= (int)$pos['sanctioned_headcount'] ?></b> · Occupied <b><?= (int)$pos['occupied_headcount'] ?></b> · Vacant <b><?= position_vacant($pos) ?></b><?= (int)$pos['budgeted_headcount'] ? ' · Budgeted <b>' . (int)$pos['budgeted_headcount'] . '</b>' : '' ?>
        <?= $pos['department'] ? ' · ' . $e($pos['department']) : '' ?><?= $pos['grade'] ? ' · Grade ' . $e($pos['grade']) : '' ?>
      </div>
      <?php endif; ?>
      <?php if ($can): ?>
      <form method="post" action="/requisition-position?id=<?= (int)$req['id'] ?>" style="display:flex;gap:7px;align-items:center;margin-top:9px;flex-wrap:wrap">
        <select name="position_id" style="min-width:220px;padding:6px 9px;border:1px solid var(--line,#d7dde5);border-radius:7px">
          <option value="0">— not linked —</option>
          <?php foreach ($positions as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)($req['position_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= $e($p['name']) ?><?= $p['department'] ? ' · ' . $e($p['department']) : '' ?> (vac <?= position_vacant($p) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="btn secondary" style="padding:6px 12px">Link position</button>
        <a class="muted" style="font-size:12px" href="/positions">Manage positions →</a>
      </form>
      <?php endif; ?>
    </div>
    <?php
}
