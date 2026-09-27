<?php
// ============================================================================
//  A BRANCH MAY NOT REWRITE ANOTHER BRANCH'S CLIENT, NOR READ ITS BILL.
//
//  Four findings from one testing session, in the owner's words:
//
//    "When Tested the coordinator is able to edit the client details associated
//     with the ahmedabad office. Wherein coordinator is from Mumbai so this must
//     not be possible."
//    "Invoice value and bill value if call allocated to other office shall not be
//     seen at any cost by the other office. Yes they can check the credit they
//     will get. Nothing else."
//    "Once reassigned it cannot be again reassigned or another update cannot be
//     done."
//
//  And the owner's ruling on how far the boundary goes:
//    "Client basic details can be seen else then the contact and commercial parts."
//
//  The registers were scoped long ago. What was never scoped was the client
//  RECORD and the reports TOTALS — so the boundary held on the lists everybody
//  looks at and failed on the two screens nobody thought to check. Proved on the
//  live database before this file was written: a Mumbai-only coordinator renamed
//  an Ahmedabad client and set its credit terms to 999 days.
//
//  Reading is a spectrum; writing is not. A branch may see WHO another branch's
//  client is, because the same company is often worked on by two branches and a
//  coordinator arranging a visit has to know who they are dealing with. It may
//  never see their contact details or their commercial terms, and it may never
//  change anything at all.
// ============================================================================

t_section('The office boundary on a client record, and on the money');

t_ok(function_exists('partner_view_level'), 'ARMING · the read rule exists');
t_ok(function_exists('partner_can_write'),  'ARMING · the write rule exists, separately');
t_ok(function_exists('reports_revenue_office'), 'ARMING · reports know whose books they are showing');

$pdo = db();
$uid = t_as_admin();

$office = function ($name, $code) use ($pdo) {
    $ex = ops_one("SELECT id FROM offices WHERE name=?", [$name]);
    if ($ex) return (int) $ex['id'];
    $pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES (?,?,1)")->execute([$name, $code]);
    return (int) $pdo->lastInsertId();
};
$ahm = $office('ZZB Ahmedabad', 'ZZBA');
$mum = $office('ZZB Mumbai', 'ZZBM');

$mkClient = function ($name, $branch) use ($pdo) {
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,home_branch_id,is_client,status,created_at)
                   VALUES (?,?,?,?,1,'ACTIVE',?)")
        ->execute(['ZB' . mt_rand(100000, 999999), $name, $name, $branch, date('c')]);
    return (int) $pdo->lastInsertId();
};
$ahmClient  = $mkClient('ZZB Ahmedabad Client', $ahm);
$mumClient  = $mkClient('ZZB Mumbai Client', $mum);
$noneClient = $mkClient('ZZB Unassigned Client', null);

$mkUser = function ($username, $role, $office) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,home_office_id,scope_offices)
                   VALUES (?,?,'ZZB',?,?,0,1,?,?)")
        ->execute([$username, password_hash('x' . bin2hex(random_bytes(6)), PASSWORD_BCRYPT),
                   $username, $role, $office, (string) $office]);
    return (int) $pdo->lastInsertId();
};
$mumCoordId = $mkUser('zzb_mum_coord', 'COORDINATOR', $mum);
$mumBmId    = $mkUser('zzb_mum_bm', 'BRANCH_MANAGER', $mum);
$ahmCoordId = $mkUser('zzb_ahm_coord', 'COORDINATOR', $ahm);

$becomes = function ($id) {
    $_SESSION['uid'] = $id;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
};

// ============================================================================
//  1 — WHAT A BRANCH MAY SEE OF ANOTHER BRANCH'S CLIENT
// ============================================================================
t_section('Reading another office\'s client');

$becomes($mumCoordId);
t_ok(!is_master(), 'ARMING · these assertions run as a REAL Mumbai coordinator, not as the master');
t_eq(partner_view_level(ops_one("SELECT * FROM business_partners WHERE id=?", [$mumClient])), 'FULL',
    'its own office\'s client is read in full');
t_eq(partner_view_level(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient])), 'BASIC',
    'another office\'s client is read at basic level only');
t_eq(partner_view_level(ops_one("SELECT * FROM business_partners WHERE id=?", [$noneClient])), 'FULL',
    'a client belonging to NO office stays readable by everyone — the same rule the registers use, '
  . 'and what stops this change blanking every existing record on the day it ships');

// ============================================================================
//  2 — AND WHAT IT MAY CHANGE: NOTHING
//
//  This is the finding itself. Seeing and changing are separate questions, asked
//  by separate functions, because answering both with one flag is how a screen
//  that correctly hid a figure still accepted a write.
// ============================================================================
t_section('Writing another office\'s client');

t_ok(partner_can_write(ops_one("SELECT * FROM business_partners WHERE id=?", [$mumClient])),
    'its own office\'s client can be changed');
t_ok(!partner_can_write(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient])),
    'another office\'s client cannot be changed — the finding, closed');
t_ok(partner_can_write(ops_one("SELECT * FROM business_partners WHERE id=?", [$noneClient])),
    'an unassigned client can still be picked up by whoever gets to it');

$msg = partner_write_denied_msg(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient]));
t_ok(strpos($msg, 'ZZB Ahmedabad') !== false,
    'and the refusal NAMES the office to ask — a refusal that does not just sends somebody hunting');

// A branch manager is more senior, and it makes no difference: seniority is not
// a licence to rewrite another branch's commercial terms.
$becomes($mumBmId);
t_ok(!partner_can_write(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient])),
    'a branch MANAGER cannot either — this is a boundary between offices, not a rank');

// The owning office is untouched. Without this the "fix" could be a lock on everybody.
$becomes($ahmCoordId);
t_ok(partner_can_write(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient])),
    'ARMING · the OWNING office can still change its own client — the boundary is not a wall around everyone');
t_eq(partner_view_level(ops_one("SELECT * FROM business_partners WHERE id=?", [$ahmClient])), 'FULL',
    'and still reads it in full');

// ============================================================================
//  3 — EVERY DOOR ASKS, NOT JUST THE OBVIOUS ONE
//
//  The edit form was the reported door. The sub-forms write to the same record
//  and were equally open; the JSON endpoints hand out addresses and purchase
//  orders without rendering a page at all.
// ============================================================================
t_section('Every door, not just the reported one');

$idx = (string) @file_get_contents(dirname(__DIR__) . '/index.php');
t_eq(substr_count($idx, "ops_require(!function_exists('partner_can_write') || partner_can_write"), 2,
    'both index.php doors ask — the edit form AND every sub-form behind it');
t_ok(strpos($idx, "\$viewLevel = function_exists('partner_view_level')") !== false,
    'the client screen decides the level before it loads anything');
foreach ([['contacts', 'contact details'], ['addresses', 'site addresses'], ['contracts', 'contracts'],
          ['pos', 'purchase orders'], ['notes', 'internal notes']] as [$key, $what]) {
    t_ok(strpos($idx, "'$key' => \$pFull ?") !== false,
        "$what are not even loaded for another office — a template that forgets one line leaks, a handler that never reads the rows cannot");
}
t_ok(strpos($idx, 'call_office_clause') !== false,
    'and the work orders listed on a client follow the same two-office rule the register does');

$ops = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
t_eq(substr_count($ops, 'if (!partner_readable('), 2,
    'the two JSON endpoints that hand out addresses and purchase orders ask as well');

$c360 = (string) @file_get_contents(dirname(__DIR__) . '/lib/customer360.php');
t_ok(strpos($c360, 'partner_can_write') !== false,
    'and so does setting a client\'s parent, which decides whose group it belongs to');

$det = (string) @file_get_contents(dirname(__DIR__) . '/views/detail.php');
t_ok(strpos($det, '$pBasic') !== false, 'the screen knows it is showing a restricted record');
t_ok(strpos($det, 'Another office') !== false,
    'and says so — a screen that silently shows less looks broken; one that explains is a boundary people can work with');

// ============================================================================
//  4 — THE MONEY: ITS OWN CREDIT, AND NOT A RUPEE MORE
// ============================================================================
t_section('Cross-office money');

$pdo->prepare("INSERT INTO calls (call_code, client_id, ibo_office_id, executing_office_id, contracting_office_id,
                                  billable_value, expected_credit, status, created_at)
               VALUES ('ZZB-CALL-1',?,?,?,?, 900000, 70000, 'OPEN', ?)")
    ->execute([$ahmClient, $ahm, $mum, $ahm, date('c')]);
$callId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, contracting_office_id,
                                 expected_credit, credit_direction, invoice_value, invoice_amount, invoice_raised, created_at)
               VALUES ('ZZB-JOB-1',?,?,?, 70000, 'GIVEN', 900000, 900000, 1, ?)")
    ->execute([$callId, $mum, $ahm, date('c')]);
$jobId = (int) $pdo->lastInsertId();
$job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);

t_ok($job && (int)$job['executing_office_id'] === $mum && (int)$job['contracting_office_id'] === $ahm,
    'ARMING · Ahmedabad holds the order and bills 900,000; Mumbai carries the work out for a credit of 70,000');

$becomes($mumCoordId);
t_eq((int) reports_revenue_office($job), $mum, 'Mumbai\'s report reads Mumbai\'s books');
t_eq((float) job_revenue_for($job, reports_revenue_office($job)), 70000.0,
    'and Mumbai\'s revenue on this job is its CREDIT — 70,000, not the client\'s 900,000');

$becomes($ahmCoordId);
t_eq((int) reports_revenue_office($job), $ahm, 'Ahmedabad\'s report reads Ahmedabad\'s books');
t_eq((float) job_revenue_for($job, reports_revenue_office($job)), 830000.0,
    'and Ahmedabad keeps what it billed less what it pays away — 900,000 − 70,000');

// The two shares are the whole, and must never both be counted.
$becomes($mumCoordId); $mumShare = (float) job_revenue_for($job, reports_revenue_office($job));
$becomes($ahmCoordId); $ahmShare = (float) job_revenue_for($job, reports_revenue_office($job));
t_eq($mumShare + $ahmShare, 900000.0,
    'the two offices\' shares add up to the invoice exactly — no rupee is double-counted or lost');

t_as_admin();
t_eq(reports_revenue_office($job), null,
    'and a master reads the company figure, because that is the one they are responsible for');

t_ok(strpos($ops, '$p = job_profit($j, $revOff);') !== false,
    'the report passes the reader\'s office into the profit engine — which always accepted one, '
  . 'and was simply never asked');
// Behaviour, not source text: the earlier version of this assertion only looked
// for the variable's NAME, so deleting the guard that uses it still passed. What
// matters is that an executing-only office books no invoice and no payment.
$becomes($mumCoordId);
$mumOff = reports_revenue_office($job);
$mumHolds = ((int) ($job['contracting_office_id'] ?? 0) === (int) $mumOff)
         || ((int) ($job['contracting_office_id'] ?? 0) === 0);
t_ok(!$mumHolds,
    'Mumbai does not hold the invoice on this job — it is owed a credit, not paid by the client');
$becomes($ahmCoordId);
$ahmOff = reports_revenue_office($job);
t_ok(((int) $job['contracting_office_id']) === (int) $ahmOff,
    'ARMING · Ahmedabad does hold it, so the test is not just asserting "nobody ever holds an invoice"');
t_as_admin();
t_ok(strpos($ops, "if (\$holdsInvoice && !empty(\$j['invoice_raised']))") !== false
  && strpos($ops, "if (\$holdsInvoice && !empty(\$j['payment_received']))") !== false,
    'and both the invoiced and the paid totals are gated on holding it, not just one of them');

// ============================================================================
//  5 — "IT CANNOT BE REASSIGNED AGAIN"
//
//  It always could. The dropdown opened on the person ALREADY assigned, so
//  pressing Reassign answered "Choose a different resource to reassign to." —
//  which reads as a lock and was reported as one.
// ============================================================================
t_section('Reassign, and the lock that was not one');

$tos = (string) @file_get_contents(dirname(__DIR__) . '/lib/tosrm.php');
t_ok(strpos($tos, 'Choose who takes it over') !== false, 'the box now opens on nobody');
t_ok(strpos($tos, "if ((int)\$ip['id'] === \$curInsp) continue;") !== false,
    'and does not offer the person who already has it — reassigning to them is not a thing to do');
t_ok(strpos($tos, "<?=(int)\$ip['id']===\$curInsp?'selected':''?>") === false,
    'the pre-selection that caused the report is gone');
t_ok(strpos($tos, 'currently ') !== false,
    'who holds it is said beside the box instead, so nothing is lost by not pre-selecting them');

// The engine never had a second-reassignment rule, and must not acquire one.
t_ok(function_exists('tosrm_reassign'), 'the reassign engine exists');
$rt = new ReflectionFunction('tosrm_reassign');
$body = implode('', array_slice(file($rt->getFileName()), $rt->getStartLine() - 1,
                                $rt->getEndLine() - $rt->getStartLine() + 1));
t_ok(strpos($body, 'if ($old === $newInspectorId) return false;') !== false,
    'it still refuses only the no-op — reassigning somebody to themselves');
t_ok(strpos($body, 'REASSIGN') !== false, 'and every move is still written to the history');

// ============================================================================
//  6 — WHO MAY REOPEN A JOB THAT LOCKED ITSELF
//
//  Owner's decision: the branch manager, yes. The coordinator, still no — if the
//  person who missed the deadline can undo it, there is no deadline.
// ============================================================================
t_section('Reopening a locked job');

// The owner asked for the branch manager to be able to reopen a locked job. On
// reading the live role map they ALREADY could — BRANCH_MANAGER carries
// workforce.report.approve, which can_unlock_job() has always accepted. So there
// was nothing to build, and the list was left exactly as it was. This locks that
// in, so a future tidy-up of the role map cannot take it away silently.
$becomes($mumBmId);
t_ok(can_unlock_job(), 'a branch manager can reopen a locked job — already true, and now guarded');
t_ok(can('workforce.report.approve'),
    'and it is that permission doing it, not a role name hard-coded into the lock');
$becomes($mumCoordId);
t_ok(!can_unlock_job(),
    'a coordinator still cannot — the deadline has to mean something, and they are the one it is on');
t_as_admin();
t_ok(can_unlock_job(), 'ARMING · and the roles that could before still can');

$lockSrc = (string) @file_get_contents(dirname(__DIR__) . '/lib/joblock.php');
t_ok(strpos($lockSrc, "in_array(user_role(), ['BRANCH_MANAGER'") === false,
    'the lock asks for a PERMISSION, never for a role name — a role list here is how a '
  . 'right gets granted to a role nobody discussed');

// ---- clean up ---------------------------------------------------------------
t_nothrow('fixtures removed', function () use ($pdo) {
    $pdo->exec("DELETE FROM jobs WHERE job_code LIKE 'ZZB-JOB-%'");
    $pdo->exec("DELETE FROM calls WHERE call_code LIKE 'ZZB-CALL-%'");
    $pdo->exec("DELETE FROM business_partners WHERE legal_name LIKE 'ZZB %'");
    $pdo->exec("DELETE FROM users WHERE username LIKE 'zzb_%'");
    $pdo->exec("DELETE FROM offices WHERE name LIKE 'ZZB %'");
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name LIKE 'ZZB %'"), 0, 'no test clients remain');
    t_as_nobody();
    t_ok(true, 'session restored for the rest of the suite');
});
