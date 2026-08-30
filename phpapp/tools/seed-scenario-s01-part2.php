<?php
// DEMO-S01 — part 2: matching snapshot → applications → selection → identity
// link → engagement → operations deployment (PDSO) → schedule.  Reuses the
// existing engines only. Loaded from seed-scenario-s01.php ($GLOBALS['S01']).
$S = $GLOBALS['S01'];
extract($S); // arjun, candB..E, party, reqId, uAdmin, uCoord, uReview, uIssue, today, D

// ---------------------------------------------------------------------------
//  PHASE 4 — APPLICATIONS + CANDIDATE SELECTION (real lifecycle)
// ---------------------------------------------------------------------------
$apply = function ($proId, $name, $rate, $note) use ($reqId) {
    return (int)cx_application_add($reqId, ['applicant_professional_id' => $proId, 'applicant_name' => $name, 'proposed_rate' => $rate, 'cover_note' => $note]);
};
$arjunApp = $apply($arjun, 'Arjun Mehta', 8500, 'Available for the proposed assignment; experienced in 220 kV and 400 kV substation inspection.');
$dupTry   = $apply($arjun, 'Arjun Mehta', 8500, 'duplicate attempt');   // NEGATIVE 05 — must be 0
$bApp     = $apply($candB, 'Vikram Rao (S01-B)', 9000, 'Strong 220 kV substation background.');
$cApp     = $apply($candC, 'Sunil Patel (S01-C)', 7000, 'Electrical inspection, limited transmission.');
$dApp     = $apply($candD, 'Ramesh Iyer (S01-D)', 6500, 'Civil/QA background.');
$eApp     = $apply($candE, 'Deepak Shah (S01-E)', 12000, 'Mechanical background; not available on dates.');
say('  Applications: Arjun=#' . $arjunApp . ' (dup prevented=' . ($dupTry === 0 ? 'yes' : 'NO') . '), B=#' . $bApp . ', C=#' . $cApp . ', D=#' . $dApp . ', E=#' . $eApp);

// Alternate/negative flows on candidates
if ($cApp) cx_application_transition($cApp, 'WITHDRAWN');            // C withdraws
if ($eApp) cx_application_transition($eApp, 'REJECTED');             // E rejected (unsuitable/unavailable)
$badTransition = $eApp ? cx_application_transition($eApp, 'ACCEPTED') : true;   // invalid REJECTED→ACCEPTED must fail

// Select Arjun: move the requirement into SHORTLISTING, shortlist the app, then AWARD.
cx_requirement_transition($reqId, 'SHORTLISTING');
cx_application_transition($arjunApp, 'SHORTLISTED');
$awarded = cx_requirement_award($reqId, $arjunApp);   // brings the app OFFERED→ACCEPTED + AWARDS
say('  Selection: Arjun shortlisted→offered→accepted; requirement awarded=' . ($awarded ? 'yes' : 'NO') . '; invalid REJECTED→ACCEPTED blocked=' . ($badTransition ? 'NO' : 'yes'));

// Client keeps Arjun on their private bench (rehire later)
if (function_exists('connect_client_bench_add'))
    connect_client_bench_add($party, ['professional_id' => $arjun, 'source' => 'marketplace', 'private_note' => 'Excellent on the 220 kV substation inspection — reuse.', 'client_rating' => 5, 'preferred' => 1, 'preferred_rate' => 8500], 'Priya Client');

// ---------------------------------------------------------------------------
//  PHASE 5 — UNIFIED IDENTITY → OPERATIONS DEPLOYMENT (no duplicate person)
// ---------------------------------------------------------------------------
// The selected marketplace professional becomes an internal inspector identity
// via a LINK (Connection #1) — one person, not a second profile.
db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,email,mobile,status,created_at) VALUES ('Arjun Mehta','DEMO-S01-INS-01','IND',?, 'arjun.s01@demo.test','9812300001','ACTIVE',?)")
    ->execute(['electrical, transmission, substation inspection, 220kV, 400kV, QA/QC', date('c')]);
$arjunInsp = (int)db()->lastInsertId();
if (function_exists('connect_identity_link_create')) connect_identity_link_create($arjun, $arjunInsp, 'email_match', 'demo-seed');

// Book the engagement (basis + rate) so deployment + billing have real terms.
if (function_exists('connect_engage_save_for_requirement'))
    connect_engage_save_for_requirement($reqId, ['deputation_basis' => 'MANDAYS', 'rate' => 8500, 'rate_unit' => 'day', 'quantity' => 5, 'start_date' => $D(10), 'end_date' => $D(15), 'rate_inclusive' => 'INCLUSIVE', 'voucher_cadence' => 'PER_DEPLOYMENT']);

// A client inspection CALL, so the job connects to the client (call → client).
db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,inspection_required_date,status,created_by,created_at) VALUES ('DEMO-S01-CALL-01',?, 'Electrical Inspection', ?, 'OPEN','demo-seed', ?)")->execute([$party, $D(10), date('c')]);
$callId = (int)db()->lastInsertId();

// Deploy the award into the EXISTING PDSO deputation engine (Connection #3).
$jobId = 0;
if (function_exists('connect_deploy_from_engagement')) { [$dok, $dmsg, $jobId] = connect_deploy_from_engagement($reqId); }
if ($jobId > 0) {
    // Give it the scenario's job code + the client call + inspection type/service.
    db()->prepare("UPDATE jobs SET job_code='DEMO-S01-JOB-001', call_id=?, inspection_type='Electrical Inspection', service_code='SUBSTATION', dep_site='Ahmedabad, Gujarat', scheduled_date=?, inspection_start_date=?, inspection_end_date=? WHERE id=?")
        ->execute([$callId, $D(10), $D(10), $D(15), $jobId]);
    if (function_exists('pdso_set_status')) pdso_set_status($jobId, 'MOBILIZED');
}
say('  Identity: Arjun ↔ inspector #' . $arjunInsp . ' linked. Deployment: ' . ($jobId ? ('DEMO-S01-JOB-001 (job #' . $jobId . ', assigned)') : 'FAILED'));

// Conflict test — Candidate B linked + an overlapping deputation on the same dates.
db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,email,status,created_at) VALUES ('Vikram Rao','DEMO-S01-INS-02','IND','substation, 220kV','cand.b.s01@demo.test','ACTIVE',?)")->execute([date('c')]);
$bInsp = (int)db()->lastInsertId();
if (function_exists('connect_identity_link_create')) connect_identity_link_create($candB, $bInsp, 'email_match', 'demo-seed');
db()->prepare("INSERT INTO jobs (job_code,inspector_id,job_type,dep_status,dep_site,inspection_start_date,inspection_end_date,sbu,created_at) VALUES ('DEMO-S01-JOB-CONFLICT',?, 'DEPUTATION','ACTIVE','Vapi, Gujarat',?,?, 'IND', ?)")
    ->execute([$bInsp, $D(11), $D(14), date('c')]);
say('  Conflict fixture: Candidate B (#' . $bInsp . ') has an overlapping deputation on the same dates.');

$GLOBALS['S01'] += compact('arjunApp','bApp','cApp','dApp','eApp','arjunInsp','bInsp','jobId','callId');

// Phase 6–7 (inspection activities, finding, evidence, report, rating, dashboard).
if (is_file(__DIR__ . '/seed-scenario-s01-part3.php')) require __DIR__ . '/seed-scenario-s01-part3.php';
else say('  (part 3 — findings/report/rating — not yet present)');
