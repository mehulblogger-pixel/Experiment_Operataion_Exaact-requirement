<?php
// Phase 5 — salary structure, HR discussion, offer lifecycle & onboarding (§25–§34).
t_section('salary, offer & onboarding (Phase 5)');

recruit_offer_migrate();

db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,designation,stage,created_at) VALUES ('CAN-OF','Ravi','Sharma','Accountant','OFFERED',?)")->execute([date('c')]);
$cid = (int)db()->lastInsertId();

// --- Salary structure (§25) — computed from the configurable components ---
sal_save($cid, ['c_BASIC' => 400000, 'c_SPECIAL' => 100000,
    'candidate_expected' => 800000, 'internal_benchmark' => 720000, 'approved_budget' => 700000]);
$sal = sal_current($cid);
t_ok((float)$sal['gross_ctc'] > 0, 'a CTC is computed from the configured components');
t_ok(!empty($sal['lines_json']), 'the component lines are stored');
t_ok((float)$sal['net_pay'] > 0 && (float)$sal['net_pay'] <= (float)$sal['gross_ctc'], 'net pay is computed and not above CTC');
t_ok((float)$sal['employer_cost'] >= 0, 'employer cost is computed');
$lines = json_decode((string)$sal['lines_json'], true); $hra = 0.0;
foreach ($lines as $l) if ($l['code'] === 'HRA') $hra = (float)$l['amount'];
t_eq($hra, 160000.0, 'HRA is computed at 40% of Basic from the config');
$v = sal_variance($sal);
t_ok($v['vs_budget'] !== null && $v['vs_expected'] !== null, 'variance vs budget and expectation are computed');

// A revision creates a new current version (never overwrites history).
sal_save($cid, ['c_BASIC' => 450000, 'approved_budget' => 700000]);
t_ok((float)sal_current($cid)['gross_ctc'] > 0, 'a revised structure becomes the current one');
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
t_eq((float)$o['ctc'], (float)sal_current($cid)['gross_ctc'], 'the offer CTC defaults to the current salary structure CTC');

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
t_ok(strpos((string)$o['letter_html'], number_format((float)sal_current($cid)['gross_ctc'], 0)) !== false, 'the offer letter shows the CTC');

// Issuing coarse-syncs the candidate legacy stage to OFFERED (already there here).
t_eq(ops_one("SELECT stage FROM candidates WHERE id=?", [$cid])['stage'], 'OFFERED', 'the candidate stage reflects OFFERED');

// Accept.
[$ok, $msg] = offer_accept($oid);
t_ok($ok && offer_get($oid)['status'] === 'ACCEPTED', 'an issued offer can be accepted (→ onboarding)');

// A withdrawn/second offer path: create another, withdraw it.
$o2 = offer_create($cid, []);
offer_withdraw($o2);
t_eq(offer_get($o2)['status'], 'WITHDRAWN', 'a draft offer can be withdrawn');
