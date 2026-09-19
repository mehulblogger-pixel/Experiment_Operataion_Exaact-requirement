<?php
// ============================================================================
//  PHASE 6 · BATCH 2 — Person relationship integrity & conversion safety.
//
//  Written BEFORE the implementation, against the code as it stood, so every
//  assertion describes the REQUIRED behaviour rather than whatever the new code
//  happens to do. The baseline run is recorded in P6-BATCH2-TEST-RESULTS.md.
//
//  Everything is read back from the DATABASE. A return code on its own is never
//  evidence: a conversion that returns "ok" and wrote nothing, or returns
//  "failed" after committing, would pass a return-code test and fail these.
//
//  Sections
//    A  conversion: transaction, uniqueness, concurrency   BD3 · I30 · I22 · I42
//    B  branch resolution: requisition → recruiter → refuse BD1
//    C  Connect ON / OFF — STATE A vs STATE B               BD2
//    D  scope, tenant, boundaries                           I15 · I16 · M4 · M5
//    E  the identity ledger and the resolver                R1 · R5
//    F  audit                                               I6
//    G  person groups: linking must never split             BD4 · I43
//    H  contradictory-state detection (report only)
//    W  structural: no undocumented second writer
// ============================================================================

$b2sess = $_SESSION;                 // restored at the very END of this file

$b2act  = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$b2user = function ($username, $role, $opt = []) {
    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,home_office_id,scope_offices,scope_sbus)
                   VALUES (?,?,?,?,?,?,?,1,?,?,?)")
        ->execute([$username, password_hash('x', PASSWORD_DEFAULT), $opt['first'] ?? 'P6B2', $opt['last'] ?? 'User',
                   $username . '@p6b2.test', $role, (int)($opt['su'] ?? 0),
                   $opt['office'] ?? null, $opt['scope_offices'] ?? '', $opt['scope_sbus'] ?? '']);
    return (int)db()->lastInsertId();
};
$b2cand = function ($first, $opt = []) {
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,sbu,stage,requisition_id,recruiter_id)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$first, $opt['last'] ?? 'Person', strtolower($first) . '@p6b2.test', $opt['mobile'] ?? '',
                   $opt['sbu'] ?? 'IND', $opt['stage'] ?? 'INTERVIEW',
                   $opt['requisition_id'] ?? null, $opt['recruiter_id'] ?? null]);
    return (int)db()->lastInsertId();
};
$b2req = function ($officeId, $opt = []) {
    db()->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$opt['code'] ?? ('P6B2-' . random_int(100000, 999999)), $opt['title'] ?? 'Inspector',
                   $officeId, $opt['sbu'] ?? 'IND', $opt['status'] ?? 'OPEN', $opt['quantity'] ?? 5, date('c')]);
    return (int)db()->lastInsertId();
};
$b2insp = function ($candId) { return (int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$candId]); };
$b2count = function ($like) { return (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE name LIKE ?", [$like]); };

$b2root = dirname(__DIR__);
$b2env = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
$b2one = function ($op, $a = 0, $b = '', $uid = 0) use ($b2root, $b2env) {
    $cmd = $b2env() . 'php ' . escapeshellarg($b2root . '/tests/_p6b2_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int)$a . ' ' . escapeshellarg((string)$b) . ' 0 ' . (int)$uid . ' 2>&1';
    foreach (explode("\n", trim((string)shell_exec($cmd))) as $line) {
        $j = json_decode(trim($line), true); if (is_array($j)) return $j;
    }
    return ['ok' => false, 'code' => 'NO_OUTPUT'];
};
// Launch several workers at once, all aiming at the SAME wall-clock microsecond.
$b2race = function (array $specs, $leadMs = 1100) use ($b2root, $b2env) {
    $target = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $b2env() . 'php ' . escapeshellarg($b2root . '/tests/_p6b2_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . (int)$s[1] . ' ' . escapeshellarg((string)($s[2] ?? '')) . ' '
             . escapeshellarg((string)$target) . ' ' . (int)($s[3] ?? 0) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim((string)$raw)) as $line) {
            $j = json_decode(trim($line), true); if (is_array($j)) { $out[] = $j; break; }
        }
    }
    return $out;
};

// ---- the cast ---------------------------------------------------------------
$uMaster = $b2user('p6b2.master', 'ADMIN', ['su' => 1, 'office' => 1]);
$uRecMum = $b2user('p6b2.recmum', 'COORDINATOR', ['office' => 2, 'scope_offices' => 'ALL', 'scope_sbus' => 'ALL']);
$uNoOff  = $b2user('p6b2.nooffice', 'COORDINATOR', ['office' => null, 'scope_offices' => 'ALL', 'scope_sbus' => 'ALL']);
$b2act($uMaster);
$reqMumbai = $b2req(2);              // a requirement raised for Mumbai
$reqNoOff  = $b2req(null);           // a requirement with no branch at all

// =============================================================================
t_section('P6-B2 · A — one candidate, one conversion (BD3 · I30 · I22 · I42)');
// =============================================================================
$aCand = $b2cand('RaceOne', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uRecMum]);
$aBefore = $b2count('RaceOne%');
$aRes = $b2race([['route_convert', $aCand, '', $uMaster],
                 ['route_convert', $aCand, '', $uMaster],
                 ['route_convert', $aCand, '', $uMaster]]);
t_eq($b2count('RaceOne%') - $aBefore, 1, 'A1 · three simultaneous conversions created exactly ONE team member');
$aIns = $b2insp($aCand);
t_ok($aIns > 0, 'A2 · the candidate is linked to that team member');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors i WHERE i.name LIKE 'RaceOne%'
                     AND NOT EXISTS (SELECT 1 FROM candidates c WHERE c.inspector_id=i.id)"), 0,
     'A3 · no orphan team member was left by the processes that lost');
t_eq(count($aRes), 3, 'A4 · all three processes reported a verdict');

// Sequential retry must be a refusal, not a second conversion.
$aAgain = $b2one('convert', $aCand, '', $uMaster);
t_ok(!($aAgain['ok'] ?? false), 'A5 · converting an already-converted candidate is refused  [' . ($aAgain['code'] ?? '') . ']');
t_eq($b2insp($aCand), $aIns, 'A6 · and the original relationship is untouched');
t_eq($b2count('RaceOne%') - $aBefore, 1, 'A7 · still exactly one team member');

// A stale browser posting the old form must not convert again either.
$b2one('route_convert', $aCand, '', $uMaster);
t_eq($b2count('RaceOne%') - $aBefore, 1, 'A8 · a stale POST after conversion creates nothing');

// A9 · NO FALSE SUCCESS. Every process that says it converted must name a team
// member that really exists and really is this candidate's. A loser told "done"
// is worse than a loser told "you lost": the screen then shows a hire nobody has.
$a9Cand = $b2cand('NoLie', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$a9 = $b2race([['convert', $a9Cand, '', $uMaster], ['convert', $a9Cand, '', $uMaster],
               ['convert', $a9Cand, '', $uMaster]]);
$a9Live = $b2insp($a9Cand);
$a9Lies = 0;
foreach ($a9 as $r) {
    if (empty($r['ok'])) continue;
    $iid = (int)($r['inspector_id'] ?? 0);
    if ($iid <= 0 || $iid !== $a9Live
        || (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$iid]) !== 1) $a9Lies++;
}
t_eq($a9Lies, 0, 'A9 · every process that reported success names the team member that really exists');
$a9Ok = 0; foreach ($a9 as $r) if (!empty($r['ok'])) $a9Ok++;
t_eq($a9Ok, 1, 'A10 · exactly ONE process reported success');
t_eq($b2count('NoLie%'), 1, 'A11 · and exactly one team member exists');

// A12 · the converse: a committed conversion must never report failure.
$a12 = $b2one('convert', $b2cand('Truthful', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]), '', $uMaster);
t_ok($a12['ok'] ?? false, 'A12 · a conversion that committed reports success');
t_ok((int)($a12['inspector_id'] ?? 0) > 0
     && (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [(int)($a12['inspector_id'] ?? 0)]) === 1,
     'A13 · and the team member it names is really there');

// A14 · M · the conversion checks its OWN authority. Field staff may not hire.
$uField = $b2user('p6b2.fieldman', 'INSPECTOR', ['office' => 2, 'scope_offices' => 'ALL', 'scope_sbus' => 'ALL']);
$a14Cand = $b2cand('NoRight', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$a14 = $b2one('convert', $a14Cand, '', $uField);
t_ok(!($a14['ok'] ?? false), 'A14 · an actor without the recruitment right is refused  [' . ($a14['code'] ?? '') . ']');
t_eq($b2count('NoRight%'), 0, 'A15 · and created no team member (state, not return code)');
t_eq($b2insp($a14Cand), 0, 'A16 · and wrote no relationship');

// =============================================================================
t_section('P6-B2 · B — branch resolution: requisition → recruiter → refuse (BD1)');
// =============================================================================
$bCand = $b2cand('BranchReq', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$bRes = $b2one('convert', $bCand, '', $uMaster);
t_ok($bRes['ok'] ?? false, 'B1 · a candidate on a Mumbai requirement converts  [' . ($bRes['code'] ?? '') . ']');
t_eq((int)ops_val("SELECT COALESCE(home_office_id,0) FROM inspectors WHERE id=?", [$b2insp($bCand)]), 2,
     'B2 · the team member takes the REQUISITION branch, not the actor\'s');

// No requisition branch → the recruiter's branch.
$bCand2 = $b2cand('BranchRec', ['requisition_id' => $reqNoOff, 'recruiter_id' => $uRecMum]);
$bRes2 = $b2one('convert', $bCand2, '', $uMaster);
t_ok($bRes2['ok'] ?? false, 'B3 · with no requisition branch it falls back to the recruiter  [' . ($bRes2['code'] ?? '') . ']');
t_eq((int)ops_val("SELECT COALESCE(home_office_id,0) FROM inspectors WHERE id=?", [$b2insp($bCand2)]), 2,
     'B4 · and takes the recruiter\'s branch');

// Neither → REFUSE. Never Ahmedabad.
$bCand3 = $b2cand('BranchNone', ['requisition_id' => $reqNoOff, 'recruiter_id' => $uNoOff]);
$bBefore = $b2count('BranchNone%');
$bRes3 = $b2one('convert', $bCand3, '', $uNoOff);
t_ok(!($bRes3['ok'] ?? false), 'B5 · with no branch anywhere the conversion is REFUSED  [' . ($bRes3['code'] ?? '') . ']');
t_eq($b2count('BranchNone%'), $bBefore, 'B6 · and NO team member was created');
t_eq($b2insp($bCand3), 0, 'B7 · and no partial relationship was written');
t_ok(stripos((string)($bRes3['code'] ?? ''), 'branch') !== false || stripos((string)($bRes3['msg'] ?? ''), 'branch') !== false,
     'B8 · the refusal is deterministic and names the reason  [' . ($bRes3['code'] ?? '') . ']');
// The unsafe generic fallback must be gone: nothing lands in Ahmedabad by default.
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE name LIKE 'Branch%' AND COALESCE(home_office_id,0)=1"), 0,
     'B9 · NO converted team member silently landed in Ahmedabad');

// =============================================================================
t_section('P6-B2 · C — Connect ON / OFF: STATE A and STATE B (BD2)');
// =============================================================================
$cCandOn = $b2cand('ConnOn', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$cOn = $b2one('convert', $cCandOn, '', $uMaster);
t_ok($cOn['ok'] ?? false, 'C1 · with Connect available the conversion succeeds');
t_eq((string)($cOn['linked'] ?? ''), 'LINKED', 'C2 · STATE A — it reports the identity relationship as recorded');
t_ok(function_exists('connect_identity_of_candidate_inspector')
     && is_array(connect_identity_of_candidate_inspector($cCandOn)), 'C3 · and the ledger row really exists');

setting_set('modules_off', 'connect'); licence_disabled(true);
$cCandOff = $b2cand('ConnOff', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$cOff = $b2one('convert', $cCandOff, '', $uMaster);
t_ok($cOff['ok'] ?? false, 'C4 · WITHOUT Connect the recruitment conversion STILL succeeds  [' . ($cOff['code'] ?? '') . ']');
t_ok($b2insp($cCandOff) > 0, 'C5 · the team member exists and the candidate is linked to it');
t_eq((string)($cOff['linked'] ?? ''), 'NOT_ENTITLED', 'C6 · STATE B — it says so, rather than claiming a link');
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE candidate_id=? AND status='LINKED'", [$cCandOff]), 0,
     'C7 · and NO false identity-ledger row was written');
setting_set('modules_off', ''); licence_disabled(true);

// The state must be reconcilable afterwards, and linkable through the proper path.
t_ok(function_exists('identity_state_findings'), 'C8 · a reconciliation report exists');
if (function_exists('identity_state_findings')) {
    $kinds = array_column(identity_state_findings(), 'kind');
    t_ok(in_array('CONVERTED_NO_LEDGER', $kinds, true),
         'C9 · the Connect-unavailable conversion is REPORTED, not forgotten');
}

// Two simultaneous conversions with Connect OFF must still produce one.
setting_set('modules_off', 'connect'); licence_disabled(true);
$cRace = $b2cand('ConnRace', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$b2race([['convert', $cRace, '', $uMaster], ['convert', $cRace, '', $uMaster]]);
t_eq($b2count('ConnRace%'), 1, 'C10 · two simultaneous conversions with Connect OFF still create exactly one');
setting_set('modules_off', ''); licence_disabled(true);

// =============================================================================
t_section('P6-B2 · D — scope, tenant and the locked boundaries');
// =============================================================================
$dCandOth = $b2cand('ScopeOth', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster, 'sbu' => 'OTH']);
$uIndOnly = $b2user('p6b2.indonly', 'COORDINATOR', ['office' => 2, 'scope_offices' => '2', 'scope_sbus' => 'IND']);
$dBefore = $b2count('ScopeOth%');
$dRes = $b2one('convert', $dCandOth, '', $uIndOnly);
t_ok(!($dRes['ok'] ?? false), 'D1 · an out-of-unit candidate cannot be converted  [' . ($dRes['code'] ?? '') . ']');
t_eq($b2count('ScopeOth%'), $dBefore, 'D2 · and nothing was created');
// The M4/M6 boundary still decides first: a cancelled requirement cannot be spent.
$reqDead = $b2req(2, ['status' => 'CANCELLED']);
$dCandDead = $b2cand('ScopeDead', ['requisition_id' => $reqDead, 'recruiter_id' => $uMaster]);
$dBefore2 = $b2count('ScopeDead%');
$dRes2 = $b2one('convert', $dCandDead, '', $uMaster);
t_ok(!($dRes2['ok'] ?? false), 'D3 · the M4/M6 execution boundary still refuses first  [' . ($dRes2['code'] ?? '') . ']');
t_eq($b2count('ScopeDead%'), $dBefore2, 'D4 · and nothing was created');

// A17 · A BORROWED TRANSACTION. When a caller has already opened one, the
// conversion joins it — and then it may neither commit nor roll back, because
// that work is not its own. A failure must therefore reach the caller, or the
// half-made team member rides along on the caller's commit while the conversion
// reports failure: a reported failure that commits a row, which is the exact
// defect this batch exists to remove. Found by the Batch 2 adversarial pass.
$a17Cand = $b2cand('Borrowed', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$a17Other = (int)team_member_create('Borrowed Other', 'FIELD', 2, '');
connect_identity_conversion_link_create($a17Cand, $a17Other, 'conversion', 'p6b2-setup');
$a17Before = $b2count('Borrowed Person%');
$a17Threw = false;
db()->beginTransaction();
try { rcv_convert($a17Cand, []); } catch (Throwable $e) { $a17Threw = true; }
t_ok($a17Threw, 'A17 · a failure inside a caller\'s transaction reaches the caller');
t_ok(db()->inTransaction(), 'A18 · and the caller\'s transaction is still its own to unwind');
db()->rollBack();
t_eq($b2count('Borrowed Person%'), $a17Before, 'A19 · after the caller rolls back, NO team member was committed');
t_eq($b2insp($a17Cand), 0, 'A20 · and no relationship was committed either');

// =============================================================================
t_section('P6-B2 · E — the identity ledger and the resolver (R1 · R5)');
// =============================================================================
$eCand = $b2cand('Resolve', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$b2one('convert', $eCand, '', $uMaster);
$eIns = $b2insp($eCand);
t_ok($eIns > 0, 'E0 · converted');
$eSum = function_exists('connect_person_summary') ? connect_person_summary('candidate', $eCand) : ['pools' => []];
t_ok((int)($eSum['pools']['inspector'] ?? 0) > 0,
     'E1 · the person resolver reaches the team member FROM the candidate');
// The three axes stay distinct: a conversion link must not answer as a professional link.
t_ok(connect_identity_of_candidate($eCand) === null,
     'E2 · a conversion link is NOT returned as a candidate↔professional link');
t_ok(connect_identity_of_inspector($eIns) === null,
     'E3 · nor as a professional↔inspector link');
// A candidate may hold BOTH a conversion link and a professional link (invariant I1).
db()->prepare("INSERT INTO cx_professionals (name,email) VALUES ('Resolve Person','resolve@p6b2.test')")->execute();
$ePro = (int)db()->lastInsertId();
[$eLinkOk] = connect_identity_candidate_link_create($eCand, $ePro, 'manual', 'p6b2');
t_ok($eLinkOk, 'E4 · the same candidate may ALSO be linked to a professional (I1)');
t_ok(function_exists('connect_identity_of_candidate_inspector')
     && is_array(connect_identity_of_candidate_inspector($eCand)), 'E5 · and the conversion link is still there');

// =============================================================================
t_section('P6-B2 · F — the conversion is audited (I6)');
// =============================================================================
$fCand = $b2cand('Audited', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
$b2one('convert', $fCand, '', $uMaster);
$fRow = ops_one("SELECT * FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? ORDER BY id DESC LIMIT 1", [$fCand]);
t_ok(is_array($fRow), 'F1 · the conversion wrote an attributable activity against the candidate');
if (is_array($fRow)) {
    t_ok(isset(ACT_KINDS[(string)$fRow['kind']]), 'F2 · with a registered activity kind');
    t_ok((string)$fRow['kind'] !== 'NOTE', 'F3 · not downgraded to a plain note');
}
$fRef = $b2cand('AuditRef', ['requisition_id' => $reqNoOff, 'recruiter_id' => $uNoOff]);
$b2one('convert', $fRef, '', $uNoOff);
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_REFUSED'", [$fRef]) >= 1,
     'F4 · a REFUSED conversion is audited too');

// =============================================================================
t_section('P6-B2 · G — linking must never split a group (BD4 · invariant I43)');
// =============================================================================
$gA = $b2cand('GrpA'); $gB = $b2cand('GrpB'); $gC = $b2cand('GrpC'); $gD = $b2cand('GrpD');
if (function_exists('person_link_rows')) { person_link_rows([$gA, $gB]); person_link_rows([$gC, $gD]); }
$ref = function ($id) { return (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$id]); };
t_eq($ref($gA), $ref($gB), 'G0 · A and B start as one person');
t_eq($ref($gC), $ref($gD), 'G0b · C and D start as one person');
if (function_exists('person_link_rows')) person_link_rows([$gB, $gC]);
t_eq($ref($gD), $ref($gC), 'G1 · I43 · after linking B↔C, D is STILL the same person as C');
t_eq($ref($gA), $ref($gD), 'G2 · all four are one person');
t_eq((int)ops_val("SELECT COUNT(DISTINCT person_ref) FROM candidates WHERE id IN (?,?,?,?)", [$gA,$gB,$gC,$gD]), 1,
     'G3 · exactly one group remains — nobody was left behind');
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind LIKE 'IDENTITY%'", [$gB]) >= 1,
     'G4 · the person link is audited');

// A group the OLD code split must be repairable — deterministically, from the
// system's own recorded grouping, never from a name or an e-mail.
$hA = $b2cand('SplitA'); $hB = $b2cand('SplitB'); $hC = $b2cand('SplitC');
db()->prepare("UPDATE candidates SET person_ref='PSPLIT1' WHERE id IN (?,?)")->execute([$hA, $hB]);
db()->prepare("UPDATE candidates SET person_ref='PSPLIT2' WHERE id=?")->execute([$hC]);
t_ok(function_exists('person_group_repair'), 'G5 · a deterministic group repair exists');
if (function_exists('person_group_repair')) {
    $rep = person_group_repair(['dry_run' => true]);
    t_ok(is_array($rep), 'G6 · it can report without changing anything');
    t_eq((string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$hC]), 'PSPLIT2',
         'G7 · a dry run changed nothing');

    // G8 · THE LIMIT THAT MATTERS. Two groups that were never split must survive
    // a live repair untouched. A repair that cannot prove a previous state must
    // report, never guess — this is the difference between restoring the
    // system's own record and inventing a person decision.
    //  KeepA and KeepC deliberately share a mobile number and an e-mail. They are
    //  two DIFFERENT people as far as the system's records go — nobody ever linked
    //  them — and a repair that reached for a similarity to decide would merge
    //  them. Sharing a number is ordinary here: families, agencies, one handset on
    //  a site. This is the fixture that makes "never fuzzy" testable.
    $kA = $b2cand('KeepA', ['mobile' => '9000000111']); $kB = $b2cand('KeepB');
    $kC = $b2cand('KeepC', ['mobile' => '9000000111']); $kD = $b2cand('KeepD');
    db()->prepare("UPDATE candidates SET email='shared.handset@p6b2.test' WHERE id IN (?,?)")->execute([$kA, $kC]);
    person_link_rows([$kA, $kB]);
    person_link_rows([$kC, $kD]);
    $kRefAB = (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kA]);
    $kRefCD = (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kC]);
    t_ok($kRefAB !== '' && $kRefCD !== '' && $kRefAB !== $kRefCD, 'G8a · two separate, intact person groups exist');
    person_group_repair([]);                        // a LIVE repair, not a dry run
    t_eq((string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kA]), $kRefAB, 'G8b · the first group is untouched');
    t_eq((string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kD]), $kRefCD, 'G8c · the second group is untouched');
    t_ok((string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kA])
      !== (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kD]),
         'G8d · TWO UNRELATED PEOPLE WERE NOT MERGED');
    t_ok((string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kA])
      !== (string)ops_val("SELECT person_ref FROM candidates WHERE id=?", [$kC]),
         'G8e · and a SHARED MOBILE AND E-MAIL did not merge them either — the repair is never fuzzy');
}

// =============================================================================
t_section('P6-B2 · H — contradictory states are DETECTED, never auto-repaired');
// =============================================================================
if (function_exists('identity_state_findings')) {
    // A dangling reference: the candidate points at a team member that is gone.
    $iCand = $b2cand('Dangle', ['requisition_id' => $reqMumbai, 'recruiter_id' => $uMaster]);
    db()->prepare("UPDATE candidates SET inspector_id=? WHERE id=?")->execute([999777, $iCand]);
    $rows = identity_state_findings();
    $kinds = array_column($rows, 'kind');
    t_ok(in_array('CANDIDATE_INSPECTOR_MISSING', $kinds, true), 'H1 · a dangling candidate→team-member reference is detected');
    $one = null; foreach ($rows as $r) if (($r['kind'] ?? '') === 'CANDIDATE_INSPECTOR_MISSING') { $one = $r; break; }
    if (is_array($one)) {
        foreach (['kind', 'what', 'records', 'why', 'safe_to_repair', 'needs_human'] as $k)
            t_ok(array_key_exists($k, $one), "H2 · the finding states '$k' so an administrator can act on it");
    }
    // Detection must not repair.
    t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$iCand]), 999777,
         'H3 · detection changed NOTHING');
} else {
    t_ok(false, 'H1 · identity_state_findings() exists');
}

// =============================================================================
t_section('P6-B2 · W — no undocumented second writer');
// =============================================================================
$wRoot = dirname(__DIR__); $wBad = [];
foreach (array_merge(glob($wRoot . '/lib/*.php') ?: [], glob($wRoot . '/views/ops/*.php') ?: [],
                     glob($wRoot . '/tools/*.php') ?: []) as $wf) {
    $base = basename($wf);
    if (in_array($base, ['recruit.php', 'ops.php'], true)) continue;      // the owners, checked below
    //  Seeds are NOT excused here. A seed may create namespaced demo INSPECTORS —
    //  that is demo data — but writing candidates.inspector_id is performing a
    //  CONVERSION, and a conversion written straight to the database skips the
    //  transaction, the branch rule, the ledger and the audit. Batch 1 was
    //  defeated once by exactly this shape of second writer.
    $src = (string)@file_get_contents($wf);
    if (preg_match('/UPDATE\s+candidates\s+SET[^;"\']*inspector_id/i', $src)) $wBad[] = $base;
}
t_eq($wBad, [], 'W1 · only the recruitment layer writes candidates.inspector_id');
// The conversion itself must live in ONE place, not inline in the route.
t_ok(function_exists('rcv_convert'), 'W2 · the conversion is a function that can be tested and attacked directly');
$wSrc = (string)@file_get_contents($wRoot . '/lib/ops.php');
t_eq(preg_match_all('/INSERT\s+INTO\s+inspectors\s*\(\s*name\s*,\s*first_name\s*,\s*middle_name/i', $wSrc), 0,
     'W3 · the raw conversion INSERT is gone from the route');

$_SESSION = $b2sess; current_user(true); ua(true);   // restored LAST, deliberately
