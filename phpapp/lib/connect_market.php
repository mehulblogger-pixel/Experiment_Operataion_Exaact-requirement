<?php
// ============================================================================
//  CONNECT — Manpower Marketplace core  (slice K2a, additive)
//
//  The two-sided marketplace folded into EXAACT: a POST (a technical-manpower
//  requirement) and an APPLY (a professional puts themselves forward), with the
//  post→shortlist→award lifecycle. Adopted from the Inspect Connect blueprint
//  (M7/M8/M9 intake side). See docs/connect/00-integration-program.md.
//
//  K2a scope = the engine + lifecycles + the STAFF desk, gated on existing
//  internal permissions (coordinator/master). External self-service (client
//  portal posts, vendor/inspector portal applies) is K2b, after the role→portal
//  mapping is confirmed — so this slice invents NO new external permission.
//
//  ADDITIVE CONTRACT: two new cx_ tables only; new statuses documented in
//  docs/03-object-lifecycles.md in the same commit; no existing table, route,
//  view, permission or status touched.
// ============================================================================

// --- Lifecycles (documented in docs/03-object-lifecycles.md) ----------------
const CX_REQ_STATUSES = ['DRAFT', 'OPEN', 'SHORTLISTING', 'AWARDED', 'CLOSED', 'CANCELLED', 'EXPIRED'];
const CX_REQ_TRANSITIONS = [
    'DRAFT'        => ['OPEN', 'CANCELLED'],
    'OPEN'         => ['SHORTLISTING', 'CLOSED', 'CANCELLED', 'EXPIRED'],
    'SHORTLISTING' => ['AWARDED', 'OPEN', 'CLOSED', 'CANCELLED'],
    'AWARDED'      => ['CLOSED'],
    'CLOSED'       => [], 'CANCELLED' => [], 'EXPIRED' => [],
];
const CX_APP_STATUSES = ['APPLIED', 'SHORTLISTED', 'OFFERED', 'ACCEPTED', 'DECLINED', 'WITHDRAWN', 'REJECTED'];
const CX_APP_TRANSITIONS = [
    'APPLIED'     => ['SHORTLISTED', 'REJECTED', 'WITHDRAWN'],
    'SHORTLISTED' => ['OFFERED', 'REJECTED', 'WITHDRAWN'],
    'OFFERED'     => ['ACCEPTED', 'DECLINED', 'WITHDRAWN'],
    'ACCEPTED'    => [], 'DECLINED' => [], 'WITHDRAWN' => [], 'REJECTED' => [],
];

function cx_req_can_transition($from, $to) {
    return in_array($to, CX_REQ_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}
function cx_app_can_transition($from, $to) {
    return in_array($to, CX_APP_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}

/** Additive tables — requirements (the post) and applications (the apply). */
function connect_market_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_requirements (
        id $pk, ref_code VARCHAR(24) DEFAULT '', title VARCHAR(200) DEFAULT '',
        poster_party_id INT DEFAULT 0, poster_name VARCHAR(200) DEFAULT '',
        sector_code VARCHAR(40) DEFAULT '', discipline_code VARCHAR(40) DEFAULT '',
        equipment_group VARCHAR(40) DEFAULT '', material_code VARCHAR(40) DEFAULT '',
        location VARCHAR(160) DEFAULT '', work_type VARCHAR(40) DEFAULT '',
        start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '',
        positions INT DEFAULT 1, rate_min REAL DEFAULT 0, rate_max REAL DEFAULT 0,
        rate_unit VARCHAR(20) DEFAULT '', description TEXT,
        status VARCHAR(16) DEFAULT 'DRAFT', awarded_application_id INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        posted_at VARCHAR(30) DEFAULT '', closed_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_applications (
        id $pk, requirement_id INT DEFAULT 0, inspector_id INT DEFAULT 0,
        applicant_name VARCHAR(200) DEFAULT '', cover_note TEXT,
        proposed_rate REAL DEFAULT 0, status VARCHAR(16) DEFAULT 'APPLIED',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    // K2b — an external applicant (an agency/vendor applying via its portal) is
    // a party, not a pool inspector. Additive column so those applications
    // dedupe correctly on the applying party.
    if (function_exists('ensure_column')) ensure_column('cx_applications', 'applicant_party_id', 'INT DEFAULT 0');
    //  Owner, 11 Nov 2026 — an applicant closed only because someone else was
    //  awarded may be brought back if that person cannot do the job; one the
    //  client actually turned down may not. And when the awarded person is
    //  replaced, when it happened.
    if (function_exists('ensure_column')) {
        //  Owner, 11 Nov 2026 — the date applications close (see cx_req_expiry_sweep).
        ensure_column('cx_requirements', 'closes_on', "VARCHAR(10) DEFAULT ''");
        ensure_column('cx_applications', 'closed_by_award', 'INT DEFAULT 0');
        ensure_column('cx_applications', 'replaced_at', "VARCHAR(30) DEFAULT ''");
    }
    // Deputation & rate terms chosen at posting — the preferred engagement basis,
    // the rate model (all-inclusive vs fee-only), and the voucher cadence. These
    // seed the booking (cx_engagements) and are additive/defaulted.
    if (function_exists('ensure_column')) {
        ensure_column('cx_requirements', 'deputation_basis', "VARCHAR(20) DEFAULT ''");      // '' = any; else a connect_engage basis
        ensure_column('cx_requirements', 'rate_inclusive', "VARCHAR(12) DEFAULT 'INCLUSIVE'"); // INCLUSIVE | EXCLUSIVE
        ensure_column('cx_requirements', 'voucher_cadence', "VARCHAR(14) DEFAULT 'PER_DEPLOYMENT'"); // PER_DAY | PER_DEPLOYMENT
        // Posting-time cost estimate & reimbursement terms (additive; all defaulted so
        // an old posting reads as "no estimate given"). The client types the base fee,
        // an estimated quantity, and — for a fee-only (EXCLUSIVE) posting — how each
        // reimbursable head is covered and its ceiling. No cap is enforced anywhere;
        // a ceiling is a number the client enters, shown as guidance to the professional.
        ensure_column('cx_requirements', 'est_rate',    "REAL DEFAULT 0");      // base fee per unit (day/month/visit)
        ensure_column('cx_requirements', 'est_qty',     "REAL DEFAULT 0");      // estimated quantity in that unit
        ensure_column('cx_requirements', 'est_tax_pct', "REAL DEFAULT 18");     // GST %, charged on top of the subtotal
        ensure_column('cx_requirements', 'est_tds_pct', "REAL DEFAULT 0");      // TDS %, withheld by the client on the pre-GST value
        ensure_column('cx_requirements', 'est_sac',     "VARCHAR(20) DEFAULT ''"); // SAC / HSN code (manpower supply = 998519)
        ensure_column('cx_requirements', 'reimb_terms', "TEXT DEFAULT ''");     // JSON: {head:{mode,ceiling,per}}
    }
}

/** Is a reimbursable head claimable on a voucher, given how the client set it?
 *  ACTUALS / CEILING → yes (a ceiling is guidance, not a block). IN_RATE (it's in
 *  the fee) and PROVIDED (the client arranges it directly) → NO amount is claimable. */
function connect_reqterms_head_claimable($mode) {
    return in_array(strtoupper((string)$mode), ['ACTUALS', 'CEILING'], true);
}

/** The set of expense heads a professional MAY claim on a voucher for this
 *  engagement/requirement row. Backward-compatible: if no reimbursement terms were
 *  set (empty), EVERY head stays claimable exactly as before — nothing is blocked. */
function connect_reqterms_claimable_heads($row) {
    $raw = is_array($row) ? (string)($row['reimb_terms'] ?? '') : (string)$row;
    $all = array_keys(connect_reqterms_heads());
    if (trim($raw) === '') return $all;                 // legacy / no terms → all claimable
    $terms = connect_reqterms_parse($row); $out = [];
    foreach ($all as $k) if (connect_reqterms_head_claimable($terms[$k]['mode'] ?? 'IN_RATE')) $out[] = $k;
    return $out;
}

/** The five reimbursable heads a client can define at posting. Keyed to the
 *  voucher expense heads (connect_engv_expense_heads) so posting terms and the
 *  later claim lines line up 1:1 — what was promised is what gets claimed. */
function connect_reqterms_heads() {
    return [
        'allowance'  => 'Food / per-diem',
        'lodging'    => 'Accommodation',
        'travel'     => 'Travel & mobilisation',
        'conveyance' => 'Local conveyance',
        'misc'       => 'Miscellaneous',
    ];
}

/** How each reimbursable head is covered. IMPORTANT: a CEILING is never enforced
 *  by code — it is a number the client types, carried through as guidance only. */
function connect_reqterms_cover_modes() {
    return [
        'IN_RATE'  => 'In the rate',
        'PROVIDED' => 'We provide it',
        'ACTUALS'  => 'Reimbursed at actuals',
        'CEILING'  => 'Reimbursed up to a ceiling',
    ];
}

/** Normalise one head's terms to legal values. */
function _reqterms_norm_head(array $h) {
    $mode = strtoupper((string)($h['mode'] ?? 'IN_RATE'));
    if (!isset(connect_reqterms_cover_modes()[$mode])) $mode = 'IN_RATE';
    $per = strtoupper((string)($h['per'] ?? 'DAY')) === 'DEPLOYMENT' ? 'DEPLOYMENT' : 'DAY';
    return ['mode' => $mode, 'ceiling' => max(0, (float)($h['ceiling'] ?? 0)), 'per' => $per];
}

/** Read the stored reimbursement-terms JSON off a requirement row into a clean,
 *  fully-populated array (every head present, defaulted to IN_RATE). */
function connect_reqterms_parse($req) {
    $raw = is_array($req) ? (string)($req['reimb_terms'] ?? '') : (string)$req;
    $t = []; if ($raw !== '') { $d = json_decode($raw, true); if (is_array($d)) $t = $d; }
    $out = [];
    foreach (connect_reqterms_heads() as $k => $lbl) $out[$k] = _reqterms_norm_head(is_array($t[$k] ?? null) ? $t[$k] : []);
    return $out;
}

/** Build the terms JSON from posted form fields shaped reimb[head][mode|ceiling|per]. */
function connect_reqterms_from_input(array $in) {
    $src = is_array($in['reimb'] ?? null) ? $in['reimb'] : [];
    $t = [];
    foreach (connect_reqterms_heads() as $k => $lbl) $t[$k] = _reqterms_norm_head(is_array($src[$k] ?? null) ? $src[$k] : []);
    return json_encode($t);
}

/**
 * The posting-time cost estimate. Pure arithmetic — it NEVER enforces a cap; it
 * only sums the numbers the client entered so they (and the professional) see the
 * likely cost before anyone commits.
 *   INCLUSIVE → subtotal = fee × qty (reimbursables are inside the rate).
 *   EXCLUSIVE → subtotal = fee × qty + Σ(ceilings); heads set to "at actuals" are
 *               flagged (excluded from the number, shown as "+ actuals").
 * GST is added on top at est_tax_pct.
 */
function connect_reqterms_estimate($req) {
    $rate = max(0, (float)($req['est_rate'] ?? 0));
    $qty  = max(0, (float)($req['est_qty'] ?? 0));
    $taxPct = (float)($req['est_tax_pct'] ?? 18); if ($taxPct < 0) $taxPct = 0;
    $inclusive = strtoupper((string)($req['rate_inclusive'] ?? 'INCLUSIVE')) !== 'EXCLUSIVE';
    $feeTotal = $rate * $qty;
    $reimbTotal = 0.0; $hasActuals = false; $lines = [];
    if (!$inclusive) {
        $terms = connect_reqterms_parse($req);
        foreach (connect_reqterms_heads() as $k => $lbl) {
            $h = $terms[$k]; $amt = 0.0;
            if ($h['mode'] === 'CEILING' && $h['ceiling'] > 0) {
                $amt = $h['ceiling'] * ($h['per'] === 'DAY' ? max(1, $qty) : 1);
                $reimbTotal += $amt;
            } elseif ($h['mode'] === 'ACTUALS') { $hasActuals = true; }
            $lines[$k] = ['label' => $lbl, 'mode' => $h['mode'], 'ceiling' => $h['ceiling'], 'per' => $h['per'], 'amount' => $amt];
        }
    }
    $subtotal = $feeTotal + $reimbTotal;
    $tax = $subtotal * $taxPct / 100.0;
    $invoice = $subtotal + $tax;                       // what is billed (incl. GST)
    // TDS is deducted by the client on the value EXCLUDING GST (CBDT Circular 23/2017),
    // i.e. on the subtotal, not the GST-inclusive invoice. It is withheld from the
    // payout, not added to the bill. The rate is the client's to set (194C 1%/2% or
    // 194J 10%), so it is read straight from the posting — never assumed by code.
    $tdsPct = (float)($req['est_tds_pct'] ?? 0); if ($tdsPct < 0) $tdsPct = 0;
    $tds = $subtotal * $tdsPct / 100.0;
    return [
        'inclusive' => $inclusive, 'rate' => $rate, 'qty' => $qty,
        'fee_total' => $feeTotal, 'reimb_total' => $reimbTotal, 'subtotal' => $subtotal,
        'tax_pct' => $taxPct, 'tax' => $tax, 'grand' => $invoice, 'invoice_total' => $invoice,
        'tds_pct' => $tdsPct, 'tds' => $tds, 'net_receivable' => $invoice - $tds,
        'sac' => (string)($req['est_sac'] ?? ''),
        'has_actuals' => $hasActuals, 'lines' => $lines,
        'has_estimate' => ($rate > 0 && $qty > 0),
    ];
}

/** Persist the posting-time deputation/rate terms on a requirement (additive). */
function cx_requirement_save_terms($id, array $in) {
    connect_market_migrate();
    $basis = strtoupper((string)($in['deputation_basis'] ?? ''));
    if ($basis !== '' && function_exists('connect_engage_bases') && !isset(connect_engage_bases()[$basis])) $basis = '';
    $rate = function_exists('connect_engage_norm_rate_model') ? connect_engage_norm_rate_model($in['rate_inclusive'] ?? 'INCLUSIVE') : 'INCLUSIVE';
    $cad  = function_exists('connect_engage_norm_cadence') ? connect_engage_norm_cadence($in['voucher_cadence'] ?? 'PER_DEPLOYMENT') : 'PER_DEPLOYMENT';
    // Posting-time estimate & reimbursement terms (additive). Only written when the
    // form supplied them; a form without these keys leaves the columns at default.
    $estRate = max(0, (float)($in['est_rate'] ?? 0));
    $estQty  = max(0, (float)($in['est_qty']  ?? 0));
    $taxPct  = array_key_exists('est_tax_pct', $in) ? max(0, (float)$in['est_tax_pct']) : 18.0;
    $tdsPct  = array_key_exists('est_tds_pct', $in) ? max(0, (float)$in['est_tds_pct']) : 0.0;
    $sac     = trim((string)($in['est_sac'] ?? ''));
    $reimb   = connect_reqterms_from_input($in);
    db()->prepare("UPDATE cx_requirements SET deputation_basis=?, rate_inclusive=?, voucher_cadence=?, est_rate=?, est_qty=?, est_tax_pct=?, est_tds_pct=?, est_sac=?, reimb_terms=?, updated_at=? WHERE id=?")
        ->execute([$basis, $rate, $cad, $estRate, $estQty, $taxPct, $tdsPct, $sac, $reimb, date('c'), (int)$id]);
    return true;
}

/** Next requirement reference — CX-REQ-0001, monotonic on id. */
function cx_req_next_code() {
    $n = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM cx_requirements") + 1;
    return 'CX-REQ-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Create a requirement (DRAFT), or post it straight to OPEN. Returns its id. */
function cx_requirement_create(array $in, $post = false) {
    connect_market_migrate();
    $now = date('c');
    $status = $post ? 'OPEN' : 'DRAFT';
    db()->prepare("INSERT INTO cx_requirements
        (ref_code,title,poster_party_id,poster_name,sector_code,discipline_code,equipment_group,material_code,
         location,work_type,start_date,end_date,positions,rate_min,rate_max,rate_unit,description,status,
         created_by,created_at,posted_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            cx_req_next_code(), trim((string)($in['title'] ?? '')), (int)($in['poster_party_id'] ?? 0),
            trim((string)($in['poster_name'] ?? '')), (string)($in['sector_code'] ?? ''), (string)($in['discipline_code'] ?? ''),
            (string)($in['equipment_group'] ?? ''), (string)($in['material_code'] ?? ''),
            trim((string)($in['location'] ?? '')), (string)($in['work_type'] ?? ''),
            (string)($in['start_date'] ?? ''), (string)($in['end_date'] ?? ''),
            max(1, (int)($in['positions'] ?? 1)), (float)($in['rate_min'] ?? 0), (float)($in['rate_max'] ?? 0),
            (string)($in['rate_unit'] ?? ''), trim((string)($in['description'] ?? '')), $status,
            function_exists('user_name') ? user_name(current_user()) : '', $now, $post ? $now : '', $now,
        ]);
    $id = (int)db()->lastInsertId();
    //  Posted straight away: it gets its closing date now (owner, 11 Nov 2026).
    if ($post) db()->prepare("UPDATE cx_requirements SET closes_on=? WHERE id=?")->execute([cx_req_closes_default(['posted_at' => $now, 'start_date' => (string)($in['start_date'] ?? '')]), $id]);
    // Deputation / rate terms, when the posting form supplied them.
    if (isset($in['deputation_basis']) || isset($in['rate_inclusive']) || isset($in['voucher_cadence']))
        cx_requirement_save_terms($id, $in);
    return $id;
}

function cx_requirement_get($id) { return ops_one("SELECT * FROM cx_requirements WHERE id=?", [(int)$id]) ?: null; }

//  Owner, 10 Nov 2026 — a posted requirement could not be corrected: a typo
//  meant duplicating it and cancelling the original. Its details may now be
//  changed while it is a draft, open or being shortlisted — before anybody has
//  been awarded on the strength of them. Returns [ok, message].
function cx_requirement_update($id, array $in) {
    $r = cx_requirement_get($id);
    if (!$r) return [false, 'Requirement not found.'];
    if (!in_array(strtoupper((string)$r['status']), ['DRAFT', 'OPEN', 'SHORTLISTING'], true))
        return [false, 'Only a requirement that is not yet awarded can be changed.'];
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') return [false, 'A requirement needs a title.'];
    $f = [
        'title' => mb_substr($title, 0, 200),
        'poster_party_id' => (int)($in['poster_party_id'] ?? $r['poster_party_id']),
        'location' => mb_substr(trim((string)($in['location'] ?? '')), 0, 160),
        //  Only a kind of work from the one list (or the one it already had).
        'work_type' => (function_exists('cx_pro_work_types') && isset(cx_pro_work_types()[(string)($in['work_type'] ?? '')]))
            ? (string)$in['work_type'] : (string)$r['work_type'],
        'start_date' => (string)($in['start_date'] ?? ''), 'end_date' => (string)($in['end_date'] ?? ''),
        'positions' => max(1, (int)($in['positions'] ?? 1)),
        'rate_min' => max(0, (float)($in['rate_min'] ?? 0)), 'rate_max' => max(0, (float)($in['rate_max'] ?? 0)),
        'rate_unit' => (string)($in['rate_unit'] ?? $r['rate_unit']),
        'description' => trim((string)($in['description'] ?? '')),
    ];
    if ($f['rate_max'] > 0 && $f['rate_min'] > $f['rate_max']) return [false, 'The lowest rate is above the highest.'];
    if ($f['end_date'] !== '' && $f['start_date'] !== '' && $f['end_date'] < $f['start_date']) return [false, 'The end date is before the start.'];
    $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($f)));
    db()->prepare("UPDATE cx_requirements SET $set, updated_at=? WHERE id=?")->execute(array_merge(array_values($f), [date('c'), (int)$id]));
    $changed = [];
    foreach ($f as $k => $v) if ((string)$v !== (string)($r[$k] ?? '')) $changed[] = $k;
    if (function_exists('act_log') && $changed) try { act_log('CX_REQUIREMENT', (int)$id, 'SYSTEM', 'Details changed: ' . implode(', ', $changed)); } catch (Throwable $e) {}
    return [true, $changed ? 'Requirement updated.' : 'Nothing was changed.'];
}

// ============================================================================
//  CLOSING DATE — owner, 11 Nov 2026: "Should an open requirement close by itself
//  after some days with no award? … there shall be some mechanism, research with
//  respect to real world scenario."
//
//  What the job boards do (Upwork, ArtStation, most job sites): a posting stays
//  open for a fixed window (commonly 30 days), the poster is reminded shortly
//  before it ends and may extend it, and an untouched posting then closes so the
//  board does not fill with dead jobs and applicants are not left waiting.
//  Here, from both sides:
//    • the client / desk: applications close the day before the work starts, or
//      after the open window (default 30 days) if that is sooner or there is no
//      start date; a reminder comes a few days before (default 3) with a one-tap
//      extend; it can be changed any time while open;
//    • the professional: a job nobody has looked at closes as EXPIRED and every
//      open application is closed, instead of waiting at "Applied" for ever;
//    • a job where the client is already shortlisting is NEVER closed by the
//      clock — they are choosing; they get a "decision due" reminder instead.
//  Uses only the existing OPEN → EXPIRED transition.
// ============================================================================
function cx_req_open_days()   { return max(1, min(365, (int)(function_exists('setting_get') ? setting_get('cx_req_open_days', 30) : 30))); }
function cx_req_remind_days() { return max(0, min(30, (int)(function_exists('setting_get') ? setting_get('cx_req_remind_days', 3) : 3))); }
function cx_req_closes_default($req, $today = null, $fromPosting = false) {
    $today = $today ?: date('Y-m-d');
    $from = substr((string)(($req['posted_at'] ?? '') ?: $today), 0, 10);
    if ($from < $today && !$fromPosting) $from = $today;
    $close = date('Y-m-d', strtotime($from . ' +' . cx_req_open_days() . ' days'));
    $start = substr(trim((string)($req['start_date'] ?? '')), 0, 10);
    if ($start !== '') {
        $dayBefore = date('Y-m-d', strtotime($start . ' -1 day'));
        if (($dayBefore >= $today || $fromPosting) && $dayBefore < $close) $close = $dayBefore;
    }
    return $close;
}
/** Days until applications close (negative once past), or null when no date. */
function cx_req_days_left($req, $today = null) {
    $c = trim((string)($req['closes_on'] ?? ''));
    if ($c === '') return null;
    return (int) round((strtotime($c) - strtotime($today ?: date('Y-m-d'))) / 86400);
}
/** Move the closing date (while the job is open or being shortlisted). [ok,msg] */
function cx_requirement_set_closes_on($id, $date, $who = '') {
    $r = cx_requirement_get($id); if (!$r) return [false, 'Requirement not found.'];
    if (!in_array(strtoupper((string)$r['status']), ['OPEN', 'SHORTLISTING'], true)) return [false, 'Only an open requirement has a closing date to change.'];
    $d = substr(trim((string)$date), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d < date('Y-m-d')) return [false, 'Choose a closing date from today onwards.'];
    if ($d > date('Y-m-d', strtotime('+365 days'))) return [false, 'A requirement can stay open for up to a year.'];
    db()->prepare("UPDATE cx_requirements SET closes_on=?, updated_at=? WHERE id=?")->execute([$d, date('c'), (int)$id]);
    if (function_exists('act_log')) try { act_log('CX_REQUIREMENT', (int)$id, 'SYSTEM', 'Applications now close on ' . $d . ($who !== '' ? ' (by ' . $who . ')' : '')); } catch (Throwable $e) {}
    return [true, 'Applications now close on ' . (function_exists('fdate') ? fdate($d, $d) : $d) . '.'];
}
function cx_requirement_extend($id, $days, $who = '') {
    $r = cx_requirement_get($id); if (!$r) return [false, 'Requirement not found.'];
    $base = max(date('Y-m-d'), (string)($r['closes_on'] ?: date('Y-m-d')));
    return cx_requirement_set_closes_on($id, date('Y-m-d', strtotime($base . ' +' . max(1, (int)$days) . ' days')), $who);
}
function cx_req_notify_client($req, $event, $title) {
    if ((int)($req['poster_party_id'] ?? 0) <= 0 || !function_exists('cvp_notify_once')) return false;
    try { if (function_exists('cvp_migrate')) cvp_migrate();
          return (bool) cvp_notify_once('CLIENT', (int)$req['poster_party_id'], $event, 'cx_requirement', (int)$req['id'], $title, '/portal/hire-req?id=' . (int)$req['id']); }
    catch (Throwable $e) { return false; }
}
/** The daily sweep. Returns ['dated'=>, 'reminded'=>, 'expired'=>, 'decide'=>]. */
function cx_req_expiry_sweep($today = null) {
    connect_market_migrate();
    $today = $today ?: date('Y-m-d');
    $out = ['dated' => 0, 'reminded' => 0, 'expired' => 0, 'decide' => 0];
    $rows = ops_all("SELECT * FROM cx_requirements WHERE status IN ('OPEN','SHORTLISTING')") ?: [];
    foreach ($rows as $r) {
        //  A requirement posted before closing dates existed gets one now, with at
        //  least a week's notice — nothing closes the day this arrives.
        if (trim((string)$r['closes_on']) === '') {
            $c = max(cx_req_closes_default($r, $today, true), date('Y-m-d', strtotime($today . ' +7 days')));
            db()->prepare("UPDATE cx_requirements SET closes_on=? WHERE id=?")->execute([$c, (int)$r['id']]);
            $out['dated']++; continue;
        }
        $left = cx_req_days_left($r, $today);
        $st = strtoupper((string)$r['status']);
        $ref = (string)($r['ref_code'] ?: ('#' . $r['id']));
        if ($st === 'OPEN' && $left < 0) {
            if (!cx_requirement_transition((int)$r['id'], 'EXPIRED')) continue;
            foreach (cx_applications_for((int)$r['id']) as $a) {
                $as = strtoupper((string)$a['status']);
                if (in_array($as, ['APPLIED', 'SHORTLISTED', 'OFFERED'], true)) cx_application_transition((int)$a['id'], $as === 'OFFERED' ? 'WITHDRAWN' : 'REJECTED');
            }
            if (function_exists('act_log')) try { act_log('CX_REQUIREMENT', (int)$r['id'], 'SYSTEM', 'Closed as expired — applications closed on ' . $r['closes_on'] . ' with nobody shortlisted'); } catch (Throwable $e) {}
            cx_req_notify_client($r, 'MKT_REQ_EXPIRED', $ref . ' "' . $r['title'] . '" closed — nobody was shortlisted by ' . $r['closes_on'] . '. Duplicate it to post again.');
            $out['expired']++;
        } elseif ($st === 'SHORTLISTING' && $left < 0) {
            if (cx_req_notify_client($r, 'MKT_REQ_DECIDE', $ref . ' "' . $r['title'] . '" — applications have closed; award it, or extend the closing date.')) $out['decide']++;
        } elseif ($left >= 0 && $left <= cx_req_remind_days()) {
            if (cx_req_notify_client($r, 'MKT_REQ_CLOSING', $ref . ' "' . $r['title'] . '" — applications close ' . ($left === 0 ? 'today' : 'in ' . $left . ' day' . ($left === 1 ? '' : 's')) . '. Shortlist, or extend it.')) $out['reminded']++;
        }
    }
    return $out;
}
/** The same sweep, at most once an hour, for installs whose scheduler is not set. */
function cx_req_expiry_sweep_maybe() {
    if (!function_exists('setting_get') || !function_exists('setting_set')) return;
    $last = (int) setting_get('cx_req_sweep_at', 0);
    if ($last > time() - 3600) return;
    setting_set('cx_req_sweep_at', (string) time());
    try { cx_req_expiry_sweep(); } catch (Throwable $e) {}
}

/** Move a requirement to a new status if the transition is legal. */
function cx_requirement_transition($id, $to) {
    $r = cx_requirement_get($id); if (!$r) return false;
    $to = strtoupper((string)$to);
    if (!cx_req_can_transition($r['status'], $to)) return false;
    $extra = ''; $args = [$to, date('c')];
    if ($to === 'OPEN' && ($r['posted_at'] ?? '') === '') { $extra = ', posted_at=?'; $args[] = date('c'); }
    if ($to === 'OPEN' && trim((string)($r['closes_on'] ?? '')) === '') { $extra .= ', closes_on=?'; $args[] = cx_req_closes_default($r); }
    if (in_array($to, ['CLOSED', 'CANCELLED', 'EXPIRED'], true)) { $extra = ', closed_at=?'; $args[] = date('c'); }
    $args[] = (int)$id;
    db()->prepare("UPDATE cx_requirements SET status=?, updated_at=?$extra WHERE id=?")->execute($args);
    return true;
}

/** Record an application to a requirement (APPLIED). One per inspector per req. */
function cx_application_add($requirementId, array $in) {
    connect_market_migrate();
    $requirementId = (int)$requirementId;
    $inspectorId = (int)($in['inspector_id'] ?? 0);
    $partyId = (int)($in['applicant_party_id'] ?? 0);
    $proId = (int)($in['applicant_professional_id'] ?? 0);
    // One live application per applicant per requirement — keyed on the pool
    // inspector, the applying party (agency/vendor), or the self-listed
    // professional (a freelancer applying as themselves).
    if ($inspectorId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND inspector_id=?", [$requirementId, $inspectorId]) > 0) return 0;
    } elseif ($proId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_professional_id=?", [$requirementId, $proId]) > 0) return 0;
    } elseif ($partyId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_party_id=?", [$requirementId, $partyId]) > 0) return 0;
    }
    $name = trim((string)($in['applicant_name'] ?? ''));
    if ($name === '' && $inspectorId > 0) $name = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$inspectorId]);
    if ($name === '' && $proId > 0) $name = (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$proId]);
    db()->prepare("INSERT INTO cx_applications (requirement_id,inspector_id,applicant_party_id,applicant_professional_id,applicant_name,cover_note,proposed_rate,status,created_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?, 'APPLIED', ?,?,?)")
        ->execute([$requirementId, $inspectorId, $partyId, $proId, $name, trim((string)($in['cover_note'] ?? '')),
                   (float)($in['proposed_rate'] ?? 0), function_exists('user_name') ? user_name(current_user()) : '', date('c'), date('c')]);
    return (int)db()->lastInsertId();
}

function cx_application_get($id) { return ops_one("SELECT * FROM cx_applications WHERE id=?", [(int)$id]) ?: null; }

function cx_application_transition($id, $to) {
    $a = cx_application_get($id); if (!$a) return false;
    $to = strtoupper((string)$to);
    if (!cx_app_can_transition($a['status'], $to)) return false;
    db()->prepare("UPDATE cx_applications SET status=?, updated_at=? WHERE id=?")->execute([$to, date('c'), (int)$id]);
    return true;
}

/**
 * Award a requirement to one application: the application is accepted (from
 * SHORTLISTED or OFFERED it goes to ACCEPTED) and the requirement moves to
 * AWARDED. A no-op unless both moves are legal.
 */
function cx_requirement_award($requirementId, $applicationId) {
    $r = cx_requirement_get($requirementId); $a = cx_application_get($applicationId);
    if (!$r || !$a || (int)$a['requirement_id'] !== (int)$r['id']) return false;
    //  Owner, 10 Nov 2026 ("very very complex"): Award was hidden until a
    //  separate "Start shortlisting" button had been pressed, and its error then
    //  said "shortlist the application first". Awarding now walks the same legal
    //  steps itself — an open requirement moves to shortlisting, an applicant to
    //  shortlisted — so one press does what the person meant.
    if (strtoupper((string)$r['status']) === 'OPEN') { cx_requirement_transition($requirementId, 'SHORTLISTING'); $r = cx_requirement_get($requirementId); }
    if (!cx_req_can_transition($r['status'], 'AWARDED')) return false;
    // Bring the chosen application to ACCEPTED via any legal path.
    $st = strtoupper((string)$a['status']);
    if ($st === 'APPLIED') { cx_application_transition($applicationId, 'SHORTLISTED'); $st = 'SHORTLISTED'; }
    if ($st === 'SHORTLISTED') { cx_application_transition($applicationId, 'OFFERED'); $st = 'OFFERED'; }
    if ($st === 'OFFERED') cx_application_transition($applicationId, 'ACCEPTED');
    if (strtoupper((string)cx_application_get($applicationId)['status']) !== 'ACCEPTED') return false;
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, updated_at=? WHERE id=?")
        ->execute([(int)$applicationId, date('c'), (int)$requirementId]);
    //  The other applicants are told the place is filled, instead of waiting at
    //  "Applied" for ever: each still-open application is closed as not selected.
    foreach (cx_applications_for($requirementId) as $o) {
        if ((int)$o['id'] === (int)$applicationId) continue;
        if (in_array(strtoupper((string)$o['status']), ['APPLIED', 'SHORTLISTED', 'OFFERED'], true)) {
            $to = strtoupper((string)$o['status']) === 'OFFERED' ? 'WITHDRAWN' : 'REJECTED';
            if (cx_application_transition((int)$o['id'], $to) && $to === 'REJECTED')
                db()->prepare("UPDATE cx_applications SET closed_by_award=1 WHERE id=?")->execute([(int)$o['id']]);
        }
    }
    if (function_exists('act_log')) try { act_log('CX_REQUIREMENT', (int)$requirementId, 'SYSTEM', 'Awarded to ' . ($a['applicant_name'] ?? ('application #' . (int)$applicationId)) . '; other open applications closed as not selected'); } catch (Throwable $e) {}
    return true;
}

/**
 * REPLACEMENT — owner, 11 Nov 2026: "One person, one requirement award … if the
 * professional who is awarded the job either cancels or is unable to attend …
 * same can be awarded to the other professional."
 *
 * A job is awarded to ONE person. If that person withdraws, does not turn up or
 * cannot attend, the job is awarded to someone else — the requirement stays
 * AWARDED throughout (no new requirement status). What happens:
 *   • the live booking ends with the reason (no-show / withdrawn / cancelled)
 *     and how much was already worked — that part is still billed;
 *   • the person replaced: ACCEPTED → WITHDRAWN, with the time it happened;
 *   • the replacement: any applicant still in play, or one closed only because
 *     the job had been awarded (REJECTED → SHORTLISTED, then the normal path
 *     to ACCEPTED). An applicant the client turned down is not offered;
 *   • a new booking for what is left, at the replacement's own rate, linked to
 *     the one it replaces.
 * Returns [ok, message].
 */
function cx_replacement_candidates($requirementId) {
    $r = cx_requirement_get($requirementId); if (!$r) return [];
    $out = [];
    foreach (cx_applications_for($requirementId) as $a) {
        if ((int)$a['id'] === (int)$r['awarded_application_id']) continue;
        $st = strtoupper((string)$a['status']);
        if (in_array($st, ['APPLIED', 'SHORTLISTED', 'OFFERED'], true) || ($st === 'REJECTED' && (int)($a['closed_by_award'] ?? 0) === 1)) $out[] = $a;
    }
    return $out;
}
/** An awarded job whose booking ended early and has no live replacement yet. */
function cx_requirement_needs_replacement($req) {
    if (!is_array($req) || strtoupper((string)($req['status'] ?? '')) !== 'AWARDED' || !function_exists('connect_engage_for_requirement')) return false;
    $e = connect_engage_for_requirement((int)$req['id']);
    return $e && strtoupper((string)$e['status']) === 'CANCELLED';
}
/** May new people be put forward / invited to this job? Open ones, and an
 *  awarded one that needs a replacement. */
function cx_requirement_takes_applicants($req) {
    return is_array($req) && (in_array(strtoupper((string)($req['status'] ?? '')), ['OPEN', 'SHORTLISTING'], true) || cx_requirement_needs_replacement($req));
}
function cx_requirement_replace($requirementId, $newApplicationId, array $in = [], $who = '') {
    $r = cx_requirement_get($requirementId);
    if (!$r) return [false, 'Requirement not found.'];
    if (strtoupper((string)$r['status']) !== 'AWARDED') return [false, 'Only an awarded job can be given to someone else.'];
    $new = cx_application_get($newApplicationId);
    $ok = false;
    foreach (cx_replacement_candidates((int)$r['id']) as $c) if ((int)$c['id'] === (int)$newApplicationId) $ok = true;
    if (!$new || !$ok) return [false, 'Choose someone who applied for this job and is still available.'];
    $old = cx_application_get((int)$r['awarded_application_id']);
    $kind = strtoupper((string)($in['kind'] ?? ''));
    $kinds = function_exists('connect_engage_cancel_kinds') ? connect_engage_cancel_kinds() : [];
    if (!isset($kinds[$kind])) return [false, 'Say why the job is going to someone else.'];
    $reason = trim((string)($in['reason'] ?? ''));
    $worked = max(0, (float)($in['worked'] ?? 0));

    // 1. End the replaced person's booking, keeping what they worked.
    $eng = function_exists('connect_engage_for_requirement') ? connect_engage_for_requirement((int)$r['id']) : null;
    if ($eng && in_array(strtoupper((string)$eng['status']), ['BOOKED', 'ACTIVE'], true)) {
        connect_engage_cancel((int)$eng['id'], $kind, $reason, $who, $worked);
        $eng = connect_engage_for_requirement((int)$r['id']);
    } elseif ($eng && strtoupper((string)$eng['status']) === 'CANCELLED' && $worked > 0 && (float)$eng['worked_qty'] <= 0) {
        db()->prepare("UPDATE cx_engagements SET worked_qty=? WHERE id=?")->execute([min($worked, (float)$eng['quantity'] ?: $worked), (int)$eng['id']]);
        $eng = connect_engage_for_requirement((int)$r['id']);
    }
    // 2. The person replaced.
    if ($old && strtoupper((string)$old['status']) === 'ACCEPTED')
        db()->prepare("UPDATE cx_applications SET status='WITHDRAWN', replaced_at=?, updated_at=? WHERE id=?")->execute([date('c'), date('c'), (int)$old['id']]);
    // 3. The replacement, along the normal path.
    if (strtoupper((string)$new['status']) === 'REJECTED')
        db()->prepare("UPDATE cx_applications SET status='SHORTLISTED', closed_by_award=0, updated_at=? WHERE id=?")->execute([date('c'), (int)$new['id']]);
    $st = strtoupper((string)cx_application_get($newApplicationId)['status']);
    if ($st === 'APPLIED') { cx_application_transition($newApplicationId, 'SHORTLISTED'); $st = 'SHORTLISTED'; }
    if ($st === 'SHORTLISTED') { cx_application_transition($newApplicationId, 'OFFERED'); $st = 'OFFERED'; }
    if ($st === 'OFFERED') cx_application_transition($newApplicationId, 'ACCEPTED');
    if (strtoupper((string)cx_application_get($newApplicationId)['status']) !== 'ACCEPTED') return [false, 'The replacement could not be accepted.'];
    db()->prepare("UPDATE cx_requirements SET awarded_application_id=?, updated_at=? WHERE id=?")->execute([(int)$newApplicationId, date('c'), (int)$r['id']]);
    $oldName = (string)($old['applicant_name'] ?? 'the person awarded'); $newName = (string)($new['applicant_name'] ?? 'the replacement');
    if (function_exists('act_log')) try { act_log('CX_REQUIREMENT', (int)$r['id'], 'SYSTEM', 'Awarded to ' . $newName . ' in place of ' . $oldName . ' — ' . ($kinds[$kind] ?? $kind) . ($reason !== '' ? ': ' . $reason : '') . ($who !== '' ? ' (by ' . $who . ')' : '')); } catch (Throwable $e) {}

    // 4. A booking for what is left, at the replacement's own rate.
    $msg = $newName . ' is now awarded the job in place of ' . $oldName . '.';
    if ($eng && function_exists('connect_engage_save_for_requirement')) {
        $bases = connect_engage_bases(); $b = $bases[strtoupper((string)$eng['basis'])] ?? null;
        $left = !empty($b['needs_qty']) ? max(0, (float)$eng['quantity'] - (float)$eng['worked_qty']) : 0;
        if ($b && (empty($b['needs_qty']) || $left > 0)) {
            $start = max(date('Y-m-d'), substr((string)$eng['start_date'], 0, 10));
            $endD = (string)$eng['end_date'];
            if ($endD !== '' && $endD < $start) $endD = '';
            [$bok] = connect_engage_save_for_requirement((int)$r['id'], [
                'basis' => $eng['basis'], 'quantity' => $left, 'frequency_note' => $eng['frequency_note'],
                'start_date' => $start, 'end_date' => $endD,
                'rate' => function_exists('connect_bridge_rate') ? connect_bridge_rate(cx_requirement_get((int)$r['id']), cx_application_get($newApplicationId)) : 0,
                'rate_unit' => $eng['rate_unit'], 'rate_inclusive' => $eng['rate_inclusive'], 'voucher_cadence' => $eng['voucher_cadence'],
                'reimb_terms' => $eng['reimb_terms'], 'shift' => $eng['shift'], 'notes' => 'Replaces ' . $oldName,
            ]);
            $msg .= $bok ? ' A booking for what is left' . ($left > 0 ? ' (' . rtrim(rtrim(number_format($left, 2), '0'), '.') . ' ' . ($eng['rate_unit'] ?: 'day') . '(s))' : '') . ' is recorded at their rate — check the dates.' : ' Record their booking.';
        } else {
            $msg .= ' Record their booking.';
        }
    }
    return [true, $msg];
}

function cx_applications_for($requirementId) {
    return ops_all("SELECT * FROM cx_applications WHERE requirement_id=? ORDER BY id", [(int)$requirementId]) ?: [];
}
function cx_requirements_list($status = '') {
    if ($status !== '') return ops_all("SELECT * FROM cx_requirements WHERE status=? ORDER BY id DESC", [$status]) ?: [];
    return ops_all("SELECT * FROM cx_requirements ORDER BY id DESC") ?: [];
}
/** K2b — a poster party's own requirements (client portal / agency portal). */
function cx_requirements_for_party($partyId) {
    return ops_all("SELECT * FROM cx_requirements WHERE poster_party_id=? ORDER BY id DESC", [(int)$partyId]) ?: [];
}
/** K2b — the open board a supplier browses to apply. */
function cx_open_requirements($limit = 200) {
    cx_req_expiry_sweep_maybe();   // a job past its closing date is not shown as open
    return ops_all("SELECT * FROM cx_requirements WHERE status IN ('OPEN','SHORTLISTING') ORDER BY id DESC LIMIT " . max(1, (int)$limit)) ?: [];
}
/** K2b — has an applying party already applied to this requirement? */
function cx_party_applied($requirementId, $partyId) {
    return (int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_party_id=?", [(int)$requirementId, (int)$partyId]) > 0;
}
/** K2b — count of applications on a requirement (poster's list badge). */
function cx_applications_count($requirementId) {
    return (int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [(int)$requirementId]);
}
function cx_market_summary() {
    $c = function($w = '', $a = []) { try { return (int)ops_val("SELECT COUNT(*) FROM cx_requirements" . ($w ? " WHERE $w" : ''), $a); } catch (Throwable $e) { return 0; } };
    return [
        'total'  => $c(),
        'open'   => $c("status IN ('OPEN','SHORTLISTING')"),
        'draft'  => $c("status='DRAFT'"),
        'awarded'=> $c("status='AWARDED'"),
        'apps'   => (function(){ try { return (int)ops_val("SELECT COUNT(*) FROM cx_applications"); } catch (Throwable $e) { return 0; } })(),
    ];
}

/**
 * The single on/off switch for the whole Connect marketplace module — dissolved
 * into EXAACT, but cleanly optional. Default ON; an installation can turn the
 * marketplace off in one place (`connect_enabled` setting) without touching core
 * operations. Every Connect entry point (staff routes, portals, public passport)
 * respects it.
 */
// MILESTONE 9 — two questions, in the only safe order.
//
//   1. Has this company BOUGHT the marketplace?   — the entitlement engine
//   2. Has it switched the marketplace on?        — its own setting
//
// Asked here because this is the one function the whole subsystem already runs
// through: fifteen of the sixteen connect_*_can() gates consult it, the public
// front door, the freelancer portal and the organisation join page consult it,
// and the client portal's hiring tiles consult it. Putting the question anywhere
// else would mean repeating it thirty-four times and missing one.
//
// The setting keeps its old meaning — a company that owns the marketplace may
// still switch it off. What it can no longer do is switch ON something it has
// not bought, which is exactly how modules_off relates to the entitlement
// ceiling everywhere else in this application.
//
// A master user gets no exemption: connect_market_can() asks this BEFORE it asks
// is_master(), so the ordering that already existed now carries the entitlement.
function connect_enabled() {
    if (function_exists('licence_module_live') && !licence_module_live('connect')) return false;
    return function_exists('setting_get') ? setting_get('connect_enabled', '1') === '1' : true;
}

/** Staff gate — reuses existing helpers; introduces NO new permission. */
function connect_market_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Board: post a requirement + list requirements. */
function ops_connect_requirements($route, $method) {
    ops_require(connect_market_can(), 'The manpower marketplace desk is for coordinators, managers and admins.');
    connect_market_migrate();
    if ($method === 'GET') cx_req_expiry_sweep_maybe();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? 'create');
        if ($act === 'set_closing_rules') {   // Owner, 11 Nov 2026 — how long requirements stay open (master only)
            if (function_exists('is_master') && is_master() && function_exists('setting_set')) {
                $od = max(1, min(365, (int)($_POST['open_days'] ?? 30))); $rd = max(0, min(30, (int)($_POST['remind_days'] ?? 3)));
                setting_set('cx_req_open_days', (string)$od); setting_set('cx_req_remind_days', (string)$rd);
                flash('New requirements stay open for ' . $od . ' days; posters are reminded ' . $rd . ' day' . ($rd === 1 ? '' : 's') . ' before they close.');
            } else {
                flash('Only an administrator can change this.', 'error');
            }
            redirect('/connect-requirements');
        }
        if ($act === 'set_commission') {   // K21 — the platform's commission rate (master only)
            if (function_exists('is_master') && is_master() && function_exists('setting_set')) {
                $pct = (float)($_POST['commission_pct'] ?? 5);
                if ($pct < 0) $pct = 0; if ($pct > 100) $pct = 100;
                setting_set('connect_commission_pct', $pct);
                flash('Platform commission set to ' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%.');
            } else {
                flash('Only an administrator can change the platform commission.', 'error');
            }
            redirect('/connect-requirements');
        }
        if ($act === 'create' || $act === 'create_post') {
            if (trim((string)($_POST['title'] ?? '')) === '') { flash('Give the requirement a title.', 'error'); redirect('/connect-requirements'); }
            $id = cx_requirement_create($_POST, $act === 'create_post');
            flash($act === 'create_post' ? 'Requirement posted — it is now open for applications.' : 'Requirement saved as a draft.');
            // Gap-6 — non-blocking near-duplicate warning: this client may already have an
            // open requirement just like this one. Advisory only; the post still succeeds.
            if (function_exists('connect_requirement_duplicates')) {
                $dupes = connect_requirement_duplicates($_POST, $id);
                if ($dupes) {
                    $names = array_map(fn($d) => $d['ref_code'] . ($d['reasons'] ? ' (' . implode(', ', $d['reasons']) . ')' : ''), array_slice($dupes, 0, 3));
                    flash('Heads up — this looks like a near-duplicate of ' . implode('; ', $names) . '. Review before working both.', 'warning');
                }
            }
            redirect('/connect-requirement?id=' . $id);
        }
        redirect('/connect-requirements');
    }
    view('ops/connect_requirements', [
        'summary'    => cx_market_summary(),
        'rows'       => cx_requirements_list(),
        'sectors'    => function_exists('connect_tx_rows') ? connect_tx_rows('cx_sectors') : [],
        'disciplines'=> function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
        'partners'   => ops_all("SELECT id, COALESCE(NULLIF(display_name,''), legal_name) AS nm FROM business_partners WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY nm") ?: [],
        'commission' => function_exists('connect_commission_summary') ? connect_commission_summary() : null,
        'is_master'  => function_exists('is_master') && is_master(),
    ]);
    return true;
}

/** Detail: a requirement, its applications, and every lifecycle action. */
/** K21 — was this requirement posted by a CLIENT (vs an internal/agency job)?
 *  For a client-posted job the client reviews and approves the voucher in its
 *  portal, so the desk does not approve/pay it. */
function connect_requirement_client_posted($req) {
    $pid = (int)($req['poster_party_id'] ?? 0);
    if ($pid <= 0) return false;
    try { return (int)ops_val("SELECT COALESCE(is_client,0) FROM business_partners WHERE id=?", [$pid]) === 1; }
    catch (Throwable $e) { return false; }
}

/** K21 — serve one voucher supporting document to the desk (gated). */
function ops_connect_voucher_file() {
    ops_require(connect_market_can(), 'The manpower marketplace desk is for coordinators, managers and admins.');
    $row = function_exists('connect_engv_file_row') ? connect_engv_file_row((int)($_GET['id'] ?? 0)) : null;
    if (!$row) { http_response_code(404); echo 'Not found.'; return; }
    $bytes = base64_decode((string)$row['file_data']);
    if (function_exists('send_uploaded_file')) { send_uploaded_file($bytes, (string)$row['file_name'], (string)$row['mime']); return; }
    header('Content-Type: ' . ((string)$row['mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . rawurlencode((string)$row['file_name']) . '"');
    echo $bytes;
}

function ops_connect_requirement($method) {
    ops_require(connect_market_can(), 'The manpower marketplace desk is for coordinators, managers and admins.');
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'req_edit') {
            [$eok, $emsg] = cx_requirement_update($id, $_POST);
            flash($eok ? $emsg . (cx_applications_for($id) ? ' People who already applied see the new details.' : '') : $emsg, $eok ? 'success' : 'error');
        } elseif ($act === 'req_transition') {
            cx_requirement_transition($id, $_POST['to'] ?? '') ? flash('Requirement updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'req_extend') {
            $who = function_exists('user_name') ? user_name(current_user()) : '';
            [$xok, $xmsg] = ($_POST['closes_on'] ?? '') !== '' ? cx_requirement_set_closes_on($id, (string)$_POST['closes_on'], $who) : cx_requirement_extend($id, (int)($_POST['days'] ?? 7), $who);
            flash($xmsg, $xok ? 'success' : 'error');
        } elseif ($act === 'replace_award') {
            //  Owner, 11 Nov 2026 — the awarded person cannot do the job.
            [$rok, $rmsg] = cx_requirement_replace($id, (int)($_POST['application_id'] ?? 0), $_POST, function_exists('user_name') ? user_name(current_user()) : '');
            flash($rmsg, $rok ? 'success' : 'error');
        } elseif ($act === 'apply') {
            $newId = cx_application_add($id, $_POST);
            flash($newId ? 'Application recorded.' : 'That professional has already applied to this requirement.', $newId ? 'success' : 'error');
        } elseif ($act === 'app_transition') {
            $okT = cx_application_transition((int)($_POST['application_id'] ?? 0), $_POST['to'] ?? '');
            //  Shortlisting someone means shortlisting has started — the
            //  requirement follows, instead of needing its own button.
            if ($okT && strtoupper((string)($_POST['to'] ?? '')) === 'SHORTLISTED' && strtoupper((string)(cx_requirement_get($id)['status'] ?? '')) === 'OPEN')
                cx_requirement_transition($id, 'SHORTLISTING');
            $okT ? flash('Application updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'award') {
            cx_requirement_award($id, (int)($_POST['application_id'] ?? 0))
                ? flash('Awarded. Next: record the booking below, then send it to billing or approve the vouchers.')
                : flash('Could not award — this requirement is not open for awarding (it is ' . strtolower((string)(cx_requirement_get($id)['status'] ?? '')) . '), or that application was withdrawn or rejected.', 'error');
        } elseif ($act === 'rate' && function_exists('cx_rating_add')) {   // K9 — two-way rating
            $newId = cx_rating_add($id, (string)($_POST['direction'] ?? ''), $_POST);
            flash($newId ? 'Rating recorded.' : 'That rating could not be saved (already rated, or the engagement is not complete).', $newId ? 'success' : 'error');
        } elseif ($act === 'dispute_raise' && function_exists('cx_dispute_raise')) {   // K9b — raise a concern
            $newId = cx_dispute_raise($id, $_POST);
            flash($newId ? 'Concern raised.' : 'Give the concern a subject first.', $newId ? 'success' : 'error');
        } elseif ($act === 'dispute_transition' && function_exists('cx_dispute_transition')) {
            $dp = cx_dispute_get((int)($_POST['dispute_id'] ?? 0));
            if ($dp && (int)$dp['requirement_id'] === (int)$id)
                cx_dispute_transition((int)$dp['id'], $_POST['to'] ?? '', (string)($_POST['resolution'] ?? ''))
                    ? flash('Concern updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'terms_save' && function_exists('cx_terms_save')) {   // K10 — commercial term-sheet
            cx_terms_save($id, $_POST);
            flash('Commercial terms saved.');
        } elseif ($act === 'readiness_toggle' && function_exists('cx_readiness_set')) {   // K10 — site readiness
            cx_readiness_set($id, (string)($_POST['item_key'] ?? ''), !empty($_POST['checked']));
        } elseif ($act === 'send_to_billing' && function_exists('connect_engagement_billable')) {   // Award → invoice bridge
            $ev = connect_engagement_billable($id);
            flash($ev ? 'Engagement sent to billing — it is now a pending billable event for finance to invoice.'
                      : 'Could not send to billing — award the requirement and set a rate first.', $ev ? 'success' : 'error');
        } elseif ($act === 'deploy_to_ops' && function_exists('connect_deploy_from_engagement')) {   // Award → deployment bridge (PDSO)
            [$dok, $dmsg] = connect_deploy_from_engagement($id);
            flash($dmsg, $dok ? 'success' : 'error');
        } elseif ($act === 'duplicate' && function_exists('connect_requirement_duplicate')) {   // §49 — reuse a requirement's shape
            $nid = connect_requirement_duplicate($id);
            if ($nid) { flash('Duplicated as a new draft.'); redirect('/connect-requirement?id=' . (int)$nid); }
            flash('Could not duplicate.', 'error');
        } elseif ($act === 'position_add' && function_exists('cx_position_add')) {   // M10 — crew manifest
            cx_position_add($id, $_POST) ? flash('Position added to the crew.') : flash('Give the position a role.', 'error');
        } elseif ($act === 'position_delete' && function_exists('cx_position_delete')) {
            cx_position_delete((int)($_POST['position_id'] ?? 0), $id); flash('Position removed.');
        } elseif ($act === 'book_engagement' && function_exists('connect_engage_save_for_requirement')) {   // K20 — booking basis
            [$eok, $emsg] = connect_engage_save_for_requirement($id, $_POST);
            flash($emsg, $eok ? 'success' : 'error');
        } elseif ($act === 'engagement_status' && function_exists('connect_engage_set_status')) {
            [$eok, $emsg] = connect_engage_set_status((int)($_POST['engagement_id'] ?? 0), (string)($_POST['status'] ?? ''));
            flash($emsg, $eok ? 'success' : 'error');
        } elseif ($act === 'engv_raise' && function_exists('connect_engv_open_for_engagement')) {   // K21 — desk raises a voucher (on-roll inspector / bench, no portal)
            [$vok, $vmsg] = connect_engv_open_for_engagement((int)($_POST['engagement_id'] ?? 0), $_POST);
            flash($vmsg, $vok ? 'success' : 'error');
        } elseif ($act === 'engv_add_line' && function_exists('connect_engv_add_line')) {            // K21 — add a day/period to a desk voucher
            [$vok, $vmsg] = connect_engv_add_line((int)($_POST['voucher_id'] ?? 0), $_POST);
            flash($vmsg, $vok ? 'success' : 'error');
        } elseif ($act === 'engv_status' && function_exists('connect_engv_set_status')) {            // K21 — approve / reject / mark paid
            [$vok, $vmsg] = connect_engv_set_status((int)($_POST['voucher_id'] ?? 0), (string)($_POST['status'] ?? ''), function_exists('user_name') ? user_name(current_user()) : '');
            flash($vmsg, $vok ? 'success' : 'error');
        } elseif ($act === 'engv_add_file' && function_exists('connect_engv_file_add')) {            // K21 — attach a supporting document (desk-raised voucher)
            [$vok, $vmsg] = connect_engv_file_add((int)($_POST['voucher_id'] ?? 0), (int)($_POST['line_id'] ?? 0), $_FILES['file'] ?? null, 'staff', 0, function_exists('user_name') ? user_name(current_user()) : '');
            flash($vmsg, $vok ? 'success' : 'error');
        } elseif ($act === 'engv_del_file' && function_exists('connect_engv_file_delete')) {
            connect_engv_file_delete((int)($_POST['file_id'] ?? 0), (int)($_POST['voucher_id'] ?? 0)) ? flash('Document removed.') : flash('Could not remove that document.', 'error');
        }
        redirect('/connect-requirement?id=' . $id);
    }
    $req = cx_requirement_get($id);
    if (!$req) { flash('That requirement was not found.', 'error'); redirect('/connect-requirements'); }
    $open = in_array(strtoupper((string)$req['status']), ['OPEN', 'SHORTLISTING'], true);
    view('ops/connect_requirement', [
        'req'          => $req,
        'apps'         => cx_applications_for($id),
        'replace_candidates' => cx_replacement_candidates($id),
        'inspectors'   => ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY name") ?: [],
        'req_next'     => CX_REQ_TRANSITIONS[strtoupper((string)$req['status'])] ?? [],
        // K3 — ranked recommendations from the pool (only worth showing while open).
        // #6 — optional AI re-ranking when a provider is configured and ?ai=1 asked.
        'matches'      => ($open && function_exists('connect_match_for_requirement'))
                            ? (function () use ($req) {
                                $wantAi = ($_GET['ai'] ?? '') === '1' && function_exists('connect_match_ai_available') && connect_match_ai_available();
                                if ($wantAi && function_exists('connect_match_for_requirement_ranked')) {
                                    [$rows, $used] = connect_match_for_requirement_ranked($req, 6, true);
                                    $GLOBALS['__cx_ai_used'] = $used; return $rows;
                                }
                                return connect_match_for_requirement($req, 6);
                              })() : [],
        'ai_available' => function_exists('connect_match_ai_available') && connect_match_ai_available(),
        'ai_used'      => $GLOBALS['__cx_ai_used'] ?? false,
        // #7 — agency bench people allocated to this requirement (fulfilment view).
        'bench_allocs' => function_exists('connect_bench_allocs_for_requirement') ? connect_bench_allocs_for_requirement($id) : [],
        // K21 — a client-posted job is reviewed/approved by the CLIENT in its
        // portal, so the desk shows the vouchers read-only (no approve/pay here).
        'client_posted' => connect_requirement_client_posted($req),
        // K20 — the booking/engagement basis once awarded (man-days / months / …).
        'engagement'   => ($eng = function_exists('connect_engage_for_requirement') ? connect_engage_for_requirement($id) : null),
        'engage_bases' => function_exists('connect_engage_bases') ? connect_engage_bases() : [],
        // K21 — the vouchers raised against that engagement (desk approve/pay + on-roll raise).
        'engv_vouchers' => ($eng && function_exists('connect_engv_for_engagement')) ? connect_engv_for_engagement((int)$eng['id']) : [],
        'engv_heads'    => function_exists('connect_engv_expense_heads') ? connect_engv_expense_heads() : [],
        // K21 — supporting documents per voucher, so the approver can verify receipts.
        'engv_files'    => ($eng && function_exists('connect_engv_files'))
            ? array_reduce(connect_engv_for_engagement((int)$eng['id']), function ($m, $vv) { $m[(int)$vv['id']] = connect_engv_files((int)$vv['id']); return $m; }, [])
            : [],
        // K9 — two-way ratings once the engagement is awarded/closed.
        'can_rate'     => function_exists('cx_rating_allowed') && cx_rating_allowed($req),
        'ratings'      => function_exists('cx_ratings_for_requirement') ? cx_ratings_for_requirement($id) : [],
        // K9b — disputes raised on this engagement.
        'disputes'     => function_exists('cx_disputes_for_requirement') ? cx_disputes_for_requirement($id) : [],
        // K10 — commercial terms (F1) + site-readiness verdict (F3).
        'terms'        => function_exists('cx_terms_get') ? cx_terms_get($id) : null,
        'terms_fields' => function_exists('cx_terms_fields') ? cx_terms_fields() : [],
        'readiness'    => function_exists('cx_readiness_get') ? cx_readiness_get($id) : [],
        'readiness_items' => function_exists('cx_readiness_items') ? cx_readiness_items() : [],
        'readiness_score' => function_exists('cx_readiness_score') ? cx_readiness_score($id) : null,
        // K12 — Operations Advisor verdict (delay risk + what to do).
        'advisor'      => function_exists('connect_advisor_for_requirement') ? connect_advisor_for_requirement($req) : null,
        // Bridge — the billable event for this awarded engagement (if sent to billing).
        'billable'     => function_exists('connect_engagement_billable_row') ? connect_engagement_billable_row($id) : null,
        // Bridge — the Operations deployment (PDSO deputation job) for this award.
        'deploy'       => function_exists('connect_deploy_row_for_requirement') ? connect_deploy_row_for_requirement($id) : null,
        // M10 — crew manifest (positions) + rollup.
        'positions'    => function_exists('cx_positions_for') ? cx_positions_for($id) : [],
        'crew'         => function_exists('cx_crew_summary') ? cx_crew_summary($id) : null,
        'disciplines'  => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
    ]);
    return true;
}
