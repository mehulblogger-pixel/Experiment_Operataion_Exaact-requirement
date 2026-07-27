<?php
// ============================================================================
//  Competence — is this engineer allowed to do this work, on this date?
//
//  ISO/IEC 17020 §6.1 requires that inspections are carried out only by people
//  the body has judged competent and has authorised. The app already held
//  certificates and e-mailed a reminder 30 days before one expired — but
//  nothing stopped a coordinator deputing somebody whose ticket had already
//  lapsed. A reminder is not a control.
//
//  The judgement call this file makes, and why:
//
//    Not every certificate an engineer holds is needed for every job. Blocking
//    on ANY lapsed certificate would stop a welding inspection because somebody's
//    first-aid card ran out, and a coordinator would rightly stop trusting the
//    system. So a certificate is only a gate when it is marked REQUIRED. That
//    is a deliberate act by whoever maintains the engineer's record.
//
//    And it can be overridden — by a manager, with a reason, recorded on the
//    deputation. Refusing outright would push people to edit the expiry date to
//    get past the gate, which destroys the very record an assessor comes to
//    read. A logged override is honest; a back-dated certificate is not.
//
//  Phase 3.2 extends this file with the authorisation matrix proper — which
//  inspection types and methods each person may sign for, and the witnessed
//  inspection record. The gate below is the part that could not wait.
// ============================================================================

function competence_migrate() {
    static $done = false; if ($done) return; $done = true;
    // Only a certificate marked required can stop an allocation.
    ensure_column('inspector_certs', 'is_mandatory', 'INT DEFAULT 0');
    // Who let a lapsed one through, and why. Held on the deputation because
    // that is the record an assessor will be looking at.
    ensure_column('jobs', 'cert_override_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'cert_override_by', "VARCHAR(150) DEFAULT ''");
}

// Certificates that are required and had already lapsed on $onDate.
// $onDate is the date the work happens — not today. Deputing somebody for next
// month against a certificate that expires next week has to fail now, not in
// three weeks when nobody is looking.
function competence_lapsed($inspectorId, $onDate = null) {
    $inspectorId = (int)$inspectorId;
    if (!$inspectorId) return [];
    $onDate = $onDate ?: date('Y-m-d');
    try {
        $rows = ops_all(
            "SELECT name, number, valid_to FROM inspector_certs
             WHERE inspector_id=? AND COALESCE(is_mandatory,0)=1 AND valid_to <> '' AND valid_to < ?
             ORDER BY valid_to", [$inspectorId, $onDate]);
    } catch (Throwable $e) { return []; }   // pre-migration
    return $rows;
}

// The sentence shown to whoever is being stopped, or '' when nothing is.
function competence_block($inspectorId, $onDate = null) {
    $bad = competence_lapsed($inspectorId, $onDate);
    if (!$bad) return '';
    $who = ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$inspectorId]) ?: 'This ' . Tl('engineer');
    $bits = [];
    foreach ($bad as $c)
        $bits[] = $c['name'] . ($c['number'] ? ' (' . $c['number'] . ')' : '') . ' — expired ' . fdate($c['valid_to']);
    return $who . ' cannot be put on work dated ' . fdate($onDate)
         . ': a required certificate had lapsed by then — ' . implode('; ', $bits) . '.';
}

// The earliest date the deputation actually covers, because that is the date
// the certificate has to be valid on. Falls back sensibly on a half-filled form.
function competence_job_date($b, $call = null) {
    foreach (['inspection_start_date', 'scheduled_date', 'inspection_end_date'] as $k)
        if (!empty($b[$k])) return substr((string)$b[$k], 0, 10);
    $dates = call_dates_parse((string)($b['inspection_dates'] ?? ''));
    if ($dates) return $dates[0];
    if ($call) foreach (['inspection_required_date', 'call_received_date'] as $k)
        if (!empty($call[$k])) return substr((string)$call[$k], 0, 10);
    return date('Y-m-d');
}

// Only a manager may let a lapsed certificate through, and only with a reason.
function competence_can_override() {
    return can('jobs.edit') && (is_admin_level() || is_master());
}
