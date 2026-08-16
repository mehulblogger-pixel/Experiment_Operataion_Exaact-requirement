<?php
// Coordinator playbook — the guided forward path shown at the top of a job.
// It must name exactly one "do this now" step (the first incomplete one), march
// it forward as each step is satisfied, and treat a client-confirmation
// requirement as a real blocker.
t_section('job coordinator playbook (TOSRM)');

tosrm_migrate_c();

// An own (employee) engineer and a freelancer to assign.
db()->prepare("INSERT INTO inspectors (name, staff_kind, status, created_at) VALUES (?,?, 'ACTIVE', ?)")
    ->execute(['Ravi Kumar', 'EMPLOYEE', date('c')]);
$emp = (int)db()->lastInsertId();
db()->prepare("INSERT INTO inspectors (name, staff_kind, status, created_at) VALUES (?,?, 'ACTIVE', ?)")
    ->execute(['Freelance Fahad', 'FREELANCER', date('c')]);
$free = (int)db()->lastInsertId();

$step = function ($pb, $key) {
    foreach ($pb['steps'] as $s) if ($s['key'] === $key) return $s;
    return null;
};

// --- Just allocated: inspector on, employee (auto-confirmed), no date yet. ----
$job = ['id' => 0, 'inspector_id' => $emp, 'inspector_name' => 'Ravi Kumar',
        'assign_state' => '', 'accept_state' => '', 'scheduled_date' => '',
        'client_confirmed' => 0, 'client_confirm_required' => 0, 'closed_flag' => 0];
$pb = tosrm_job_playbook($job);
t_eq($step($pb, 'assigned')['state'], 'done', 'an assigned engineer marks step 1 done');
t_eq($step($pb, 'commit')['state'],   'done', 'an own employee is auto-confirmed — commit is done');
t_eq($step($pb, 'date')['state'],     'now',  'with no date, "set the visit date" is the one to do now');
$nows = array_filter($pb['steps'], fn($s) => $s['state'] === 'now');
t_eq(count($nows), 1, 'exactly one step is flagged "do this now"');

// --- Add the date: forward flow, nothing else required -> ready to run. -------
$job['scheduled_date'] = '2026-09-20';
$pb = tosrm_job_playbook($job);
t_eq($step($pb, 'date')['state'], 'done', 'setting the date marks step 3 done');
t_ok($pb['all_done'], 'employee + date + nothing required => ready to run (no open steps)');

// --- A freelancer must accept before commit is done. --------------------------
$job2 = ['id' => 0, 'inspector_id' => $free, 'inspector_name' => 'Freelance Fahad',
         'assign_state' => 'CONFIRMED', 'accept_state' => 'PENDING', 'scheduled_date' => '2026-09-20',
         'client_confirmed' => 0, 'client_confirm_required' => 0, 'closed_flag' => 0];
$pb = tosrm_job_playbook($job2);
t_eq($step($pb, 'commit')['state'], 'now', 'a freelancer awaiting acceptance holds the commit step open');
$job2['accept_state'] = 'ACCEPTED';
$pb = tosrm_job_playbook($job2);
t_eq($step($pb, 'commit')['state'], 'done', 'once the freelancer accepts, commit is done');

// --- A required-but-missing client confirmation is a blocker, not just "todo". -
$job3 = ['id' => 0, 'inspector_id' => $emp, 'inspector_name' => 'Ravi Kumar',
         'assign_state' => 'CONFIRMED', 'accept_state' => '', 'scheduled_date' => '2026-09-20',
         'client_confirmed' => 0, 'client_confirm_required' => 1, 'closed_flag' => 0];
$pb = tosrm_job_playbook($job3);
t_eq($step($pb, 'confirm')['state'], 'blocked', 'a required, unmet client confirmation shows as blocked');
t_ok(!$pb['all_done'], 'a blocker keeps the job from reading as ready to run');
$job3['client_confirmed'] = 1;
$pb = tosrm_job_playbook($job3);
t_eq($step($pb, 'confirm')['state'], 'done', 'recording the confirmation clears the blocker');

// --- A closed job reads as closed, no open steps. -----------------------------
$job4 = $job; $job4['closed_flag'] = 1;
$pb = tosrm_job_playbook($job4);
t_ok($pb['closed'] && $pb['all_done'], 'a closed job has no open steps and is marked closed');


// ===========================================================================
//  Order (call) playbook — the upstream sibling: details -> allocate -> dates
//  -> track to close. Same one-'now' invariant, same real blocker on details.
// ===========================================================================
t_section('order (call) playbook (TOSRM)');

$cstep = function ($pb, $key) {
    foreach ($pb['steps'] as $s) if ($s['key'] === $key) return $s;
    return null;
};

// A bare call is missing mandatory fields -> "complete the details" is a blocker,
// and allocation cannot yet be the "do this now" step.
db()->prepare("INSERT INTO calls (call_code, status, created_at) VALUES ('CALL-PB-1','OPEN',?)")->execute([date('c')]);
$callId = (int)db()->lastInsertId();
$call = ops_one("SELECT * FROM calls WHERE id=?", [$callId]);
$pb = tosrm_call_playbook($call);
t_eq($cstep($pb, 'details')['state'], 'blocked', 'a call missing mandatory fields blocks on "complete the details"');
t_ok(!$pb['all_done'], 'an incomplete order is not ready to run');

// Override the validation -> details clear, and with no jobs "allocate" is now.
tosrm_override_validation($callId, 'Client PO follows next week');
$call = ops_one("SELECT * FROM calls WHERE id=?", [$callId]);
$pb = tosrm_call_playbook($call);
t_eq($cstep($pb, 'details')['state'],  'done', 'overriding the validation clears the details step');
t_eq($cstep($pb, 'allocate')['state'], 'now',  'a ready order with no jobs points to "allocate the work"');

// Allocate a job with no date -> allocate done, "set the visit dates" is now.
db()->prepare("INSERT INTO jobs (job_code, call_id, scheduled_date, closed_flag, created_at)
    VALUES ('JOB-PB-1', ?, '', 0, ?)")->execute([$callId, date('c')]);
$pb = tosrm_call_playbook($call);
t_eq($cstep($pb, 'allocate')['state'], 'done', 'allocating a job satisfies the allocate step');
t_eq($cstep($pb, 'schedule')['state'], 'now',  'an allocated job with no date points to "set the visit dates"');

// Give it a date -> everything actionable is done; only "track to close" remains.
db()->prepare("UPDATE jobs SET scheduled_date='2026-09-20' WHERE call_id=?")->execute([$callId]);
$pb = tosrm_call_playbook($call);
t_eq($cstep($pb, 'schedule')['state'], 'done', 'setting the date satisfies the schedule step');
t_ok($pb['all_done'] && !$pb['closed'], 'scheduled + under way => no open steps, not yet closed');

// Close the only job -> the order reads as closed.
db()->prepare("UPDATE jobs SET closed_flag=1 WHERE call_id=?")->execute([$callId]);
$pb = tosrm_call_playbook($call);
t_ok($pb['closed'], 'once every job is closed, the order is closed');
