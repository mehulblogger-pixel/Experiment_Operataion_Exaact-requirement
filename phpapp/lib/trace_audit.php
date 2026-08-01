<?php
// ============================================================================
//  Audit & compliance thread — the inspection/audit pack, proven end to end
//
//  The twin of the sales traceability thread. It builds one compliance chain —
//  internal audit → finding → corrective action (CAPA) with an owner and a due
//  date → verify → close — plus an NCR and a complaint that each raise their own
//  corrective action, and a management review. Then it FIRES the accreditation
//  gates on purpose — a lapsed certificate, an out-of-calibration instrument, an
//  open impartiality threat, and the inspection pack's own assign-hook — and
//  confirms each one actually blocks. Finally it reads the database back and
//  shows, link by link and gate by gate, that the pack holds together.
//
//  Everything it writes is anchored on a dedicated auditor's inspector record
//  (code GT-AUD) and its own reference prefixes, and the ids it creates are
//  remembered so trace_audit_remove() can take the whole chain out again.
// ============================================================================

const TRACE_AUD_INSP_EMP = 'GT-AUD';
const TRACE_AUD_EQ_CODE  = 'GTAUD-EQ';

function trace_audit_seeded() { return setting_get('trace_audit_ids', '') !== ''; }

function trace_audit_seed($force = false) {
    if (trace_audit_seeded() && !$force) return ['skipped' => true];
    if (trace_audit_seeded() && $force) trace_audit_remove();
    if (function_exists('trace_become_admin')) trace_become_admin();

    $pdo = db();
    $today = date('Y-m-d'); $now = date('c');
    $past  = date('Y-m-d', strtotime('-30 days'));
    $steps = []; $ids = [];
    $step = function ($title, $ref, $detail) use (&$steps) { $steps[] = ['title' => $title, 'ref' => $ref, 'detail' => $detail]; };

    // A customer to hang the compliance records on — reuse the sales thread's,
    // else any active client, else a light stand-in.
    $partnerId = (int) (ops_val("SELECT id FROM business_partners WHERE code=?", ['GT-CLIENT'])
                        ?: ops_val("SELECT id FROM business_partners WHERE is_client=1 ORDER BY id LIMIT 1"));
    if (!$partnerId) {
        $pdo->prepare("INSERT INTO business_partners(code,legal_name,display_name,is_client,status,state,created_at)
                       VALUES('GT-AUD-CL','Golden Thread Audit Client','GT Audit Client',1,'ACTIVE','Gujarat',?)")->execute([$now]);
        $partnerId = (int)$pdo->lastInsertId();
    }
    $ids['partner'] = $partnerId;
    $office = ops_one("SELECT id FROM offices ORDER BY id LIMIT 1");
    $officeId = (int)($office['id'] ?? 0) ?: null;

    // ---- An auditor/engineer to test the people-gates against ----------------
    $pdo->prepare("INSERT INTO inspectors(name,first_name,last_name,emp_code,sbu,status,designation,staff_kind,created_at)
                   VALUES('GT Audit Engineer','GT','Auditor',?, 'IND','ACTIVE','Sr. Inspector','ASSET',?)")
        ->execute([TRACE_AUD_INSP_EMP, $now]);
    $inspId = (int)$pdo->lastInsertId(); $ids['inspector'] = $inspId;
    // A required certificate that has already lapsed → the competence gate must bite.
    $pdo->prepare("INSERT INTO inspector_certs(inspector_id,name,number,issued_date,valid_to,status,is_mandatory,created_at)
                   VALUES(?,?,?,?,?, 'ACTIVE', 1, ?)")
        ->execute([$inspId, 'ASNT NDT Level II', 'CERT-GTAUD-01', date('Y-m-d', strtotime('-2 years')), $past, $now]);
    // An open impartiality threat on this engineer for this customer → the impartiality gate must bite.
    try {
        $pdo->prepare("INSERT INTO impartiality_threats(threat_kind,person_kind,person_id,partner_id,office_id,description,raised_by,raised_on,status,created_at)
                       VALUES('FINANCIAL','INSPECTOR',?,?,?, 'Engineer previously employed by this customer.', 'trace', ?, 'OPEN', ?)")
            ->execute([$inspId, $partnerId, $officeId, $today, $now]);
    } catch (Throwable $e) {}
    $step('An engineer set up to test the gates', TRACE_AUD_INSP_EMP, 'With a lapsed required certificate and an open impartiality threat — both should block allocation.');

    // ---- An instrument out of calibration → the equipment gate must bite -----
    try {
        $pdo->prepare("INSERT INTO equipment(code,name,serial_no,office_id,inspector_id,status,cal_interval_months,owned_by,created_by,created_at)
                       VALUES(?, 'Ultrasonic Flaw Detector', 'GTAUD-9001', ?,?, 'ACTIVE', 12, 'OWN', 'trace', ?)")
            ->execute([TRACE_AUD_EQ_CODE, $officeId, $inspId, $now]);
        $eqId = (int)$pdo->lastInsertId(); $ids['equipment'] = $eqId;
        $pdo->prepare("INSERT INTO equipment_calibrations(equipment_id,cert_no,cal_date,valid_to,cal_body,result,uploaded_by,uploaded_at)
                       VALUES(?, 'CAL-GTAUD-01', ?, ?, 'NABL Lab', 'PASS', 'trace', ?)")
            ->execute([$eqId, date('Y-m-d', strtotime('-18 months')), $past, $now]);
        $step('An instrument out of calibration', TRACE_AUD_EQ_CODE, 'Its calibration lapsed — naming it on a report should stop the report issuing.');
    } catch (Throwable $e) { $ids['equipment'] = 0; }

    // ---- 1. Internal audit → finding → corrective action --------------------
    $iaRef = function_exists('audit_ref_next') ? audit_ref_next() : ('IA-' . date('Y') . '-T01');
    $pdo->prepare("INSERT INTO internal_audits(ref,planned_on,carried_out_on,clauses,scope,office_id,area_owner,auditor,method,summary,status,reported_on,created_by,created_at)
                   VALUES(?,?,?, '7.4, 7.5', 'Report review & complaint handling', ?, 'Operation Manager', 'Quality Manager', 'ON_SITE', 'One minor nonconformity raised on report turnaround.', 'REPORTED', ?, 'trace', ?)")
        ->execute([$iaRef, $today, $today, $officeId, $today, $now]);
    $auditId = (int)$pdo->lastInsertId(); $ids['audit'] = $auditId;
    $pdo->prepare("INSERT INTO audit_findings(audit_id,clause,kind,detail,evidence,raised_by,raised_on)
                   VALUES(?, '7.5', 'NC_MINOR', 'Two reports issued beyond the agreed turnaround.', 'IRN log for the month.', 'trace', ?)")
        ->execute([$auditId, $today]);
    $findingId = (int)$pdo->lastInsertId(); $ids['finding'] = $findingId;
    $capaA = function_exists('capa_create') ? capa_create([
        'source' => 'AUDIT', 'audit_finding_id' => $findingId, 'title' => 'Reduce report turnaround',
        'description' => 'Reports issued late against the agreed TAT on two jobs.', 'clause' => '7.5',
        'severity' => 'MINOR', 'office_id' => $officeId, 'owner' => 'Operation Manager', 'due_on' => date('Y-m-d', strtotime('+14 days')),
        'immediate_action' => 'Chased both reports; issued.',
    ]) : 0;
    if ($capaA) {
        $pdo->prepare("UPDATE audit_findings SET capa_ref=(SELECT ref FROM capa WHERE id=?), capa_id=? WHERE id=?")->execute([$capaA, $capaA, $findingId]);
        $ids['capa_audit'] = $capaA;
        if (function_exists('capa_action_add')) capa_action_add($capaA, [
            'description' => 'Add a mid-week TAT check to the coordinator’s board', 'owner' => 'Coordinator', 'due_on' => date('Y-m-d', strtotime('+10 days')),
        ]);
        // Verify and close the loop.
        try {
            $act = ops_one("SELECT id FROM capa_actions WHERE capa_id=? ORDER BY id LIMIT 1", [$capaA]);
            if ($act && function_exists('capa_action_done')) capa_action_done((int)$act['id'], 'Board updated.', $today);
            $pdo->prepare("UPDATE capa SET root_cause='No mid-week checkpoint on turnaround.', rc_method='5-WHY', similar_checked=1,
                           completed_on=?, verified_on=?, effective='YES', status='CLOSED', closed_on=?, closed_by='trace' WHERE id=?")
                ->execute([$today, $today, $today, $capaA]);
        } catch (Throwable $e) {}
    }
    $step('Internal audit → finding → corrective action', $iaRef, 'A minor nonconformity became a corrective action with an owner and a due date, verified and closed.');

    // ---- 2. Nonconformity → its own corrective action -----------------------
    if (function_exists('ncr_create')) {
        $ncrId = (int) ncr_create([
            'source' => 'INTERNAL', 'partner_id' => $partnerId, 'office_id' => $officeId, 'sbu' => 'IND',
            'title' => 'Wrong acceptance standard cited', 'description' => 'A report cited the superseded revision of the standard.',
            'clause' => '7.5', 'severity' => 'MAJOR', 'detected_on' => $today, 'detected_by' => 'trace',
            'owner' => 'Branch Application Manager', 'due_on' => date('Y-m-d', strtotime('+21 days')),
            'containment' => 'Report recalled and reissued.',
        ]);
        if ($ncrId) {
            $ids['ncr'] = $ncrId;
            $capaN = function_exists('ncr_to_capa') ? (int) ncr_to_capa($ncrId) : 0;
            if ($capaN) $ids['capa_ncr'] = $capaN;
            $step('Nonconformity raised its corrective action', ops_val("SELECT ref FROM nonconformities WHERE id=?", [$ncrId]) ?: ('NCR#' . $ncrId),
                  'The NCR and the corrective action point at each other, both ways.');
        }
    }

    // ---- 3. Complaint → its own corrective action ---------------------------
    if (function_exists('cmp_create')) {
        $cmpId = (int) cmp_create([
            'kind' => 'COMPLAINT', 'source' => 'CLIENT', 'channel' => 'EMAIL', 'partner_id' => $partnerId,
            'subject' => 'Report received two days late', 'description' => 'Customer says the report missed the agreed date.',
            'received_on' => $today, 'received_by' => 'trace', 'office_id' => $officeId,
        ]);
        if ($cmpId) {
            $ids['complaint'] = $cmpId;
            $capaC = function_exists('capa_from_complaint') ? (int) capa_from_complaint($cmpId) : 0;
            if ($capaC) $ids['capa_complaint'] = $capaC;
            $step('Complaint raised its corrective action', ops_val("SELECT ref FROM complaints WHERE id=?", [$cmpId]) ?: ('CMP#' . $cmpId),
                  'The customer complaint is linked to the corrective action that answers it.');
        }
    }

    // ---- 4. Management review ------------------------------------------------
    try {
        $mrRef = function_exists('review_ref_next') ? review_ref_next() : ('MR-' . date('Y') . '-T1');
        $pdo->prepare("INSERT INTO mgmt_reviews(ref,held_on,period_from,period_to,chair,attendees,status,created_by,created_at)
                       VALUES(?,?,?,?, 'Business Director', 'Quality Manager, Operation Manager', 'DRAFT', 'trace', ?)")
            ->execute([$mrRef, $today, date('Y-m-d', strtotime('-3 months')), $today, $now]);
        $reviewId = (int)$pdo->lastInsertId(); $ids['review'] = $reviewId;
        if (function_exists('mr_refresh_measures')) mr_refresh_measures($reviewId);
        $pdo->prepare("INSERT INTO mr_actions(review_id,kind,decision,owner,due_on,status,created_at)
                       VALUES(?, 'IMPROVEMENT', 'Add a turnaround KPI to the monthly pack.', 'Operation Manager', ?, 'OPEN', ?)")
            ->execute([$reviewId, date('Y-m-d', strtotime('+30 days')), $now]);
        $step('Management review recorded', $mrRef, 'Its inputs were measured and a decision with an owner was logged.');
    } catch (Throwable $e) {}

    setting_set('trace_audit_ids', json_encode($ids));
    return ['ok' => true, 'ids' => $ids, 'steps' => $steps, 'checks' => trace_audit_checks($ids)];
}

// ----------------------------------------------------------------------------
//  The checks — links recorded, and gates that actually fire.
// ----------------------------------------------------------------------------
function trace_audit_checks(array $ids) {
    // The inspection pack registers its hooks in packs_boot() (run once). The web
    // app fires it on every request; a CLI check must call it, or the assign-hook
    // has no handlers registered and would look "open" when it is not.
    if (function_exists('packs_boot')) packs_boot();
    $c = []; $v = fn($sql, $a = []) => ops_val($sql, $a);
    $add = function ($area, $proves, $expected, $actual) use (&$c) {
        $c[] = ['area' => $area, 'proves' => $proves, 'expected' => (string)$expected,
                'actual' => (string)$actual, 'ok' => (string)$expected === (string)$actual];
    };
    $inspId = $ids['inspector'] ?? 0; $partnerId = $ids['partner'] ?? 0; $eqId = $ids['equipment'] ?? 0;

    // ---- Links recorded ----
    if (!empty($ids['finding'])) {
        $add('Audit → CAPA', 'The finding is linked to a corrective action', '1',
             (int)($v("SELECT CASE WHEN COALESCE(capa_id,0)>0 THEN 1 ELSE 0 END FROM audit_findings WHERE id=?", [$ids['finding']])));
        if (!empty($ids['capa_audit'])) {
            $add('Audit → CAPA', 'The corrective action knows its finding', $ids['finding'], $v("SELECT audit_finding_id FROM capa WHERE id=?", [$ids['capa_audit']]));
            $add('Audit → CAPA', 'The corrective action has an action with an owner', '1',
                 (int)($v("SELECT CASE WHEN COUNT(*)>0 THEN 1 ELSE 0 END FROM capa_actions WHERE capa_id=? AND COALESCE(owner,'')<>''", [$ids['capa_audit']])));
            $add('Audit → CAPA', 'The corrective action is verified & closed', 'CLOSED', $v("SELECT status FROM capa WHERE id=?", [$ids['capa_audit']]));
        }
    }
    if (!empty($ids['ncr']) && !empty($ids['capa_ncr'])) {
        $add('NCR → CAPA', 'The nonconformity is linked to a corrective action', $ids['capa_ncr'], $v("SELECT capa_id FROM nonconformities WHERE id=?", [$ids['ncr']]));
        $add('NCR → CAPA', 'The corrective action knows its nonconformity', $ids['ncr'], $v("SELECT ncr_id FROM capa WHERE id=?", [$ids['capa_ncr']]));
    }
    if (!empty($ids['complaint']) && !empty($ids['capa_complaint'])) {
        $add('Complaint → CAPA', 'The complaint is linked to a corrective action', '1',
             (int)($v("SELECT CASE WHEN COALESCE(capa_ref,'')<>'' THEN 1 ELSE 0 END FROM complaints WHERE id=?", [$ids['complaint']])));
    }
    if (!empty($ids['review'])) {
        $add('Management review', 'The review has its inputs measured', '1',
             (int)($v("SELECT CASE WHEN COUNT(*)>0 THEN 1 ELSE 0 END FROM mr_inputs WHERE review_id=?", [$ids['review']])));
        $add('Management review', 'The review has a decision with an owner', '1',
             (int)($v("SELECT CASE WHEN COUNT(*)>0 THEN 1 ELSE 0 END FROM mr_actions WHERE review_id=? AND COALESCE(owner,'')<>''", [$ids['review']])));
    }

    // ---- Gates that must fire (protections working) ----
    $today = date('Y-m-d');
    if ($inspId && function_exists('competence_block'))
        $add('Gate', 'A lapsed required certificate blocks allocation', 'blocked', competence_block($inspId, $today) !== '' ? 'blocked' : 'ALLOWED');
    if ($eqId && function_exists('equipment_block'))
        $add('Gate', 'An out-of-calibration instrument is refused', 'blocked', equipment_block($eqId, $today) !== '' ? 'blocked' : 'ALLOWED');
    if ($inspId && function_exists('imp_block'))
        $add('Gate', 'An open impartiality threat blocks allocation', 'blocked', imp_block($inspId, [$partnerId], $today) !== '' ? 'blocked' : 'ALLOWED');
    if ($inspId && function_exists('pack_block')) {
        $blk = pack_block('work.assign', ['person_id' => $inspId, 'on_date' => $today, 'work_type' => 'INSPECTION',
                                          'activity_id' => null, 'partner_id' => $partnerId, 'vendor_id' => null, 'site_id' => null]);
        $add('Gate', 'The inspection pack’s assign-hook blocks the wrong engineer', 'blocked', $blk !== '' ? 'blocked' : 'ALLOWED');
    }
    return $c;
}

// ----------------------------------------------------------------------------
function trace_audit_remove() {
    $pdo = db();
    $n = 0;
    $del = function ($sql, $args = []) use ($pdo, &$n) {
        try { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {}
    };
    $ids = json_decode((string) setting_get('trace_audit_ids', ''), true) ?: [];
    foreach ([
        ['capa_events', 'capa_id', ['capa_audit','capa_ncr','capa_complaint']],
        ['capa_actions', 'capa_id', ['capa_audit','capa_ncr','capa_complaint']],
    ] as [$tbl, $col, $keys]) {
        foreach ($keys as $k) if (!empty($ids[$k])) $del("DELETE FROM $tbl WHERE $col=?", [$ids[$k]]);
    }
    foreach (['capa_audit','capa_ncr','capa_complaint'] as $k) if (!empty($ids[$k])) $del("DELETE FROM capa WHERE id=?", [$ids[$k]]);
    if (!empty($ids['ncr'])) { $del("DELETE FROM ncr_events WHERE ncr_id=?", [$ids['ncr']]); $del("DELETE FROM nonconformities WHERE id=?", [$ids['ncr']]); }
    if (!empty($ids['complaint'])) { $del("DELETE FROM complaint_events WHERE complaint_id=?", [$ids['complaint']]); $del("DELETE FROM complaints WHERE id=?", [$ids['complaint']]); }
    if (!empty($ids['audit'])) { $del("DELETE FROM audit_findings WHERE audit_id=?", [$ids['audit']]); $del("DELETE FROM internal_audits WHERE id=?", [$ids['audit']]); }
    if (!empty($ids['review'])) { $del("DELETE FROM mr_inputs WHERE review_id=?", [$ids['review']]); $del("DELETE FROM mr_actions WHERE review_id=?", [$ids['review']]); $del("DELETE FROM mgmt_reviews WHERE id=?", [$ids['review']]); }
    if (!empty($ids['inspector'])) {
        $del("DELETE FROM inspector_certs WHERE inspector_id=?", [$ids['inspector']]);
        $del("DELETE FROM impartiality_threats WHERE person_id=? AND raised_by='trace'", [$ids['inspector']]);
        $del("DELETE FROM inspectors WHERE id=?", [$ids['inspector']]);
    }
    if (!empty($ids['equipment'])) { $del("DELETE FROM equipment_calibrations WHERE equipment_id=?", [$ids['equipment']]); $del("DELETE FROM equipment WHERE id=?", [$ids['equipment']]); }
    $del("DELETE FROM business_partners WHERE code='GT-AUD-CL'");
    setting_set('trace_audit_ids', '');
    return ['deleted' => $n];
}
