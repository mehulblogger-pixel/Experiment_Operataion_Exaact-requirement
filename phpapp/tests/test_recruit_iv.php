<?php
// Phase 4 — multi-round interviews + scorecards, and the candidate document DMS.
t_section('interviews & document DMS (Phase 4)');

recruit_iv_migrate();

// A candidate to hang interviews/documents off.
db()->prepare("INSERT INTO candidates (cand_code,first_name,stage,created_at) VALUES ('CAN-IV','Test',' RECEIVED',?)")->execute([date('c')]);
$cid = (int)db()->lastInsertId();

// --- Interviews: multi-round ---
$l1 = iv_schedule($cid, ['round' => 'L1', 'mode' => 'Video', 'panel' => 'A. Mehta', 'competencies' => 'SQL, Tally', 'scheduled_at' => '2026-09-20T10:00']);
$l2 = iv_schedule($cid, ['round' => 'L2', 'mode' => 'In person', 'panel' => 'VP-HR']);
t_eq(count(iv_list($cid)), 2, 'multiple interview rounds are stored per candidate');
$iv = iv_get($l1);
t_eq($iv['result'], 'SCHEDULED', 'a new interview starts SCHEDULED');
t_eq($iv['round'], 'L1', 'the round is stored');

// Record a scorecard → PASS stamps done_at.
iv_record($l1, ['result' => 'PASS', 'rating' => 4, 'recommendation' => 'Hire', 'comments' => 'Strong on fundamentals']);
$iv = iv_get($l1);
t_eq($iv['result'], 'PASS', 'the interview result is recorded');
t_eq((int)$iv['rating'], 4, 'the rating is stored');
t_eq($iv['recommendation'], 'Hire', 'the recommendation is stored');
t_ok($iv['done_at'] !== '', 'a concluded interview stamps done_at');

// An out-of-range rating is clamped; an unknown result falls back to SCHEDULED.
iv_record($l2, ['result' => 'BOGUS', 'rating' => 99]);
$iv2 = iv_get($l2);
t_eq($iv2['result'], 'SCHEDULED', 'an unknown result is rejected (stays SCHEDULED)');
t_eq((int)$iv2['rating'], 5, 'a rating over 5 is clamped to 5');

iv_delete($l2);
t_eq(count(iv_list($cid)), 1, 'an interview can be removed');

// --- Documents: the status lifecycle ---
$d1 = doc_add($cid, ['doc_type' => 'PAN', 'status' => 'REQUIRED']);
t_ok($d1 > 0, 'a required document is added to the checklist');
$doc = doc_get($d1);
t_eq($doc['status'], 'REQUIRED', 'it starts REQUIRED');
t_eq((int)$doc['sensitive'], 1, 'PAN is flagged sensitive');

doc_status_set($d1, 'UNDER_REVIEW');
doc_verify($d1);
$doc = doc_get($d1);
t_eq($doc['status'], 'VERIFIED', 'a document can be verified');
t_ok($doc['verified_at'] !== '', 'verification is timestamped');

doc_reject($d1, 'blurred scan', true);
$doc = doc_get($d1);
t_eq($doc['status'], 'RESUBMIT', 'a document can be returned for resubmission');
t_eq($doc['rejection_reason'], 'blurred scan', 'the reason is recorded');

// Expiry: a verified doc past its expiry reads EXPIRED (display logic).
$d2 = doc_add($cid, ['doc_type' => 'Medical report']);
db()->prepare("UPDATE candidate_docs SET status='VERIFIED', expiry_date='2020-01-01' WHERE id=?")->execute([$d2]);
t_eq(doc_effective_status(doc_get($d2)), 'EXPIRED', 'a verified document past its expiry reads EXPIRED');
t_ok(doc_is_sensitive('Medical report'), 'a medical report is sensitive');
t_ok(!doc_is_sensitive('Resume / CV'), 'a résumé is not sensitive');

// A sensitive document is not downloadable without admin-level (no user in tests).
t_ok(!doc_can_download(['sensitive' => 1]), 'a sensitive file is download-restricted for a non-admin');
t_ok(doc_can_download(['sensitive' => 0]), 'a non-sensitive file is downloadable');
