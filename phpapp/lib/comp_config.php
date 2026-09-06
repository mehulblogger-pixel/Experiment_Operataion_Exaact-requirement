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
    static $done = false; if ($done) return; $done = true;
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

// ---- Admin screen ----------------------------------------------------------
function ops_comp_setup($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure the compensation structure.');
    comp_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'save') { comp_save((int)($_POST['id'] ?? 0), $_POST); flash('Component saved.'); redirect('/comp-setup'); return true; }
        if ($do === 'toggle') { $c = comp_get((int)($_POST['id'] ?? 0)); if ($c) comp_set_active($c['id'], (int)$c['active'] === 0); flash('Component updated.'); redirect('/comp-setup'); return true; }
    }
    view('ops/comp_setup', ['defs' => comp_defs(false)]);
    return true;
}
