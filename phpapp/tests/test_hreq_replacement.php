<?php
// ---------------------------------------------------------------------------
//  UAT A1 — A REPLACEMENT REQUEST SAID NOTHING ABOUT WHO WAS LEAVING.
//
//    "if there is a replacement then we must be able to add the left engineer
//     details like name, department, reporting manager, revenue he was
//     generating, automatically fetching his month salary and other details
//     which hiring request approver must know. Research it well and make it
//     more detailed."
//
//  An approver was shown "Inspector x 1" and had no way to tell a like-for-like
//  backfill from a quiet upgrade, nor to ask the question that actually decides
//  a backfill: was the seat earning its keep?
//
//  The design rule: the requester names WHO, WHEN and WHY; everything the system
//  already knows is FETCHED. A cost typed by hand into a request is a figure
//  nobody can audit, and the person raising a backfill is rarely the one who
//  knows it.
// ---------------------------------------------------------------------------
t_section('A1 — who is being replaced');
t_as_admin();
hreq_migrate();

t_ok(function_exists('hreq_is_replacement'), 'the replacement request types are named in one place');
t_ok(hreq_is_replacement('REPLACEMENT'), 'a replacement is one');
t_ok(hreq_is_replacement('BACKFILL'),
    '*** and so is a backfill — covering somebody asks the same questions');
foreach (['NEW', 'PROJECT', 'ADDITIONAL_MANPOWER', ''] as $t)
    t_ok(!hreq_is_replacement($t), "'$t' is not a replacement, so the block stays hidden");

// ---- a leaver with a salary, a manager and some billed work ---------------
db()->prepare("INSERT INTO inspectors (name,emp_code,designation,sbu,salary_ctc,status,created_at)
               VALUES (?,?,?,?,?,?,?)")
    ->execute(['ZZ Leaver', 'ZZ-900', 'Senior Inspector', 'Quality', 85000, 'ACTIVE', '2024-01-01']);
$lv = (int) db()->lastInsertId();
db()->prepare("INSERT INTO inspectors (name,designation,status,created_at) VALUES (?,?,?,?)")
    ->execute(['ZZ Manager', 'Operation Manager', 'ACTIVE', '2024-01-01']);
$mg = (int) db()->lastInsertId();
db()->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$mg, $lv]);
foreach ([120000, 95000, 140000] as $k => $val)
    db()->prepare("INSERT INTO jobs (inspector_id,invoice_value,scheduled_date,created_at) VALUES (?,?,?,?)")
        ->execute([$lv, $val, date('Y-m-d', strtotime('-' . (30 * ($k + 1)) . ' day')), date('c')]);

// ---- everything is fetched, nothing re-typed ------------------------------
$snap = hreq_leaver_snapshot($lv);
t_eq($snap['name'], 'ZZ Leaver', 'the name is fetched');
t_eq($snap['designation'], 'Senior Inspector', '*** and the designation');
t_eq($snap['department'], 'Quality', '*** and the department');
t_eq($snap['reports_to'], 'ZZ Manager', '*** and the reporting manager — who feels the gap');
t_eq((float) ($snap['monthly_cost'] ?? 0), 85000.0, '*** and the monthly cost, from their own record');
t_eq((float) ($snap['revenue_12m'] ?? -1), 355000.0,
    '*** and what they billed over 12 months, from the job book — not typed by hand');
t_eq((int) ($snap['jobs_12m'] ?? 0), 3, '*** across the right number of jobs');
t_ok(($snap['taken_at'] ?? '') !== '', 'and the snapshot records when it was taken');

//  Billing is a WINDOW, not a lifetime: an old job must not inflate the case.
db()->prepare("INSERT INTO jobs (inspector_id,invoice_value,scheduled_date,created_at) VALUES (?,?,?,?)")
    ->execute([$lv, 999999, date('Y-m-d', strtotime('-3 year')), date('c')]);
$snap2 = hreq_leaver_snapshot($lv);
t_eq((float) ($snap2['revenue_12m'] ?? -1), 355000.0,
    '*** a job from three years ago is not counted as this year\'s billing');

// ---- nothing is invented when there is nothing to show --------------------
$empty = hreq_leaver_snapshot(99999);
t_eq($empty, [], 'an unknown person yields nothing at all, rather than a row of zeros');
//  TWO DIFFERENT NOTHINGS, and the first draft of this assertion conflated
//  them. "Nobody was named" cannot be computed, so it is null. "This person
//  billed nothing in twelve months" IS computed, and 0 is the true answer —
//  returning null there would hide a real and quite damning fact from an
//  approver. The snapshot refuses an unknown person outright, so a zero from a
//  bad id never reaches a screen.
$bNone = hreq_leaver_billing(0);
t_eq($bNone['revenue'], null,
    '*** with nobody named, billing is null — a screen can say "not known" rather than a confident 0');
$bUnknown = hreq_leaver_billing(99999);
t_eq((float) $bUnknown['revenue'], 0.0, 'a person with no jobs billed 0, which is the true answer');
t_eq(hreq_leaver_snapshot(99999), [], '*** and an unknown person yields no snapshot at all, so that 0 is never printed');

// ---- MONEY OBEYS THE MATRIX ------------------------------------------------
//  The keys are ABSENT, not zero, for a viewer who may not see them — so a
//  snapshot can never leak a figure to a screen that would happily print it.
$src = (string) file_get_contents(__DIR__ . '/../lib/hiringreq.php');
t_ok(strpos($src, "can('data.salary')") !== false,
    '*** the salary line is gated on data.salary, which the matrix already defines');
t_ok(strpos($src, 'can_see_revenue()') !== false,
    '*** and the billing line on the existing revenue right');
//  SET INSIDE THE GUARD, never set-then-blanked: an unset key cannot be leaked
//  by a renderer that forgets to check, which a blanked one can.
t_ok(preg_match('/if \(\$maySalary\b.*?\)\s*\$out\[\x27monthly_cost\x27\]/s', $src) === 1
     || preg_match("/if \(\\\$maySalary[^\n]*\n\s*\\\$out\['monthly_cost'\]/", $src) === 1,
    '*** the salary key is only SET when allowed — not set-then-blanked');
t_ok(!preg_match("/\\\$out\['monthly_cost'\]\s*=\s*''/", $src),
    '*** and never written as an empty value, which a screen would print as a figure of nothing');

$html = hreq_leaver_facts_html($snap);
t_ok(strpos($html, 'ZZ Manager') !== false, 'the shared renderer prints the reporting line');
t_ok(strpos($html, '85,000') !== false, 'and the cost, for a viewer who may see it');
$noMoney = $snap; unset($noMoney['monthly_cost'], $noMoney['revenue_12m'], $noMoney['jobs_12m']);
$html2 = hreq_leaver_facts_html($noMoney);
t_ok(strpos($html2, '85,000') === false, '*** and prints no figures when the snapshot holds none');
t_ok(stripos($html2, 'not yours to see') !== false,
    '*** saying so plainly, rather than printing a zero that reads as "they billed nothing"');

// ---- the snapshot is STAMPED, so the approver's case cannot be rewritten ---
t_ok(strpos($src, "'replacing_snapshot_json'   => \$replSnap") !== false,
    'the fetched figures are stored with the request');
t_ok(strpos($src, '$wasSame = $existing') !== false,
    '*** and are not re-taken when the request is merely edited — only when the person changes');

// ---- changing the type away from a replacement clears it ------------------
t_ok(strpos($src, '$replId = hreq_is_replacement($rtype) ? (int) ($post[\'replacing_inspector_id\'] ?? 0) : 0;') !== false,
    '*** switching a request to "new position" cannot leave a leaver attached to it');

// ---- the approver actually sees it ----------------------------------------
$v = (string) file_get_contents(__DIR__ . '/../views/ops/hiring_request.php');
t_ok(strpos($v, '<tr><th>Replacing</th>') !== false, 'the read-only view shows who is being replaced');
t_ok(strpos($v, 'Is the seat still needed?') !== false,
    '*** and asks the question that decides a backfill');
t_ok(strpos($v, 'hreq_leaver_stamped($r)') !== false,
    '*** rendering the STAMPED figures, not a fresh lookup that could differ');
t_as_nobody();
