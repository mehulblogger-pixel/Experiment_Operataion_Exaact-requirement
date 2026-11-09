<?php
// Owner, 9 Nov 2026: from a costing, raise the hiring request (with all its
// approvals) — but "if quote is won or accepted by client then only the hiring
// request or requisition button shall be shown", and the hire must keep the
// costing's margin. Driven end to end: costing → quotation accepted → hiring
// request (DRAFT, from the costing) → approved → requisition with the costing's budget.
t_section('A won costing raises its hiring requests; the requisition keeps the costing budget');
t_as_admin();
pc_migrate(); pc_link_migrate();
$tag = 'CTH' . getmypid();
db()->prepare("INSERT INTO quotations (quote_no, rev, status, is_current, subject) VALUES (?,0,'SENT',1,'probe')")->execute([$tag . '-Q']);
$qid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO project_costings (code, title, basis, status, quote_id, overhead_pct, created_at) VALUES (?, 'Probe', 'LONG', 'DRAFT', ?, 0, ?)")
    ->execute([$tag, $qid, date('c')]);
$cid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, rate_manual, oneoff, headcount, created_at)
               VALUES (?, 1, 'Shift Engineer', ?, 36, 20, 0, 5000, 3, ?)")->execute([$cid, json_encode(['PERSONNEL' => 40000, 'FOOD' => 6000]), date('c')]);
$lid = (int) db()->lastInsertId();
$h = pc_get($cid);

t_ok(strpos(pc_hire_gate($h), 'approved') !== false, 'a costing not yet approved cannot start recruitment');
db()->prepare("UPDATE project_costings SET status='APPROVED' WHERE id=?")->execute([$cid]); $h = pc_get($cid);
t_ok(strpos(pc_hire_gate($h), 'accepts the quotation') !== false, 'an approved costing waits for the client to accept the quotation');
[$ok] = pc_raise_hiring_request($cid, $lid);
t_ok(!$ok, 'so no hiring request can be raised yet');
t_ok(isset(pc_make_requisition($cid, $lid)['err']), 'nor a requisition directly');

// The client accepts a later revision of the quotation.
db()->prepare("INSERT INTO quotations (quote_no, rev, parent_id, status, is_current, subject) VALUES (?,1,?,'ACCEPTED',1,'probe')")->execute([$tag . '-Q-R1', $qid]);
t_eq(pc_hire_gate($h), '', 'once a revision of the quotation is accepted, recruitment may start');

[$ok, $msg, $hid] = pc_raise_hiring_request($cid, $lid);
t_ok($ok && $hid > 0, 'a hiring request is raised from the role: ' . $msg);
$hr = ops_one("SELECT * FROM hiring_requests WHERE id=?", [$hid]);
t_eq([(string) $hr['status'], (int) $hr['quantity'], (int) $hr['costing_line_id']], ['DRAFT', 3, $lid], 'a DRAFT for the 3 people costed, linked to the role — it goes into the normal approvals');
$c = pc_line_calc(ops_one("SELECT * FROM project_costing_lines WHERE id=?", [$lid]), $h);
t_eq([(float) $hr['est_cost_per_person'], (float) $hr['est_duration_months'], (float) $hr['est_onetime_cost']], [(float) $c['loaded'], 12.0, 5000.0],
     'it carries the budget: loaded cost per person, 12 months each, the one-off');
t_ok(strpos((string) $hr['project_ref'], $tag . '-Q-R1') !== false, 'and names the accepted quotation and the costing');
[$ok2, $msg2] = pc_raise_hiring_request($cid, $lid);
t_ok(!$ok2 && strpos($msg2, 'already asked for') !== false, 'a role is never asked for more people than were costed');

// Rejected: the people may be asked for again.
db()->prepare("UPDATE hiring_requests SET status='REJECTED' WHERE id=?")->execute([$hid]);
t_eq(pc_line_raised($lid), 0, 'a rejected request no longer counts');
db()->prepare("UPDATE hiring_requests SET status='APPROVED', decided_by='probe', decided_at=? WHERE id=?")->execute([date('c'), $hid]);

// Approved → requisition, with the costing's budget.
[$ok, $msg, $rid] = hreq_to_requisition($hid);
t_ok($ok && $rid > 0, 'the approved request becomes a requisition: ' . $msg);
$rq = ops_one("SELECT * FROM requisitions WHERE id=?", [$rid]);
t_eq([(float) $rq['billing_rate'], (float) $rq['target_margin'], (int) $rq['costing_line_id'], (float) $rq['cost_wage']],
     [(float) $c['proposed'], 20.0, $lid, 40000.0], 'the requisition takes the costing\'s billing rate, target margin and salary as its budget');

// Costing page lists what was raised.
$lk = pc_line_links($lid);
t_ok(count($lk['hreq']) === 1 && count($lk['req']) === 1, 'the costing shows the hiring request and the requisition raised from the role');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
t_ok(strpos($v, "\$gate === '' && \$done < \$ppl") !== false, 'the buttons are offered only when the gate is open and people are left');
t_ok(strpos($v, 'name="headcount"') !== false, 'each role has its number of people');

db()->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rid]);
db()->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$hid]);
db()->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$cid]);
db()->prepare("DELETE FROM project_costings WHERE id=?")->execute([$cid]);
db()->prepare("DELETE FROM quotations WHERE id=? OR parent_id=?")->execute([$qid, $qid]);
t_as_nobody();
