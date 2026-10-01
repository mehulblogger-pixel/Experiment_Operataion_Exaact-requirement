<?php
// ---------------------------------------------------------------------------
//  GATE 3 — seed the browser scenario.
//
//  Builds, in the running workspace, the exact situation a reviewer meets:
//  an approved requirement with five live candidates spanning the suitability
//  spectrum, one of them holding an ISSUED offer, and then a stricter approved
//  version. Prints the ids the browser check needs.
//
//    php tools/g3-seed.php
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
//  Require exactly what the front controller requires, discovered from it — the
//  same bootstrap every other seeder in this folder uses, so this command and the
//  app can never load a different set of libraries.
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$s = 'G3UI';
rver_migrate(); hreq_migrate(); appr_migrate(); recruitpipe_migrate(); rkpi_migrate(); crev_migrate();
$pdo = db();

//  Act as the installation's administrator, so the seed has the authority a real
//  administrator would. Nothing here grants itself anything.
$admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
if (!$admin) { fwrite(STDERR, "g3-seed: no active administrator to act as\n"); exit(2); }
$_SESSION['uid'] = (int) $admin['id']; current_user(true); ua(true);

$off = (int) ops_val("SELECT COALESCE(home_office_id,0) FROM users WHERE id=?", [(int) $admin['id']]);
if ($off <= 0) $off = (int) ops_val("SELECT MIN(id) FROM offices");
$eng = dept_of('Engineering'); $qua = dept_of('Quality');

$post = [
    'requested_by_id' => (int) $admin['id'], 'requested_by_name' => 'G3 Seed',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'G3 Review Scenario Engineer', 'designation' => 'ENGINEER',
    'job_description' => 'Gate 3 browser scenario', 'quantity' => 6, 'office_id' => $off,
    'required_by' => date('Y-m-d', strtotime('+120 days')),
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
    'reason' => 'Gate 3 scenario.', 'min_experience_years' => 2, 'change_reason' => 'seed',
];
[$ok,, $hid] = hreq_save(0, $post);
if (!$ok) { fwrite(STDERR, "g3-seed: could not raise the hiring request\n"); exit(2); }
hreq_submit($hid); hreq_apply_decision($hid, 'APPROVED', 'G3 Approver', 'seeded');

[$rok,, $rq] = hreq_to_requisition($hid, 5);
if (!$rok) { fwrite(STDERR, "g3-seed: could not raise the requirement\n"); exit(2); }
$pdo->prepare("UPDATE requisitions SET min_experience_years=5 WHERE id=?")->execute([$rq]);
rver_ensure_initial('REQUISITION', (int) $rq, null, ['note' => 'G3 scenario baseline']);

$mk = function ($first, $exp) use ($pdo, $s, $rq) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,
                                           experience_years,mobile,created_at)
                   VALUES (?,?,'Reviewcase','RECEIVED',?,?,?,?)")
        ->execute([$s . '-' . strtoupper(substr($first, 0, 3)) . random_int(10, 99), $first,
                   (int) $rq, $exp, '90000' . random_int(10000, 99999), date('c')]);
    return (int) $pdo->lastInsertId();
};
$strong = $mk('Strong', 22);
$mid    = $mk('Middling', 11);
$weak   = $mk('Weak', 2);
$blank  = $mk('Unstated', 0);
$offered = $mk('Promised', 20);

//  The promised candidate holds an ISSUED offer, so a later version must not reach
//  them. Everything else about them is identical to the others.
$pipe = recruitpipe_for(ops_one("SELECT * FROM requisitions WHERE id=?", [$rq]));
$goto = function ($cid, $key) use ($pipe, $rq) {
    $req = ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $rq]) ?: [];
    foreach (recruitpipe_resolvable_stages((int) $pipe['id'], $req) as $sg)
        if (strcasecmp((string) $sg['stage_key'], $key) === 0) {
            db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=? WHERE id=?")
                ->execute([(int) $sg['pipeline_id'], (int) $sg['id'], (int) $cid]);
            rkpi_stage_log((int) $cid, '', (string) $sg['name'],
                ['to_code' => (string) $sg['stage_key'], 'track' => 'PIPELINE', 'kind' => 'MOVE', 'actor' => 'G3 Seed']);
            return true;
        }
    return false;
};
$goto($mid, 'HOD_SHORTLIST');
$goto($weak, 'L1');
$goto($offered, 'OFFER');
$oid = (int) offer_create($offered, ['ctc' => 900000, 'joining_date' => date('Y-m-d', strtotime('+45 days'))]);
if ($oid > 0) { offer_submit($oid); offer_approve($oid, 'G3 Approver', 'seeded'); offer_issue($oid); }

//  AND NOW THE CHANGE: the approved minimum rises from 5 years to 12.
rver_gate_requisition_edit((int) $rq, ['min_experience_years' => 12],
    ['change_reason' => 'Client raised the site competency bar after the pre-award audit.']);
$p = rver_pending('REQUISITION', (int) $rq);
if ($p) rver_apply((int) $p['id'], ['decided_by' => 'G3 Approver', 'note' => 'Agreed with the client.']);

//  A candidate already closed before the change, so the screen can show that a
//  relaxed-or-later change does not reopen anybody.
$closed = $mk('Turneddown', 4);
$goto($closed, 'L1');
$ct = rpipe_stage_for_legacy_target(ops_one("SELECT * FROM candidates WHERE id=?", [$closed]), 'REJECTED');
if ($ct) {
    $pdo->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at=? WHERE id=?")
        ->execute([(int) $ct['pipeline_id'], (int) $ct['id'], date('c'), $closed]);
    rkpi_stage_log($closed, 'L1 Interview', (string) $ct['name'],
        ['from_code' => 'L1', 'to_code' => (string) ($ct['stage_key'] ?? ''), 'track' => 'PIPELINE',
         'kind' => 'MOVE', 'actor' => 'G3 Seed']);
}

//  WHICH candidates ended up in review, by name. A seeder that silently produced a
//  different scenario on different runs would make every browser result unreadable.
$who = [];
foreach (ops_all("SELECT c.first_name, cr.status FROM candidates c
                  LEFT JOIN candidate_reviews cr ON cr.candidate_id=c.id AND cr.status='OPEN'
                  WHERE c.requisition_id=? ORDER BY c.id", [$rq]) ?: [] as $r)
    $who[] = $r['first_name'] . '=' . ($r['status'] ?: '-');
fwrite(STDERR, "  reviews: " . implode(' ', $who) . "\n");

printf("REQ=%d HID=%d STRONG=%d MID=%d WEAK=%d BLANK=%d OFFERED=%d CLOSED=%d VER=%d OPEN=%d\n",
    $rq, $hid, $strong, $mid, $weak, $blank, $offered, $closed,
    (int) rver_current('REQUISITION', $rq)['version'],
    (int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE requisition_id=? AND status='OPEN'", [$rq]));
