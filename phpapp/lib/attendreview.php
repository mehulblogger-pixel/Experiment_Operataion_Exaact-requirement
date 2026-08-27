<?php
// Phase 3 §35 — attendance review.
//
// An inspector self-marks their own attendance (office or site) — that capture already exists
// (lib/attend.php: OFFICE/SITE/… with a geofenced check-in). What was missing is the oversight your
// rules ask for: if a mark looks wrong, a coordinator (first-line) and, on escalation, the reporting
// manager review it and send it back to the inspector to re-mark. Per the scoping decisions this is:
//   • only ANOMALOUS entries surface (clean self-marks are left alone);
//   • the reviewer SENDS BACK to the inspector (they own their record), or clears / escalates;
//   • ADVISORY — the attendance still counts immediately; this catches and corrects errors, it does
//     not gate the timesheet.
// Additive columns on the existing `attendance` table; nothing is removed, no capture path changes.

function attendreview_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('attendance', 'review_status', "VARCHAR(20) DEFAULT ''");   // '' / RETURNED / CLEARED / ESCALATED
    ensure_column('attendance', 'review_note',   "VARCHAR(500) DEFAULT ''");
    ensure_column('attendance', 'reviewed_by',   'INT NULL');
    ensure_column('attendance', 'reviewed_at',   "VARCHAR(30) DEFAULT ''");
}

// The one reason a self-marked entry looks wrong, or '' if it is clean. Ordered by seriousness.
function attend_anomaly($row) {
    $row = (array)$row;
    $today = date('Y-m-d');
    $date  = substr((string)($row['att_date'] ?? ''), 0, 10);

    // 1. Marked SITE but the GPS is outside the job's geofence — i.e. "on site" from somewhere else.
    if (strtoupper((string)($row['status'] ?? '')) === 'SITE'
        && ($row['in_lat'] ?? '') !== '' && ($row['in_lat'] ?? null) !== null
        && !empty($row['job_id']) && function_exists('geo_distance_m') && function_exists('geofence_target')) {
        try {
            $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$row['job_id']]);
            $t = $job ? geofence_target($job) : null;
            if ($t && ($t['lat'] ?? null) !== null) {
                $d = geo_distance_m($row['in_lat'], $row['in_lon'], $t['lat'], $t['lon']);
                $r = (float)($t['radius'] ?? 250);
                if ($d > $r) return 'Marked on site, but the GPS is ' . round($d / 1000, 1) . ' km from ' . ($t['label'] ?? 'the site') . '.';
            }
        } catch (Throwable $e) {}
    }
    // 2. Checked in on a past day but never checked out.
    if (($row['check_in_at'] ?? '') !== '' && ($row['check_out_at'] ?? '') === '' && $date !== '' && $date < $today)
        return 'Checked in but never checked out.';
    // 3. Marked well after the fact (back-dated more than 2 days).
    $marked = substr((string)($row['marked_at'] ?? $row['created_at'] ?? ''), 0, 10);
    if ($date !== '' && $marked !== '' && $marked > $date) {
        $lag = (int) round((strtotime($marked) - strtotime($date)) / 86400);
        if ($lag > 2) return 'Marked ' . $lag . ' days after the date.';
    }
    return '';
}

// The scope clause over the inspector's home office, so a branch coordinator sees only their branch.
function _attendreview_scope() {
    $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($scope === 'ALL' || !is_array($scope) || !$scope) return '1';
    return 'i.home_office_id IN (' . implode(',', array_map('intval', $scope)) . ')';
}

// The review queue: recent anomalous entries not already cleared or sent back. $window days back.
function attend_review_scan($window = 45, $limit = 200) {
    attendreview_migrate();
    $from = date('Y-m-d', time() - max(1, (int)$window) * 86400);
    $w = _attendreview_scope();
    $rows = [];
    try {
        $rows = ops_all("SELECT a.*, i.name AS inspector_name
                         FROM attendance a LEFT JOIN inspectors i ON i.id=a.inspector_id
                         WHERE a.att_date >= ? AND COALESCE(a.review_status,'') NOT IN ('CLEARED','RETURNED')
                           AND ($w)
                         ORDER BY a.att_date DESC, a.id DESC LIMIT " . max(1, (int)$limit), [$from]) ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $reason = attend_anomaly($r);
        if ($reason === '') continue;
        $r['flag_reason'] = $reason;
        $out[] = $r;
    }
    return $out;
}
function attend_review_count() { return count(attend_review_scan()); }

// Who may review. Coordinator level is the first line; managers (admin level) also review and own the
// escalations. No new permission — gated on the existing role helpers.
function attend_review_can() {
    return (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('is_admin_level') && is_admin_level())
        || (function_exists('is_master') && is_master());
}

function _attend_review_set($id, $status, $note, $action) {
    attendreview_migrate();
    if (!attend_review_can()) return false;
    $row = ops_one("SELECT * FROM attendance WHERE id=?", [(int)$id]); if (!$row) return false;
    db()->prepare("UPDATE attendance SET review_status=?, review_note=?, reviewed_by=?, reviewed_at=? WHERE id=?")
        ->execute([$status, substr((string)$note, 0, 500), (int)(current_user()['id'] ?? 0) ?: null, date('c'), (int)$id]);
    if (function_exists('idems_log')) { try { idems_log('attendance', (int)$id, $action, ['note' => $note]); } catch (Throwable $e) {} }
    return true;
}
// Send it back to the inspector to re-mark (they own their record).
function attend_review_return($id, $note = '')   { return _attend_review_set($id, 'RETURNED',  $note ?: 'Please re-check and re-mark.', 'ATT_RETURNED'); }
// Accept it despite the flag (a coordinator judged it fine).
function attend_review_clear($id, $note = '')    { return _attend_review_set($id, 'CLEARED',   $note, 'ATT_CLEARED'); }
// Escalate to the reporting manager.
function attend_review_escalate($id, $note = '') { return _attend_review_set($id, 'ESCALATED', $note, 'ATT_ESCALATED'); }

// When the inspector re-marks an entry that was sent back, the review flag resets so it is re-checked.
// Called from the attendance capture path.
function attend_review_reset($id) {
    attendreview_migrate();
    $row = ops_one("SELECT review_status FROM attendance WHERE id=?", [(int)$id]);
    if ($row && in_array((string)($row['review_status'] ?? ''), ['RETURNED', 'ESCALATED', 'CLEARED'], true))
        db()->prepare("UPDATE attendance SET review_status='', review_note='' WHERE id=?")->execute([(int)$id]);
}

// The entries a given inspector has had sent back to them (for their own attendance screen).
function attend_review_returned_for($inspectorId) {
    attendreview_migrate();
    try {
        return ops_all("SELECT * FROM attendance WHERE inspector_id=? AND review_status='RETURNED' ORDER BY att_date DESC", [(int)$inspectorId]) ?: [];
    } catch (Throwable $e) { return []; }
}

function ops_attendance_review($method) {
    ops_require(attend_review_can(), 'You cannot review attendance.');
    if ($method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $note = trim((string)($_POST['note'] ?? '')); $do = $_POST['do'] ?? '';
        if ($do === 'return')        { attend_review_return($id, $note);   flash('Sent back to the inspector to re-mark.'); }
        elseif ($do === 'clear')     { attend_review_clear($id, $note);    flash('Cleared — the mark stands.'); }
        elseif ($do === 'escalate')  { attend_review_escalate($id, $note); flash('Escalated to the reporting manager.'); }
        redirect('/attendance-review');
    }
    view('ops/attendance_review', ['rows' => attend_review_scan(), 'csrf' => function_exists('csrf_token') ? csrf_token() : '']);
    return true;
}
