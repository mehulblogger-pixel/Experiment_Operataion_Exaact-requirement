<?php
// ============================================================================
//  RECRUITMENT & WORKFORCE — Command Centre  (Phase 1, additive & read-only)
//
//  A single landing that answers, from data that ALREADY exists, the three
//  questions a recruiter / coordinator / manager opens the module to ask:
//     · Today   — what needs action right now
//     · Risks   — what is slipping
//     · Opportunities — what we can act on before recruiting externally
//
//  ZERO-BREAK: this file adds a new route (/recruitment) and reads existing
//  tables (requisitions, candidates, jobs, inspector_certs, inspector_day_status).
//  It creates no tables, changes no schema, and touches no existing handler.
//  Every query is guarded so a table/column missing on an older install makes a
//  card quietly read zero rather than taking the page down.
// ============================================================================

// ---- Phase 2 vocab (work models, shifts, billing basis) --------------------
const REQ_WORK_MODELS = ['DEPUTATION'=>'Deputation (to client site)', 'SPOT'=>'Spot / call-out', 'CONTRACT'=>'Fixed-term contract', 'PERMANENT'=>'Permanent'];
const REQ_SHIFTS      = ['GENERAL'=>'General', 'DAY'=>'Day', 'NIGHT'=>'Night', 'ROTATING'=>'Rotating', 'FLEX'=>'Flexible'];
const REQ_RATE_BASIS  = ['MONTHLY'=>'Per month', 'MANMONTH'=>'Per man-month', 'MANDAY'=>'Per man-day', 'DAILY'=>'Per day', 'FIXED'=>'Fixed (whole order)'];

// How WE source the person — the cost side. Each model builds the monthly cost
// per person from different heads, which is why a flat "cost/person" was never
// enough to compare a sub-contract agency against a manpower-supply agency.
const REQ_SOURCING_MODELS = [
    'OWN_PAYROLL'     => 'Own payroll / asset',
    'MANPOWER_AGENCY' => 'Manpower supply agency',
    'SUBCON_AGENCY'   => 'Third-party (sub-contract) agency',
    'FREELANCER'      => 'Freelancer / consultant',
];

// Additive, nullable columns that enrich a requirement (Phase 2). Never renames
// or drops anything — a requisition raised before this still loads and saves.
function req_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $cols = [
        // Client & contact (client_id links the CRM master — no second client store)
        ['client_id','INT NULL'], ['contact_name',"VARCHAR(150) DEFAULT ''"],
        ['contact_email',"VARCHAR(200) DEFAULT ''"], ['contact_phone',"VARCHAR(60) DEFAULT ''"],
        ['contract_ref',"VARCHAR(120) DEFAULT ''"],
        // Position
        ['quantity','INT DEFAULT 1'], ['discipline',"VARCHAR(120) DEFAULT ''"], ['category',"VARCHAR(120) DEFAULT ''"],
        // M3 — the canonical Department relationship, alongside the free-text
        // column rather than instead of it (see docs/phase2/M3-REQUISITION-STRUCTURE.md).
        ['department_id','INT NULL'],
        ['skills',"VARCHAR(400) DEFAULT ''"], ['qualification',"VARCHAR(200) DEFAULT ''"],
        ['experience_min',"DECIMAL(5,1) DEFAULT 0"], ['relevant_experience',"VARCHAR(200) DEFAULT ''"],
        // Deployment
        ['start_date',"VARCHAR(20) DEFAULT ''"], ['end_date',"VARCHAR(20) DEFAULT ''"],
        ['duration_months',"DECIMAL(6,2) DEFAULT 0"], ['duty_hours',"VARCHAR(40) DEFAULT ''"],
        ['shift',"VARCHAR(40) DEFAULT ''"], ['work_model',"VARCHAR(30) DEFAULT ''"],
        ['deploy_location',"VARCHAR(160) DEFAULT ''"],
        // Several locations can be needed against one requirement — one per line.
        // Received CVs are then tagged to one of them.
        ['locations',"TEXT"],
        ['prov_travel','INT DEFAULT 0'],
        ['prov_accommodation','INT DEFAULT 0'], ['prov_food','INT DEFAULT 0'], ['other_allowances',"VARCHAR(300) DEFAULT ''"],
        // Selection
        ['sel_client_interview','INT DEFAULT 0'], ['sel_tech_interview','INT DEFAULT 0'],
        ['sel_hr_interview','INT DEFAULT 0'], ['client_approval_req','INT DEFAULT 0'], ['training_req','INT DEFAULT 0'],
        // Compliance
        ['cmp_medical','INT DEFAULT 0'], ['cmp_pcc','INT DEFAULT 0'], ['cmp_gate_pass','INT DEFAULT 0'],
        ['cmp_safety','INT DEFAULT 0'], ['cmp_certification','INT DEFAULT 0'], ['documents_note',"VARCHAR(400) DEFAULT ''"],
        // Commercial
        ['billing_rate',"DECIMAL(14,2) DEFAULT 0"], ['rate_basis',"VARCHAR(20) DEFAULT 'MONTHLY'"],
        ['target_margin',"DECIMAL(6,2) DEFAULT 0"], ['negotiation_floor',"DECIMAL(14,2) DEFAULT 0"],
        ['expected_revenue',"DECIMAL(16,2) DEFAULT 0"], ['expected_profit',"DECIMAL(16,2) DEFAULT 0"],
        // Cost build-up — how WE source this person, and the heads that make up
        // the monthly cost. Kept per requirement so the same screen shows the
        // costing for a third-party sub-contract agency, a manpower-supply
        // agency, own payroll or a freelancer. All additive & nullable.
        ['sourcing_model',"VARCHAR(24) DEFAULT ''"],
        ['cost_wage',"DECIMAL(14,2) DEFAULT 0"],          // base wage / salary (or the sub-contractor's lump rate) per month
        ['cost_statutory_pct',"DECIMAL(6,2) DEFAULT 0"],  // PF/ESIC/bonus/leave, as % of wage
        ['cost_agency_pct',"DECIMAL(6,2) DEFAULT 0"],     // manpower-agency service fee / markup, as %
        ['cost_reimburse',"DECIMAL(14,2) DEFAULT 0"],     // monthly reimbursables (travel/accommodation/food/PPE)
        ['cost_oneoff',"DECIMAL(14,2) DEFAULT 0"],        // one-time per person (medical/PCC/training/mobilisation)
    ];
    foreach ($cols as $c) ensure_column('requisitions', $c[0], $c[1]);
    //  WHAT KIND OF TEAM MEMBER THIS REQUIREMENT IS FOR — decided here, at the
    //  requirement, and confirmed again when somebody is accepted.
    //
    //  DELIBERATELY NULLABLE AND WITHOUT A DEFAULT. `inspectors.team_role`
    //  carries DEFAULT 'FIELD', and that default is precisely the problem: a
    //  recruited person was classified as a deployable field inspector because
    //  nobody had said otherwise, not because anybody decided it. NULL here
    //  means "not decided yet", which acceptance can detect and ask about. A
    //  default would make "not decided" indistinguishable from "decided FIELD",
    //  and the question could never be asked again.
    ensure_column('requisitions', 'team_role', "VARCHAR(10) NULL");
    // Track the source paperwork against the requirement.
    ensure_column('requisitions', 'quotation_ref', "VARCHAR(80) DEFAULT ''");
    // 1d — PO reference and Contract number are DIFFERENT things, so they get
    // separate boxes. contract_ref becomes the contract number; po_ref is the client's PO.
    ensure_column('requisitions', 'po_ref', "VARCHAR(120) DEFAULT ''");
    // 1f — who provides each facility: '' = not applicable, 'US' = we provide,
    // 'CLIENT' = the client provides. Replaces the old us-only booleans and adds
    // local conveyance. The legacy prov_* booleans are still written for compatibility.
    ensure_column('requisitions', 'prov_food_by',   "VARCHAR(10) DEFAULT ''");
    ensure_column('requisitions', 'prov_accom_by',  "VARCHAR(10) DEFAULT ''");
    ensure_column('requisitions', 'prov_travel_by', "VARCHAR(10) DEFAULT ''");
    ensure_column('requisitions', 'prov_local_by',  "VARCHAR(10) DEFAULT ''");
    // The structured, meaningful code carries office/client/month/year + two
    // running numbers, so it can be longer than the old 30-char field. Widening
    // is a no-op on SQLite (no fixed length) and best-effort on MySQL.
    foreach (['requisitions.req_code', 'candidates.cand_code'] as $tc) {
        [$t, $c] = explode('.', $tc);
        try { db()->exec("ALTER TABLE $t MODIFY $c VARCHAR(70)"); } catch (Throwable $e) { /* sqlite / already wide */ }
    }
    req_groups_migrate();
}

// 1c — deployment GROUPS on a requisition. One requisition can depute several
// people who report to DIFFERENT persons at DIFFERENT sites (e.g. 8 inspectors:
// 3 under A, 3 under B, 2 under C). Each group is a headcount + a reporting contact
// (picked from the client's own contacts, or typed if not on file) + an optional
// site. The requisition's total quantity is the sum of the group headcounts.
function req_groups_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS requisition_groups (
            id $pk, requisition_id INT, seq INT DEFAULT 0, headcount INT DEFAULT 1,
            report_contact_id INT NULL, report_name VARCHAR(150) DEFAULT '',
            report_email VARCHAR(200) DEFAULT '', report_phone VARCHAR(60) DEFAULT '',
            site VARCHAR(200) DEFAULT '', notes VARCHAR(300) DEFAULT '')");
        if (function_exists('act_index')) act_index('requisition_groups', 'idx_rg_req', '(requisition_id)');
    } catch (Throwable $e) { /* never break boot */ }
}

// The groups on a requisition, resolving the reporting contact's name from the
// client's contact record when one is linked (else the typed name).
function req_groups($reqId) {
    req_groups_migrate();
    $rows = ops_all("SELECT g.*, c.name c_name, c.designation c_desig, c.mobile c_mobile, c.email c_email
                     FROM requisition_groups g LEFT JOIN partner_contacts c ON c.id=g.report_contact_id
                     WHERE g.requisition_id=? ORDER BY g.seq, g.id", [(int)$reqId]) ?: [];
    foreach ($rows as &$r) {
        $r['report_display'] = $r['report_contact_id'] ? (string)$r['c_name'] : (string)$r['report_name'];
        $r['report_phone_display'] = $r['report_contact_id'] ? (string)$r['c_mobile'] : (string)$r['report_phone'];
    }
    return $rows;
}
function req_groups_total($reqId) {
    req_groups_migrate();
    return (int) ops_val("SELECT COALESCE(SUM(headcount),0) FROM requisition_groups WHERE requisition_id=?", [(int)$reqId]);
}

// Replace-all save from the posted arrays (group_headcount[], group_contact_id[],
// group_report_name[]/email/phone, group_site[], group_notes[]). Empty rows (no
// headcount and no contact) are skipped. Returns the total headcount saved.
function req_groups_save($reqId, array $b) {
    req_groups_migrate();
    $reqId = (int)$reqId; if (!$reqId) return 0;
    $pdo = db();
    $hc   = (array)($b['group_headcount'] ?? []);
    $cid  = (array)($b['group_contact_id'] ?? []);
    $rn   = (array)($b['group_report_name'] ?? []);
    $re   = (array)($b['group_report_email'] ?? []);
    $rp   = (array)($b['group_report_phone'] ?? []);
    $site = (array)($b['group_site'] ?? []);
    $note = (array)($b['group_notes'] ?? []);
    $pdo->prepare("DELETE FROM requisition_groups WHERE requisition_id=?")->execute([$reqId]);
    $ins = $pdo->prepare("INSERT INTO requisition_groups (requisition_id,seq,headcount,report_contact_id,report_name,report_email,report_phone,site,notes) VALUES (?,?,?,?,?,?,?,?,?)");
    $seq = 0; $total = 0;
    $n = max(count($hc), count($cid), count($rn), count($site));
    for ($i = 0; $i < $n; $i++) {
        $count = (int)($hc[$i] ?? 0);
        $contactId = (int)($cid[$i] ?? 0) ?: null;
        $name = trim((string)($rn[$i] ?? ''));
        $st   = trim((string)($site[$i] ?? ''));
        // Skip a row that carries nothing meaningful.
        if ($count <= 0 && !$contactId && $name === '' && $st === '') continue;
        $ins->execute([$reqId, $seq++, max(0, $count), $contactId, $name,
                       trim((string)($re[$i] ?? '')), trim((string)($rp[$i] ?? '')), $st, trim((string)($note[$i] ?? ''))]);
        $total += max(0, $count);
    }
    return $total;
}

// A short, readable code from a name: letters only, upper-cased, first few.
// "L&T" -> "LT", "Narmada Industries" -> "NAR". Falls back to 'NA'.
function recruit_shortcode($name, $len = 3) {
    $s = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$name));
    return $s === '' ? 'NA' : substr($s, 0, max(2, $len));
}

// The structured requirement code:
//   REQ / <office> / <client> / <YYMM> / C<client-req-no> / O<office-run-no>
// e.g. REQ/AMD/LNT/2608/C005/O047. Client 'NA' (and its own counter bucket)
// when no client is linked yet; the code stays stable afterwards.
function recruit_req_code($officeId, $clientId, $when = null) {
    $officeId = (int)$officeId; $clientId = (int)$clientId;
    $when = $when ?: date('Y-m-d');
    $ym = date('ym', strtotime($when));
    $off = $officeId ? trim((string)ops_val("SELECT code FROM offices WHERE id=?", [$officeId])) : '';
    if ($off === '' && $officeId) $off = recruit_shortcode(ops_val("SELECT name FROM offices WHERE id=?", [$officeId]));
    $off = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $off)) ?: 'HO';
    $cli = $clientId
        ? recruit_shortcode((string)ops_val("SELECT COALESCE(NULLIF(display_name,''),legal_name) FROM business_partners WHERE id=?", [$clientId]))
        : 'NA';
    $cliNo = $clientId
        ? (int)ops_val("SELECT COUNT(*) FROM requisitions WHERE client_id=?", [$clientId]) + 1
        : (int)ops_val("SELECT COUNT(*) FROM requisitions WHERE COALESCE(client_id,0)=0") + 1;
    $offNo = $officeId
        ? (int)ops_val("SELECT COUNT(*) FROM requisitions WHERE office_id=?", [$officeId]) + 1
        : (int)ops_val("SELECT COUNT(*) FROM requisitions WHERE COALESCE(office_id,0)=0") + 1;
    $code = sprintf('REQ/%s/%s/%s/C%03d/O%03d', $off, $cli, $ym, $cliNo, $offNo);
    $base = $code; $n = 1;
    while ((int)ops_val("SELECT COUNT(*) FROM requisitions WHERE req_code=?", [$code])) $code = $base . '-' . (++$n);
    return $code;
}

// The candidate code hangs off its requirement: <req-code>-CV<nn>, nn being the
// CV number against that requirement. Falls back to the old CV-series for a
// candidate raised against a legacy requisition with no structured code.
function recruit_cand_code($req) {
    $rc = trim((string)($req['req_code'] ?? ''));
    if ($rc === '' || strncmp($rc, 'REQ/', 4) !== 0)
        return function_exists('ops_next_code') ? ops_next_code('candidates', 'cand_code', 'CV') : 'CV-1';
    $n = (int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [(int)($req['id'] ?? 0)]) + 1;
    $code = sprintf('%s-CV%02d', $rc, $n);
    while ((int)ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code=?", [$code])) { $n++; $code = sprintf('%s-CV%02d', $rc, $n); }
    return $code;
}

// The additive Phase-2 field list (used by the save handler). Kept here so the
// form, the handler and the detail stay in step.
function req_extra_fields() {
    return ['client_id','contact_name','contact_email','contact_phone','contract_ref','po_ref',
        'quantity','discipline','category','skills','qualification','experience_min','relevant_experience',
        'start_date','end_date','duty_hours','shift','work_model','deploy_location',
        'prov_travel','prov_accommodation','prov_food','prov_food_by','prov_accom_by','prov_travel_by','prov_local_by','other_allowances',
        'sel_client_interview','sel_tech_interview','sel_hr_interview','client_approval_req','training_req',
        'cmp_medical','cmp_pcc','cmp_gate_pass','cmp_safety','cmp_certification','documents_note',
        'billing_rate','rate_basis','target_margin','negotiation_floor',
        // Cost build-up heads (sourcing-model aware).
        'sourcing_model','cost_wage','cost_statutory_pct','cost_agency_pct','cost_reimburse','cost_oneoff',
        // Phase 7 — ownership (Responsible 1 = recruiter, Responsible 2 = manager) + department.
        'recruiter_id','manager_id','department','department_id',
        // Auto job-description — free-text key responsibilities feed the generator.
        'responsibilities'];
}

// Duration in months from an explicit value, else derived from start/end dates.
function req_duration_months($b) {
    $m = (float)($b['duration_months'] ?? 0);
    if ($m > 0) return $m;
    $s = substr((string)($b['start_date'] ?? ''), 0, 10); $e = substr((string)($b['end_date'] ?? ''), 0, 10);
    if ($s !== '' && $e !== '' && strtotime($e) >= strtotime($s)) return round((strtotime($e) - strtotime($s)) / 86400 / 30.4, 2);
    return 0;
}

// Build the monthly cost per person from the cost heads, the way the chosen
// sourcing model actually bills. Deterministic and mirrored in the form's live
// preview. Returns the per-head lines, the monthly total and the one-off total.
//
//   Own payroll / asset : wage + wage×statutory% + reimbursables
//   Manpower agency     : (wage + wage×statutory%) × (1 + agency%) + reimbursables
//   Sub-contract agency : wage (their all-in lump rate) + reimbursables we carry
//   Freelancer          : wage (professional fee) + reimbursables  (no statutory)
function req_cost_buildup($b) {
    $model = strtoupper((string)($b['sourcing_model'] ?? ''));
    $wage  = max(0, (float)($b['cost_wage'] ?? 0));
    $statP = max(0, (float)($b['cost_statutory_pct'] ?? 0));
    $agncP = max(0, (float)($b['cost_agency_pct'] ?? 0));
    $reimb = max(0, (float)($b['cost_reimburse'] ?? 0));
    $oneoff= max(0, (float)($b['cost_oneoff'] ?? 0));

    $lines = [];
    $statutory = 0; $agency = 0;
    // Statutory applies to the two models where we carry the person on a roll.
    if (in_array($model, ['OWN_PAYROLL', 'MANPOWER_AGENCY'], true)) $statutory = $wage * $statP / 100;
    // Agency service fee applies only to a manpower-supply agency, on wage+statutory.
    if ($model === 'MANPOWER_AGENCY') $agency = ($wage + $statutory) * $agncP / 100;

    $wageLabel = $model === 'SUBCON_AGENCY' ? 'Sub-contractor rate' : ($model === 'FREELANCER' ? 'Professional fee' : 'Base wage / salary');
    $lines[] = ['k' => $wageLabel, 'v' => $wage];
    if ($statutory > 0) $lines[] = ['k' => 'Statutory & benefits (' . rtrim(rtrim(number_format($statP, 2), '0'), '.') . '%)', 'v' => $statutory];
    if ($agency > 0)    $lines[] = ['k' => 'Agency service fee (' . rtrim(rtrim(number_format($agncP, 2), '0'), '.') . '%)', 'v' => $agency];
    if ($reimb > 0)     $lines[] = ['k' => 'Reimbursables / month', 'v' => $reimb];

    $monthly = $wage + $statutory + $agency + $reimb;
    return ['model' => $model, 'lines' => $lines, 'monthly' => round($monthly, 2), 'oneoff' => round($oneoff, 2)];
}

// Expected revenue & profit from quantity × rate × duration vs monthly cost.
// Deterministic and mirrored in the form's live preview.
function req_commercials($b) {
    $qty   = max(1, (int)($b['quantity'] ?? 1));
    $rate  = (float)($b['billing_rate'] ?? 0);
    $basis = (string)($b['rate_basis'] ?? 'MONTHLY');
    $months = req_duration_months($b);
    // An unspecified duration must NOT zero out the P&L. A per-month/day rate with
    // no stated duration is quoted per month — show at least one month so the
    // Expected cost and Expected profit are real, not a misleading zero.
    if ($months <= 0) $months = 1;
    // Monthly cost per person: use the flat "Est. cost / person / month" the user
    // sees on the form (the live preview populates it from the cost build-up, and
    // a manual figure typed over it must win — what is shown is what is saved).
    // Fall back to the built-up figure only when the flat field is empty (e.g. a
    // save with JavaScript off).
    $bu = req_cost_buildup($b);
    $flatCost = (float)($b['budgeted_cost'] ?? 0);
    $cost  = $flatCost > 0 ? $flatCost : $bu['monthly'];
    $oneoff = $bu['oneoff'];
    $units = ($basis === 'MANDAY' || $basis === 'DAILY') ? round($months * 22) : $months;
    if ($basis === 'FIXED') $revenue = $qty * $rate;
    else                    $revenue = $qty * $rate * max($units, 0);
    $recurringCost = $qty * $cost * max($months, 0);
    $oneoffTotal   = $qty * $oneoff;
    $costTotal = $recurringCost + $oneoffTotal;
    $profit = $revenue - $costTotal;
    return ['revenue' => round($revenue, 2), 'cost' => round($costTotal, 2), 'profit' => round($profit, 2),
            'months' => $months, 'units' => $units, 'monthly_cost' => round($cost, 2),
            'oneoff_total' => round($oneoffTotal, 2), 'recurring_cost' => round($recurringCost, 2)];
}

// ===========================================================================
//  Phase 3 — candidate intelligence: duplicate detection & submission guard.
//  Read-only helpers; they never merge or delete anything on their own.
// ===========================================================================

// §11 — candidates that look like the SAME person (by mobile / email / name),
// each with a confidence and the reason(s). Sorted strongest first.
function cand_find_duplicates($b, $excludeId = 0) {
    $mobile = preg_replace('/\D+/', '', (string)($b['mobile'] ?? ''));
    $email  = strtolower(trim((string)($b['email'] ?? '')));
    $first  = strtolower(trim((string)($b['first_name'] ?? '')));
    $last   = strtolower(trim((string)($b['last_name'] ?? '')));
    if ($mobile === '' && $email === '' && ($first === '' || $last === '')) return [];
    try {
        $rows = ops_all("SELECT id, cand_code, first_name, middle_name, last_name, email, mobile, stage,
                                requisition_id, client_id,
                                (SELECT display_name FROM business_partners WHERE id=candidates.client_id) client_disp
                         FROM candidates WHERE id<>? ORDER BY id DESC LIMIT 500", [(int)$excludeId]) ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $conf = 0; $reasons = [];
        $rm = preg_replace('/\D+/', '', (string)$r['mobile']);
        if ($mobile !== '' && $rm !== '' && substr($rm, -10) === substr($mobile, -10)) { $conf = max($conf, 96); $reasons[] = 'same mobile'; }
        if ($email !== '' && strtolower(trim((string)$r['email'])) === $email) { $conf = max($conf, 94); $reasons[] = 'same email'; }
        $rf = strtolower(trim((string)$r['first_name'])); $rl = strtolower(trim((string)$r['last_name']));
        if ($first !== '' && $last !== '' && $rf === $first && $rl === $last) { $conf = max($conf, 72); $reasons[] = 'same name'; }
        elseif ($last !== '' && $rl === $last && $first !== '' && $rf !== '' && $rf[0] === $first[0]) { $conf = max($conf, 46); $reasons[] = 'similar name'; }
        if ($conf >= 46) { $r['confidence'] = $conf; $r['reasons'] = implode(' · ', $reasons); $out[] = $r; }
    }
    usort($out, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
    return array_slice($out, 0, 6);
}

// §12 — has this SAME person already been submitted to this SAME client?
function cand_submission_dupes($cand) {
    $clientId = (int)($cand['client_id'] ?? 0); if (!$clientId) return [];
    $mobile = preg_replace('/\D+/', '', (string)($cand['mobile'] ?? ''));
    $email  = strtolower(trim((string)($cand['email'] ?? '')));
    if ($mobile === '' && $email === '') return [];
    try {
        $rows = ops_all("SELECT id, cand_code, first_name, last_name, submitted_client_date, client_feedback, stage, mobile, email
                         FROM candidates WHERE client_id=? AND id<>? AND COALESCE(submitted_client_date,'')<>''
                         ORDER BY submitted_client_date DESC LIMIT 40", [$clientId, (int)($cand['id'] ?? 0)]) ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $rm = preg_replace('/\D+/', '', (string)$r['mobile']);
        $match = ($mobile !== '' && $rm !== '' && substr($rm, -10) === substr($mobile, -10))
              || ($email !== '' && strtolower(trim((string)$r['email'])) === $email);
        if ($match) $out[] = $r;
    }
    return array_slice($out, 0, 5);
}

// ===========================================================================
//  Phase 4 — intelligence (all deterministic & explainable; AI is opt-in and
//  human-approved). Nothing here writes data or makes a decision on its own.
// ===========================================================================

// Lower-cased token set from a free-text string (skills, keywords, disciplines).
function _rk_tokens($s) {
    $s = strtolower((string)$s);
    $parts = preg_split('/[,;\/\|\n]+|\s{2,}/', $s) ?: [];
    $out = [];
    foreach ($parts as $p) { $p = trim($p); if ($p !== '' && strlen($p) >= 2) $out[] = $p; }
    return $out;
}
// Does any needle token appear in the haystack text?
function _rk_hit($haystack, $needles) {
    $h = strtolower((string)$haystack);
    foreach ((array)$needles as $n) { $n = trim(strtolower((string)$n)); if ($n !== '' && strpos($h, $n) !== false) return true; }
    return false;
}

// §16 — explainable Workforce-Fit of a candidate against a requirement.
// Returns ['score'=>0..100, 'factors'=>[['label','state'=>ok|part|no,'note','wt'], …]].
function recruit_fit_score($cand, $req) {
    $F = [];
    $add = function ($label, $state, $note, $wt) use (&$F) { $F[] = ['label' => $label, 'state' => $state, 'note' => $note, 'wt' => $wt]; };
    $candText = strtolower(trim(($cand['trade_label'] ?? '') . ' ' . ($cand['skill_label'] ?? '') . ' ' . ($cand['cv_keywords'] ?? '') . ' ' . ($cand['designation'] ?? '')));

    // Designation (20)
    $rd = (string)($req['designation'] ?? ''); $cd = (string)($cand['designation'] ?? '');
    if ($rd !== '' && $cd !== '') $add('Designation', $rd === $cd ? 'ok' : 'no', $rd === $cd ? 'matches' : 'differs', 20);
    else $add('Designation', 'part', 'not specified', 20);

    // Discipline / trade (20)
    $disc = trim((string)($req['discipline'] ?? ''));
    if ($disc !== '') $add('Discipline', _rk_hit($candText, _rk_tokens($disc)) ? 'ok' : 'no', $disc, 20);
    else $add('Discipline', 'part', 'any', 20);

    // Skills / certifications (15)
    $sk = _rk_tokens($req['skills'] ?? '');
    if ($sk) { $hit = array_filter($sk, fn($t) => strpos($candText, $t) !== false);
        $add('Skills', count($hit) === count($sk) ? 'ok' : (count($hit) ? 'part' : 'no'), count($hit) . '/' . count($sk) . ' matched', 15); }
    else $add('Skills', 'part', 'none required', 15);

    // Experience (15)
    $need = (float)($req['experience_min'] ?? 0); $have = (float)($cand['experience_years'] ?? 0);
    if ($need > 0) $add('Experience', $have >= $need ? 'ok' : ($have >= $need - 1 ? 'part' : 'no'), $have . ' vs ' . $need . ' yrs', 15);
    else $add('Experience', 'ok', $have . ' yrs', 15);

    // Business unit (10)
    $rs = (string)($req['sbu'] ?? ''); $cs = (string)($cand['sbu'] ?? '');
    if ($rs !== '') $add('Business unit', $rs === $cs ? 'ok' : 'no', $rs === $cs ? 'same' : 'different', 10);
    else $add('Business unit', 'part', 'any', 10);

    // Location (10)
    $loc = trim((string)($req['deploy_location'] ?? '') . ' ' . (string)($req['project_site'] ?? ''));
    if (trim($loc) !== '' && trim((string)($cand['proposed_site'] ?? '')) !== '')
        $add('Location', _rk_hit($loc, _rk_tokens($cand['proposed_site'])) || _rk_hit($cand['proposed_site'], _rk_tokens($loc)) ? 'ok' : 'no', 'proposed vs site', 10);
    else $add('Location', 'part', 'flexible', 10);

    // Cost vs billing rate (10)
    $rate = (float)($cand['expected_rate'] ?? 0); $bill = (float)($req['billing_rate'] ?? 0);
    if ($rate > 0 && $bill > 0) $add('Cost', $rate <= $bill ? 'ok' : ($rate <= $bill * 1.1 ? 'part' : 'no'), 'expected vs bill rate', 10);
    else $add('Cost', 'part', 'not priced', 10);

    $score = 0; $max = 0;
    foreach ($F as $f) { $max += $f['wt']; $score += $f['wt'] * ($f['state'] === 'ok' ? 1 : ($f['state'] === 'part' ? 0.5 : 0)); }
    return ['score' => $max ? (int)round($score / $max * 100) : 0, 'factors' => $F];
}
function recruit_fit_band($s) { return $s >= 80 ? ['Strong', 'p-ok'] : ($s >= 55 ? ['Fair', 'p-warn'] : ['Weak', 'p-bad']); }

// P1b — the same explainable Workforce-Fit, but for a MARKETPLACE PROFESSIONAL
// (cx_professionals) against a recruitment requisition. So a recruiter filling a
// requisition automatically sees benched / verified people from the marketplace who
// fit — not only existing candidate rows. Same ['score','factors'] shape as
// recruit_fit_score(), so recruit_fit_band() and the fit UI render it unchanged.
function recruit_pro_fit_score($pro, $req) {
    $F = [];
    $add = function ($label, $state, $note, $wt) use (&$F) { $F[] = ['label' => $label, 'state' => $state, 'note' => $note, 'wt' => $wt]; };
    $proText = strtolower(trim(($pro['disciplines'] ?? '') . ' ' . ($pro['skills'] ?? '') . ' ' . ($pro['headline'] ?? '') . ' ' . ($pro['work_types'] ?? '')));

    // Discipline (25)
    $disc = trim((string)($req['discipline'] ?? ''));
    if ($disc !== '') $add('Discipline', _rk_hit($proText, _rk_tokens($disc)) ? 'ok' : 'no', $disc, 25);
    else $add('Discipline', 'part', 'any', 25);

    // Skills (20)
    $sk = _rk_tokens($req['skills'] ?? '');
    if ($sk) { $hit = array_filter($sk, fn($t) => strpos($proText, $t) !== false);
        $add('Skills', count($hit) === count($sk) ? 'ok' : (count($hit) ? 'part' : 'no'), count($hit) . '/' . count($sk) . ' matched', 20); }
    else $add('Skills', 'part', 'none required', 20);

    // Role / designation (15) — matched against the professional's headline/disciplines
    $rd = trim((string)($req['designation'] ?? ''));
    if ($rd !== '') $add('Role', _rk_hit($proText, _rk_tokens($rd)) ? 'ok' : 'no', $rd, 15);
    else $add('Role', 'part', 'not specified', 15);

    // Location (15) — pan-India pros fit anywhere; else match base city / preferred locations
    $loc = trim((string)($req['deploy_location'] ?? '') . ' ' . (string)($req['project_site'] ?? ''));
    $proLoc = trim((string)($pro['base_city'] ?? '') . ' ' . (string)($pro['preferred_locations'] ?? ''));
    if (!empty($pro['pan_india'])) $add('Location', 'ok', 'pan-India', 15);
    elseif (trim($loc) !== '' && $proLoc !== '')
        $add('Location', _rk_hit($loc, _rk_tokens($proLoc)) || _rk_hit($proLoc, _rk_tokens($loc)) ? 'ok' : 'no', 'base vs site', 15);
    else $add('Location', 'part', 'flexible', 15);

    // Availability (15)
    $av = strtoupper(trim((string)($pro['availability'] ?? '')));
    if ($av === 'AVAILABLE') $add('Availability', 'ok', 'available', 15);
    elseif (in_array($av, ['AVAILABLE_SOON', 'OPEN', 'BUSY'], true)) $add('Availability', 'part', strtolower($av), 15);
    elseif ($av === '') $add('Availability', 'part', 'unknown', 15);
    else $add('Availability', 'no', strtolower($av), 15);

    // Verification (10) — a proven professional is worth more confidence
    $vt = strtolower(trim((string)($pro['verification_tier'] ?? '')));
    if (in_array($vt, ['verified', 'id_verified', 'engaged'], true)) $add('Verification', 'ok', $vt, 10);
    elseif (in_array($vt, ['documented', 'document'], true)) $add('Verification', 'part', $vt, 10);
    else $add('Verification', 'part', $vt ?: 'registered', 10);

    // Rate (10) — the professional's floor day-rate vs the requisition's billing rate
    $rate = (float)($pro['day_rate_min'] ?? 0); $bill = (float)($req['billing_rate'] ?? 0);
    if ($rate > 0 && $bill > 0) $add('Rate', $rate <= $bill ? 'ok' : ($rate <= $bill * 1.1 ? 'part' : 'no'), 'floor vs bill rate', 10);
    else $add('Rate', 'part', 'not priced', 10);

    $score = 0; $max = 0;
    foreach ($F as $f) { $max += $f['wt']; $score += $f['wt'] * ($f['state'] === 'ok' ? 1 : ($f['state'] === 'part' ? 0.5 : 0)); }
    return ['score' => $max ? (int)round($score / $max * 100) : 0, 'factors' => $F];
}

// The ranked marketplace-professional shortlist for a requisition: active pros
// scored against it, kept at or above $min, strongest first, capped at $limit.
// Read-only; scores in memory (one pool read, no per-pro query).
function recruit_pro_pool($req, $limit = 5, $min = 55) {
    try { $pros = ops_all("SELECT id, name, headline, disciplines, skills, work_types, base_city, preferred_locations,
                                  pan_india, verification_tier, availability, day_rate_min, day_rate_max
                           FROM cx_professionals WHERE COALESCE(is_active,1)=1") ?: []; }
    catch (Throwable $e) { return []; }
    $out = [];
    foreach ($pros as $p) {
        $f = recruit_pro_fit_score($p, $req);
        if ($f['score'] >= $min) { $p['fit'] = $f; $out[] = $p; }
    }
    usort($out, fn($a, $b) => $b['fit']['score'] <=> $a['fit']['score']);
    return array_slice($out, 0, $limit);
}

// §18 — Requirement Health: is this vacancy on track to fill in time?
function recruit_req_health($req) {
    $id = (int)($req['id'] ?? 0);
    $qty = max(1, (int)($req['quantity'] ?? 1));
    $one = function ($sql, $a = []) { try { return (int)(ops_one($sql, $a)['n'] ?? 0); } catch (Throwable $e) { return 0; } };
    $filled     = $one("SELECT COUNT(*) n FROM candidates WHERE requisition_id=? AND stage IN ('OFFERED','ACCEPTED')", [$id]);
    $inPipe     = $one("SELECT COUNT(*) n FROM candidates WHERE requisition_id=? AND stage IN ('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','HOLD')", [$id]);
    $vacancies  = max(0, $qty - $filled);
    $start = substr((string)($req['start_date'] ?? ''), 0, 10);
    $days  = $start !== '' && function_exists('days_between') ? days_between(date('Y-m-d'), $start) : null;

    $score = 100; $reasons = []; $actions = [];
    if ($vacancies > 0) {
        if ($inPipe === 0)         { $score -= 55; $reasons[] = 'No candidates in the pipeline'; $actions[] = 'Start sourcing / activate an agency'; }
        elseif ($inPipe < $vacancies) { $score -= 30; $reasons[] = "$inPipe in pipeline for $vacancies vacancy(ies)"; $actions[] = 'Add more candidates for cover'; }
        else                        { $reasons[] = "$inPipe in pipeline"; }
        if ($days !== null) {
            if ($days < 0)      { $score -= 25; $reasons[] = 'Start date already passed'; $actions[] = 'Confirm revised start with client'; }
            elseif ($days <= 7) { $score -= 25; $reasons[] = "Mobilises in $days day(s)"; $actions[] = 'Fast-track shortlisting & documents'; }
            elseif ($days <= 21){ $score -= 10; $reasons[] = "Mobilises in $days day(s)"; }
        }
    } else { $reasons[] = 'All positions filled'; }
    $age = ($req['created_at'] ?? '') !== '' && function_exists('days_between') ? days_between(substr((string)$req['created_at'], 0, 10), date('Y-m-d')) : null;
    if ($vacancies > 0 && $age !== null && $age > 21 && $inPipe === 0) { $score -= 10; $reasons[] = "Open $age days with no pipeline"; }
    $score = max(0, min(100, $score));
    $band = $score >= 75 ? ['On track', 'p-ok'] : ($score >= 45 ? ['At risk', 'p-warn'] : ['Critical', 'p-bad']);
    if ($vacancies > 0 && !$actions) $actions[] = 'Keep the pipeline warm';
    return ['score' => $score, 'band' => $band, 'vacancies' => $vacancies, 'filled' => $filled, 'quantity' => $qty,
            'pipeline' => $inPipe, 'days_to_start' => $days, 'reasons' => $reasons, 'actions' => $actions];
}

// §17 — Deployment Readiness of a hired/offered candidate. Reuses the deputation
// mobilisation checklist (pdso_mob_readiness) when a deputation exists; otherwise
// derives from the candidate + the requirement's compliance requirements.
function recruit_deploy_readiness($cand, $req = null) {
    $items = [];
    $add = function ($label, $ok, $reqd = true) use (&$items) { $items[] = ['label' => $label, 'ok' => (bool)$ok, 'req' => (bool)$reqd]; };
    $stage = (string)($cand['stage'] ?? '');
    $add('Technical fit / shortlisted', in_array($stage, ['SHORTLISTED','INTERVIEW','OFFERED','ACCEPTED'], true));
    $add('Client approval', (($cand['interview_outcome'] ?? '') === 'SELECTED') || (($cand['client_feedback'] ?? '') === 'SHORTLISTED'));
    $add('CV on file', trim((string)($cand['cv_link'] ?? '')) !== '' || trim((string)($cand['cv_text'] ?? '')) !== '');
    $add('Credentials requested', !empty($cand['credential_requested']));
    // Requirement-driven compliance gates (only those the requirement demands).
    if ($req) {
        foreach ([['cmp_medical','Medical fitness'], ['cmp_pcc','Police verification'], ['cmp_gate_pass','Gate pass'],
                  ['cmp_safety','Safety induction'], ['cmp_certification','Certification']] as $c) {
            if (!empty($req[$c[0]])) $add($c[1], false);   // required by the role; unverified until an inspector record exists
        }
    }
    $add('Joining confirmed', $stage === 'ACCEPTED', true);
    // If already hired and on a deputation, prefer the live mobilisation checklist.
    if (!empty($cand['inspector_id']) && function_exists('pdso_mob_readiness')) {
        try {
            $job = ops_one("SELECT id FROM jobs WHERE inspector_id=? ORDER BY id DESC LIMIT 1", [(int)$cand['inspector_id']]);
            if ($job) { $mr = pdso_mob_readiness((int)$job['id']); if (($mr['total'] ?? 0) > 0) {
                $items[] = ['label' => 'Mobilisation checklist', 'ok' => !empty($mr['ready']), 'req' => true,
                            'note' => ($mr['required_done'] ?? 0) . '/' . ($mr['required'] ?? 0) . ' required done']; } }
        } catch (Throwable $e) {}
    }
    $reqItems = array_filter($items, fn($i) => $i['req']);
    $done = count(array_filter($reqItems, fn($i) => $i['ok']));
    $pct = count($reqItems) ? (int)round($done / count($reqItems) * 100) : 0;
    return ['pct' => $pct, 'done' => $done, 'total' => count($reqItems), 'ready' => $pct === 100, 'items' => $items];
}

// ---------------------------------------------------------------------------
//  RÉSUMÉ AUTO-EXTRACT for candidate intake. Reuses the marketplace CV engine
//  (connect_cv_extract_text + connect_cv_scan) that already reads txt/docx/pdf and
//  maps text to the skills/role taxonomy — we do NOT build a second parser. From a
//  résumé we prefill the reliable fields (name, e-mail, mobile, experience) and a
//  role/skills summary; the recruiter always reviews and saves. Nothing auto-creates.
// ---------------------------------------------------------------------------
function recruit_cv_autofill($text) {
    $text = (string)$text;
    $out = ['first_name'=>'','middle_name'=>'','last_name'=>'','email'=>'','mobile'=>'',
            'experience_years'=>'','remarks'=>'','cv_keywords'=>''];
    if (trim($text) === '') return $out;

    // E-mail (first match).
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) $out['email'] = strtolower($m[0]);
    // Mobile — an Indian 10-digit number, optional +91 / 0 prefix. First join digit
    // groups split by spaces or dashes ("98123 45678" → "9812345678") so a formatted
    // number still matches, then read the 10-digit core.
    $numNorm = preg_replace('/(?<=\d)[\s\-]+(?=\d)/', '', $text);
    if (preg_match('/(?:\+?91|\b0)?([6-9]\d{9})\b/', $numNorm, $m)) $out['mobile'] = $m[1];
    // Experience — the largest "N years / yrs" mentioned.
    if (preg_match_all('/(\d{1,2})\s*\+?\s*(?:years|yrs|year)\b/i', $text, $mm) && $mm[1]) $out['experience_years'] = (string)max(array_map('intval', $mm[1]));
    // Name — the first line that reads like a person's name (2–4 words, letters only).
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line); if ($line === '' || strlen($line) > 40) continue;
        if (stripos($line, 'resume') !== false || stripos($line, 'curriculum') !== false || stripos($line, 'vitae') !== false) continue;
        if (preg_match("/^[A-Za-z][A-Za-z.'\\-]+(?:\\s+[A-Za-z.'\\-]+){1,3}$/", $line)) {
            $parts = preg_split('/\s+/', $line);
            $out['first_name'] = $parts[0];
            $out['last_name']  = count($parts) > 1 ? $parts[count($parts)-1] : '';
            if (count($parts) > 2) $out['middle_name'] = implode(' ', array_slice($parts, 1, -1));
            break;
        }
    }
    // Taxonomy scan → primary role + skills + base city (the real, existing engine).
    $role = ''; $skills = []; $base = '';
    if (function_exists('connect_cv_scan')) {
        $scan = connect_cv_scan($text);
        foreach (($scan['expertise'] ?? []) as $node) {
            $kind = strtoupper((string)($node['kind'] ?? ''));
            if (($node['relation'] ?? '') === 'PRIMARY_ROLE' && $role === '') $role = (string)$node['name'];
            elseif ($kind === 'ROLE' && $role === '') $role = (string)$node['name'];
            if (in_array($kind, ['SKILL','METHOD','EQUIPMENT','CERTIFICATION','ACTIVITY','SYSTEM','SPECIALIZATION'], true)) $skills[] = (string)$node['name'];
        }
        if (!empty($scan['base_place']['name'])) $base = (string)$scan['base_place']['name'];
    }
    $skills = array_values(array_unique(array_filter($skills)));
    $bits = [];
    if ($role)   $bits[] = 'Role: ' . $role;
    if ($skills) $bits[] = 'Skills: ' . implode(', ', array_slice($skills, 0, 20));
    if ($base)   $bits[] = 'Base: ' . $base;
    if ($bits)   $out['remarks'] = 'From résumé — ' . implode(' · ', $bits);
    $out['cv_keywords'] = function_exists('cv_extract_keywords') ? cv_extract_keywords($text) : '';
    return $out;
}

/** A short human line saying what the résumé extract filled in (for the banner). */
function recruit_cv_autofill_summary($a) {
    $got = [];
    if (!empty($a['first_name']))      $got[] = 'name';
    if (!empty($a['email']))           $got[] = 'e-mail';
    if (!empty($a['mobile']))          $got[] = 'mobile';
    if (!empty($a['experience_years']))$got[] = $a['experience_years'] . ' yrs experience';
    if (!empty($a['remarks']))         $got[] = 'role & skills';
    return $got ? implode(', ', $got) : '';
}

// §15 — AI extraction of a requirement from a pasted email / JD / WhatsApp.
// Returns JSON {ok, fields:{…}} for the form to fill in — the user always
// reviews and saves; the AI never creates a requirement itself.
function recruit_ai_extract() {
    header('Content-Type: application/json');
    if (!function_exists('ai_enabled') || !ai_enabled() || !function_exists('ai_chat')) {
        echo json_encode(['ok' => false, 'error' => 'AI is not enabled. Add a provider key under Settings → AI providers.']); return;
    }
    if (function_exists('csrf_ok') && !csrf_ok($_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false, 'error' => 'Session expired — reload the page.']); return; }
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text === '') { echo json_encode(['ok' => false, 'error' => 'Paste the requirement text first.']); return; }
    $sys = "You extract a structured recruitment requirement from a client's message (email / job description / WhatsApp) "
         . "for a technical-manpower / inspection agency. Return ONLY compact JSON, no prose, with these keys (use \"\" or omit when unknown): "
         . "designation, quantity (integer), discipline, category, skills, qualification, experience_min (years, number), "
         . "project_site, deploy_location, work_model (DEPUTATION|SPOT|CONTRACT|PERMANENT), start_date (YYYY-MM-DD), end_date (YYYY-MM-DD), "
         . "duty_hours, shift (GENERAL|DAY|NIGHT|ROTATING|FLEX), billing_rate (number), rate_basis (MONTHLY|MANMONTH|MANDAY|DAILY|FIXED), "
         . "contact_name, contact_phone, notes. Never invent facts.";
    try { [$out, $err] = ai_chat($sys, $text, 700); } catch (Throwable $e) { $out = null; $err = $e->getMessage(); }
    if (!is_string($out) || trim($out) === '') { echo json_encode(['ok' => false, 'error' => $err ?: 'The AI did not respond.']); return; }
    $json = $out; if (preg_match('/\{.*\}/s', $out, $m)) $json = $m[0];
    $data = json_decode($json, true);
    if (!is_array($data)) { echo json_encode(['ok' => false, 'error' => 'Could not read the AI response — try again or fill the form manually.']); return; }
    $allow = ['designation','quantity','discipline','category','skills','qualification','experience_min','project_site',
        'deploy_location','work_model','start_date','end_date','duty_hours','shift','billing_rate','rate_basis','contact_name','contact_phone','notes'];
    $fields = [];
    foreach ($allow as $k) if (isset($data[$k]) && is_scalar($data[$k]) && (string)$data[$k] !== '') $fields[$k] = (string)$data[$k];
    echo json_encode(['ok' => true, 'fields' => $fields]);
}

function recruit_home_can() {
    // M6 — index.php can hand a recruitment-only company straight to the command
    // centre BEFORE ops_dispatch(), so this helper is itself an entry point and
    // must ask the module question rather than trust the master flag.
    return function_exists('can') && (can('mod.hiring.view')
        || (function_exists('is_master_of') && is_master_of('hiring')));
}

// SBU scope for candidate rows (candidates have no office_id — they carry an sbu).
function recruit_sbu_clause($col = 'c.sbu') {
    if (!function_exists('scope_sbus')) return ['1=1', []];
    $s = scope_sbus();
    if ($s === 'ALL' || !is_array($s) || !$s) return ['1=1', []];
    $in = implode(',', array_fill(0, count($s), '?'));
    return ["($col IN ($in) OR COALESCE($col,'')='')", array_values($s)];
}

// ---- Data ------------------------------------------------------------------
// Module 35 — interviews whose date has PASSED with no outcome recorded. Every
// other interview query in this module filters interview_date >= today (upcoming
// only), so a past interview with no interview_done_date and no interview_outcome
// was surfaced NOWHERE — the outcome simply never got chased. Read-only worklist,
// oldest first, scoped like every other candidate read. Only chases candidates
// still in play (a terminal/hired candidate's stale interview is not a task).
function recruit_overdue_interviews($limit = 50) {
    if (function_exists('req_migrate')) req_migrate();
    $today = date('Y-m-d');
    [$cw, $ca] = recruit_sbu_clause('c.sbu');
    $lim = max(1, min(500, (int)$limit));
    try {
        return ops_all(
            "SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.stage,
                    c.interview_date, r.req_code
             FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
             WHERE COALESCE(c.interview_required,0)=1
               AND COALESCE(c.interview_date,'')<>'' AND c.interview_date < ?
               AND COALESCE(c.interview_done_date,'')='' AND COALESCE(c.interview_outcome,'')=''
               AND c.stage IN ('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD')
               AND $cw
             ORDER BY c.interview_date LIMIT $lim", array_merge([$today], $ca)) ?: [];
    } catch (Throwable $e) { return []; }
}
function recruit_overdue_interviews_count() {
    if (function_exists('req_migrate')) req_migrate();
    $today = date('Y-m-d');
    [$cw, $ca] = recruit_sbu_clause('c.sbu');
    try {
        return (int)(ops_one(
            "SELECT COUNT(*) n FROM candidates c
             WHERE COALESCE(c.interview_required,0)=1
               AND COALESCE(c.interview_date,'')<>'' AND c.interview_date < ?
               AND COALESCE(c.interview_done_date,'')='' AND COALESCE(c.interview_outcome,'')=''
               AND c.stage IN ('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD')
               AND $cw", array_merge([$today], $ca))['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

function recruit_data() {
    req_migrate();   // ensure the Phase-2 columns exist before we read them
    $today = date('Y-m-d');
    $in30  = date('Y-m-d', strtotime('+30 days'));
    $in7   = date('Y-m-d', strtotime('+7 days'));
    $in14  = date('Y-m-d', strtotime('+14 days'));
    $ago7  = date('Y-m-d', strtotime('-7 days'));
    $ago14 = date('Y-m-d', strtotime('-14 days'));

    // Guarded readers — a missing table/column yields 0 / [] not a fatal.
    $one  = function ($sql, $a = []) { try { return (int)(ops_one($sql, $a)['n'] ?? 0); } catch (Throwable $e) { return 0; } };
    $rows = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };

    [$rw, $ra]   = function_exists('scope_clause') ? scope_clause('r.office_id', 'r.sbu') : ['1=1', []];
    [$jw, $ja]   = function_exists('scope_clause') ? scope_clause('j.executing_office_id', "''") : ['1=1', []];
    [$cw, $ca]   = recruit_sbu_clause('c.sbu');
    $activeStages = "('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD')";

    $d = [];

    // ---------- Headline counts (KPI row) ----------
    $d['open_reqs']   = $one("SELECT COUNT(*) n FROM requisitions r WHERE r.status='OPEN' AND $rw", $ra);
    $d['pipeline']    = $one("SELECT COUNT(*) n FROM candidates c WHERE c.stage IN $activeStages AND $cw", $ca);
    $d['interviews']  = $one("SELECT COUNT(*) n FROM candidates c WHERE COALESCE(c.interview_required,0)=1
                              AND COALESCE(c.interview_date,'')<>'' AND COALESCE(c.interview_done_date,'')=''
                              AND c.interview_date>=? AND c.interview_date<=? AND $cw", array_merge([$today,$in7], $ca));
    $d['offers']      = $one("SELECT COUNT(*) n FROM candidates c WHERE c.stage='OFFERED' AND $cw", $ca);
    $d['expiring']    = $one("SELECT COUNT(*) n FROM jobs j WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                              AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw", array_merge([$today,$in30], $ja));
    $d['available']   = $one("SELECT COUNT(*) n FROM inspector_day_status s WHERE s.day=? AND s.status='AVAILABLE'", [$today]);

    // ---------- TODAY — needs action ----------
    //  PHASE 5 — cancelled_qty travels with the quantity, so the screen can show
    //  the APPROVED number rather than the original ask.
    $d['t_reqs'] = $rows("SELECT r.id, r.req_code, r.designation, r.project_site, o.name office, r.status, COALESCE(r.quantity,1) quantity,
                                 COALESCE(r.cancelled_qty,0) cancelled_qty,
                                 (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id AND c.stage IN ('OFFERED','ACCEPTED')) filled
                          FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
                          WHERE r.status IN ('OPEN','PROPOSED') AND $rw ORDER BY r.id DESC LIMIT 6", $ra);
    $d['t_followups'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.stage,
                                      COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) since
                               FROM candidates c WHERE c.stage IN $activeStages
                                 AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date,'') <> ''
                                 AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) < ? AND $cw
                               ORDER BY since LIMIT 6", array_merge([$ago7], $ca));
    $d['t_interviews'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.interview_date
                                FROM candidates c WHERE COALESCE(c.interview_required,0)=1
                                  AND COALESCE(c.interview_date,'')<>'' AND COALESCE(c.interview_done_date,'')=''
                                  AND c.interview_date>=? AND $cw ORDER BY c.interview_date LIMIT 6", array_merge([$today], $ca));
    $d['t_offers'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.expected_rate, c.rate_type
                            FROM candidates c WHERE c.stage='OFFERED' AND $cw ORDER BY c.decided_at DESC LIMIT 6", $ca);
    $d['t_joinings'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.proposed_site
                              FROM candidates c WHERE c.stage='ACCEPTED' AND c.inspector_id IS NULL AND $cw
                              ORDER BY c.decided_at DESC LIMIT 6", $ca);
    $d['t_expiring'] = $rows("SELECT j.id, j.job_code, i.name inspector, bp.display_name client,
                                     COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                              FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                              LEFT JOIN calls cl ON cl.id=j.call_id LEFT JOIN business_partners bp ON bp.id=cl.client_id
                              WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                                AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                              ORDER BY endd LIMIT 6", array_merge([$today,$in30], $ja));

    // ---------- RISKS ----------
    // Interviews whose date has passed with no outcome — chased nowhere else.
    $d['r_interviews'] = function_exists('recruit_overdue_interviews') ? recruit_overdue_interviews(6) : [];
    $d['r_reqs'] = $rows("SELECT r.id, r.req_code, r.designation, o.name office, r.created_at,
                                 (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id) cands
                          FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
                          WHERE r.status='OPEN' AND COALESCE(r.created_at,'') < ? AND $rw
                            AND NOT EXISTS (SELECT 1 FROM candidates c WHERE c.requisition_id=r.id AND c.stage IN ('OFFERED','ACCEPTED'))
                          ORDER BY r.created_at LIMIT 6", array_merge([$ago14], $ra));
    $d['r_dormant'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.stage,
                                    COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) since
                             FROM candidates c WHERE c.stage IN $activeStages
                               AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date,'')<>''
                               AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) < ? AND $cw
                             ORDER BY since LIMIT 6", array_merge([$ago14], $ca));
    $d['r_urgent'] = $rows("SELECT j.id, j.job_code, i.name inspector,
                                   COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                            FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                            WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                              AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                            ORDER BY endd LIMIT 6", array_merge([$today,$in7], $ja));
    $d['r_certs'] = $rows("SELECT ic.id, i.name inspector, ic.name cert, ic.valid_to
                           FROM inspector_certs ic JOIN inspectors i ON i.id=ic.inspector_id
                           WHERE COALESCE(ic.valid_to,'')<>'' AND ic.valid_to BETWEEN ? AND ?
                           ORDER BY ic.valid_to LIMIT 6", [$today, $in30]);

    // ---------- OPPORTUNITIES ----------
    $d['o_available'] = $rows("SELECT s.inspector_id, i.name, i.designation
                               FROM inspector_day_status s JOIN inspectors i ON i.id=s.inspector_id
                               WHERE s.day=? AND s.status='AVAILABLE' ORDER BY i.name LIMIT 6", [$today]);
    $d['o_freeing'] = $rows("SELECT j.id, j.job_code, i.name inspector,
                                    COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                             FROM jobs j JOIN inspectors i ON i.id=j.inspector_id
                             WHERE COALESCE(j.closed_flag,0)=0
                               AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                             ORDER BY endd LIMIT 6", array_merge([$today,$in14], $ja));
    // Dormant candidates whose designation matches a live open requirement.
    $d['o_match'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation
                           FROM candidates c
                           WHERE c.stage IN ('HOLD','REJECTED','WITHDRAWN') AND COALESCE(c.designation,'')<>''
                             AND EXISTS (SELECT 1 FROM requisitions r WHERE r.status='OPEN' AND r.designation=c.designation)
                             AND $cw ORDER BY c.id DESC LIMIT 6", $ca);
    $d['o_extensions'] = $d['t_expiring'];   // same set, framed as an extension opportunity

    $d['counts'] = [
        'today' => count($d['t_reqs']) + count($d['t_followups']) + count($d['t_interviews']) + count($d['t_offers']) + count($d['t_joinings']) + count($d['t_expiring']),
        'risks' => count($d['r_reqs']) + count($d['r_dormant']) + count($d['r_urgent']) + count($d['r_certs']),
        'opps'  => count($d['o_available']) + count($d['o_freeing']) + count($d['o_match']),
    ];
    return $d;
}

function ops_recruitment_home($method) {
    ops_require(recruit_home_can(), 'You do not have access to Recruitment.');
    $d = recruit_data();
    view('ops/recruitment_home', ['d' => $d]);
    return true;
}

// ============================================================================
//  Phase 5 — Assignment commercials
//
//  A hired candidate placed against a requirement IS the "assignment". The
//  requirement already carries the EXPECTED commercial (billing rate, budgeted
//  cost, target margin). What was missing is the per-placement lifecycle every
//  manpower business actually runs on:
//
//     estimate  →  approved  →  actual        (with variance and margin at each)
//
//  Estimate is always computed fresh from the requirement, so it can never drift
//  out of step. Approved is what a coordinator locks for THIS person (the rate we
//  will really bill and the cost we will really carry). Actual is what was truly
//  billed and paid, recorded once the placement has run. All of it lives in
//  additive, nullable columns on the candidate row — nothing renamed, nothing
//  dropped, and a candidate with no commercials set simply shows the estimate.
// ============================================================================

// Additive, nullable commercial-lifecycle columns on the assignment (candidate).
function asg_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $cols = [
        ['asg_bill_rate','DECIMAL(14,2) NULL'], ['asg_bill_basis',"VARCHAR(20) NULL"],
        ['asg_cost_rate','DECIMAL(14,2) NULL'], ['asg_months','DECIMAL(8,2) NULL'],
        ['asg_onetime','DECIMAL(14,2) NULL'],  ['asg_status',"VARCHAR(16) NULL"],
        ['asg_approved_by',"VARCHAR(150) NULL"], ['asg_approved_at',"VARCHAR(30) NULL"],
        ['asg_ref',"VARCHAR(60) NULL"], ['asg_act_rev','DECIMAL(16,2) NULL'],
        ['asg_act_cost','DECIMAL(16,2) NULL'], ['asg_note',"VARCHAR(400) NULL"],
    ];
    foreach ($cols as $c) ensure_column('candidates', $c[0], $c[1]);
}

// A rate + basis + months turned into one person's amount. Mirrors req_commercials'
// unit logic so estimate and approved use exactly the same arithmetic.
function asg_amount($rate, $basis, $months) {
    $rate = (float)$rate; $months = (float)$months; $basis = $basis ?: 'MONTHLY';
    if ($basis === 'FIXED') return round($rate, 2);
    $units = ($basis === 'MANDAY' || $basis === 'DAILY') ? round($months * 22) : $months;
    return round($rate * max($units, 0), 2);
}

// A stored numeric column that may be NULL (never set) vs 0 (deliberately zero).
function _asg_num($v, $fallback) {
    return ($v === null || $v === '') ? (float)$fallback : (float)$v;
}

// The three-tier commercial for a single assignment (a candidate vs its
// requirement). Estimate from the requirement; approved/actual from stored
// asg_* when present. Always safe to call — falls back to the estimate.
function assignment_commercials($cand, $req) {
    $req = $req ?: [];
    $months = _asg_num($cand['asg_months'] ?? null, req_duration_months($req));
    $basis  = ($cand['asg_bill_basis'] ?? '') ?: ($req['rate_basis'] ?? 'MONTHLY');

    $estBillRate = (float)($req['billing_rate'] ?? 0);
    $estCostRate = (float)($req['budgeted_cost'] ?? 0);
    $est = ['rev' => asg_amount($estBillRate, $basis, $months),
            'cost' => asg_amount($estCostRate, 'MONTHLY', $months)];
    $est['profit'] = $est['rev'] - $est['cost'];
    $est['margin'] = $est['rev'] > 0 ? $est['profit'] / $est['rev'] * 100 : 0;

    $apBillRate = _asg_num($cand['asg_bill_rate'] ?? null, $estBillRate);
    $apCostRate = _asg_num($cand['asg_cost_rate'] ?? null, $estCostRate);
    $onetime    = (float)($cand['asg_onetime'] ?? 0);
    $appr = ['rev' => asg_amount($apBillRate, $basis, $months),
             'cost' => asg_amount($apCostRate, 'MONTHLY', $months) + $onetime];
    $appr['profit'] = $appr['rev'] - $appr['cost'];
    $appr['margin'] = $appr['rev'] > 0 ? $appr['profit'] / $appr['rev'] * 100 : 0;

    $hasAct = (($cand['asg_act_rev'] ?? null) !== null && ($cand['asg_act_rev'] ?? '') !== '')
           || (($cand['asg_act_cost'] ?? null) !== null && ($cand['asg_act_cost'] ?? '') !== '');
    $act = null;
    if ($hasAct) {
        $act = ['rev' => (float)($cand['asg_act_rev'] ?? 0), 'cost' => (float)($cand['asg_act_cost'] ?? 0)];
        $act['profit'] = $act['rev'] - $act['cost'];
        $act['margin'] = $act['rev'] > 0 ? $act['profit'] / $act['rev'] * 100 : 0;
    }

    $status = (string)($cand['asg_status'] ?? '');
    $var = [
        'appr_rev'    => round($appr['rev'] - $est['rev'], 2),
        'appr_profit' => round($appr['profit'] - $est['profit'], 2),
        'act_rev'     => $act ? round($act['rev'] - $appr['rev'], 2) : null,
        'act_profit'  => $act ? round($act['profit'] - $appr['profit'], 2) : null,
    ];
    return ['months' => $months, 'basis' => $basis, 'onetime' => $onetime,
            'est' => $est, 'appr' => $appr, 'act' => $act, 'var' => $var,
            'status' => $status, 'approved' => in_array($status, ['APPROVED','ACTUAL'], true),
            'approved_by' => (string)($cand['asg_approved_by'] ?? ''),
            'approved_at' => (string)($cand['asg_approved_at'] ?? ''),
            'ref' => (string)($cand['asg_ref'] ?? ''),
            'bill_rate' => $apBillRate, 'cost_rate' => $apCostRate];
}

// Is this assignment ready to hand to billing? A packet of explicit checks —
// reuses the existing deputation bill gate (job_bills_missing) for chargeable
// expenses so recruitment never re-implements what Operations already enforces.
function assignment_billing_packet($cand, $req) {
    $checks = []; $ready = true;
    $add = function ($ok, $label, $hint = '') use (&$checks, &$ready) {
        $checks[] = ['ok' => (bool)$ok, 'label' => $label, 'hint' => $hint];
        if (!$ok) $ready = false;
    };
    $c = assignment_commercials($cand, $req);
    $hired = ($cand['stage'] ?? '') === 'ACCEPTED' || !empty($cand['inspector_id']);
    $add($hired, 'Candidate hired & on the workforce', 'Move to Accepted and add to the workforce first.');
    $add($c['approved'], 'Commercials approved', 'A coordinator must approve the billing rate for this placement.');
    $add($c['appr']['rev'] > 0, 'Billing rate set', 'No billing rate on this assignment yet.');

    // Chargeable expense bills owed to the client — via the deputation, if any.
    $job = null;
    if (!empty($cand['inspector_id'])) {
        try { $job = ops_one("SELECT * FROM jobs WHERE inspector_id=? ORDER BY id DESC LIMIT 1", [(int)$cand['inspector_id']]); }
        catch (Throwable $e) { $job = null; }
    }
    if ($job && function_exists('job_bills_missing')) {
        $miss = job_bills_missing($job);
        $add(empty($miss), 'Chargeable bills on file', $miss ? ('Awaiting: ' . implode(', ', $miss)) : '');
    }
    return ['ready' => $ready, 'checks' => $checks, 'commercials' => $c, 'job' => $job];
}

// Roll every hire on a requirement up into planned vs approved vs actual, so the
// requisition detail can show whether the placements are landing on plan.
function recruit_req_commercial_rollup($req) {
    asg_migrate();
    $rows = [];
    try {
        $rows = ops_all("SELECT * FROM candidates WHERE requisition_id=? AND (stage='ACCEPTED' OR inspector_id IS NOT NULL)", [(int)($req['id'] ?? 0)]);
    } catch (Throwable $e) { $rows = []; }
    $s = ['n' => 0, 'approved' => 0, 'n_act' => 0,
          'plan_rev' => (float)($req['expected_revenue'] ?? 0), 'plan_profit' => (float)($req['expected_profit'] ?? 0),
          'appr_rev' => 0.0, 'appr_cost' => 0.0, 'appr_profit' => 0.0,
          'act_rev' => 0.0, 'act_cost' => 0.0, 'act_profit' => 0.0];
    foreach ($rows as $r) {
        $c = assignment_commercials($r, $req); $s['n']++;
        $s['appr_rev'] += $c['appr']['rev']; $s['appr_cost'] += $c['appr']['cost']; $s['appr_profit'] += $c['appr']['profit'];
        if ($c['approved']) $s['approved']++;
        if ($c['act']) { $s['n_act']++; $s['act_rev'] += $c['act']['rev']; $s['act_cost'] += $c['act']['cost']; $s['act_profit'] += $c['act']['profit']; }
    }
    $s['plan_margin'] = $s['plan_rev'] > 0 ? $s['plan_profit'] / $s['plan_rev'] * 100 : 0;
    $s['appr_margin'] = $s['appr_rev'] > 0 ? $s['appr_profit'] / $s['appr_rev'] * 100 : 0;
    $s['act_margin']  = $s['act_rev']  > 0 ? $s['act_profit']  / $s['act_rev']  * 100 : 0;
    return $s;
}

// ============================================================================
//  Phase 6 — Engagement mode + the person (multi-application) model
//
//  Two long-standing gaps, both filled additively:
//
//   1. An agency is usually one of: it RECRUITS people onto the client's own
//      roll for a one-time fee, or it SUPPLIES manpower on its own roll for a
//      monthly charge — or it does both. That choice colours every hire, so it
//      belongs in one setting instead of being re-decided on each candidate.
//
//   2. One human being applies to several requirements over time. The register
//      keeps one row per APPLICATION (so history is never lost), which means the
//      same person can appear as several candidate rows. Rather than merge and
//      destroy history, a nullable person_ref threads those rows together, and
//      the view falls back to a phone/e-mail match so it works even before any
//      linking is done. Nothing is deleted; the applications stay distinct.
// ============================================================================

const RECRUIT_ENGAGEMENT_MODES = [
    'BOTH'        => 'Both — recruit onto client roll and supply manpower',
    'RECRUITMENT' => 'Recruitment only — place onto the client roll (one-time fee)',
    'MANPOWER'    => 'Manpower only — supply on our roll (monthly charge)',
];

// The installation's engagement mode (Settings-backed, defaults to BOTH).
function recruit_engagement_mode() {
    $m = function_exists('setting_get') ? strtoupper((string)setting_get('recruit_engagement_mode', 'BOTH')) : 'BOTH';
    return isset(RECRUIT_ENGAGEMENT_MODES[$m]) ? $m : 'BOTH';
}

// The hire-type the form should default to, given the mode. BOTH → let the user pick.
function recruit_default_hire_type() {
    $m = recruit_engagement_mode();
    return $m === 'BOTH' ? '' : $m;
}

// Additive, nullable thread that ties a person's several application rows.
function person_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    ensure_column('candidates', 'person_ref', "VARCHAR(40) NULL");
}

// The stable person key for a candidate row: an explicit link if set, else the
// last 10 digits of the mobile, else the lower-cased e-mail. '' when unknowable.
function person_key($cand) {
    $ref = trim((string)($cand['person_ref'] ?? ''));
    if ($ref !== '') return 'ref:' . $ref;
    $mob = preg_replace('/\D+/', '', (string)($cand['mobile'] ?? ''));
    if (strlen($mob) >= 10) return 'mob:' . substr($mob, -10);
    $em = strtolower(trim((string)($cand['email'] ?? '')));
    if ($em !== '') return 'em:' . $em;
    return '';
}

// Every OTHER application row that belongs to the same person — explicit link
// first, phone/e-mail match as the fallback. Read-only; keeps each application.
function person_applications($cand) {
    person_migrate();
    $selfId = (int)($cand['id'] ?? 0);
    $ref    = trim((string)($cand['person_ref'] ?? ''));
    $mob    = preg_replace('/\D+/', '', (string)($cand['mobile'] ?? ''));
    $email  = strtolower(trim((string)($cand['email'] ?? '')));
    $mob10  = strlen($mob) >= 10 ? substr($mob, -10) : '';
    if ($ref === '' && $mob10 === '' && $email === '') return [];
    try {
        $rows = ops_all("SELECT c.id, c.cand_code, c.first_name, c.last_name, c.stage, c.designation,
                            c.mobile, c.email, c.person_ref, c.requisition_id, c.client_id, c.created_at,
                            c.submitted_client_date, c.interview_outcome, c.client_feedback,
                            r.req_code, bp.legal_name client_name
                         FROM candidates c
                         LEFT JOIN requisitions r ON r.id=c.requisition_id
                         LEFT JOIN business_partners bp ON bp.id=c.client_id
                         WHERE c.id<>? ORDER BY c.id DESC LIMIT 400", [$selfId]) ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $rRef = trim((string)($r['person_ref'] ?? ''));
        $rMob = preg_replace('/\D+/', '', (string)$r['mobile']);
        $rEm  = strtolower(trim((string)$r['email']));
        $match = ($ref !== '' && $rRef !== '' && $rRef === $ref)
              || ($mob10 !== '' && strlen($rMob) >= 10 && substr($rMob, -10) === $mob10)
              || ($email !== '' && $rEm === $email);
        if ($match) $out[] = $r;
    }
    return array_slice($out, 0, 20);
}

// Link a set of candidate rows as one person: they all take a single shared
// person_ref (an existing one in the group, else a fresh key from the lowest id).
// Additive — only stamps person_ref, never merges or deletes an application.
function person_link_rows(array $ids) {
    person_migrate();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (count($ids) < 2) return '';
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $rows = ops_all("SELECT id, person_ref FROM candidates WHERE id IN ($ph)", $ids) ?: [];
    } catch (Throwable $e) { return 'Could not read those records.'; }
    if (count($rows) !== count($ids)) return 'One of those records no longer exists.';

    //  PHASE 6 · BATCH 2 — LINKING MUST NEVER SPLIT A GROUP (invariant I43).
    //
    //  This used to UPDATE only the ids it was handed. Saying "B and C are the
    //  same person", where C already shared a group with D, moved C and left D
    //  behind: a record previously recorded as the same person silently became a
    //  different one, with no audit entry anywhere. Proved in
    //  P6-BATCH2-PREIMPLEMENTATION-AUDIT.md, Finding B.
    //
    //  The operation is a closure, so it is computed as one. Every member of
    //  every group being joined comes along. This uses ONLY the groupings the
    //  system itself already recorded — never a name, never an e-mail, never a
    //  similarity. It is not an identity inference.
    $refs = [];
    foreach ($rows as $r) { $v = trim((string)$r['person_ref']); if ($v !== '') $refs[$v] = 1; }
    $ref = $refs ? (string)array_key_first($refs) : 'P' . str_pad((string)min($ids), 6, '0', STR_PAD_LEFT);

    $all = $ids;
    if ($refs) {
        $rph = implode(',', array_fill(0, count($refs), '?'));
        try {
            foreach (ops_all("SELECT id FROM candidates WHERE person_ref IN ($rph)", array_keys($refs)) ?: [] as $r)
                $all[] = (int)$r['id'];
        } catch (Throwable $e) { return 'Could not read the existing person groups.'; }
    }
    $all = array_values(array_unique($all));
    $aph = implode(',', array_fill(0, count($all), '?'));
    db()->prepare("UPDATE candidates SET person_ref=? WHERE id IN ($aph)")->execute(array_merge([$ref], $all));

    $extra = count($all) - count($ids);
    foreach ($ids as $cid)
        rcv_log($cid, 'IDENTITY_LINKED', 'Recorded as one person with ' . (count($all) - 1) . ' other application(s)'
            . ($extra > 0 ? ' (' . $extra . ' brought in from an existing group)' : '') . ' — reference ' . $ref);
    return '';
}

/**
 * Restore person groups that the OLD person_link_rows() split (owner decision BD4).
 *
 *  STRICT LIMITS, and they are the point:
 *   · deterministic — it reads only groupings the system itself recorded
 *   · never fuzzy — no name, no e-mail, no mobile, no similarity of any kind
 *   · never silent — every repair writes an audit entry
 *   · never a merge of unrelated people — it only rejoins groups that a single
 *     recorded link operation is proven to have separated
 *   · if the previous state cannot be proven from existing data, it REPORTS and
 *     does not touch anything
 *
 *  The proof it requires: a candidate whose group was absorbed elsewhere leaves
 *  behind siblings that still carry the OLD reference while at least one former
 *  member of that same old reference now carries a different one. That pattern
 *  is only producible by the split, and the two references are both the
 *  system's own records.
 *
 *  $opt['dry_run'] reports without changing anything.
 *  Returns a list of findings: [['old_ref'=>…, 'new_ref'=>…, 'ids'=>[…], 'repaired'=>bool], …]
 */
function person_group_repair(array $opt = []) {
    person_migrate();
    $dry = !empty($opt['dry_run']);
    $out = [];
    try {
        //  The evidence: the activity spine's own record of which applications a
        //  link operation named together. Without that record the previous state
        //  cannot be PROVEN, and nothing is repaired.
        $links = ops_all("SELECT entity_id, subject FROM activities
                          WHERE entity_kind='CANDIDATE' AND kind='IDENTITY_LINKED'
                            AND subject LIKE '%reference %' ORDER BY id") ?: [];
    } catch (Throwable $e) { return $out; }
    $seen = [];
    foreach ($links as $l) {
        if (!preg_match('/reference\s+(\S+)$/', (string)$l['subject'], $m)) continue;
        $ref = $m[1];
        $cid = (int)$l['entity_id'];
        if (isset($seen[$cid . '|' . $ref])) continue;
        $seen[$cid . '|' . $ref] = 1;
        $now = (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$cid]);
        if ($now === '' || $now === $ref) continue;              // still where the record says
        //  Anyone still carrying the OLD reference was left behind by a later link.
        $left = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM candidates WHERE person_ref=?", [$ref]) ?: []);
        if (!$left) continue;                                     // nothing stranded
        $out[] = ['old_ref' => $ref, 'new_ref' => $now, 'ids' => $left, 'repaired' => !$dry];
        if ($dry) continue;
        $ph = implode(',', array_fill(0, count($left), '?'));
        db()->prepare("UPDATE candidates SET person_ref=? WHERE id IN ($ph)")->execute(array_merge([$now], $left));
        foreach ($left as $lid)
            rcv_log($lid, 'IDENTITY_LINKED',
                'Person group restored — rejoined reference ' . $now . ' after an earlier link separated it from ' . $ref);
    }
    return $out;
}

// ============================================================================
//  PHASE 6 · BATCH 2 — CANDIDATE → TEAM MEMBER CONVERSION
//
//  This used to be twenty lines inline in the /candidate-stage route, with no
//  transaction and no ceiling. Three browsers accepting the same person at the
//  same instant produced three staff records, two of them belonging to nobody
//  and reported by nothing (P6-BATCH2-PREIMPLEMENTATION-AUDIT.md, Finding A).
//
//  It lives here now so it can be tested and attacked directly rather than only
//  through a route, and so every gate is asked by the action rather than
//  inherited from whatever page called it (invariant I27).
//
//  IT CREATES NO PERSON HUB AND MERGES NOTHING. `candidates.inspector_id` keeps
//  working unchanged for every existing reader; the identity-ledger row is
//  additive.
// ============================================================================

// ===========================================================================
//  RB-3 · STEP 2 — IS THIS PERSON ALREADY ON OUR TEAM?   (owner decision 2)
//
//  "Do not refuse automatically. Do not silently continue. Show the match and
//   require the recruiter to explicitly acknowledge it."
//
//  THE GAP THIS FILLS: nothing in this application ever compared an applicant
//  against the existing workforce. cand_find_duplicates() compares applicants to
//  applicants; connect_identity_suggestions() bridges marketplace professionals
//  to team members. This file contained no query against `inspectors` at all
//  (P6-BATCH2-PREIMPLEMENTATION-AUDIT-R2.md, finding N4), so "we already employ
//  this person" was a question the system could not ask.
//
//  THE EMPLOYEE NUMBER IS NEVER A MATCHING SIGNAL. It identifies an employment
//  record, not a human, and Step 1 already made it unique for life.
//
//  The rules live in docs/phase7/RB3-STEP2-DUPLICATE-MATCH-RULES.md and the
//  scale is the one cand_find_duplicates() has used since Phase 3 — a second
//  scoring model would mean one screen calling something 96 and another calling
//  the same thing 80.
// ===========================================================================

/** Strong enough that a human must look: things a person chooses and owns. */
const WORKFORCE_STRONG_MIN = 90;      // mobile 96 · e-mail 94
/** How long an acknowledgement stays usable. Long enough to read, short enough
 *  that it cannot be pocketed and replayed tomorrow. */
const WORKFORCE_ACK_TTL = 1800;       // 30 minutes

/**
 * Team members who may be the person in this application.
 *
 * Read-only. It never links, never merges and never writes. It returns a list
 * and a question; a human answers it.
 */
function workforce_matches(array $cand) {
    $mobile = preg_replace('/\D+/', '', (string)($cand['mobile'] ?? ''));
    $email  = strtolower(trim((string)($cand['email'] ?? '')));
    $first  = strtolower(trim((string)($cand['first_name'] ?? '')));
    $last   = strtolower(trim((string)($cand['last_name'] ?? '')));
    if ($mobile === '' && $email === '' && ($first === '' || $last === '')) return [];
    try {
        //  LIVE team members only. Somebody who has left is history, not a
        //  duplicate, and warning about them would teach recruiters to click
        //  past the warning next to it.
        //
        //  NO `LIMIT`, deliberately and permanently. cand_find_duplicates() has
        //  one (500) and never says the check was partial; a check that quietly
        //  becomes partial is worse than one that is absent (finding N5).
        $rows = ops_all("SELECT id, name, first_name, last_name, emp_code, email, mobile, home_office_id, sbu
                           FROM inspectors
                          WHERE COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE'") ?: [];
    } catch (Throwable $e) { return []; }

    $out = [];
    foreach ($rows as $r) {
        $conf = 0; $basis = '';
        $rm = preg_replace('/\D+/', '', (string)($r['mobile'] ?? ''));
        if ($mobile !== '' && $rm !== '' && substr($rm, -10) === substr($mobile, -10)) { $conf = 96; $basis = 'mobile'; }
        $re = strtolower(trim((string)($r['email'] ?? '')));
        if ($conf < 94 && $email !== '' && $re !== '' && $re === $email) { $conf = 94; $basis = 'email'; }
        if ($conf < 72) {
            $rf = strtolower(trim((string)($r['first_name'] ?? '')));
            $rl = strtolower(trim((string)($r['last_name'] ?? '')));
            //  Older rows carry only `name`; split it so they are not invisible.
            if ($rf === '' && $rl === '') {
                $bits = preg_split('/\s+/', strtolower(trim((string)($r['name'] ?? ''))));
                $rf = (string)($bits[0] ?? ''); $rl = (string)(count($bits) > 1 ? end($bits) : '');
            }
            if ($first !== '' && $last !== '' && $rf === $first && $rl === $last) { $conf = 72; $basis = 'name'; }
            elseif ($conf < 46 && $last !== '' && $rl === $last && $first !== '' && $rf !== '' && $rf[0] === $first[0]) { $conf = 46; $basis = 'similar name'; }
        }
        if ($conf < 46) continue;

        //  A match the actor may not open is COUNTED but NEVER NAMED — a record
        //  id is never proof of authorisation (invariant I25). It still blocks:
        //  otherwise "I cannot see that branch" would be a way round the gate.
        $visible = !function_exists('connect_identity_scope_ok') || connect_identity_scope_ok('inspector', (int)$r['id']);
        $out[] = [
            'inspector_id' => (int)$r['id'],
            'class'   => $conf >= WORKFORCE_STRONG_MIN ? 'STRONG' : 'WEAK',
            'basis'   => $basis,
            'confidence' => $conf,
            'visible' => $visible,
            'name'     => $visible ? (string)($r['name'] ?: trim(((string)$r['first_name']) . ' ' . ((string)$r['last_name']))) : '',
            'emp_code' => $visible ? (string)($r['emp_code'] ?? '') : '',
            'email'    => $visible ? workforce_mask_email((string)($r['email'] ?? '')) : '',
            'mobile'   => $visible ? workforce_mask_mobile((string)($r['mobile'] ?? '')) : '',
        ];
    }
    usort($out, fn($a, $b) => $b['confidence'] <=> $a['confidence'] ?: $a['inspector_id'] <=> $b['inspector_id']);
    return $out;
}

/** Only the matches strong enough to stop a hire. */
function workforce_strong_matches(array $matches) {
    return array_values(array_filter($matches, fn($m) => ($m['class'] ?? '') === 'STRONG'));
}

//  Shown so a recruiter can recognise the person, masked so a screen does not
//  become a contact-details export (§7 — do not expose more than the decision needs).
function workforce_mask_mobile($m) {
    $d = preg_replace('/\D+/', '', (string)$m);
    return $d === '' ? '' : str_repeat('X', max(0, strlen($d) - 2)) . substr($d, -2);
}
function workforce_mask_email($e) {
    $e = trim((string)$e); if ($e === '' || strpos($e, '@') === false) return '';
    [$u, $d] = explode('@', $e, 2);
    return (strlen($u) <= 2 ? substr($u, 0, 1) : substr($u, 0, 2)) . str_repeat('*', max(1, strlen($u) - 2)) . '@' . $d;
}

/**
 * The per-workspace key the acknowledgement is signed with.
 *
 * NO NEW TABLE. It lives in the `settings` store this application already has,
 * which is already per-tenant — which is also what makes an acknowledgement
 * signed in one workspace unverifiable in another.
 */
function workforce_ack_secret() {
    $k = (string)(function_exists('setting_get') ? setting_get('rb3_ack_key', '') : '');
    if (strlen($k) >= 32) return $k;
    if (!function_exists('setting_set')) return '';
    try {
        setting_set('rb3_ack_key', bin2hex(random_bytes(32)));
        //  Read back from the DATABASE, not from the in-memory cache: two boots
        //  creating the key at the same instant both write, one wins, and the
        //  loser must sign with the winner's value or every token it issues will
        //  be rejected a moment later.
        $live = (string)ops_val("SELECT svalue FROM settings WHERE skey=?", ['rb3_ack_key']);
        return strlen($live) >= 32 ? $live : '';
    } catch (Throwable $e) { return ''; }
}

/**
 * A fingerprint of EXACTLY what is being acknowledged.
 *
 * It covers the strong matches AND the applicant's own contact identity, because
 * if their mobile changes the question is a different question and yesterday's
 * answer does not answer it (§10).
 */
function workforce_ack_evidence(array $matches, array $cand) {
    $ids = [];
    foreach (workforce_strong_matches($matches) as $m) $ids[] = (int)$m['inspector_id'] . ':' . (string)$m['basis'];
    sort($ids);
    $who = strtolower(trim((string)($cand['email'] ?? '')))
         . '|' . substr(preg_replace('/\D+/', '', (string)($cand['mobile'] ?? '')), -10);
    return hash('sha256', $who . '#' . implode(',', $ids));
}

/**
 * Issue an acknowledgement for this candidate, this user, this evidence, now.
 *
 * Returns '' when there is nothing to acknowledge — so a screen with no strong
 * match cannot render a tick at all, and the default state is always NOT
 * ACKNOWLEDGED because an unticked checkbox sends nothing.
 */
function workforce_ack_issue($candId, array $matches, array $cand, $userId = 0) {
    if (!workforce_strong_matches($matches)) return '';
    $sec = workforce_ack_secret(); if ($sec === '') return '';
    $uid = (int)$userId ?: (int)(function_exists('current_user') ? (current_user()['id'] ?? 0) : 0);
    $iat = time();
    $mac = hash_hmac('sha256', implode('|', [(int)$candId, $uid, workforce_ack_evidence($matches, $cand), $iat]), $sec);
    return $iat . '.' . $mac;
}

/**
 * Is this acknowledgement real, for THIS candidate, from THIS user, about THIS
 * evidence, and still fresh?
 *
 * Every one of those is a separate way the gate could be walked around, and each
 * is closed by being inside the signature rather than beside it.
 */
function workforce_ack_ok($token, $candId, array $matches, array $cand, $userId = 0) {
    $token = trim((string)$token); if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 2) return false;
    [$iat, $mac] = $parts;
    if ($iat === '' || !ctype_digit($iat) || !preg_match('/^[0-9a-f]{64}$/', (string)$mac)) return false;
    $age = time() - (int)$iat;
    if ($age > WORKFORCE_ACK_TTL || $age < -60) return false;     // stale, or issued in the future
    $sec = workforce_ack_secret(); if ($sec === '') return false;
    $uid = (int)$userId ?: (int)(function_exists('current_user') ? (current_user()['id'] ?? 0) : 0);
    $want = hash_hmac('sha256', implode('|', [(int)$candId, $uid, workforce_ack_evidence($matches, $cand), (int)$iat]), $sec);
    return hash_equals($want, (string)$mac);
}

/** One line naming what the recruiter was shown, for the audit trail. */
function workforce_ack_note(array $matches) {
    $bits = [];
    foreach (workforce_strong_matches($matches) as $m)
        $bits[] = '#' . $m['inspector_id'] . ($m['emp_code'] !== '' ? ' (' . $m['emp_code'] . ')' : '') . ' by ' . $m['basis'];
    return implode(', ', $bits);
}

/** Refusal codes — deterministic, testable, and safe to show. */
const RCV_CODES = [
    'CONVERTED'      => 'Added to the team.',
    'ALREADY'        => 'This application has already been converted.',
    'NO_CANDIDATE'   => 'No such application for this record.',
    'NOT_ALLOWED'    => 'You cannot convert this application.',
    'NO_BRANCH'      => 'This conversion has no branch: the requirement carries none and neither does the recruiter. Set a branch on the requirement, or on the recruiter, and try again.',
    'BLOCKED'        => 'The requirement does not allow this right now.',
    'RACE_LOST'      => 'Somebody else converted this application a moment ago.',
    'FAILED'         => 'The conversion could not be completed. Nothing was changed.',
    'EMP_CODE'       => 'An employee number could not be issued, so nobody was added. Try again; if it keeps happening, ask an administrator to check the employee-number report.',
    'BUSY'           => 'Somebody else was saving at the same moment, so nothing was changed. Please try again.',
    'WORKFORCE_MATCH'=> 'This person may already be on your team. Open the application, check the possible match shown there, and tick to confirm before accepting.',
    'TEAM_ROLE'      => 'Nobody has said which team this person joins. Choose Field, Coordinator or Back office on the requirement, or confirm it on the application, and try again.',
];

/**
 * WHICH BRANCH does a converted team member belong to? (owner decision BD1)
 *
 *    1. the requirement's branch
 *    2. failing that, the recruiter's branch — the candidate's assigned
 *       recruiter if there is one, otherwise the person performing the
 *       conversion, who is by definition a recruiter doing recruitment work
 *    3. failing both, NOTHING — and the conversion is refused
 *
 *  There is deliberately no fallback to the platform's generic "no office means
 *  Ahmedabad" rule. That rule is right for reading a register and wrong for
 *  creating a person: it silently filed every hire under a branch nobody chose.
 *
 *  Returns [officeId|null, source].
 */
function rcv_branch_for($candId, $actorId = 0) {
    $cand = ops_one("SELECT id, requisition_id, recruiter_id FROM candidates WHERE id=?", [(int)$candId]);
    if (!$cand) return [null, 'no_candidate'];
    $valid = function ($id) {
        $id = (int)$id; if ($id <= 0) return 0;
        return (int)ops_val("SELECT COUNT(*) FROM offices WHERE id=?", [$id]) > 0 ? $id : 0;
    };
    if (!empty($cand['requisition_id'])) {
        $o = $valid(ops_val("SELECT office_id FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]));
        if ($o) return [$o, 'requisition'];
    }
    if (!empty($cand['recruiter_id'])) {
        $o = $valid(ops_val("SELECT home_office_id FROM users WHERE id=?", [(int)$cand['recruiter_id']]));
        if ($o) return [$o, 'recruiter'];
    }
    $actorId = (int)$actorId ?: (int)(current_user()['id'] ?? 0);
    if ($actorId) {
        $o = $valid(ops_val("SELECT home_office_id FROM users WHERE id=?", [$actorId]));
        if ($o) return [$o, 'actor'];
    }
    return [null, 'none'];
}

// ===========================================================================
//  RB-3 · STEP 3 — THE ACCEPTANCE TRANSACTION
//
//  Owner decision (2026-09-20): every refusable check happens BEFORE the
//  transaction; the stage transition, the KPI stage ledger, the workforce
//  record, the employee number and every other acceptance-related write happen
//  INSIDE one transaction. Either the whole acceptance happens or none of it
//  does.
//
//  Two things had to be settled before this could be built, and both were put
//  to the owner rather than decided here
//  (docs/phase7/RB3-STEP3-TRANSACTION-BOUNDARY-STOP-REPORT.md):
//
//    · THE SEAT CEILING moves inside the transaction, under a lock on the
//      requirement. M6 used to let two recruiters both pass and then put the
//      loser BACK after the write — which was safe only because the revert ran
//      before the workforce record was created. Inside one transaction that
//      ordering disappears, and the revert (which undoes the stage and must
//      never delete a person) would have left a real team member holding a
//      permanent employee number for a hire that was undone. So the second
//      recruiter is now stopped AT THE SAVE instead of being unwound after it.
//
//    · THE AUDIT stays OUTSIDE, because invariant I41 says a failed observation
//      is never a failed transaction — a database hiccup while writing a note
//      must not cost somebody a completed hire. What changes is that the gap is
//      no longer silent: see rcv_audit_or_report().
// ===========================================================================

//  MariaDB COMMITS IMPLICITLY ON ANY DDL. Every one of these runs schema
//  changes on a process whose epoch marker is not yet warm — that is, the first
//  acceptance after any restart or deploy — so one firing inside the
//  transaction would silently commit a half-finished acceptance. They are run
//  BEFORE it opens, every time. Cheap when warm; essential when cold.
const RCV_ACCEPT_MIGRATIONS = [
    'rkpi_migrate', 'reqf_migrate', 'rful_migrate', 'asg_migrate', 'person_migrate',
    'connect_identity_migrate', 'emp_code_migrate', 'act_migrate',
    //  Owner decision 3 asks the capability catalogue during acceptance, and
    //  that catalogue installs its own table on first use. Warmed here so the
    //  DDL can never land inside the transaction, where MariaDB would commit it
    //  — and the caller's work with it.
    'cockpit_migrate',
];

/** Run every migration the acceptance path can reach, outside any transaction. */
function rcv_prewarm_migrations() {
    try { if (db()->inTransaction()) return false; } catch (Throwable $e) {}
    foreach (RCV_ACCEPT_MIGRATIONS as $fn)
        if (function_exists($fn)) { try { $fn(); } catch (Throwable $e) {} }
    return true;
}

/**
 * Write an acceptance audit entry OUTSIDE the transaction — and never silently
 * lose it (owner decision 2).
 *
 * I41 stands: the hire is already committed and a failed note does not undo it.
 * But until now a failed audit write simply returned false and nobody ever
 * learned the note was gone. A lost entry is now COUNTED, and the count is
 * reported by identity_state_findings() so somebody can see it.
 *
 * The counter is best-effort by nature — if the database is refusing writes it
 * may refuse this one too — so the error log is the last resort. Neither can
 * fail the hire.
 */
function rcv_audit_or_report($candId, $kind, $subject) {
    $ok = false;
    try { $ok = function_exists('act_log') && (int)act_log('CANDIDATE', (int)$candId, $kind, $subject, ['auto' => 0]) > 0; }
    catch (Throwable $e) { $ok = false; }
    if ($ok) return true;
    try {
        if (function_exists('setting_get') && function_exists('setting_set'))
            setting_set('audit_writes_lost', (string)(((int)setting_get('audit_writes_lost', 0)) + 1));
    } catch (Throwable $e) {}
    @error_log('EXAACT: acceptance audit entry could not be written for candidate #' . (int)$candId . ' (' . $kind . ')');
    return false;
}

/**
 * EVERY condition that can legitimately refuse an acceptance, asked BEFORE the
 * transaction opens (owner §7). Returns '' to proceed, or the sentence to show.
 *
 * It re-implements NOTHING. Each answer comes from the function that already
 * owns that question — rcv_branch_for() for the branch, and Step 2's
 * workforce_matches() / workforce_ack_ok() for the duplicate and its
 * acknowledgement. Step 2's design is used, not copied.
 *
 * rcv_convert() asks these same questions AGAIN inside the transaction, and
 * that duplication is deliberate: a forged POST that never came through this
 * route must fail exactly as the screen does (invariant I27). This pass exists
 * so an honest refusal never has to open a transaction, write a stage and a
 * ledger row, and then throw all of it away.
 */
// ============================================================================
//  WHAT KIND OF TEAM MEMBER — decided, never defaulted
//
//  `inspectors.team_role` carries DEFAULT 'FIELD'. Until now nothing on the
//  recruitment path ever set it, so every recruited person became a deployable
//  field inspector by omission — including the office and coordination hires.
//  The owner's decision is that this classification is chosen at the
//  requirement, confirmed at acceptance, and NEVER silently defaulted.
//
//  Nothing new is invented here. The vocabulary is the existing FIELD / COORD /
//  OFFICE. The question "does this workspace deploy people to site at all?" is
//  answered by the SAME check the Add-a-person form has always used —
//  licence_enabled('operations') — so the two screens cannot disagree.
// ============================================================================

const WF_TEAM_ROLES = [
    'FIELD'  => 'Field — goes to site',
    'COORD'  => 'Coordinator / office-based',
    'OFFICE' => 'Back office',
];

/** One of the three, or '' when the value is absent or not a real role. */
function wf_team_role_normalise($v) {
    $v = strtoupper(trim((string)$v));
    return isset(WF_TEAM_ROLES[$v]) ? $v : '';
}

/**
 * Does the Inspector / site-deployment concept exist in this workspace?
 *
 *   'NO'            a recruitment-only workspace. It does no site work, so a
 *                   hire is a workforce employee and nothing more. Inspector
 *                   functionality is NOT invented because somebody was hired.
 *   'YES'           Operations is on and the company has said what it does.
 *   'UNCONFIGURED'  Operations is on but the company has never chosen its
 *                   business activities. The owner's rule is that applicability
 *                   must NOT be inferred from silence — so acceptance asks
 *                   instead of guessing.
 */
function wf_ops_capability() {
    $opsOn = !function_exists('licence_enabled') || licence_enabled('operations');
    if (!$opsOn) return 'NO';

    //  NEVER MIGRATE INSIDE SOMEBODY ELSE'S TRANSACTION.
    //
    //  cockpit_capabilities() installs its table on first use, and on MariaDB
    //  ANY DDL commits the open transaction implicitly — so asking this question
    //  from inside a caller's transaction would silently commit that caller's
    //  half-written work and leave them with nothing to roll back. A conversion
    //  running inside a borrowed transaction hit exactly that, and the caller's
    //  rollBack() then failed because there was no longer a transaction to undo.
    //
    //  Inside a transaction the question is therefore answered by READING the
    //  table directly. If it does not exist yet, nothing has ever been
    //  configured, which is precisely 'UNCONFIGURED'.
    $inTx = false;
    try { $inTx = db()->inTransaction(); } catch (Throwable $e) {}
    if ($inTx) {
        try {
            $n = (int) ops_val("SELECT COUNT(*) FROM company_capabilities WHERE enabled=1");
            return $n > 0 ? 'YES' : 'UNCONFIGURED';
        } catch (Throwable $e) { return 'UNCONFIGURED'; }
    }

    if (function_exists('cockpit_capabilities')) {
        try { if (!cockpit_capabilities()) return 'UNCONFIGURED'; } catch (Throwable $e) {}
    }
    return 'YES';
}

/**
 * The role a given acceptance should record, and where it came from.
 *
 *   ['role' => 'FIELD'|'COORD'|'OFFICE'|'', 'source' => '…', 'why' => '…']
 *
 * An empty role is a REFUSAL, not a licence to fall back to FIELD. `why` is the
 * sentence the recruiter is shown, and it says what to do about it.
 */
function wf_team_role_resolve(array $cand, $posted = '') {
    $cap = wf_ops_capability();

    //  A workspace that does no site work has no field/office distinction to
    //  make. The Add-a-person form already records such people as OFFICE with
    //  an explicit hidden value rather than letting the database default decide;
    //  recruitment now does exactly the same thing, for the same reason.
    if ($cap === 'NO') return ['role' => 'OFFICE', 'source' => 'no-operations', 'why' => ''];

    //  The recruiter's confirmation at acceptance wins — that is the "visible
    //  and explicitly confirmed" half of the decision.
    $p = wf_team_role_normalise($posted);
    if ($p !== '') return ['role' => $p, 'source' => 'acceptance', 'why' => ''];

    //  Otherwise the requirement's own decision carries.
    $rqId = (int)($cand['requisition_id'] ?? 0);
    if ($rqId > 0) {
        $r = '';
        try { $r = (string)ops_val("SELECT team_role FROM requisitions WHERE id=?", [$rqId]); } catch (Throwable $e) {}
        $r = wf_team_role_normalise($r);
        if ($r !== '') return ['role' => $r, 'source' => 'requisition', 'why' => ''];
    }

    //  Nobody has decided. This is where the old code quietly wrote FIELD.
    return ['role' => '', 'source' => 'none',
            'why' => $cap === 'UNCONFIGURED'
                ? 'This workspace has not yet recorded what kind of business it does, so the system cannot tell whether this person is a field inspector or office staff. Choose which team they join, or set the workspace\'s business activities under Workspace setup.'
                : 'Nobody has said which team this person joins. Choose Field, Coordinator or Back office on the requirement, or confirm it here, and try again.'];
}

/** Is this hire a deployable Inspector? Only ever true where site work exists. */
function wf_inspector_applies($role) {
    return wf_ops_capability() === 'YES' && wf_team_role_normalise($role) === 'FIELD';
}

function rcv_refusal_before_transaction(array $cand, array $opt = []) {
    $candId = (int)($cand['id'] ?? 0);
    if ($candId <= 0) return RCV_CODES['NO_CANDIDATE'];
    if (function_exists('is_coordinator_level') && !is_coordinator_level()) return RCV_CODES['NOT_ALLOWED'];
    //  Scope: a record id is never proof of authorisation, and "not yours" is
    //  answered in the same words as "not there" so nothing can be enumerated.
    if (function_exists('connect_identity_scope_ok') && !connect_identity_scope_ok('candidate', $candId))
        return RCV_CODES['NO_CANDIDATE'];
    if (empty($opt['want_hire'])) return '';

    //  IDEMPOTENCY (§13) — nothing new is built for it. The application already
    //  records which team member it produced, and that column is the guard.
    if ((int)($cand['inspector_id'] ?? 0) > 0) return RCV_CODES['ALREADY'];

    //  BRANCH (BD1) — no branch, no hire, and never a silent default.
    if (function_exists('rcv_branch_for')) {
        [$office, ] = rcv_branch_for($candId, (int)($opt['actor_id'] ?? 0));
        if (!$office) return RCV_CODES['NO_BRANCH'];
    }

    //  WHICH TEAM (owner decision 2) — decided at the requirement, confirmed
    //  here, and never allowed to fall through to the database's FIELD default.
    //  Settled BEFORE the transaction like every other refusable question.
    if (function_exists('wf_team_role_resolve')) {
        $tr = wf_team_role_resolve($cand, (string)($opt['team_role'] ?? ''));
        if ($tr['role'] === '') return $tr['why'];
    }

    //  DUPLICATE STAFF + THE TICK — Step 2's canonical functions, called.
    if (function_exists('workforce_matches') && function_exists('workforce_ack_ok')) {
        $mm = workforce_matches($cand);
        if (workforce_strong_matches($mm)
            && !workforce_ack_ok((string)($opt['dup_ack'] ?? ''), $candId, $mm, $cand, (int)($opt['actor_id'] ?? 0)))
            return RCV_CODES['WORKFORCE_MATCH'];
    }
    return '';
}

/** Audit a conversion outcome against the candidate — the record it is about. */
function rcv_log($candId, $kind, $subject) {
    if (!function_exists('act_log')) return;
    try { act_log('CANDIDATE', (int)$candId, $kind, $subject, ['auto' => 0]); } catch (Throwable $e) {}
}

/**
 * Convert an accepted application into a team member.
 *
 * Returns a result that always corresponds to COMMITTED business state:
 *   ['ok'=>bool, 'code'=>string, 'message'=>string, 'inspector_id'=>int,
 *    'identity'=>'LINKED'|'NOT_ENTITLED'|'NONE', 'branch'=>int|null, 'branch_source'=>string]
 *
 *  'identity' is owner decision BD2 made explicit:
 *     LINKED        — STATE A: converted AND the identity relationship recorded
 *     NOT_ENTITLED  — STATE B: converted, and the relationship deliberately NOT
 *                     recorded because the marketplace capability is unavailable
 *  The two are never reported as the same thing, and STATE B is reported by
 *  identity_state_findings() so it can be linked later through the proper path.
 */
function rcv_convert($candId, array $opt = []) {
    $candId = (int)$candId;
    $fail = function ($code, $extra = '') use ($candId) {
        return ['ok' => false, 'code' => $code, 'message' => (RCV_CODES[$code] ?? $code) . ($extra ? ' ' . $extra : ''),
                'inspector_id' => 0, 'identity' => 'NONE', 'branch' => null, 'branch_source' => 'none'];
    };

    //  1 PERMISSION — asked here, not inherited from the route.
    if (function_exists('is_coordinator_level') && !is_coordinator_level()) return $fail('NOT_ALLOWED');
    //  2 TENANT — structural: db() is this tenant's database and nothing else is reachable.
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$candId]);
    if (!$cand) return $fail('NO_CANDIDATE');
    //  3 SCOPE — the actor must be able to open this application.
    if (function_exists('connect_identity_scope_ok') && !connect_identity_scope_ok('candidate', $candId))
        return $fail('NO_CANDIDATE');       // same words as "not there": no enumeration
    //  4 STATE — already converted is a refusal, not a second conversion.
    if ((int)($cand['inspector_id'] ?? 0) > 0) return $fail('ALREADY');
    //  5 THE LOCKED BOUNDARIES still decide first — M4's execution boundary and
    //     M6's seat gate are authoritative and are not re-implemented here.
    if (!empty($cand['requisition_id']) && function_exists('rexec_block_reason')) {
        $why = rexec_block_reason((int)$cand['requisition_id'], 'JOIN', $candId);
        if ($why !== '') { rcv_log($candId, 'IDENTITY_REFUSED', 'Conversion refused — ' . $why); return $fail('BLOCKED', $why); }
    }
    //  6 BRANCH — BD1. No branch, no conversion. Never a silent Ahmedabad.
    [$office, $src] = rcv_branch_for($candId, (int)($opt['actor_id'] ?? 0));
    if (!$office) {
        rcv_log($candId, 'IDENTITY_REFUSED', 'Conversion refused — no branch on the requirement or the recruiter');
        return $fail('NO_BRANCH');
    }
    //  6a WHICH TEAM — owner decision 2. Decided at the requirement, confirmed
    //     at acceptance, and NEVER allowed to fall through to the FIELD default
    //     that `inspectors.team_role` carries. Asked HERE as well as in the
    //     route's pre-transaction pass, because the action asks its own gates
    //     (invariant I27) and a forged POST must fail exactly as the screen does.
    //
    //     Like every other refusable question, it is settled BEFORE the
    //     transaction opens, so refusing writes nothing at all.
    $teamRole = 'OFFICE';
    if (function_exists('wf_team_role_resolve')) {
        $trR = wf_team_role_resolve($cand, (string)($opt['team_role'] ?? ''));
        if ($trR['role'] === '') {
            rcv_log($candId, 'IDENTITY_REFUSED', 'Conversion refused — no team was chosen for this hire');
            return $fail('TEAM_ROLE');
        }
        $teamRole = $trR['role'];
    }

    //  7 IDENTITY CAPABILITY — decided BEFORE the write so the outcome is known,
    //     and never a blocker on recruitment (BD2).
    $mayLink = function_exists('connect_identity_conversion_allowed') && connect_identity_conversion_allowed();

    //  7a IS THIS PERSON ALREADY ON THE TEAM? — owner decision 2, RB-3 Step 2.
    //
    //     Asked HERE, in the action, and not by the page: a forged POST that
    //     never rendered the warning must fail exactly as the screen does
    //     (invariant I27, and the instruction's "do not trust the UI").
    //
    //     Refusing writes NOTHING and leaves the application untouched — the
    //     refusal happens before the transaction is even opened.
    //
    //     Only a STRONG match stops a hire: a shared mobile number or a shared
    //     e-mail address, which are things a person chooses and owns. A shared
    //     NAME never stops anything. Rajesh Patel does not block Rajesh Patel.
    $wfAckNote = '';
    if (function_exists('workforce_matches')) {
        $wfAll    = workforce_matches($cand);
        $wfStrong = workforce_strong_matches($wfAll);
        if ($wfStrong) {
            $wfActor = (int)($opt['actor_id'] ?? 0);
            if (!workforce_ack_ok((string)($opt['dup_ack'] ?? ''), $candId, $wfAll, $cand, $wfActor)) {
                rcv_log($candId, 'IDENTITY_REFUSED',
                    'Conversion refused — ' . count($wfStrong) . ' possible existing team member(s) and no confirmation: '
                    . workforce_ack_note($wfAll));
                return $fail('WORKFORCE_MATCH');
            }
            //  Acknowledged. Record WHICH matches the recruiter was shown, not
            //  merely that a box was ticked — "they confirmed" is not evidence
            //  unless it says what they confirmed.
            $wfAckNote = ' — possible existing team member(s) reviewed and confirmed: ' . workforce_ack_note($wfAll);
        }
    }

    $name = function_exists('candidate_name') ? candidate_name($cand)
          : trim(((string)($cand['first_name'] ?? '')) . ' ' . ((string)($cand['last_name'] ?? '')));
    $ag   = (!empty($opt['agency_id']) && function_exists('agency_get')) ? agency_get((int)$opt['agency_id']) : null;
    $roll = (($opt['roll_type'] ?? '') === 'AGENCY') ? 'AGENCY' : 'OWN';
    $kind = ($roll === 'AGENCY') ? 'SUBCON' : 'ASSET';
    $placement = (float)($opt['placement_fee'] ?? 0);
    $gd = (int)($ag['guarantee_days'] ?? 90) ?: 90;

    //  8 THE TRANSACTION — the smallest correct business boundary: the team
    //     member, the relationship this conversion exists to create, and the
    //     ledger row that constrains it. Nothing downstream is dragged in to
    //     make it larger.
    //  If a caller already opened a transaction we JOIN it rather than opening a
    //  second one — and then we may neither commit nor roll back, because that
    //  work is not ours. Getting this wrong is not theoretical: catching the
    //  nested-begin exception and carrying on meant a failure inside a caller's
    //  transaction rolled back NOTHING and left the half-made team member for the
    //  caller to commit, while this function reported failure. A reported failure
    //  that commits a row is the exact defect this batch exists to remove, so on
    //  a borrowed transaction the failure is RE-THROWN and the caller unwinds.
    $own = true;
    try { $own = !db()->inTransaction(); } catch (Throwable $e) { $own = true; }
    $tx = false; $insId = 0; $identity = $mayLink ? 'LINKED' : 'NOT_ENTITLED';
    if ($own) { try { $tx = (bool)db()->beginTransaction(); } catch (Throwable $e) { $tx = false; } }
    try {
        //  A0 — TAKE THE APPLICATION'S ROW FIRST. Lock ordering, and it is not
        //       theoretical: with the employee number now protected by a unique
        //       index, four processes converting ONE application contended for
        //       two resources — the application row and the same employee-number
        //       key, which they all compute at the same instant — and acquired
        //       them in whatever order they arrived. InnoDB found a cycle and
        //       killed one transaction outright: a DEADLOCK in 3 runs out of 3,
        //       reported to the recruiter as a bare "could not be completed".
        //
        //       Everybody now queues on the SAME lock FIRST, so the order is the
        //       same for everybody and there is no cycle to find. The three who
        //       lose discover it here and leave without creating anything —
        //       which is also three team members and three employee numbers that
        //       are no longer made only to be rolled back.
        //
        //       SQLite has no row locks and needs none: it serialises writers
        //       across the whole database, so the first writer in is the only
        //       one inside this block at all.
        if (db_driver() !== 'sqlite') {
            $held = ops_one("SELECT COALESCE(inspector_id,0) ins FROM candidates WHERE id=? FOR UPDATE", [$candId]);
            if (!$held) throw new RuntimeException('RACE_LOST');
            if ((int)$held['ins'] > 0) throw new RuntimeException('RACE_LOST');
        }

        //  A — the team member.
        //
        //  The employee number is CLAIMED, not guessed (owner decision 1). Before
        //  this, next_emp_code() read the highest code and added one with nothing
        //  reserving the answer: four real processes hiring four different people
        //  at one wall-clock microsecond each received EMP01 on MariaDB, four
        //  times out of four, and all four reported success. Now the database
        //  holds the rule, and a number somebody took a microsecond ago simply
        //  costs this INSERT one more attempt.
        //
        //  Retrying inside this transaction is safe on BOTH engines — measured,
        //  not assumed: a UNIQUE violation rolls back the statement only and
        //  leaves the transaction open. And because nothing is reserved outside
        //  the row, a rollback below consumes no number at all.
        $writeInspector = function ($code) use ($name, $cand, $kind, $office, $ag, $opt, $roll, $placement, $gd, $teamRole) {
            db()->prepare("INSERT INTO inspectors (name,first_name,middle_name,last_name,email,mobile,trade_id,skill_ids,sbus,sbu,designation,staff_kind,emp_code,home_office_id,agency_id,roll_type,agency_name,agency_cost,placement_fee,fee_status,guarantee_upto,team_role,status,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ACTIVE',?)")
                ->execute([$name, $cand['first_name'], $cand['middle_name'], $cand['last_name'], $cand['email'], $cand['mobile'],
                           $cand['trade_id'], (string)($cand['skill_id'] ?: ''), $cand['sbu'], $cand['sbu'], $cand['designation'], $kind,
                           $code, $office,
                           $ag ? (int)$opt['agency_id'] : null, $roll, (string)($ag['name'] ?? ''), (float)($opt['agency_cost'] ?? 0),
                           $placement, $placement > 0 ? 'PROVISIONAL' : '', $placement > 0 ? date('Y-m-d', strtotime("+$gd days")) : '',
                           $teamRole,
                           date('c')]);
            return (int)db()->lastInsertId();
        };
        $insId = function_exists('emp_code_claim')
               ? (int)emp_code_claim($kind, $writeInspector)
               : (int)$writeInspector(function_exists('next_emp_code') ? next_emp_code($kind) : '');
        if ($insId <= 0) throw new RuntimeException('the team member could not be created');

        //  B — the relationship, claimed CONDITIONALLY. This is what makes the
        //      race safe with or without the marketplace: a second process that
        //      got this far a microsecond ago has already set the column, so
        //      this matches nothing and the whole transaction goes back.
        $st = db()->prepare("UPDATE candidates SET inspector_id=? WHERE id=? AND (inspector_id IS NULL OR inspector_id=0)");
        $st->execute([$insId, $candId]);
        if ($st->rowCount() < 1) throw new RuntimeException('RACE_LOST');

        //  C — the identity ledger, only when the workspace may record identity.
        if ($mayLink) {
            [$lok, $lmsg] = connect_identity_conversion_link_create($candId, $insId, 'conversion', '', 'recruitment conversion');
            if (!$lok) throw new RuntimeException('RACE_LOST');
        }
        if ($own && $tx) db()->commit();
    } catch (Throwable $e) {
        if ($tx) { try { db()->rollBack(); } catch (Throwable $e2) {} }
        $lost = strpos($e->getMessage(), 'RACE_LOST') !== false
             || (function_exists('connect_identity_is_duplicate') && connect_identity_is_duplicate($e));
        //  The claim gave up rather than hand out a number somebody already has.
        //  It is a different thing from a lost race and says so (decision 5).
        $noCode = strpos($e->getMessage(), 'free employee number') !== false;
        //  A deadlock or a lock-wait timeout aborts the WHOLE transaction, so no
        //  retry inside it can help. It is not a defect in the hire and must not
        //  read like one: the work is intact, it simply has to be done again.
        $busy = strpos($e->getMessage(), '40001') !== false
             || stripos($e->getMessage(), 'deadlock') !== false
             || stripos($e->getMessage(), 'lock wait timeout') !== false
             || stripos($e->getMessage(), 'database is locked') !== false;
        rcv_log($candId, 'IDENTITY_REFUSED', 'Conversion rolled back — ' . ($lost ? 'another process converted it first' : $e->getMessage()));
        //  Borrowed transaction: we could not undo the half-made work, so we must
        //  not pretend it is gone. The caller owns the rollback and is told.
        if (!$own) throw $e;
        return $fail($lost ? 'RACE_LOST' : ($noCode ? 'EMP_CODE' : ($busy ? 'BUSY' : 'FAILED')));
    }

    //  9 AUDIT — outside the transaction. A failed observation is never a failed
    //     transaction (invariant I41): the hire stands whatever the audit does.
    rcv_log($candId, 'IDENTITY_LINKED',
        'Converted to team member #' . $insId . ' (branch from ' . $src . ')'
        . ($identity === 'LINKED' ? ' — identity relationship recorded'
                                  : ' — identity relationship NOT recorded (marketplace capability unavailable)')
        . $wfAckNote);

    return ['ok' => true, 'code' => 'CONVERTED', 'message' => RCV_CODES['CONVERTED'], 'inspector_id' => $insId,
            'identity' => $identity, 'branch' => $office, 'branch_source' => $src];
}
