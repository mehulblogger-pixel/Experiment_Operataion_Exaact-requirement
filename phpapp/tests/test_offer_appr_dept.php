<?php
// Regression (found in end-to-end testing): an offer's approval-matching context
// must take its DEPARTMENT from the requisition's department (then the candidate's,
// then the SBU) — not the SBU alone. Otherwise an approval rule keyed on
// Department never matches a requisition that sets a department but no SBU, and
// the offer silently skips the approval chain.
t_section('offer approval context — department dimension');

if (function_exists('req_migrate')) req_migrate();
$pdo = db();

// A requisition with a DEPARTMENT but no SBU.
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,grade,sbu,status,created_at) VALUES ('RQ-DPT','QA Engineer','Quality','M2','','OPEN', ?)")->execute([date('c')]);
$rq = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,requisition_id,stage,created_at) VALUES ('CV-DPT','Dept','Test',?, 'OFFERED', ?)")->execute([$rq, date('c')]);
$cid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_at) VALUES (?,?, 'DRAFT', ?)")->execute([$cid, 900000, date('c')]);
$oid = (int)$pdo->lastInsertId();

$ctx = offer_appr_ctx(offer_get($oid));
t_eq($ctx['department'], 'Quality', "the offer context's department comes from the requisition's department, not the SBU");

// End to end: a rule keyed on that department must route the offer.
$rule = appr_rule_save(0, ['name' => 'Quality offers', 'entity' => 'OFFER', 'applies_department' => 'Quality']);
appr_level_save(['rule_id' => $rule, 'seq' => 10, 'label' => 'Head', 'approver_role' => 'MASTER_ADMIN', 'sla_days' => 2, 'reminder_days' => 1]);
offer_submit($oid);
t_ok(appr_open('OFFER', $oid) !== null, 'an approval rule keyed on Department routes the offer (regression)');

// Clean up.
$pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id IN (SELECT id FROM recruit_approval_requests WHERE entity='OFFER' AND entity_id=?)")->execute([$oid]);
$pdo->prepare("DELETE FROM recruit_approval_requests WHERE entity='OFFER' AND entity_id=?")->execute([$oid]);
$pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$rule]);
$pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$rule]);
$pdo->prepare("DELETE FROM job_offers WHERE id=?")->execute([$oid]);
$pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rq]);
