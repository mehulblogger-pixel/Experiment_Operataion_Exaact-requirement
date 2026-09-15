<?php
// ============================================================================
//  EXAACT — HIRING REQUEST  (Phase 2 · M4)
//
//  The audit found one thing genuinely missing: a REQUEST LAYER.
//
//  A requisition is created directly, already OPEN — a status whose own label
//  reads "Open (approved, sourcing)" — and candidates can be attached to it at
//  once. Nothing distinguished "we would like to hire" from "this is approved,
//  start sourcing". That distinction is what this adds, and it is the only new
//  object M4 introduces.
//
//  WHAT THIS IS NOT. A hiring request is not a requisition, not a position, not
//  a candidate and not an approval. It is the business request to recruit:
//
//      Requestor -> Hiring Request -> [Phase 3 approval] -> Requisition -> execution
//
//  WHAT IS REUSED RATHER THAN REBUILT:
//    · Department      the canonical vocabulary from M2/M3 (identity, not text)
//    · Designation     the canonical vocabulary
//    · Position        the establishment, with its five-case manpower check
//    · Quantity        M3's model; see the note on authority below
//    · Branch          offices + the existing scope architecture
//    · Custom fields   the existing engine, entity 'hiring_request'
//    · Approval        recruit_approval_requests is entity-agnostic, so Phase 3
//                      plugs in without a new engine. M4 adds the hooks only.
//
//  QUANTITY AUTHORITY. Two numbers, because they are two facts:
//      hiring_requests.quantity   how many were ASKED for
//      requisitions.quantity      how many are being EXECUTED (M3's, unchanged)
//  Approval may cut ten to six; both numbers then matter, and neither is a copy
//  of the other. No third quantity exists.
// ============================================================================

// The request's own lifecycle — deliberately NOT the requisition's. A requisition
// says how execution is going; a request says whether execution may begin.
const HREQ_STATUS = [
    'DRAFT'        => 'Draft',
    'SUBMITTED'    => 'Submitted',
    'UNDER_REVIEW' => 'Under review',
    'APPROVED'     => 'Approved',
    'REJECTED'     => 'Rejected',
    'CANCELLED'    => 'Cancelled',
];
// The states from which recruitment may begin. Phase 3 will enforce the routing
// that reaches APPROVED; M4 only has to make the boundary expressible.
const HREQ_EXECUTABLE = ['APPROVED'];

// Starter vocabularies. Each is created through the EXISTING lookup engine, so a
// customer can extend it on day one — none of these lists existed before.
const HREQ_PRIORITIES = [
    'LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High',
    'URGENT' => 'Urgent', 'CRITICAL' => 'Critical',
];
const HREQ_EMPLOYMENT_TYPES = [
    'PERMANENT' => 'Permanent', 'CONTRACT' => 'Fixed-term contract',
    'TEMPORARY' => 'Temporary', 'PART_TIME' => 'Part time',
    'CONSULTANT' => 'Consultant / freelance', 'INTERN' => 'Intern / trainee',
];
// §8 asks for these request types. The application already has a configurable
// `requisition_type` list holding NEW and REPLACEMENT, so these EXTEND it rather
// than starting a second vocabulary for the same concept.
const HREQ_EXTRA_REQUEST_TYPES = [
    'ADDITIONAL_MANPOWER' => 'Additional manpower (same role, more people)',
    'TEMPORARY'           => 'Temporary / short-term need',
    'PROJECT'             => 'Project-specific requirement',
    'BACKFILL'            => 'Backfill (cover for leave / secondment)',
    'CONTRACT'            => 'Against a client contract',
    'OTHER'               => 'Other',
];

function hreq_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = (function_exists('db_driver') && db_driver() === 'mysql')
        ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS hiring_requests (
            id $pk,
            req_no VARCHAR(40) DEFAULT '',
            status VARCHAR(20) DEFAULT 'DRAFT',

            -- WHO is asking. A canonical identity, not a typed-in name (§6).
            requested_by_id INT NULL,
            requested_by_name VARCHAR(150) DEFAULT '',   -- display only; history keeps its own words
            requesting_department_id INT NULL,           -- the department ASKING

            -- WHAT is needed.
            hiring_department_id INT NULL,               -- the department the person will JOIN
            designation VARCHAR(60) DEFAULT '',
            grade VARCHAR(80) DEFAULT '',
            position_id INT NULL,                        -- an existing establishment seat…
            new_position_requested INT DEFAULT 0,        -- …or none, and one is being asked for (§15)
            job_title VARCHAR(200) DEFAULT '',
            job_description TEXT,                        -- specific to THIS request (§11)
            quantity INT DEFAULT 1,

            -- WHERE.
            office_id INT NULL,
            work_location VARCHAR(200) DEFAULT '',
            client_id INT NULL,
            project_ref VARCHAR(200) DEFAULT '',

            -- WHEN and HOW.
            required_by VARCHAR(20) DEFAULT '',
            employment_type VARCHAR(30) DEFAULT '',
            request_type VARCHAR(40) DEFAULT '',
            priority VARCHAR(20) DEFAULT 'NORMAL',

            -- WHY.
            reason VARCHAR(400) DEFAULT '',

            -- Approval boundary. M4 records the state; Phase 3 does the routing.
            approval_required INT DEFAULT 1,
            approval_ref VARCHAR(80) DEFAULT '',
            decided_by VARCHAR(150) DEFAULT '',
            decided_at VARCHAR(30) DEFAULT '',
            decision_note VARCHAR(400) DEFAULT '',

            -- What it meant when it was submitted, so a later master edit cannot
            -- silently change the business meaning of an approved request (§12).
            snapshot_json TEXT,
            submitted_at VARCHAR(30) DEFAULT '',

            created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '',
            updated_by VARCHAR(150) DEFAULT '',
            updated_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_hreq_status ON hiring_requests (status)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_hreq_office ON hiring_requests (office_id)");
        db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_hreq_no ON hiring_requests (req_no)");
    } catch (Throwable $e) {}

    // The execution record points back at the request it came from. Additive:
    // a requisition raised directly carries NULL, exactly as it always has (§19).
    if (function_exists('ensure_column')) {
        try { ensure_column('requisitions', 'hiring_request_id', 'INT NULL'); } catch (Throwable $e) {}
    }

    // Vocabularies, through the engine that already exists.
    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('hr_priority', 'Hiring priority', HREQ_PRIORITIES, 'People');
            lk_ensure_type_map('employment_type', 'Employment type', HREQ_EMPLOYMENT_TYPES, 'People');
            lk_ensure_type_map('hiring_request_status', 'Hiring request status', HREQ_STATUS, 'People');
        } catch (Throwable $e) {}
    }
    // §8 — extend the request-type list the application already has.
    if (function_exists('lk_type') && function_exists('lk_ensure_value')) {
        try {
            if (lk_type('requisition_type'))
                foreach (HREQ_EXTRA_REQUEST_TYPES as $c => $l) lk_ensure_value('requisition_type', $c, $l);
        } catch (Throwable $e) {}
    }
}

function hreq_priorities()       { return lk_options_or('hr_priority', HREQ_PRIORITIES); }
function hreq_employment_types() { return lk_options_or('employment_type', HREQ_EMPLOYMENT_TYPES); }
function hreq_statuses()         { return lk_options_or('hiring_request_status', HREQ_STATUS); }
// The union of what the workspace's own list holds and the types M4 adds.
// hreq_migrate() persists the additions, but the lookup list is cached for the
// life of a request — so on the very request that first creates them, reading
// the list alone would still show the old two. The union makes it immediate and
// is a no-op from the next request onward.
function hreq_request_types() {
    $live = lk_options_or('requisition_type', defined('REQ_TYPES') ? REQ_TYPES : []);
    return $live + HREQ_EXTRA_REQUEST_TYPES;
}

function hreq_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function hreq_who() {
    if (!function_exists('current_user')) return '';
    $u = current_user(); if (!$u) return '';
    return function_exists('user_name') ? (string) user_name($u) : (string) ($u['username'] ?? '');
}

function hreq_get($id) {
    hreq_migrate();
    try { $r = ops_one("SELECT * FROM hiring_requests WHERE id=?", [(int) $id]); }
    catch (Throwable $e) { return null; }
    return $r ?: null;
}

// Its own stable reference, distinct from the requisition's (§21, §22).
function hreq_next_no() {
    hreq_migrate();
    $yr = date('Y');
    try {
        $last = ops_val("SELECT req_no FROM hiring_requests WHERE req_no LIKE ? ORDER BY req_no DESC LIMIT 1", ["HRQ-$yr-%"]);
    } catch (Throwable $e) { $last = null; }
    $seq = ($last && preg_match('/-(\d{6})$/', (string) $last, $m)) ? (int) $m[1] + 1 : 1;
    return sprintf('HRQ-%s-%06d', $yr, $seq);
}

// May recruitment begin against this request? The whole point of the layer.
function hreq_is_executable($req) {
    $r = is_array($req) ? $req : hreq_get($req);
    if (!$r) return false;
    if (empty($r['approval_required'])) return true;          // a workspace that does not require approval
    return in_array(strtoupper((string) $r['status']), HREQ_EXECUTABLE, true);
}

// ---- Save -----------------------------------------------------------------
// Returns [ok, message, id]. Validates against the real masters rather than
// trusting what the browser sent — a dropdown is not a security boundary (§37).
function hreq_save($id, array $post) {
    hreq_migrate();
    $id = (int) $id;
    $existing = $id > 0 ? hreq_get($id) : null;
    if ($id > 0 && !$existing) return [false, 'That hiring request no longer exists.', 0];

    // Once approved, the business meaning is protected (§33). Phase 3 will add
    // the re-approval path; M4 has to make the boundary real.
    if ($existing && strtoupper((string) $existing['status']) === 'APPROVED')
        return [false, 'This request is approved. Changing it needs a re-approval, which is not built yet.', 0];
    if ($existing && in_array(strtoupper((string) $existing['status']), ['REJECTED', 'CANCELLED'], true))
        return [false, 'A ' . strtolower($existing['status']) . ' request cannot be edited.', 0];

    $qty = (int) ($post['quantity'] ?? 1);
    if ($qty <= 0) return [false, 'How many people are needed? Enter at least one.', 0];

    $title = trim((string) ($post['job_title'] ?? ''));
    if ($title === '') return [false, 'Say what is being requested.', 0];

    // Requestor — a canonical identity, checked against the register (§6, §43).
    $byId = (int) ($post['requested_by_id'] ?? 0) ?: null;
    if ($byId) {
        try { $u = ops_one("SELECT id, is_active FROM users WHERE id=?", [$byId]); } catch (Throwable $e) { $u = null; }
        if (!$u) return [false, 'That requestor is not someone in this workspace.', 0];
    }
    // Departments — resolved through the canonical vocabulary, never free text.
    $deptId = function ($v) {
        $v = trim((string) $v); if ($v === '') return null;
        if (ctype_digit($v)) { $row = function_exists('vocab_value') ? vocab_value((int) $v) : null; }
        else { $row = function_exists('dept_of') ? dept_of($v) : null; }
        return $row ? (int) $row['id'] : null;
    };
    $reqDept = $deptId($post['requesting_department_id'] ?? '');
    $hirDept = $deptId($post['hiring_department_id'] ?? '');
    foreach ([['requesting_department_id', $reqDept], ['hiring_department_id', $hirDept]] as $d) {
        if (trim((string) ($post[$d[0]] ?? '')) !== '' && $d[1] === null)
            return [false, 'That department is not one this workspace knows.', 0];
        if ($d[1] !== null && function_exists('vocab_value')) {
            $row = vocab_value($d[1]);
            if (!$row || (int) $row['type_id'] !== (int) vocab_type_id('department'))
                return [false, 'That department is not a department.', 0];
            if ((int) ($row['active'] ?? 1) !== 1)
                return [false, 'That department is switched off and cannot be requested against.', 0];
        }
    }
    // Position — must exist, be active, and be one this user may act on.
    $posId = (int) ($post['position_id'] ?? 0) ?: null;
    if ($posId) {
        try { $p = ops_one("SELECT id, active, office_id FROM positions WHERE id=?", [$posId]); } catch (Throwable $e) { $p = null; }
        if (!$p) return [false, 'That position does not exist.', 0];
        if ((int) ($p['active'] ?? 1) !== 1) return [false, 'That position is switched off.', 0];
        if (function_exists('scope_office_allows') && !scope_office_allows($p['office_id'] ?? null))
            return [false, 'That position is outside your office / branch scope.', 0];
    }
    $office = (int) ($post['office_id'] ?? 0) ?: null;
    if ($office !== null && function_exists('scope_allows') && !scope_allows($office, null))
        return [false, 'That branch is outside your office / branch scope.', 0];

    $reqBy = trim((string) ($post['required_by'] ?? ''));
    if ($reqBy !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($reqBy, 0, 10)))
        return [false, 'The required-by date is not a date.', 0];

    $prio = strtoupper(trim((string) ($post['priority'] ?? 'NORMAL'))) ?: 'NORMAL';
    if (!isset(hreq_priorities()[$prio])) return [false, 'That is not a priority this workspace uses.', 0];
    $emp = strtoupper(trim((string) ($post['employment_type'] ?? '')));
    if ($emp !== '' && !isset(hreq_employment_types()[$emp])) return [false, 'That is not an employment type this workspace uses.', 0];
    $rtype = strtoupper(trim((string) ($post['request_type'] ?? '')));
    if ($rtype !== '' && !isset(hreq_request_types()[$rtype])) return [false, 'That is not a request type this workspace uses.', 0];

    $cols = [
        'status'                   => $existing ? (string) $existing['status'] : 'DRAFT',
        'requested_by_id'          => $byId,
        'requested_by_name'        => trim((string) ($post['requested_by_name'] ?? '')),
        'requesting_department_id' => $reqDept,
        'hiring_department_id'     => $hirDept,
        'designation'              => trim((string) ($post['designation'] ?? '')),
        'grade'                    => trim((string) ($post['grade'] ?? '')),
        'position_id'              => $posId,
        'new_position_requested'   => !empty($post['new_position_requested']) ? 1 : 0,
        'job_title'                => $title,
        'job_description'          => (string) ($post['job_description'] ?? ''),
        'quantity'                 => $qty,
        'office_id'                => $office,
        'work_location'            => trim((string) ($post['work_location'] ?? '')),
        'client_id'                => (int) ($post['client_id'] ?? 0) ?: null,
        'project_ref'              => trim((string) ($post['project_ref'] ?? '')),
        'required_by'              => substr($reqBy, 0, 10),
        'employment_type'          => $emp,
        'request_type'             => $rtype,
        'priority'                 => $prio,
        'reason'                   => trim((string) ($post['reason'] ?? '')),
        'approval_required'        => isset($post['approval_required']) ? (int) !!$post['approval_required'] : 1,
    ];
    $now = hreq_now(); $who = hreq_who();
    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE hiring_requests SET $set, updated_by=?, updated_at=? WHERE id=?")
            ->execute([...array_values($cols), $who, $now, $id]);
    } else {
        $keys = array_keys($cols);
        $ph = implode(',', array_fill(0, count($keys) + 4, '?'));
        db()->prepare("INSERT INTO hiring_requests (req_no," . implode(',', $keys) . ",created_by,created_at,updated_at) VALUES ($ph)")
            ->execute([hreq_next_no(), ...array_values($cols), $who, $now, $now]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('custom_save')) custom_save('hiring_request', $id, $post);
    if (function_exists('activity_log')) activity_log('hiring_request', $id, $existing ? 'update' : 'create', $title);
    return [true, $existing ? 'Hiring request saved.' : 'Hiring request created.', $id];
}

// ---- Submit — takes the snapshot (§12) ------------------------------------
// What the request MEANT when it was sent. A later edit to the department or
// designation master cannot silently change the meaning of what was approved.
function hreq_snapshot(array $r) {
    $dept = fn($id) => ($id && function_exists('vocab_value') && ($v = vocab_value((int) $id)))
        ? (function_exists('vocab_display') ? vocab_display($v) : $v['label']) : '';
    $desig = function_exists('lk_options_or') ? lk_options_or('designation', defined('DESIGNATIONS') ? DESIGNATIONS : []) : [];
    $pos = null;
    if (!empty($r['position_id'])) { try { $pos = ops_one("SELECT code, name FROM positions WHERE id=?", [(int) $r['position_id']]); } catch (Throwable $e) {} }
    return [
        'taken_at'             => hreq_now(),
        'job_title'            => (string) ($r['job_title'] ?? ''),
        'job_description'      => (string) ($r['job_description'] ?? ''),
        'designation'          => (string) ($r['designation'] ?? ''),
        'designation_display'  => (string) ($desig[$r['designation'] ?? ''] ?? ($r['designation'] ?? '')),
        'requesting_department'=> $dept($r['requesting_department_id'] ?? 0),
        'hiring_department'    => $dept($r['hiring_department_id'] ?? 0),
        'position'             => $pos ? trim(($pos['code'] ?? '') . ' ' . ($pos['name'] ?? '')) : '',
        'quantity'             => (int) ($r['quantity'] ?? 0),
        'grade'                => (string) ($r['grade'] ?? ''),
        'employment_type'      => (string) ($r['employment_type'] ?? ''),
        'work_location'        => (string) ($r['work_location'] ?? ''),
        'required_by'          => (string) ($r['required_by'] ?? ''),
        'priority'             => (string) ($r['priority'] ?? ''),
    ];
}

function hreq_submit($id) {
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    $st = strtoupper((string) $r['status']);
    if ($st !== 'DRAFT') return [false, 'Only a draft can be submitted (this one is ' . strtolower($st) . ').'];
    if ((int) $r['quantity'] <= 0) return [false, 'Say how many people are needed before submitting.'];
    if (trim((string) $r['job_title']) === '') return [false, 'Say what is being requested before submitting.'];
    $next = empty($r['approval_required']) ? 'APPROVED' : 'SUBMITTED';
    db()->prepare("UPDATE hiring_requests SET status=?, snapshot_json=?, submitted_at=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([$next, json_encode(hreq_snapshot($r)), hreq_now(), hreq_who(), hreq_now(), (int) $id]);
    if (function_exists('activity_log')) activity_log('hiring_request', (int) $id, 'status', 'Submitted');
    return [true, $next === 'APPROVED' ? 'Request submitted — this workspace does not require approval, so it is ready.' : 'Request submitted for approval.'];
}

// The decision itself. Phase 3 replaces the caller with the approval engine;
// the state transition lives here so both use the same one.
function hreq_decide($id, $approve, $note = '') {
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    $st = strtoupper((string) $r['status']);
    if (!in_array($st, ['SUBMITTED', 'UNDER_REVIEW'], true))
        return [false, 'Only a submitted request can be decided (this one is ' . strtolower($st) . ').'];
    $to = $approve ? 'APPROVED' : 'REJECTED';
    db()->prepare("UPDATE hiring_requests SET status=?, decided_by=?, decided_at=?, decision_note=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([$to, hreq_who(), hreq_now(), substr(trim((string) $note), 0, 400), hreq_who(), hreq_now(), (int) $id]);
    if (function_exists('activity_log')) activity_log('hiring_request', (int) $id, 'status', $to);
    return [true, $approve ? 'Request approved.' : 'Request rejected.'];
}

function hreq_cancel($id, $note = '') {
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    if (strtoupper((string) $r['status']) === 'CANCELLED') return [true, 'Already cancelled.'];
    db()->prepare("UPDATE hiring_requests SET status='CANCELLED', decision_note=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([substr(trim((string) $note), 0, 400), hreq_who(), hreq_now(), (int) $id]);
    if (function_exists('activity_log')) activity_log('hiring_request', (int) $id, 'status', 'Cancelled');
    return [true, 'Request cancelled.'];
}

// ---- Request -> Requisition (§18, §20) ------------------------------------
//  THE boundary this milestone exists to create: recruitment execution cannot
//  begin from a request that is not ready. M4 makes that refusable; Phase 3 adds
//  the routing that reaches APPROVED.
//
//  Cardinality is one request to MANY requisitions (§20). A request for ten
//  people may legitimately be executed as two requisitions of five in different
//  branches, so nothing here forces one-to-one. What is NOT allowed is
//  converting more people than were approved.
function hreq_requisitions($id) {
    hreq_migrate();
    try { return ops_all("SELECT * FROM requisitions WHERE hiring_request_id=? ORDER BY id", [(int) $id]); }
    catch (Throwable $e) { return []; }
}

// How many of the approved headcount are already being executed.
function hreq_converted_qty($id) {
    $n = 0;
    foreach (hreq_requisitions($id) as $r) {
        if (in_array(strtoupper((string) $r['status']), ['CANCELLED'], true)) continue;
        $n += max(1, (int) ($r['quantity'] ?? 1));
    }
    return $n;
}

function hreq_remaining_qty($id) {
    $r = hreq_get($id); if (!$r) return 0;
    return max(0, (int) $r['quantity'] - hreq_converted_qty($id));
}

// Create the execution record. Returns [ok, message, requisitionId].
function hreq_to_requisition($id, $qty = 0) {
    hreq_migrate();
    if (function_exists('req_migrate')) req_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.', 0];

    // The boundary. Nothing below runs for a request that is not ready.
    if (!hreq_is_executable($r))
        return [false, 'This request is ' . strtolower((string) $r['status'])
            . '. Recruitment cannot start until it is approved.', 0];

    $left = hreq_remaining_qty($id);
    if ($left <= 0) return [false, 'Every approved vacancy on this request is already being recruited.', 0];
    $qty = (int) $qty ?: $left;
    if ($qty > $left) return [false, 'Only ' . $left . ' of the approved headcount is left to recruit.', 0];

    // The department goes across as the canonical identity AND the text column,
    // exactly as a directly raised requisition now does.
    $deptCode = ''; $deptId = (int) ($r['hiring_department_id'] ?: $r['requesting_department_id'] ?: 0) ?: null;
    if ($deptId && function_exists('vocab_value')) { $v = vocab_value($deptId); if ($v) $deptCode = (string) $v['code']; }

    $code = function_exists('recruit_req_code')
        ? recruit_req_code((int) ($r['office_id'] ?? 0), (int) ($r['client_id'] ?? 0), date('Y-m-d'))
        : (function_exists('ops_next_code') ? ops_next_code('requisitions', 'req_code', 'REQ') : 'REQ-' . $id);

    db()->prepare("INSERT INTO requisitions
        (req_code, hiring_request_id, office_id, client_id, department, department_id, designation, grade,
         position_id, quantity, req_type, project_site, deploy_location, start_date, responsibilities,
         status, approved_by, approval_date, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?, ?, ?, ?)")
        ->execute([$code, (int) $id, $r['office_id'], $r['client_id'], $deptCode, $deptId,
                   $r['designation'], $r['grade'], $r['position_id'], $qty, $r['request_type'],
                   $r['project_ref'], $r['work_location'], $r['required_by'], $r['job_description'],
                   (string) $r['decided_by'], (string) substr((string) $r['decided_at'], 0, 10),
                   hreq_who(), hreq_now()]);
    $rid = (int) db()->lastInsertId();
    if (function_exists('reqf_sync')) reqf_sync($rid);
    if (function_exists('activity_log')) activity_log('hiring_request', (int) $id, 'requisition', 'Raised ' . $code);
    return [true, 'Requisition ' . $code . ' raised for ' . $qty . ' of the approved headcount.', $rid];
}

// ---- Scope (§35) ----------------------------------------------------------
// One door, the pattern M14 established and M3 extended to candidates. A hiring
// request belongs to a branch; a user may only act within their own.
function hreq_scope_gate() {
    if (!function_exists('scope_allows')) return;
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id > 0) {
        $r = hreq_get($id);
        if ($r) ops_require(scope_allows($r['office_id'] ?? null, null),
                            'This hiring request is outside your office / branch scope.');
    }
    // A branch named on the way in must also be one they may use.
    $to = (int) ($_POST['office_id'] ?? 0);
    if ($to > 0) ops_require(scope_allows($to, null), 'That branch is outside your office / branch scope.');
}

// Only requests this user may see.
function hreq_list($status = '') {
    hreq_migrate();
    $w = []; $a = [];
    if ($status !== '') { $w[] = 'status=?'; $a[] = strtoupper($status); }
    if (function_exists('scope_office_clause')) {
        [$sw, $sa] = scope_office_clause('office_id');
        if ($sw && $sw !== '1=1') { $w[] = $sw; $a = array_merge($a, $sa); }
    }
    $sql = "SELECT * FROM hiring_requests" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY id DESC";
    try { return ops_all($sql, $a); } catch (Throwable $e) { return []; }
}

// ---- Routes ---------------------------------------------------------------
//  One dispatcher, one door. Scope first, then permission, exactly as every
//  other register in the application does — no second access-control mechanism.
function ops_hiring_requests($route, $method) {
    hreq_scope_gate();                       // M4 — branch scope, before anything reads an id
    hreq_migrate();
    $mayRaise  = function_exists('is_coordinator_level') && is_coordinator_level();
    $mayDecide = function_exists('is_admin_level') && is_admin_level();

    if ($method === 'POST') {
        ops_require($mayRaise, 'Only coordinators / managers can raise a hiring request.');
        $do = (string) ($_POST['do'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($do === 'save') {
            [$ok, $msg, $newId] = hreq_save($id, $_POST);
            flash($msg, $ok ? 'success' : 'error');
            redirect($ok ? '/hiring-request?id=' . $newId : '/hiring-requests'); return true;
        }
        if ($do === 'submit')  { [$ok, $msg] = hreq_submit($id);  flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true; }
        if ($do === 'cancel')  { [$ok, $msg] = hreq_cancel($id, (string) ($_POST['note'] ?? '')); flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true; }
        if ($do === 'decide') {
            // Recording a decision is an administrator's act. Phase 3 replaces
            // this with the approval engine; the state change stays here so both
            // go through one place.
            ops_require($mayDecide, 'Only administrators can approve or reject a hiring request.');
            [$ok, $msg] = hreq_decide($id, ($_POST['decision'] ?? '') === 'approve', (string) ($_POST['note'] ?? ''));
            flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true;
        }
        if ($do === 'raise-requisition') {
            [$ok, $msg, $rid] = hreq_to_requisition($id, (int) ($_POST['qty'] ?? 0));
            flash($msg, $ok ? 'success' : 'error');
            redirect($ok ? '/requisition?id=' . $rid : '/hiring-request?id=' . $id); return true;
        }
    }

    if ($route === 'hiring-request') {
        $id = (int) ($_GET['id'] ?? 0);
        $r = $id > 0 ? hreq_get($id) : null;
        if ($id > 0 && !$r) { http_response_code(404); view('notfound'); return true; }
        view('ops/hiring_request', [
            'req'        => $r,
            'mayRaise'   => $mayRaise,
            'mayDecide'  => $mayDecide,
            'requisitions' => $r ? hreq_requisitions($id) : [],
            'remaining'  => $r ? hreq_remaining_qty($id) : 0,
            'people'     => function_exists('rcc_users') ? rcc_users() : [],
            'offices'    => function_exists('offices_list') ? offices_list() : [],
            'positions'  => function_exists('positions_all') ? positions_all(true) : [],
        ]);
        return true;
    }
    view('ops/hiring_request_list', ['rows' => hreq_list((string) ($_GET['status'] ?? '')), 'mayRaise' => $mayRaise]);
    return true;
}
