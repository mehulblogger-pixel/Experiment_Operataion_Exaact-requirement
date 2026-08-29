<?php
// ============================================================================
//  CONNECT — Engagements / Bookings  (slice K20 / freelancer completion, additive)
//
//  When a requirement is AWARDED to a professional (or inspector / agency-bench
//  person), the day is BOOKED — but a booking is not one shape. This models the
//  real ways a technical person is engaged:
//
//    MAN_DAYS    — a number of man-days (one-off / short assignment)
//    MAN_MONTHS  — a number of man-months
//    DEPUTATION  — a long-term deputation (extended posting, start→end)
//    CONTINUOUS  — continuous / ongoing (no fixed end, billed monthly)
//    FREQUENCY   — regular frequency (e.g. "2 days a week", "monthly visit")
//
//  It sits on top of the existing award (cx_requirements.awarded_application_id)
//  and the P4 billable bridge — it captures the *basis* of the booking so the
//  professional sees their real commitment and the desk bills it correctly.
//
//  ADDITIVE CONTRACT: one new table (cx_engagements), cx_* namespaced; reuses the
//  award, the professional/inspector/bench subject, and the requirement. No new
//  named permission (marketplace desk); no existing route/view/status changed.
// ============================================================================

function connect_engage_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_engagements (
        id $pk,
        requirement_id INT DEFAULT 0, application_id INT DEFAULT 0,
        subject_kind VARCHAR(20) DEFAULT 'professional',  -- professional | inspector | bench
        subject_id   INT DEFAULT 0, subject_name VARCHAR(200) DEFAULT '',
        poster_party_id INT DEFAULT 0, poster_name VARCHAR(200) DEFAULT '',
        basis VARCHAR(20) DEFAULT 'MAN_DAYS',             -- see connect_engage_bases()
        rate REAL DEFAULT 0, rate_unit VARCHAR(10) DEFAULT 'day',  -- day | month | visit
        quantity REAL DEFAULT 0,                          -- days or months, per basis
        frequency_note VARCHAR(120) DEFAULT '',           -- e.g. '2 days / week'
        start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '',
        status VARCHAR(12) DEFAULT 'BOOKED',              -- BOOKED | ACTIVE | COMPLETED | CANCELLED
        notes VARCHAR(400) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_eng_subject ON cx_engagements (subject_kind, subject_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_eng_req ON cx_engagements (requirement_id)"); } catch (Throwable $e) {}
}

/** The engagement bases, with what each one needs. */
function connect_engage_bases() {
    return [
        'MAN_DAYS'   => ['label' => 'Man-days',              'unit' => 'day',   'needs_qty' => true,  'qty_label' => 'Days',   'needs_end' => false, 'needs_freq' => false],
        'MAN_MONTHS' => ['label' => 'Man-months',            'unit' => 'month', 'needs_qty' => true,  'qty_label' => 'Months', 'needs_end' => true,  'needs_freq' => false],
        'DEPUTATION' => ['label' => 'Long-term deputation',  'unit' => 'month', 'needs_qty' => false, 'qty_label' => 'Months', 'needs_end' => true,  'needs_freq' => false],
        'CONTINUOUS' => ['label' => 'Continuous (ongoing)',  'unit' => 'month', 'needs_qty' => false, 'qty_label' => '',       'needs_end' => false, 'needs_freq' => false],
        'FREQUENCY'  => ['label' => 'Regular frequency',     'unit' => 'visit', 'needs_qty' => false, 'qty_label' => '',       'needs_end' => false, 'needs_freq' => true],
    ];
}
function connect_engage_basis_label($basis) { return connect_engage_bases()[strtoupper((string)$basis)]['label'] ?? (string)$basis; }
function connect_engage_statuses() { return ['BOOKED', 'ACTIVE', 'COMPLETED', 'CANCELLED']; }

/** The subject (who is booked) for a requirement's awarded application. */
function connect_engage_subject_for_award($req) {
    $aid = (int)($req['awarded_application_id'] ?? 0);
    if ($aid <= 0) return null;
    try { $a = ops_one("SELECT * FROM cx_applications WHERE id=?", [$aid]); } catch (Throwable $e) { $a = null; }
    if (!$a) return null;
    if ((int)($a['applicant_professional_id'] ?? 0) > 0)
        return ['application_id' => $aid, 'kind' => 'professional', 'id' => (int)$a['applicant_professional_id'], 'name' => (string)($a['applicant_name'] ?? '')];
    if ((int)($a['inspector_id'] ?? 0) > 0)
        return ['application_id' => $aid, 'kind' => 'inspector', 'id' => (int)$a['inspector_id'], 'name' => (string)($a['applicant_name'] ?? '')];
    if ((int)($a['applicant_party_id'] ?? 0) > 0)
        return ['application_id' => $aid, 'kind' => 'bench', 'id' => (int)$a['applicant_party_id'], 'name' => (string)($a['applicant_name'] ?? '')];
    return ['application_id' => $aid, 'kind' => 'professional', 'id' => 0, 'name' => (string)($a['applicant_name'] ?? '')];
}

/** The engagement for a requirement (one per award), or null. */
function connect_engage_for_requirement($requirementId) {
    connect_engage_migrate();
    try { return ops_one("SELECT * FROM cx_engagements WHERE requirement_id=? ORDER BY id DESC", [(int)$requirementId]) ?: null; }
    catch (Throwable $e) { return null; }
}

/**
 * Record (or update) the booking basis for a requirement that has been AWARDED.
 * Derives the subject from the awarded application. Returns [ok, msg, id].
 */
function connect_engage_save_for_requirement($requirementId, array $in) {
    connect_engage_migrate();
    $req = function_exists('cx_requirement_get') ? cx_requirement_get($requirementId) : null;
    if (!$req) return [false, 'Requirement not found.', 0];
    if (strtoupper((string)$req['status']) !== 'AWARDED')
        return [false, 'Record a booking only after the requirement is awarded.', 0];
    $subj = connect_engage_subject_for_award($req);
    if (!$subj) return [false, 'No awarded person to book.', 0];

    $bases = connect_engage_bases();
    $basis = strtoupper((string)($in['basis'] ?? 'MAN_DAYS'));
    if (!isset($bases[$basis])) return [false, 'Pick a valid engagement basis.', 0];
    $cfg = $bases[$basis];

    $rate = (float)($in['rate'] ?? 0);
    $unit = in_array(($in['rate_unit'] ?? ''), ['day', 'month', 'visit'], true) ? $in['rate_unit'] : $cfg['unit'];
    $qty  = !empty($cfg['needs_qty']) ? max(0, (float)($in['quantity'] ?? 0)) : 0;
    $freq = !empty($cfg['needs_freq']) ? trim((string)($in['frequency_note'] ?? '')) : '';
    $start = trim((string)($in['start_date'] ?? ''));
    $end   = !empty($cfg['needs_end']) ? trim((string)($in['end_date'] ?? '')) : trim((string)($in['end_date'] ?? ''));
    $status = strtoupper((string)($in['status'] ?? 'BOOKED'));
    if (!in_array($status, connect_engage_statuses(), true)) $status = 'BOOKED';

    if (!empty($cfg['needs_qty']) && $qty <= 0) return [false, $cfg['qty_label'] . ' is required for ' . $cfg['label'] . '.', 0];
    if (!empty($cfg['needs_freq']) && $freq === '') return [false, 'Describe the frequency (e.g. "2 days a week").', 0];

    $existing = connect_engage_for_requirement($requirementId);
    if ($existing) {
        db()->prepare("UPDATE cx_engagements SET basis=?, rate=?, rate_unit=?, quantity=?, frequency_note=?, start_date=?, end_date=?, status=?, notes=?, updated_at=? WHERE id=?")
            ->execute([$basis, $rate, $unit, $qty, $freq, $start, $end, $status, trim((string)($in['notes'] ?? '')), date('c'), (int)$existing['id']]);
        return [true, 'Booking updated.', (int)$existing['id']];
    }
    db()->prepare("INSERT INTO cx_engagements
        (requirement_id,application_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,basis,rate,rate_unit,quantity,frequency_note,start_date,end_date,status,notes,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$requirementId, (int)$subj['application_id'], $subj['kind'], (int)$subj['id'], $subj['name'],
                   (int)($req['poster_party_id'] ?? 0), (string)($req['poster_name'] ?? ''), $basis, $rate, $unit, $qty, $freq,
                   $start, $end, $status, trim((string)($in['notes'] ?? '')), date('c'), date('c')]);
    return [true, 'Booking recorded.', (int)db()->lastInsertId()];
}

/** Move an engagement to a new lifecycle status. */
function connect_engage_set_status($id, $status) {
    connect_engage_migrate();
    $status = strtoupper((string)$status);
    if (!in_array($status, connect_engage_statuses(), true)) return [false, 'Invalid status.'];
    $e = ops_one("SELECT * FROM cx_engagements WHERE id=?", [(int)$id]);
    if (!$e) return [false, 'Engagement not found.'];
    db()->prepare("UPDATE cx_engagements SET status=?, updated_at=? WHERE id=?")->execute([$status, date('c'), (int)$id]);
    return [true, 'Engagement ' . strtolower($status) . '.'];
}

/** A professional's own engagements/bookings, newest first. */
function connect_engage_for_professional($proId) {
    connect_engage_migrate();
    try {
        $st = db()->prepare("SELECT e.*, r.ref_code, r.title AS req_title, r.location
                             FROM cx_engagements e LEFT JOIN cx_requirements r ON r.id=e.requirement_id
                             WHERE e.subject_kind='professional' AND e.subject_id=? ORDER BY e.id DESC");
        $st->execute([(int)$proId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** Booking summary for a professional (dashboard tile). */
function connect_engage_summary_pro($proId) {
    $out = ['booked' => 0, 'active' => 0, 'completed' => 0, 'total' => 0];
    foreach (connect_engage_for_professional($proId) as $e) {
        $s = strtoupper((string)$e['status']);
        if ($s === 'CANCELLED') continue;
        $out['total']++;
        if ($s === 'BOOKED') $out['booked']++;
        elseif ($s === 'ACTIVE') $out['active']++;
        elseif ($s === 'COMPLETED') $out['completed']++;
    }
    return $out;
}

/** A plain-language description of the commitment + its value. */
function connect_engage_describe($e) {
    $basis = strtoupper((string)($e['basis'] ?? ''));
    $rate = (float)($e['rate'] ?? 0); $unit = (string)($e['rate_unit'] ?? 'day');
    $qty = (float)($e['quantity'] ?? 0); $freq = (string)($e['frequency_note'] ?? '');
    $rateStr = $rate > 0 ? '₹' . number_format((int)$rate) . '/' . $unit : '';
    switch ($basis) {
        case 'MAN_DAYS':   $commit = rtrim(rtrim((string)$qty, '0'), '.') . ' man-days'; break;
        case 'MAN_MONTHS': $commit = rtrim(rtrim((string)$qty, '0'), '.') . ' man-months'; break;
        case 'DEPUTATION': $commit = 'Long-term deputation'; break;
        case 'CONTINUOUS': $commit = 'Continuous — ongoing'; break;
        case 'FREQUENCY':  $commit = $freq !== '' ? $freq : 'Regular frequency'; break;
        default:           $commit = connect_engage_basis_label($basis);
    }
    // Deterministic total only where quantity × rate is meaningful.
    $total = null;
    if (in_array($basis, ['MAN_DAYS', 'MAN_MONTHS'], true) && $qty > 0 && $rate > 0) $total = (int)round($qty * $rate);
    return ['commitment' => $commit, 'rate' => $rateStr, 'total' => $total];
}
