<?php
// Part 3 (owner, 9 Nov 2026) — "the rates of the candidates who are hired shall
// meet the margin as well as costing" — "Last candidate actual comparison. One at
// a time." A candidate's actual CTC replaces the costed salary + statutory; the
// margin is measured at the rate quoted to the client.
t_section('A candidate measured against the costing of their role');
t_as_admin();
pc_migrate(); pc_link_migrate(); ch_migrate(); recruit_offer_migrate();
if (function_exists('req_migrate')) req_migrate();
$tag = 'CCC' . getmypid();

// Role: Basic 18,000 (salary), PF 12% capped at 15,000 (= 1,800, statutory),
// food 6,500 (other). Overhead 10%, target margin 20%, monthly sheet.
db()->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, created_at) VALUES (?, 'Margin probe', 'LONG', 'APPROVED', 10, 0, 0, 0, 0, ?)")
    ->execute([$tag, date('c')]);
$pc = (int) db()->lastInsertId();
[$items] = ch_items_clean([
    ['label' => 'Basic', 'code' => 'BASIC', 'grp' => 'PAY', 'calc' => '18000'],
    ['label' => 'PF', 'code' => 'PF', 'grp' => 'STAT', 'type' => 'PCT', 'pct' => 12, 'of' => ['BASIC'], 'cap' => 15000],
    ['label' => 'Food', 'code' => 'FOOD', 'grp' => 'COST', 'calc' => '6500'],
]);
db()->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, rate_manual, oneoff, headcount, spec, created_at) VALUES (?,1,'Technician','{}',12,20,0,0,1,?,?)")
    ->execute([$pc, json_encode(['v' => 1, 'dpm' => 26, 'items' => $items]), date('c')]);
$lid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO requisitions (req_code, designation, status, quantity, costing_line_id, created_at) VALUES (?, 'Technician', 'OPEN', 1, ?, ?)")->execute([$tag . '-R', $lid, date('c')]);
$rq = (int) db()->lastInsertId();
db()->prepare("INSERT INTO candidates (first_name, last_name, stage, requisition_id, created_at) VALUES (?, 'Probe', 'OFFERED', ?, ?)")->execute([$tag, $rq, date('c')]);
$cid = (int) db()->lastInsertId();
$cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);

$k = pc_cand_check($cand);
t_ok($k !== null, 'the candidate is traced to the costing role through their requirement');
t_eq([$k['costed_pay'], $k['rate'], $k['target']], [19800.0, 36162.5, 20.0], 'costed salary + statutory 19,800; billed 36,162.50 a man-month; target 20%');
t_eq($k['verdict'], 'NONE', 'with no offer or salary structure yet, there is nothing to compare');
t_eq($k['max_ctc'], 19800.0, '… but the highest CTC that keeps the target is already known: 19,800');

$ok = pc_cand_check($cand, 19800);
t_eq([$ok['margin'], $ok['verdict']], [20.0, 'OK'], 'a CTC of 19,800 keeps exactly the 20% target');
$low = pc_cand_check($cand, 22000);
t_eq($low['verdict'], 'LOW', 'a CTC of 22,000 falls below the target');
t_eq($low['margin'], round((36162.5 - (6500 + 22000) * 1.1) / 36162.5 * 100, 2), '… its margin worked out at the quoted rate: ' . $low['margin'] . '%');
t_eq(pc_cand_check($cand, 40000)['verdict'], 'LOSS', 'a CTC that costs more than the client pays is a loss');
t_ok(strpos(pc_cand_verdict_text($low, true), 'up to') !== false, 'the screen says how much pay keeps the target');
t_ok(strpos(pc_cand_verdict_text($low, false), '₹') === false && strpos(pc_cand_verdict_text($low, false), '22,000') === false, '… and shows no pay at all to someone without salary access');

t_section('The salary structure, then the offer, are what is measured');
db()->prepare("INSERT INTO salary_structures (candidate_id, gross_ctc, created_at) VALUES (?, 21000, ?)")->execute([$cid, date('c')]);
t_eq([pc_cand_check($cand)['ctc'], pc_cand_check($cand)['source']], [21000.0, 'salary structure'], 'the salary structure is used when there is no offer');
db()->prepare("INSERT INTO job_offers (candidate_id, ctc, status, created_at) VALUES (?, 22000, 'DRAFT', ?)")->execute([$cid, date('c')]);
$oid = (int) db()->lastInsertId();
t_eq([pc_cand_check($cand)['ctc'], pc_cand_check($cand)['source']], [22000.0, 'offer'], 'the offer, once drafted, is what counts');
t_eq((int) (pc_line_last_candidate($lid)['id'] ?? 0), $cid, 'the costing page shows this person as the role\'s last candidate');

t_section('An offer below the target margin needs a reason to go for approval');
[$s1, $m1] = offer_submit($oid);
t_ok(!$s1 && strpos($m1, 'below its 20% target') !== false, 'refused without a reason: ' . $m1);
t_ok(strpos($m1, '19,800') !== false, '… and says what CTC keeps the target');
[$s2] = offer_submit($oid, 'Only candidate with the client\'s clearance; client agreed a rate review in month 3');
t_ok($s2, 'with a reason it goes for approval');
t_eq((string) ops_val("SELECT margin_note FROM job_offers WHERE id=?", [$oid]), 'Only candidate with the client\'s clearance; client agreed a rate review in month 3', 'and the reason stays on the offer for the approver');

foreach ([["DELETE FROM job_offers WHERE candidate_id=?", [$cid]], ["DELETE FROM salary_structures WHERE candidate_id=?", [$cid]],
          ["DELETE FROM candidates WHERE id=?", [$cid]], ["DELETE FROM requisitions WHERE id=?", [$rq]],
          ["DELETE FROM project_costing_lines WHERE id=?", [$lid]], ["DELETE FROM project_costings WHERE id=?", [$pc]]] as [$sql, $a])
    try { db()->prepare($sql)->execute($a); } catch (Throwable $e) {}
