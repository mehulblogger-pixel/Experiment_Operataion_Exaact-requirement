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
