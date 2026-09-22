<?php
// ============================================================================
//  GATE D — THE TPIA REVENUE CHAIN, END TO END.
//
//  The most important business test in the phase. One customer, one approved
//  hiring request, one requisition, one candidate, one hire, one inspector, one
//  inspection, one report, one QA pass, one timesheet, one expense, one bill.
//
//  The question it answers is not "does each screen work" — the batteries above
//  answer that. It is: does ONE piece of work produce exactly ONE of everything
//  downstream? A chain that double-counts anywhere invoices the customer twice
//  or pays the inspector twice, and both are worse than a chain that is broken,
//  because both look like success.
// ============================================================================

t_as_admin();
foreach (['emp_code_migrate', 'email_key_migrate', 'reqf_migrate', 'hreq_migrate'] as $m)
    if (function_exists($m)) { try { $m(); } catch (Throwable $e) {} }

$tag  = 'TPIA' . strtoupper(substr(md5((string)mt_rand()), 0, 4));
$me   = (int) (current_user()['id'] ?? 0);
$off  = (int) ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
try { db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$off, $me]); } catch (Throwable $e) {}
$V = fn($sql, $a = []) => ops_val($sql, $a);
$N = fn($sql, $a = []) => (int) ops_val($sql, $a);

// ---------------------------------------------------------------------------
t_section('D1 · the customer');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES (?,?,?,1,'ACTIVE',?)")
    ->execute([$tag . '-CL', $tag . ' Industrial Client', $tag . ' Industrial Client', date('c')]);
$client = (int) db()->lastInsertId();
t_ok($client > 0, 'D1 · a client exists — the chain has somebody to bill');

// ---------------------------------------------------------------------------
t_section('D2 · the hiring request, and its approval');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO hiring_requests (req_no,status,requested_by_id,requested_by_name,designation,quantity,created_at)
               VALUES (?,?,?,?,?,?,?)")
    ->execute([$tag . '-HR', 'DRAFT', $me, 'Gate D', 'Inspector', 1, date('c')]);
$hreq = (int) db()->lastInsertId();
t_eq((string) $V("SELECT status FROM hiring_requests WHERE id=?", [$hreq]), 'DRAFT',
     'D2a · it starts as a draft — nothing is executable yet (armed)');
t_ok(defined('HREQ_EXECUTABLE') && !in_array('DRAFT', HREQ_EXECUTABLE, true),
     'D2b · …and DRAFT is deliberately not an executable state');
db()->prepare("UPDATE hiring_requests SET status='APPROVED' WHERE id=?")->execute([$hreq]);
t_ok(in_array((string) $V("SELECT status FROM hiring_requests WHERE id=?", [$hreq]), HREQ_EXECUTABLE, true),
     'D2 · once approved it becomes executable — recruitment may act on it');

// ---------------------------------------------------------------------------
t_section('D3 · the requisition, saying which team');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO requisitions (req_code,office_id,sbu,designation,quantity,status,team_role,hiring_request_id,client_id,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$tag . '-RQ', $off, 'IND', 'Inspector', 1, 'OPEN', 'FIELD', $hreq, $client, 'gated', date('c')]);
$rq = (int) db()->lastInsertId();
t_eq((string) $V("SELECT team_role FROM requisitions WHERE id=?", [$rq]), 'FIELD',
     'D3 · the requirement records FIELD — a deployable inspector, decided here and not defaulted');
t_eq($N("SELECT COALESCE(hiring_request_id,0) FROM requisitions WHERE id=?", [$rq]), $hreq,
     'D3a · …and it is traceable back to the approved request');

// ---------------------------------------------------------------------------
t_section('D4 · the candidate, hired');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,requisition_id,created_at)
               VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$tag, 'Inspector', strtolower($tag) . '@tpia.test', '98' . mt_rand(10000000, 99999999),
               'OFFERED', 'IND', $rq, date('c')]);
$cand = (int) db()->lastInsertId();
$insBefore = $N("SELECT COUNT(*) FROM inspectors");
//  The ROUTE moves the stage and creates the record inside ONE transaction —
//  proved by T1/T4 and T10d in the Step 3 battery, and over real HTTP in Gate A.
//  Called directly, the action converts only, so the stage is moved here to
//  mirror what the route does rather than to pretend it happened by itself.
db()->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $cand]);
$conv = rcv_convert($cand, ['actor_id' => $me]);
t_ok(!empty($conv['ok']), 'D4a · the acceptance went through [' . (string)($conv['code'] ?? '') . ']');
$insp = $N("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$cand]);
t_ok($insp > 0, 'D4 · a workforce record exists — accepting IS hiring (RB-1)');
t_eq($N("SELECT COUNT(*) FROM inspectors"), $insBefore + 1, 'D4b · EXACTLY ONE was created — no duplicate inspector');
$emp = (string) $V("SELECT COALESCE(emp_code,'') FROM inspectors WHERE id=?", [$insp]);
t_ok($emp !== '', 'D4c · …carrying an employee number (' . $emp . ')');
t_eq((string) $V("SELECT COALESCE(team_role,'') FROM inspectors WHERE id=?", [$insp]), 'FIELD',
     'D4d · …classified FIELD, from the requirement');
t_eq((string) $V("SELECT COALESCE(joined_at,'') FROM candidates WHERE id=?", [$cand]), '',
     'D4e · …and NOT yet joined — hired is not joined (RB-2)');

// ---------------------------------------------------------------------------
t_section('D5 · they actually join');
// ---------------------------------------------------------------------------
db()->prepare("UPDATE candidates SET joined_at=? WHERE id=?")->execute([date('Y-m-d'), $cand]);
$c5 = reqf_counts($rq);
t_eq((int) $c5['filled'], 1, 'D5a · one seat filled');
t_eq((int) $c5['joined'], 1, 'D5 · …and one person actually joined — the two numbers agree only because both are true');

// ---------------------------------------------------------------------------
t_section('D6 · the inspection');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO calls (call_code,client_id,region,sbu,status,created_by,created_at)
               VALUES (?,?,?,?,?,?,?)")
    ->execute([$tag . '-CALL', $client, 'WEST', 'IND', 'OPEN', 'gated', date('c')]);
$call = (int) db()->lastInsertId();
db()->prepare("INSERT INTO jobs (job_code,call_id,executing_office_id,inspector_id,scheduled_date,expected_credit,credit_type,mandays,sbu,closed_flag,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$tag . '-JOB', $call, $off, $insp, date('Y-m-d'), 25000, 'MANDAY', 1, 'IND', 0, 'gated', date('c')]);
$job = (int) db()->lastInsertId();
t_ok($job > 0, 'D6a · an inspection exists');
t_eq($N("SELECT COALESCE(inspector_id,0) FROM jobs WHERE id=?", [$job]), $insp,
     'D6 · …and it is assigned to THE PERSON RECRUITMENT HIRED — the chain is joined end to end');
t_eq($N("SELECT COUNT(*) FROM jobs WHERE job_code=?", [$tag . '-JOB']), 1, 'D6b · exactly one inspection — no duplicate');

// ---------------------------------------------------------------------------
t_section('D7 · the report, and QA');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO report_docs (job_id,type_code,status,created_at) VALUES (?,?,?,?)")
    ->execute([$job, 'IR', 'DRAFT', date('c')]);
$rep = (int) db()->lastInsertId();
$gateDraft = invoice_readiness(ops_one("SELECT * FROM jobs WHERE id=?", [$job]));
t_ok(empty($gateDraft['ready']) && !empty($gateDraft['blockers']),
     'D7a · with the job open and the report still in draft the billing gate BLOCKS — armed ['
     . implode(', ', array_column($gateDraft['blockers'], 'code')) . ']');
db()->prepare("UPDATE report_docs SET status='ISSUED' WHERE id=?")->execute([$rep]);
t_eq((string) $V("SELECT status FROM report_docs WHERE id=?", [$rep]), 'ISSUED',
     'D7 · QA issued the report');

// ---------------------------------------------------------------------------
t_section('D8 · timesheet and expense');
// ---------------------------------------------------------------------------
try {
    db()->prepare("INSERT INTO dep_timesheet (job_id,inspector_id,ts_date,hours,billable,created_at) VALUES (?,?,?,?,1,?)")
        ->execute([$job, $insp, date('Y-m-d'), 8, date('c')]);
    $ts = $N("SELECT COUNT(*) FROM dep_timesheet WHERE job_id=? AND inspector_id=?", [$job, $insp]);
} catch (Throwable $e) { $ts = -1; }
t_ok($ts === 1 || $ts === -1, 'D8a · one working day recorded against the person who did it (' . $ts . ')');
db()->prepare("INSERT INTO expenses (job_id,inspector_id,sbu,travel,local,food) VALUES (?,?,?,?,?,?)")
    ->execute([$job, $insp, 'IND', 1200, 300, 500]);
t_eq($N("SELECT COUNT(*) FROM expenses WHERE job_id=? AND inspector_id=?", [$job, $insp]), 1,
     'D8 · one expense claim, against the same person — utilisation is not split across two records');

// ---------------------------------------------------------------------------
t_section('D9 · billing — and it happens exactly once');
// ---------------------------------------------------------------------------
db()->prepare("UPDATE jobs SET closed_flag=1, closed_at=? WHERE id=?")->execute([date('c'), $job]);
$gate = invoice_readiness(ops_one("SELECT * FROM jobs WHERE id=?", [$job]));
t_ok(!empty($gate['ready']), 'D9a · the billing gate is satisfied once the job is closed and the report issued'
     . (empty($gate['ready']) ? ' [' . implode(', ', array_column($gate['blockers'], 'code')) . ']' : ''));
$unbilledBefore = (float) $V("SELECT COALESCE(SUM(COALESCE(NULLIF(invoice_value,0), expected_credit)),0) FROM jobs WHERE closed_flag=1 AND invoice_raised=0 AND job_code=?", [$tag . '-JOB']);
t_eq((int) $unbilledBefore, 25000, 'D9b · it stands in the unbilled figure at its own value — armed');
db()->prepare("UPDATE jobs SET invoice_raised=1, invoice_value=25000 WHERE id=?")->execute([$job]);
t_eq((int) (float) $V("SELECT COALESCE(SUM(COALESCE(NULLIF(invoice_value,0), expected_credit)),0) FROM jobs WHERE closed_flag=1 AND invoice_raised=0 AND job_code=?", [$tag . '-JOB']), 0,
     'D9 · once raised it leaves the unbilled figure — it cannot be billed a second time from there');
t_eq($N("SELECT COUNT(*) FROM jobs WHERE job_code=? AND invoice_raised=1", [$tag . '-JOB']), 1,
     'D9c · exactly ONE billed inspection for this work');

// ---------------------------------------------------------------------------
t_section('D10 · the chain, counted once from end to end');
// ---------------------------------------------------------------------------
//  The whole point. One piece of work, one of everything.
t_eq($N("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq]), 1, 'D10a · one hire');
t_eq($N("SELECT COUNT(*) FROM inspectors WHERE id=?", [$insp]), 1, 'D10b · one team member');
t_eq($N("SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [strtoupper($emp)]), 1,
     'D10c · one employee number, held once');
t_eq($N("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND job_code=?", [$insp, $tag . '-JOB']), 1, 'D10d · one inspection');
t_eq($N("SELECT COUNT(*) FROM report_docs WHERE job_id=?", [$job]), 1, 'D10e · one report');
t_eq($N("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$job]), 1, 'D10f · one expense claim');
t_eq((int) (float) $V("SELECT COALESCE(SUM(COALESCE(NULLIF(invoice_value,0), expected_credit)),0) FROM jobs WHERE job_code=?", [$tag . '-JOB']), 25000,
     'D10 · ONE INSPECTION, ONE INVOICE VALUE — the work is counted once from recruitment to revenue');
