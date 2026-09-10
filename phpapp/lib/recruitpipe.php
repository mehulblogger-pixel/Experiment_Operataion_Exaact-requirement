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
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
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
        // Phase 2b — the candidate's position within its configured pipeline.
        // Additive nullable columns; the legacy `stage` (CAND_STAGES) is untouched.
        ensure_column('candidates', 'pipeline_id', 'INT NULL');
        ensure_column('candidates', 'pipeline_stage_id', 'INT NULL');
        // A grade band on the requisition, so a stage can be made conditional on
        // seniority (e.g. an L2 only for senior grades). Additive; the SRF form
        // exposes it in Phase 3. Empty = "any grade".
        ensure_column('requisitions', 'grade', "VARCHAR(80) DEFAULT ''");
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
    ops_require(hiring_admin_can(), 'Only an administrator can configure hiring workflows.');
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

// ============================================================================
//  Phase 2b — drive a real candidate along its configured pipeline.
//
//  The candidate's detailed journey runs on the configured pipeline (stored in
//  candidates.pipeline_stage_id). The legacy `stage` (CAND_STAGES) is preserved
//  and kept in COARSE sync only at the interview/offer milestones, so existing
//  dashboards keep working and the explicit Hire action (create inspector) is
//  never triggered as a side-effect here.
// ============================================================================

// Legacy stages that mean "closed" — the configured flow is locked at these.
function recruitpipe_legacy_terminal() { return ['ACCEPTED', 'REJECTED', 'WITHDRAWN', 'OFFER_DECLINED']; }

// Resolve a candidate's live position: [pipeline, effectiveStages, idx].
function recruitpipe_cand_state($cand) {
    recruitpipe_migrate();
    $req = !empty($cand['requisition_id'])
        ? ops_one("SELECT * FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]) : [];
    $req = is_array($req) ? $req : [];
    // A locked pipeline (chosen on first move) wins; otherwise resolve by rule.
    $pipe = null;
    if (!empty($cand['pipeline_id'])) $pipe = recruitpipe_get((int)$cand['pipeline_id']);
    if (!$pipe || (int)($pipe['active'] ?? 0) === 0) $pipe = recruitpipe_for($req);
    if (!$pipe) return [null, [], 0];
    $eff = recruitpipe_effective_stages($pipe['id'], $req);
    $idx = 0;
    if (!empty($cand['pipeline_stage_id'])) {
        foreach ($eff as $i => $s) if ((int)$s['id'] === (int)$cand['pipeline_stage_id']) { $idx = $i; break; }
    }
    return [$pipe, $eff, $idx];
}

// Move a candidate to a specific stage id within its pipeline (with audit).
function recruitpipe_cand_goto($cand, $targetStageId, $remark, $actor) {
    [$pipe, $eff, $idx] = recruitpipe_cand_state($cand);
    if (!$pipe || !$eff) return false;
    $target = null; foreach ($eff as $s) if ((int)$s['id'] === (int)$targetStageId) { $target = $s; break; }
    if (!$target) return false;
    $fromName = $eff[$idx]['name'] ?? '';
    db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=? WHERE id=?")
        ->execute([(int)$pipe['id'], (int)$target['id'], (int)$cand['id']]);
    db()->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$cand['id'], $fromName, $target['name'], (string)$remark, (string)$actor, date('c')]);
    // Coarse legacy sync — only at the interview/offer milestones, and never
    // over a terminal legacy stage (so hire/loss handling is never disturbed).
    $cur = (string)($cand['stage'] ?? '');
    if (!in_array($cur, recruitpipe_legacy_terminal(), true)) {
        if ($target['kind'] === 'interview' && in_array($cur, ['RECEIVED','SUBMITTED','SHORTLISTED',''], true))
            db()->prepare("UPDATE candidates SET stage='INTERVIEW' WHERE id=?")->execute([(int)$cand['id']]);
        elseif ($target['kind'] === 'offer' && $cur !== 'ACCEPTED')
            db()->prepare("UPDATE candidates SET stage='OFFERED' WHERE id=?")->execute([(int)$cand['id']]);
    }
    return true;
}

// The candidate-flow route: advance / back / jump within the pipeline.
function ops_recruit_candidate_flow($route, $method) {
    ops_require(is_coordinator_level(), 'Only coordinators and admins can move a candidate.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    if ($method !== 'POST') { redirect('/candidate?id=' . $id); return true; }

    if (in_array((string)$cand['stage'], recruitpipe_legacy_terminal(), true)) {
        flash('This candidate is closed (' . (lk_options_or('candidate_stage', CAND_STAGES)[$cand['stage']] ?? $cand['stage']) . ') — reopen it from the stage control to continue the workflow.', 'warning');
        redirect('/candidate?id=' . $id); return true;
    }

    [$pipe, $eff, $idx] = recruitpipe_cand_state($cand);
    if (!$pipe || !$eff) { flash('No hiring workflow applies to this candidate yet.', 'warning'); redirect('/candidate?id=' . $id); return true; }

    $action = (string)($_POST['action'] ?? '');
    $remark = trim((string)($_POST['remark'] ?? ''));
    $target = null;
    if ($action === 'advance') $target = $eff[min($idx + 1, count($eff) - 1)] ?? null;
    elseif ($action === 'back') $target = $eff[max($idx - 1, 0)] ?? null;
    elseif ($action === 'jump') { $tid = (int)($_POST['stage_id'] ?? 0); foreach ($eff as $s) if ((int)$s['id'] === $tid) $target = $s; }

    if ($target && (int)$target['id'] !== (int)($eff[$idx]['id'] ?? 0)) {
        recruitpipe_cand_goto($cand, (int)$target['id'], $remark, user_name(current_user()));
        flash('Moved to “' . $target['name'] . '”.');
    } elseif ($target) {
        flash('Already at that stage.');
    }
    redirect('/candidate?id=' . $id);
    return true;
}

// The panel injected at the top of the candidate screen — the primary tracker.
function recruitpipe_candidate_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    [$pipe, $eff, $idx] = recruitpipe_cand_state($cand);
    if (!$pipe || !$eff) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $closed = in_array((string)$cand['stage'], recruitpipe_legacy_terminal(), true);
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $cur = $eff[$idx] ?? null;
    ?>
    <div class="panel" style="border-left:4px solid var(--brand,#1e40af);padding:13px 16px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
        <b style="font-size:13.5px">Hiring workflow — <?= $e($pipe['name']) ?></b>
        <span class="muted" style="font-size:12px"><?= $closed ? 'Closed ('.$e(lk_options_or('candidate_stage', CAND_STAGES)[$cand['stage']] ?? $cand['stage']).')' : ('Stage '.($idx+1).' of '.count($eff).($cur?' · '.$e($cur['name']):'')) ?></span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin:9px 0 4px">
        <?php foreach ($eff as $i => $s):
          $cls = $i < $idx ? 'done' : ($i === $idx ? 'now' : '');
          $bg = $cls === 'done' ? 'background:#dcfce7;color:#15803d' : ($cls === 'now' ? 'background:var(--brand,#1e40af);color:#fff;font-weight:700' : 'background:#f1f5f9;color:#64748b'); ?>
          <span style="font-size:10.5px;padding:3px 9px;border-radius:16px;white-space:nowrap;<?= $bg ?>"><?= $e($s['name']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php if ($can && !$closed): ?>
      <form method="post" action="/candidate-flow?id=<?= (int)$cand['id'] ?>" style="display:flex;gap:7px;align-items:center;margin-top:8px;flex-wrap:wrap">
        <input name="remark" placeholder="remark (optional)" style="flex:1;min-width:160px;padding:6px 9px;border:1px solid var(--line,#d7dde5);border-radius:7px;font:inherit">
        <?php if ($idx > 0): ?><button name="action" value="back" class="btn secondary" style="padding:6px 11px">← Back</button><?php endif; ?>
        <?php if ($idx < count($eff) - 1): ?>
          <button name="action" value="advance" class="btn" style="padding:6px 13px">Advance → <?= $e($eff[$idx + 1]['name'] ?? '') ?></button>
        <?php else: ?>
          <span class="muted" style="font-size:12px">Final stage — complete the hire from the stage control below.</span>
        <?php endif; ?>
      </form>
      <?php elseif ($closed): ?>
        <div class="muted" style="font-size:12px;margin-top:4px">The workflow is locked while the candidate is closed.</div>
      <?php endif; ?>
    </div>
    <?php
}
