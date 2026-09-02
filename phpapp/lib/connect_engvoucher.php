<?php
// ============================================================================
//  CONNECT — Engagement Vouchers  (marketplace + on-roll, inclusive/exclusive)
//
//  Once a person is booked (cx_engagements), they claim their days and — when
//  the rate is quoted EXCLUSIVE of expenses — their reimbursables. ONE voucher
//  model covers every case, because the subject of an engagement is already
//  one of professional | inspector | bench:
//
//    WHO        — a marketplace freelancer, an inspector on a company/agency
//                 roll, or an agency-bench person (engagement.subject_kind).
//    BASIS      — man-days · man-months · deputation · continuous · frequency
//                 (engagement.basis; the rate_unit is day | month | visit).
//    RATE MODEL — INCLUSIVE  → the rate covers fee + travel/hotel/conveyance/
//                              allowances; the voucher claims NO expense head.
//                 EXCLUSIVE  → the rate is the fee; travel, hotel/lodging, local
//                              conveyance and allowances are claimed against
//                              receipts on the voucher.
//    CADENCE    — PER_DAY (same client, day after day) or PER_DEPLOYMENT
//                 (one claim per deployment / per month).
//
//  A voucher = header (cx_engagement_vouchers) + day/period lines
//  (cx_engagement_voucher_lines). fee = Σ(units × rate); reimbursable = Σ(heads)
//  ONLY when the engagement is EXCLUSIVE; grand = fee + reimbursable.
//
//  ADDITIVE: two new cx_* tables; no existing route/status changed. The status
//  lifecycle is a NEW object lifecycle (documented in 03-object-lifecycles.md).
// ============================================================================

function connect_engv_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_engagement_vouchers (
        id $pk,
        engagement_id INT DEFAULT 0, requirement_id INT DEFAULT 0,
        subject_kind VARCHAR(20) DEFAULT 'professional', subject_id INT DEFAULT 0, subject_name VARCHAR(200) DEFAULT '',
        poster_party_id INT DEFAULT 0, poster_name VARCHAR(200) DEFAULT '',
        basis VARCHAR(20) DEFAULT 'MAN_DAYS', rate REAL DEFAULT 0, rate_unit VARCHAR(10) DEFAULT 'day',
        rate_inclusive VARCHAR(12) DEFAULT 'INCLUSIVE', cadence VARCHAR(14) DEFAULT 'PER_DEPLOYMENT',
        period_label VARCHAR(40) DEFAULT '', period_start VARCHAR(20) DEFAULT '', period_end VARCHAR(20) DEFAULT '',
        fee_total REAL DEFAULT 0, reimb_total REAL DEFAULT 0, grand_total REAL DEFAULT 0,
        status VARCHAR(12) DEFAULT 'DRAFT',               -- DRAFT | SUBMITTED | APPROVED | PAID | REJECTED
        note VARCHAR(400) DEFAULT '', decided_by VARCHAR(150) DEFAULT '', decided_note VARCHAR(300) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '',
        submitted_at VARCHAR(30) DEFAULT '', decided_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_engagement_voucher_lines (
        id $pk, voucher_id INT DEFAULT 0,
        work_date VARCHAR(20) DEFAULT '', units REAL DEFAULT 1,
        fee REAL DEFAULT 0,
        travel REAL DEFAULT 0, lodging REAL DEFAULT 0, conveyance REAL DEFAULT 0, allowance REAL DEFAULT 0, misc REAL DEFAULT 0,
        receipt_ref VARCHAR(120) DEFAULT '', note VARCHAR(300) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // Supporting documents — receipts / bills / boarding passes a claimant
    // attaches to back a voucher (and, optionally, one day line). Lightweight
    // base64 storage, mirroring the professional's own file vault. ADDITIVE.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_engagement_voucher_files (
        id $pk, voucher_id INT DEFAULT 0, line_id INT DEFAULT 0,
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', size INT DEFAULT 0, file_data LONGTEXT,
        uploaded_kind VARCHAR(20) DEFAULT '', uploaded_id INT DEFAULT 0, uploaded_name VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // Platform commission + settlement (marketplace matchmaker model). Additive
    // columns so an existing voucher keeps working; recompute fills them in.
    if (function_exists('ensure_column')) {
        ensure_column('cx_engagement_vouchers', 'commission_pct',    'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'commission_total',  'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'commission_client', 'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'commission_pro',    'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'client_payable',    'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'pro_net',           'REAL DEFAULT 0');
        ensure_column('cx_engagement_vouchers', 'client_paid_at',    "VARCHAR(30) DEFAULT ''");
        ensure_column('cx_engagement_vouchers', 'pro_received_at',   "VARCHAR(30) DEFAULT ''");
        ensure_column('cx_engagement_vouchers', 'settled_at',        "VARCHAR(30) DEFAULT ''");
    }
    // The inspection report deliverable the professional produces for the client.
    // Held until the transaction is cleared (both sides confirmed payment) — the
    // client cannot download it before then. ADDITIVE.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_engagement_reports (
        id $pk, engagement_id INT DEFAULT 0, requirement_id INT DEFAULT 0, poster_party_id INT DEFAULT 0,
        subject_kind VARCHAR(20) DEFAULT '', subject_id INT DEFAULT 0,
        title VARCHAR(200) DEFAULT '', file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', size INT DEFAULT 0, file_data LONGTEXT,
        uploaded_kind VARCHAR(20) DEFAULT '', uploaded_id INT DEFAULT 0, uploaded_name VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_engv_subject ON cx_engagement_vouchers (subject_kind, subject_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_engv_eng ON cx_engagement_vouchers (engagement_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_engvl_v ON cx_engagement_voucher_lines (voucher_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_engvf_v ON cx_engagement_voucher_files (voucher_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_engr_eng ON cx_engagement_reports (engagement_id)"); } catch (Throwable $e) {}
}

/** The platform's commission rate (percent), admin-configurable. The platform
 *  is a matchmaker: it charges a nominal commission on the FEE (not on pass-through
 *  reimbursed expenses) and takes no responsibility for the settlement itself. */
function connect_commission_pct() {
    $v = function_exists('setting_get') ? setting_get('connect_commission_pct', 5.0) : 5.0;
    $v = (float)$v;
    if ($v < 0) $v = 0.0; if ($v > 100) $v = 100.0;
    return $v;
}

/** Reimbursable expense heads — claimable ONLY on an EXCLUSIVE engagement. */
function connect_engv_expense_heads() {
    return [
        'travel'     => 'Travel',
        'lodging'    => 'Hotel / lodging',
        'conveyance' => 'Local conveyance',
        'allowance'  => 'Allowances',
        'misc'       => 'Other',
    ];
}

/** The voucher status lifecycle. */
function connect_engv_statuses() { return ['DRAFT', 'SUBMITTED', 'APPROVED', 'PAID', 'REJECTED']; }
function connect_engv_status_label($s) {
    return ['DRAFT'=>'Draft','SUBMITTED'=>'Submitted','APPROVED'=>'Approved','PAID'=>'Paid','REJECTED'=>'Sent back'][strtoupper((string)$s)] ?? (string)$s;
}
/** Legal transitions (a new object lifecycle — see 03-object-lifecycles.md). */
function connect_engv_can_transition($from, $to) {
    $from = strtoupper((string)$from); $to = strtoupper((string)$to);
    $ok = [
        'DRAFT'     => ['SUBMITTED'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'DRAFT'],
        'APPROVED'  => ['PAID', 'REJECTED'],
        'REJECTED'  => ['DRAFT', 'SUBMITTED'],
        'PAID'      => [],
    ];
    return in_array($to, $ok[$from] ?? [], true);
}

function connect_engv_get($id) {
    connect_engv_migrate();
    return ops_one("SELECT * FROM cx_engagement_vouchers WHERE id=?", [(int)$id]) ?: null;
}
function connect_engv_lines($voucherId) {
    connect_engv_migrate();
    return ops_all("SELECT * FROM cx_engagement_voucher_lines WHERE voucher_id=? ORDER BY (work_date=''), work_date, id", [(int)$voucherId]) ?: [];
}

/** Is this engagement's rate quoted exclusive of expenses (heads claimable)? */
function connect_engv_is_exclusive($voucherOrEng) {
    $m = strtoupper((string)($voucherOrEng['rate_inclusive'] ?? 'INCLUSIVE'));
    return $m === 'EXCLUSIVE';
}

/**
 * Open (or reuse a DRAFT) voucher for an engagement. Copies the rate model,
 * basis and cadence off the engagement so the voucher is self-describing.
 * Returns [ok, msg, id].
 */
function connect_engv_open_for_engagement($engagementId, array $in = []) {
    connect_engv_migrate();
    $eng = function_exists('connect_engage_get_by_id') ? connect_engage_get_by_id($engagementId)
         : ops_one("SELECT * FROM cx_engagements WHERE id=?", [(int)$engagementId]);
    if (!$eng) return [false, 'Engagement not found.', 0];

    $cadence = strtoupper((string)($eng['voucher_cadence'] ?? 'PER_DEPLOYMENT'));
    $label   = trim((string)($in['period_label'] ?? ''));
    if ($label === '') $label = $cadence === 'PER_DAY' ? date('Y-m-d') : date('Y-m');

    // Reuse an existing DRAFT for the same engagement + period label (idempotent).
    $ex = ops_one("SELECT * FROM cx_engagement_vouchers WHERE engagement_id=? AND period_label=? AND status='DRAFT' ORDER BY id DESC", [(int)$engagementId, $label]);
    if ($ex) return [true, 'Voucher reopened.', (int)$ex['id']];

    $now = date('c');
    db()->prepare("INSERT INTO cx_engagement_vouchers
        (engagement_id,requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,
         basis,rate,rate_unit,rate_inclusive,cadence,period_label,period_start,period_end,status,note,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT',?,?,?)")
        ->execute([(int)$engagementId, (int)($eng['requirement_id'] ?? 0), (string)($eng['subject_kind'] ?? 'professional'),
                   (int)($eng['subject_id'] ?? 0), (string)($eng['subject_name'] ?? ''), (int)($eng['poster_party_id'] ?? 0),
                   (string)($eng['poster_name'] ?? ''), (string)($eng['basis'] ?? 'MAN_DAYS'), (float)($eng['rate'] ?? 0),
                   (string)($eng['rate_unit'] ?? 'day'), connect_engage_norm_rate_model($eng['rate_inclusive'] ?? 'INCLUSIVE'),
                   connect_engage_norm_cadence($cadence), $label, trim((string)($in['period_start'] ?? '')),
                   trim((string)($in['period_end'] ?? '')), trim((string)($in['note'] ?? '')), $now, $now]);
    return [true, 'Voucher opened.', (int)db()->lastInsertId()];
}

/** Fee for a line = units × the engagement's rate (day/month/visit). */
function connect_engv_line_fee($voucher, $units) {
    return round(max(0, (float)$units) * (float)($voucher['rate'] ?? 0), 2);
}

/**
 * Add a day/period line to a DRAFT voucher. On an INCLUSIVE engagement every
 * expense head is forced to 0 (the rate already covers them). Returns [ok,msg,id].
 */
function connect_engv_add_line($voucherId, array $in) {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId);
    if (!$v) return [false, 'Voucher not found.', 0];
    if (strtoupper((string)$v['status']) !== 'DRAFT') return [false, 'Add lines only while the voucher is a draft.', 0];

    $units = max(0, (float)($in['units'] ?? 1));
    if ($units <= 0) $units = 1;
    $fee = connect_engv_line_fee($v, $units);

    $exclusive = connect_engv_is_exclusive($v);
    // Which heads may be claimed at all is driven by the engagement's reimbursement
    // terms: a head the client marked "we provide it" or "in the rate" is NOT
    // claimable — its amount is forced to 0 no matter what is posted. If no terms were
    // set (legacy engagement), every head stays claimable exactly as before.
    $claimable = null;
    if ($exclusive && function_exists('connect_reqterms_claimable_heads')) {
        $eng = ops_one("SELECT reimb_terms FROM cx_engagements WHERE id=?", [(int)($v['engagement_id'] ?? 0)]);
        $claimable = array_flip(connect_reqterms_claimable_heads($eng ?: ''));
    }
    $head = function ($k) use ($in, $exclusive, $claimable) {
        if (!$exclusive) return 0.0;
        if ($claimable !== null && !isset($claimable[$k])) return 0.0; // client covers this itself
        return max(0, (float)($in[$k] ?? 0));
    };
    db()->prepare("INSERT INTO cx_engagement_voucher_lines
        (voucher_id,work_date,units,fee,travel,lodging,conveyance,allowance,misc,receipt_ref,note,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$voucherId, trim((string)($in['work_date'] ?? '')), $units, $fee,
                   $head('travel'), $head('lodging'), $head('conveyance'), $head('allowance'), $head('misc'),
                   trim((string)($in['receipt_ref'] ?? '')), trim((string)($in['note'] ?? '')), date('c')]);
    $id = (int)db()->lastInsertId();
    connect_engv_recompute($voucherId);
    return [true, 'Day added.', $id];
}

/** Remove a line from a DRAFT voucher and recompute. */
function connect_engv_delete_line($lineId, $voucherId) {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId);
    if (!$v || strtoupper((string)$v['status']) !== 'DRAFT') return false;
    db()->prepare("DELETE FROM cx_engagement_voucher_lines WHERE id=? AND voucher_id=?")->execute([(int)$lineId, (int)$voucherId]);
    connect_engv_recompute($voucherId);
    return true;
}

/** Recompute fee / reimbursable / grand totals from the lines. */
function connect_engv_recompute($voucherId) {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId); if (!$v) return;
    $exclusive = connect_engv_is_exclusive($v);
    $fee = 0.0; $reimb = 0.0;
    foreach (connect_engv_lines($voucherId) as $l) {
        $fee += (float)$l['fee'];
        if ($exclusive) $reimb += (float)$l['travel'] + (float)$l['lodging'] + (float)$l['conveyance'] + (float)$l['allowance'] + (float)$l['misc'];
    }
    $fee = round($fee, 2); $reimb = round($reimb, 2); $grand = round($fee + $reimb, 2);

    // Platform commission — on the FEE only, split 50/50 between the two sides.
    // The client pays (grand + its half); the professional nets (grand − its half);
    // reimbursed expenses pass through untouched. The platform earns the whole
    // commission for making the match.
    $pct    = connect_commission_pct();
    $commTot = round($fee * $pct / 100, 2);
    $commCl  = round($commTot / 2, 2);
    $commPro = round($commTot - $commCl, 2);           // keep the two halves summing exactly
    $clientPayable = round($grand + $commCl, 2);
    $proNet        = round($grand - $commPro, 2);

    db()->prepare("UPDATE cx_engagement_vouchers
        SET fee_total=?, reimb_total=?, grand_total=?,
            commission_pct=?, commission_total=?, commission_client=?, commission_pro=?, client_payable=?, pro_net=?,
            updated_at=? WHERE id=?")
        ->execute([$fee, $reimb, $grand, $pct, $commTot, $commCl, $commPro, $clientPayable, $proNet, date('c'), (int)$voucherId]);
}

/** The commission / payable breakdown for a voucher, as plain numbers for a view. */
function connect_engv_money($v) {
    return [
        'fee'            => (float)($v['fee_total'] ?? 0),
        'reimb'          => (float)($v['reimb_total'] ?? 0),
        'grand'          => (float)($v['grand_total'] ?? 0),
        'commission_pct' => (float)($v['commission_pct'] ?? 0),
        'commission'     => (float)($v['commission_total'] ?? 0),
        'commission_client' => (float)($v['commission_client'] ?? 0),
        'commission_pro'    => (float)($v['commission_pro'] ?? 0),
        'client_payable' => (float)($v['client_payable'] ?? 0),
        'pro_net'        => (float)($v['pro_net'] ?? 0),
    ];
}

/** Move a voucher along its lifecycle. Returns [ok, msg]. The optional $note is
 *  recorded as the decision note — used e.g. when a client returns a voucher to
 *  the inspector for clarification (SUBMITTED → REJECTED). */
function connect_engv_set_status($voucherId, $to, $by = '', $note = '') {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId); if (!$v) return [false, 'Voucher not found.'];
    $to = strtoupper((string)$to);
    if (!in_array($to, connect_engv_statuses(), true)) return [false, 'Invalid status.'];
    if (!connect_engv_can_transition($v['status'], $to)) return [false, 'That change is not allowed from ' . connect_engv_status_label($v['status']) . '.'];
    if ($to === 'SUBMITTED' && !connect_engv_lines($voucherId)) return [false, 'Add at least one day before submitting.'];

    $now = date('c'); $sets = "status=?, updated_at=?"; $args = [$to, $now];
    if ($to === 'SUBMITTED') { $sets .= ", submitted_at=?"; $args[] = $now; }
    if (in_array($to, ['APPROVED', 'REJECTED', 'PAID'], true)) {
        $sets .= ", decided_at=?, decided_by=?, decided_note=?"; $args[] = $now; $args[] = (string)$by; $args[] = substr((string)$note, 0, 300);
    }
    // Re-opening a returned voucher to revise it clears the prior decision note.
    if ($to === 'DRAFT') { $sets .= ", decided_note=?"; $args[] = ''; }
    $args[] = (int)$voucherId;
    db()->prepare("UPDATE cx_engagement_vouchers SET $sets WHERE id=?")->execute($args);
    return [true, 'Voucher ' . strtolower(connect_engv_status_label($to)) . '.'];
}

/** True when this poster party (a client) owns the voucher's requirement — i.e.
 *  the voucher is a claim against a job THAT client posted. Gates client review. */
function connect_engv_owned_by_party($voucher, $partyId) {
    return (int)($voucher['poster_party_id'] ?? 0) === (int)$partyId && (int)$partyId > 0;
}
/** All vouchers a poster party may review (their own posted jobs), newest first. */
function connect_engv_for_poster_party($partyId) {
    connect_engv_migrate();
    return ops_all("SELECT * FROM cx_engagement_vouchers WHERE poster_party_id=? ORDER BY id DESC", [(int)$partyId]) ?: [];
}

// ---------------------------------------------------------------------------
//  Settlement (matchmaker model) — the platform is not the paymaster, so BOTH
//  sides confirm: the client that it has paid, the professional that it has been
//  received. Only when both confirm is the transaction "cleared" — which is what
//  releases the inspection report to the client. Meaningful once the client has
//  APPROVED the voucher.
// ---------------------------------------------------------------------------

/** Settled = the money is done. Reached either the marketplace way (BOTH sides
 *  confirmed) or by any path that already marked the voucher PAID (e.g. the desk
 *  marking an on-roll voucher paid). */
function connect_engv_is_settled($v) {
    if (strtoupper((string)($v['status'] ?? '')) === 'PAID') return true;
    return trim((string)($v['client_paid_at'] ?? '')) !== '' && trim((string)($v['pro_received_at'] ?? '')) !== '';
}
/** Record a payment confirmation from one side. $side = 'client' | 'pro'.
 *  Allowed only after the client has approved the voucher. Returns [ok, msg]. */
function connect_engv_confirm($voucherId, $side, $by = '') {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId); if (!$v) return [false, 'Voucher not found.'];
    if (!in_array(strtoupper((string)$v['status']), ['APPROVED', 'PAID'], true))
        return [false, 'The voucher must be approved before payment is confirmed.'];
    $col = $side === 'client' ? 'client_paid_at' : ($side === 'pro' ? 'pro_received_at' : '');
    if ($col === '') return [false, 'Unknown party.'];
    if (trim((string)$v[$col]) !== '') return [true, 'Already confirmed.'];
    $now = date('c');
    db()->prepare("UPDATE cx_engagement_vouchers SET $col=?, updated_at=? WHERE id=?")->execute([$now, $now, (int)$voucherId]);
    // If both sides are now confirmed, stamp the settlement + move to PAID.
    $v = connect_engv_get($voucherId);
    if (connect_engv_is_settled($v) && trim((string)$v['settled_at']) === '') {
        db()->prepare("UPDATE cx_engagement_vouchers SET settled_at=?, updated_at=? WHERE id=?")->execute([$now, $now, (int)$voucherId]);
        if (strtoupper((string)$v['status']) === 'APPROVED') connect_engv_set_status($voucherId, 'PAID', $by);
    }
    return [true, 'Payment confirmed.'];
}

/** Has this engagement been fully cleared? True when it has at least one approved
 *  voucher and every approved voucher on it is settled (both sides confirmed).
 *  This is the gate that releases the inspection report to the client. */
function connect_engv_engagement_cleared($engagementId) {
    connect_engv_migrate();
    $rows = ops_all("SELECT status, client_paid_at, pro_received_at FROM cx_engagement_vouchers
                     WHERE engagement_id=? AND status IN ('APPROVED','PAID')", [(int)$engagementId]) ?: [];
    if (!$rows) return false;
    foreach ($rows as $r) if (!connect_engv_is_settled($r)) return false;
    return true;
}

/** Platform commission rollup — what the matchmaker has earned / is in pipeline. */
function connect_commission_summary() {
    connect_engv_migrate();
    $sum = function ($where) {
        try { return (float)ops_val("SELECT COALESCE(SUM(commission_total),0) FROM cx_engagement_vouchers WHERE $where"); }
        catch (Throwable $e) { return 0.0; }
    };
    return [
        'earned'   => round($sum("status IN ('APPROVED','PAID')"), 2),   // client has accepted the claim
        'settled'  => round($sum("settled_at <> ''"), 2),                // both sides cleared
        'pipeline' => round($sum("status='SUBMITTED'"), 2),              // awaiting client review
        'rate'     => connect_commission_pct(),
    ];
}

/** All vouchers for one engagement, newest first. */
function connect_engv_for_engagement($engagementId) {
    connect_engv_migrate();
    return ops_all("SELECT * FROM cx_engagement_vouchers WHERE engagement_id=? ORDER BY id DESC", [(int)$engagementId]) ?: [];
}
/** All vouchers for one subject (professional | inspector | bench). */
function connect_engv_for_subject($kind, $id) {
    connect_engv_migrate();
    return ops_all("SELECT * FROM cx_engagement_vouchers WHERE subject_kind=? AND subject_id=? ORDER BY id DESC", [(string)$kind, (int)$id]) ?: [];
}
/** Convenience: a professional's own vouchers, with the engagement title. */
function connect_engv_for_professional($proId) {
    connect_engv_migrate();
    try {
        $st = db()->prepare("SELECT ev.*, r.ref_code, r.title AS req_title
                             FROM cx_engagement_vouchers ev LEFT JOIN cx_requirements r ON r.id=ev.requirement_id
                             WHERE ev.subject_kind='professional' AND ev.subject_id=? ORDER BY ev.id DESC");
        $st->execute([(int)$proId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** A short summary for a subject: counts by status + total submitted/paid value. */
function connect_engv_summary_for_subject($kind, $id) {
    $out = ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'paid' => 0, 'total' => 0, 'paid_value' => 0.0, 'pending_value' => 0.0];
    foreach (connect_engv_for_subject($kind, $id) as $v) {
        $s = strtoupper((string)$v['status']); $out['total']++;
        if ($s === 'DRAFT') $out['draft']++;
        elseif ($s === 'SUBMITTED') { $out['submitted']++; $out['pending_value'] += (float)$v['grand_total']; }
        elseif ($s === 'APPROVED') { $out['approved']++; $out['pending_value'] += (float)$v['grand_total']; }
        elseif ($s === 'PAID') { $out['paid']++; $out['paid_value'] += (float)$v['grand_total']; }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Supporting documents (receipts / bills) on a voucher.
// ---------------------------------------------------------------------------

/** Supporting docs may be added/removed while the voucher is still open for
 *  change — DRAFT (being built) or SUBMITTED (under review). Once APPROVED or
 *  PAID the attachment set is frozen with the decision. */
function connect_engv_can_attach($v) {
    return in_array(strtoupper((string)($v['status'] ?? '')), ['DRAFT', 'SUBMITTED'], true);
}

/** Attach a supporting document to a voucher (optionally to one day line).
 *  Reuses the shared upload guard. Returns [ok, msg, id]. */
function connect_engv_file_add($voucherId, $lineId, $file, $byKind = '', $byId = 0, $byName = '') {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId);
    if (!$v) return [false, 'Voucher not found.', 0];
    if (!connect_engv_can_attach($v)) return [false, 'Documents can be added only while the voucher is a draft or under review.', 0];
    if (!$file || ($file['tmp_name'] ?? '') === '' || !is_uploaded_file($file['tmp_name']))
        return [false, 'Choose a file to upload.', 0];
    $bytes = (string)@file_get_contents($file['tmp_name']);
    if ($bytes === '') return [false, 'That file looks empty.', 0];
    if (function_exists('upload_reject_reason')) {
        $why = upload_reject_reason($bytes, (string)($file['name'] ?? ''), (string)($file['type'] ?? ''));
        if ($why !== '') return [false, $why, 0];
    }
    // If a line is named, it must belong to this voucher.
    $lineId = (int)$lineId;
    if ($lineId > 0 && !ops_val("SELECT COUNT(*) FROM cx_engagement_voucher_lines WHERE id=? AND voucher_id=?", [$lineId, (int)$voucherId]))
        $lineId = 0;
    db()->prepare("INSERT INTO cx_engagement_voucher_files
        (voucher_id,line_id,file_name,mime,size,file_data,uploaded_kind,uploaded_id,uploaded_name,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$voucherId, $lineId, substr((string)($file['name'] ?? 'file'), 0, 255),
                   (string)($file['type'] ?? ''), strlen($bytes), base64_encode($bytes),
                   (string)$byKind, (int)$byId, substr((string)$byName, 0, 150), date('c')]);
    return [true, 'Supporting document uploaded.', (int)db()->lastInsertId()];
}

/** All supporting docs for a voucher (metadata only — no bytes), newest first. */
function connect_engv_files($voucherId) {
    connect_engv_migrate();
    return ops_all("SELECT id,voucher_id,line_id,file_name,mime,size,uploaded_kind,uploaded_name,created_at
                    FROM cx_engagement_voucher_files WHERE voucher_id=? ORDER BY id DESC", [(int)$voucherId]) ?: [];
}
/** How many supporting docs a voucher carries. */
function connect_engv_file_count($voucherId) {
    connect_engv_migrate();
    return (int)ops_val("SELECT COUNT(*) FROM cx_engagement_voucher_files WHERE voucher_id=?", [(int)$voucherId]);
}
/** One file row WITH bytes, for serving. When $voucherId is given it must match
 *  (ownership scoping by the caller). */
function connect_engv_file_row($fileId, $voucherId = 0) {
    connect_engv_migrate();
    $row = ops_one("SELECT * FROM cx_engagement_voucher_files WHERE id=?", [(int)$fileId]);
    if (!$row) return null;
    if ($voucherId > 0 && (int)$row['voucher_id'] !== (int)$voucherId) return null;
    return $row;
}
/** Remove a supporting doc while the voucher is still open for change. */
function connect_engv_file_delete($fileId, $voucherId) {
    connect_engv_migrate();
    $v = connect_engv_get($voucherId);
    if (!$v || !connect_engv_can_attach($v)) return false;
    db()->prepare("DELETE FROM cx_engagement_voucher_files WHERE id=? AND voucher_id=?")->execute([(int)$fileId, (int)$voucherId]);
    return true;
}

// ---------------------------------------------------------------------------
//  Inspection report deliverable (held until the transaction is cleared).
// ---------------------------------------------------------------------------

/** The professional uploads the inspection report for an engagement. Reuses the
 *  shared upload guard. Returns [ok, msg, id]. */
function connect_engv_report_add($engagementId, $file, $title = '', $byKind = '', $byId = 0, $byName = '') {
    connect_engv_migrate();
    $eng = ops_one("SELECT * FROM cx_engagements WHERE id=?", [(int)$engagementId]);
    if (!$eng) return [false, 'Engagement not found.', 0];
    if (!$file || ($file['tmp_name'] ?? '') === '' || !is_uploaded_file($file['tmp_name']))
        return [false, 'Choose a file to upload.', 0];
    $bytes = (string)@file_get_contents($file['tmp_name']);
    if ($bytes === '') return [false, 'That file looks empty.', 0];
    if (function_exists('upload_reject_reason')) {
        $why = upload_reject_reason($bytes, (string)($file['name'] ?? ''), (string)($file['type'] ?? ''));
        if ($why !== '') return [false, $why, 0];
    }
    db()->prepare("INSERT INTO cx_engagement_reports
        (engagement_id,requirement_id,poster_party_id,subject_kind,subject_id,title,file_name,mime,size,file_data,uploaded_kind,uploaded_id,uploaded_name,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$engagementId, (int)($eng['requirement_id'] ?? 0), (int)($eng['poster_party_id'] ?? 0),
                   (string)($eng['subject_kind'] ?? ''), (int)($eng['subject_id'] ?? 0),
                   substr(trim((string)$title), 0, 200) ?: 'Inspection report',
                   substr((string)($file['name'] ?? 'report'), 0, 255), (string)($file['type'] ?? ''), strlen($bytes),
                   base64_encode($bytes), (string)$byKind, (int)$byId, substr((string)$byName, 0, 150), date('c')]);
    return [true, 'Report uploaded.', (int)db()->lastInsertId()];
}
/** Report deliverables for an engagement (metadata only). */
function connect_engv_reports($engagementId) {
    connect_engv_migrate();
    return ops_all("SELECT id,engagement_id,requirement_id,poster_party_id,title,file_name,mime,size,uploaded_name,created_at
                    FROM cx_engagement_reports WHERE engagement_id=? ORDER BY id DESC", [(int)$engagementId]) ?: [];
}
/** One report row WITH bytes, for serving. */
function connect_engv_report_row($id) {
    connect_engv_migrate();
    return ops_one("SELECT * FROM cx_engagement_reports WHERE id=?", [(int)$id]) ?: null;
}
/** Remove a report deliverable (uploader / desk). */
function connect_engv_report_delete($id, $engagementId) {
    connect_engv_migrate();
    db()->prepare("DELETE FROM cx_engagement_reports WHERE id=? AND engagement_id=?")->execute([(int)$id, (int)$engagementId]);
    return true;
}

/** Plain-language one-liner for a voucher header. */
function connect_engv_describe($v) {
    $model = connect_engv_is_exclusive($v) ? 'Fee + expenses' : 'All-inclusive';
    $unit  = (string)($v['rate_unit'] ?? 'day');
    $rate  = (float)($v['rate'] ?? 0) > 0 ? '₹' . number_format((int)$v['rate']) . '/' . $unit : '';
    return ['model' => $model, 'rate' => $rate,
            'fee' => (float)($v['fee_total'] ?? 0), 'reimb' => (float)($v['reimb_total'] ?? 0), 'grand' => (float)($v['grand_total'] ?? 0)];
}
