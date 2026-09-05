<?php
// ============================================================================
//  EXAACT Recruitment — configurable pipeline / stage engine  (Phase 2)
//
//  The recruitment stages a candidate moves through must be CONFIGURED per
//  company, not hardcoded. Different customers run completely different
//  selection processes; some stages (an L2 interview, a medical, a reference
//  check) apply only under a condition. This engine stores pipelines and their
//  stages as per-tenant DATA, resolves the applicable pipeline for a given
//  requisition, and evaluates conditional stages.
//
//  It is ADDITIVE and non-destructive: it adds two tables and a config screen,
//  changes no existing table, permission, status or transition, and leaves the
//  legacy CAND_STAGES flow untouched. Wiring the live candidate screen to drive
//  off a resolved pipeline is a later, separate step; until then this engine is
//  the configuration surface and the resolver the rest of the product builds on.
//
//  Governance: config is gated is_admin_level() (like the engagement-mode
//  config); the operating gates (is_coordinator_level, mod.hiring) are unchanged.
// ============================================================================

// Stage kinds — behaviour hints, not new statuses. 'gate' needs a decision,
// 'interview' schedules a round, 'offer' issues the offer, 'terminal' completes.
const RPIPE_STAGE_KINDS = [
    'step'      => 'Step',
    'gate'      => 'Decision gate',
    'interview' => 'Interview',
    'offer'     => 'Offer',
    'terminal'  => 'Final (hired / onboarding)',
];

// Condition operators for a conditional stage (§10 of the brief).
const RPIPE_COND_OPS = [
    ''    => 'Always (unconditional)',
    'in'  => 'is one of',
    'eq'  => 'equals',
    'ne'  => 'is not',
    'gte' => 'at least (number)',
];

// ---- Schema (additive; safe to run repeatedly) -----------------------------
function recruitpipe_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_pipelines (
            id $pk,
            code VARCHAR(40) DEFAULT '',
            name VARCHAR(160) DEFAULT '',
            description VARCHAR(400) DEFAULT '',
            applies_company VARCHAR(160) DEFAULT '',
            applies_sbu VARCHAR(120) DEFAULT '',
            applies_department VARCHAR(160) DEFAULT '',
            applies_position VARCHAR(160) DEFAULT '',
            applies_employment VARCHAR(60) DEFAULT '',
            applies_grade VARCHAR(80) DEFAULT '',
            applies_location VARCHAR(160) DEFAULT '',
            is_default INT DEFAULT 0,
            active INT DEFAULT 1,
            sort INT DEFAULT 0,
            created_at VARCHAR(30) DEFAULT ''
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_stages (
            id $pk,
            pipeline_id INT,
            seq INT DEFAULT 0,
            stage_key VARCHAR(60) DEFAULT '',
            name VARCHAR(160) DEFAULT '',
            kind VARCHAR(30) DEFAULT 'step',
            responsible_role VARCHAR(60) DEFAULT '',
            mandatory INT DEFAULT 1,
            condition_field VARCHAR(60) DEFAULT '',
            condition_op VARCHAR(10) DEFAULT '',
            condition_value VARCHAR(240) DEFAULT '',
            sla_days INT DEFAULT 0,
            required_docs VARCHAR(300) DEFAULT '',
            active INT DEFAULT 1
        )");
        if (function_exists('act_index')) {
            act_index('recruit_stages', 'idx_rs_pipe', '(pipeline_id)');
        }
    } catch (Throwable $e) { /* never break boot */ }
    recruitpipe_seed();
}

// ---- Seed the shipped templates (only when empty) --------------------------
function recruitpipe_seed() {
    try {
        $n = (int)ops_one("SELECT COUNT(*) c FROM recruit_pipelines")['c'];
        if ($n > 0) return;
    } catch (Throwable $e) { return; }

    // The client's approved Corporate Recruitment Workflow — the default.
    // Two stages ship CONDITIONAL to prove configurability (brief §10): an L2
    // only for senior grades, a medical only when the requisition requires it.
    recruitpipe_create('CORP18', 'Corporate Recruitment Workflow',
        'The full 18-step staff selection process — the default template.', true, [
        ['SRF',            'Staff Requisition',        'gate',      'HR_MANAGER'],
        ['ORGANOGRAM',     'Organogram Verification',  'gate',      'HR_HEAD'],
        ['SOURCING',       'Candidate Sourcing',       'step',      'RECRUITER'],
        ['CV_SCREEN',      'CV Screening',             'gate',      'RECRUITER'],
        ['HOD_SHORTLIST',  'HOD Shortlisting',         'gate',      'HOD'],
        ['L1',             'L1 Interview',             'interview', 'HIRING_MANAGER'],
        ['L2',             'L2 Interview',             'interview', 'HIRING_MANAGER', 0, 'grade', 'in', 'SENIOR,LEAD,MANAGER,VP'],
        ['DOCS',           'Document Collection',      'step',      'RECRUITER'],
        ['SALARY',         'Salary Structure',         'step',      'FINANCE'],
        ['HR_DISCUSS',     'HR Discussion',            'step',      'HR_HEAD'],
        ['SALARY_ACCEPT',  'Salary Acceptance',        'gate',      'RECRUITER'],
        ['MEDICAL',        'Medical Examination',      'step',      'MEDICAL', 0, 'medical_required', 'eq', 'YES'],
        ['REFERENCE',      'Reference Verification',   'step',      'HR_MANAGER'],
        ['MED_FITNESS',    'Medical Fitness Clearance','gate',      'MEDICAL', 0, 'medical_required', 'eq', 'YES'],
        ['ONE_PAGER',      'One-Pager Approval',       'gate',      'HR_HEAD'],
        ['OFFER',          'Offer Letter',             'offer',     'HR_HEAD'],
        ['OFFER_ACCEPT',   'Offer Acceptance',         'gate',      'RECRUITER'],
        ['ONBOARDING',     'Onboarding',               'terminal',  'HR_MANAGER'],
    ]);

    // A lean process — for companies that want a short flow.
    recruitpipe_create('SIMPLE', 'Simple Recruitment',
        'A short process: requisition to joining.', false, [
        ['SRF',        'Requisition', 'gate',      'HR_MANAGER'],
        ['SCREEN',     'Screening',   'gate',      'RECRUITER'],
        ['INTERVIEW',  'Interview',   'interview', 'HIRING_MANAGER'],
        ['SELECT',     'Selection',   'gate',      'HR_HEAD'],
        ['OFFER',      'Offer',       'offer',     'HR_HEAD'],
        ['JOINING',    'Joining',     'terminal',  'HR_MANAGER'],
    ]);

    // Executive search — a heavier, approval-rich flow.
    recruitpipe_create('EXEC', 'Executive Recruitment',
        'Senior / leadership hiring with search, compensation and references.', false, [
        ['SRF',        'Requisition',           'gate',      'HR_HEAD'],
        ['SEARCH',     'Search',                'step',      'RECRUITER'],
        ['SCREEN',     'Screening',             'gate',      'HR_HEAD'],
        ['MGMT_INT',   'Management Interview',  'interview', 'MANAGEMENT'],
        ['COMP',       'Compensation',          'step',      'FINANCE'],
        ['REFERENCE',  'Reference',             'step',      'HR_HEAD'],
        ['FINAL_APPR', 'Final Approval',        'gate',      'MANAGEMENT'],
        ['OFFER',      'Offer',                 'offer',     'HR_HEAD'],
    ]);
}

// Create one pipeline with its stages. $stages rows:
//   [stage_key, name, kind, responsible_role, mandatory=1, cond_field='', cond_op='', cond_value='']
function recruitpipe_create($code, $name, $description, $isDefault, array $stages) {
    $now = function_exists('now_iso') ? now_iso() : date('c');
    db()->prepare("INSERT INTO recruit_pipelines (code,name,description,is_default,active,sort,created_at)
                   VALUES (?,?,?,?,1,?,?)")
        ->execute([$code, $name, $description, $isDefault ? 1 : 0, 0, $now]);
    $pid = (int)db()->lastInsertId();
    $seq = 10;
    $ins = db()->prepare("INSERT INTO recruit_stages
        (pipeline_id,seq,stage_key,name,kind,responsible_role,mandatory,condition_field,condition_op,condition_value,active)
        VALUES (?,?,?,?,?,?,?,?,?,?,1)");
    foreach ($stages as $s) {
        $ins->execute([
            $pid, $seq, $s[0], $s[1], $s[2], $s[3] ?? '',
            array_key_exists(4, $s) ? (int)$s[4] : 1,
            $s[5] ?? '', $s[6] ?? '', $s[7] ?? '',
        ]);
        $seq += 10;
    }
    return $pid;
}

// ---- Reads -----------------------------------------------------------------
function recruitpipe_all($activeOnly = true) {
    recruitpipe_migrate();
    $w = $activeOnly ? "WHERE active=1" : "";
    return ops_all("SELECT * FROM recruit_pipelines $w ORDER BY is_default DESC, sort, id");
}
function recruitpipe_get($id) {
    recruitpipe_migrate();
    return ops_one("SELECT * FROM recruit_pipelines WHERE id=?", [(int)$id]);
}
function recruitpipe_default() {
    recruitpipe_migrate();
    $d = ops_one("SELECT * FROM recruit_pipelines WHERE active=1 AND is_default=1 ORDER BY id LIMIT 1");
    if ($d) return $d;
    return ops_one("SELECT * FROM recruit_pipelines WHERE active=1 ORDER BY sort, id LIMIT 1");
}
function recruitpipe_stages($pipelineId, $activeOnly = true) {
    recruitpipe_migrate();
    $w = $activeOnly ? "AND active=1" : "";
    return ops_all("SELECT * FROM recruit_stages WHERE pipeline_id=? $w ORDER BY seq, id", [(int)$pipelineId]);
}

// ---- Resolver: which pipeline applies to this requisition? -----------------
// Narrowest match wins: a pipeline scores +1 for every non-empty applies_*
// filter that matches the requisition context, and is disqualified if any of
// its filters is set but does NOT match. Ties fall back to is_default then sort.
function recruitpipe_context($req) {
    $req = (array)$req;
    $g = fn($k) => isset($req[$k]) ? (string)$req[$k] : '';
    return [
        'company'    => $g('client_name') ?: $g('client_id'),
        'sbu'        => $g('sbu'),
        'department' => $g('department'),
        'position'   => $g('designation') ?: $g('position'),
        'employment' => $g('req_type') ?: $g('employment_type'),
        'grade'      => $g('grade'),
        'location'   => $g('locations') ?: $g('project_site') ?: $g('location'),
        // A convenience flag some conditional stages key on.
        'medical_required' => ($g('cmp_medical') === '1' || strtoupper($g('medical_required')) === 'YES') ? 'YES' : 'NO',
    ];
}
function recruitpipe_for($req) {
    $ctx = recruitpipe_context($req);
    $pipes = recruitpipe_all(true);
    $best = null; $bestScore = -1;
    $map = [
        'applies_company' => 'company', 'applies_sbu' => 'sbu', 'applies_department' => 'department',
        'applies_position' => 'position', 'applies_employment' => 'employment',
        'applies_grade' => 'grade', 'applies_location' => 'location',
    ];
    foreach ($pipes as $p) {
        $score = 0; $ok = true;
        foreach ($map as $col => $ctxKey) {
            $filter = trim((string)($p[$col] ?? ''));
            if ($filter === '') continue;                 // wildcard
            $vals = array_map('strtolower', array_map('trim', explode(',', $filter)));
            if (in_array(strtolower(trim((string)$ctx[$ctxKey])), $vals, true)) $score++;
            else { $ok = false; break; }                  // a set filter that misses disqualifies
        }
        if (!$ok) continue;
        // Prefer higher score; tie-break default then lower sort.
        if ($score > $bestScore
            || ($score === $bestScore && $best && ((int)$p['is_default'] > (int)$best['is_default']))) {
            $best = $p; $bestScore = $score;
        }
    }
    return $best ?: recruitpipe_default();
}

// ---- Conditional stage evaluation (§10) ------------------------------------
function recruitpipe_stage_applies($stage, array $ctx) {
    $field = trim((string)($stage['condition_field'] ?? ''));
    if ($field === '') return true;                       // unconditional
    $op  = (string)($stage['condition_op'] ?? '');
    $val = (string)($stage['condition_value'] ?? '');
    $have = (string)($ctx[$field] ?? '');
    switch ($op) {
        case 'in':  $vals = array_map('strtolower', array_map('trim', explode(',', $val)));
                    return in_array(strtolower(trim($have)), $vals, true);
        case 'eq':  return strcasecmp(trim($have), trim($val)) === 0;
        case 'ne':  return strcasecmp(trim($have), trim($val)) !== 0;
        case 'gte': return is_numeric($have) && is_numeric($val) && (float)$have >= (float)$val;
        default:    return true;                           // no/unknown op = include
    }
}
// The stages that actually apply to a given requisition, in order.
function recruitpipe_effective_stages($pipelineId, $req) {
    $ctx = recruitpipe_context($req);
    $out = [];
    foreach (recruitpipe_stages($pipelineId, true) as $s)
        if (recruitpipe_stage_applies($s, $ctx)) $out[] = $s;
    return $out;
}

// ---- Writes (config screen) ------------------------------------------------
function recruitpipe_pipeline_save($id, $post) {
    recruitpipe_migrate();
    $cols = ['code','name','description','applies_company','applies_sbu','applies_department',
             'applies_position','applies_employment','applies_grade','applies_location'];
    $vals = []; foreach ($cols as $c) $vals[$c] = trim((string)($post[$c] ?? ''));
    if ((int)$id > 0) {
        $set = implode(',', array_map(fn($c) => "$c=?", $cols));
        $args = array_values($vals); $args[] = (int)$id;
        db()->prepare("UPDATE recruit_pipelines SET $set WHERE id=?")->execute($args);
        return (int)$id;
    }
    $now = function_exists('now_iso') ? now_iso() : date('c');
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare("INSERT INTO recruit_pipelines (".implode(',', $cols).",active,sort,created_at) VALUES ($ph,1,0,?)")
        ->execute(array_merge(array_values($vals), [$now]));
    return (int)db()->lastInsertId();
}
function recruitpipe_set_default($id) {
    recruitpipe_migrate();
    db()->exec("UPDATE recruit_pipelines SET is_default=0");
    db()->prepare("UPDATE recruit_pipelines SET is_default=1, active=1 WHERE id=?")->execute([(int)$id]);
}
function recruitpipe_pipeline_delete($id) {
    recruitpipe_migrate();
    $p = recruitpipe_get($id); if (!$p) return;
    if ((int)$p['is_default'] === 1) return;              // never delete the default
    db()->prepare("UPDATE recruit_pipelines SET active=0 WHERE id=?")->execute([(int)$id]);  // soft-disable
}
function recruitpipe_stage_save($post) {
    recruitpipe_migrate();
    $pid = (int)($post['pipeline_id'] ?? 0); if (!$pid) return;
    $sid = (int)($post['stage_id'] ?? 0);
    $kind = in_array($post['kind'] ?? '', array_keys(RPIPE_STAGE_KINDS), true) ? $post['kind'] : 'step';
    $op   = in_array($post['condition_op'] ?? '', array_keys(RPIPE_COND_OPS), true) ? $post['condition_op'] : '';
    $data = [
        (int)($post['seq'] ?? 0), trim((string)($post['stage_key'] ?? '')), trim((string)($post['name'] ?? '')),
        $kind, trim((string)($post['responsible_role'] ?? '')), empty($post['mandatory']) ? 0 : 1,
        trim((string)($post['condition_field'] ?? '')), $op, trim((string)($post['condition_value'] ?? '')),
        max(0, (int)($post['sla_days'] ?? 0)), trim((string)($post['required_docs'] ?? '')),
    ];
    if ($sid > 0) {
        $data[] = $sid;
        db()->prepare("UPDATE recruit_stages SET seq=?,stage_key=?,name=?,kind=?,responsible_role=?,mandatory=?,
                       condition_field=?,condition_op=?,condition_value=?,sla_days=?,required_docs=? WHERE id=?")->execute($data);
    } else {
        array_unshift($data, $pid);
        db()->prepare("INSERT INTO recruit_stages (pipeline_id,seq,stage_key,name,kind,responsible_role,mandatory,
                       condition_field,condition_op,condition_value,sla_days,required_docs,active)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)")->execute($data);
    }
}
function recruitpipe_stage_delete($sid) {
    recruitpipe_migrate();
    db()->prepare("UPDATE recruit_stages SET active=0 WHERE id=?")->execute([(int)$sid]);
}

// ============================================================================
//  Admin screen — Settings-style config, gated is_admin_level()
// ============================================================================
function ops_recruit_pipelines($route, $method) {
    ops_require(is_admin_level(), 'Only an administrator can configure hiring workflows.');
    recruitpipe_migrate();

    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'pipeline_save') {
            $id = recruitpipe_pipeline_save((int)($_POST['id'] ?? 0), $_POST);
            flash('Workflow saved.'); redirect('/recruit-pipelines?id=' . $id); return true;
        }
        if ($do === 'pipeline_new') {
            $id = recruitpipe_pipeline_save(0, $_POST + ['name' => 'New workflow', 'code' => 'NEW']);
            flash('Workflow created — now add its stages.'); redirect('/recruit-pipelines?id=' . $id); return true;
        }
        if ($do === 'pipeline_default') { recruitpipe_set_default((int)($_POST['id'] ?? 0)); flash('Default workflow set.'); redirect('/recruit-pipelines?id=' . (int)($_POST['id'] ?? 0)); return true; }
        if ($do === 'pipeline_delete')  { recruitpipe_pipeline_delete((int)($_POST['id'] ?? 0)); flash('Workflow disabled.'); redirect('/recruit-pipelines'); return true; }
        if ($do === 'stage_save')       { recruitpipe_stage_save($_POST); flash('Stage saved.'); redirect('/recruit-pipelines?id=' . (int)($_POST['pipeline_id'] ?? 0)); return true; }
        if ($do === 'stage_delete')     { recruitpipe_stage_delete((int)($_POST['stage_id'] ?? 0)); flash('Stage removed.'); redirect('/recruit-pipelines?id=' . (int)($_POST['pipeline_id'] ?? 0)); return true; }
    }

    $pipes = recruitpipe_all(false);
    $selId = (int)($_GET['id'] ?? 0);
    $sel = $selId ? recruitpipe_get($selId) : (recruitpipe_default() ?: ($pipes[0] ?? null));
    $stages = $sel ? recruitpipe_stages($sel['id'], false) : [];
    view('ops/recruit_pipelines', [
        'pipes'  => $pipes,
        'sel'    => $sel,
        'stages' => $stages,
        'kinds'  => RPIPE_STAGE_KINDS,
        'ops'    => RPIPE_COND_OPS,
    ]);
    return true;
}
