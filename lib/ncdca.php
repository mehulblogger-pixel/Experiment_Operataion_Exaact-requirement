<?php
// ===========================================================================
//  NCDCA — Universal Nonconformity, NCR, CAPA, Deviation & Concession Engine
//          (Phase 8)
// ---------------------------------------------------------------------------
//  This is NOT a new NCR form and NOT a QMS monster. The app ALREADY has the
//  corrective-action spine, and it is LOCKED:
//    * nonconformities (lib/ncr.php) — the issue record: source-linking
//      (inspection / audit / complaint / equipment / deputation), containment,
//      disposition, closure. ncr_create() is the universal entry every module
//      already calls.
//    * capa + capa_actions (lib/capa.php) — correction (immediate_action),
//      root cause + method, recurrence check (similar_*), a multi-action
//      register, effectiveness verification (verify_due / verified / effective)
//      and a reopen chain (follows_id).
//    * Evidence (report_files), approvals, the Template Builder (URFE), the
//      Service-Scope engine, the audit trail (idems_log / act_log).
//
//  So NCDCA ELEVATES that spine into a Universal Issue Engine and fills only the
//  genuine gaps — it creates NO duplicate NCR / CAPA / evidence / complaint /
//  audit-finding record:
//
//    1. ISSUE-TYPE dimension — a nonconformity becomes ONE TYPE of issue
//       (NCR / Observation / OFI / Audit-finding / Deviation / Discrepancy …),
//       plus a finding classification, responsibility and visibility. An
//       observation is no longer forced to be an "NCR" (§96 — these are not
//       interchangeable). Additive columns on the existing record.
//    2. Controlled ALTERNATE PATHS — Deviation / Concession / Waiver as their
//       own approval-gated records (§36/§37/§38 — a departure is NOT a
//       nonconformity closure). One typed table, its own workflow & validity.
//    3. DISPUTE / finding-response (§53/§54) — the original finding is retained.
//    4. DUE-DATE EXTENSION history (§32) — the original date is never overwritten.
//    5. Effectiveness-result vocabulary (§34), root-cause-method master (§25).
//    6. Issue REPORT TYPES via the Template Builder (§80 — no new report engine).
//    7. Advisory AI QA (§75-79) via idems_qa_run — flags; never invents, changes
//       severity/responsibility, approves or closes.
//
//  Independence (§2/§48): everything self-gates on the NCR_CAPA service and is
//  function_exists-guarded — a client may use ONLY NCR/CAPA with every other
//  service off, and standalone manual issues work with no linked record.
// ===========================================================================

// ---- Masters (all editable via the lookup engine) --------------------------
// §6 Issue types — NCR is ONE of them, not the whole system.
const NCDCA_ISSUE_TYPES = [
    'NCR'=>'NCR','NONCONFORMITY'=>'Nonconformity','OBSERVATION'=>'Observation','OFI'=>'Opportunity for improvement',
    'AUDIT_FINDING'=>'Audit finding','INSPECTION_FINDING'=>'Inspection finding','DEVIATION'=>'Deviation',
    'DOC_DISCREPANCY'=>'Document discrepancy','PROCESS_DEVIATION'=>'Process deviation','PRODUCT_NC'=>'Product nonconformity',
    'SERVICE_NC'=>'Service nonconformity','COMPLAINT'=>'Complaint','EXPEDITING_ISSUE'=>'Expediting issue',
    'SITE_ISSUE'=>'Site issue','CONCESSION'=>'Concession','WAIVER'=>'Waiver','EXCEPTION'=>'Exception','OTHER'=>'Other',
];
// §16 Finding classification — DISTINCT from severity (§17: never auto-equate).
const NCDCA_CLASSES = [
    'CONFORMING'=>'Conforming','OBSERVATION'=>'Observation','OFI'=>'Opportunity for improvement',
    'MINOR_NC'=>'Minor nonconformity','MAJOR_NC'=>'Major nonconformity','CRITICAL_NC'=>'Critical nonconformity',
    'DEVIATION'=>'Deviation','OTHER'=>'Other',
];
// §55 Responsibility — never auto-assigned.
const NCDCA_RESPONSIBILITY = [
    'VENDOR'=>'Vendor','CLIENT'=>'Client','TPIA'=>'TPIA','CONTRACTOR'=>'Contractor','SUBCONTRACTOR'=>'Subcontractor',
    'SUPPLIER'=>'Supplier','DESIGNER'=>'Designer','MANUFACTURER'=>'Manufacturer','SERVICE_PROVIDER'=>'Service provider',
    'SHARED'=>'Shared','UNKNOWN'=>'Unknown','OTHER'=>'Other',
];
// §90 Visibility — internal investigation notes are never exposed automatically.
const NCDCA_VISIBILITY = [
    'INTERNAL'=>'Internal only','CLIENT_VISIBLE'=>'Client visible','VENDOR_VISIBLE'=>'Vendor visible',
    'RESTRICTED'=>'Restricted','MGMT_ONLY'=>'Management only',
];
// §25 Root-cause methods (a master; CAPA already stores rc_method free-text).
const NCDCA_RC_METHODS = [
    '5WHY'=>'5 Why','FISHBONE'=>'Fishbone / Ishikawa','8D'=>'8D','FAULT_TREE'=>'Fault tree','PARETO'=>'Pareto',
    'CAUSE_EFFECT'=>'Cause & effect','BARRIER'=>'Barrier analysis','FREEFORM'=>'Free-form','OTHER'=>'Other',
];
// §34 Effectiveness result.
const NCDCA_EFFECTIVENESS = [
    'EFFECTIVE'=>'Effective','PARTIAL'=>'Partially effective','NOT_EFFECTIVE'=>'Not effective',
    'UNABLE'=>'Unable to verify','NOT_REQUIRED'=>'Not required',
];
// §39 Departure (deviation / concession / waiver) approval workflow.
const NCDCA_DEPARTURE_STATUS = [
    'DRAFT'=>'Draft','SUBMITTED'=>'Submitted','TECH_REVIEW'=>'Technical review','CLIENT_REVIEW'=>'Client review',
    'APPROVED'=>'Approved','REJECTED'=>'Rejected','EXPIRED'=>'Expired','CLOSED'=>'Closed',
];
const NCDCA_DEPARTURE_KINDS = ['DEVIATION'=>'Deviation','CONCESSION'=>'Concession','WAIVER'=>'Waiver'];
// §22 Broader disposition set (the existing NCR_DISPOSITIONS stay; this adds the
// physical-product options a manufacturing client expects — editable).
const NCDCA_DISPOSITIONS = [
    'ACCEPT'=>'Accept','REJECT'=>'Reject','REWORK'=>'Rework','REPAIR'=>'Repair','REPLACE'=>'Replace',
    'SCRAP'=>'Scrap','RETURN'=>'Return','USE_AS_IS'=>'Use as is','CONCESSION'=>'Concession','WAIVER'=>'Waiver','PENDING'=>'Pending decision',
];
// §53 Dispute.
const NCDCA_DISPUTE_STATUS = ['OPEN'=>'Open','UNDER_REVIEW'=>'Under review','UPHELD'=>'Finding upheld','REVISED'=>'Finding revised','WITHDRAWN'=>'Withdrawn'];
const NCDCA_RESPONSE_KINDS = ['ACCEPT'=>'Acceptance','PARTIAL'=>'Partial acceptance','DISAGREE'=>'Disagreement','CLARIFY'=>'Clarification','PROPOSE'=>'Proposed action'];

// ---------------------------------------------------------------------------
//  Schema — additive only. Elevate the existing record; add the gap tables.
// ---------------------------------------------------------------------------
function ncdca_migrate() {
    static $done = false; if ($done) return; $done = true;
    // The corrective-action spine must exist before we elevate it — both are
    // idempotent (static-guarded), so calling them here just guarantees order
    // regardless of where run_schema places this migrate.
    if (function_exists('capa_migrate')) { try { capa_migrate(); } catch (Throwable $e) {} }
    if (function_exists('ncr_migrate'))  { try { ncr_migrate(); }  catch (Throwable $e) {} }
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('issue_type',            'Issue type',              NCDCA_ISSUE_TYPES,      'ncdca');
        lk_ensure_type_map('issue_class',           'Finding classification',  NCDCA_CLASSES,          'ncdca');
        lk_ensure_type_map('issue_responsibility',  'Responsible party',       NCDCA_RESPONSIBILITY,   'ncdca');
        lk_ensure_type_map('issue_visibility',      'Issue visibility',        NCDCA_VISIBILITY,       'ncdca');
        lk_ensure_type_map('rc_method',             'Root-cause method',       NCDCA_RC_METHODS,       'ncdca');
        lk_ensure_type_map('effectiveness_result',  'Effectiveness result',    NCDCA_EFFECTIVENESS,    'ncdca');
        lk_ensure_type_map('departure_status',      'Departure status',        NCDCA_DEPARTURE_STATUS, 'ncdca');
        lk_ensure_type_map('issue_disposition',     'Disposition',             NCDCA_DISPOSITIONS,     'ncdca');
        lk_ensure_type_map('dispute_status',        'Dispute status',          NCDCA_DISPUTE_STATUS,   'ncdca');
    }
    // Elevate the EXISTING issue record — a nonconformity gains an issue type,
    // classification, responsibility and visibility. Nothing is removed.
    if (function_exists('ensure_column')) {
        ensure_column('nonconformities', 'issue_type',     "VARCHAR(30) DEFAULT 'NCR'");
        ensure_column('nonconformities', 'issue_class',     "VARCHAR(30) DEFAULT ''");
        ensure_column('nonconformities', 'responsibility',  "VARCHAR(30) DEFAULT ''");
        ensure_column('nonconformities', 'visibility',      "VARCHAR(30) DEFAULT 'INTERNAL'");
        // CAPA gains a richer effectiveness result beside its existing flag.
        ensure_column('capa', 'eff_result', "VARCHAR(30) DEFAULT ''");
    }
    $pk = pk_clause();

    // §36/§37/§38 Controlled departures — deviation / concession / waiver share a
    // shape, so ONE typed table (kind) with its own approval workflow & validity.
    db()->exec("CREATE TABLE IF NOT EXISTS issue_departures (
        id $pk, kind VARCHAR(16) DEFAULT 'DEVIATION', ref VARCHAR(40) DEFAULT '', ncr_id INT DEFAULT 0,
        client_id INT DEFAULT 0, vendor_id INT DEFAULT 0, office_id INT DEFAULT 0, project VARCHAR(200) DEFAULT '',
        title VARCHAR(255) DEFAULT '', requirement VARCHAR(600) DEFAULT '', planned_condition TEXT, actual_condition TEXT,
        reason TEXT, justification TEXT, scope VARCHAR(400) DEFAULT '', risk VARCHAR(20) DEFAULT '',
        compensating_controls TEXT, conditions TEXT, temporary INT DEFAULT 0, valid_from VARCHAR(20) DEFAULT '',
        valid_to VARCHAR(20) DEFAULT '', approver VARCHAR(150) DEFAULT '', client_approval INT DEFAULT 0,
        client_approved_by VARCHAR(150) DEFAULT '', approved_on VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'DRAFT',
        change_control_ref VARCHAR(80) DEFAULT '', visibility VARCHAR(30) DEFAULT 'INTERNAL',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('issue_departures', 'ref');

    // §53/§54 Dispute + response — the ORIGINAL finding is retained; this records
    // the challenge and its resolution, never edits the nonconformity in place.
    db()->exec("CREATE TABLE IF NOT EXISTS issue_disputes (
        id $pk, ncr_id INT DEFAULT 0, party VARCHAR(20) DEFAULT 'VENDOR', disputed_field VARCHAR(60) DEFAULT '',
        reason TEXT, response_kind VARCHAR(20) DEFAULT '', response TEXT, proposed_action TEXT, evidence_ref VARCHAR(300) DEFAULT '',
        decision VARCHAR(20) DEFAULT '', decision_note TEXT, decided_by VARCHAR(150) DEFAULT '', decided_on VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    // §32 Due-date extension history — the ORIGINAL due date is never overwritten.
    db()->exec("CREATE TABLE IF NOT EXISTS issue_extensions (
        id $pk, entity VARCHAR(12) DEFAULT 'NCR', entity_id INT DEFAULT 0, original_due VARCHAR(20) DEFAULT '',
        requested_due VARCHAR(20) DEFAULT '', reason TEXT, requested_by VARCHAR(150) DEFAULT '',
        approved_by VARCHAR(150) DEFAULT '', approved_on VARCHAR(20) DEFAULT '', new_due VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'REQUESTED', created_at VARCHAR(30) DEFAULT '')");

    if (function_exists('setting_get') && !setting_get('ncdca_library_seeded_v1', '')) {
        try { ncdca_seed_library(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ncdca_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('ncdca_samples_seeded_v1', '')) {
        try { ncdca_seed_sample_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ncdca_samples_seeded_v1', '1');
    }
}

// ---------------------------------------------------------------------------
//  Service gate + masters getters.
// ---------------------------------------------------------------------------
function ncdca_enabled() { return !function_exists('svc_globally_active') || svc_globally_active('NCR_CAPA'); }
function ncdca_issue_types()     { return function_exists('lk_options_or') ? lk_options_or('issue_type', NCDCA_ISSUE_TYPES) : NCDCA_ISSUE_TYPES; }
function ncdca_classes()         { return function_exists('lk_options_or') ? lk_options_or('issue_class', NCDCA_CLASSES) : NCDCA_CLASSES; }
function ncdca_responsibilities(){ return function_exists('lk_options_or') ? lk_options_or('issue_responsibility', NCDCA_RESPONSIBILITY) : NCDCA_RESPONSIBILITY; }
function ncdca_visibilities()    { return function_exists('lk_options_or') ? lk_options_or('issue_visibility', NCDCA_VISIBILITY) : NCDCA_VISIBILITY; }
function ncdca_effectiveness()   { return function_exists('lk_options_or') ? lk_options_or('effectiveness_result', NCDCA_EFFECTIVENESS) : NCDCA_EFFECTIVENESS; }
function ncdca_departure_statuses(){ return function_exists('lk_options_or') ? lk_options_or('departure_status', NCDCA_DEPARTURE_STATUS) : NCDCA_DEPARTURE_STATUS; }
function ncdca_actor() { return function_exists('current_user') ? (string)(current_user()['username'] ?? user_name(current_user()) ?? 'system') : 'system'; }

// Called from ncr_create() (guarded) to stamp the classification the caller
// passed. Absent keys leave the record as a plain NCR (§48 backward-compatible).
function ncdca_apply_classification($ncrId, $b) {
    $ncrId = (int)$ncrId; if (!$ncrId) return;
    $sets = []; $args = [];
    $map = ['issue_type'=>NCDCA_ISSUE_TYPES, 'issue_class'=>NCDCA_CLASSES,
            'responsibility'=>NCDCA_RESPONSIBILITY, 'visibility'=>NCDCA_VISIBILITY];
    foreach ($map as $col => $valid) {
        if (!isset($b[$col]) || (string)$b[$col] === '') continue;
        $v = strtoupper((string)$b[$col]);
        if (isset($valid[$v])) { $sets[] = "$col=?"; $args[] = $v; }
    }
    if (!$sets) return;
    $args[] = $ncrId;
    try { db()->prepare("UPDATE nonconformities SET " . implode(',', $sets) . " WHERE id=?")->execute($args); } catch (Throwable $e) {}
}
// Set the type/class/etc on an existing issue (an authorised human action).
function ncdca_classify($ncrId, $data) {
    ncdca_apply_classification($ncrId, $data);
    if (function_exists('ncr_log')) { try { ncr_log($ncrId, 'CLASSIFIED', 'Issue type / classification set'); } catch (Throwable $e) {} }
}

// ---------------------------------------------------------------------------
//  §36/§37/§38 Controlled departures — deviation / concession / waiver.
// ---------------------------------------------------------------------------
function ncdca_departure_ref($kind) {
    $p = ['DEVIATION'=>'DEV','CONCESSION'=>'CON','WAIVER'=>'WAV'][$kind] ?? 'DEP';
    $n = (int)(ops_val("SELECT COUNT(*) FROM issue_departures WHERE kind=?", [$kind]) ?: 0);
    return $p . '-' . date('Y') . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
}
function ncdca_departure_create($kind, $data) {
    $kind = isset(NCDCA_DEPARTURE_KINDS[$kind]) ? $kind : 'DEVIATION';
    $ref = ncdca_departure_ref($kind); $now = date('c');
    db()->prepare("INSERT INTO issue_departures
        (kind,ref,ncr_id,client_id,vendor_id,office_id,project,title,requirement,planned_condition,actual_condition,
         reason,justification,scope,risk,compensating_controls,conditions,temporary,valid_from,valid_to,
         status,visibility,created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'DRAFT',?,?,?,?)")
        ->execute([$kind, $ref, (int)($data['ncr_id'] ?? 0), (int)($data['client_id'] ?? 0), (int)($data['vendor_id'] ?? 0),
            (int)($data['office_id'] ?? 0), (string)($data['project'] ?? ''), substr((string)($data['title'] ?? ''), 0, 255),
            (string)($data['requirement'] ?? ''), (string)($data['planned_condition'] ?? ''), (string)($data['actual_condition'] ?? ''),
            (string)($data['reason'] ?? ''), (string)($data['justification'] ?? ''), (string)($data['scope'] ?? ''),
            (string)($data['risk'] ?? ''), (string)($data['compensating_controls'] ?? ''), (string)($data['conditions'] ?? ''),
            !empty($data['temporary']) ? 1 : 0, (string)($data['valid_from'] ?? ''), (string)($data['valid_to'] ?? ''),
            (string)($data['visibility'] ?? 'INTERNAL'), ncdca_actor(), $now, $now]);
    $id = (int)db()->lastInsertId();
    if (function_exists('idems_log')) { try { idems_log('departure', $id, 'CREATE', ['kind'=>$kind, 'ref'=>$ref]); } catch (Throwable $e) {} }
    return $id;
}
// Advance the approval workflow. A departure is never "auto-accepted" (§37) — an
// authorised person moves it, and APPROVED stamps who/when.
function ncdca_departure_set_status($id, $status, $approver = '', $clientApproval = null) {
    $id = (int)$id; if (!$id || !isset(NCDCA_DEPARTURE_STATUS[$status])) return false;
    $approvedOn = $status === 'APPROVED' ? date('Y-m-d') : '';
    $sets = "status=?, updated_at=?"; $args = [$status, date('c')];
    if ($approver !== '') { $sets .= ", approver=?"; $args[] = $approver; }
    if ($approvedOn !== '') { $sets .= ", approved_on=?"; $args[] = $approvedOn; }
    if ($clientApproval !== null) { $sets .= ", client_approval=?"; $args[] = $clientApproval ? 1 : 0; }
    $args[] = $id;
    db()->prepare("UPDATE issue_departures SET $sets WHERE id=?")->execute($args);
    if (function_exists('idems_log')) { try { idems_log('departure', $id, 'STATUS', ['new'=>$status]); } catch (Throwable $e) {} }
    return true;
}
function ncdca_departures($kind = null, $filter = []) {
    $w = ['1=1']; $a = [];
    if ($kind !== null) { $w[] = 'kind=?'; $a[] = $kind; }
    if (!empty($filter['status'])) { $w[] = 'status=?'; $a[] = $filter['status']; }
    if (!empty($filter['ncr_id'])) { $w[] = 'ncr_id=?'; $a[] = (int)$filter['ncr_id']; }
    if (!empty($filter['client_id'])) { $w[] = 'client_id=?'; $a[] = (int)$filter['client_id']; }
    return ops_all("SELECT * FROM issue_departures WHERE " . implode(' AND ', $w) . " ORDER BY id DESC", $a) ?: [];
}
function ncdca_departure($id) { return ops_one("SELECT * FROM issue_departures WHERE id=?", [(int)$id]) ?: null; }
// Expire departures past their validity — a deterministic sweep for the cron /
// dashboard. Never deletes; only flips APPROVED → EXPIRED.
function ncdca_expire_departures($today = null) {
    $today = $today ?: date('Y-m-d'); $n = 0;
    foreach (ncdca_departures(null, ['status'=>'APPROVED']) as $d) {
        if ($d['valid_to'] !== '' && strtotime($d['valid_to']) && strtotime($d['valid_to']) < strtotime($today)) {
            ncdca_departure_set_status((int)$d['id'], 'EXPIRED'); $n++;
        }
    }
    return $n;
}

// ---------------------------------------------------------------------------
//  §53/§54 Dispute + response — retains the original finding.
// ---------------------------------------------------------------------------
function ncdca_dispute_create($ncrId, $data) {
    db()->prepare("INSERT INTO issue_disputes (ncr_id,party,disputed_field,reason,response_kind,response,proposed_action,evidence_ref,status,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?, 'OPEN', ?,?)")
        ->execute([(int)$ncrId, strtoupper((string)($data['party'] ?? 'VENDOR')), (string)($data['disputed_field'] ?? ''),
            (string)($data['reason'] ?? ''), strtoupper((string)($data['response_kind'] ?? '')), (string)($data['response'] ?? ''),
            (string)($data['proposed_action'] ?? ''), (string)($data['evidence_ref'] ?? ''), ncdca_actor(), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('ncr_log')) { try { ncr_log((int)$ncrId, 'DISPUTED', 'Finding disputed by ' . ($data['party'] ?? 'a party')); } catch (Throwable $e) {} }
    return $id;
}
function ncdca_dispute_decide($id, $decision, $note = '') {
    $id = (int)$id; if (!$id || !isset(NCDCA_DISPUTE_STATUS[$decision])) return false;
    db()->prepare("UPDATE issue_disputes SET decision=?, decision_note=?, decided_by=?, decided_on=?, status=? WHERE id=?")
        ->execute([$decision, (string)$note, ncdca_actor(), date('Y-m-d'), $decision, $id]);
    return true;
}
function ncdca_disputes($ncrId) { return ops_all("SELECT * FROM issue_disputes WHERE ncr_id=? ORDER BY id DESC", [(int)$ncrId]) ?: []; }

// ---------------------------------------------------------------------------
//  §32 Due-date extension — request + approve; original date preserved.
// ---------------------------------------------------------------------------
function ncdca_extension_request($entity, $entityId, $originalDue, $requestedDue, $reason) {
    db()->prepare("INSERT INTO issue_extensions (entity,entity_id,original_due,requested_due,reason,requested_by,status,created_at)
        VALUES (?,?,?,?,?,?, 'REQUESTED', ?)")
        ->execute([strtoupper((string)$entity), (int)$entityId, (string)$originalDue, (string)$requestedDue, (string)$reason, ncdca_actor(), date('c')]);
    return (int)db()->lastInsertId();
}
// Approve an extension: records the new due date and (only then) moves the live
// record's due date forward. The ORIGINAL is kept in the extension row (§32).
function ncdca_extension_approve($id, $approvedBy = '') {
    $id = (int)$id; $ext = ops_one("SELECT * FROM issue_extensions WHERE id=?", [$id]); if (!$ext) return false;
    $new = (string)$ext['requested_due'];
    db()->prepare("UPDATE issue_extensions SET status='APPROVED', approved_by=?, approved_on=?, new_due=? WHERE id=?")
        ->execute([$approvedBy !== '' ? $approvedBy : ncdca_actor(), date('Y-m-d'), $new, $id]);
    // Move the live record's due date — never touching the stored original.
    if ($ext['entity'] === 'NCR') db()->prepare("UPDATE nonconformities SET due_on=? WHERE id=?")->execute([$new, (int)$ext['entity_id']]);
    elseif ($ext['entity'] === 'CAPA') db()->prepare("UPDATE capa SET due_on=? WHERE id=?")->execute([$new, (int)$ext['entity_id']]);
    elseif ($ext['entity'] === 'ACTION') db()->prepare("UPDATE capa_actions SET due_on=? WHERE id=?")->execute([$new, (int)$ext['entity_id']]);
    return true;
}
function ncdca_extensions($entity, $entityId) {
    return ops_all("SELECT * FROM issue_extensions WHERE entity=? AND entity_id=? ORDER BY id DESC", [strtoupper((string)$entity), (int)$entityId]) ?: [];
}

// ---------------------------------------------------------------------------
//  §57/§58 Recurrence — reuse CAPA's similar_* fields + a deterministic search
//  for possible repeats (same partner / clause / issue type). ADVISORY: it
//  suggests; a human confirms (§58 — never auto-label a repeat).
// ---------------------------------------------------------------------------
function ncdca_possible_repeats($ncr, $limit = 10) {
    if (!is_array($ncr)) return [];
    $id = (int)($ncr['id'] ?? 0); $partner = (int)($ncr['partner_id'] ?? 0);
    $clause = trim((string)($ncr['clause'] ?? '')); $type = (string)($ncr['issue_type'] ?? '');
    if (!$partner && $clause === '') return [];
    $w = ['id<>?']; $a = [$id];
    if ($partner) { $w[] = 'partner_id=?'; $a[] = $partner; }
    if ($clause !== '') { $w[] = "clause=?"; $a[] = $clause; }
    $a[] = (int)$limit;
    try {
        return ops_all("SELECT id, ref, title, clause, severity, detected_on, status FROM nonconformities
                        WHERE " . implode(' AND ', $w) . " ORDER BY detected_on DESC, id DESC LIMIT ?", $a) ?: [];
    } catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------------------
//  Issue report library (§80-85) — assembled from the Template Builder (URFE).
//  New report TYPES only; the report engine / evidence / approval / PDF reused.
// ---------------------------------------------------------------------------
const NCDCA_LIB_FIELDS = [
    ['iss_type',        'Issue type',            'select',   'lookup:issue_type',          'Issue'],
    ['iss_source',      'Source',                'text',     '',                            'Issue'],
    ['iss_class',       'Classification',        'select',   'lookup:issue_class',         'Issue'],
    ['iss_severity',    'Severity',              'select',   'Low\nMedium\nHigh\nCritical', 'Issue'],
    ['iss_requirement', 'Requirement',           'textarea', '',                            'Finding'],
    ['iss_reference',   'Reference / clause',    'text',     '',                            'Finding'],
    ['iss_expected',    'Expected condition',    'textarea', '',                            'Finding'],
    ['iss_observed',    'Observed condition',    'textarea', '',                            'Finding'],
    ['iss_gap',         'Gap',                   'textarea', '',                            'Finding'],
    ['iss_statement',   'Finding statement',     'textarea', '',                            'Finding'],
    ['iss_risk',        'Risk / impact',         'textarea', '',                            'Finding'],
    ['iss_containment', 'Immediate containment', 'textarea', '',                            'Containment'],
    ['iss_disposition', 'Disposition',           'select',   'lookup:issue_disposition',   'Disposition'],
    ['iss_correction',  'Correction',            'textarea', '',                            'Correction'],
    ['iss_rootcause',   'Root cause',            'textarea', '',                            'Root cause'],
    ['iss_rc_method',   'Analysis method',       'select',   'lookup:rc_method',           'Root cause'],
    ['iss_responsibility','Responsible party',   'select',   'lookup:issue_responsibility','Root cause'],
    ['iss_effectiveness','Effectiveness',        'select',   'lookup:effectiveness_result','Verification'],
    ['iss_closure',     'Closure summary',       'textarea', '',                            'Closure'],
    ['dep_requirement', 'Requirement',           'textarea', '',                            'Departure'],
    ['dep_planned',     'Planned condition',     'textarea', '',                            'Departure'],
    ['dep_actual',      'Actual / proposed condition','textarea','',                        'Departure'],
    ['dep_justification','Technical justification','textarea','',                           'Departure'],
    ['dep_controls',    'Compensating controls', 'textarea', '',                            'Departure'],
    ['dep_validity',    'Validity',              'text',     '',                            'Departure'],
];
const NCDCA_LIB_TABLES = [
    ['iss_evidence',   'Evidence', 'Evidence',
        "Type\nDate|date\nDescription\nSource\nReference\nVerification|select|Not verified,Verified,Rejected"],
    ['iss_actions',    'Corrective actions', 'Corrective action',
        "Action\nType|select|Correction,Corrective,Systemic/Preventive\nOwner\nDue|date\nStatus|select|Open,In Progress,Completed,Verified,Closed\nEvidence"],
];
const NCDCA_LIB_SECTIONS = [
    ['ISS_IDENT',      'Issue identification',   'FIELDS','', 'Type, source, classification & severity',
        ['iss_type','iss_source','iss_class','iss_severity'], []],
    ['ISS_FINDING',    'Finding',                'FIELDS','', 'Requirement, expected vs observed, gap & statement',
        ['iss_requirement','iss_reference','iss_expected','iss_observed','iss_gap','iss_statement','iss_risk'], []],
    ['ISS_EVIDENCE',   'Evidence',               'TABLE', '', 'Objective evidence',
        ['iss_evidence'], []],
    ['ISS_CONTAINMENT','Containment & disposition','FIELDS','', 'Immediate containment and disposition',
        ['iss_containment','iss_disposition','iss_correction'], []],
    ['ISS_ROOTCAUSE',  'Root cause',             'FIELDS','', 'Root cause, method & responsible party',
        ['iss_rootcause','iss_rc_method','iss_responsibility'], []],
    ['ISS_ACTIONS',    'Corrective actions',     'TABLE', '', 'Correction / corrective / systemic actions',
        ['iss_actions'], []],
    ['ISS_VERIFICATION','Verification & effectiveness','FIELDS','', 'Effectiveness verification',
        ['iss_effectiveness'], []],
    ['ISS_CLOSURE',    'Closure',                'FIELDS','', 'Closure summary',
        ['iss_closure'], []],
    ['DEP_DETAILS',    'Departure details',      'FIELDS','', 'Requirement, planned vs actual, justification & validity',
        ['dep_requirement','dep_planned','dep_actual','dep_justification','dep_controls','dep_validity'], []],
];
function ncdca_seed_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (NCDCA_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','',$now]); }
    foreach (NCDCA_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Issue',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 900;
    foreach (NCDCA_LIB_SECTIONS as $s) { $ord+=10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc){ $fo+=10; $insSF->execute([$s[0],$fc,$fo]); } }
}
function ncdca_assemble_report_type($code, $name, $sectionCodes, $opts = []) {
    if (!function_exists('urfe_assemble_report_type')) return 0;
    return urfe_assemble_report_type($code, $name, $sectionCodes, array_merge(['category' => 'ISSUE', 'evidence_enabled' => 1], $opts));
}
const NCDCA_SAMPLE_TYPES = [
    ['NCDCA_NCR','NCR / Nonconformity Report',
        ['DOC_CONTROL','VENDOR_INFO','ISS_IDENT','ISS_FINDING','ISS_EVIDENCE','ISS_CONTAINMENT','ISS_ROOTCAUSE','ISS_ACTIONS','ISS_VERIFICATION','ISS_CLOSURE','APPROVAL']],
    ['NCDCA_CAPA','CAPA Report',
        ['DOC_CONTROL','VENDOR_INFO','ISS_IDENT','ISS_FINDING','ISS_CONTAINMENT','ISS_ROOTCAUSE','ISS_ACTIONS','ISS_VERIFICATION','ISS_CLOSURE','APPROVAL']],
    ['NCDCA_DEVIATION','Deviation Report',
        ['DOC_CONTROL','VENDOR_INFO','ISS_IDENT','DEP_DETAILS','ISS_EVIDENCE','APPROVAL']],
    ['NCDCA_CONCESSION','Concession Report',
        ['DOC_CONTROL','VENDOR_INFO','ISS_IDENT','DEP_DETAILS','ISS_EVIDENCE','APPROVAL']],
    ['NCDCA_CLOSURE','Issue Closure Report',
        ['DOC_CONTROL','VENDOR_INFO','ISS_IDENT','ISS_ACTIONS','ISS_VERIFICATION','ISS_CLOSURE','APPROVAL']],
];
function ncdca_seed_sample_types() {
    foreach (NCDCA_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        ncdca_assemble_report_type($s[0], $s[1], $s[2], ['subcategory'=>'NCDCA','description'=>'Sample issue / NCR / CAPA report configuration (NCDCA) — editable & removable.']);
    }
}

// ---------------------------------------------------------------------------
//  Advisory AI / data-integrity QA (§75-79). DETERMINISTIC and ADVISORY — flags;
//  NEVER invents a finding/root-cause/action, changes severity/responsibility,
//  approves a departure or closes an issue. idems_qa_run item 18. Self-gates.
// ---------------------------------------------------------------------------
function ncdca_qa_checks($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $isIssue = false; foreach ($fields as $f) { if (in_array(($f['fkey'] ?? ''), ['iss_statement','iss_requirement','iss_type','dep_requirement'], true)) { $isIssue = true; break; } }
    if (!$isIssue) return [];
    $out = [];
    $val = fn($k) => trim((string)($data[$k] ?? ''));
    $has = fn($k) => $val($k) !== '';

    // (a) §76 finding-quality — a finding statement without a requirement or gap.
    if ($has('iss_statement') || $has('iss_observed')) {
        $miss = [];
        if (!$has('iss_requirement') && !$has('iss_reference')) $miss[] = 'the requirement it fails';
        if (!$has('iss_observed')) $miss[] = 'the observed condition';
        if (!$has('iss_gap') && !$has('iss_statement')) $miss[] = 'the gap / finding statement';
        if ($miss) $out[] = ['sev'=>'medium','cat'=>'issue','title'=>'The finding is missing part of its structure','loc'=>'Finding',
            'why'=>'A defensible finding names the requirement, the observed condition and the gap. Missing: ' . implode('; ', $miss) . '.',
            'fix'=>'Complete the finding structure. The system will not fill it in.'];
    }
    // (b) §16/§17 an NC classification with nothing to put right recorded.
    $cls = strtoupper($val('iss_class'));
    if (in_array($cls, ['MINOR_NC','MAJOR_NC','CRITICAL_NC'], true) && !$has('iss_correction') && !$has('iss_containment'))
        $out[] = ['sev'=>'low','cat'=>'issue','title'=>'A nonconformity with no correction or containment recorded','loc'=>'Containment & disposition',
            'why'=>'This is classified as a nonconformity but neither a correction nor a containment is recorded.',
            'fix'=>'Record the correction / containment, or reconsider the classification.'];
    // (c) §79 closure QA — a closure summary with no effectiveness on an NC.
    if ($has('iss_closure') && in_array($cls, ['MAJOR_NC','CRITICAL_NC'], true) && !$has('iss_effectiveness'))
        $out[] = ['sev'=>'medium','cat'=>'issue','title'=>'Closing a major nonconformity without recording effectiveness','loc'=>'Verification & effectiveness',
            'why'=>'A major / critical nonconformity is being closed but effectiveness of the corrective action is not recorded.',
            'fix'=>'Record the effectiveness verification before closing, or note why it is not required.'];
    // (d) §77 evidence-vs-finding conflict — evidence table empty on a stated finding.
    foreach ($fields as $f) {
        if (($f['fkey'] ?? '') !== 'iss_evidence' || ($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data['iss_evidence'] ?? null;
        if ($has('iss_statement') && (!is_array($rows) || !$rows))
            $out[] = ['sev'=>'low','cat'=>'issue','title'=>'A finding is stated but no evidence is listed','loc'=>'Evidence',
                'why'=>'The finding statement is filled in but the evidence table is empty. Objective evidence is what makes a finding defensible.',
                'fix'=>'List the objective evidence, or note why none applies.'];
        break;
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Read helpers (issue register spanning types — reuses nonconformities).
// ---------------------------------------------------------------------------
function ncdca_issue_class_label($k) { $m = ncdca_classes(); return $m[$k] ?? ($k ?: '—'); }
function ncdca_issue_type_label($k)  { $m = ncdca_issue_types(); return $m[$k] ?? ($k ?: 'NCR'); }
