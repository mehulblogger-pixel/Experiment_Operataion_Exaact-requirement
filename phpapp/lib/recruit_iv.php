<?php
// ============================================================================
//  EXAACT Recruitment — Interviews (multi-round + scorecards) & Document DMS
//  (Phase 4; brief §22–§24). Additive and non-destructive.
//
//  Interviews become a first-class, multi-round entity (L1/L2/L3/HR/technical/
//  management/panel/client/practical/assessment) with a scorecard: competencies,
//  rating, recommendation, result and comments. Documents become a structured
//  set with configurable types and a real status lifecycle (required → requested
//  → uploaded → under review → verified / rejected / resubmit / expired).
//  Sensitive documents (salary, medical, identity) are download-restricted.
// ============================================================================

const IV_ROUNDS = ['L1', 'L2', 'L3', 'HR', 'Technical', 'Management', 'Panel', 'Client', 'Practical test', 'Assessment'];
const IV_MODES  = ['In person', 'Video', 'Phone'];
const IV_RESULTS = [
    'SCHEDULED'    => 'Scheduled',
    'PASS'         => 'Pass',
    'FAIL'         => 'Fail',
    'HOLD'         => 'Hold',
    'RE_INTERVIEW' => 'Re-interview',
    'NO_SHOW'      => 'No show',
    'CANCELLED'    => 'Cancelled',
];
const IV_RECOMMENDATIONS = ['', 'Strong hire', 'Hire', 'Hold', 'No hire'];

// Document status lifecycle (§24).
const DOC_STATUSES = [
    'NOT_REQUIRED' => 'Not required',
    'REQUIRED'     => 'Required',
    'REQUESTED'    => 'Requested',
    'UPLOADED'     => 'Uploaded',
    'UNDER_REVIEW' => 'Under review',
    'VERIFIED'     => 'Verified',
    'REJECTED'     => 'Rejected',
    'RESUBMIT'     => 'Resubmission required',
    'EXPIRED'      => 'Expired',
];
// Default document types (configurable on the Masters screen: candidate_doc_type).
const DOC_TYPES_SEED = [
    'Resume / CV', 'Educational certificate', 'Experience certificate', 'Identity (KYC)',
    'Address proof', 'PAN', 'Salary slip', 'Relieving letter', 'Reference', 'Photograph',
    'Medical report', 'Other',
];
// Types whose file is sensitive — download restricted to admin-level users (§39).
const DOC_SENSITIVE = ['Salary slip', 'Medical report', 'Identity (KYC)', 'PAN'];

function doc_types() {
    return function_exists('lk_options_or')
        ? array_values(lk_options_or('candidate_doc_type', array_combine(DOC_TYPES_SEED, DOC_TYPES_SEED)))
        : DOC_TYPES_SEED;
}
function doc_is_sensitive($type) { return in_array((string)$type, DOC_SENSITIVE, true); }

// ---- Schema (one migrate creates both tables; wired into boot) --------------
function recruit_iv_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $lt = (function_exists('db_driver') && db_driver() === 'sqlite') ? 'TEXT' : 'LONGTEXT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS interviews (
            id $pk,
            candidate_id INT,
            round VARCHAR(40) DEFAULT 'L1',
            mode VARCHAR(30) DEFAULT 'In person',
            location VARCHAR(240) DEFAULT '',
            scheduled_at VARCHAR(30) DEFAULT '',
            panel VARCHAR(300) DEFAULT '',
            competencies VARCHAR(400) DEFAULT '',
            questions $lt,
            rating INT DEFAULT 0,
            recommendation VARCHAR(40) DEFAULT '',
            result VARCHAR(20) DEFAULT 'SCHEDULED',
            comments $lt,
            created_by VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '',
            done_at VARCHAR(30) DEFAULT ''
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS candidate_docs (
            id $pk,
            candidate_id INT,
            doc_type VARCHAR(120) DEFAULT '',
            `sensitive` INT DEFAULT 0,
            file_name VARCHAR(200) DEFAULT '',
            file_data $lt,
            issue_date VARCHAR(20) DEFAULT '',
            expiry_date VARCHAR(20) DEFAULT '',
            status VARCHAR(20) DEFAULT 'REQUIRED',
            uploaded_by VARCHAR(160) DEFAULT '',
            uploaded_at VARCHAR(30) DEFAULT '',
            verifier VARCHAR(160) DEFAULT '',
            verified_at VARCHAR(30) DEFAULT '',
            rejection_reason VARCHAR(300) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
        if (function_exists('act_index')) {
            act_index('interviews', 'idx_iv_cand', '(candidate_id)');
            act_index('candidate_docs', 'idx_cd_cand', '(candidate_id)');
        }
        // A person's department — used to default an interview panel to the
        // candidate's own department. Additive; blank on existing users.
        ensure_column('users', 'department', "VARCHAR(120) DEFAULT ''");
    } catch (Throwable $e) { /* never break boot */ }
}

function _iv_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function _iv_actor() { return function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'system'; }

// ============================================================================
//  Interviews
// ============================================================================
function iv_list($candidateId) {
    recruit_iv_migrate();
    return ops_all("SELECT * FROM interviews WHERE candidate_id=? ORDER BY id DESC", [(int)$candidateId]);
}
function iv_get($id) { recruit_iv_migrate(); return ops_one("SELECT * FROM interviews WHERE id=?", [(int)$id]) ?: null; }

function iv_schedule($candidateId, $post) {
    recruit_iv_migrate();
    $round = in_array($post['round'] ?? '', IV_ROUNDS, true) ? $post['round'] : 'L1';
    $mode  = in_array($post['mode'] ?? '', IV_MODES, true) ? $post['mode'] : 'In person';
    db()->prepare("INSERT INTO interviews (candidate_id,round,mode,location,scheduled_at,panel,competencies,result,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,'SCHEDULED',?,?)")
        ->execute([(int)$candidateId, $round, $mode, trim((string)($post['location'] ?? '')),
            trim((string)($post['scheduled_at'] ?? '')), iv_panel_from_post($post),
            trim((string)($post['competencies'] ?? '')), _iv_actor(), _iv_now()]);
    return (int)db()->lastInsertId();
}
function iv_record($id, $post) {
    recruit_iv_migrate();
    $result = array_key_exists($post['result'] ?? '', IV_RESULTS) ? $post['result'] : 'SCHEDULED';
    $rec = in_array($post['recommendation'] ?? '', IV_RECOMMENDATIONS, true) ? $post['recommendation'] : '';
    $rating = max(0, min(5, (int)($post['rating'] ?? 0)));
    $done = in_array($result, ['PASS','FAIL','HOLD','NO_SHOW','CANCELLED'], true) ? _iv_now() : '';
    db()->prepare("UPDATE interviews SET result=?, rating=?, recommendation=?, competencies=?, questions=?, comments=?, done_at=? WHERE id=?")
        ->execute([$result, $rating, $rec, trim((string)($post['competencies'] ?? '')),
            trim((string)($post['questions'] ?? '')), trim((string)($post['comments'] ?? '')), $done, (int)$id]);
}
function iv_delete($id) { recruit_iv_migrate(); db()->prepare("DELETE FROM interviews WHERE id=?")->execute([(int)$id]); }

// The people who can sit on an interview panel — active users, with their
// department so the panel can default to the candidate's own department.
function iv_interviewers() {
    recruit_iv_migrate();
    try {
        return ops_all("SELECT id, first_name, last_name, COALESCE(department,'') department, COALESCE(role,'') role
                        FROM users WHERE is_active=1 ORDER BY first_name, last_name");
    } catch (Throwable $e) { return []; }
}
// The department a candidate is being hired into (candidate row, else its
// requisition, else the requisition's position) — used to pre-tick the panel.
function iv_candidate_department($cand) {
    $dept = trim((string)($cand['department'] ?? ''));
    if ($dept === '' && !empty($cand['requisition_id'])) {
        $req = ops_one("SELECT department, position_id FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]);
        $dept = trim((string)($req['department'] ?? ''));
        if ($dept === '' && $req && !empty($req['position_id']) && function_exists('position_get')) {
            $pos = position_get((int)$req['position_id']); if ($pos) $dept = trim((string)($pos['department'] ?? ''));
        }
    }
    return $dept;
}
// Build the stored panel string from the multi-select (user ids) plus any free
// text for people not in the system (e.g. a client-side interviewer). Falls back
// to a legacy plain 'panel' text field so older forms still work.
function iv_panel_from_post($post) {
    $names = [];
    $ids = array_values(array_filter(array_map('intval', (array)($post['panel_users'] ?? []))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            foreach (ops_all("SELECT first_name, last_name FROM users WHERE id IN ($in)", $ids) as $u) {
                $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                if ($nm !== '') $names[] = $nm;
            }
        } catch (Throwable $e) { /* ignore — fall through to extra/legacy */ }
    }
    $extra = trim((string)($post['panel_extra'] ?? ''));
    if ($extra !== '') $names[] = $extra;
    if (!$names && trim((string)($post['panel'] ?? '')) !== '') return trim((string)$post['panel']);
    return implode(', ', $names);
}

// ============================================================================
//  Documents
// ============================================================================
function docs_list($candidateId) {
    recruit_iv_migrate();
    return ops_all("SELECT * FROM candidate_docs WHERE candidate_id=? ORDER BY id", [(int)$candidateId]);
}
function doc_get($id) { recruit_iv_migrate(); return ops_one("SELECT * FROM candidate_docs WHERE id=?", [(int)$id]) ?: null; }

// The effective (display) status — a verified doc whose expiry has passed reads EXPIRED.
function doc_effective_status($d) {
    $s = (string)($d['status'] ?? '');
    if ($s === 'VERIFIED' && !empty($d['expiry_date']) && strtotime($d['expiry_date']) && strtotime($d['expiry_date']) < strtotime(date('Y-m-d')))
        return 'EXPIRED';
    return $s;
}

// Add a required/requested document row (a checklist item, no file yet).
function doc_add($candidateId, $post) {
    recruit_iv_migrate();
    $type = trim((string)($post['doc_type'] ?? 'Other')) ?: 'Other';
    $status = array_key_exists($post['status'] ?? '', DOC_STATUSES) ? $post['status'] : 'REQUIRED';
    db()->prepare("INSERT INTO candidate_docs (candidate_id,doc_type,`sensitive`,status,created_at) VALUES (?,?,?,?,?)")
        ->execute([(int)$candidateId, $type, doc_is_sensitive($type) ? 1 : 0, $status, _iv_now()]);
    return (int)db()->lastInsertId();
}
// Attach an uploaded file to a doc row (or create one on the fly), status → UPLOADED.
function doc_upload($candidateId, $post, $file) {
    recruit_iv_migrate();
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return [false, 'No file chosen.'];
    $bytes = file_get_contents($file['tmp_name']);
    if (strlen($bytes) > 12 * 1024 * 1024) return [false, 'File too large (max 12 MB).'];
    $data = 'data:' . ($file['type'] ?: 'application/octet-stream') . ';base64,' . base64_encode($bytes);
    $name = basename($file['name']);
    $id = (int)($post['doc_id'] ?? 0);
    $issue = trim((string)($post['issue_date'] ?? ''));
    $expiry = trim((string)($post['expiry_date'] ?? ''));
    if ($id > 0) {
        db()->prepare("UPDATE candidate_docs SET file_name=?, file_data=?, issue_date=?, expiry_date=?, status='UPLOADED', uploaded_by=?, uploaded_at=?, rejection_reason='' WHERE id=?")
            ->execute([$name, $data, $issue, $expiry, _iv_actor(), _iv_now(), $id]);
    } else {
        $type = trim((string)($post['doc_type'] ?? 'Other')) ?: 'Other';
        db()->prepare("INSERT INTO candidate_docs (candidate_id,doc_type,`sensitive`,file_name,file_data,issue_date,expiry_date,status,uploaded_by,uploaded_at,created_at)
                       VALUES (?,?,?,?,?,?,?, 'UPLOADED', ?,?,?)")
            ->execute([(int)$candidateId, $type, doc_is_sensitive($type) ? 1 : 0, $name, $data, $issue, $expiry, _iv_actor(), _iv_now(), _iv_now()]);
    }
    return [true, 'Document uploaded.'];
}
function doc_verify($id) {
    recruit_iv_migrate();
    db()->prepare("UPDATE candidate_docs SET status='VERIFIED', verifier=?, verified_at=?, rejection_reason='' WHERE id=?")
        ->execute([_iv_actor(), _iv_now(), (int)$id]);
}
function doc_reject($id, $reason, $resubmit = false) {
    recruit_iv_migrate();
    db()->prepare("UPDATE candidate_docs SET status=?, verifier=?, verified_at=?, rejection_reason=? WHERE id=?")
        ->execute([$resubmit ? 'RESUBMIT' : 'REJECTED', _iv_actor(), _iv_now(), substr((string)$reason, 0, 300), (int)$id]);
}
function doc_status_set($id, $status) {
    recruit_iv_migrate();
    if (!array_key_exists($status, DOC_STATUSES)) return;
    db()->prepare("UPDATE candidate_docs SET status=? WHERE id=?")->execute([$status, (int)$id]);
}
function doc_delete($id) { recruit_iv_migrate(); db()->prepare("DELETE FROM candidate_docs WHERE id=?")->execute([(int)$id]); }

// A candidate may download a sensitive file only if admin-level.
function doc_can_download($d) {
    if ((int)($d['sensitive'] ?? 0) !== 1) return true;
    return function_exists('is_admin_level') && is_admin_level();
}

// ============================================================================
//  Routes
// ============================================================================
function ops_candidate_interview($route, $method) {
    recruit_iv_migrate();
    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage interviews.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'schedule') { iv_schedule($id, $_POST); flash('Interview scheduled.'); }
        elseif ($do === 'record') { iv_record((int)($_POST['iv_id'] ?? 0), $_POST); flash('Interview outcome saved.'); }
        elseif ($do === 'delete') { iv_delete((int)($_POST['iv_id'] ?? 0)); flash('Interview removed.'); }
    }
    redirect('/candidate?id=' . $id . '#tab=Interviews');
    return true;
}

function ops_candidate_doc($route, $method) {
    recruit_iv_migrate();
    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage documents.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'add') { doc_add($id, $_POST); flash('Document added to the checklist.'); }
        elseif ($do === 'upload') { [$ok, $msg] = doc_upload($id, $_POST, $_FILES['file'] ?? []); flash($msg, $ok ? 'success' : 'error'); }
        elseif ($do === 'verify') { doc_verify((int)($_POST['doc_id'] ?? 0)); flash('Document verified.'); }
        elseif ($do === 'reject') { doc_reject((int)($_POST['doc_id'] ?? 0), $_POST['reason'] ?? '', !empty($_POST['resubmit'])); flash('Document marked ' . (!empty($_POST['resubmit']) ? 'for resubmission' : 'rejected') . '.'); }
        elseif ($do === 'status') { doc_status_set((int)($_POST['doc_id'] ?? 0), (string)($_POST['status'] ?? '')); flash('Document status updated.'); }
        elseif ($do === 'delete') { doc_delete((int)($_POST['doc_id'] ?? 0)); flash('Document removed.'); }
    }
    redirect('/candidate?id=' . $id . '#tab=Documents');
    return true;
}

// ============================================================================
//  Panels (rendered as tabs on the candidate screen)
// ============================================================================
function recruit_iv_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $ivs = iv_list((int)$cand['id']);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $ivUsers = $can ? iv_interviewers() : [];
    $ivDept  = strtolower(trim(iv_candidate_department($cand)));
    $pill = ['PASS'=>'p-ok','FAIL'=>'p-bad','HOLD'=>'p-warn','RE_INTERVIEW'=>'p-warn','NO_SHOW'=>'p-bad','CANCELLED'=>'p-mut','SCHEDULED'=>'p-info'];
    ?>
    <div class="panel">
      <h3 class="tab-sub">Interviews <span class="muted" style="font-weight:400">(<?= count($ivs) ?>)</span></h3>
      <?php if ($can): ?>
      <form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;margin-bottom:12px">
        <input type="hidden" name="do" value="schedule">
        <b style="font-size:12.5px">Schedule an interview</b>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:6px">
          <div><label class="ff-l">Round</label><select class="form-control" name="round"><?php foreach (IV_ROUNDS as $r): ?><option><?= $e($r) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">When</label><input class="form-control" type="datetime-local" name="scheduled_at"></div>
          <div><label class="ff-l">Mode</label><select class="form-control" name="mode"><?php foreach (IV_MODES as $m): ?><option><?= $e($m) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">Location / link</label><input class="form-control" name="location"></div>
          <div style="grid-column:1/-1"><label class="ff-l">Competencies to assess</label><input class="form-control" name="competencies"></div>
        </div>
        <?php // Interview panel — pick one or more interviewers from your people.
              //  Those in the candidate's own department are pre-ticked; change
              //  them freely, and add anyone not in the system in the box below. ?>
        <div style="margin-top:10px">
          <label class="ff-l">Interview panel — choose interviewers<?= $ivDept !== '' ? ' <span class="muted" style="font-weight:400">(people in the ' . $e(iv_candidate_department($cand)) . ' department are pre-selected)</span>' : '' ?></label>
          <?php if ($ivUsers): ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px 14px;max-height:150px;overflow:auto;border:1px solid var(--line,#e5e7eb);border-radius:9px;padding:9px 11px;background:var(--surface,#fff)">
            <?php foreach ($ivUsers as $u): $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); if ($nm === '') continue;
                  $udept = strtolower(trim((string)($u['department'] ?? '')));
                  $pre = ($ivDept !== '' && $udept === $ivDept); ?>
              <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap">
                <input type="checkbox" name="panel_users[]" value="<?= (int)$u['id'] ?>" <?= $pre ? 'checked' : '' ?>>
                <?= $e($nm) ?><?= ($u['department'] ?? '') !== '' ? ' <span class="muted" style="font-size:11px">· ' . $e($u['department']) . '</span>' : '' ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <input class="form-control" name="panel_extra" placeholder="<?= $ivUsers ? 'Other panelists not in the system (e.g. a client-side interviewer)' : 'Panel / interviewers (names)' ?>" style="margin-top:7px">
        </div>
        <div style="margin-top:10px"><button class="btn">Schedule</button></div>
      </form>
      <?php endif; ?>
      <?php if (!$ivs): ?><p class="muted">No interviews yet.</p><?php endif; ?>
      <?php foreach ($ivs as $iv): $st = (string)$iv['result']; ?>
        <div style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline">
            <b><?= $e($iv['round']) ?> · <?= $e($iv['mode']) ?><?= $iv['scheduled_at'] ? ' · ' . $e(function_exists('fdate') ? fdate(substr($iv['scheduled_at'],0,10), substr($iv['scheduled_at'],0,10)) . ' ' . substr($iv['scheduled_at'],11,5) : $iv['scheduled_at']) : '' ?></b>
            <span class="pill <?= $pill[$st] ?? 'p-mut' ?>"><?= $e(IV_RESULTS[$st] ?? $st) ?><?= (int)$iv['rating'] ? ' · ' . str_repeat('★', (int)$iv['rating']) : '' ?></span>
          </div>
          <?php if ($iv['panel']): ?><div class="muted" style="font-size:12px">Panel: <?= $e($iv['panel']) ?><?= $iv['location'] ? ' · ' . $e($iv['location']) : '' ?></div><?php endif; ?>
          <?php if ($iv['competencies']): ?><div class="muted" style="font-size:12px">Competencies: <?= $e($iv['competencies']) ?></div><?php endif; ?>
          <?php if ($iv['recommendation'] || $iv['comments']): ?><div style="font-size:12.5px;margin-top:4px"><?= $iv['recommendation'] ? '<b>' . $e($iv['recommendation']) . '</b> — ' : '' ?><?= $e($iv['comments']) ?></div><?php endif; ?>
          <?php if ($can): ?>
          <details style="margin-top:8px"><summary style="cursor:pointer;font-size:12px;color:var(--brand,#1e40af)">Record / edit scorecard</summary>
            <form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="margin-top:8px">
              <input type="hidden" name="do" value="record"><input type="hidden" name="iv_id" value="<?= (int)$iv['id'] ?>">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                <div><label class="ff-l">Result</label><select class="form-control" name="result"><?php foreach (IV_RESULTS as $k=>$v): ?><option value="<?= $k ?>" <?= $st===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
                <div><label class="ff-l">Rating (0–5)</label><input class="form-control" type="number" min="0" max="5" name="rating" value="<?= (int)$iv['rating'] ?>"></div>
                <div><label class="ff-l">Recommendation</label><select class="form-control" name="recommendation"><?php foreach (IV_RECOMMENDATIONS as $r): ?><option value="<?= $e($r) ?>" <?= $iv['recommendation']===$r?'selected':'' ?>><?= $e($r ?: '—') ?></option><?php endforeach; ?></select></div>
              </div>
              <label class="ff-l" style="margin-top:8px">Competencies assessed</label><input class="form-control" name="competencies" value="<?= $e($iv['competencies']) ?>">
              <label class="ff-l" style="margin-top:8px">Questions asked</label><textarea class="form-control" name="questions" rows="2"><?= $e($iv['questions']) ?></textarea>
              <label class="ff-l" style="margin-top:8px">Comments</label><textarea class="form-control" name="comments" rows="2"><?= $e($iv['comments']) ?></textarea>
              <div style="margin-top:8px;display:flex;gap:8px"><button class="btn">Save scorecard</button>
                <button class="btn secondary" name="do" value="delete" onclick="return confirm('Remove this interview?')">Delete</button></div>
            </form>
          </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <style>.ff-l{display:block;font-size:11.5px;font-weight:600;color:var(--muted,#656e7a);margin-bottom:3px}</style>
    <?php
}

function recruit_docs_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $docs = docs_list((int)$cand['id']);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $pill = ['VERIFIED'=>'p-ok','REJECTED'=>'p-bad','EXPIRED'=>'p-bad','RESUBMIT'=>'p-warn','UNDER_REVIEW'=>'p-info','UPLOADED'=>'p-info','REQUESTED'=>'p-warn','REQUIRED'=>'p-mut','NOT_REQUIRED'=>'p-mut'];
    ?>
    <div class="panel">
      <h3 class="tab-sub">Documents <span class="muted" style="font-weight:400">(<?= count($docs) ?>)</span></h3>
      <?php if ($can): ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
        <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
          <input type="hidden" name="do" value="add">
          <div><label class="ff-l">Add a required document</label>
            <select class="form-control" name="doc_type"><?php foreach (doc_types() as $t): ?><option><?= $e($t) ?></option><?php endforeach; ?></select></div>
          <button class="btn secondary">Add to checklist</button>
        </form>
        <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
          <input type="hidden" name="do" value="upload">
          <div><label class="ff-l">Or upload a document</label>
            <select class="form-control" name="doc_type"><?php foreach (doc_types() as $t): ?><option><?= $e($t) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">File (≤12 MB)</label><input class="form-control" type="file" name="file"></div>
          <div><label class="ff-l">Expiry (optional)</label><input class="form-control" type="date" name="expiry_date"></div>
          <button class="btn">Upload</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if (!$docs): ?><p class="muted">No documents yet — add the ones you need to the checklist.</p><?php else: ?>
      <table style="width:100%;border-collapse:collapse">
        <tr><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Document</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Status</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Dates</th><th></th></tr>
        <?php foreach ($docs as $d): $st = doc_effective_status($d); ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <td style="padding:8px"><b><?= $e($d['doc_type']) ?></b><?= (int)$d['sensitive'] ? ' <span class="pill p-mut" style="font-size:10px">sensitive</span>' : '' ?>
            <?php if ($d['file_name']): ?><br>
              <?php if (doc_can_download($d) && $d['file_data']): ?><a href="<?= $e($d['file_data']) ?>" download="<?= $e($d['file_name']) ?>" style="font-size:12px"><?= $e($d['file_name']) ?></a>
              <?php else: ?><span class="muted" style="font-size:12px"><?= $e($d['file_name']) ?> <em>(restricted)</em></span><?php endif; ?>
            <?php endif; ?>
            <?php if ($d['rejection_reason']): ?><br><span class="p-bad" style="font-size:11.5px">✕ <?= $e($d['rejection_reason']) ?></span><?php endif; ?>
          </td>
          <td style="padding:8px"><span class="pill <?= $pill[$st] ?? 'p-mut' ?>"><?= $e(DOC_STATUSES[$st] ?? $st) ?></span>
            <?php if ($d['verifier'] && $st==='VERIFIED'): ?><div class="muted" style="font-size:11px"><?= $e($d['verifier']) ?></div><?php endif; ?></td>
          <td style="padding:8px;font-size:12px;color:var(--muted,#656e7a)"><?= $d['issue_date'] ? 'Issued ' . $e($d['issue_date']) : '' ?><?= $d['expiry_date'] ? '<br>Expires ' . $e($d['expiry_date']) : '' ?></td>
          <td style="padding:8px;white-space:nowrap">
            <?php if ($can): ?>
              <?php if (in_array($st, ['UPLOADED','UNDER_REVIEW','RESUBMIT'], true)): ?>
                <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline"><input type="hidden" name="do" value="verify"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Verify</button></form>
                <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="this.reason.value=prompt('Reason for rejection / resubmission?')||'';return this.reason.value!==''"><input type="hidden" name="do" value="reject"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="resubmit" value="1"><input type="hidden" name="reason"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Return</button></form>
              <?php endif; ?>
              <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Remove this document?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn secondary" style="padding:4px 9px;font-size:12px">✕</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php
    // The auto-generated letters (offer / appointment / one-pager / …) belong in
    // the Documents tab too — this is where anyone looks for a candidate's papers,
    // not only at the foot of the Offer tab. Merge tokens are filled from the
    // candidate's own data; missing fields are highlighted in the letter.
    if (function_exists('recruit_letters_block')) recruit_letters_block($cand);
}
