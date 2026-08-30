<?php
// ============================================================================
//  CONNECT — Agency Bench workspace  (slice K18 / backlog #7, additive)
//
//  Turns an agency from a mere applicant into a FULFILLER. A manpower/staffing
//  agency keeps its own roster of people — its "bench" — and allocates them to
//  the requirements it is fulfilling.
//
//  THE PRIVACY INVARIANT (established with the owner): an agency's own employees
//  are PRIVATE. Bench people live in their OWN table, scoped to the agency org,
//  and are NEVER written into the shared self-registered pool (cx_professionals)
//  or surfaced in public search / the shared recommender. The shared pool is only
//  individuals who self-upload; the bench is the agency's private workforce.
//
//  ADDITIVE CONTRACT: new tables only (cx_bench, cx_bench_alloc), cx_* namespaced;
//  reuses cx_organisations (the agency) and cx_requirements / cx_positions (the
//  demand it fulfils). No new named permission — the marketplace desk gate. No
//  existing route, view, permission or object status is changed.
// ============================================================================

function connect_bench_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // The agency's private roster. org_id scopes every row to one agency.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_bench (
        id $pk, org_id INT DEFAULT 0,
        name VARCHAR(150) DEFAULT '', role_code VARCHAR(40) DEFAULT '', job_title VARCHAR(160) DEFAULT '',
        skills VARCHAR(600) DEFAULT '', discipline_code VARCHAR(40) DEFAULT '', base_city VARCHAR(120) DEFAULT '',
        availability VARCHAR(16) DEFAULT 'AVAILABLE',   -- AVAILABLE | ALLOCATED | OFF
        day_rate REAL DEFAULT 0, is_active INT DEFAULT 1,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    // Which bench person fills which requirement (and optionally which position).
    db()->exec("CREATE TABLE IF NOT EXISTS cx_bench_alloc (
        id $pk, org_id INT DEFAULT 0, bench_id INT DEFAULT 0,
        requirement_id INT DEFAULT 0, position_id INT DEFAULT 0,
        status VARCHAR(12) DEFAULT 'PROPOSED',   -- PROPOSED | CONFIRMED | RELEASED
        note VARCHAR(300) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_bench_org ON cx_bench (org_id, is_active)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_bench_alloc ON cx_bench_alloc (org_id, requirement_id)"); } catch (Throwable $e) {}
    // Additive: a bench entry may be linked to a full professional passport
    // (cx_professionals) so the agency's roster is taxonomy-searchable and shares
    // one identity with the marketplace; plus an internal cost rate (for margin,
    // staff-only) and the association type. All optional; the flat roster still works.
    if (function_exists('ensure_column')) {
        try { ensure_column('cx_bench', 'professional_id', "INT DEFAULT 0"); }       catch (Throwable $e) {}
        try { ensure_column('cx_bench', 'association', "VARCHAR(24) DEFAULT ''"); }   catch (Throwable $e) {}
        try { ensure_column('cx_bench', 'cost_rate', "REAL DEFAULT 0"); }            catch (Throwable $e) {}
        try { ensure_column('cx_bench', 'available_from', "VARCHAR(20) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('cx_bench_alloc', 'client_rate', "REAL DEFAULT 0"); }      catch (Throwable $e) {}
        try { ensure_column('cx_bench_alloc', 'professional_id', "INT DEFAULT 0"); }   catch (Throwable $e) {}
    }
}

/** Resolve a bench entry to its linked professional passport (or 0). */
function connect_bench_professional($benchId) {
    connect_bench_migrate();
    return (int)ops_val("SELECT professional_id FROM cx_bench WHERE id=?", [(int)$benchId]);
}

/** The active manpower/staffing agencies that can hold a bench. */
function connect_bench_agencies() {
    connect_bench_migrate();
    try {
        return ops_all("SELECT id, name, org_type FROM cx_organisations
                        WHERE org_type IN ('MANPOWER_AGENCY','RECRUITMENT_AGENCY') AND COALESCE(status,'ACTIVE')='ACTIVE'
                        ORDER BY name") ?: [];
    } catch (Throwable $e) { return []; }
}

/** Is this org an agency that may keep a bench? */
function connect_bench_org_ok($orgId) {
    try {
        $t = (string)ops_val("SELECT org_type FROM cx_organisations WHERE id=?", [(int)$orgId]);
        return in_array($t, ['MANPOWER_AGENCY', 'RECRUITMENT_AGENCY'], true);
    } catch (Throwable $e) { return false; }
}

// ---- Bench roster (private to the agency) -----------------------------------

/** Add a person to an agency's private bench. Returns [ok, msg, id]. */
function connect_bench_add($orgId, array $in) {
    connect_bench_migrate();
    $orgId = (int)$orgId;
    if (!connect_bench_org_ok($orgId)) return [false, 'Pick a valid staffing / recruitment agency first.', 0];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') return [false, 'A name is required.', 0];
    $av = strtoupper((string)($in['availability'] ?? 'AVAILABLE'));
    if (!in_array($av, ['AVAILABLE', 'ALLOCATED', 'OFF'], true)) $av = 'AVAILABLE';
    db()->prepare("INSERT INTO cx_bench (org_id,name,role_code,job_title,skills,discipline_code,base_city,availability,day_rate,is_active,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,1,?,?)")
        ->execute([$orgId, $name, (string)($in['role_code'] ?? ''), trim((string)($in['job_title'] ?? '')),
                   trim((string)($in['skills'] ?? '')), (string)($in['discipline_code'] ?? ''), trim((string)($in['base_city'] ?? '')),
                   $av, (float)($in['day_rate'] ?? 0), date('c'), date('c')]);
    return [true, 'Added to the bench.', (int)db()->lastInsertId()];
}

/** One bench row (scoped to its org for safety), or null. */
function connect_bench_get($id, $orgId = 0) {
    connect_bench_migrate();
    try {
        if ($orgId > 0) return ops_one("SELECT * FROM cx_bench WHERE id=? AND org_id=?", [(int)$id, (int)$orgId]) ?: null;
        return ops_one("SELECT * FROM cx_bench WHERE id=?", [(int)$id]) ?: null;
    } catch (Throwable $e) { return null; }
}

/** Edit a bench person. */
function connect_bench_update($id, $orgId, array $in) {
    $row = connect_bench_get($id, $orgId);
    if (!$row) return [false, 'Not found.'];
    $name = trim((string)($in['name'] ?? $row['name']));
    if ($name === '') return [false, 'A name is required.'];
    db()->prepare("UPDATE cx_bench SET name=?, role_code=?, job_title=?, skills=?, discipline_code=?, base_city=?, day_rate=?, updated_at=? WHERE id=? AND org_id=?")
        ->execute([$name, (string)($in['role_code'] ?? $row['role_code']), trim((string)($in['job_title'] ?? $row['job_title'])),
                   trim((string)($in['skills'] ?? $row['skills'])), (string)($in['discipline_code'] ?? $row['discipline_code']),
                   trim((string)($in['base_city'] ?? $row['base_city'])), (float)($in['day_rate'] ?? $row['day_rate']),
                   date('c'), (int)$id, (int)$orgId]);
    return [true, 'Updated.'];
}

/** Switch a bench person on/off (soft — keeps their allocation history). */
function connect_bench_toggle($id, $orgId) {
    $row = connect_bench_get($id, $orgId);
    if (!$row) return [false, 'Not found.'];
    $to = (int)($row['is_active'] ?? 1) === 1 ? 0 : 1;
    db()->prepare("UPDATE cx_bench SET is_active=?, updated_at=? WHERE id=? AND org_id=?")->execute([$to, date('c'), (int)$id, (int)$orgId]);
    return [true, $to ? 'Back on the bench.' : 'Taken off the bench.'];
}

/** An agency's bench roster. Active-only by default. */
function connect_bench_list($orgId, $activeOnly = true) {
    connect_bench_migrate();
    $w = $activeOnly ? "AND COALESCE(is_active,1)=1" : '';
    try {
        $st = db()->prepare("SELECT * FROM cx_bench WHERE org_id=? $w ORDER BY name");
        $st->execute([(int)$orgId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** Bench utilisation for an agency: total / available / allocated / off. */
function connect_bench_summary($orgId) {
    connect_bench_migrate();
    $out = ['total' => 0, 'available' => 0, 'allocated' => 0, 'off' => 0];
    foreach (connect_bench_list($orgId, false) as $b) {
        $out['total']++;
        if ((int)($b['is_active'] ?? 1) !== 1) { $out['off']++; continue; }
        if (strtoupper((string)$b['availability']) === 'ALLOCATED') $out['allocated']++;
        else $out['available']++;
    }
    return $out;
}

// ---- Allocation (supply → demand) -------------------------------------------

/**
 * Allocate a bench person to a requirement (optionally a specific crew position).
 * Marks them ALLOCATED. Guards: the person is this agency's, active, and not
 * already actively allocated to the same requirement. Returns [ok, msg, id].
 */
function connect_bench_allocate($benchId, $orgId, $requirementId, $positionId = 0, $note = '') {
    connect_bench_migrate();
    $b = connect_bench_get($benchId, $orgId);
    if (!$b) return [false, 'That bench member is not in this agency.', 0];
    if ((int)($b['is_active'] ?? 1) !== 1) return [false, 'That person is off the bench.', 0];
    $requirementId = (int)$requirementId;
    if ($requirementId <= 0 || !function_exists('cx_requirement_get') || !cx_requirement_get($requirementId))
        return [false, 'Pick a valid requirement to fulfil.', 0];
    $dupe = (int)ops_val("SELECT COUNT(*) FROM cx_bench_alloc WHERE bench_id=? AND requirement_id=? AND status<>'RELEASED'",
        [(int)$benchId, $requirementId]);
    if ($dupe > 0) return [false, 'Already allocated to that requirement.', 0];
    db()->prepare("INSERT INTO cx_bench_alloc (org_id,bench_id,requirement_id,position_id,status,note,created_at,updated_at)
                   VALUES (?,?,?,?, 'PROPOSED', ?, ?, ?)")
        ->execute([(int)$orgId, (int)$benchId, $requirementId, (int)$positionId, trim((string)$note), date('c'), date('c')]);
    $id = (int)db()->lastInsertId();
    db()->prepare("UPDATE cx_bench SET availability='ALLOCATED', updated_at=? WHERE id=? AND org_id=?")->execute([date('c'), (int)$benchId, (int)$orgId]);
    return [true, 'Allocated to the requirement.', $id];
}

/** Move an allocation to CONFIRMED or RELEASED. RELEASED frees the person. */
function connect_bench_alloc_set($allocId, $orgId, $status) {
    connect_bench_migrate();
    $status = strtoupper((string)$status);
    if (!in_array($status, ['PROPOSED', 'CONFIRMED', 'RELEASED'], true)) return [false, 'Invalid status.'];
    $a = ops_one("SELECT * FROM cx_bench_alloc WHERE id=? AND org_id=?", [(int)$allocId, (int)$orgId]);
    if (!$a) return [false, 'Allocation not found.'];
    db()->prepare("UPDATE cx_bench_alloc SET status=?, updated_at=? WHERE id=? AND org_id=?")->execute([$status, date('c'), (int)$allocId, (int)$orgId]);
    // A RELEASED person returns to the bench (unless still allocated elsewhere).
    if ($status === 'RELEASED') {
        $stillOn = (int)ops_val("SELECT COUNT(*) FROM cx_bench_alloc WHERE bench_id=? AND status<>'RELEASED'", [(int)$a['bench_id']]);
        if ($stillOn === 0) db()->prepare("UPDATE cx_bench SET availability='AVAILABLE', updated_at=? WHERE id=?")->execute([date('c'), (int)$a['bench_id']]);
    }
    return [true, 'Allocation ' . strtolower($status) . '.'];
}

/** Allocations for an agency, joined to bench + requirement, newest first. */
function connect_bench_allocs_for_org($orgId, $activeOnly = true) {
    connect_bench_migrate();
    $w = $activeOnly ? "AND a.status<>'RELEASED'" : '';
    try {
        $st = db()->prepare("SELECT a.*, b.name AS bench_name, b.job_title, r.ref_code, r.title AS req_title, r.status AS req_status
                             FROM cx_bench_alloc a
                             JOIN cx_bench b ON b.id=a.bench_id
                             LEFT JOIN cx_requirements r ON r.id=a.requirement_id
                             WHERE a.org_id=? $w ORDER BY a.id DESC");
        $st->execute([(int)$orgId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** Allocations on a requirement (used by the requirement desk). */
function connect_bench_allocs_for_requirement($requirementId) {
    connect_bench_migrate();
    try {
        $st = db()->prepare("SELECT a.*, b.name AS bench_name, b.job_title, o.name AS agency_name
                             FROM cx_bench_alloc a JOIN cx_bench b ON b.id=a.bench_id
                             LEFT JOIN cx_organisations o ON o.id=a.org_id
                             WHERE a.requirement_id=? AND a.status<>'RELEASED' ORDER BY a.id");
        $st->execute([(int)$requirementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

// ---- Gate + the staff-desk workspace ----------------------------------------

/** Manage gate — the marketplace desk (coordinator level). No new permission. */
function connect_bench_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** The agency-bench workspace: pick an agency, manage its private bench, allocate. */
function ops_connect_bench($method) {
    ops_require(connect_bench_can(), 'The agency bench workspace is available to the coordinator desk.');
    connect_bench_migrate();
    $orgId = (int)($_GET['org'] ?? $_POST['org_id'] ?? 0);

    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'add')            [$ok, $msg] = connect_bench_add($orgId, $_POST);
        elseif ($act === 'update')     [$ok, $msg] = connect_bench_update((int)($_POST['id'] ?? 0), $orgId, $_POST);
        elseif ($act === 'toggle')     [$ok, $msg] = connect_bench_toggle((int)($_POST['id'] ?? 0), $orgId);
        elseif ($act === 'allocate')   [$ok, $msg] = connect_bench_allocate((int)($_POST['bench_id'] ?? 0), $orgId, (int)($_POST['requirement_id'] ?? 0), (int)($_POST['position_id'] ?? 0), (string)($_POST['note'] ?? ''));
        elseif ($act === 'alloc_set')  [$ok, $msg] = connect_bench_alloc_set((int)($_POST['alloc_id'] ?? 0), $orgId, (string)($_POST['status'] ?? ''));
        else { $ok = false; $msg = 'Unknown action.'; }
        flash($msg, $ok ? 'success' : 'error');
        redirect('/connect-bench' . ($orgId ? '?org=' . $orgId : ''));
    }

    $agencies = connect_bench_agencies();
    if (!$orgId && $agencies) $orgId = (int)$agencies[0]['id'];

    // Requirements this agency can fulfil (open / shortlisting / awarded).
    $reqs = [];
    try { $reqs = ops_all("SELECT id, ref_code, title, status FROM cx_requirements
                           WHERE status IN ('OPEN','SHORTLISTING','AWARDED') ORDER BY id DESC LIMIT 60") ?: []; }
    catch (Throwable $e) {}

    view('ops/connect_bench', [
        'agencies' => $agencies,
        'orgId'    => $orgId,
        'org'      => $orgId ? ops_one("SELECT * FROM cx_organisations WHERE id=?", [$orgId]) : null,
        'bench'    => $orgId ? connect_bench_list($orgId, false) : [],
        'summary'  => $orgId ? connect_bench_summary($orgId) : ['total' => 0, 'available' => 0, 'allocated' => 0, 'off' => 0],
        'allocs'   => $orgId ? connect_bench_allocs_for_org($orgId, false) : [],
        'reqs'     => $reqs,
        'roles'    => function_exists('connect_qtx_rows') ? connect_qtx_rows('cx_roles') : [],
    ]);
    return true;
}
