<?php
// ===========================================================================
//  Closing a job on time, or losing the right to change it
//
//  A job left open is not a small thing. Until it is closed the man-days are
//  provisional, the expenses are unclaimed, the invoice cannot be raised and
//  every figure on the management dashboard is missing part of its month. The
//  longer it stays open the more likely somebody is to "tidy up" a date weeks
//  later, and a date changed weeks later is a figure nobody can defend.
//
//  So: an engineer has a fixed number of days after the inspection ends to
//  close it. Miss that and the job locks itself. Nothing on it can be changed
//  after that — not by the engineer, not by the coordinator — until somebody
//  with the authority reopens it and says why.
//
//  One deliberate exception: DOCUMENTS CAN STILL BE UPLOADED. The report, the
//  photographs, the mill certificates — those are the evidence, they are often
//  what the engineer was waiting for, and refusing them would only push people
//  into sending files by e-mail instead. Locking is about stopping figures
//  being rewritten, not about stopping work.
// ===========================================================================

function joblock_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('jobs', 'locked_at',      "VARCHAR(30) DEFAULT ''");
    ensure_column('jobs', 'lock_alerted_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('jobs', 'unlocked_by',    "VARCHAR(120) DEFAULT ''");
    ensure_column('jobs', 'unlocked_at',    "VARCHAR(30) DEFAULT ''");
    ensure_column('jobs', 'unlock_reason',  "VARCHAR(255) DEFAULT ''");
    ensure_column('jobs', 'unlock_until',   "VARCHAR(20) DEFAULT ''");
}

// How long after the inspection ends the engineer has. A setting, not a rule
// baked into the code — two days is what was asked for, but a business that
// works differently should not need a programmer.
function job_close_grace_days() {
    $v = setting_get('job_close_grace_days', '');
    if ($v === '' || $v === null) return 2;
    $n = (int)$v;
    return ($n >= 0 && $n <= 365) ? $n : 2;
}
// Turning it off entirely: an empty or zero grace means no automatic locking.
function job_lock_enabled() { return setting_get('job_lock_enabled', '1') === '1'; }

// The last day the engineer may still close it themselves.
function job_close_due($job) {
    $end = trim((string)($job['inspection_end_date'] ?? '')) ?: trim((string)($job['inspection_start_date'] ?? ''))
        ?: trim((string)($job['scheduled_date'] ?? ''));
    if ($end === '') return '';
    $ts = strtotime($end);
    if ($ts === false) return '';
    return date('Y-m-d', strtotime("+" . job_close_grace_days() . " day", $ts));
}

// Everything a screen needs to know, in one call.
//   locked   — is it locked right now
//   due      — the last day to close it
//   overdue  — days past that (0 or more)
//   left     — days still in hand (only when not yet overdue)
function job_lock_state($job, $today = null) {
    $today = $today ?: date('Y-m-d');
    $out = ['locked'=>false, 'due'=>'', 'overdue'=>0, 'left'=>null, 'reason'=>'', 'unlocked'=>false];
    if (!$job) return $out;
    $out['due'] = job_close_due($job);
    // An administrator can hand back a window; while it lasts the job behaves
    // as though it were still in time.
    $until = trim((string)($job['unlock_until'] ?? ''));
    if ($until !== '' && $until >= $today) {
        $out['unlocked'] = true;
        $out['reason'] = trim((string)($job['unlock_reason'] ?? ''));
        return $out;
    }
    if (!empty($job['closed_flag'])) return $out;            // closed on time, nothing to lock
    if (!job_lock_enabled() || $out['due'] === '') return $out;
    $d = days_between($out['due'], $today);
    if ($d === null) return $out;
    if ($d > 0) { $out['locked'] = true; $out['overdue'] = $d; }
    else        { $out['left'] = -$d; }
    // A row already stamped stays locked even if somebody moves the date later —
    // moving the date to escape the lock is exactly what this prevents.
    if (trim((string)($job['locked_at'] ?? '')) !== '') $out['locked'] = true;
    return $out;
}

// Who may hand a locked job back. Deliberately not the engineer and not the
// coordinator: if the person who missed the deadline can undo it, there is no
// deadline.
function can_unlock_job() {
    return is_master() || can('users.manage.global') || can('settings.manage')
        || can('workforce.report.approve');
}

// A one-line sentence for the screen, in the words a person would use.
function job_lock_text($state) {
    if ($state['unlocked'])
        return 'Reopened until ' . $state['reason'];
    if ($state['locked'])
        return 'Locked — it was due to be closed on ' . $state['due'] . ', which was '
             . $state['overdue'] . ' day' . ($state['overdue'] === 1 ? '' : 's') . ' ago.';
    if ($state['left'] === null) return '';
    if ($state['left'] === 0) return 'Close it today — it locks after ' . $state['due'] . '.';
    return 'Close it within ' . $state['left'] . ' day' . ($state['left'] === 1 ? '' : 's')
         . ' — it locks after ' . $state['due'] . '.';
}

// The gate every write path calls. Returns '' when the change may go ahead,
// otherwise the reason it may not, in plain words.
function job_lock_block($job) {
    $st = job_lock_state($job);
    if (!$st['locked']) return '';
    return 'This ' . Tl('job') . ' was locked because it was not closed within '
         . job_close_grace_days() . ' day' . (job_close_grace_days() === 1 ? '' : 's')
         . ' of the inspection finishing (due ' . $st['due'] . '). Nothing on it can be changed now. '
         . 'Reports and photographs can still be uploaded. Ask a manager to reopen it if a figure has to be corrected.';
}

// ---------------------------------------------------------------------------
//  The sweep — run from cron, once a day is enough
//
//  Stamps every job that has run out of time, and tells the four people who
//  need to know. Once each: a lock that mails every morning stops being read.
// ---------------------------------------------------------------------------
function joblock_sweep($today = null) {
    if (!job_lock_enabled()) return ['locked' => 0, 'alerted' => 0];
    $today = $today ?: date('Y-m-d');
    $locked = 0; $alerted = 0;
    $open = ops_all("SELECT j.*, c.client_id, i.name inspector_name, i.email inspector_email,
                            o.name office_name, o.manager_email, o.coordinator_email
                     FROM jobs j
                     LEFT JOIN calls c ON c.id = j.call_id
                     LEFT JOIN inspectors i ON i.id = j.inspector_id
                     LEFT JOIN offices o ON o.id = j.executing_office_id
                     WHERE COALESCE(j.closed_flag,0) = 0");
    foreach ($open as $j) {
        $st = job_lock_state($j, $today);
        if (!$st['locked'] || $st['unlocked']) continue;
        if (trim((string)($j['locked_at'] ?? '')) === '') {
            db()->prepare("UPDATE jobs SET locked_at=? WHERE id=?")->execute([date('c'), $j['id']]);
            idems_log('job', $j['id'], 'LOCKED', ['field' => $j['job_code'],
                'reason' => 'not closed by ' . $st['due']]);
            $locked++;
        }
        if (trim((string)($j['lock_alerted_at'] ?? '')) !== '') continue;   // told them already
        if (joblock_alert($j, $st)) {
            db()->prepare("UPDATE jobs SET lock_alerted_at=? WHERE id=?")->execute([date('c'), $j['id']]);
            $alerted++;
        }
    }
    return ['locked' => $locked, 'alerted' => $alerted];
}

// The engineer, their branch manager, the coordinator and the administrators —
// the four the owner named. Everybody on one mail, so the conversation that
// follows has everyone in it.
function joblock_alert($j, $st) {
    $to = []; $cc = [];
    if (trim((string)($j['inspector_email'] ?? '')) !== '') $to[] = $j['inspector_email'];
    foreach (['manager_email', 'coordinator_email'] as $k)
        if (trim((string)($j[$k] ?? '')) !== '') $cc[] = $j[$k];
    // the reporting manager of the engineer's own login, if they have one
    $mgr = ops_val("SELECT u2.email FROM users u1 LEFT JOIN users u2 ON u2.id = u1.reports_to_id
                    WHERE u1.inspector_id = ? AND u2.email <> ''", [(int)($j['inspector_id'] ?? 0)]);
    if ($mgr) $cc[] = $mgr;
    foreach (ops_all("SELECT email FROM users WHERE is_active=1 AND email <> ''
                      AND (is_superuser=1 OR role IN ('MASTER_ADMIN','BRANCH_MANAGER'))") as $u) $cc[] = $u['email'];
    $to = array_values(array_unique(array_filter($to)));
    $cc = array_values(array_unique(array_diff(array_filter($cc), $to)));
    if (!$to && !$cc) return false;
    if (!$to) { $to = $cc; $cc = []; }

    $sub = 'Locked: ' . $j['job_code'] . ' was not closed within ' . job_close_grace_days() . ' days';
    $body = "The following " . Tl('job') . " has been locked because it was not closed in time.\n\n"
        . Tl('job') . ": {$j['job_code']}\n"
        . TH('engineer') . ": " . ($j['inspector_name'] ?: 'not allocated') . "\n"
        . T('office') . ": " . ($j['office_name'] ?: '—') . "\n"
        . "Inspection finished: " . ($j['inspection_end_date'] ?: $j['scheduled_date'] ?: '—') . "\n"
        . "Was due to be closed by: {$st['due']}\n"
        . "Days overdue: {$st['overdue']}\n\n"
        . "Nothing on this " . Tl('job') . " can be changed now — dates, man-days, expenses and credit are fixed as they stand.\n"
        . "Reports, photographs and other documents CAN still be uploaded; that part is not blocked.\n\n"
        . "If a figure genuinely has to be corrected, a manager can reopen it for a few days from the "
        . Tl('job') . " screen, giving a reason. The reopening is recorded.\n";
    ops_mail(implode(',', $to), $sub, $body, implode(',', $cc), 'JOB_LOCKED');
    return true;
}

// ---------------------------------------------------------------------------
//  Reopening — an administrator's decision, with a reason and an end date
// ---------------------------------------------------------------------------
function job_unlock($jobId, $days, $reason) {
    $jobId = (int)$jobId;
    $days = max(1, min(30, (int)$days));
    $reason = trim((string)$reason);
    $until = date('Y-m-d', strtotime("+$days day"));
    db()->prepare("UPDATE jobs SET unlock_until=?, unlock_reason=?, unlocked_by=?, unlocked_at=?, locked_at='' WHERE id=?")
        ->execute([$until, $reason, user_name(current_user()), date('c'), $jobId]);
    idems_log('job', $jobId, 'UNLOCKED', ['field' => $until, 'reason' => $reason]);
    return $until;
}

function ops_job_unlock($method) {
    ops_require($method === 'POST', 'Use the button on the ' . Tl('job') . ' screen.');
    ops_require(can_unlock_job(), 'Only a manager or an administrator can reopen a locked ' . Tl('job') . '.');
    $id = (int)($_GET['id'] ?? $_POST['job_id'] ?? 0);
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$id]);
    if (!$job) { http_response_code(404); view('notfound'); return; }
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        flash('Say why it is being reopened — the reason is kept with the ' . Tl('job') . '.', 'error');
        redirect('/job?id=' . $id);
    }
    $until = job_unlock($id, $_POST['days'] ?? 3, $reason);
    flash($job['job_code'] . ' reopened until ' . $until . '. Close it before then or it locks again.');
    redirect('/job?id=' . $id);
}
