<?php
// Owner's notes of 9 Nov 2026 — recruitment interviews, dropping a candidate,
// joining into a department, and the company letterhead on every document.
t_as_admin();
recruit_iv_migrate();
$tag = 'ORD' . getmypid();
$day = fn($n, $t = '10:00') => date('Y-m-d', strtotime("+$n days")) . 'T' . $t;

t_section('Interviews — a booking needs a time, an interviewer and a place');
db()->prepare("INSERT INTO candidates (first_name,last_name,stage,department,created_at) VALUES (?,?,?,?,?)")->execute([$tag, 'Cand', 'INTERVIEW', 'Quality', date('c')]);
$c = (int) db()->lastInsertId();
$base = ['round' => 'L1', 'mode' => 'In person', 'location' => 'Head office', 'scheduled_at' => $day(3), 'panel_users' => [1]];
t_eq(iv_schedule($c, ['scheduled_at' => ''] + $base), 0, 'no date → refused');
t_ok(stripos(iv_last_error(), 'date and time') !== false, '… "Choose the date and time"');
t_eq(iv_schedule($c, ['panel_users' => []] + $base), 0, 'nobody to take it → refused');
t_ok(stripos(iv_last_error(), 'who will take') !== false, '… "Choose who will take the interview"');
t_eq(iv_schedule($c, ['location' => ''] + $base), 0, 'in person with no place → refused');
t_ok(iv_schedule($c, ['mode' => 'Phone', 'location' => '', 'round' => 'HR', 'scheduled_at' => $day(1)] + $base) > 0, 'a phone call needs no place');
$l1 = iv_schedule($c, $base);
t_ok($l1 > 0, 'a complete booking is saved, with several interviewers allowed');
t_eq(iv_schedule($c, ['round' => 'Technical'] + $base), 0, 'the same interviewer cannot be booked twice at the same time');

t_section('Interviews — L2 only after L1 is complete');
$l2p = ['round' => 'L2', 'scheduled_at' => $day(6)] + $base;
t_eq(iv_schedule($c, $l2p), 0, 'L2 is refused while L1 has no result');
t_ok(strpos(iv_last_error(), 'L1 has no result yet') !== false, '… and says L1 comes first');
t_eq(iv_schedule($c, $l2p + ['reason' => 'skip please']), 0, '… a reason does not override a level still waiting');

t_section('Interviews — results are kept, changes are explained');
[$ok, $m] = iv_record($l1, ['result' => 'PASS', 'recommendation' => 'No hire']);
t_ok(!$ok, 'a Pass with "No hire" is refused as contradictory: ' . $m);
[$ok] = iv_record($l1, ['result' => 'PASS', 'rating' => 4, 'recommendation' => 'Strong hire']);
t_ok($ok, 'L1 recorded: Pass / Strong hire');
[$ok, $m] = iv_record($l1, ['result' => 'FAIL', 'recommendation' => 'No hire']);
t_ok(!$ok && stripos($m, 'why') !== false, 'changing a recorded result needs a reason');
[$ok, $m] = iv_record($l1, ['result' => 'SCHEDULED']);
t_ok(!$ok && stripos($m, 're-interview') !== false, 'a result can never be put back to "Scheduled" — book a re-interview');
[$ok, $m] = iv_delete($l1);
t_ok(!$ok, 'an interview that took place cannot be deleted');
t_eq(iv_schedule($c, $base + ['reinterview' => 1]), 0, 'a re-interview needs its reason');

t_section('Interviews — L1 says hire, L2 says no hire');
$l2 = iv_schedule($c, $l2p);
t_ok($l2 > 0, 'with L1 complete, L2 can be booked');
iv_record($l2, ['result' => 'FAIL', 'rating' => 2, 'recommendation' => 'No hire']);
$ov = iv_overall($c);
t_ok($ov['conflict'], 'the disagreement is recognised as a conflict');
t_eq($ov['deciding']['round'] ?? '', 'L2', 'the higher level decides');
t_eq($ov['suggest'], 'REJECTED', 'so the suggested next step is Reject');
t_ok(stripos($ov['why'], 'tie-break') !== false, '… with the tie-break and re-interview options spelt out');
$l3 = iv_schedule($c, ['round' => 'L3', 'scheduled_at' => $day(9)] + $base);
t_eq($l3, 0, 'L3 after a failed L2 needs a reason');
$l3 = iv_schedule($c, ['round' => 'L3', 'scheduled_at' => $day(9), 'reason' => 'tie-break: L1 and L2 disagree'] + $base);
t_ok($l3 > 0, 'with a reason, a tie-break L3 can be held');
iv_record($l3, ['result' => 'PASS', 'rating' => 4, 'recommendation' => 'Hire']);
t_eq(iv_overall($c)['suggest'], 'OFFERED', 'and the highest level held (L3) then decides');

t_section('Dropping a candidate needs a reason; a hire who never starts is closed');
$ops = (string) file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "Say why this candidate is being dropped") !== false, 'a drop with no reason and no remark is refused');
t_ok(strpos($ops, "'Did not join — '") !== false && strpos($ops, 'WF_ST_JOINING, WF_ST_INACTIVE') !== false, 'a hire who did not join has the waiting team record closed');
$cd = (string) file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
t_ok(strpos($cd, 'They did not join…') !== false && strpos($cd, 'Expected date of joining') !== false, 'the joining panel shows the expected date and a "did not join" action');

t_section('Joining places the person in their department');
t_eq(candidate_join_department(['department' => 'Quality']), dept_canon('Quality'), 'the department they were recruited for');
db()->prepare("INSERT INTO inspectors (name, status, department) VALUES (?, 'ACTIVE', 'Quality')")->execute([$tag . ' Joined']);
$ins = (int) db()->lastInsertId();
$hub = dept_hub();
$q = array_values(array_filter($hub['depts'], fn($d) => $d['department'] === dept_canon('Quality')));
t_ok($q && $q[0]['people'] >= 1, 'the department hub counts a joined team member without a login');

t_section('Every document carries the company letterhead');
$keep = [];
foreach (['company_legal_name', 'company_address', 'company_gstin', 'quote_lh_logo', 'quote_lh_name', 'company_email'] as $k) $keep[$k] = setting_get($k, '');
setting_set('company_legal_name', 'Probe Inspection Pvt Ltd'); setting_set('company_address', '12 Ring Road, Pune');
setting_set('company_gstin', '27ABCDE1234F1Z5'); setting_set('company_email', 'ops@probe.example');
setting_set('quote_lh_name', ''); setting_set('quote_lh_logo', base64_encode("\xFF\xD8\xFF\xE0probe"));
$id = doc_identity();
t_eq($id['name'], 'Probe Inspection Pvt Ltd', 'the company profile name is used');
t_ok(strpos($id['logo'], 'data:image/jpeg;base64,') === 0, 'with the logo');
$lh = doc_letterhead_html(['pan' => true]);
t_ok(strpos($lh, 'Probe Inspection Pvt Ltd') !== false && strpos($lh, '<img') !== false && strpos($lh, '27ABCDE1234F1Z5') !== false && strpos($lh, 'ops@probe.example') !== false,
     'the letterhead shows logo, name, address, contact and GSTIN');
t_eq(quote_letterhead()['name'], 'Probe Inspection Pvt Ltd', 'PDFs (quotation, reports) fall back to the company profile too');
t_eq(quote_letterhead()['address'], '12 Ring Road, Pune', '… including the address');
foreach (['views/ops/invoice_print.php' => 'the tax invoice', 'views/ops/project_costing_print.php' => 'the costing sheet', 'views/ops/voucher_print.php' => 'the expense voucher'] as $f => $what)
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../' . $f), 'doc_letterhead_html(') !== false, "$what prints the letterhead");
t_ok(strpos((string) file_get_contents(__DIR__ . '/../views/ops/voucher_print.php'), 'AHMEDABAD') === false, 'the voucher no longer prints a hard-coded city');
t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/recruit_offer.php'), 'doc_letterhead_html()') !== false, 'the offer letter prints on the letterhead');
doc_tpl_migrate();
$r = doc_render_template(['body' => 'Dear {name}, welcome to {company}.'], ['id' => $c, 'first_name' => 'Asha', 'last_name' => 'K']);
t_ok(strpos($r['html'], 'doc-letterhead') !== false && strpos($r['html'], '<img') !== false, 'appointment and other letters carry the letterhead with the logo');
t_ok(strpos($r['html'], 'welcome to Probe Inspection Pvt Ltd') !== false, '{company} is the company\'s own name');
foreach ($keep as $k => $v) setting_set($k, $v);

// Leave nothing behind.
foreach ([["DELETE FROM interviews WHERE candidate_id=?", [$c]], ["DELETE FROM candidates WHERE id=?", [$c]], ["DELETE FROM inspectors WHERE id=?", [$ins]]] as [$sql, $a])
    try { db()->prepare($sql)->execute($a); } catch (Throwable $e) {}
