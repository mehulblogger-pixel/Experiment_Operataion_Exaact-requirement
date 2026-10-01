<?php
// ============================================================================
//  EXAACT — REQUIREMENT VERSIONING & CHANGE CONTROL  (Gate 2)
//
//  ONE mechanism, serving BOTH the Hiring Request and the Recruitment
//  Requisition. Not two engines: the entity descriptors in RVER_ENTITIES are the
//  only per-entity knowledge, and everything below is shared.
//
//      approved requirement
//            -> current approved version        (immutable, kept for ever)
//            -> execution runs against it
//            -> a material change is PROPOSED   (the approved version stays live)
//            -> approved / rejected / withdrawn
//            -> a NEW approved version, the previous one still readable
//
//  THE DEFECT THIS EXISTS TO REMOVE.
//
//  M4 already detected material change correctly and already asked the existing
//  approval engine for a new decision — that part is reused wholesale. What it
//  did NOT do is keep the approved requirement effective while the change waited:
//  hreq_save() wrote the incoming values straight into the live row and only then
//  noticed the change was material. The approved snapshot survived in a column,
//  so nothing was lost, but every screen, count and export then read the
//  PROPOSED values as though they had been approved. A pending change silently
//  became effective.
//
//  So: a material change no longer touches the record. It becomes a proposal.
//
//  WHAT IS REUSED RATHER THAN REBUILT
//    · the approval engine            appr_start / appr_match / appr_open
//    · the audit spine                act_log
//    · materiality for hiring requests HREQ_MATERIAL_FIELDS, hreq_commitment()
//    · the graduated execution gate   REXEC_ACTIONS
//    · configuration                  setting_get / setting_set
//    · role permissions               can() / the role_access matrix
//
//  No second approval engine, no second audit engine, no second lifecycle
//  engine, and no "master requirement" table replacing the two it versions.
// ============================================================================

//  A separate approval chain MAY be configured for material changes. These are
//  entities of the EXISTING approval engine, so the matrix, authority,
//  delegation, scope, segregation, inbox, SLA and notifications are the ones
//  already built — nothing here approves anything by itself.
const RVER_APPR_ENTITY = [
    'HIRING_REQUEST' => 'HREQ_CHANGE',
    'REQUISITION'    => 'REQ_CHANGE',
];

//  Proposal lifecycle. PENDING is the only open state; the other three are final
//  and permanently retained (G2).
const RVER_STATUSES = [
    'PENDING'   => 'Awaiting decision',
    'APPROVED'  => 'Approved',
    'REJECTED'  => 'Rejected',
    'WITHDRAWN' => 'Withdrawn',
];

//  WHAT A PENDING CHANGE STOPS, as four organisation-configured levels. These
//  are expressed in the EXISTING REXEC_ACTIONS vocabulary rather than a new one,
//  so a refusal here reads like every other execution refusal in the product.
//
//  Level 2 ships as the default: keep looking at people, do not commit to them.
const RVER_EFFECT_LEVELS = [
    1 => 'Pause everything',
    2 => 'Continue screening and interviewing — no offer, no joining',
    3 => 'Continue everything except joining',
    4 => 'Continue normally',
];
const RVER_EFFECT_BLOCKS = [
    1 => ['ADVANCE', 'OFFER', 'JOIN'],
    2 => ['OFFER', 'JOIN'],
    3 => ['JOIN'],
    4 => [],
];

//  THE PERSON-SPECIFICATION FLOOR (D2 / A8). These are the CORE requirement the
//  Hiring Request carries and the Requisition may only make STRICTER. The
//  comparison direction is stated once, here, so no caller has to guess whether
//  bigger is stricter.
//
//    'min' — a higher number is stricter (years of experience)
//    'set' — a larger set is stricter (essential skills)
//    'rank'— a later position in the configured list is stricter (qualification)
const RVER_SPEC_FLOOR = [
    'min_experience_years' => ['dir' => 'min',  'label' => 'minimum experience'],
    'min_qualification'    => ['dir' => 'rank', 'label' => 'minimum qualification'],
    'essential_skills'     => ['dir' => 'set',  'label' => 'essential skills'],
];

//  Qualification order, weakest first, so "Diploma" can be compared with
//  "Degree". Configurable through the existing lookup engine.
const RVER_QUAL_ORDER = [
    'NONE' => 'No formal qualification', 'SCHOOL' => 'School', 'ITI' => 'ITI / trade',
    'DIPLOMA' => 'Diploma', 'DEGREE' => 'Degree', 'PG' => 'Post-graduate',
    'DOCTORATE' => 'Doctorate', 'PROFESSIONAL' => 'Professional qualification',
];

// ---------------------------------------------------------------------------
//  Entity descriptors — the ONLY per-entity knowledge in this file.
// ---------------------------------------------------------------------------
function rver_entities() {
    return [
        'HIRING_REQUEST' => [
            'table'    => 'hiring_requests',
            'label'    => 'hiring request',
            'no'       => 'req_no',
            //  CORE requirement: what the approver authorised.
            'fields'   => rver_hreq_fields(),
            'material' => function_exists('hreq_material_fields') ? hreq_material_fields()
                            : (defined('HREQ_MATERIAL_FIELDS') ? HREQ_MATERIAL_FIELDS : []),
        ],
        'REQUISITION' => [
            'table'    => 'requisitions',
            'label'    => 'requirement',
            'no'       => 'req_code',
            //  DETAILED execution requirement: what recruitment works to.
            'fields'   => rver_req_fields(),
            'material' => RVER_REQ_MATERIAL,
        ],
    ];
}

//  The Requisition's own material-field list (A6 — each entity has its own).
const RVER_REQ_MATERIAL = [
    'designation'          => 'the role being recruited',
    'grade'                => 'the pay band',
    'position_id'          => 'the establishment seat',
    'office_id'            => 'the branch the cost and the approval chain belong to',
    'department'           => 'the department the person joins',
    'client_id'            => 'the client contract it is billed against',
    'req_type'             => 'the basis on which it was authorised',
    'team_role'            => 'the kind of team member being recruited',
    'min_experience_years' => 'the experience floor candidates are judged against',
    'min_qualification'    => 'the qualification floor candidates are judged against',
    'essential_skills'     => 'the skills a candidate must have to be eligible',
];

//  Which columns make up each entity's versioned snapshot. Read from the table
//  itself so a column added later is versioned without editing this list, minus
//  the bookkeeping that is not part of the requirement.
const RVER_SKIP_COLS = ['id', 'created_at', 'created_by', 'updated_at', 'updated_by',
                        'approved_snapshot_json', 'approved_snapshot_at', 'reapproval_state',
                        'reapproval_started_at', 'approval_ref', 'approved_by', 'approval_date',
                        'hired_inspector_id', 'careers_published', 'careers_summary'];

function rver_table_fields($table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $out = [];
    try {
        if (function_exists('db_driver') && db_driver() === 'sqlite') {
            foreach (ops_all("PRAGMA table_info(" . $table . ")") ?: [] as $c) $out[] = (string) $c['name'];
        } else {
            foreach (ops_all("SHOW COLUMNS FROM `" . $table . "`") ?: [] as $c) $out[] = (string) ($c['Field'] ?? '');
        }
    } catch (Throwable $e) { return []; }
    $out = array_values(array_filter($out, fn($c) => $c !== '' && !in_array($c, RVER_SKIP_COLS, true)));
    return $cache[$table] = $out;
}
function rver_hreq_fields() { return rver_table_fields('hiring_requests'); }
function rver_req_fields()  { return rver_table_fields('requisitions'); }

function rver_entity($entity) {
    $e = rver_entities();
    return $e[strtoupper(trim((string) $entity))] ?? null;
}
//  READ ONE REQUIREMENT. The hiring request is read through its own layer, which
//  owns that table; the requisition is read directly, as every other module does.
function rver_row($entity, $id) {
    $entity = strtoupper(trim((string) $entity));
    if ($entity === 'HIRING_REQUEST')
        return function_exists('hreq_get') ? (hreq_get((int) $id) ?: null) : null;
    $d = rver_entity($entity); if (!$d) return null;
    try { return ops_one("SELECT * FROM " . $d['table'] . " WHERE id=?", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }
}
function rver_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function rver_who() { return function_exists('user_name') ? (string) user_name(current_user()) : 'system'; }
function rver_who_id() { return function_exists('current_user') && ($u = current_user()) ? (int) ($u['id'] ?? 0) : 0; }

// ---------------------------------------------------------------------------
//  MIGRATION — additive, idempotent, non-destructive.
// ---------------------------------------------------------------------------
function rver_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $lt = (function_exists('db_driver') && db_driver() === 'sqlite') ? 'TEXT' : 'LONGTEXT';
    try {
        //  THE APPROVED VERSIONS. One row per approved version, never updated and
        //  never deleted: this table IS the history (Q12, §13).
        db()->exec("CREATE TABLE IF NOT EXISTS requirement_versions (
            id $pk,
            entity VARCHAR(20) DEFAULT '',
            entity_id INT DEFAULT 0,
            version INT DEFAULT 1,
            snapshot_json $lt,
            effective_from VARCHAR(30) DEFAULT '',
            approval_ref VARCHAR(40) DEFAULT '',
            proposal_id INT DEFAULT 0,
            decided_by VARCHAR(150) DEFAULT '',
            decided_by_id INT DEFAULT 0,
            decided_at VARCHAR(30) DEFAULT '',
            note VARCHAR(500) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')");
        //  THE PROPOSALS. A refused or withdrawn proposal is kept for ever and is
        //  never amended in place (G2) — a new attempt is a new row.
        db()->exec("CREATE TABLE IF NOT EXISTS requirement_change_proposals (
            id $pk,
            entity VARCHAR(20) DEFAULT '',
            entity_id INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'PENDING',
            base_version INT DEFAULT 0,
            proposed_json $lt,
            diff_json $lt,
            reason VARCHAR(1000) DEFAULT '',
            docs_json TEXT,
            proposed_by VARCHAR(150) DEFAULT '',
            proposed_by_id INT DEFAULT 0,
            proposed_at VARCHAR(30) DEFAULT '',
            approval_ref VARCHAR(40) DEFAULT '',
            requires_approval INT DEFAULT 1,
            route VARCHAR(40) DEFAULT '',
            decided_by VARCHAR(150) DEFAULT '',
            decided_by_id INT DEFAULT 0,
            decided_at VARCHAR(30) DEFAULT '',
            decision_note VARCHAR(1000) DEFAULT '',
            created_version INT DEFAULT 0,
            prefill_of INT DEFAULT 0,
            created_at VARCHAR(30) DEFAULT '',
            pending_key VARCHAR(60) DEFAULT NULL)");
        //  ONE PENDING PROPOSAL PER REQUIREMENT, enforced by the database rather
        //  than by a check the next caller might forget.
        //
        //  A partial index would be the natural way to say this, and MariaDB does
        //  not have them. So `pending_key` carries "ENTITY:id" only while the row
        //  is PENDING and NULL otherwise: a unique index treats NULLs as distinct
        //  on BOTH engines, so any number of decided proposals coexist while a
        //  second PENDING one cannot be inserted.
        foreach (["CREATE UNIQUE INDEX idx_rcp_pending ON requirement_change_proposals (pending_key)",
                  "CREATE INDEX idx_rcp_ent ON requirement_change_proposals (entity, entity_id)",
                  "CREATE UNIQUE INDEX idx_rv_ver ON requirement_versions (entity, entity_id, version)",
                  "CREATE INDEX idx_rv_ent ON requirement_versions (entity, entity_id)"] as $ddl) {
            try { db()->exec($ddl); } catch (Throwable $e) { /* already there */ }
        }
        //  D2 — the REQUISITION's copy of the person specification, which it may
        //  make stricter than the request's. The hiring request's own copy is
        //  created by hreq_migrate(), because that layer owns its table.
        //
        //  Additive and nullable: an existing requirement simply has no floor
        //  recorded, which is read as "not specified" and never as zero.
        ensure_column('requisitions', 'min_experience_years', 'DECIMAL(5,2) DEFAULT NULL');
        ensure_column('requisitions', 'min_qualification',    "VARCHAR(40) DEFAULT ''");
        ensure_column('requisitions', 'essential_skills',     "VARCHAR(600) DEFAULT ''");
        //  The Requisition's own execution detail (D2's "adds" list). The
        //  requisition already carries responsibilities, locations and the
        //  selection/compliance flags, so only what is genuinely missing is added.
        ensure_column('requisitions', 'preferred_skills',      "VARCHAR(600) DEFAULT ''");
        ensure_column('requisitions', 'screening_questions',   'TEXT');
        ensure_column('requisitions', 'sourcing_requirements', "VARCHAR(600) DEFAULT ''");
        ensure_column('requisitions', 'client_requirements',   "VARCHAR(600) DEFAULT ''");
        ensure_column('requisitions', 'recruitment_notes',     'TEXT');
        ensure_column('requisitions', 'evaluation_criteria',   "VARCHAR(600) DEFAULT ''");
        //  Where a requisition's approved version lives, mirroring what the
        //  hiring request already had. Kept so a requisition can be judged
        //  against what was approved rather than against its last edit.
        ensure_column('requisitions', 'approved_snapshot_json', $lt === 'TEXT' ? 'TEXT' : 'LONGTEXT');
        ensure_column('requisitions', 'approved_snapshot_at',  "VARCHAR(30) DEFAULT ''");
        ensure_column('requisitions', 'change_state',          "VARCHAR(20) DEFAULT 'NONE'");

    } catch (Throwable $e) { /* never break boot */ }
    rver_ensure_vocab();
}

//  Configurable vocabularies, through the existing lookup engine.
function rver_ensure_vocab() {
    if (!function_exists('lk_ensure_value') || !function_exists('lk_type')) return;
    try {
        if (lk_type('qualification_level'))
            foreach (RVER_QUAL_ORDER as $c => $l) lk_ensure_value('qualification_level', $c, $l);
    } catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  CONFIGURATION — material fields, thresholds, pending effect.
// ---------------------------------------------------------------------------

//  THE MATERIAL FIELD LIST, per entity, CONFIGURABLE BY ORGANISATION (D7).
//
//  Configuration may only ADD. The shipped list is a floor, not a default that a
//  careless administrator can empty: removing a protection is exactly the change
//  an organisation should not be able to make by accident, and the audit matrix
//  in docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md is what an approver relies on.
function rver_material_fields($entity) {
    rver_migrate();
    $d = rver_entity($entity); if (!$d) return [];
    $base = is_array($d['material']) ? $d['material'] : [];
    $extra = [];
    if (function_exists('setting_get')) {
        $raw = (string) setting_get('rver_material_extra_' . strtolower((string) $entity), '');
        foreach (preg_split('~[,\n]~', $raw) ?: [] as $f) {
            $f = trim((string) $f);
            if ($f !== '' && !isset($base[$f])) $extra[$f] = 'added to the material list by this organisation';
        }
    }
    //  Only fields the entity actually has, so a typo in configuration cannot
    //  make every save look material.
    $cols = rver_table_fields($d['table']);
    foreach (array_keys($extra) as $f) if (!in_array($f, $cols, true)) unset($extra[$f]);
    return $base + $extra;
}

//  THE BUDGET THRESHOLD (C45 / C9). Shipped: 10% OR Rs 1,00,000, whichever is
//  GREATER, so a small requirement is not re-approved over a rounding change and
//  a large one is not waved through on a percentage.
function rver_threshold() {
    $g = fn($k, $d) => function_exists('setting_get') ? setting_get($k, $d) : $d;
    $pct = (float) $g('rver_budget_pct', 10);
    $abs = (float) $g('rver_budget_abs', 100000);
    $rule = strtoupper(trim((string) $g('rver_budget_rule', 'GREATER')));
    if (!in_array($rule, ['GREATER', 'LESSER', 'PCT', 'ABS'], true)) $rule = 'GREATER';
    return ['pct' => max(0.0, $pct), 'abs' => max(0.0, $abs), 'rule' => $rule];
}

//  WHAT A REQUIREMENT COMMITS THE COMPANY TO, as one number.
//
//      per-person cost  x  quantity  x  applicable periods  +  one-time cost
//
//  Never judged on a single cost field: the fields trade off against each other,
//  so halving a duration while doubling a rate is not a change anybody would
//  call a change, and comparing one field at a time would fire a needless
//  re-approval on it.
function rver_commitment($entity, $row) {
    $row = is_array($row) ? $row : [];
    $entity = strtoupper((string) $entity);
    //  The hiring request already has this calculation and it is reused exactly.
    if ($entity === 'HIRING_REQUEST' && function_exists('hreq_commitment')) return hreq_commitment($row);

    $n = fn($v) => ($v === null || $v === '') ? 0.0 : (float) $v;
    $qty     = max(1, (int) ($row['quantity'] ?? 1));
    $months  = $n($row['duration_months'] ?? null);
    $onetime = $n($row['cost_oneoff'] ?? null);
    //  A requisition's per-person monthly cost is its wage plus the statutory and
    //  agency percentages on that wage, plus monthly reimbursables — the same
    //  build-up the costing screen shows.
    $wage    = $n($row['cost_wage'] ?? null);
    $per     = $wage
             + ($wage * $n($row['cost_statutory_pct'] ?? null) / 100)
             + ($wage * $n($row['cost_agency_pct'] ?? null) / 100)
             + $n($row['cost_reimburse'] ?? null);
    if ($per <= 0) $per = $n($row['budgeted_cost'] ?? null);
    $basis     = strtoupper((string) ($row['rate_basis'] ?? 'MONTHLY'));
    $perPeriod = in_array($basis, ['MONTHLY', 'MANMONTH', 'MANDAY', 'DAILY'], true);
    $partial   = $perPeriod && $months <= 0;
    $periods   = $perPeriod ? ($months > 0 ? $months : 1.0) : 1.0;
    $recurring = $per * $qty * $periods;
    return ['has' => ($per > 0 || $onetime > 0), 'per_person' => $per, 'basis' => $basis,
            'months' => $months, 'qty' => $qty, 'onetime' => $onetime,
            'recurring' => $recurring, 'total' => $recurring + $onetime,
            'per_period' => $perPeriod, 'partial' => $partial];
}

//  Is the move from one commitment to another material?
//
//  A DECREASE never is: it stays inside what was approved. An increase is
//  measured against the configured threshold, and where nothing was approved
//  before, against the absolute floor — because a percentage of nothing would
//  make any first estimate material.
function rver_commitment_material($entity, $was, $now) {
    $cWas = rver_commitment($entity, $was);
    $cNow = rver_commitment($entity, $now);
    $t = rver_threshold();
    $delta = round($cNow['total'] - $cWas['total'], 2);
    $out = ['was' => $cWas['total'], 'now' => $cNow['total'], 'delta' => $delta,
            'material' => false, 'threshold' => 0.0, 'basis' => '', 'rule' => $t['rule']];
    if ($delta <= 0.01) return $out;                     // a decrease, or a rounding tail
    $pctAmt = $cWas['total'] * ($t['pct'] / 100);
    if ($cWas['total'] <= 0.0) { $limit = $t['abs']; $basis = 'absolute floor (nothing was approved before)'; }
    else switch ($t['rule']) {
        case 'PCT':    $limit = $pctAmt;                 $basis = $t['pct'] . '%'; break;
        case 'ABS':    $limit = $t['abs'];               $basis = 'absolute'; break;
        case 'LESSER': $limit = min($pctAmt, $t['abs']); $basis = 'lesser of ' . $t['pct'] . '% and absolute'; break;
        default:       $limit = max($pctAmt, $t['abs']); $basis = 'greater of ' . $t['pct'] . '% and absolute';
    }
    $out['threshold'] = round($limit, 2);
    $out['basis']     = $basis;
    //  "Exceeds" means exceeds. A change landing exactly ON the threshold is
    //  inside it, which is the reading an approver signing a limit expects.
    $out['material']  = ($delta - $limit) > 0.01;
    return $out;
}

// ---------------------------------------------------------------------------
//  THE COMPARISON (§17) — deterministic, and always against the APPROVED
//  version. Never against the previous draft or the last edit: ten harmless
//  edits followed by one material one must still be compared with what the
//  approver actually saw.
// ---------------------------------------------------------------------------

//  One normalisation, used by every comparison, so a NULL/0/'' shuffle is never
//  reported as a business change.
function rver_norm($v) {
    if (is_array($v)) { $v = array_map(fn($x) => trim((string) $x), $v); sort($v); return implode('|', $v); }
    if ($v === null) return '';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_numeric($v)) {
        $f = (float) $v;
        return ($f === floor($f)) ? (string) (int) $f : rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
    }
    return strtolower(trim((string) $v));
}

//  Compare an approved field set with a proposed one.
//
//  Returns added / removed / changed, each split into material and non-material,
//  plus the commitment verdict. `material` being empty is what lets a change take
//  the ordinary edit path.
function rver_diff($entity, array $approved, array $proposed) {
    rver_migrate();
    $d = rver_entity($entity);
    $mat = rver_material_fields($entity);
    $cols = $d ? rver_table_fields($d['table']) : array_keys($approved + $proposed);
    $out = ['added' => [], 'removed' => [], 'changed' => [],
            'material' => [], 'non_material' => [], 'commitment' => null, 'is_material' => false];

    foreach ($cols as $f) {
        $hadKey = array_key_exists($f, $approved);
        $hasKey = array_key_exists($f, $proposed);
        if (!$hadKey && !$hasKey) continue;
        $a = $hadKey ? $approved[$f] : null;
        $b = $hasKey ? $proposed[$f] : $a;          // absent from the proposal = unchanged
        $na = rver_norm($a); $nb = rver_norm($b);
        if ($na === $nb) continue;
        $rec = ['field' => $f, 'was' => $a, 'now' => $b,
                'why' => $mat[$f] ?? '', 'material' => isset($mat[$f])];
        //  ADDED means a value where there was none; REMOVED the reverse. Both
        //  are also CHANGED, so a caller that wants "everything that moved" reads
        //  one list rather than three.
        if ($na === '' && $nb !== '')      $out['added'][$f] = $rec;
        elseif ($na !== '' && $nb === '')  $out['removed'][$f] = $rec;
        $out['changed'][$f] = $rec;
        if ($rec['material']) $out['material'][$f] = $rec; else $out['non_material'][$f] = $rec;
    }

    //  QUANTITY, asymmetrically — an increase spends authority nobody granted, a
    //  decrease stays inside the approval. Treating them alike would force a
    //  re-approval on a manager asking for fewer people, which teaches people to
    //  route around the control.
    $qWas = (int) ($approved['quantity'] ?? 0);
    $qNow = array_key_exists('quantity', $proposed) ? (int) $proposed['quantity'] : $qWas;
    if ($qNow > $qWas) {
        $rec = ['field' => 'quantity', 'was' => $qWas, 'now' => $qNow,
                'why' => 'more headcount than was authorised', 'material' => true];
        $out['material']['quantity'] = $rec; $out['changed']['quantity'] = $rec;
        unset($out['non_material']['quantity']);
    } elseif ($qNow < $qWas) {
        unset($out['material']['quantity']);
    }

    //  WHAT IT COSTS, on the same asymmetry and against the configured threshold.
    $c = rver_commitment_material($entity, $approved, array_key_exists('quantity', $proposed) ? $proposed + $approved : $proposed + $approved);
    $out['commitment'] = $c;
    if ($c['material']) {
        $rec = ['field' => 'commitment', 'was' => $c['was'], 'now' => $c['now'],
                'why' => 'a larger financial commitment than was approved (' . $c['basis'] . ')',
                'material' => true, 'threshold' => $c['threshold']];
        $out['material']['commitment'] = $rec; $out['changed']['commitment'] = $rec;
    }
    //  The individual cost fields are evidence, not the verdict: the commitment
    //  above is what decides, so a cost field moving inside the threshold is
    //  reported as non-material rather than as a change needing approval.
    foreach (['est_cost_per_person', 'est_duration_months', 'est_onetime_cost', 'est_cost_basis',
              'cost_wage', 'cost_statutory_pct', 'cost_agency_pct', 'cost_reimburse', 'cost_oneoff',
              'duration_months', 'budgeted_cost', 'rate_basis'] as $f)
        unset($out['material'][$f]);

    $out['is_material'] = !empty($out['material']);
    return $out;
}

// ---------------------------------------------------------------------------
//  THE VERSION CHAIN
// ---------------------------------------------------------------------------

//  The field set that makes up a version of this requirement.
function rver_field_set($entity, array $row) {
    $d = rver_entity($entity); if (!$d) return [];
    $out = [];
    foreach (rver_table_fields($d['table']) as $f) $out[$f] = $row[$f] ?? null;
    return $out;
}

function rver_versions($entity, $id) {
    rver_migrate();
    try {
        return ops_all("SELECT * FROM requirement_versions WHERE entity=? AND entity_id=? ORDER BY version",
                       [strtoupper((string) $entity), (int) $id]) ?: [];
    } catch (Throwable $e) { return []; }
}
function rver_current($entity, $id) {
    rver_migrate();
    try {
        return ops_one("SELECT * FROM requirement_versions WHERE entity=? AND entity_id=? ORDER BY version DESC LIMIT 1",
                       [strtoupper((string) $entity), (int) $id]) ?: null;
    } catch (Throwable $e) { return null; }
}
function rver_version($entity, $id, $n) {
    rver_migrate();
    try {
        return ops_one("SELECT * FROM requirement_versions WHERE entity=? AND entity_id=? AND version=?",
                       [strtoupper((string) $entity), (int) $id, (int) $n]) ?: null;
    } catch (Throwable $e) { return null; }
}
//  The approved field set of a version — or of the current one.
function rver_snapshot_fields($row) {
    if (!is_array($row)) return null;
    $d = json_decode((string) ($row['snapshot_json'] ?? ''), true);
    if (!is_array($d)) return null;
    return is_array($d['fields'] ?? null) ? $d['fields'] : $d;
}
function rver_approved_fields($entity, $id) {
    $v = rver_current($entity, $id);
    return $v ? rver_snapshot_fields($v) : null;
}

//  RECORD A NEW APPROVED VERSION. Append-only: the previous row is never updated
//  and never deleted, so history cannot be rewritten by a later approval (§13).
//
//  The version number is allocated by an INSERT that would collide on the unique
//  (entity, entity_id, version) index if two approvals raced, so a duplicate
//  version cannot be created — the loser retries against the new highest.
function rver_record_version($entity, $id, array $fields, array $meta = []) {
    rver_migrate();
    $entity = strtoupper((string) $entity); $id = (int) $id;
    $payload = json_encode(['fields' => $fields, 'at' => rver_now(),
                            'entity' => $entity, 'entity_id' => $id]);
    for ($try = 0; $try < 5; $try++) {
        $cur = rver_current($entity, $id);
        $next = $cur ? ((int) $cur['version'] + 1) : 1;
        try {
            db()->prepare("INSERT INTO requirement_versions
                (entity,entity_id,version,snapshot_json,effective_from,approval_ref,proposal_id,
                 decided_by,decided_by_id,decided_at,note,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$entity, $id, $next, $payload,
                           (string) ($meta['effective_from'] ?? rver_now()),
                           (string) ($meta['approval_ref'] ?? ''),
                           (int) ($meta['proposal_id'] ?? 0),
                           (string) ($meta['decided_by'] ?? rver_who()),
                           (int) ($meta['decided_by_id'] ?? rver_who_id()),
                           (string) ($meta['decided_at'] ?? rver_now()),
                           substr((string) ($meta['note'] ?? ''), 0, 500), rver_now()]);
            return $next;
        } catch (Throwable $e) { /* a version was taken; look again */ }
    }
    return 0;
}

//  The first approved version, taken when a requirement is approved. Idempotent:
//  a requirement that already has a version is not given a second identical one.
function rver_ensure_initial($entity, $id, array $row = null, array $meta = []) {
    rver_migrate();
    if (rver_current($entity, $id)) return 0;
    $row = is_array($row) ? $row : rver_row($entity, $id);
    if (!$row) return 0;
    return rver_record_version($entity, $id, rver_field_set($entity, $row),
        ['note' => 'First approved version'] + $meta);
}

// ---------------------------------------------------------------------------
//  A8 — THE HIRING REQUEST FLOOR
//
//  A Requisition may be MORE specific than the Hiring Request it came from:
//  Diploma + a certification where the request said Diploma, five years where it
//  said three. That is ordinary, and allowed.
//
//  What is not ordinary is dropping BELOW the approved Hiring Request minimum —
//  two years where the approved request said three. That weakens an approved
//  requirement, so it goes through change control even though it is the
//  Requisition that is versioned, not the request.
//
//  Judged against the APPROVED HIRING REQUEST minimum, never against the
//  previous Requisition version: coming DOWN from five to four is not a
//  weakening below a floor of three, and going from two back up to three is a
//  move TOWARDS the floor, which is never treated as a weakening.
// ---------------------------------------------------------------------------

//  The qualification order this workspace uses, weakest first.
function rver_qual_rank($code) {
    $order = function_exists('lk_options_or')
        ? lk_options_or('qualification_level', RVER_QUAL_ORDER) : RVER_QUAL_ORDER;
    $keys = array_keys($order);
    $i = array_search(strtoupper(trim((string) $code)), array_map('strtoupper', $keys), true);
    return $i === false ? -1 : (int) $i;          // -1 = not specified / unknown
}

//  The approved CORE floor of the hiring request a requisition belongs to, or
//  null when it has no request (the direct path, which has no floor to breach).
function rver_hr_floor($reqRow) {
    $hid = (int) ($reqRow['hiring_request_id'] ?? 0);
    if ($hid <= 0) return null;
    //  The APPROVED version of the request, not its live row — that is the whole
    //  point of a floor.
    $f = rver_approved_fields('HIRING_REQUEST', $hid);
    if ($f === null && function_exists('hreq_approved_snapshot')) {
        //  A request approved before Gate 2 has its snapshot in the old column.
        $r = rver_row('HIRING_REQUEST', $hid);
        $snap = $r ? hreq_approved_snapshot($r) : null;
        if (is_array($snap['fields'] ?? null)) $f = $snap['fields'];
    }
    return is_array($f) ? $f : null;
}

//  Which floors a proposed requisition would breach. Empty means none.
function rver_floor_breaches($reqRow, array $proposed) {
    $floor = rver_hr_floor($reqRow);
    if (!$floor) return [];
    $out = [];
    foreach (RVER_SPEC_FLOOR as $f => $spec) {
        if (!array_key_exists($f, $floor)) continue;
        $fv = $floor[$f];
        $pv = array_key_exists($f, $proposed) ? $proposed[$f] : ($reqRow[$f] ?? null);
        //  A floor that was never specified cannot be breached.
        if (rver_norm($fv) === '') continue;
        if ($spec['dir'] === 'min') {
            $a = (float) $fv; $b = ($pv === null || $pv === '') ? null : (float) $pv;
            //  Removing the requirement entirely is the strongest weakening there
            //  is, so an absent value counts as a breach when a floor exists.
            if ($b === null || $b < $a - 0.0001)
                $out[$f] = ['floor' => $a, 'proposed' => $b, 'label' => $spec['label']];
        } elseif ($spec['dir'] === 'rank') {
            $a = rver_qual_rank($fv); $b = rver_qual_rank($pv);
            if ($a >= 0 && $b < $a)
                $out[$f] = ['floor' => $fv, 'proposed' => $pv, 'label' => $spec['label']];
        } else {
            //  A set floor is breached when an essential skill the approved
            //  request named is no longer required.
            $split = fn($v) => array_values(array_filter(array_map('trim',
                preg_split('~[,;\n]~', strtolower((string) $v)) ?: [])));
            $miss = array_diff($split($fv), $split($pv));
            if ($miss) $out[$f] = ['floor' => $fv, 'proposed' => $pv,
                                   'missing' => array_values($miss), 'label' => $spec['label']];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  WHO MAY PROPOSE (Q8 / C46 / A4) — role defaults + permission + scope.
//  Configured per ROLE, never per user, and the recruitment scope still applies.
// ---------------------------------------------------------------------------
const RVER_PERM_PROPOSE = 'hiring.material_change.propose';

//  Q8's model is ROLE DEFAULTS *plus* a specific permission *plus* scope — three
//  things that add up, not one that replaces the others. So:
//
//    · a role that may already change this requirement keeps that ability, which
//      is what its role default says it can do. Requiring the new permission
//      INSTEAD would silently take away, on upgrade, something every existing
//      recruiter can do today — and an upgrade that removes a capability nobody
//      asked to remove is not a safe upgrade;
//    · the specific permission EXTENDS it to roles whose defaults do not include
//      changing requirements, which is what a customer grants it for;
//    · and the recruitment scope applies on top of both, so neither is a licence
//      to reach another branch's work.
//
//  A user with neither the role default nor the permission is refused.
function rver_can_propose($entity, $row = null) {
    if (!function_exists('can')) return false;
    $byRole = (strtoupper((string) $entity) === 'HIRING_REQUEST')
        ? (function_exists('hreq_can_create') && hreq_can_create())
        : can('mod.hiring.edit');
    if (!can(RVER_PERM_PROPOSE) && !$byRole) return false;
    //  SCOPE. A permission is not a licence to reach another branch's work; the
    //  same rule the registers use decides what is visible, and therefore what is
    //  changeable. scope_allows() is the one the hiring request itself asks
    //  (hreq_in_scope), so a proposal cannot reach further than an edit could.
    if (is_array($row) && function_exists('scope_allows'))
        if (!scope_allows($row['office_id'] ?? null, $row['sbu'] ?? null)) return false;
    return true;
}

// ---------------------------------------------------------------------------
//  THE PROPOSAL LIFECYCLE
// ---------------------------------------------------------------------------

function rver_pending($entity, $id) {
    rver_migrate();
    try {
        return ops_one("SELECT * FROM requirement_change_proposals
                        WHERE entity=? AND entity_id=? AND status='PENDING' ORDER BY id DESC LIMIT 1",
                       [strtoupper((string) $entity), (int) $id]) ?: null;
    } catch (Throwable $e) { return null; }
}
function rver_proposals($entity, $id) {
    rver_migrate();
    try {
        return ops_all("SELECT * FROM requirement_change_proposals
                        WHERE entity=? AND entity_id=? ORDER BY id DESC",
                       [strtoupper((string) $entity), (int) $id]) ?: [];
    } catch (Throwable $e) { return []; }
}
function rver_proposal($pid) {
    rver_migrate();
    try { return ops_one("SELECT * FROM requirement_change_proposals WHERE id=?", [(int) $pid]) ?: null; }
    catch (Throwable $e) { return null; }
}
function rver_proposed_fields($p) {
    if (!is_array($p)) return [];
    $d = json_decode((string) ($p['proposed_json'] ?? ''), true);
    return is_array($d) ? $d : [];
}

//  Does this organisation require approval for a change to this requirement?
//
//  B2 — ON by default, and "no matching rule" is NOT "approval off". The hiring
//  request carries the control per record; a requisition inherits it from the
//  request it belongs to, and otherwise from the workspace setting, which also
//  defaults ON.
function rver_approval_required($entity, $row) {
    $entity = strtoupper((string) $entity);
    if ($entity === 'HIRING_REQUEST') return !empty($row['approval_required']);
    $hid = (int) ($row['hiring_request_id'] ?? 0);
    if ($hid > 0 && ($h = rver_row('HIRING_REQUEST', $hid))) return !empty($h['approval_required']);
    return function_exists('setting_get') ? (int) setting_get('rver_req_approval_required', 1) === 1 : true;
}

//  WHICH CHAIN APPROVES THIS CHANGE (F1).
//
//  A chain configured specifically for material changes WINS. Otherwise the
//  change inherits the underlying requirement's own chain. Either way it is the
//  EXISTING approval engine that is asked, with the PROPOSED values as context —
//  because the change must be judged by what it is becoming, not by what it was.
function rver_route($entity, array $proposedRow) {
    if (!function_exists('appr_match')) return ['entity' => '', 'rule' => null];
    $ctx = rver_appr_ctx($entity, $proposedRow);
    $own = RVER_APPR_ENTITY[strtoupper((string) $entity)] ?? '';
    if ($own !== '') {
        try { $r = appr_match($own, $ctx); } catch (Throwable $e) { $r = null; }
        if ($r) return ['entity' => $own, 'rule' => $r];
    }
    try { $r = appr_match(strtoupper((string) $entity), $ctx); } catch (Throwable $e) { $r = null; }
    return ['entity' => $r ? strtoupper((string) $entity) : '', 'rule' => $r];
}

//  The approval context, from the PROPOSED values.
function rver_appr_ctx($entity, array $row) {
    if (strtoupper((string) $entity) === 'HIRING_REQUEST' && function_exists('hreq_appr_ctx'))
        return hreq_appr_ctx($row);
    $deptLabel = '';
    if (function_exists('dept_row_label')) { try { $deptLabel = (string) dept_row_label($row); } catch (Throwable $e) {} }
    return ['department' => $deptLabel !== '' ? $deptLabel : (string) ($row['department'] ?? ''),
            'sbu'        => (string) ($row['sbu'] ?? ''),
            'grade'      => (string) ($row['grade'] ?? ''),
            'position'   => (string) ($row['designation'] ?? ''),
            'office_id'  => (int) ($row['office_id'] ?? 0),
            'amount'     => rver_commitment($entity, $row)['total']];
}

//  PROPOSE A CHANGE.
//
//  The approved requirement is NOT touched. The whole proposed field set is kept
//  on the proposal, and the record keeps serving the approved values until a
//  decision is made (Q5, §6, §18).
//
//  The ENTIRE submit becomes the proposal, not just its material parts. Splitting
//  it would let somebody slip a material change into an otherwise harmless edit
//  and have the rest applied immediately, leaving the record in a state no
//  approver ever saw and no version records.
//
//  Returns [ok, message, proposalId].
function rver_propose($entity, $id, array $incoming, $reason, array $docs = []) {
    rver_migrate();
    $entity = strtoupper((string) $entity); $id = (int) $id;
    $d = rver_entity($entity); if (!$d) return [false, 'Unknown requirement type.', 0];
    $row = rver_row($entity, $id);
    if (!$row) return [false, 'That ' . $d['label'] . ' no longer exists.', 0];

    //  Q8 / C46 — permission and scope, asked HERE where the write happens, so a
    //  helper called directly cannot reach the table around the capability.
    if (!rver_can_propose($entity, $row))
        return [false, 'You do not have the right to propose a change to this ' . $d['label'] . '.', 0];

    //  Q10 — the reason is MANDATORY. A change to an approved requirement without
    //  a stated reason is not reviewable, so it is refused rather than stored.
    $reason = trim((string) $reason);
    if ($reason === '') return [false, 'A reason is required for a change to an approved ' . $d['label'] . '.', 0];

    if (rver_pending($entity, $id))
        return [false, 'A change is already awaiting a decision on this ' . $d['label']
                     . '. Withdraw it before proposing another.', 0];

    $approved = rver_approved_fields($entity, $id);
    if ($approved === null) return [false, 'This ' . $d['label'] . ' has no approved version to change.', 0];
    $proposed = rver_field_set($entity, $incoming + $row);
    $diff = rver_diff($entity, $approved, $proposed);
    $breach = ($entity === 'REQUISITION') ? rver_floor_breaches($row, $proposed) : [];
    if (!$diff['is_material'] && !$breach)
        return [false, 'Nothing material has changed, so no proposal is needed.', 0];
    if ($breach) $diff['floor'] = $breach;

    $cur = rver_current($entity, $id);
    $needs = rver_approval_required($entity, $row);
    $route = $needs ? rver_route($entity, $proposed) : ['entity' => '', 'rule' => null];

    //  ONE PENDING PROPOSAL, enforced by the unique index rather than by the
    //  check above: two users proposing at the same instant both pass the check,
    //  and only one INSERT can succeed.
    try {
        db()->prepare("INSERT INTO requirement_change_proposals
            (entity,entity_id,status,base_version,proposed_json,diff_json,reason,docs_json,
             proposed_by,proposed_by_id,proposed_at,requires_approval,route,prefill_of,created_at,pending_key)
             VALUES (?,?,'PENDING',?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$entity, $id, (int) ($cur['version'] ?? 0),
                       json_encode($proposed), json_encode($diff),
                       substr($reason, 0, 1000), json_encode(array_values($docs)),
                       rver_who(), rver_who_id(), rver_now(),
                       $needs ? 1 : 0, (string) $route['entity'],
                       (int) ($incoming['_prefill_of'] ?? 0), rver_now(),
                       $entity . ':' . $id]);
    } catch (Throwable $e) {
        return [false, 'A change is already awaiting a decision on this ' . $d['label'] . '.', 0];
    }
    $pid = (int) db()->lastInsertId();
    //  Audited with the outcome the product already uses for this, so every
    //  existing reader of the audit spine keeps working: MATERIAL for the change
    //  itself, BLOCKED for what it stops. A new vocabulary here would leave the
    //  screens and reports that read those outcomes silently empty.
    rver_audit($entity, $id, 'Material change proposed: ' . implode(', ', array_keys($diff['material']))
        . ($breach ? ' (below the approved hiring request floor: ' . implode(', ', array_keys($breach)) . ')' : ''),
        ['outcome' => 'MATERIAL', 'body' => json_encode(['proposal' => $pid, 'reason' => $reason, 'diff' => $diff])]);
    if (rver_effect_level() < 4)
        rver_audit($entity, $id, 'Recruitment paused pending the decision on this change ('
            . (RVER_EFFECT_LEVELS[rver_effect_level()] ?? '') . ')', ['outcome' => 'BLOCKED']);

    //  NO APPROVAL REQUIRED — the change still gets a reason, a new version and a
    //  full audit trail (A8's tail). It is applied at once because nobody has to
    //  decide anything.
    if (!$needs) {
        [$ok, $msg] = rver_apply($pid, ['decided_by' => rver_who(), 'note' => 'No approval required by configuration']);
        return [true, $ok ? 'Change recorded as a new version. No approval was required by your configuration.'
                          : $msg, $pid];
    }

    //  APPROVAL REQUIRED. Hand it to the EXISTING engine.
    $started = false; $apprId = 0;
    if ($route['rule'] && function_exists('appr_start')) {
        try {
            [$started, $apprId] = appr_start((string) $route['entity'], $id,
                rver_appr_ctx($entity, $proposed),
                'Change to ' . $d['label'] . ' ' . (string) ($row[$d['no']] ?? '') . ' — ' . $reason,
                rver_commitment($entity, $proposed)['total']);
        } catch (Throwable $e) { $started = false; }
    }
    if ($started && $apprId > 0) {
        db()->prepare("UPDATE requirement_change_proposals SET approval_ref=? WHERE id=?")
            ->execute([(string) $apprId, $pid]);
        rver_set_change_state($entity, $id, 'IN_PROGRESS');
        rver_audit($entity, $id, 'Change sent to the approval chain', ['outcome' => 'SUBMITTED']);
        return [true, 'Change proposed and sent for re-approval. The approved '
                    . $d['label'] . ' stays in force until a decision is made.', $pid];
    }
    //  B2 — APPROVAL IS REQUIRED BUT NO RULE MATCHES. Nothing self-approves and
    //  nobody is invented: the proposal waits, and the configuration gap is said
    //  out loud so an administrator can fix it.
    rver_set_change_state($entity, $id, 'REQUIRED');
    rver_audit($entity, $id, 'Change is waiting: approval is required but no approval rule matches it',
               ['outcome' => 'NO_RULE']);
    return [true, 'Change proposed and needs re-approval. Approval is required but no approval rule '
                . 'matches this change, so it is waiting — ask an administrator to configure an approval rule.', $pid];
}

//  APPLY AN APPROVED PROPOSAL — the only place the record's approved values move.
//
//  One transaction: the record, the new version and the proposal's closure either
//  all happen or none do, so there is never a new version whose values the record
//  does not carry.
function rver_apply($pid, array $meta = []) {
    rver_migrate();
    $p = rver_proposal($pid);
    if (!$p) return [false, 'That proposal no longer exists.'];
    if ((string) $p['status'] !== 'PENDING') return [false, 'That proposal has already been decided.'];
    $entity = (string) $p['entity']; $id = (int) $p['entity_id'];
    $d = rver_entity($entity); if (!$d) return [false, 'Unknown requirement type.'];
    $row = rver_row($entity, $id); if (!$row) return [false, 'That requirement no longer exists.'];
    $fields = rver_proposed_fields($p);
    if (!$fields) return [false, 'That proposal carries no values.'];
    $g3 = [];

    //  Only real columns, and never the identity or the bookkeeping.
    $cols = [];
    foreach (rver_table_fields($d['table']) as $c)
        if (array_key_exists($c, $fields)) $cols[$c] = $fields[$c];
    if (!$cols) return [false, 'That proposal changes nothing.'];

    //  GATE 3 — ITS SCHEMA, BEFORE THE TRANSACTION OPENS.
    //
    //  Applying a change now also raises requirement reviews, and that table has to
    //  exist first. MariaDB COMMITS IMPLICITLY ON ANY DDL, so a CREATE TABLE firing
    //  inside the transaction below would silently commit a half-applied change —
    //  the same trap RB-3 documented on the joining path. Migrated here, where a
    //  commit costs nothing.
    if (function_exists('crev_migrate')) { try { crev_migrate(); } catch (Throwable $e) {} }

    $pdo = db();
    $own = false;
    try { if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $own = true; } } catch (Throwable $e) {}
    try {
        //  Close the proposal FIRST, conditionally on it still being PENDING. Two
        //  approvers deciding at the same instant cannot both win, and the loser
        //  changes nothing — no duplicate version, no contradictory final state.
        $st = $pdo->prepare("UPDATE requirement_change_proposals
                             SET status='APPROVED', decided_by=?, decided_by_id=?, decided_at=?,
                                 decision_note=?, pending_key=NULL
                             WHERE id=? AND status='PENDING'");
        $st->execute([(string) ($meta['decided_by'] ?? rver_who()), (int) ($meta['decided_by_id'] ?? rver_who_id()),
                      rver_now(), substr((string) ($meta['note'] ?? ''), 0, 1000), (int) $pid]);
        if ($st->rowCount() < 1) throw new RuntimeException('RACE');

        if ($entity === 'HIRING_REQUEST') {
            //  Deferred until the version number is known, so the record and its
            //  snapshot are written together with the version they belong to.
            $g2deferred = $cols;
        } else {
            $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
            $pdo->prepare("UPDATE " . $d['table'] . " SET $set WHERE id=?")
                ->execute([...array_values($cols), $id]);
        }

        $ver = rver_record_version($entity, $id, $fields, [
            'proposal_id' => (int) $pid, 'approval_ref' => (string) $p['approval_ref'],
            'decided_by' => (string) ($meta['decided_by'] ?? rver_who()),
            'decided_by_id' => (int) ($meta['decided_by_id'] ?? rver_who_id()),
            'note' => 'Approved change: ' . (string) $p['reason']]);
        if ($ver <= 0) throw new RuntimeException('VERSION');
        if ($entity === 'HIRING_REQUEST') {
            if (!function_exists('hreq_apply_approved_version')
                || !hreq_apply_approved_version($id, $g2deferred, $ver)) throw new RuntimeException('WRITE');
        }
        $pdo->prepare("UPDATE requirement_change_proposals SET created_version=? WHERE id=?")
            ->execute([$ver, (int) $pid]);

        //  Keep the entity's own approved-snapshot column in step, so every
        //  existing reader of it sees the newly approved values without being
        //  rewritten. One authority, two readers.
        //  The hiring request's snapshot was written by the call above, with its
        //  version. Other entities keep theirs in step here.
        if ($entity !== 'HIRING_REQUEST') {
            try {
                $pdo->prepare("UPDATE " . $d['table'] . " SET approved_snapshot_json=?, approved_snapshot_at=? WHERE id=?")
                    ->execute([json_encode(['fields' => $fields, 'at' => rver_now(), 'version' => $ver]), rver_now(), $id]);
            } catch (Throwable $e) { /* an entity without the column */ }
        }
        rver_set_change_state($entity, $id, $entity === 'HIRING_REQUEST' ? 'REAPPROVED' : 'NONE');

        //  GATE 3 — A1. THE MOMENT A STRICTER VERSION BECOMES EFFECTIVE, EVERY
        //  ACTIVE CANDIDATE IN THAT PROCESS GOES TO A HUMAN.
        //
        //  Raised INSIDE this transaction, deliberately. A new version in force with
        //  nobody reviewed is precisely the silent wrongness Gate 2 was built to end:
        //  the requirement would have moved while five people carried on being
        //  advanced against the old one. So it fails CLOSED — if the reviews cannot
        //  be raised, the change does not take effect and the approver is told,
        //  rather than the change landing and the control quietly not running.
        //
        //  Gate 3 decides direction and audience; this gate only tells it that a new
        //  version is now in force, and from which one.
        if ($ver > 1 && function_exists('crev_raise_for_version'))
            $g3 = crev_raise_for_version($entity, $id, $ver - 1, $ver);

        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own) { try { $pdo->rollBack(); } catch (Throwable $e2) {} }
        return [false, ((string) $e->getMessage() === 'RACE')
            ? 'That proposal was decided by somebody else a moment ago.'
            : 'The change could not be applied, so nothing was changed.'];
    }
    rver_audit($entity, $id, 'Change approved — new version ' . $ver . ' is now in force',
               ['outcome' => 'APPROVED', 'body' => json_encode(['proposal' => (int) $pid, 'version' => $ver])]);
    $msg = 'Change approved. Version ' . $ver . ' is now the approved requirement.';
    //  SAY SO. A reviewer being asked to re-check people is work somebody has to do,
    //  and an approver who has just created that work should be told they created it
    //  rather than discovering it on a register later.
    $n = (int) (($g3['raised'] ?? 0) + ($g3['refreshed'] ?? 0));
    if ($n > 0) $msg .= ' ' . $n . ' candidate' . ($n === 1 ? '' : 's')
                      . ' now need' . ($n === 1 ? 's' : '') . ' a review against it.';
    return [true, $msg];
}

//  REJECT (G2) — the proposal is kept for ever, the approved version does not
//  move, and the refused values remain available to prefill a NEW proposal. It is
//  never amended in place.
function rver_reject($pid, $note = '') {
    rver_migrate();
    $p = rver_proposal($pid);
    if (!$p) return [false, 'That proposal no longer exists.'];
    if ((string) $p['status'] !== 'PENDING') return [false, 'That proposal has already been decided.'];
    $st = db()->prepare("UPDATE requirement_change_proposals
                         SET status='REJECTED', decided_by=?, decided_by_id=?, decided_at=?,
                             decision_note=?, pending_key=NULL
                         WHERE id=? AND status='PENDING'");
    $st->execute([rver_who(), rver_who_id(), rver_now(), substr((string) $note, 0, 1000), (int) $pid]);
    if ($st->rowCount() < 1) return [false, 'That proposal was decided by somebody else a moment ago.'];
    //  A refused change leaves the requirement unchanged and, for a hiring
    //  request, in the REJECTED re-approval state the product already understands.
    rver_set_change_state((string) $p['entity'], (int) $p['entity_id'],
        strtoupper((string) $p['entity']) === 'HIRING_REQUEST' ? 'REJECTED' : 'NONE');
    rver_audit((string) $p['entity'], (int) $p['entity_id'],
        'Change rejected — the approved requirement is unchanged',
        ['outcome' => 'REJECTED', 'body' => json_encode(['proposal' => (int) $pid, 'note' => (string) $note])]);
    return [true, 'Change rejected. The approved requirement is unchanged and the refused proposal is kept on record.'];
}

//  WITHDRAW (G2) — the proposer stands the change down. Reason recorded, approved
//  version untouched, normal operation restored, and a new proposal may follow.
function rver_withdraw($pid, $reason = '') {
    rver_migrate();
    $p = rver_proposal($pid);
    if (!$p) return [false, 'That proposal no longer exists.'];
    if ((string) $p['status'] !== 'PENDING') return [false, 'That proposal has already been decided.'];
    $reason = trim((string) $reason);
    if ($reason === '') return [false, 'A reason is required to withdraw a proposed change.'];
    $st = db()->prepare("UPDATE requirement_change_proposals
                         SET status='WITHDRAWN', decided_by=?, decided_by_id=?, decided_at=?,
                             decision_note=?, pending_key=NULL
                         WHERE id=? AND status='PENDING'");
    $st->execute([rver_who(), rver_who_id(), rver_now(), substr($reason, 0, 1000), (int) $pid]);
    if ($st->rowCount() < 1) return [false, 'That proposal was decided by somebody else a moment ago.'];
    rver_clear_change_state((string) $p['entity'], (int) $p['entity_id']);
    rver_audit((string) $p['entity'], (int) $p['entity_id'],
        'Change withdrawn — the approved requirement is unchanged',
        ['outcome' => 'WITHDRAWN', 'body' => json_encode(['proposal' => (int) $pid, 'reason' => $reason])]);
    return [true, 'Change withdrawn. The approved requirement is unchanged and recruitment continues as before.'];
}

//  THE ENTITY'S OWN STATE MARKER, KEPT IN STEP.
//
//  The hiring request already had a re-approval state, and the whole product reads
//  it: hreq_is_executable(), the registers, the screens, the guards. Gate 2 changes
//  WHERE the proposed values live, not what that marker means — so the marker is
//  driven from the proposal rather than replaced by a second one. One state, one
//  reader set, nothing to disagree.
//
//      no proposal      NONE / REAPPROVED   as before
//      pending, routed  IN_PROGRESS         a chain is running
//      pending, no rule REQUIRED            waiting on configuration (B2)
//      approved         REAPPROVED
//      rejected         REJECTED
//      withdrawn        NONE                normal operation restored
function rver_set_change_state($entity, $id, $state) {
    $entity = strtoupper((string) $entity); $id = (int) $id;
    try {
        //  Asked of the layer that owns the table, not written behind its back.
        if ($entity === 'HIRING_REQUEST') {
            if (function_exists('hreq_set_reapproval')) hreq_set_reapproval($id, $state);
        }
        elseif ($entity === 'REQUISITION')
            db()->prepare("UPDATE requisitions SET change_state=? WHERE id=?")->execute([$state, $id]);
    } catch (Throwable $e) {}
}
function rver_clear_change_state($entity, $id) {
    rver_set_change_state($entity, $id, 'NONE');
}

//  AUDIT — the existing spine, never a new one.
function rver_audit($entity, $id, $subject, array $opt = []) {
    if (!function_exists('act_log')) return;
    try { act_log(strtoupper((string) $entity), (int) $id, 'SYSTEM', (string) $subject, ['auto' => 1] + $opt); }
    catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  WHAT A PENDING CHANGE STOPS (A5 / Q6) — the graduated effect, expressed in
//  the EXISTING REXEC_ACTIONS vocabulary. Level 2 by default.
// ---------------------------------------------------------------------------
function rver_effect_level() {
    $n = function_exists('setting_get') ? (int) setting_get('rver_pending_effect', 2) : 2;
    return array_key_exists($n, RVER_EFFECT_LEVELS) ? $n : 2;
}

//  Why this action is refused while a change is pending, or '' when it is not.
function rver_block_reason($entity, $id, $action = 'ADVANCE') {
    rver_migrate();
    $p = rver_pending($entity, $id);
    if (!$p) return '';
    $lvl = rver_effect_level();
    $blocked = RVER_EFFECT_BLOCKS[$lvl] ?? RVER_EFFECT_BLOCKS[2];
    if (!in_array(strtoupper((string) $action), $blocked, true)) return '';
    $d = rver_entity($entity);
    return 'A change to this ' . ($d['label'] ?? 'requirement')
         . ' is awaiting a decision, so this step is paused. The approved requirement is still in force.';
}

// ---------------------------------------------------------------------------
//  THE REQUISITION EDIT GATE
//
//  Called from the requisition save BEFORE it writes. Returns null to let the
//  ordinary edit proceed, or [ok, message] when the change has been taken into
//  change control and the save must not write.
// ---------------------------------------------------------------------------
function rver_gate_requisition_edit($id, array $incoming, array $post = []) {
    rver_migrate();
    $id = (int) $id;
    $row = rver_row('REQUISITION', $id);
    if (!$row) return null;

    //  A requisition that has never had a version gets one from its current
    //  values, so there is something to judge a change against. Lazy on purpose:
    //  a workspace full of requirements raised before Gate 2 is not rewritten, and
    //  the first change to each is what gives it its baseline.
    if (!rver_current('REQUISITION', $id))
        rver_ensure_initial('REQUISITION', $id, $row,
            ['note' => 'Baseline taken from the requirement as it stood when change control began']);
    $approved = rver_approved_fields('REQUISITION', $id);
    if ($approved === null) return null;

    $proposed = rver_field_set('REQUISITION', $incoming + $row);
    $diff = rver_diff('REQUISITION', $approved, $proposed);
    $breach = rver_floor_breaches($row, $proposed);
    if (!$diff['is_material'] && !$breach) return null;            // ordinary edit

    $why = trim((string) ($post['change_reason'] ?? ''));
    if ($why === '') {
        $what = array_keys($diff['material']);
        foreach ($breach as $f => $b) $what[] = $f . ' (below the approved hiring request minimum)';
        return [false, 'This change alters what was approved (' . implode(', ', $what)
                     . '). Give a reason for the change so it can go to change control — nothing was saved.'];
    }
    return rver_propose('REQUISITION', $id, $incoming, $why,
        is_array($post['change_docs'] ?? null) ? $post['change_docs'] : []);
}

// ---------------------------------------------------------------------------
//  WHAT A SCREEN NEEDS TO SHOW (C40, §23) — one call, so no screen invents its
//  own idea of the state and none of them can disagree.
//
//  Deliberately says which values are APPROVED and which are merely PROPOSED, so
//  a screen cannot accidentally present a pending change as effective.
// ---------------------------------------------------------------------------
function rver_state($entity, $id) {
    rver_migrate();
    $entity = strtoupper((string) $entity); $id = (int) $id;
    $cur = rver_current($entity, $id);
    $p   = rver_pending($entity, $id);
    $all = rver_proposals($entity, $id);
    $hist = rver_versions($entity, $id);
    $out = [
        'entity'        => $entity,
        'entity_id'     => $id,
        'state'         => $p ? 'PENDING' : ($cur ? 'APPROVED' : 'UNVERSIONED'),
        'version'       => $cur ? (int) $cur['version'] : 0,
        'effective_from'=> $cur ? (string) $cur['effective_from'] : '',
        'versions'      => $hist,
        'version_count' => count($hist),
        'pending'       => null,
        'history'       => [],
        'effect_level'  => rver_effect_level(),
        'effect_label'  => RVER_EFFECT_LEVELS[rver_effect_level()] ?? '',
    ];
    if ($p) {
        $diff = json_decode((string) $p['diff_json'], true);
        $out['pending'] = [
            'id'        => (int) $p['id'],
            'reason'    => (string) $p['reason'],
            'by'        => (string) $p['proposed_by'],
            'at'        => (string) $p['proposed_at'],
            'material'  => is_array($diff['material'] ?? null) ? $diff['material'] : [],
            'non_material' => is_array($diff['non_material'] ?? null) ? $diff['non_material'] : [],
            'floor'     => is_array($diff['floor'] ?? null) ? $diff['floor'] : [],
            'commitment'=> $diff['commitment'] ?? null,
            'docs'      => json_decode((string) $p['docs_json'], true) ?: [],
            'needs_approval' => (int) $p['requires_approval'] === 1,
            'route'     => (string) $p['route'],
            'awaiting_rule'  => (int) $p['requires_approval'] === 1 && (string) $p['approval_ref'] === '',
            'proposed'  => rver_proposed_fields($p),
        ];
    }
    foreach ($all as $row) {
        if ((string) $row['status'] === 'PENDING') continue;
        $out['history'][] = [
            'id' => (int) $row['id'], 'status' => (string) $row['status'],
            'label' => RVER_STATUSES[(string) $row['status']] ?? (string) $row['status'],
            'reason' => (string) $row['reason'], 'by' => (string) $row['proposed_by'],
            'at' => (string) $row['proposed_at'], 'decided_by' => (string) $row['decided_by'],
            'decided_at' => (string) $row['decided_at'], 'note' => (string) $row['decision_note'],
            'version' => (int) $row['created_version'],
        ];
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  THE PANEL (C40 / §23)
//
//  ONE panel, rendered on both requirement screens, so neither can develop its
//  own idea of what a pending change means. It exists to make a single mistake
//  impossible: believing a proposed value is already in force.
//
//  So the approved version is always stated first and labelled as the one in
//  force, the proposal is always labelled as PROPOSED and NOT YET EFFECTIVE, and
//  the two are shown side by side rather than merged. Built from the existing
//  panel/pill/table classes — no new UI framework.
// ---------------------------------------------------------------------------
function rver_panel($entity, $id) {
    if (!function_exists('rver_state')) return;
    $st = rver_state($entity, $id);
    if ($st['state'] === 'UNVERSIONED' && !$st['history']) return;      // nothing to say yet
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $d = rver_entity($entity);
    $label = $d['label'] ?? 'requirement';
    $fmt = function ($v) use ($e) {
        $v = is_array($v) ? implode(', ', $v) : (string) $v;
        return $v === '' ? '<span class="muted">—</span>' : $e($v);
    };
    ?>
    <div class="panel" style="margin-top:14px">
      <h3 style="margin:0 0 8px">Change control
        <?php if ($st['version'] > 0): ?>
          <span class="pill p-ok" style="font-size:12px">Version <?= (int) $st['version'] ?> in force</span>
        <?php endif; ?>
        <?php if ($st['pending']): ?>
          <span class="pill p-warn" style="font-size:12px">A change is awaiting a decision</span>
        <?php endif; ?>
      </h3>
      <?php if ($st['version'] > 0): ?>
        <p class="muted" style="margin:0 0 10px">
          The approved <?= $e($label) ?> in force is <strong>version <?= (int) $st['version'] ?></strong><?php
          if ($st['effective_from'] !== ''): ?>, effective from <?= $e(substr($st['effective_from'], 0, 10)) ?><?php
          endif; ?>.<?php if ($st['version_count'] > 1): ?>
          <?= (int) ($st['version_count'] - 1) ?> earlier version<?= $st['version_count'] === 2 ? '' : 's' ?>
          remain<?= $st['version_count'] === 2 ? 's' : '' ?> on record.<?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if ($st['pending']): $p = $st['pending']; ?>
        <div class="panel" style="border-left:4px solid var(--warn,#c47f17);background:var(--surface-2,#fafafa)">
          <p style="margin:0 0 6px"><strong>Proposed — not yet in force.</strong>
            The approved <?= $e($label) ?> above is what recruitment is working to until this is decided.</p>
          <p class="muted" style="margin:0 0 8px">
            Proposed by <?= $e($p['by']) ?><?php if ($p['at'] !== ''): ?>
            on <?= $e(substr($p['at'], 0, 10)) ?><?php endif; ?> —
            <em><?= $e($p['reason']) ?></em></p>
          <?php if ($p['awaiting_rule']): ?>
            <p class="pill p-bad" style="display:inline-block">Approval is required but no approval rule matches this
              change, so it is waiting. Ask an administrator to configure one.</p>
          <?php elseif ($p['needs_approval']): ?>
            <p class="pill p-info" style="display:inline-block">Awaiting approval<?php
              if ($p['route'] !== ''): ?> (<?= $e($p['route'] === 'HREQ_CHANGE' || $p['route'] === 'REQ_CHANGE'
                  ? 'change-specific approval chain' : 'the requirement\'s own approval chain') ?>)<?php endif; ?></p>
          <?php endif; ?>
          <?php if ($p['material']): ?>
            <table class="tbl" style="margin-top:8px"><thead><tr>
              <th>What changes</th><th>Approved now</th><th>Proposed</th><th>Why it needs a decision</th>
            </tr></thead><tbody>
            <?php foreach ($p['material'] as $f => $c): ?>
              <tr>
                <td><strong><?= $e(str_replace('_', ' ', (string) $f)) ?></strong></td>
                <td><?= $fmt($c['was'] ?? '') ?></td>
                <td><?= $fmt($c['now'] ?? '') ?></td>
                <td class="muted"><?= $e((string) ($c['why'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
          <?php if ($p['floor']): ?>
            <p class="pill p-bad" style="display:inline-block;margin-top:8px">Below the approved hiring request
              minimum: <?= $e(implode(', ', array_map(fn($x) => (string) ($x['label'] ?? ''), $p['floor']))) ?></p>
          <?php endif; ?>
          <?php if ($p['non_material']): ?>
            <p class="muted" style="margin:8px 0 0">Also changed, without needing a decision:
              <?= $e(implode(', ', array_map(fn($f) => str_replace('_', ' ', (string) $f), array_keys($p['non_material'])))) ?></p>
          <?php endif; ?>
          <p class="muted" style="margin:8px 0 0"><?= $e($st['effect_label']) ?>.</p>
        </div>
      <?php endif; ?>

      <?php if ($st['history']): ?>
        <h4 style="margin:12px 0 6px">Previous proposals</h4>
        <table class="tbl"><thead><tr>
          <th>Proposed</th><th>By</th><th>Reason</th><th>Outcome</th><th>Decided</th>
        </tr></thead><tbody>
        <?php foreach ($st['history'] as $h): ?>
          <tr>
            <td><?= $e(substr($h['at'], 0, 10)) ?></td>
            <td><?= $e($h['by']) ?></td>
            <td><?= $e($h['reason']) ?></td>
            <td><span class="pill <?= $h['status'] === 'APPROVED' ? 'p-ok' : ($h['status'] === 'REJECTED' ? 'p-bad' : 'p-mut') ?>"><?= $e($h['label']) ?></span><?php
                if ((int) $h['version'] > 0): ?> <span class="muted">version <?= (int) $h['version'] ?></span><?php endif; ?></td>
            <td><?= $e($h['decided_by']) ?><?php if ($h['decided_at'] !== ''): ?>
                <span class="muted"><?= $e(substr($h['decided_at'], 0, 10)) ?></span><?php endif; ?>
                <?php if ($h['note'] !== ''): ?><div class="muted"><?= $e($h['note']) ?></div><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
        <p class="muted" style="margin:6px 0 0">A refused or withdrawn proposal is kept for ever and never amended —
          a new attempt is a new proposal.</p>
      <?php endif; ?>

      <?php if ($st['versions'] && count($st['versions']) > 1): ?>
        <h4 style="margin:12px 0 6px">Approved versions</h4>
        <table class="tbl"><thead><tr>
          <th>Version</th><th>Effective from</th><th>Decided by</th><th>Why</th>
        </tr></thead><tbody>
        <?php foreach (array_reverse($st['versions']) as $v): ?>
          <tr<?= (int) $v['version'] === $st['version'] ? ' style="font-weight:600"' : '' ?>>
            <td><?= (int) $v['version'] ?><?= (int) $v['version'] === $st['version'] ? ' <span class="pill p-ok">in force</span>' : '' ?></td>
            <td><?= $e(substr((string) $v['effective_from'], 0, 10)) ?></td>
            <td><?= $e((string) $v['decided_by']) ?></td>
            <td class="muted"><?= $e((string) $v['note']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
//  THE ISSUED-OFFER BOUNDARY (A2)
//
//  A new approved version affects candidates only UNTIL an offer has been issued.
//  Somebody holding an issued offer, an accepted offer, or already in the
//  workforce stays attached to the version that was in force when that offer went
//  out — they were made a commitment against a stated requirement, and a later
//  change to that requirement does not retrospectively move them.
//
//  A DRAFT offer is still in scope: nothing has been promised yet.
//
//  DERIVED from the version chain and the offer's own issue timestamp rather than
//  stored on the offer. That way it is also correct for offers issued before this
//  gate existed, and there is no second copy of the answer to drift.
//
//  Gate 2 establishes this boundary ONLY. What a later gate then does about those
//  candidates — Review Required — is Gate 3's, and is not implemented here.
// ---------------------------------------------------------------------------

//  Which version was in force at a given moment: the highest version whose
//  effective date is not after it.
function rver_version_at($entity, $id, $when) {
    rver_migrate();
    $when = trim((string) $when);
    if ($when === '') return 0;
    $best = 0;
    foreach (rver_versions($entity, $id) as $v) {
        $from = trim((string) $v['effective_from']);
        if ($from === '' || strcmp($from, $when) <= 0) $best = (int) $v['version'];
    }
    return $best;
}

//  The statuses that mean a commitment has left the building.
const RVER_OFFER_COMMITTED = ['ISSUED', 'VIEWED', 'ACCEPTED'];

//  The version a candidate is PINNED to by an issued offer, or 0 when they are not
//  pinned at all. Also pinned once they are in the workforce, because a joining is
//  a stronger commitment than the offer that preceded it.
function rver_offer_pinned_version($entity, $id, $candidateId) {
    rver_migrate();
    $cid = (int) $candidateId; if ($cid <= 0) return 0;
    $in = "'" . implode("','", RVER_OFFER_COMMITTED) . "'";
    try {
        //  Only columns job_offers actually has: it carries issued_at and created_at
        //  and no updated_at, and selecting one that is not there would throw, be
        //  swallowed, and silently report the candidate as unpinned.
        $o = ops_one("SELECT status, issued_at, created_at, COALESCE(req_version_at_issue,0) rvi
                      FROM job_offers
                      WHERE candidate_id=? AND UPPER(COALESCE(status,'')) IN ($in)
                      ORDER BY id ASC LIMIT 1", [$cid]);
    } catch (Throwable $e) { $o = null; }
    if (!$o) {
        //  No offer, but already in the workforce: the joining itself is the
        //  commitment, so they are pinned to where the requirement stood then.
        try { $joined = (string) ops_val("SELECT COALESCE(joined_at,'') FROM candidates WHERE id=?", [$cid]); }
        catch (Throwable $e) { $joined = ''; }
        return $joined !== '' ? rver_version_at($entity, $id, $joined) : 0;
    }
    //  THE RECORDED ANSWER WINS. It was stamped at the moment of issue, so it
    //  cannot be confused by an approval that happened in the same second.
    if ((int) ($o['rvi'] ?? 0) > 0) return (int) $o['rvi'];
    //  Only an offer issued before this gate has to be derived, and for those the
    //  timestamp is the best evidence that exists.
    $at = trim((string) ($o['issued_at'] ?? ''));
    if ($at === '') $at = trim((string) ($o['created_at'] ?? ''));
    $v = rver_version_at($entity, $id, $at);
    //  An offer issued before any version was recorded still pins them: to the
    //  earliest version there is, which is the closest thing to what they were
    //  offered against.
    if ($v <= 0) { $all = rver_versions($entity, $id); $v = $all ? (int) $all[0]['version'] : 0; }
    return $v;
}

//  THE VERSION THAT APPLIES TO ONE CANDIDATE. The pinned one when they hold a
//  commitment, otherwise the latest approved one.
//
//  Q13's relationship, expressed as one function so Gate 3 has a single place to
//  ask "which requirement does this person have to meet?" — and so that nothing in
//  Gate 2 has to guess at it.
function rver_applicable_version($entity, $id, $candidateId) {
    $pinned = rver_offer_pinned_version($entity, $id, $candidateId);
    if ($pinned > 0) return $pinned;
    $cur = rver_current($entity, $id);
    return $cur ? (int) $cur['version'] : 0;
}

//  The candidates a new approved version reaches, and the ones it does not.
//  Gate 3 reads this; Gate 2 only has to get the boundary right.
function rver_version_audience($entity, $id) {
    rver_migrate();
    $out = ['in_scope' => [], 'pinned' => []];
    if (strtoupper((string) $entity) !== 'REQUISITION') return $out;
    try { $rows = ops_all("SELECT id FROM candidates WHERE requisition_id=? ORDER BY id", [(int) $id]) ?: []; }
    catch (Throwable $e) { return $out; }
    $cur = rver_current($entity, $id);
    $curV = $cur ? (int) $cur['version'] : 0;
    foreach ($rows as $r) {
        $cid = (int) $r['id'];
        $p = rver_offer_pinned_version($entity, $id, $cid);
        if ($p > 0 && $p < $curV) $out['pinned'][$cid] = $p;
        else $out['in_scope'][] = $cid;
    }
    return $out;
}

// ===========================================================================
//  GATE 3 — WHICH WAY DID THE REQUIREMENT MOVE?
//
//  Gate 2 answers "what changed, and is it material". Gate 3 needs one more
//  thing: DIRECTION. A requirement that asks for more than it used to is not the
//  same event as one that asks for less, and the locked rules treat them
//  oppositely — stricter sends every active candidate to a human (A1), relaxed
//  deliberately does nothing (§18).
//
//  This is an EXTENSION of the Gate 2 comparison, not a second one. It consumes
//  rver_diff()'s own output, so there is exactly one algorithm that decides
//  whether two field sets differ, and it reuses RVER_SPEC_FLOOR's directions and
//  rver_qual_rank()'s configured order — the same semantics A8 already judges a
//  requisition's floor with. Nothing here re-reads a row or re-compares a value.
//
//  THREE DIRECTIONS, because two are not enough:
//
//    stricter  — the bar a candidate must clear went UP. More experience, a
//                higher qualification, an extra essential skill.
//    relaxed   — the bar came DOWN. Never triggers anything (§18).
//    redefined — what is wanted CHANGED, neither up nor down: the role, the
//                grade, the department, the branch. Candidates were sourced for
//                something else. Not "stricter" in the arithmetic sense, which
//                is exactly why it needs naming rather than silently landing in
//                "neither" and reaching nobody.
//
//  Everything else — headcount, budget, the client, the authorisation basis — is
//  'neither'. A budget increase is material (Gate 2 stops execution while it is
//  pending) but it does not change what a candidate has to be, so forcing a human
//  to re-read every CV because a rate moved would teach people to click through
//  reviews without looking. That is the failure mode this engine exists to avoid.
// ===========================================================================

//  Fields whose change REDEFINES what is being recruited. Each is already in
//  Gate 2's material list with the same business reason; this says which of them
//  a candidate is judged by, rather than which ones an approver authorises.
const RVER_REDEFINES = [
    'designation'    => 'the role being recruited',
    'grade'          => 'the pay band',
    'department'     => 'the department the person joins',
    'department_id'  => 'the department the person joins',
    'team_role'      => 'the kind of team member being recruited',
    'office_id'      => 'the branch the person would work from',
    'trade_id'       => 'the trade being recruited',
    'skill_id'       => 'the skill being recruited',
];

//  Did one field get stricter, more relaxed, or redefined?
//
//  Returns 'stricter' | 'relaxed' | 'redefined' | 'neither'. A field that is both
//  (a skill added AND another removed) is 'redefined': it is not a clean raise,
//  and calling it stricter would overstate what happened while calling it relaxed
//  would hide that a new skill is now compulsory.
function rver_field_direction($field, $was, $now) {
    $f = (string) $field;
    if (isset(RVER_SPEC_FLOOR[$f])) {
        $dir = RVER_SPEC_FLOOR[$f]['dir'];
        if ($dir === 'min') {
            $a = (float) $was; $b = (float) $now;
            if ($b > $a) return 'stricter';
            if ($b < $a) return 'relaxed';
            return 'neither';
        }
        if ($dir === 'rank') {
            //  The CONFIGURED order decides, so a workspace that defines its own
            //  qualification ladder is judged against its own ladder. -1 means the
            //  value is not in the ladder at all: naming an unknown qualification
            //  is a redefinition, not a raise, because nobody can say how high it is.
            $a = rver_qual_rank($was); $b = rver_qual_rank($now);
            if ($a < 0 && $b < 0) return 'neither';
            if ($b < 0 || $a < 0) return 'redefined';
            if ($b > $a) return 'stricter';
            if ($b < $a) return 'relaxed';
            return 'neither';
        }
        if ($dir === 'set') {
            $split = fn($v) => array_values(array_filter(array_map(
                fn($x) => strtolower(trim((string) $x)),
                preg_split('~[,;\n]~', (string) $v) ?: []), fn($x) => $x !== ''));
            $a = $split($was); $b = $split($now);
            $added   = array_diff($b, $a);
            $removed = array_diff($a, $b);
            if ($added && $removed) return 'redefined';
            if ($added)   return 'stricter';
            if ($removed) return 'relaxed';
            return 'neither';
        }
    }
    if (isset(RVER_REDEFINES[$f])) return 'redefined';
    return 'neither';
}

//  THE DIRECTION OF A WHOLE CHANGE, built on rver_diff() and on nothing else.
//
//  $was / $now are field sets — an approved version's fields and the newly
//  approved ones. The verdict is deliberately coarse: a change is stricter if ANY
//  candidate-facing field got stricter, because a candidate has to clear every
//  part of the bar, not the average of it.
function rver_strictness($entity, array $was, array $now) {
    $d = rver_diff($entity, $was, $now);
    $out = ['stricter' => [], 'relaxed' => [], 'redefined' => [], 'neither' => [],
            'is_stricter' => false, 'is_relaxed' => false, 'is_redefined' => false,
            'changed' => $d['changed'], 'summary' => ''];
    foreach ($d['changed'] as $f => $rec) {
        //  'commitment' is Gate 2's derived verdict, not a column a candidate is
        //  judged by. It must never be read as a direction.
        if ($f === 'commitment') { $out['neither'][$f] = $rec; continue; }
        $dir = rver_field_direction($f, $rec['was'], $rec['now']);
        $rec['direction'] = $dir;
        $rec['label'] = RVER_SPEC_FLOOR[$f]['label'] ?? (RVER_REDEFINES[$f] ?? $f);
        $out[$dir][$f] = $rec;
    }
    $out['is_stricter']  = !empty($out['stricter']);
    $out['is_relaxed']   = !empty($out['relaxed']);
    $out['is_redefined'] = !empty($out['redefined']);

    $bits = [];
    foreach (['stricter' => 'stricter', 'redefined' => 'changed', 'relaxed' => 'relaxed'] as $k => $word)
        foreach ($out[$k] as $rec) $bits[] = (string) $rec['label'] . ' ' . $word;
    $out['summary'] = $bits ? ucfirst(implode('; ', $bits)) : 'Nothing a candidate is judged by changed.';
    return $out;
}

//  The same question asked of two VERSIONS of one requirement, by number. This is
//  how Gate 3 asks "did version 3 raise the bar above version 2" without ever
//  touching the version store itself.
function rver_strictness_between($entity, $id, $fromVersion, $toVersion) {
    $a = rver_version($entity, $id, (int) $fromVersion);
    $b = rver_version($entity, $id, (int) $toVersion);
    if (!$a || !$b) return null;
    //  Decoded by the version store's OWN reader, so a snapshot's shape is known
    //  in one place. Decoding it here would be a second reader of the same column.
    $fa = rver_snapshot_fields($a);
    $fb = rver_snapshot_fields($b);
    if (!is_array($fa) || !is_array($fb)) return null;
    return rver_strictness($entity, $fa, $fb);
}
