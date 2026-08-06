<?php
// ============================================================================
//  Customer satisfaction — ISO 9001 §9.1.2, roadmap 5.4, open item M6
//
//  After work closes, ask the customer how it went and record the answer where
//  the account is managed. This is both an ISO 9001 expectation (monitor the
//  customer's perception) and an ordinary account tool: a low score that nobody
//  sees is a customer about to leave quietly.
//
//  A survey is a small lifecycle: REQUESTED when we ask, RECEIVED when they
//  answer (with an overall score, per-aspect ratings, a would-recommend flag and
//  free comments), or DECLINED when they will not. A response at or below a
//  configurable threshold raises a follow-up flag — surfaced, never auto-graded
//  into a complaint, because whether a 2/5 is a grievance or a grumble is a
//  person's call, the same principle the rest of this system follows.
//
//  Configuration-first: the aspects are an editable Master; the scale and the
//  follow-up threshold are Settings; extra data via custom fields (entity
//  'satisfaction'); the whole feature is toggle-able per customer and its labels
//  route through the terminology engine. Sensible defaults, full override.
// ============================================================================

const SAT_STATUSES = ['REQUESTED', 'RECEIVED', 'DECLINED'];
const SAT_OPEN     = ['REQUESTED'];

function sat_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS satisfaction_surveys (
            id $pk, survey_code VARCHAR(30) DEFAULT '', client_id INT NULL, about VARCHAR(200) DEFAULT '',
            job_id INT NULL, report_ref VARCHAR(60) DEFAULT '', office_id INT NULL, sbu VARCHAR(40) DEFAULT '',
            status VARCHAR(20) DEFAULT 'REQUESTED',
            requested_on VARCHAR(20) DEFAULT '', requested_by VARCHAR(150) DEFAULT '',
            respondent VARCHAR(150) DEFAULT '', respondent_role VARCHAR(90) DEFAULT '',
            received_on VARCHAR(20) DEFAULT '', score INT NULL, recommend VARCHAR(2) DEFAULT '',
            aspects TEXT, comments TEXT,
            followup_needed INT DEFAULT 0, followup_note VARCHAR(300) DEFAULT '', followup_done INT DEFAULT 0,
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('satisfaction_aspect', 'Satisfaction aspect', [
                'TIMELINESS'    => 'Timeliness',
                'TECHNICAL'     => 'Technical quality',
                'COMMUNICATION' => 'Communication',
                'PROFESSIONAL'  => 'Professionalism / conduct',
                'VALUE'         => 'Value for money',
                'RESPONSIVE'    => 'Responsiveness to issues',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience */ }
    }
}

// ---- Configuration --------------------------------------------------------
// The whole feature can be switched off per customer; on by default so it works
// on day one.
function sat_enabled() {
    return (string)setting_get('satisfaction_on', '1') !== '0';
}
// The rating scale, 1..N. Default 5, clamped to a sane range.
function sat_scale() {
    $n = (int)setting_get('satisfaction_scale', 5);
    return max(3, min(10, $n ?: 5));
}
// A response at or below this raises the follow-up flag. Empty default derives
// from the scale (roughly the lower half), so it stays sensible if the scale is
// changed and nobody set an explicit threshold.
function sat_followup_threshold() {
    $raw = trim((string)setting_get('satisfaction_followup_below', ''));
    $scale = sat_scale();
    $t = $raw === '' ? (int)ceil($scale * 0.5) : (int)$raw;
    return max(1, min($scale, $t));
}

function sat_can_view() {
    if (!sat_enabled()) return false;
    return is_master() || (function_exists('can') && (can('mod.clients.view')
        || can('mod.complaints.view') || can('mod.jobs.view')));
}
function sat_can_manage() {
    if (!sat_enabled()) return false;
    return is_master() || (function_exists('can') && (can('mod.clients.view') || can('mod.complaints.view')));
}

function sat_aspects() {
    sat_migrate();
    $t = function_exists('lk_type') ? lk_type('satisfaction_aspect') : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function sat_aspect_label($code) { $o = sat_aspects(); return $o[$code] ?? ($code ?: '—'); }

function sat_client_name($id) {
    if (!$id) return '';
    $r = ops_one("SELECT display_name, legal_name FROM business_partners WHERE id=?", [(int)$id]);
    return $r ? (trim((string)($r['display_name'] ?? '')) ?: (string)($r['legal_name'] ?? '')) : '';
}
function sat_clients() {
    return ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1
        ORDER BY COALESCE(display_name, legal_name) LIMIT 500") ?: [];
}

function sat_all($filter = []) {
    sat_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['open']))      $where .= " AND status IN ('" . implode("','", SAT_OPEN) . "')";
    if (!empty($filter['status']) && in_array($filter['status'], SAT_STATUSES, true)) { $where .= " AND status=?"; $args[] = $filter['status']; }
    if (!empty($filter['followup']))  $where .= " AND followup_needed=1 AND followup_done=0";
    if (!empty($filter['client_id'])) { $where .= " AND client_id=?"; $args[] = (int)$filter['client_id']; }
    if (!empty($filter['q'])) {
        $where .= " AND (survey_code LIKE ? OR about LIKE ? OR respondent LIKE ? OR comments LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like, $like, $like);
    }
    $rows = ops_all("SELECT * FROM satisfaction_surveys WHERE $where ORDER BY id DESC", $args) ?: [];
    foreach ($rows as &$r) $r['client_name'] = sat_client_name($r['client_id']);
    return $rows;
}
function sat_get($id) {
    sat_migrate();
    $r = ops_one("SELECT * FROM satisfaction_surveys WHERE id=?", [(int)$id]);
    if ($r) $r['client_name'] = sat_client_name($r['client_id']);
    return $r;
}
function sat_next_code() {
    $n = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys") + 1;
    do { $code = sprintf('CSAT-%04d', $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE survey_code=?", [$code]) > 0);
    return $code;
}

// Aggregate metrics for the dashboard tiles. Averages and rates are computed
// only over surveys that were actually answered, so an unanswered request never
// drags the average down.
function sat_summary($filter = []) {
    sat_migrate();
    $total     = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys");
    $received  = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE status='RECEIVED'");
    $requested = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE status='REQUESTED'");
    $declined  = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE status='DECLINED'");
    $asked     = $received + $declined;                       // everyone who gave us an answer of any kind
    $avg       = $received ? (float)ops_val("SELECT AVG(score) FROM satisfaction_surveys WHERE status='RECEIVED' AND score IS NOT NULL") : null;
    $rec       = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE status='RECEIVED' AND recommend='Y'");
    $notRec    = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE status='RECEIVED' AND recommend='N'");
    $followup  = (int)ops_val("SELECT COUNT(*) FROM satisfaction_surveys WHERE followup_needed=1 AND followup_done=0");
    return [
        'total' => $total, 'received' => $received, 'requested' => $requested, 'declined' => $declined,
        'response_rate' => $asked ? round($received * 100 / $asked) : null,
        'avg' => $avg === null ? null : round($avg, 1), 'scale' => sat_scale(),
        'recommend' => $rec, 'not_recommend' => $notRec,
        'recommend_rate' => ($rec + $notRec) ? round($rec * 100 / ($rec + $notRec)) : null,
        'followup' => $followup,
    ];
}

// Raise a request (the "we have asked" state). Client is required so the survey
// is always attached to an account.
function sat_request($b) {
    sat_migrate();
    if (!sat_enabled()) return [0, 'Customer satisfaction capture is switched off.'];
    $clientId = (int)($b['client_id'] ?? 0);
    if ($clientId <= 0) return [0, 'Choose the ' . Tl('client') . ' this feedback is about.'];
    $cols = [
        'survey_code'  => sat_next_code(),
        'client_id'    => $clientId,
        'about'        => substr(trim((string)($b['about'] ?? '')), 0, 200),
        'job_id'       => ($b['job_id'] ?? '') !== '' ? (int)$b['job_id'] : null,
        'report_ref'   => substr(trim((string)($b['report_ref'] ?? '')), 0, 60),
        'status'       => 'REQUESTED',
        'requested_on' => date('Y-m-d'),
        'requested_by' => user_name(current_user()),
        'created_by'   => user_name(current_user()),
        'created_at'   => date('c'),
        'updated_at'   => date('c'),
    ];
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO satisfaction_surveys (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $id = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('satisfaction', $id, $b);
    if (function_exists('idems_log')) try { idems_log('satisfaction', $id, 'REQUESTED', ['reason' => $cols['about']]); } catch (Throwable $e) {}
    return [$id, ''];
}

// Record a response (moves REQUESTED -> RECEIVED). Also usable to edit an
// existing response.
function sat_record($id, $b) {
    $s = sat_get($id);
    if (!$s) return 'That survey no longer exists.';
    $scale = sat_scale();
    $score = ($b['score'] ?? '') !== '' ? max(1, min($scale, (int)$b['score'])) : null;

    // Per-aspect ratings, keyed by the aspect code, each clamped to the scale.
    $aspects = [];
    foreach (sat_aspects() as $code => $label) {
        $v = $b['aspect'][$code] ?? ($b['aspect_' . $code] ?? '');
        if ($v !== '' && $v !== null) $aspects[$code] = max(1, min($scale, (int)$v));
    }

    $recommend = in_array($b['recommend'] ?? '', ['Y', 'N'], true) ? $b['recommend'] : '';
    $needFollow = $score !== null && $score <= sat_followup_threshold() ? 1 : 0;

    db()->prepare("UPDATE satisfaction_surveys SET status='RECEIVED', received_on=?, score=?, recommend=?,
        aspects=?, comments=?, respondent=?, respondent_role=?, followup_needed=?, updated_at=? WHERE id=?")
        ->execute([
            date('Y-m-d'), $score, $recommend, json_encode($aspects, JSON_UNESCAPED_UNICODE),
            trim((string)($b['comments'] ?? '')),
            substr(trim((string)($b['respondent'] ?? '')), 0, 150),
            substr(trim((string)($b['respondent_role'] ?? '')), 0, 90),
            $needFollow, date('c'), (int)$id,
        ]);
    if (function_exists('custom_save')) custom_save('satisfaction', (int)$id, $b);
    if (function_exists('idems_log')) try {
        idems_log('satisfaction', (int)$id, 'RECEIVED', ['reason' => 'score ' . ($score ?? '—') . '/' . $scale]);
    } catch (Throwable $e) {}
    return '';
}

function sat_decline($id, $note = '') {
    $s = sat_get($id);
    if (!$s) return 'That survey no longer exists.';
    db()->prepare("UPDATE satisfaction_surveys SET status='DECLINED', received_on=?, comments=?, updated_at=? WHERE id=?")
        ->execute([date('Y-m-d'), substr(trim((string)$note), 0, 300), date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('satisfaction', (int)$id, 'DECLINED', ['reason' => $note]); } catch (Throwable $e) {}
    return '';
}

// Close out a follow-up on a low score.
function sat_followup_close($id, $note) {
    $s = sat_get($id);
    if (!$s) return 'That survey no longer exists.';
    if ((int)$s['followup_needed'] !== 1) return 'That survey has no follow-up to close.';
    db()->prepare("UPDATE satisfaction_surveys SET followup_done=1, followup_note=?, updated_at=? WHERE id=?")
        ->execute([substr(trim((string)$note), 0, 300), date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('satisfaction', (int)$id, 'FOLLOWUP_CLOSED', ['reason' => $note]); } catch (Throwable $e) {}
    return '';
}

function sat_aspects_of($s) {
    $j = json_decode((string)($s['aspects'] ?? ''), true);
    return is_array($j) ? $j : [];
}

// ---- Routing -------------------------------------------------------------
function ops_satisfaction($route, $method) {
    sat_migrate();
    ops_require(sat_can_view(), 'Customer satisfaction is not available to you.');
    $canEdit = sat_can_manage();

    if ($route === 'satisfaction') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        $show = $_GET['show'] ?? 'all';
        if ($show === 'open')     $filter['open'] = 1;
        if ($show === 'followup') $filter['followup'] = 1;
        if (in_array($_GET['status'] ?? '', SAT_STATUSES, true)) $filter['status'] = $_GET['status'];
        view('ops/satisfaction_list', [
            'rows' => sat_all($filter), 'canEdit' => $canEdit, 'show' => $show,
            'status' => $_GET['status'] ?? '', 'q' => $filter['q'],
            'sum' => sat_summary(), 'scale' => sat_scale(),
        ]);
        return true;
    }
    if ($route === 'satisfaction-survey') {
        $s = sat_get($_GET['id'] ?? 0);
        if (!$s) { http_response_code(404); view('notfound'); return true; }
        view('ops/satisfaction_detail', [
            's' => $s, 'canEdit' => $canEdit, 'scale' => sat_scale(),
            'aspects' => sat_aspects(), 'given' => sat_aspects_of($s),
            'cvals' => function_exists('custom_display') ? custom_display('satisfaction', $s['id']) : [],
        ]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the satisfaction register.');

    if ($route === 'satisfaction-new') {
        if ($method === 'POST') {
            [$sid, $err] = sat_request($_POST);
            if ($err) { flash($err, 'error'); redirect('/satisfaction-new'); }
            flash('Feedback request recorded. Record the response when it comes in.');
            redirect('/satisfaction-survey?id=' . $sid);
        }
        view('ops/satisfaction_form', [
            's' => null, 'clients' => sat_clients(),
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('satisfaction') : [],
            'cvals' => [],
        ]);
        return true;
    }
    if ($route === 'satisfaction-record' && $method === 'POST') {
        $err = sat_record((int)($_POST['id'] ?? 0), $_POST);
        flash($err ?: 'Response recorded.', $err ? 'error' : 'success');
        redirect('/satisfaction-survey?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'satisfaction-decline' && $method === 'POST') {
        $err = sat_decline((int)($_POST['id'] ?? 0), (string)($_POST['note'] ?? ''));
        flash($err ?: 'Recorded as declined.', $err ? 'error' : 'success');
        redirect('/satisfaction-survey?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'satisfaction-followup' && $method === 'POST') {
        $err = sat_followup_close((int)($_POST['id'] ?? 0), (string)($_POST['note'] ?? ''));
        flash($err ?: 'Follow-up closed.', $err ? 'error' : 'success');
        redirect('/satisfaction-survey?id=' . (int)($_POST['id'] ?? 0));
    }
    http_response_code(404); view('notfound'); return true;
}
