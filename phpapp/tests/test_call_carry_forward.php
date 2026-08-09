<?php
// Editing an inspection call AFTER it is allocated must carry the change forward
// onto its open deputations — dates, days, reporting rhythm, report formats,
// contract number — EXCEPT where the deputation has its own value, which is
// left alone. Closed deputations are never touched. All against the real
// boot()-migrated schema.
t_section('edit-the-call carries forward to open jobs (#carry)');

$now = date('c');

// A client + vendor so the call is complete enough to resolve dates.
$bp = function ($name, $client, $vendor) use ($now) {
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, is_vendor, status, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$name, $name, strtoupper(substr($name,0,3)), $client, $vendor, 'ACTIVE', $now]);
    return (int)db()->lastInsertId();
};
$client = $bp('Carry Client', 1, 0);
$vendor = $bp('Carry Vendor', 0, 1);

// A call with a single required date, a reporting rhythm and one report format.
db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, inspection_required_date,
               inspection_dates, engagement_type, reporting_frequency, deliverables, contract_number, created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['CCF', $client, $vendor, 'ALLOCATED', '2026-10-05', '2026-10-05', 'SINGLE',
               'WEEKLY', 'IC', 'CT-100', $now]);
$callId = (int)db()->lastInsertId();
$oldCall = ops_one("SELECT * FROM calls WHERE id=?", [$callId]);

// Two deputations off it: one still in step with the call, one that has been
// hand-changed (its reporting set to NOREPORT on the job itself). Plus a CLOSED
// deputation that must never be touched.
$mkJob = function ($code, $closed, $reporting) use ($callId, $now) {
    db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, inspection_dates,
                   engagement_type, reporting_frequency, deliverables, contract_number, closed_flag, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$code, $callId, 1, '2026-10-05', '2026-10-05', 'SINGLE', $reporting, 'IC', 'CT-100', $closed, $now]);
    return (int)db()->lastInsertId();
};
$jInStep = $mkJob('JCF1', 0, 'WEEKLY');     // matches the call
$jCustom = $mkJob('JCF2', 0, 'NOREPORT');   // reporting overridden on the job
$jClosed = $mkJob('JCF3', 1, 'WEEKLY');     // closed — frozen

// Now the coordinator edits the call: new dates (a 3-day run), a new reporting
// rhythm, an extra report format, and a new contract number.
db()->prepare("UPDATE calls SET inspection_required_date='2026-11-10', inspection_dates='2026-11-10,2026-11-11,2026-11-12',
               engagement_type='MULTIPLE', reporting_frequency='DAILY', deliverables='IC,RN', contract_number='CT-200'
               WHERE id=?")->execute([$callId]);
$newCall = ops_one("SELECT * FROM calls WHERE id=?", [$callId]);

[$changed, $kept] = call_carry_forward_to_jobs($oldCall, $newCall);
t_ok($changed >= 1, 'at least one open deputation was carried forward');

$j1 = ops_one("SELECT * FROM jobs WHERE id=?", [$jInStep]);
$j2 = ops_one("SELECT * FROM jobs WHERE id=?", [$jCustom]);
$j3 = ops_one("SELECT * FROM jobs WHERE id=?", [$jClosed]);

// The in-step job follows the call on every carried field.
t_eq($j1['contract_number'], 'CT-200', 'the contract number carries to the in-step deputation');
t_eq($j1['reporting_frequency'], 'DAILY', 'the reporting rhythm carries to the in-step deputation');
t_eq($j1['deliverables'], 'IC,RN', 'the report formats carry to the in-step deputation');
t_eq($j1['engagement_type'], 'MULTIPLE', 'the engagement shape carries to the in-step deputation');
t_eq($j1['inspection_dates'], '2026-11-10,2026-11-11,2026-11-12', 'the new dates carry to the in-step deputation');
t_eq(substr((string)$j1['scheduled_date'],0,10), '2026-11-10', 'the scheduled date follows the new first date');
t_eq(substr((string)$j1['inspection_end_date'],0,10), '2026-11-12', 'the end date follows the new last date');

// The overridden job keeps its own reporting rhythm, but still follows fields it
// had NOT diverged on (the contract number and dates were still in step).
t_eq($j2['reporting_frequency'], 'NOREPORT', 'a deputation that overrode its reporting keeps it — not clobbered');
t_eq($j2['contract_number'], 'CT-200', 'but the same deputation still follows fields it had not changed');

// The closed deputation is frozen — nothing carries.
t_eq($j3['contract_number'], 'CT-100', 'a CLOSED deputation is never touched (contract)');
t_eq($j3['reporting_frequency'], 'WEEKLY', 'a CLOSED deputation is never touched (reporting)');
t_eq($j3['inspection_dates'], '2026-10-05', 'a CLOSED deputation is never touched (dates)');

t_ok($kept >= 1, 'the overridden field was reported as kept, not carried');
