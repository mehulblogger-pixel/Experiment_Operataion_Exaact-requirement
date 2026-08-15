<?php
// ============================================================================
//  RECRUITMENT COMMAND CENTRE  (Phase 7)
//
//  A faithful, in-app rebuild of the client's Excel "Recruitment Command Centre"
//  — same sections, in the same order — plus the ownership & money layer the
//  spreadsheet could not carry (Responsible 1/2, profit earned vs lost, project
//  headcount, a linked requirement tracker).
//
//  Discipline (unchanged from the whole recruitment enhancement):
//   • ADDITIVE ONLY — nullable columns, nothing renamed or dropped.
//   • FULLY CONFIGURABLE — every filter and dropdown comes from the Masters /
//     lookup engine (lk_options_or) and Settings (FY), so an admin changes them
//     with no code. The shipped constants are only the fallback default.
//   • Read-only analytics — this dashboard computes, it does not mutate.
// ============================================================================

// Additive, nullable ownership columns. Responsible 1 = Recruiter, Responsible 2
// = Reporting manager. Department + drop reason are configurable lookups.
function rcc_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('requisitions', 'recruiter_id', 'INT NULL');   // Responsible 1
    ensure_column('requisitions', 'manager_id',   'INT NULL');   // Responsible 2
    ensure_column('requisitions', 'department',    "VARCHAR(60) NULL");
    ensure_column('candidates',   'recruiter_id', 'INT NULL');   // who is chasing this person
    ensure_column('candidates',   'department',    "VARCHAR(60) NULL");
    ensure_column('candidates',   'drop_reason',   "VARCHAR(60) NULL"); // why lost (configurable)
    ensure_column('candidates',   'source_type',   "VARCHAR(30) NULL"); // alias of source, kept configurable
}

// Configurable option maps — Masters override the shipped defaults.
function rcc_departments()  { return lk_options_or('hr_department', RCC_DEPARTMENTS); }
function rcc_sources()      { return lk_options_or('candidate_source', CAND_SOURCES); }
function rcc_drop_reasons() { return lk_options_or('drop_reason', RCC_DROP_REASONS); }
function rcc_stage_labels() { return lk_options_or('candidate_stage', CAND_STAGES); }

const RCC_DEPARTMENTS = [
    'INSPECTION'=>'Inspection', 'ENGINEERING'=>'Engineering', 'QAQC'=>'QA / QC',
    'NDT'=>'NDT', 'HSE'=>'HSE / Safety', 'COMMERCIAL'=>'Commercial', 'HR'=>'HR', 'FINANCE'=>'Finance',
];
const RCC_DROP_REASONS = [
    'HIGH_SALARY'=>'High salary expectation', 'NOT_RESPONDING'=>'Not responding',
    'REQ_FULFILLED'=>'Requirement fulfilled', 'NOT_INTERESTED'=>'Not interested',
    'NOT_AVAILABLE'=>'Not available', 'BETTER_OFFER'=>'Better offer elsewhere',
    'NOTICE_PERIOD'=>'Notice period', 'LOCATION'=>'Location constraint',
    'COUNTER_OFFER'=>'Counter offer', 'PROFILE_MISMATCH'=>'Profile mismatch',
    'PERSONAL'=>'Personal reason', 'OTHER'=>'Other',
];

// The recruitment funnel order — the app's own candidate stages, so it stays
// configurable through the candidate_stage master. Terminal states sit outside.
const RCC_FUNNEL_ORDER = ['RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','ACCEPTED'];
const RCC_TERMINAL     = ['REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD'];

// Active users for the Recruiter / Hiring-manager pickers (configurable = the
// user register itself).
function rcc_users() {
    static $u = null; if ($u !== null) return $u;
    $u = [];
    try {
        foreach (ops_all("SELECT id, TRIM(COALESCE(first_name,'')||' '||COALESCE(last_name,'')) nm, username
                          FROM users WHERE is_active=1 ORDER BY first_name, last_name, username") as $r)
            $u[(int)$r['id']] = trim($r['nm']) !== '' ? trim($r['nm']) : $r['username'];
    } catch (Throwable $e) { $u = []; }
    return $u;
}
function rcc_user_name($id) { $u = rcc_users(); return $u[(int)$id] ?? ''; }

// ---- Filters --------------------------------------------------------------
// Reads $_GET, and returns both the chosen values and the option lists for the
// four (configurable) filter dropdowns, plus FY and Month.
function rcc_filters() {
    rcc_migrate();
    $fyOpts = function_exists('fy_options') ? fy_options(6) : [];
    $fy = (string)($_GET['fy'] ?? (function_exists('current_fy') ? current_fy() : ''));
    if ($fyOpts && !in_array($fy, $fyOpts, true)) $fy = $fyOpts[0] ?? $fy;
    $range = ($fy !== '' && function_exists('fy_range')) ? fy_range($fy) : null;

    // Months present in the data (CV received / created), newest first.
    $months = [];
    try {
        foreach (ops_all("SELECT DISTINCT substr(COALESCE(NULLIF(cv_received_date,''),created_at),1,7) m
                          FROM candidates WHERE COALESCE(NULLIF(cv_received_date,''),created_at)<>'' ORDER BY m DESC") as $r)
            if (($r['m'] ?? '') !== '') $months[$r['m']] = rcc_month_label($r['m']);
    } catch (Throwable $e) {}

    $f = [
        'fy'      => $fy,
        'range'   => $range,
        'month'   => (string)($_GET['month'] ?? ''),
        'dept'    => (string)($_GET['dept'] ?? ''),
        'source'  => (string)($_GET['source'] ?? ''),
        'manager' => (string)($_GET['manager'] ?? ''),   // user id
        'opts'    => [
            'fy'      => $fyOpts,
            'month'   => $months,
            'dept'    => rcc_departments(),
            'source'  => rcc_sources(),
            'manager' => rcc_users(),
        ],
    ];
    return $f;
}
function rcc_month_label($ym) {
    $t = strtotime($ym . '-01'); return $t ? date('M Y', $t) : $ym;
}

// Build a WHERE fragment + args for the candidate table from the filters.
function rcc_cand_where($f, $alias = 'c') {
    $w = ['1=1']; $a = [];
    $dateCol = "COALESCE(NULLIF($alias.cv_received_date,''),$alias.created_at)";
    if (!empty($f['month'])) { $w[] = "substr($dateCol,1,7)=?"; $a[] = $f['month']; }
    elseif (!empty($f['range'])) { $w[] = "substr($dateCol,1,10) BETWEEN ? AND ?"; $a[] = $f['range'][0]; $a[] = $f['range'][1]; }
    if ($f['dept'] !== '')   { $w[] = "$alias.department=?"; $a[] = $f['dept']; }
    if ($f['source'] !== '') { $w[] = "(COALESCE($alias.source_type,$alias.source)=?)"; $a[] = $f['source']; }
    if ($f['manager'] !== '' && ctype_digit($f['manager'])) { $w[] = "$alias.recruiter_id=?"; $a[] = (int)$f['manager']; }
    return [implode(' AND ', $w), $a];
}
// WHERE for requisitions from the filters (department + owner).
function rcc_req_where($f, $alias = 'r') {
    $w = ['1=1']; $a = [];
    if ($f['dept'] !== '') { $w[] = "$alias.department=?"; $a[] = $f['dept']; }
    if ($f['manager'] !== '' && ctype_digit($f['manager'])) { $w[] = "($alias.recruiter_id=? OR $alias.manager_id=?)"; $a[] = (int)$f['manager']; $a[] = (int)$f['manager']; }
    if (!empty($f['range'])) { $w[] = "(substr($alias.created_at,1,10)<=? )"; $a[] = $f['range'][1]; } // opened on/before FY end
    return [implode(' AND ', $w), $a];
}

// ---- The data ------------------------------------------------------------
function rcc_data($f) {
    rcc_migrate();
    $stages = rcc_stage_labels();
    [$cw, $ca] = rcc_cand_where($f);
    [$rw, $ra] = rcc_req_where($f);
    $one = function ($sql, $args = []) { try { $r = ops_one($sql, $args); return $r ? (int)array_values($r)[0] : 0; } catch (Throwable $e) { return 0; } };
    $rows = function ($sql, $args = []) { try { return ops_all($sql, $args) ?: []; } catch (Throwable $e) { return []; } };

    $d = ['f' => $f, 'stages' => $stages];

    // ---- Stage counts (current) ----
    $stageCount = [];
    foreach ($rows("SELECT stage, COUNT(*) n FROM candidates c WHERE $cw GROUP BY stage", $ca) as $r) $stageCount[$r['stage']] = (int)$r['n'];
    $sc = function ($k) use ($stageCount) { return (int)($stageCount[$k] ?? 0); };
    $total = array_sum($stageCount);

    // reached-stage (cumulative) for the funnel
    $order = RCC_FUNNEL_ORDER; $idx = array_flip($order);
    $reached = [];
    foreach ($order as $s) $reached[$s] = 0;
    foreach ($stageCount as $st => $n) {
        if (isset($idx[$st])) { for ($i = 0; $i <= $idx[$st]; $i++) $reached[$order[$i]] += $n; }
        // terminal candidates still passed CV-received at least
        elseif (in_array($st, RCC_TERMINAL, true)) { $reached['RECEIVED'] += $n; }
    }
    $d['funnel'] = [];
    foreach ($order as $s) $d['funnel'][] = ['key' => $s, 'label' => $stages[$s] ?? $s, 'n' => $reached[$s]];

    // ---- Status donut (outcome buckets) ----
    $d['status'] = [
        ['key' => 'INPROCESS', 'label' => 'In process',    'n' => $sc('RECEIVED') + $sc('SUBMITTED') + $sc('SHORTLISTED') + $sc('INTERVIEW'), 'c' => 1],
        ['key' => 'OFFERED',   'label' => 'Offer issued',   'n' => $sc('OFFERED'), 'c' => 2],
        ['key' => 'ACCEPTED',  'label' => 'Offer accepted', 'n' => $sc('ACCEPTED'), 'c' => 3],
        ['key' => 'HOLD',      'label' => 'On hold',        'n' => $sc('HOLD'), 'c' => 5],
        ['key' => 'REJECTED',  'label' => 'Rejected',       'n' => $sc('REJECTED'), 'c' => 7],
        ['key' => 'DROPPED',   'label' => 'Dropped',        'n' => $sc('WITHDRAWN') + $sc('OFFER_DECLINED'), 'c' => 8],
    ];

    // ---- Demand & pipeline KPIs ----
    $reqRows = $rows("SELECT r.*, (SELECT COUNT(*) FROM candidates cc WHERE cc.requisition_id=r.id AND cc.stage='ACCEPTED') filled
                      FROM requisitions r WHERE $rw AND r.status IN ('OPEN','PROPOSED','OFFERED','HIRED') ", $ra);
    $openSeats = 0; $orderedSeats = 0; $filledSeats = 0;
    foreach ($reqRows as $r) { $q = max(1, (int)($r['quantity'] ?? 1)); $fl = (int)$r['filled']; $orderedSeats += $q; $filledSeats += min($fl, $q); $openSeats += max(0, $q - $fl); }
    $d['kpi'] = [
        'open_positions' => $openSeats,
        'pipeline'       => $total,
        'active_now'     => $sc('RECEIVED') + $sc('SUBMITTED') + $sc('SHORTLISTED') + $sc('INTERVIEW') + $sc('OFFERED'),
        'offers_issued'  => $sc('OFFERED'),
        'ordered'        => $orderedSeats,
        'filled'         => $filledSeats,
    ];

    // ---- Conversion, speed & cost ----
    $recv = max(1, $reached['RECEIVED']);
    $d['conv'] = [
        'shortlist' => $reached['RECEIVED'] ? round($reached['SHORTLISTED'] / $recv * 100, 1) : 0,
        'int_offer' => $reached['INTERVIEW'] ? round($reached['OFFERED'] / max(1, $reached['INTERVIEW']) * 100, 1) : 0,
        'accept'    => $reached['OFFERED'] ? round($reached['ACCEPTED'] / max(1, $reached['OFFERED']) * 100, 1) : 0,
        'tth'       => 0, 'tth_n' => 0,
    ];
    $hires = $rows("SELECT created_at, decided_at FROM candidates c WHERE $cw AND stage='ACCEPTED' AND COALESCE(decided_at,'')<>'' AND COALESCE(created_at,'')<>''", $ca);
    $days = []; foreach ($hires as $h) { $dd = (strtotime(substr($h['decided_at'],0,10)) - strtotime(substr($h['created_at'],0,10))) / 86400; if ($dd >= 0 && $dd < 400) $days[] = $dd; }
    if ($days) { $d['conv']['tth'] = (int)round(array_sum($days) / count($days)); $d['conv']['tth_n'] = count($days); }

    // ---- Monthly trend (CVs shared / offers / joined) ----
    $trend = [];
    foreach ($rows("SELECT substr(COALESCE(NULLIF(cv_received_date,''),created_at),1,7) m, COUNT(*) n
                    FROM candidates c WHERE $cw AND COALESCE(NULLIF(cv_received_date,''),created_at)<>'' GROUP BY m ORDER BY m", $ca) as $r) $trend[$r['m']]['cv'] = (int)$r['n'];
    foreach ($rows("SELECT substr(COALESCE(NULLIF(decided_at,''),created_at),1,7) m, COUNT(*) n
                    FROM candidates c WHERE $cw AND stage IN ('OFFERED','ACCEPTED') GROUP BY m ORDER BY m", $ca) as $r) $trend[$r['m']]['off'] = (int)$r['n'];
    foreach ($rows("SELECT substr(COALESCE(NULLIF(decided_at,''),created_at),1,7) m, COUNT(*) n
                    FROM candidates c WHERE $cw AND stage='ACCEPTED' GROUP BY m ORDER BY m", $ca) as $r) $trend[$r['m']]['join'] = (int)$r['n'];
    ksort($trend);
    $d['trend'] = array_slice(array_map(fn($k) => ['m' => $k, 'label' => rcc_month_label($k),
        'cv' => (int)($trend[$k]['cv'] ?? 0), 'off' => (int)($trend[$k]['off'] ?? 0), 'join' => (int)($trend[$k]['join'] ?? 0)], array_keys($trend)), -8);

    // ---- Department load (candidates vs reached-offer) ----
    $dept = [];
    foreach ($rows("SELECT COALESCE(NULLIF(department,''),'—') d, COUNT(*) n FROM candidates c WHERE $cw GROUP BY d", $ca) as $r) $dept[$r['d']]['cand'] = (int)$r['n'];
    foreach ($rows("SELECT COALESCE(NULLIF(department,''),'—') d, COUNT(*) n FROM candidates c WHERE $cw AND stage IN ('OFFERED','ACCEPTED') GROUP BY d", $ca) as $r) $dept[$r['d']]['off'] = (int)$r['n'];
    $deptOpt = rcc_departments();
    $d['dept'] = [];
    foreach ($dept as $k => $v) $d['dept'][] = ['label' => $deptOpt[$k] ?? $k, 'cand' => (int)($v['cand'] ?? 0), 'off' => (int)($v['off'] ?? 0)];
    usort($d['dept'], fn($a, $b) => $b['cand'] <=> $a['cand']); $d['dept'] = array_slice($d['dept'], 0, 8);

    // ---- Drop / hold reasons ----
    $dropOpt = rcc_drop_reasons(); $drops = [];
    foreach ($rows("SELECT COALESCE(NULLIF(drop_reason,''),'OTHER') dr, COUNT(*) n FROM candidates c WHERE $cw AND stage IN ('REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD') GROUP BY dr", $ca) as $r) $drops[] = ['label' => $dropOpt[$r['dr']] ?? $r['dr'], 'n' => (int)$r['n']];
    usort($drops, fn($a, $b) => $b['n'] <=> $a['n']); $d['drops'] = array_slice($drops, 0, 10);

    // ---- How long active (ageing buckets) ----
    $buckets = ['0–15 days' => 0, '16–30 days' => 0, '31–45 days' => 0, 'Over 45 days' => 0];
    foreach ($rows("SELECT substr(COALESCE(NULLIF(cv_received_date,''),created_at),1,10) dt FROM candidates c
                    WHERE $cw AND stage NOT IN ('ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED')", $ca) as $r) {
        $age = (strtotime(date('Y-m-d')) - strtotime($r['dt'] ?: date('Y-m-d'))) / 86400;
        if ($age <= 15) $buckets['0–15 days']++; elseif ($age <= 30) $buckets['16–30 days']++; elseif ($age <= 45) $buckets['31–45 days']++; else $buckets['Over 45 days']++;
    }
    $d['ageing'] = $buckets;

    // ---- Needs attention: longest-waiting candidates ----
    $d['waiting'] = [];
    foreach ($rows("SELECT c.id, (c.first_name||' '||c.last_name) nm, c.cand_code, c.department, c.recruiter_id,
                        substr(COALESCE(NULLIF(c.cv_received_date,''),c.created_at),1,10) dt
                    FROM candidates c WHERE $cw AND c.stage NOT IN ('ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED')
                    ORDER BY dt ASC LIMIT 8", $ca) as $r) {
        $age = (int)floor((strtotime(date('Y-m-d')) - strtotime($r['dt'] ?: date('Y-m-d'))) / 86400);
        $d['waiting'][] = ['id' => (int)$r['id'], 'nm' => trim($r['nm']) ?: $r['cand_code'], 'dept' => ($deptOpt[$r['department']] ?? $r['department'] ?: '—'),
                           'mgr' => rcc_user_name($r['recruiter_id']) ?: '—', 'days' => max(0, $age)];
    }

    // ---- Needs attention: biggest open demand ----
    $d['demand'] = [];
    foreach ($reqRows as $r) { $q = max(1, (int)($r['quantity'] ?? 1)); $open = max(0, $q - (int)$r['filled']); if ($open > 0) $d['demand'][] = ['id' => (int)$r['id'], 'req' => $r['req_code'], 'dept' => ($deptOpt[$r['department']] ?? $r['department'] ?: '—'), 'role' => (DESIGNATIONS[$r['designation']] ?? $r['designation'] ?: '—'), 'vac' => $q, 'open' => $open]; }
    usort($d['demand'], fn($a, $b) => $b['open'] <=> $a['open']); $d['demand'] = array_slice($d['demand'], 0, 8);

    // ---- Recruiter performance + manpower P&L (reuses Phase 5 commercials) ----
    $d['recruiters'] = rcc_recruiter_perf($f, $reqRows);
    $pl = ['earned' => 0.0, 'lost' => 0.0, 'working' => 0];
    foreach ($d['recruiters'] as $rp) { $pl['earned'] += $rp['earned']; $pl['lost'] += $rp['lost']; $pl['working'] += $rp['working']; }
    $d['pl'] = $pl;

    // ---- Tracker rows + project headcount ----
    $d['tracker'] = rcc_tracker($reqRows, $deptOpt);
    $d['projects'] = rcc_projects($reqRows);

    return $d;
}

// Per-recruiter (Responsible 1) posted / working / recruited + earned/lost profit.
function rcc_recruiter_perf($f, $reqRows) {
    $byUser = [];
    foreach ($reqRows as $r) {
        $uid = (int)($r['recruiter_id'] ?? 0); if (!$uid) continue;
        $byUser[$uid] ??= ['uid' => $uid, 'name' => rcc_user_name($uid) ?: ('User #' . $uid), 'posted' => 0, 'working' => 0, 'recruited' => 0, 'earned' => 0.0, 'lost' => 0.0];
        $q = max(1, (int)($r['quantity'] ?? 1)); $filled = (int)$r['filled'];
        $byUser[$uid]['posted'] += $q;
        $byUser[$uid]['recruited'] += $filled;
        $byUser[$uid]['working'] += $filled;   // filled == currently deployed (accepted+placed)
        if (function_exists('recruit_req_commercial_rollup')) {
            try { $roll = recruit_req_commercial_rollup($r); $byUser[$uid]['earned'] += (float)$roll['appr_profit'];
                  $perHead = $q > 0 ? ((float)($r['expected_profit'] ?? 0)) / $q : 0; $byUser[$uid]['lost'] += max(0, $q - $filled) * $perHead; } catch (Throwable $e) {}
        }
    }
    $out = array_values($byUser);
    usort($out, fn($a, $b) => $b['posted'] <=> $a['posted']);
    return array_slice($out, 0, 8);
}

function rcc_tracker($reqRows, $deptOpt) {
    $out = [];
    foreach ($reqRows as $r) {
        $q = max(1, (int)($r['quantity'] ?? 1)); $filled = (int)$r['filled'];
        $roll = function_exists('recruit_req_commercial_rollup') ? (function () use ($r) { try { return recruit_req_commercial_rollup($r); } catch (Throwable $e) { return null; } })() : null;
        $perHead = $q > 0 ? ((float)($r['expected_profit'] ?? 0)) / $q : 0;
        $out[] = [
            'id' => (int)$r['id'], 'req' => $r['req_code'],
            'posted' => (DESIGNATIONS[$r['designation']] ?? $r['designation'] ?: '—'), 'qty' => $q,
            'dept' => ($deptOpt[$r['department']] ?? $r['department'] ?: ''),
            'recruiter' => rcc_user_name($r['recruiter_id']), 'manager' => rcc_user_name($r['manager_id']),
            'filled' => $filled, 'status' => $r['status'],
            'earned' => $roll ? (float)$roll['appr_profit'] : 0.0,
            'lost'   => max(0, $q - $filled) * $perHead,
        ];
    }
    usort($out, fn($a, $b) => $b['lost'] <=> $a['lost']);
    return array_slice($out, 0, 12);
}

// People working per project site (deployed vs ordered).
function rcc_projects($reqRows) {
    $proj = [];
    foreach ($reqRows as $r) {
        $site = trim((string)($r['project_site'] ?? '')) ?: '—';
        $proj[$site] ??= ['site' => $site, 'ordered' => 0, 'working' => 0];
        $proj[$site]['ordered'] += max(1, (int)($r['quantity'] ?? 1));
        $proj[$site]['working'] += (int)$r['filled'];
    }
    $out = array_values($proj);
    usort($out, fn($a, $b) => ($b['ordered'] - $b['working']) <=> ($a['ordered'] - $a['working']));
    return array_slice($out, 0, 6);
}

function ops_recruitment_cc($method) {
    ops_require(recruit_home_can(), 'You do not have access to Recruitment.');
    $f = rcc_filters();
    $d = rcc_data($f);
    view('ops/recruitment_cc', ['d' => $d]);
    return true;
}
