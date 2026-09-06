<?php
// ============================================================================
//  EXAACT Recruitment — Salary structure, HR discussion, Offer & Onboarding
//  (Phase 5; brief §25–§27, §32–§34). Additive and non-destructive.
//
//  A salary structure is built from components (not one CTC field) and compared
//  to the candidate's expectation, an internal benchmark and the approved
//  budget. An offer is generated from an approved structure and moves through a
//  controlled lifecycle — an UNAPPROVED offer can never be issued (§32). On
//  acceptance the candidate is handed to onboarding, which REUSES the existing
//  candidate → inspector person spine (no duplicate person, §34).
//
//  Salary figures are shown only to users with `data.salary` (can_see_salary()).
// ============================================================================

// Salary components (§25) — ordered [column => label].
function sal_components() {
    return [
        'basic'             => 'Basic',
        'hra'               => 'HRA',
        'conveyance'        => 'Conveyance',
        'special_allowance' => 'Special allowance',
        'other_fixed'       => 'Other fixed',
        'variable_pay'      => 'Variable pay',
        'bonus'             => 'Bonus',
        'benefits'          => 'Benefits',
        'joining_bonus'     => 'Joining bonus',
        'incentives'        => 'Incentives',
    ];
}

const OFFER_STATUS = [
    'DRAFT'            => 'Draft',
    'PENDING_APPROVAL' => 'Pending approval',
    'APPROVED'         => 'Approved',
    'ISSUED'           => 'Issued',
    'VIEWED'           => 'Viewed',
    'ACCEPTED'         => 'Accepted',
    'DECLINED'         => 'Declined',
    'EXPIRED'          => 'Expired',
    'WITHDRAWN'        => 'Withdrawn',
];
const HRD_OUTCOMES = ['PENDING' => 'Pending', 'AGREED' => 'Agreed', 'REVISION' => 'Revision requested', 'DECLINED' => 'Declined'];

// ---- Schema (one migrate, three tables; wired into boot) -------------------
function recruit_offer_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $lt = (function_exists('db_driver') && db_driver() === 'sqlite') ? 'TEXT' : 'LONGTEXT';
    try {
        $comp = '';
        foreach (array_keys(sal_components()) as $c) $comp .= "$c DECIMAL(14,2) DEFAULT 0, ";
        db()->exec("CREATE TABLE IF NOT EXISTS salary_structures (
            id $pk,
            candidate_id INT,
            currency VARCHAR(8) DEFAULT '',
            $comp
            gross_ctc DECIMAL(14,2) DEFAULT 0,
            candidate_expected DECIMAL(14,2) DEFAULT 0,
            internal_benchmark DECIMAL(14,2) DEFAULT 0,
            approved_budget DECIMAL(14,2) DEFAULT 0,
            notes VARCHAR(400) DEFAULT '',
            created_by VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS hr_discussions (
            id $pk,
            candidate_id INT,
            discussion_date VARCHAR(20) DEFAULT '',
            hr_rep VARCHAR(160) DEFAULT '',
            candidate_expectation DECIMAL(14,2) DEFAULT 0,
            offered_amount DECIMAL(14,2) DEFAULT 0,
            agreed_amount DECIMAL(14,2) DEFAULT 0,
            terms VARCHAR(500) DEFAULT '',
            comments $lt,
            outcome VARCHAR(20) DEFAULT 'PENDING',
            created_at VARCHAR(30) DEFAULT ''
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS job_offers (
            id $pk,
            candidate_id INT,
            salary_structure_id INT NULL,
            ctc DECIMAL(14,2) DEFAULT 0,
            joining_date VARCHAR(20) DEFAULT '',
            offer_terms $lt,
            status VARCHAR(20) DEFAULT 'DRAFT',
            letter_html $lt,
            approved_by VARCHAR(160) DEFAULT '',
            approved_at VARCHAR(30) DEFAULT '',
            issued_by VARCHAR(160) DEFAULT '',
            issued_at VARCHAR(30) DEFAULT '',
            viewed_at VARCHAR(30) DEFAULT '',
            accepted_at VARCHAR(30) DEFAULT '',
            declined_at VARCHAR(30) DEFAULT '',
            decline_reason VARCHAR(300) DEFAULT '',
            expiry_date VARCHAR(20) DEFAULT '',
            created_by VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
        // Phase 5.1A — computed totals + the component lines (configurable comp).
        ensure_column('salary_structures', 'net_pay', "DECIMAL(14,2) DEFAULT 0");
        ensure_column('salary_structures', 'total_deductions', "DECIMAL(14,2) DEFAULT 0");
        ensure_column('salary_structures', 'employer_cost', "DECIMAL(14,2) DEFAULT 0");
        ensure_column('salary_structures', 'lines_json', $lt);
        if (function_exists('act_index')) {
            act_index('salary_structures', 'idx_sal_cand', '(candidate_id)');
            act_index('hr_discussions', 'idx_hrd_cand', '(candidate_id)');
            act_index('job_offers', 'idx_off_cand', '(candidate_id)');
        }
    } catch (Throwable $e) { /* never break boot */ }
}

function _off_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function _off_actor() { return function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'system'; }
function _off_cur() { return function_exists('cur_sym') ? cur_sym() : (function_exists('setting_get') ? (setting_get('currency_symbol', '₹') ?: '₹') : '₹'); }

// ============================================================================
//  Salary structure
// ============================================================================
function sal_current($candidateId) {
    recruit_offer_migrate();
    return ops_one("SELECT * FROM salary_structures WHERE candidate_id=? ORDER BY id DESC LIMIT 1", [(int)$candidateId]) ?: null;
}
// The component lines of a structure — from the configurable engine (lines_json)
// if present, else reconstructed from the legacy fixed columns (back-compat).
function sal_lines($row) {
    if (!empty($row['lines_json'])) {
        $j = json_decode((string)$row['lines_json'], true);
        if (is_array($j)) return array_values(array_filter($j, fn($l) => (float)($l['amount'] ?? 0) != 0));
    }
    $out = [];
    foreach (sal_components() as $k => $lbl) {
        $v = (float)($row[$k] ?? 0); if ($v == 0) continue;
        $out[] = ['code' => strtoupper($k), 'name' => $lbl, 'section' => 'EARNING', 'amount' => $v, 'statutory' => 0, 'taxable' => 1];
    }
    return $out;
}
function sal_total($row) {
    // Gross earnings = sum of EARNING lines.
    $t = 0.0; foreach (sal_lines($row) as $l) if (($l['section'] ?? 'EARNING') === 'EARNING') $t += (float)$l['amount'];
    if ($t == 0) foreach (array_keys(sal_components()) as $c) $t += (float)($row[$c] ?? 0);
    return $t;
}
function sal_save($candidateId, $post) {
    recruit_offer_migrate();
    // Drive off the configurable component definitions (Phase 5.1A).
    if (function_exists('comp_defs') && function_exists('comp_compute')) {
        $inputs = [];
        foreach (comp_defs(true) as $d) if ($d['calc'] === 'FIXED') $inputs[$d['code']] = (float)($post['c_' . $d['code']] ?? 0);
        $r = comp_compute($inputs);
        db()->prepare("INSERT INTO salary_structures (candidate_id,currency,gross_ctc,net_pay,total_deductions,employer_cost,lines_json,candidate_expected,internal_benchmark,approved_budget,notes,created_by,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([(int)$candidateId, _off_cur(), $r['ctc'], $r['net'], $r['deductions'], $r['employer'], json_encode($r['lines']),
                (float)($post['candidate_expected'] ?? 0), (float)($post['internal_benchmark'] ?? 0), (float)($post['approved_budget'] ?? 0),
                trim((string)($post['notes'] ?? '')), _off_actor(), _off_now()]);
        return (int)db()->lastInsertId();
    }
    // Fallback (no comp engine): legacy fixed columns.
    $cols = array_keys(sal_components());
    $vals = []; foreach ($cols as $c) $vals[$c] = (float)($post['c_' . strtoupper($c)] ?? $post[$c] ?? 0);
    $gross = array_sum($vals);
    $set = implode(',', $cols);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare("INSERT INTO salary_structures (candidate_id,currency,$set,gross_ctc,candidate_expected,internal_benchmark,approved_budget,notes,created_by,created_at)
                   VALUES (?,?, $ph, ?,?,?,?,?,?,?)")
        ->execute(array_merge([(int)$candidateId, _off_cur()], array_values($vals),
            [$gross, (float)($post['candidate_expected'] ?? 0), (float)($post['internal_benchmark'] ?? 0),
             (float)($post['approved_budget'] ?? 0), trim((string)($post['notes'] ?? '')), _off_actor(), _off_now()]));
    return (int)db()->lastInsertId();
}
// Variance vs budget / expectation (positive = over).
function sal_variance($row) {
    $gross = (float)($row['gross_ctc'] ?? sal_total($row));
    return [
        'gross'       => $gross,
        'vs_budget'   => (float)($row['approved_budget'] ?? 0) > 0 ? $gross - (float)$row['approved_budget'] : null,
        'vs_expected' => (float)($row['candidate_expected'] ?? 0) > 0 ? $gross - (float)$row['candidate_expected'] : null,
        'vs_benchmark'=> (float)($row['internal_benchmark'] ?? 0) > 0 ? $gross - (float)$row['internal_benchmark'] : null,
    ];
}

// ============================================================================
//  HR discussion (§26)
// ============================================================================
function hrd_list($candidateId) {
    recruit_offer_migrate();
    return ops_all("SELECT * FROM hr_discussions WHERE candidate_id=? ORDER BY id DESC", [(int)$candidateId]);
}
function hrd_save($candidateId, $post) {
    recruit_offer_migrate();
    $out = array_key_exists($post['outcome'] ?? '', HRD_OUTCOMES) ? $post['outcome'] : 'PENDING';
    db()->prepare("INSERT INTO hr_discussions (candidate_id,discussion_date,hr_rep,candidate_expectation,offered_amount,agreed_amount,terms,comments,outcome,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$candidateId, trim((string)($post['discussion_date'] ?? '')), trim((string)($post['hr_rep'] ?? '')),
            (float)($post['candidate_expectation'] ?? 0), (float)($post['offered_amount'] ?? 0), (float)($post['agreed_amount'] ?? 0),
            trim((string)($post['terms'] ?? '')), trim((string)($post['comments'] ?? '')), $out, _off_now()]);
    return (int)db()->lastInsertId();
}

// ============================================================================
//  Offer — controlled lifecycle (§32–§33)
// ============================================================================
function offer_current($candidateId) {
    recruit_offer_migrate();
    return ops_one("SELECT * FROM job_offers WHERE candidate_id=? ORDER BY id DESC LIMIT 1", [(int)$candidateId]) ?: null;
}
function offer_get($id) { recruit_offer_migrate(); return ops_one("SELECT * FROM job_offers WHERE id=?", [(int)$id]) ?: null; }

function offer_create($candidateId, $post) {
    recruit_offer_migrate();
    $sal = sal_current($candidateId);
    $ctc = (float)($post['ctc'] ?? 0) ?: ($sal ? (float)$sal['gross_ctc'] : 0);
    db()->prepare("INSERT INTO job_offers (candidate_id,salary_structure_id,ctc,joining_date,offer_terms,status,expiry_date,created_by,created_at)
                   VALUES (?,?,?,?,?, 'DRAFT', ?,?,?)")
        ->execute([(int)$candidateId, $sal['id'] ?? null, $ctc, trim((string)($post['joining_date'] ?? '')),
            trim((string)($post['offer_terms'] ?? '')), trim((string)($post['expiry_date'] ?? '')), _off_actor(), _off_now()]);
    return (int)db()->lastInsertId();
}
// Build the matching context for the configurable approval engine (Phase 6)
// from the candidate's requisition: business unit, grade, position and value.
function offer_appr_ctx($o) {
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int)$o['candidate_id']]) ?: [];
    $req = !empty($cand['requisition_id']) ? (ops_one("SELECT * FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]) ?: []) : [];
    // Department for rule-matching: the requisition's department first, then the
    // candidate's, then the business unit as a last resort. (Previously this used
    // the SBU alone, so an approval rule keyed on Department never matched a
    // requisition that set a department but no SBU.)
    $dept = (string)($req['department'] ?? '');
    if ($dept === '') $dept = (string)($cand['department'] ?? '');
    if ($dept === '') $dept = (string)($req['sbu'] ?? '');
    return [
        'department' => $dept,
        'sbu'        => (string)($req['sbu'] ?? ''),
        'grade'      => (string)($req['grade'] ?? ''),
        'position'   => (string)($req['designation'] ?? ''),
        'amount'     => (float)($o['ctc'] ?? 0),
        '_cand'      => $cand,
    ];
}
function offer_submit($id) {
    $o = offer_get($id); if (!$o || $o['status'] !== 'DRAFT') return [false, 'Only a draft can be submitted for approval.'];
    db()->prepare("UPDATE job_offers SET status='PENDING_APPROVAL' WHERE id=?")->execute([(int)$id]);
    // Phase 6 — if a configurable approval rule matches, route the offer through
    // the chain (SLA + reminders + escalation). The manual admin approve coexists
    // as a fallback for tenants that have configured no rule.
    if (function_exists('appr_start')) {
        $ctx = offer_appr_ctx($o);
        $subject = 'Offer' . (!empty($ctx['_cand']['name']) ? ' — ' . $ctx['_cand']['name'] : (' #' . (int)$id))
                 . (($ctx['position'] ?? '') !== '' ? ' (' . $ctx['position'] . ')' : '');
        [$started] = appr_start('OFFER', (int)$id, $ctx, $subject, (float)($o['ctc'] ?? 0));
        if ($started) return [true, 'Offer submitted — routed to the approval chain.'];
    }
    return [true, 'Offer submitted for approval.'];
}
function offer_approve($id) {
    $o = offer_get($id); if (!$o || !in_array($o['status'], ['PENDING_APPROVAL','DRAFT'], true)) return [false, 'This offer is not awaiting approval.'];
    db()->prepare("UPDATE job_offers SET status='APPROVED', approved_by=?, approved_at=? WHERE id=?")->execute([_off_actor(), _off_now(), (int)$id]);
    return [true, 'Offer approved — it can now be issued.'];
}
function offer_issue($id) {
    $o = offer_get($id);
    // §32 — an UNAPPROVED offer can never be issued.
    if (!$o) return [false, 'Offer not found.'];
    if ($o['status'] !== 'APPROVED') return [false, 'The offer must be approved before it can be issued.'];
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int)$o['candidate_id']]);
    // Prefer a configurable OFFER template (Phase 5.1B); fall back to the built-in letter.
    $letter = '';
    if (function_exists('doc_tpl_by_code') && function_exists('doc_render_template')) {
        $tpl = doc_tpl_by_code('OFFER');
        if ($tpl) { $r = doc_render_template($tpl, $cand); $letter = $r['html']; }
    }
    if ($letter === '') $letter = offer_letter_html($o, $cand, sal_current((int)$o['candidate_id']));
    db()->prepare("UPDATE job_offers SET status='ISSUED', issued_by=?, issued_at=?, letter_html=? WHERE id=?")
        ->execute([_off_actor(), _off_now(), $letter, (int)$id]);
    // Coarse-sync the candidate's legacy stage to OFFERED (unless already closed).
    if ($cand && !in_array($cand['stage'], ['ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED'], true))
        db()->prepare("UPDATE candidates SET stage='OFFERED' WHERE id=?")->execute([(int)$o['candidate_id']]);
    return [true, 'Offer issued. Share the letter with the candidate.'];
}
function offer_accept($id) {
    $o = offer_get($id); if (!$o || !in_array($o['status'], ['ISSUED','VIEWED'], true)) return [false, 'Only an issued offer can be accepted.'];
    db()->prepare("UPDATE job_offers SET status='ACCEPTED', accepted_at=? WHERE id=?")->execute([_off_now(), (int)$id]);
    return [true, 'Offer accepted — proceed to onboarding.'];
}
function offer_decline($id, $reason) {
    $o = offer_get($id); if (!$o || !in_array($o['status'], ['ISSUED','VIEWED'], true)) return [false, 'Only an issued offer can be declined.'];
    db()->prepare("UPDATE job_offers SET status='DECLINED', declined_at=?, decline_reason=? WHERE id=?")->execute([_off_now(), substr((string)$reason, 0, 300), (int)$id]);
    return [true, 'Offer marked declined.'];
}
function offer_withdraw($id) {
    $o = offer_get($id); if (!$o || in_array($o['status'], ['ACCEPTED','DECLINED','WITHDRAWN','EXPIRED'], true)) return [false, 'This offer cannot be withdrawn.'];
    db()->prepare("UPDATE job_offers SET status='WITHDRAWN' WHERE id=?")->execute([(int)$id]);
    return [true, 'Offer withdrawn.'];
}

// The default offer-letter template (tokens are replaced). Overridable per tenant
// via setting 'offer_letter_template'.
function offer_letter_template() {
    $t = function_exists('setting_get') ? trim((string)setting_get('offer_letter_template', '')) : '';
    if ($t !== '') return $t;
    return "Dear {name},\n\nWe are pleased to offer you the position of {position} at {company}.\n\n"
        . "Your total annual compensation (CTC) will be {ctc}. Your expected date of joining is {joining_date}.\n\n"
        . "{terms}\n\nWe look forward to welcoming you to the team.\n\nWarm regards,\n{company}\nDate: {date}";
}
function offer_letter_html($offer, $cand, $sal) {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cur = _off_cur();
    $name = function_exists('candidate_name') && $cand ? candidate_name($cand) : ($cand['first_name'] ?? 'Candidate');
    $position = $cand['designation'] ?? '';
    if (function_exists('DESIGNATIONS') || defined('DESIGNATIONS')) $position = (defined('DESIGNATIONS') && isset(DESIGNATIONS[$position])) ? DESIGNATIONS[$position] : ($position ?: 'the role');
    $company = function_exists('app_name') ? app_name() : (function_exists('setting_get') ? setting_get('app_name', 'the Company') : 'the Company');
    $tokens = [
        '{name}'         => $name,
        '{position}'     => $position ?: 'the role',
        '{company}'      => $company,
        '{ctc}'          => $cur . number_format((float)$offer['ctc'], 0),
        '{joining_date}' => $offer['joining_date'] ? (function_exists('fdate') ? fdate($offer['joining_date'], $offer['joining_date']) : $offer['joining_date']) : '—',
        '{terms}'        => (string)$offer['offer_terms'],
        '{date}'         => date('d M Y'),
    ];
    $body = strtr(offer_letter_template(), $tokens);
    $html = '<div style="font-family:Georgia,serif;max-width:720px;margin:auto;color:#1a2230;line-height:1.6">'
          . '<div style="white-space:pre-wrap;font-size:15px">' . $e($body) . '</div>';
    // Salary-structure annexure (configurable component breakdown, grouped).
    if ($sal) {
        $lines = sal_lines($sal);
        $secLbl = ['EARNING' => 'Earnings', 'DEDUCTION' => 'Deductions', 'EMPLOYER' => 'Employer contributions'];
        $html .= '<h3 style="margin:22px 0 8px">Annexure — Compensation structure</h3><table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif">';
        foreach (['EARNING', 'DEDUCTION', 'EMPLOYER'] as $sec) {
            $secLines = array_filter($lines, fn($l) => ($l['section'] ?? 'EARNING') === $sec);
            if (!$secLines) continue;
            $html .= '<tr><td colspan="2" style="padding:8px 8px 3px;font-weight:700;color:#555;font-size:12px">' . $e($secLbl[$sec]) . '</td></tr>';
            foreach ($secLines as $l)
                $html .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #eee">' . $e($l['name']) . ((int)($l['statutory'] ?? 0) ? ' <span style="color:#999;font-size:10px">(statutory)</span>' : '') . '</td><td style="padding:4px 8px;border-bottom:1px solid #eee;text-align:right">' . $e($cur . number_format((float)$l['amount'], 0)) . '</td></tr>';
        }
        $ctc = (float)($sal['gross_ctc'] ?? sal_total($sal));
        $html .= '<tr><td style="padding:7px 8px;font-weight:700;border-top:2px solid #333">Total CTC</td><td style="padding:7px 8px;text-align:right;font-weight:700;border-top:2px solid #333">' . $e($cur . number_format($ctc, 0)) . '</td></tr>';
        if ((float)($sal['net_pay'] ?? 0) > 0)
            $html .= '<tr><td style="padding:4px 8px;color:#555">Net pay</td><td style="padding:4px 8px;text-align:right;color:#555">' . $e($cur . number_format((float)$sal['net_pay'], 0)) . '</td></tr>';
        $html .= '</table>';
    }
    return $html . '</div>';
}

// ============================================================================
//  Routes
// ============================================================================
function ops_candidate_offer($route, $method) {
    recruit_offer_migrate();
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }

    if ($route === 'offer-letter') {
        ops_require(can('mod.hiring.view'), 'You cannot view offers.');
        $o = offer_current($id);
        if (!$o || !$o['letter_html']) { flash('No issued offer letter yet.', 'warning'); redirect('/candidate?id=' . $id); return true; }
        // A standalone printable letter.
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Offer letter</title><body style="padding:32px;background:#fff">';
        echo '<div style="max-width:760px;margin:auto"><button onclick="window.print()" style="float:right;padding:8px 14px;border:1px solid #ccc;border-radius:6px;cursor:pointer" class="no-print">Print / Save PDF</button>';
        echo $o['letter_html'];
        echo '</div><style>@media print{.no-print{display:none}}</style></body>';
        return true;
    }

    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage offers.');
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'sal_save') { sal_save($id, $_POST); flash('Salary structure saved.'); }
        elseif ($do === 'hrd_save') { hrd_save($id, $_POST); flash('HR discussion recorded.'); }
        elseif ($do === 'offer_create') { offer_create($id, $_POST); flash('Offer drafted.'); }
        elseif ($do === 'submit') { [$ok, $m] = offer_submit((int)($_POST['offer_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'approve') { ops_require(is_admin_level(), 'Only a manager can approve an offer.'); [$ok, $m] = offer_approve((int)($_POST['offer_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'issue') { [$ok, $m] = offer_issue((int)($_POST['offer_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'accept') { [$ok, $m] = offer_accept((int)($_POST['offer_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'decline') { [$ok, $m] = offer_decline((int)($_POST['offer_id'] ?? 0), $_POST['reason'] ?? ''); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'withdraw') { [$ok, $m] = offer_withdraw((int)($_POST['offer_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
    }
    redirect('/candidate?id=' . $id . '#tab=Offer');
    return true;
}

// ============================================================================
//  The Offer tab panel
// ============================================================================
function recruit_offer_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $cid = (int)$cand['id'];
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cur = _off_cur();
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $canApprove = function_exists('is_admin_level') && is_admin_level();
    $seeSal = function_exists('can_see_salary') ? can_see_salary() : $can;
    $sal = sal_current($cid); $hrds = hrd_list($cid); $offer = offer_current($cid);
    $comp = sal_components();
    $money = fn($v) => $cur . number_format((float)$v, 0);
    $act = "/candidate-offer?id=$cid";
    $stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','ISSUED'=>'p-info','VIEWED'=>'p-info','ACCEPTED'=>'p-ok','DECLINED'=>'p-bad','EXPIRED'=>'p-bad','WITHDRAWN'=>'p-mut'];
    ?>
    <div class="panel">
      <h3 class="tab-sub">Salary structure</h3>
      <?php if (!$seeSal): ?>
        <p class="muted">Compensation is restricted — you do not have salary access.</p>
      <?php else:
        $var = $sal ? sal_variance($sal) : null;
        $lines = $sal ? sal_lines($sal) : [];
        $secLbl = ['EARNING'=>'Earnings','DEDUCTION'=>'Deductions (employee)','EMPLOYER'=>'Employer contributions'];
        $defs = function_exists('comp_defs') ? comp_defs(true) : [];
        $cur_amt = []; foreach ($lines as $l) $cur_amt[$l['code']] = (float)$l['amount']; ?>
        <?php if ($sal): ?>
          <table style="width:100%;border-collapse:collapse;max-width:560px">
            <?php foreach (['EARNING','DEDUCTION','EMPLOYER'] as $sec): $secLines = array_filter($lines, fn($l)=>($l['section']??'EARNING')===$sec); if (!$secLines) continue; ?>
              <tr><td colspan="2" style="padding:8px 8px 2px;font-weight:700;font-size:11px;text-transform:uppercase;color:var(--muted,#656e7a)"><?= $e($secLbl[$sec]) ?></td></tr>
              <?php foreach ($secLines as $l): ?>
                <tr><td style="padding:4px 8px;border-bottom:1px solid var(--line,#eef1f5)"><?= $e($l['name']) ?><?= (int)($l['statutory']??0)?' <span class="pill p-mut" style="font-size:9.5px">statutory</span>':'' ?></td><td style="padding:4px 8px;border-bottom:1px solid var(--line,#eef1f5);text-align:right"><?= $e($money($l['amount'])) ?></td></tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <tr><td style="padding:6px 8px;font-weight:700;border-top:2px solid var(--ink,#333)">Total CTC</td><td style="padding:6px 8px;text-align:right;font-weight:700;border-top:2px solid var(--ink,#333)"><?= $e($money($sal['gross_ctc'])) ?></td></tr>
            <?php if ((float)($sal['net_pay']??0)>0): ?><tr><td style="padding:4px 8px;color:var(--muted,#656e7a)">Net pay (in-hand)</td><td style="padding:4px 8px;text-align:right;color:var(--muted,#656e7a)"><?= $e($money($sal['net_pay'])) ?></td></tr><?php endif; ?>
          </table>
          <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:12.5px">
            <?php if ($var['vs_budget'] !== null): ?><span>Budget <?= $e($money($sal['approved_budget'])) ?> · <span class="pill <?= $var['vs_budget']>0?'p-bad':'p-ok' ?>"><?= ($var['vs_budget']>0?'+':'') . $e($money($var['vs_budget'])) ?></span></span><?php endif; ?>
            <?php if ($var['vs_expected'] !== null): ?><span>Candidate expected <?= $e($money($sal['candidate_expected'])) ?> · <span class="pill <?= $var['vs_expected']<0?'p-warn':'p-ok' ?>"><?= ($var['vs_expected']>0?'+':'') . $e($money($var['vs_expected'])) ?></span></span><?php endif; ?>
          </div>
        <?php else: ?><p class="muted">No salary structure yet.</p><?php endif; ?>
        <?php if ($can): ?>
        <details style="margin-top:10px"><summary style="cursor:pointer;font-size:12.5px;color:var(--brand,#1e40af)"><?= $sal ? 'Revise the salary structure' : 'Build the salary structure' ?></summary>
          <form method="post" action="<?= $act ?>" style="margin-top:8px"><input type="hidden" name="do" value="sal_save">
            <p class="muted" style="margin:0 0 6px;font-size:11.5px">Enter the fixed amounts; % components compute automatically. Headings are set under <a href="/comp-setup">Compensation setup</a>.</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
              <?php foreach ($defs as $d): ?>
                <div><label class="ff-l"><?= $e($d['name']) ?><?= $d['calc']!=='FIXED'?' <span style="color:var(--muted,#94a3b8)">('.$e(rtrim(rtrim(number_format((float)$d['rate'],2,'.',''),'0'),'.')).'% '.($d['calc']==='PCT_BASIC'?'basic':'gross').')</span>':'' ?></label>
                  <?php if ($d['calc']==='FIXED'): ?>
                    <input class="form-control" type="number" step="any" name="c_<?= $e($d['code']) ?>" value="<?= isset($cur_amt[$d['code']])?$cur_amt[$d['code']]:'' ?>">
                  <?php else: ?>
                    <input class="form-control" value="auto" disabled style="background:#f1f5f9;color:#94a3b8">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px">
              <div><label class="ff-l">Candidate expected (CTC)</label><input class="form-control" type="number" step="any" name="candidate_expected" value="<?= $sal ? (float)$sal['candidate_expected'] : '' ?>"></div>
              <div><label class="ff-l">Internal benchmark</label><input class="form-control" type="number" step="any" name="internal_benchmark" value="<?= $sal ? (float)$sal['internal_benchmark'] : '' ?>"></div>
              <div><label class="ff-l">Approved budget</label><input class="form-control" type="number" step="any" name="approved_budget" value="<?= $sal ? (float)$sal['approved_budget'] : '' ?>"></div>
            </div>
            <div style="margin-top:8px"><button class="btn">Save structure</button></div>
          </form>
        </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3 class="tab-sub">HR discussion</h3>
      <?php if ($can): ?>
      <details style="margin-bottom:10px"><summary style="cursor:pointer;font-size:12.5px;color:var(--brand,#1e40af)">Record a discussion</summary>
        <form method="post" action="<?= $act ?>" style="margin-top:8px"><input type="hidden" name="do" value="hrd_save">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
            <div><label class="ff-l">Date</label><input class="form-control" type="date" name="discussion_date"></div>
            <div><label class="ff-l">HR representative</label><input class="form-control" name="hr_rep"></div>
            <div><label class="ff-l">Outcome</label><select class="form-control" name="outcome"><?php foreach (HRD_OUTCOMES as $k=>$v): ?><option value="<?= $k ?>"><?= $e($v) ?></option><?php endforeach; ?></select></div>
            <?php if ($seeSal): ?>
            <div><label class="ff-l">Candidate expectation</label><input class="form-control" type="number" step="any" name="candidate_expectation"></div>
            <div><label class="ff-l">Offered</label><input class="form-control" type="number" step="any" name="offered_amount"></div>
            <div><label class="ff-l">Agreed</label><input class="form-control" type="number" step="any" name="agreed_amount"></div>
            <?php endif; ?>
          </div>
          <label class="ff-l" style="margin-top:8px">Terms</label><input class="form-control" name="terms">
          <label class="ff-l" style="margin-top:8px">Comments</label><textarea class="form-control" name="comments" rows="2"></textarea>
          <div style="margin-top:8px"><button class="btn">Save discussion</button></div>
        </form>
      </details>
      <?php endif; ?>
      <?php if (!$hrds): ?><p class="muted">No HR discussions yet.</p><?php else: foreach ($hrds as $h): ?>
        <div style="border:1px solid var(--line,#e5e7eb);border-radius:9px;padding:9px 12px;margin-bottom:8px;font-size:12.5px">
          <b><?= $e($h['discussion_date'] ?: '—') ?></b> · <?= $e($h['hr_rep'] ?: '—') ?> · <span class="pill <?= $h['outcome']==='AGREED'?'p-ok':($h['outcome']==='DECLINED'?'p-bad':'p-warn') ?>"><?= $e(HRD_OUTCOMES[$h['outcome']] ?? $h['outcome']) ?></span>
          <?php if ($seeSal && ((float)$h['agreed_amount'] || (float)$h['offered_amount'])): ?><div class="muted">Expected <?= $e($money($h['candidate_expectation'])) ?> · Offered <?= $e($money($h['offered_amount'])) ?> · Agreed <?= $e($money($h['agreed_amount'])) ?></div><?php endif; ?>
          <?php if ($h['terms']): ?><div class="muted">Terms: <?= $e($h['terms']) ?></div><?php endif; ?>
          <?php if ($h['comments']): ?><div><?= $e($h['comments']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="panel">
      <h3 class="tab-sub">Offer</h3>
      <?php if ($offer): $st = $offer['status']; ?>
        <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">
          <span class="pill <?= $stPill[$st] ?? 'p-mut' ?>" style="font-size:12px"><?= $e(OFFER_STATUS[$st] ?? $st) ?></span>
          <?php if ($seeSal): ?><b><?= $e($money($offer['ctc'])) ?></b><?php endif; ?>
          <?php if ($offer['joining_date']): ?><span class="muted">Joining <?= $e($offer['joining_date']) ?></span><?php endif; ?>
          <?php if ($offer['approved_by'] && in_array($st,['APPROVED','ISSUED','VIEWED','ACCEPTED'],true)): ?><span class="muted">· Approved by <?= $e($offer['approved_by']) ?></span><?php endif; ?>
        </div>
        <?php if ($offer['decline_reason']): ?><div class="p-bad" style="font-size:12.5px;margin-top:4px">Declined: <?= $e($offer['decline_reason']) ?></div><?php endif; ?>
        <?php if ($can): ?>
        <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:10px">
          <?php if ($st === 'DRAFT'): ?>
            <?php echo _offer_btn($act, 'submit', $offer['id'], 'Submit for approval', 'btn'); ?>
            <?php if ($canApprove) echo _offer_btn($act, 'approve', $offer['id'], 'Approve', 'btn secondary'); ?>
          <?php elseif ($st === 'PENDING_APPROVAL'): ?>
            <?php echo $canApprove ? _offer_btn($act, 'approve', $offer['id'], 'Approve', 'btn') : '<span class="muted" style="font-size:12px">Awaiting a manager\'s approval.</span>'; ?>
          <?php elseif ($st === 'APPROVED'): ?>
            <?php echo _offer_btn($act, 'issue', $offer['id'], 'Issue offer', 'btn'); ?>
          <?php elseif (in_array($st, ['ISSUED','VIEWED'], true)): ?>
            <a class="btn secondary" href="/offer-letter?id=<?= $cid ?>" target="_blank">View / print letter</a>
            <?php echo _offer_btn($act, 'accept', $offer['id'], 'Mark accepted', 'btn'); ?>
            <?php echo _offer_btn($act, 'decline', $offer['id'], 'Mark declined', 'btn secondary', true); ?>
          <?php elseif ($st === 'ACCEPTED'): ?>
            <a class="btn secondary" href="/offer-letter?id=<?= $cid ?>" target="_blank">View letter</a>
          <?php endif; ?>
          <?php if (in_array($st, ['DRAFT','PENDING_APPROVAL','APPROVED','ISSUED','VIEWED'], true)) echo _offer_btn($act, 'withdraw', $offer['id'], 'Withdraw', 'btn secondary'); ?>
        </div>
        <?php endif; ?>
        <?php if ($st === 'ACCEPTED'): ?>
          <div class="panel" style="border-left:4px solid var(--ok,#16a34a);margin-top:12px;padding:11px 14px">
            <b style="color:var(--ok,#16a34a)">✓ Offer accepted — ready for onboarding.</b>
            <div class="muted" style="font-size:12.5px;margin-top:3px">Complete onboarding from the candidate's stage control: move to <b>Accepted (Hired)</b> and tick “add to workforce”. This reuses the same person record — the candidate becomes an employee with no duplicate. (§34)</div>
          </div>
        <?php endif; ?>
      <?php elseif ($can): ?>
        <p class="muted">No offer yet.</p>
        <form method="post" action="<?= $act ?>" style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;max-width:520px"><input type="hidden" name="do" value="offer_create">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <?php if ($seeSal): ?><div><label class="ff-l">Total CTC (blank = from structure <?= $sal ? $e($money($sal['gross_ctc'])) : '—' ?>)</label><input class="form-control" type="number" step="any" name="ctc"></div><?php endif; ?>
            <div><label class="ff-l">Joining date</label><input class="form-control" type="date" name="joining_date"></div>
            <div><label class="ff-l">Offer valid till</label><input class="form-control" type="date" name="expiry_date"></div>
          </div>
          <label class="ff-l" style="margin-top:8px">Offer terms / notes</label><textarea class="form-control" name="offer_terms" rows="2"></textarea>
          <div style="margin-top:8px"><button class="btn">Draft offer</button></div>
        </form>
      <?php else: ?><p class="muted">No offer yet.</p><?php endif; ?>
    </div>
    <?php if (function_exists('recruit_letters_block')) recruit_letters_block($cand); ?>
    <style>.ff-l{display:block;font-size:11.5px;font-weight:600;color:var(--muted,#656e7a);margin-bottom:3px}</style>
    <?php
}

// A tiny inline POST button (optionally prompting for a decline reason).
function _offer_btn($action, $do, $offerId, $label, $cls, $needReason = false) {
    $onsub = $needReason ? " onsubmit=\"this.reason.value=prompt('Reason?')||'';return this.reason.value!==''\"" : '';
    $extra = $needReason ? '<input type="hidden" name="reason">' : '';
    return '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES) . '" style="display:inline"' . $onsub . '>'
        . '<input type="hidden" name="do" value="' . htmlspecialchars($do, ENT_QUOTES) . '">'
        . '<input type="hidden" name="offer_id" value="' . (int)$offerId . '">' . $extra
        . '<button class="' . htmlspecialchars($cls, ENT_QUOTES) . '" style="padding:6px 12px">' . htmlspecialchars($label, ENT_QUOTES) . '</button></form>';
}
