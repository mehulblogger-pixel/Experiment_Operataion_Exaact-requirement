<?php
// ============================================================================
//  Nonconformities and corrective actions — ISO/IEC 17020 §8.7
//
//  Complaints (3.4) already demand a corrective-action reference before an
//  upheld complaint can close. This is the register behind that reference.
//
//  Almost every body has something like this already, usually a spreadsheet,
//  and almost every one of them loses the same two marks:
//
//   1. THE EFFECTIVENESS REVIEW NEVER HAPPENS. §8.7.3 says the body shall
//      review the effectiveness of any corrective action taken. In practice
//      somebody does the action, ticks "closed", and nobody ever goes back to
//      see whether the problem stopped. So here a corrective action CANNOT be
//      closed until somebody has gone back, on a date, and said whether it
//      worked — and if it did not work, closing as "done" is refused outright.
//
//   2. NOBODY ASKS WHETHER IT HAPPENED ELSEWHERE. §8.7.2 d) asks the body to
//      determine whether similar nonconformities exist, or could occur. It is
//      one line in the standard and it is the single most-missed one, because
//      it is the only part that costs real thinking. It is a required answer
//      here, with a note, and "no" is a perfectly good answer as long as
//      somebody actually said it.
//
//  What is deliberately NOT done: no scoring, no automatic root cause, no
//  "AI-suggested" corrective action. A root cause that software guessed is a
//  root cause nobody owns, and an assessor will find that out in one question.
// ============================================================================

// Where the nonconformity came from. The first two are wired: a complaint or an
// audit finding raises one directly and the link is kept both ways.
const CAPA_SOURCES = [
    // A corrective action is the response; the nonconformity is the event. Most
    // corrective actions should now arrive by escalation from one — see lib/ncr.php.
    'NCR'              => 'A nonconformity',
    'COMPLAINT'        => 'A complaint or appeal',
    'INTERNAL_AUDIT'   => 'An internal audit finding',
    'EXTERNAL_AUDIT'   => 'An assessment by an accreditation or client body',
    'MANAGEMENT_REVIEW'=> 'A management review decision',
    'INCIDENT'         => 'An incident',
    'STAFF'            => 'Raised by our own people',
    'CLIENT_FEEDBACK'  => 'Client feedback short of a complaint',
    'RISK'             => 'A risk we identified before it bit',
    'SELF_IDENTIFIED'  => 'We found it ourselves in the course of the work',
];

const CAPA_SEVERITY = [
    'MAJOR'       => 'Major — the management system failed, or the result is in doubt',
    'MINOR'       => 'Minor — a lapse against our own procedure',
    'OBSERVATION' => 'Observation — no failure yet, but it is heading somewhere',
];

// How the cause was worked out. Not a judgement of quality — an assessor asks
// "how did you arrive at that?" and this is the answer.
const CAPA_RC_METHODS = [
    'FIVE_WHY'   => 'Five whys',
    'FISHBONE'   => 'Cause and effect (fishbone)',
    'INTERVIEW'  => 'Interview and record review',
    'DATA'       => 'Looked at the data across similar work',
    'OTHER'      => 'Other',
];

const CAPA_STATUS = [
    'OPEN'          => 'Open — cause and plan not yet settled',
    'IN_PROGRESS'   => 'Action in progress',
    'VERIFYING'     => 'Done, waiting to see whether it worked',
    'CLOSED'        => 'Closed — verified effective',
    'CLOSED_FAILED' => 'Closed as ineffective — a further action was raised',
];

const CAPA_DUE_DEFAULT    = 30;   // days to complete the action
const CAPA_VERIFY_DEFAULT = 60;   // days after completion before we check it worked

function capa_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS capa (
        id $pk, ref VARCHAR(40) DEFAULT '',
        source VARCHAR(30) DEFAULT 'SELF_IDENTIFIED', source_ref VARCHAR(80) DEFAULT '',
        complaint_id INT NULL, audit_finding_id INT NULL, review_id INT NULL,
        follows_id INT NULL,
        title VARCHAR(255) DEFAULT '', description TEXT,
        clause VARCHAR(20) DEFAULT '', severity VARCHAR(20) DEFAULT 'MINOR',
        office_id INT NULL,
        raised_by VARCHAR(150) DEFAULT '', raised_on VARCHAR(20) DEFAULT '',
        immediate_action TEXT,
        root_cause TEXT, rc_method VARCHAR(20) DEFAULT '',
        similar_checked INT DEFAULT 0, similar_found VARCHAR(10) DEFAULT '', similar_note VARCHAR(1000) DEFAULT '',
        action_plan TEXT, owner VARCHAR(150) DEFAULT '', due_on VARCHAR(20) DEFAULT '',
        completed_on VARCHAR(20) DEFAULT '', completed_by VARCHAR(150) DEFAULT '',
        verify_due VARCHAR(20) DEFAULT '', verified_on VARCHAR(20) DEFAULT '',
        verified_by VARCHAR(150) DEFAULT '', effective VARCHAR(10) DEFAULT '',
        effectiveness_note TEXT,
        status VARCHAR(20) DEFAULT 'OPEN', closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // A corrective action is rarely one thing. "Revise the procedure, retrain
    // four inspectors, and add a check to the report form" is three tasks with
    // three owners and three dates, and holding them in one action_plan text
    // box meant none of them could be chased, owned or counted. The old single
    // field is KEPT and still shown: it is the summary, and every record
    // written before this change lives in it.
    $pdo->exec("CREATE TABLE IF NOT EXISTS capa_actions (
        id $pk, capa_id INT, seq INT DEFAULT 0,
        description TEXT, owner VARCHAR(150) DEFAULT '', due_on VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        done_on VARCHAR(20) DEFAULT '', done_by VARCHAR(150) DEFAULT '', done_note VARCHAR(1000) DEFAULT '',
        cancel_reason VARCHAR(500) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS capa_events (
        id $pk, capa_id INT, at VARCHAR(30) DEFAULT '',
        action VARCHAR(40) DEFAULT '', actor VARCHAR(150) DEFAULT '', note VARCHAR(1000) DEFAULT '')");
}

function capa_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- Settings ---------------------------------------------------------------
function capa_due_days()    { $d = (int)setting_get('capa_due_days', (string)CAPA_DUE_DEFAULT);       return $d > 0 ? $d : CAPA_DUE_DEFAULT; }
function capa_verify_days() { $d = (int)setting_get('capa_verify_days', (string)CAPA_VERIFY_DEFAULT); return $d > 0 ? $d : CAPA_VERIFY_DEFAULT; }

// ---- Reading ----------------------------------------------------------------
function capa_all($filter = []) {
    // office_id was stored from the first day and never read, so a Pune manager
    // saw every corrective action in the company. It is read now.
    [$sw, $sa] = scope_office_clause('office_id');
    $w = [$sw]; $a = $sa;
    if (!empty($filter['status'])) { $w[] = 'status = ?'; $a[] = $filter['status']; }
    if (!empty($filter['source'])) { $w[] = 'source = ?'; $a[] = $filter['source']; }
    if (!empty($filter['open']))   { $w[] = "status NOT IN ('CLOSED','CLOSED_FAILED')"; }
    if (!empty($filter['from']))   { $w[] = 'raised_on >= ?'; $a[] = $filter['from']; }
    if (!empty($filter['to']))     { $w[] = 'raised_on <= ?'; $a[] = $filter['to']; }
    try {
        return ops_all("SELECT * FROM capa WHERE " . implode(' AND ', $w)
                     . " ORDER BY (status NOT IN ('CLOSED','CLOSED_FAILED')) DESC, raised_on DESC, id DESC", $a);
    } catch (Throwable $e) { if (capa_missing_table($e)) return []; throw $e; }
}

function capa_row($id) {
    try { return ops_one("SELECT * FROM capa WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (capa_missing_table($e)) return null; throw $e; }
}
function capa_by_ref($ref) {
    try { return ops_one("SELECT * FROM capa WHERE ref=?", [(string)$ref]); }
    catch (Throwable $e) { if (capa_missing_table($e)) return null; throw $e; }
}
function capa_events($id) {
    try { return ops_all("SELECT * FROM capa_events WHERE capa_id=? ORDER BY id", [(int)$id]); }
    catch (Throwable $e) { if (capa_missing_table($e)) return []; throw $e; }
}
function capa_log($id, $action, $note = '') {
    try {
        db()->prepare("INSERT INTO capa_events (capa_id,at,action,actor,note) VALUES (?,?,?,?,?)")
            ->execute([(int)$id, date('c'), $action, user_name(current_user()), substr((string)$note, 0, 1000)]);
    } catch (Throwable $e) { /* the trail must not break the screen */ }
}

function capa_ref_next() {
    $n = 0;
    try { $n = (int)ops_val("SELECT COUNT(*) FROM capa"); } catch (Throwable $e) {}
    return 'CAPA-' . date('Y') . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

function capa_is_open($c) { return !in_array($c['status'] ?? '', ['CLOSED', 'CLOSED_FAILED'], true); }

// ---- Clocks -----------------------------------------------------------------
function capa_action_overdue($c, $today = null) {
    if (!capa_is_open($c)) return 0;
    if (($c['completed_on'] ?? '') !== '') return 0;
    if (($c['due_on'] ?? '') === '') return 0;
    $today = $today ?: date('Y-m-d');
    return $today > $c['due_on'] ? (int)floor((strtotime($today) - strtotime($c['due_on'])) / 86400) : 0;
}
// The one nobody chases, which is exactly why it is here.
function capa_verify_overdue($c, $today = null) {
    if (!capa_is_open($c)) return 0;
    if (($c['completed_on'] ?? '') === '') return 0;
    if (($c['verified_on'] ?? '') !== '') return 0;
    $due = ($c['verify_due'] ?? '') !== '' ? $c['verify_due']
         : date('Y-m-d', strtotime($c['completed_on'] . ' +' . capa_verify_days() . ' days'));
    $today = $today ?: date('Y-m-d');
    return $today > $due ? (int)floor((strtotime($today) - strtotime($due)) / 86400) : 0;
}

// ---- Closing ----------------------------------------------------------------
// The whole of §8.7 in one list, in the order somebody would work through it.
// ---- The actions --------------------------------------------------------
// A corrective action is rarely one thing. These are the tasks it breaks into,
// each with its own owner and date, each chaseable and countable.
function capa_actions($capaId) {
    try {
        return ops_all("SELECT * FROM capa_actions WHERE capa_id=? ORDER BY seq, id", [(int)$capaId]);
    } catch (Throwable $e) { if (capa_missing_table($e)) return []; throw $e; }
}

// Still to do. A cancelled action counts as settled — somebody decided against
// it and said why — but an open one blocks the close.
function capa_actions_open($capaId) {
    return array_values(array_filter(capa_actions($capaId), fn($a) => ($a['status'] ?? 'OPEN') === 'OPEN'));
}

function capa_action_add($capaId, array $b) {
    $desc = trim((string)($b['description'] ?? ''));
    if ($desc === '') return 'Say what has to be done.';
    $seq = 1 + (int)ops_val("SELECT COALESCE(MAX(seq),0) FROM capa_actions WHERE capa_id=?", [(int)$capaId]);
    $c = capa_row($capaId);
    // Inherit the corrective action's own date rather than leaving it blank —
    // an action with no date is an action nobody is late on.
    $due = trim((string)($b['due_on'] ?? '')) ?: (string)($c['due_on'] ?? '');
    db()->prepare("INSERT INTO capa_actions (capa_id,seq,description,owner,due_on,status,created_by,created_at)
                   VALUES (?,?,?,?,?,'OPEN',?,?)")
        ->execute([(int)$capaId, $seq, $desc,
                   substr(trim((string)($b['owner'] ?? '')), 0, 150), $due,
                   user_name(current_user()), date('c')]);
    capa_log($capaId, 'ACTION_ADDED', $desc . ' — ' . (trim((string)($b['owner'] ?? '')) ?: 'no owner'));
    return '';
}

function capa_action_done($actionId, $note = '', $on = null) {
    $a = ops_one("SELECT * FROM capa_actions WHERE id=?", [(int)$actionId]);
    if (!$a) return 'That action no longer exists.';
    db()->prepare("UPDATE capa_actions SET status='DONE', done_on=?, done_by=?, done_note=? WHERE id=?")
        ->execute([$on ?: date('Y-m-d'), user_name(current_user()),
                   substr(trim((string)$note), 0, 1000), (int)$actionId]);
    capa_log((int)$a['capa_id'], 'ACTION_DONE_ONE', $a['description']);
    return '';
}

// Dropping an action needs a reason, for the same reason accepting a
// nonconformity as it stands does: it is the convenient way out.
function capa_action_cancel($actionId, $why) {
    $why = trim((string)$why);
    if ($why === '') return 'Say why this action is being dropped.';
    $a = ops_one("SELECT * FROM capa_actions WHERE id=?", [(int)$actionId]);
    if (!$a) return 'That action no longer exists.';
    db()->prepare("UPDATE capa_actions SET status='CANCELLED', cancel_reason=?, done_on=?, done_by=? WHERE id=?")
        ->execute([substr($why, 0, 500), date('Y-m-d'), user_name(current_user()), (int)$actionId]);
    capa_log((int)$a['capa_id'], 'ACTION_CANCELLED', $a['description'] . ' — ' . $why);
    return '';
}

function capa_action_reopen($actionId) {
    $a = ops_one("SELECT * FROM capa_actions WHERE id=?", [(int)$actionId]);
    if (!$a) return;
    db()->prepare("UPDATE capa_actions SET status='OPEN', done_on='', done_by='', done_note='', cancel_reason='' WHERE id=?")
        ->execute([(int)$actionId]);
    capa_log((int)$a['capa_id'], 'ACTION_REOPENED', $a['description']);
}

// Every action past its date, across the whole register, for the nightly chase.
function capa_actions_overdue($today = null) {
    $today = $today ?: date('Y-m-d');
    try {
        return ops_all("SELECT a.*, c.ref, c.title FROM capa_actions a
                        JOIN capa c ON c.id = a.capa_id
                        WHERE a.status='OPEN' AND a.due_on <> '' AND a.due_on < ?
                          AND c.status NOT IN ('CLOSED','CLOSED_FAILED')", [$today]);
    } catch (Throwable $e) { if (capa_missing_table($e)) return []; throw $e; }
}

function capa_close_missing($c) {
    $miss = [];
    if (trim((string)($c['root_cause'] ?? '')) === '') $miss[] = 'record the root cause';
    if (trim((string)($c['rc_method'] ?? '')) === '')  $miss[] = 'say how the cause was worked out';
    // §8.7.2 d). One line in the standard, the most-missed line in practice.
    if (empty($c['similar_checked']))                  $miss[] = 'answer whether this happened, or could happen, elsewhere';
    $acts = capa_actions((int)($c['id'] ?? 0));
    if (!$acts && trim((string)($c['action_plan'] ?? '')) === '')
        $miss[] = 'record what will be done about it';
    // Closing the parent while a task under it is still open is how a
    // corrective action gets marked done on the strength of the easy third of
    // it. Cancelled counts as settled; somebody decided and said why.
    $open = array_values(array_filter($acts, fn($a) => ($a['status'] ?? 'OPEN') === 'OPEN'));
    if ($open) $miss[] = count($open) . ' action(s) still open: '
        . implode('; ', array_map(fn($a) => trim(mb_substr((string)$a['description'], 0, 60)), $open));
    if (($c['completed_on'] ?? '') === '')             $miss[] = 'record that the action was carried out';
    // §8.7.3. The reason this register exists at all.
    if (($c['verified_on'] ?? '') === '')              $miss[] = 'go back and check whether it worked';
    return $miss;
}

function capa_close_block($c) {
    $miss = capa_close_missing($c);
    if ($miss) {
        return 'This corrective action cannot be closed yet. Still to do: ' . implode('; ', $miss)
             . '. ISO/IEC 17020 §8.7.3 — the body reviews the effectiveness of the action it took, '
             . 'and an action nobody went back to check is not a corrective action, it is a note.';
    }
    // Verified as NOT effective. Closing it as done would be a false record.
    if (($c['effective'] ?? '') === 'NO')
        return 'This action was checked and found not to have worked. It cannot be closed as done — '
             . 'either raise a further corrective action from here, or plan and carry out something else.';
    return '';
}

// ---- Raising ----------------------------------------------------------------
// Returns the new id, or 0 when there is nothing worth recording.
function capa_create($b) {
    $title = trim((string)($b['title'] ?? ''));
    $desc  = trim((string)($b['description'] ?? ''));
    if ($title === '' || $desc === '') return 0;
    $src = isset(CAPA_SOURCES[$b['source'] ?? '']) ? $b['source'] : 'SELF_IDENTIFIED';
    $sev = isset(CAPA_SEVERITY[$b['severity'] ?? '']) ? $b['severity'] : 'MINOR';
    $raised = (string)($b['raised_on'] ?? date('Y-m-d'));
    $due = trim((string)($b['due_on'] ?? ''));
    if ($due === '') $due = date('Y-m-d', strtotime($raised . ' +' . capa_due_days() . ' days'));
    $ref = capa_ref_next();
    db()->prepare("INSERT INTO capa
        (ref,source,source_ref,complaint_id,audit_finding_id,review_id,follows_id,title,description,clause,
         severity,office_id,raised_by,raised_on,immediate_action,owner,due_on,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'OPEN',?)")
        ->execute([$ref, $src, substr(trim((string)($b['source_ref'] ?? '')), 0, 80),
                   ($b['complaint_id'] ?? '') !== '' ? (int)$b['complaint_id'] : null,
                   ($b['audit_finding_id'] ?? '') !== '' ? (int)$b['audit_finding_id'] : null,
                   ($b['review_id'] ?? '') !== '' ? (int)$b['review_id'] : null,
                   ($b['follows_id'] ?? '') !== '' ? (int)$b['follows_id'] : null,
                   substr($title, 0, 255), $desc,
                   substr(trim((string)($b['clause'] ?? '')), 0, 20), $sev,
                   ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null,
                   user_name(current_user()), $raised,
                   (string)($b['immediate_action'] ?? ''),
                   substr(trim((string)($b['owner'] ?? '')), 0, 150), $due, date('c')]);
    $id = (int)db()->lastInsertId();
    capa_log($id, 'RAISED', $ref . ' — ' . $title);
    return $id;
}

// Raise one straight off a complaint, and write the reference back so the
// complaint's own close gate is satisfied by a real record rather than by
// somebody typing a plausible-looking string into a box.
function capa_from_complaint($complaintId) {
    if (!function_exists('cmp_row')) return 0;
    $c = cmp_row($complaintId);
    if (!$c) return 0;
    if (trim((string)$c['capa_ref']) !== '' && capa_by_ref($c['capa_ref'])) return 0;   // already has one
    $id = capa_create([
        'source' => 'COMPLAINT', 'source_ref' => $c['ref'], 'complaint_id' => (int)$c['id'],
        'title' => 'From ' . $c['ref'] . ': ' . $c['subject'],
        'description' => "The complaint was upheld.\n\nWhat they told us:\n" . $c['description']
                       . "\n\nOur decision:\n" . $c['decision_note'],
        'root_cause_seed' => $c['root_cause'], 'clause' => '7.5',
        'severity' => $c['outcome'] === 'UPHELD' ? 'MINOR' : 'OBSERVATION',
        'office_id' => $c['office_id'],
    ]);
    if (!$id) return 0;
    // Carry over the cause the complaint investigation already found, if any —
    // retyping it is how the two records end up disagreeing.
    if (trim((string)$c['root_cause']) !== '')
        db()->prepare("UPDATE capa SET root_cause=? WHERE id=?")->execute([$c['root_cause'], $id]);
    $ref = capa_row($id)['ref'];
    db()->prepare("UPDATE complaints SET capa_ref=? WHERE id=?")->execute([$ref, (int)$c['id']]);
    if (function_exists('cmp_log')) cmp_log((int)$c['id'], 'CAPA', 'Raised ' . $ref . '.');
    return $id;
}

// ---- How this body is doing on §8.7 ----------------------------------------
function capa_readiness($from = null, $to = null) {
    $rows = capa_all(array_filter(['from' => $from, 'to' => $to]));
    $open = 0; $actionLate = 0; $verifyLate = 0; $failed = 0; $noSimilar = 0; $closed = 0;
    foreach ($rows as $c) {
        if (capa_is_open($c)) $open++; else $closed++;
        if (capa_action_overdue($c)) $actionLate++;
        if (capa_verify_overdue($c)) $verifyLate++;
        if ($c['status'] === 'CLOSED_FAILED') $failed++;
        if (capa_is_open($c) && empty($c['similar_checked'])) $noSimilar++;
    }
    return [
        'total' => count($rows), 'open' => $open, 'closed' => $closed,
        'action_late' => $actionLate, 'verify_late' => $verifyLate,
        'failed' => $failed, 'no_similar' => $noSimilar,
        'due_days' => capa_due_days(), 'verify_days' => capa_verify_days(),
    ];
}

// ---- Nightly ----------------------------------------------------------------
function capa_run_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $late = [];
    foreach (capa_all(['open' => 1]) as $c) {
        $a = capa_action_overdue($c, $today); $v = capa_verify_overdue($c, $today);
        if ($a) $late[] = $c['ref'] . ' — action ' . $a . ' day(s) past ' . fdate($c['due_on'])
                        . ($c['owner'] ? ' (' . $c['owner'] . ')' : '');
        if ($v) $late[] = $c['ref'] . ' — done on ' . fdate($c['completed_on'])
                        . ', nobody has checked whether it worked (' . $v . ' day(s) late)';
    }
    if (!$late) return 0;
    $body = "Corrective actions past their date:\n\n  " . implode("\n  ", $late)
          . "\n\nISO/IEC 17020 §8.7.3 — an action nobody went back to check is not a corrective action.\n\n"
          . app_name();
    ops_mail('', 'Corrective actions overdue (' . count($late) . ')', $body, manager_emails(), 'capa');
    return count($late);
}

function capa_can_view()  { return can('mod.capa.view'); }
function capa_can_edit()  { return can('mod.capa.edit'); }
function capa_can_close() { return can('capa.close'); }

// ---- Screens ----------------------------------------------------------------
function ops_capa($route, $method) {
    ops_require(capa_can_view(), 'You don’t have access to the corrective-action register. Ask your administrator.');

    if ($route === 'capa') {
        view('ops/capa_list', [
            'ready' => capa_readiness(), 'rows' => capa_all(),
            'canEdit' => capa_can_edit(), 'canClose' => capa_can_close(),
        ]);
        return true;
    }

    if ($route === 'capa-item') {
        $c = capa_row((int)($_GET['id'] ?? 0));
        if (!$c) { http_response_code(404); view('notfound'); return true; }
        view('ops/capa_detail', [
            'c' => $c, 'events' => capa_events($c['id']),
            'actions' => capa_actions((int)$c['id']),
            'missing' => capa_close_missing($c), 'block' => capa_close_block($c),
            'follows' => !empty($c['follows_id']) ? capa_row((int)$c['follows_id']) : null,
            'followed' => capa_followups((int)$c['id']),
            'complaint' => (!empty($c['complaint_id']) && function_exists('cmp_row')) ? cmp_row((int)$c['complaint_id']) : null,
            'canEdit' => capa_can_edit(), 'canClose' => capa_can_close(),
        ]);
        return true;
    }

    ops_require(capa_can_edit(), 'Only somebody with the corrective-action permission can raise or update one.');

    if ($route === 'capa-new') {
        if ($method === 'POST') {
            $id = capa_create($_POST);
            if (!$id) { flash('A corrective action needs a title and a description of what went wrong.', 'error'); redirect('/capa-new'); }
            redirect('/capa-item?id=' . $id);
        }
        view('ops/capa_form', ['from' => (int)($_GET['follows'] ?? 0) ? capa_row((int)$_GET['follows']) : null]);
        return true;
    }

    // Raised straight from a complaint, so the reference on the complaint is a
    // real record rather than a string somebody typed.
    if ($route === 'capa-from-complaint' && $method === 'POST') {
        $id = capa_from_complaint((int)($_POST['complaint_id'] ?? 0));
        if (!$id) { flash('That complaint already has a corrective action, or could not be read.', 'error'); redirect('/complaints'); }
        flash('Raised ' . capa_row($id)['ref'] . ' and linked it to the complaint.');
        redirect('/capa-item?id=' . $id);
    }

    $c = capa_row((int)($_POST['id'] ?? $_GET['id'] ?? 0));
    if (!$c) redirect('/capa');

    if ($route === 'capa-cause' && $method === 'POST') {
        $rc = trim((string)($_POST['root_cause'] ?? ''));
        $m  = (string)($_POST['rc_method'] ?? '');
        if (!isset(CAPA_RC_METHODS[$m])) $m = '';
        // "The engineer was careless" is a symptom with a name attached. Saying
        // so here is cheaper than an assessor saying it later.
        if ($rc !== '' && $m === '') {
            flash('Say how the cause was worked out. An assessor asks that next, every time.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        $sim = (string)($_POST['similar_found'] ?? '');
        $simNote = trim((string)($_POST['similar_note'] ?? ''));
        $simChecked = in_array($sim, ['YES', 'NO'], true) ? 1 : 0;
        if ($simChecked && $sim === 'YES' && $simNote === '') {
            flash('If it happened elsewhere too, say where. That is the part of §8.7.2 that has teeth.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        db()->prepare("UPDATE capa SET root_cause=?, rc_method=?, similar_checked=?, similar_found=?, similar_note=?,
                       immediate_action=? WHERE id=?")
            ->execute([$rc, $m, $simChecked, $simChecked ? $sim : '', substr($simNote, 0, 1000),
                       (string)($_POST['immediate_action'] ?? $c['immediate_action']), $c['id']]);
        capa_log($c['id'], 'CAUSE', $m !== '' ? CAPA_RC_METHODS[$m] : '');
        flash('Saved.');
        redirect('/capa-item?id=' . $c['id']);
    }

    if ($route === 'capa-action-add' && $method === 'POST') {
        if (($err = capa_action_add((int)$c['id'], $_POST)) !== '') flash($err, 'error');
        else flash('Action added.');
        redirect('/capa-item?id=' . $c['id']);
    }
    if ($route === 'capa-action-done' && $method === 'POST') {
        if (($err = capa_action_done((int)($_POST['action_id'] ?? 0), (string)($_POST['note'] ?? ''))) !== '')
            flash($err, 'error');
        else flash('Action marked done.');
        redirect('/capa-item?id=' . $c['id']);
    }
    if ($route === 'capa-action-cancel' && $method === 'POST') {
        if (($err = capa_action_cancel((int)($_POST['action_id'] ?? 0), (string)($_POST['why'] ?? ''))) !== '')
            flash($err, 'error');
        else flash('Action dropped, with the reason recorded.');
        redirect('/capa-item?id=' . $c['id']);
    }
    if ($route === 'capa-action-reopen' && $method === 'POST') {
        capa_action_reopen((int)($_POST['action_id'] ?? 0));
        flash('Action reopened.');
        redirect('/capa-item?id=' . $c['id']);
    }

    if ($route === 'capa-plan' && $method === 'POST') {
        $due = trim((string)($_POST['due_on'] ?? ''));
        db()->prepare("UPDATE capa SET action_plan=?, owner=?, due_on=?, status=? WHERE id=?")
            ->execute([(string)($_POST['action_plan'] ?? ''),
                       substr(trim((string)($_POST['owner'] ?? '')), 0, 150),
                       $due !== '' ? $due : $c['due_on'],
                       capa_is_open($c) ? 'IN_PROGRESS' : $c['status'], $c['id']]);
        capa_log($c['id'], 'PLAN', 'Owner: ' . (string)($_POST['owner'] ?? '—'));
        flash('Plan saved.');
        redirect('/capa-item?id=' . $c['id']);
    }

    if ($route === 'capa-done' && $method === 'POST') {
        if (trim((string)$c['action_plan']) === '' && !capa_actions((int)$c['id'])) {
            flash('Record what was going to be done before recording that it was done.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        if ($open = capa_actions_open((int)$c['id'])) {
            flash('There ' . (count($open) === 1 ? 'is 1 action' : 'are ' . count($open) . ' actions')
                . ' still open under this corrective action. Finish or drop '
                . (count($open) === 1 ? 'it' : 'them') . ' first.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        $on = (string)($_POST['completed_on'] ?? date('Y-m-d'));
        db()->prepare("UPDATE capa SET completed_on=?, completed_by=?, verify_due=?, status='VERIFYING' WHERE id=?")
            ->execute([$on, user_name(current_user()),
                       date('Y-m-d', strtotime($on . ' +' . capa_verify_days() . ' days')), $c['id']]);
        capa_log($c['id'], 'ACTION_DONE', '');
        flash('Recorded. It is now waiting to be checked — in ' . capa_verify_days()
            . ' days somebody has to say whether it actually worked.');
        redirect('/capa-item?id=' . $c['id']);
    }

    // §8.7.3, and the reason the whole register exists.
    if ($route === 'capa-verify' && $method === 'POST') {
        if (($c['completed_on'] ?? '') === '') {
            flash('There is nothing to check yet — the action has not been recorded as carried out.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        $eff = (string)($_POST['effective'] ?? '');
        if (!in_array($eff, ['YES', 'NO'], true)) redirect('/capa-item?id=' . $c['id']);
        $note = trim((string)($_POST['effectiveness_note'] ?? ''));
        if ($note === '') {
            flash('Say what you looked at. "Verified effective" with nothing behind it is the same as not checking.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        db()->prepare("UPDATE capa SET verified_on=?, verified_by=?, effective=?, effectiveness_note=? WHERE id=?")
            ->execute([(string)($_POST['verified_on'] ?? date('Y-m-d')), user_name(current_user()),
                       $eff, $note, $c['id']]);
        capa_log($c['id'], 'VERIFIED', $eff === 'YES' ? 'It worked.' : 'It did not work.');
        flash($eff === 'YES' ? 'Recorded as effective. It can be closed now.'
            : 'Recorded as NOT effective. This one cannot be closed as done — raise a further action from here.',
            $eff === 'YES' ? 'success' : 'warning');
        redirect('/capa-item?id=' . $c['id']);
    }

    if ($route === 'capa-close' && $method === 'POST') {
        ops_require(capa_can_close(), 'Closing a corrective action needs the “Close corrective actions” permission.');
        $why = capa_close_block($c);
        if ($why !== '') { flash($why, 'error'); redirect('/capa-item?id=' . $c['id']); }
        db()->prepare("UPDATE capa SET status='CLOSED', closed_on=?, closed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $c['id']]);
        capa_log($c['id'], 'CLOSED', '');
        flash('Closed, and verified effective.');
        redirect('/capa-item?id=' . $c['id']);
    }

    // The honest ending for one that did not work: it is closed, marked as
    // having failed, and a successor is raised carrying the history forward.
    if ($route === 'capa-escalate' && $method === 'POST') {
        if (($c['effective'] ?? '') !== 'NO') {
            flash('This is for an action that was checked and found not to have worked.', 'error');
            redirect('/capa-item?id=' . $c['id']);
        }
        $new = capa_create([
            'source' => $c['source'], 'source_ref' => $c['source_ref'], 'follows_id' => (int)$c['id'],
            'complaint_id' => $c['complaint_id'], 'audit_finding_id' => $c['audit_finding_id'],
            'title' => 'After ' . $c['ref'] . ' did not work: ' . $c['title'],
            'description' => "The first corrective action was carried out and checked, and the problem did not stop.\n\n"
                           . "What was tried:\n" . $c['action_plan']
                           . "\n\nWhat the check found:\n" . $c['effectiveness_note'],
            'clause' => $c['clause'], 'severity' => 'MAJOR', 'office_id' => $c['office_id'],
        ]);
        db()->prepare("UPDATE capa SET status='CLOSED_FAILED', closed_on=?, closed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $c['id']]);
        capa_log($c['id'], 'CLOSED_FAILED', 'Superseded by ' . capa_row($new)['ref'] . '.');
        flash('Recorded honestly: this one did not work, and ' . capa_row($new)['ref'] . ' carries it forward. '
            . 'A body that shows this is a body that is actually improving.');
        redirect('/capa-item?id=' . $new);
    }

    if ($route === 'capa-settings' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can change these.');
        $d = (int)($_POST['capa_due_days'] ?? 0); $v = (int)($_POST['capa_verify_days'] ?? 0);
        if ($d > 0) setting_set('capa_due_days', (string)$d);
        if ($v > 0) setting_set('capa_verify_days', (string)$v);
        flash('Saved.');
        redirect('/capa');
    }

    redirect('/capa');
    return true;
}

function capa_followups($id) {
    try { return ops_all("SELECT * FROM capa WHERE follows_id=? ORDER BY id", [(int)$id]); }
    catch (Throwable $e) { if (capa_missing_table($e)) return []; throw $e; }
}
