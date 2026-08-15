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

// Additive, nullable columns that enrich a requirement (Phase 2). Never renames
// or drops anything — a requisition raised before this still loads and saves.
function req_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    $cols = [
        // Client & contact (client_id links the CRM master — no second client store)
        ['client_id','INT NULL'], ['contact_name',"VARCHAR(150) DEFAULT ''"],
        ['contact_email',"VARCHAR(200) DEFAULT ''"], ['contact_phone',"VARCHAR(60) DEFAULT ''"],
        ['contract_ref',"VARCHAR(120) DEFAULT ''"],
        // Position
        ['quantity','INT DEFAULT 1'], ['discipline',"VARCHAR(120) DEFAULT ''"], ['category',"VARCHAR(120) DEFAULT ''"],
        ['skills',"VARCHAR(400) DEFAULT ''"], ['qualification',"VARCHAR(200) DEFAULT ''"],
        ['experience_min',"DECIMAL(5,1) DEFAULT 0"], ['relevant_experience',"VARCHAR(200) DEFAULT ''"],
        // Deployment
        ['start_date',"VARCHAR(20) DEFAULT ''"], ['end_date',"VARCHAR(20) DEFAULT ''"],
        ['duration_months',"DECIMAL(6,2) DEFAULT 0"], ['duty_hours',"VARCHAR(40) DEFAULT ''"],
        ['shift',"VARCHAR(40) DEFAULT ''"], ['work_model',"VARCHAR(30) DEFAULT ''"],
        ['deploy_location',"VARCHAR(160) DEFAULT ''"], ['prov_travel','INT DEFAULT 0'],
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
    ];
    foreach ($cols as $c) ensure_column('requisitions', $c[0], $c[1]);
}

// The additive Phase-2 field list (used by the save handler). Kept here so the
// form, the handler and the detail stay in step.
function req_extra_fields() {
    return ['client_id','contact_name','contact_email','contact_phone','contract_ref',
        'quantity','discipline','category','skills','qualification','experience_min','relevant_experience',
        'start_date','end_date','duty_hours','shift','work_model','deploy_location',
        'prov_travel','prov_accommodation','prov_food','other_allowances',
        'sel_client_interview','sel_tech_interview','sel_hr_interview','client_approval_req','training_req',
        'cmp_medical','cmp_pcc','cmp_gate_pass','cmp_safety','cmp_certification','documents_note',
        'billing_rate','rate_basis','target_margin','negotiation_floor'];
}

// Duration in months from an explicit value, else derived from start/end dates.
function req_duration_months($b) {
    $m = (float)($b['duration_months'] ?? 0);
    if ($m > 0) return $m;
    $s = substr((string)($b['start_date'] ?? ''), 0, 10); $e = substr((string)($b['end_date'] ?? ''), 0, 10);
    if ($s !== '' && $e !== '' && strtotime($e) >= strtotime($s)) return round((strtotime($e) - strtotime($s)) / 86400 / 30.4, 2);
    return 0;
}

// Expected revenue & profit from quantity × rate × duration vs monthly cost.
// Deterministic and mirrored in the form's live preview.
function req_commercials($b) {
    $qty   = max(1, (int)($b['quantity'] ?? 1));
    $rate  = (float)($b['billing_rate'] ?? 0);
    $cost  = (float)($b['budgeted_cost'] ?? 0);         // monthly cost per person
    $basis = (string)($b['rate_basis'] ?? 'MONTHLY');
    $months = req_duration_months($b);
    $units = ($basis === 'MANDAY' || $basis === 'DAILY') ? round($months * 22) : $months;
    if ($basis === 'FIXED') $revenue = $qty * $rate;
    else                    $revenue = $qty * $rate * max($units, 0);
    $costTotal = $qty * $cost * max($months, 0);
    $profit = $revenue - $costTotal;
    return ['revenue' => round($revenue, 2), 'cost' => round($costTotal, 2), 'profit' => round($profit, 2), 'months' => $months, 'units' => $units];
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
    return function_exists('can') && (can('mod.hiring.view') || (function_exists('is_master') && is_master()));
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
    $d['t_reqs'] = $rows("SELECT r.id, r.req_code, r.designation, r.project_site, o.name office, r.status, COALESCE(r.quantity,1) quantity,
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
    static $done = false; if ($done) return; $done = true;
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
    static $done = false; if ($done) return; $done = true;
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
    $ref = '';
    foreach ($rows as $r) if (trim((string)$r['person_ref']) !== '') { $ref = trim((string)$r['person_ref']); break; }
    if ($ref === '') $ref = 'P' . str_pad((string)min($ids), 6, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE candidates SET person_ref=? WHERE id IN ($ph)")->execute(array_merge([$ref], $ids));
    return '';
}
