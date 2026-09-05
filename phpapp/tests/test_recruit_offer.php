<?php
// Phase 5 — salary structure, HR discussion, offer lifecycle & onboarding (§25–§34).
t_section('salary, offer & onboarding (Phase 5)');

recruit_offer_migrate();

db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,designation,stage,created_at) VALUES ('CAN-OF','Ravi','Sharma','Accountant','OFFERED',?)")->execute([date('c')]);
$cid = (int)db()->lastInsertId();

// --- Salary structure (§25) — components, total, variance ---
sal_save($cid, ['basic' => 400000, 'hra' => 200000, 'special_allowance' => 100000, 'bonus' => 50000,
    'candidate_expected' => 800000, 'internal_benchmark' => 720000, 'approved_budget' => 700000]);
$sal = sal_current($cid);
t_eq((float)$sal['gross_ctc'], 750000.0, 'the total CTC is the sum of the components');
t_eq(sal_total($sal), 750000.0, 'sal_total sums the component columns');
$v = sal_variance($sal);
t_eq($v['vs_budget'], 50000.0, 'variance vs approved budget is computed (over by 50k)');
t_eq($v['vs_expected'], -50000.0, 'variance vs candidate expectation is computed (under by 50k)');

// A revision creates a new current version (never overwrites history).
sal_save($cid, ['basic' => 450000, 'hra' => 200000, 'approved_budget' => 700000]);
t_eq((float)sal_current($cid)['gross_ctc'], 650000.0, 'a revised structure becomes the current one');
t_eq(count(ops_all("SELECT id FROM salary_structures WHERE candidate_id=?", [$cid])), 2, 'the earlier structure is kept (versioned)');

// --- HR discussion (§26) ---
hrd_save($cid, ['discussion_date' => '2026-09-20', 'hr_rep' => 'VP-HR', 'candidate_expectation' => 800000,
    'offered_amount' => 720000, 'agreed_amount' => 740000, 'outcome' => 'AGREED', 'terms' => 'Joining bonus on confirmation']);
$hrds = hrd_list($cid);
t_eq(count($hrds), 1, 'an HR discussion is recorded');
t_eq($hrds[0]['outcome'], 'AGREED', 'the discussion outcome is stored');

// --- Offer lifecycle (§32–§33) ---
$oid = offer_create($cid, ['joining_date' => '2026-10-01', 'offer_terms' => 'Standard terms']);
$o = offer_get($oid);
t_eq($o['status'], 'DRAFT', 'a new offer starts as DRAFT');
t_eq((float)$o['ctc'], 650000.0, 'the offer CTC defaults to the current salary structure total');

// §32 — an UNAPPROVED offer can never be issued.
[$ok, $msg] = offer_issue($oid);
t_ok(!$ok, 'issuing is refused while the offer is not approved');
t_eq(offer_get($oid)['status'], 'DRAFT', 'the offer stays DRAFT when issue is refused');

// Submit → approve → issue.
offer_submit($oid);
t_eq(offer_get($oid)['status'], 'PENDING_APPROVAL', 'the offer moves to PENDING_APPROVAL');
offer_approve($oid);
$o = offer_get($oid);
t_eq($o['status'], 'APPROVED', 'the offer is approved');
t_ok($o['approved_by'] !== '', 'the approver is recorded');
[$ok, $msg] = offer_issue($oid);
$o = offer_get($oid);
t_ok($ok, 'an approved offer can be issued');
t_eq($o['status'], 'ISSUED', 'the offer is now ISSUED');
t_ok(strpos((string)$o['letter_html'], 'Ravi') !== false, 'the offer letter is generated with the candidate name');
t_ok(strpos((string)$o['letter_html'], '650,000') !== false, 'the offer letter shows the CTC');

// Issuing coarse-syncs the candidate legacy stage to OFFERED (already there here).
t_eq(ops_one("SELECT stage FROM candidates WHERE id=?", [$cid])['stage'], 'OFFERED', 'the candidate stage reflects OFFERED');

// Accept.
[$ok, $msg] = offer_accept($oid);
t_ok($ok && offer_get($oid)['status'] === 'ACCEPTED', 'an issued offer can be accepted (→ onboarding)');

// A withdrawn/second offer path: create another, withdraw it.
$o2 = offer_create($cid, []);
offer_withdraw($o2);
t_eq(offer_get($o2)['status'], 'WITHDRAWN', 'a draft offer can be withdrawn');
