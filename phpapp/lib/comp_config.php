<?php
// ============================================================================
//  EXAACT Recruitment — Configurable Compensation setup (Phase 5.1A)
//
//  Salary headings are DATA, not code. An administrator defines the components
//  (earnings, employee deductions, employer contributions) with a calculation
//  rule (a fixed amount, a % of basic, or a % of gross) and a statutory flag
//  (PF / ESI / Professional Tax / Gratuity …). The salary structure is then
//  COMPUTED from these definitions: gross, total deductions, employer cost, CTC
//  and net pay. Different companies configure completely different structures.
//  Additive and non-destructive.
// ============================================================================

const COMP_SECTIONS = ['EARNING' => 'Earning', 'DEDUCTION' => 'Deduction (employee)', 'EMPLOYER' => 'Employer contribution'];
const COMP_CALCS = [
    'FIXED'     => 'Fixed amount',
    'PCT_BASIC' => '% of Basic',
    'PCT_GROSS' => '% of Gross earnings',
];

function comp_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS salary_component_defs (
            id $pk,
            code VARCHAR(40) DEFAULT '',
            name VARCHAR(120) DEFAULT '',
            section VARCHAR(20) DEFAULT 'EARNING',
            calc VARCHAR(20) DEFAULT 'FIXED',
            rate DECIMAL(9,4) DEFAULT 0,
            statutory INT DEFAULT 0,
            taxable INT DEFAULT 1,
            sort INT DEFAULT 0,
            active INT DEFAULT 1,
            created_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) { return; }
    comp_seed();
}

// Sensible Indian-statutory defaults on first run (all editable).
function comp_seed() {
    try { if ((int)ops_one("SELECT COUNT(*) c FROM salary_component_defs")['c'] > 0) return; }
    catch (Throwable $e) { return; }
    $now = function_exists('now_iso') ? now_iso() : date('c');
    $rows = [
        // code, name, section, calc, rate, statutory, taxable
        ['BASIC',     'Basic',                'EARNING',   'FIXED',     0,     0, 1],
        ['HRA',       'HRA',                  'EARNING',   'PCT_BASIC', 40,    0, 1],
        ['CONVEY',    'Conveyance allowance', 'EARNING',   'FIXED',     0,     0, 1],
        ['SPECIAL',   'Special allowance',    'EARNING',   'FIXED',     0,     0, 1],
        ['PF_EE',     'Provident Fund (employee)', 'DEDUCTION', 'PCT_BASIC', 12, 1, 0],
        ['PT',        'Professional Tax',     'DEDUCTION', 'FIXED',     0,     1, 0],
        ['TDS',       'Income Tax (TDS)',     'DEDUCTION', 'FIXED',     0,     0, 0],
        ['PF_ER',     'Provident Fund (employer)', 'EMPLOYER', 'PCT_BASIC', 12, 1, 0],
        ['GRATUITY',  'Gratuity',             'EMPLOYER',  'PCT_BASIC', 4.81,  1, 0],
    ];
    $seq = 10;
    $ins = db()->prepare("INSERT INTO salary_component_defs (code,name,section,calc,rate,statutory,taxable,sort,active,created_at) VALUES (?,?,?,?,?,?,?,?,1,?)");
    foreach ($rows as $r) { $ins->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$seq,$now]); $seq += 10; }
}

function comp_defs($activeOnly = true) {
    comp_migrate();
    $w = $activeOnly ? "WHERE active=1" : "";
    // Earnings first, then deductions, then employer — each by sort.
    return ops_all("SELECT * FROM salary_component_defs $w ORDER BY
        CASE section WHEN 'EARNING' THEN 1 WHEN 'DEDUCTION' THEN 2 ELSE 3 END, sort, id");
}
function comp_get($id) { comp_migrate(); return ops_one("SELECT * FROM salary_component_defs WHERE id=?", [(int)$id]) ?: null; }

function comp_save($id, $post) {
    comp_migrate();
    $section = array_key_exists($post['section'] ?? '', COMP_SECTIONS) ? $post['section'] : 'EARNING';
    $calc = array_key_exists($post['calc'] ?? '', COMP_CALCS) ? $post['calc'] : 'FIXED';
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string)($post['code'] ?? ''))) ?: ('C' . substr((string)time(), -5));
    $args = [$code, trim((string)($post['name'] ?? '')), $section, $calc, (float)($post['rate'] ?? 0),
             empty($post['statutory']) ? 0 : 1, empty($post['taxable']) ? 0 : 1, (int)($post['sort'] ?? 0)];
    if ((int)$id > 0) {
        $args[] = (int)$id;
        db()->prepare("UPDATE salary_component_defs SET code=?,name=?,section=?,calc=?,rate=?,statutory=?,taxable=?,sort=? WHERE id=?")->execute($args);
        return (int)$id;
    }
    $args[] = function_exists('now_iso') ? now_iso() : date('c');
    db()->prepare("INSERT INTO salary_component_defs (code,name,section,calc,rate,statutory,taxable,sort,active,created_at) VALUES (?,?,?,?,?,?,?,?,1,?)")->execute($args);
    return (int)db()->lastInsertId();
}
function comp_set_active($id, $on) { comp_migrate(); db()->prepare("UPDATE salary_component_defs SET active=? WHERE id=?")->execute([$on ? 1 : 0, (int)$id]); }

// ---- The compute engine ----------------------------------------------------
//  $inputs = [code => amount] for FIXED components (Basic, Conveyance, Special,
//  fixed PT/TDS, …). Everything else is derived from the definitions.
//  Returns ['lines'=>[…], 'gross'=>, 'deductions'=>, 'employer'=>, 'ctc'=>, 'net'=>].
function comp_compute($inputs) {
    $defs = comp_defs(true);
    $amt = [];   // code => amount
    // Basic first (many rules key off it).
    $basic = (float)($inputs['BASIC'] ?? 0);

    // Pass 1: FIXED + PCT_BASIC for every section (independent of gross).
    foreach ($defs as $d) {
        if ($d['calc'] === 'FIXED')          $amt[$d['code']] = (float)($inputs[$d['code']] ?? 0);
        elseif ($d['calc'] === 'PCT_BASIC')  $amt[$d['code']] = round($basic * (float)$d['rate'] / 100, 2);
    }
    // Gross earnings so far (FIXED + PCT_BASIC earnings).
    $grossSoFar = 0.0;
    foreach ($defs as $d) if ($d['section'] === 'EARNING' && isset($amt[$d['code']])) $grossSoFar += $amt[$d['code']];
    // Pass 2: PCT_GROSS (earnings/deductions/employer) off the subtotal.
    foreach ($defs as $d) if ($d['calc'] === 'PCT_GROSS') $amt[$d['code']] = round($grossSoFar * (float)$d['rate'] / 100, 2);

    $lines = []; $gross = 0.0; $ded = 0.0; $emp = 0.0;
    foreach ($defs as $d) {
        $v = (float)($amt[$d['code']] ?? 0);
        $lines[] = ['code' => $d['code'], 'name' => $d['name'], 'section' => $d['section'],
                    'amount' => $v, 'statutory' => (int)$d['statutory'], 'taxable' => (int)$d['taxable']];
        if ($d['section'] === 'EARNING')       $gross += $v;
        elseif ($d['section'] === 'DEDUCTION') $ded += $v;
        else                                   $emp += $v;
    }
    return ['lines' => $lines, 'gross' => $gross, 'deductions' => $ded, 'employer' => $emp,
            'ctc' => $gross + $emp, 'net' => $gross - $ded];
}

// ---- The SOLVER: a CTC in, a whole structure out ---------------------------
//
//  comp_compute() above works bottom-up: you type every FIXED component and it
//  derives the percentage ones. That is the wrong way round for the conversation
//  that actually happens. Nobody negotiates a Basic. They agree a CTC — "twelve
//  lakh" — and the structure has to follow from it.
//
//  Every Indian payroll platform does this top-down (Zoho Payroll, Keka,
//  greytHR, Darwinbox), and they all do it the same way:
//
//      Basic              = a percentage of CTC          (policy)
//      HRA, PF, gratuity… = derived from Basic            (already configured)
//      one component      = the BALANCING figure          (policy)
//
//  The last line is what makes the arithmetic close exactly on the agreed
//  number. Without a balancing component you get a structure that is nearly the
//  CTC, and "nearly" is not something you can put in an offer letter.
//
//  BOTH policy numbers are settings, not constants, because they differ by
//  company and by country — and this product is sold to TPIAs and project
//  management companies who operate outside India.

//  Basic as a percentage of CTC. 40% is the common Indian default; a company
//  that uses 50% changes it once, here.
function comp_basic_pct() {
    $v = function_exists('setting_get') ? (float) setting_get('comp_basic_pct', 40) : 40.0;
    return ($v > 0 && $v <= 100) ? $v : 40.0;
}

//  Which component absorbs the remainder so the total lands exactly on the CTC.
//  It must be a FIXED earning — a percentage component cannot absorb anything,
//  because changing it changes what it is a percentage of.
function comp_balance_code() {
    $c = function_exists('setting_get') ? strtoupper(trim((string) setting_get('comp_balance_code', 'SPECIAL'))) : 'SPECIAL';
    return $c !== '' ? $c : 'SPECIAL';
}

//  The components a person can type a number into: FIXED ones only.
function comp_fixed_codes() {
    $out = [];
    foreach (comp_defs(true) as $d) if ($d['calc'] === 'FIXED') $out[] = (string) $d['code'];
    return $out;
}

/**
 * Build a whole salary structure from one CTC figure.
 *
 * $targetCtc is in the SAME period as the component amounts — monthly, unless a
 * workspace has configured otherwise. Callers holding an ANNUAL figure divide by
 * twelve before calling; comp_solve_from_annual_ctc() does that for them.
 *
 * $pinned lets a person override any FIXED component ("make HRA 25,000") and
 * re-solve around it: the balancing component simply absorbs a different
 * remainder, so the total still lands on the CTC. That is the behaviour people
 * expect when they edit one line of an offer.
 *
 * Returns comp_compute()'s result plus:
 *    'inputs'  the FIXED amounts it solved for — what to store
 *    'err'     set when the CTC cannot be made to fit, with a sentence saying why
 *
 * WHY IT ITERATES: the structure is linear in Basic, so one pass would do — but
 * only while no component is a percentage of GROSS. A PCT_GROSS component makes
 * the balancing figure feed back into the total it is trying to complete. Three
 * or four passes settle that to the paisa; the loop stops as soon as it has.
 */
function comp_solve_from_ctc($targetCtc, array $pinned = []) {
    comp_migrate();
    $target = (float) $targetCtc;
    if ($target <= 0)
        return ['err' => 'Enter the CTC first — the structure is worked out from it.'];

    $defs = comp_defs(true);
    if (!$defs)
        return ['err' => 'No salary components are configured yet. Set them up under Compensation setup first.'];

    $balCode = comp_balance_code();
    $balDef  = null;
    foreach ($defs as $d) if ((string) $d['code'] === $balCode) $balDef = $d;
    if (!$balDef || $balDef['calc'] !== 'FIXED' || $balDef['section'] !== 'EARNING')
        return ['err' => 'The balancing component (' . e_plain($balCode) . ') must be a fixed EARNING, '
                       . 'because it is what absorbs the remainder. Change it under Compensation setup.'];

    //  Pinned values a person typed win over anything solved.
    $pin = [];
    foreach ($pinned as $k => $v) {
        $k = strtoupper(trim((string) $k));
        if ($v === '' || $v === null) continue;
        $pin[$k] = max(0.0, (float) $v);
    }

    //  Basic follows policy unless it was pinned.
    $inputs = $pin;
    if (!isset($inputs['BASIC'])) $inputs['BASIC'] = round($target * comp_basic_pct() / 100, 2);
    if (!isset($inputs[$balCode])) $inputs[$balCode] = 0.0;

    $r = null;
    for ($i = 0; $i < 12; $i++) {
        $r = comp_compute($inputs);
        $gap = $target - (float) $r['ctc'];
        if (abs($gap) < 0.01) break;
        //  A pinned balancing component cannot absorb anything — the person has
        //  fixed it — so there is nothing left to adjust and the gap is real.
        if (isset($pin[$balCode])) break;
        $inputs[$balCode] = round(max(0.0, $inputs[$balCode] + $gap), 2);
        if ($inputs[$balCode] <= 0 && $gap < 0) break;      // cannot go below zero
    }
    $r = comp_compute($inputs);
    $r['inputs'] = $inputs;

    //  THE HONEST FAILURE. A CTC too small for the structure — the statutory
    //  floor plus the Basic percentage already exceed it — cannot be made to fit
    //  by driving a component negative. Say so, with the figure it CAN do, rather
    //  than silently returning something that does not add up.
    if (abs($target - (float) $r['ctc']) >= 0.01) {
        $r['err'] = 'This CTC cannot be split by the configured structure: the components already come to '
                  . number_format((float) $r['ctc'], 2)
                  . ($target < (float) $r['ctc']
                     ? ', which is more than the CTC entered. Lower the Basic percentage, or raise the CTC.'
                     : '. Check the pinned amounts.');
    }
    return $r;
}

/** The same, from an ANNUAL CTC — what a person actually negotiates. */
function comp_solve_from_annual_ctc($annual, array $pinned = []) {
    $a = (float) $annual;
    if ($a <= 0) return ['err' => 'Enter the annual CTC first — the structure is worked out from it.'];
    $r = comp_solve_from_ctc($a / 12, $pinned);
    if (!isset($r['err'])) { $r['annual_ctc'] = round((float) $r['ctc'] * 12, 2); $r['monthly_ctc'] = (float) $r['ctc']; }
    return $r;
}

//  A tiny escaper so this library never depends on a view helper being loaded.
if (!function_exists('e_plain')) {
    function e_plain($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
}

// ---- Admin screen ----------------------------------------------------------
function ops_comp_setup($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure the compensation structure.');
    comp_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'save') { comp_save((int)($_POST['id'] ?? 0), $_POST); flash('Component saved.'); redirect('/comp-setup'); return true; }
        if ($do === 'toggle') { $c = comp_get((int)($_POST['id'] ?? 0)); if ($c) comp_set_active($c['id'], (int)$c['active'] === 0); flash('Component updated.'); redirect('/comp-setup'); return true; }
        if ($do === 'save_policy') {
            //  Validated here, not trusted from the form: a dropdown is not a
            //  security boundary, and a bad value here silently breaks every
            //  structure solved from a CTC afterwards.
            $pct = (float) ($_POST['comp_basic_pct'] ?? 0);
            if ($pct <= 0 || $pct > 100) { flash('Basic must be between 1 and 100 percent of CTC.', 'error'); redirect('/comp-setup'); return true; }
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_POST['comp_balance_code'] ?? '')));
            $ok = false;
            foreach (comp_defs(true) as $d)
                if ((string) $d['code'] === $code && $d['calc'] === 'FIXED' && $d['section'] === 'EARNING') $ok = true;
            if (!$ok) { flash('The balancing heading must be an active fixed earning.', 'error'); redirect('/comp-setup'); return true; }
            setting_set('comp_basic_pct', (string) $pct);
            setting_set('comp_balance_code', $code);
            flash('Saved. Structures built from a CTC will use these rules.');
            redirect('/comp-setup'); return true;
        }
    }
    view('ops/comp_setup', ['defs' => comp_defs(false)]);
    return true;
}
