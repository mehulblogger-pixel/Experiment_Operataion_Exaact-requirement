<?php
// ============================================================================
//  PHASE 6 · BATCH 3 — Organisation duplicate safety & cross-reference.
//
//  Written BEFORE the implementation, against the code as it stood. Every
//  assertion states the required behaviour. Baseline recorded in
//  docs/phase6/P6-BATCH3-TEST-RESULTS.md.
//
//  Everything is read back from the DATABASE. A return code is never evidence.
//
//  Sections
//    A  /join duplicate protection                     F1 · Q20 · Q21
//    B  /join concurrency                              F2 · Q23
//    C  /join transaction                              F3
//    D  agency cross-reference                         F4 · Q19
//    E  CRM and lead writers                           F5
//    F  detector backward compatibility                F6 · Q20
//    G  one primary contact                            Q22
//    H  portal account boundary                        Q23
//    I  organisation audit                             F8
//    J  historical detection, report-only              F9
//    K  authorisation and forged ids
//    L  tenant isolation
// ============================================================================

$b3sess = $_SESSION;                       // restored at the very END

$b3act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$b3root = dirname(__DIR__);
$b3env = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
$b3one = function ($op, $a = 0, $b = '', $uid = 0) use ($b3root, $b3env) {
    $cmd = $b3env() . 'php ' . escapeshellarg($b3root . '/tests/_p6b3_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int)$a . ' ' . escapeshellarg((string)$b) . ' 0 ' . (int)$uid . ' 2>&1';
    foreach (explode("\n", trim((string)shell_exec($cmd))) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) return $j; }
    return ['ok' => false, 'code' => 'NO_OUTPUT'];
};
$b3race = function (array $specs, $leadMs = 1100) use ($b3root, $b3env) {
    $t = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $b3env() . 'php ' . escapeshellarg($b3root . '/tests/_p6b3_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . (int)$s[1] . ' ' . escapeshellarg((string)($s[2] ?? '')) . ' '
             . escapeshellarg((string)$t) . ' ' . (int)($s[3] ?? 0) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim((string)$raw)) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) { $out[] = $j; break; } }
    }
    return $out;
};
$b3partner = function ($name, $opt = []) {
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,gstin,pan,tan,created_at)
                   VALUES (?,?,?,1,'ACTIVE',?,?,?,?)")
        ->execute([$opt['code'] ?? ('B3-' . random_int(100000, 999999)), $name, $name,
                   $opt['gstin'] ?? '', $opt['pan'] ?? '', $opt['tan'] ?? '', date('c')]);
    return (int)db()->lastInsertId();
};
$b3parts = function ($name) { return (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name=?", [$name]); };
$b3orgs  = function ($name) { return (int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE name=?", [$name]); };
$b3accts = function ($email) { return (int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [strtolower($email)]); };

$uAdmin = (int)(ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: 0);
$b3act($uAdmin);

// =============================================================================
t_section('P6-B3 · A — /join must not create a duplicate organisation (F1)');
// =============================================================================
$aGst = '24AAABB1111A1Z9';
$aPid = $b3partner('Northwind Energy Ltd', ['gstin' => $aGst, 'pan' => 'AAABB1111A', 'tan' => 'AHMN11111A']);

// NONE — a genuinely new company must still register.
$a1 = $b3one('join', 0, json_encode(['name' => 'Southgate Marine Pvt Ltd', 'email' => 'a1@southgate.test']));
t_ok($a1['ok'] ?? false, 'A1 · a genuinely new company still registers  [' . ($a1['msg'] ?? '') . ']');
t_eq($b3parts('Southgate Marine Pvt Ltd'), 1, 'A2 · exactly one organisation was created for it');

// EXACT by GSTIN — refuse, create nothing.
$a3 = $b3one('join', 0, json_encode(['name' => 'Totally Different Name Ltd', 'email' => 'a3@nw.test', 'gstin' => $aGst]));
t_ok(!($a3['ok'] ?? false), 'A3 · an EXACT GSTIN match is refused  [' . ($a3['msg'] ?? '') . ']');
t_eq($b3parts('Totally Different Name Ltd'), 0, 'A4 · and NO second organisation was created');
t_eq($b3accts('a3@nw.test'), 0, 'A5 · and no portal account was created either');

// The refusal must disclose nothing about the organisation it matched.
$a3msg = strtolower((string)($a3['msg'] ?? ''));
foreach (['northwind', strtolower($aGst), 'aaabb1111a', (string)$aPid] as $secret)
    t_ok(strpos($a3msg, $secret) === false, 'A6 · the public refusal does not disclose "' . substr($secret, 0, 12) . '"');

// EXACT by name — the same company registering again.
$a7 = $b3one('join', 0, json_encode(['name' => 'Northwind Energy Ltd', 'email' => 'a7@nw.test']));
t_ok(!($a7['ok'] ?? false), 'A7 · registering an existing organisation by name is refused  [' . ($a7['msg'] ?? '') . ']');
t_eq($b3parts('Northwind Energy Ltd'), 1, 'A8 · still exactly one Northwind');
t_eq($b3orgs('Northwind Energy Ltd'), 0, 'A9 · and no marketplace organisation was created for it');

// A similar but DIFFERENT company must not be blocked by a loose name rule.
$b3partner('Apex Engineering Services Pvt Ltd', ['gstin' => '24CCCDD2222C1Z9']);
$a10 = $b3one('join', 0, json_encode(['name' => 'Apex Engineering Solutions LLP', 'email' => 'a10@apex.test']));
t_ok($a10['ok'] ?? false, 'A10 · a similarly named but distinct company still registers  [' . ($a10['msg'] ?? '') . ']');

// =============================================================================
t_section('P6-B3 · B — /join concurrency (F2 · CRITICAL)');
// =============================================================================
$bName = 'Racecourse Holdings Ltd'; $bMail = 'race@racecourse.test';
$bRes = $b3race([['join', 0, json_encode(['name' => $bName, 'email' => $bMail])],
                 ['join', 0, json_encode(['name' => $bName, 'email' => $bMail])],
                 ['join', 0, json_encode(['name' => $bName, 'email' => $bMail])]]);
t_eq(count($bRes), 3, 'B0 · all three processes reported a verdict');
t_eq($b3accts($bMail), 1, 'B1 · three simultaneous registrations left exactly ONE portal account');
t_eq($b3parts($bName), 1, 'B2 · exactly ONE business partner');
t_eq($b3orgs($bName), 1, 'B3 · exactly ONE marketplace organisation');
$bOk = 0; foreach ($bRes as $r) if (!empty($r['ok'])) $bOk++;
t_eq($bOk, 1, 'B4 · exactly one process reported success');
$bCrash = 0; foreach ($bRes as $r) if (($r['code'] ?? '') === 'EX') $bCrash++;
t_eq($bCrash, 0, 'B5 · no process crashed — the conflict was translated into a business answer');
foreach ($bRes as $i => $r)
    if (empty($r['ok'])) t_ok(trim((string)($r['msg'] ?? '')) !== '', 'B6 · a losing process was told something useful, not nothing');

// =============================================================================
t_section('P6-B3 · C — /join is transactional (F3)');
// =============================================================================
// A registration that cannot complete must leave nothing behind.
$cName = 'Halfway House Ltd';
$cPartsBefore = $b3parts($cName); $cOrgsBefore = $b3orgs($cName);
// The account e-mail already exists, so the account write cannot succeed.
$cExisting = $b3partner('Halfway Existing Ltd');
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at) VALUES (?,?,?,?,1,?)")
    ->execute([$cExisting, 'taken@halfway.test', 'Taken', password_hash('x', PASSWORD_DEFAULT), date('c')]);
$c1 = $b3one('join', 0, json_encode(['name' => $cName, 'email' => 'taken@halfway.test']));
t_ok(!($c1['ok'] ?? false), 'C1 · a registration whose account is already taken is refused');
t_eq($b3parts($cName), $cPartsBefore, 'C2 · and NO orphan business partner was left behind');
t_eq($b3orgs($cName), $cOrgsBefore, 'C3 · and NO orphan marketplace organisation was left behind');

//  C1-C3 are answered by the check at the TOP of the route, before a single row
//  is written - worth having, but they prove nothing about the transaction.
//  These force a failure PART WAY THROUGH, which is what the transaction is for:
//  a unique index placed on the organisation name for the length of the probe
//  makes step 2 fail after step 1 has already written a row.
$cBlock = 'Blockade Industries Ltd';
$cIxOk = false;
try { db()->exec("CREATE UNIQUE INDEX tmp_uq_cxorg_name ON cx_organisations (name)"); $cIxOk = true; } catch (Throwable $e) {}
if ($cIxOk) {
    db()->prepare("INSERT INTO cx_organisations (name,org_type,status,created_at) VALUES (?, 'ENTERPRISE','ACTIVE',?)")
        ->execute([$cBlock, date('c')]);

    //  (a) ON ITS OWN - it owns the transaction, so it answers in business terms
    //      and leaves nothing behind.
    $cOwn = $b3one('join', 0, json_encode(['name' => $cBlock, 'email' => 'c5@blockade.test']));
    t_ok(!($cOwn['ok'] ?? false), 'C5 . a failure part way through is reported, not swallowed');
    //  AND IT MUST SAY THE RIGHT THING. A database failure is not a duplicate:
    //  telling somebody their organisation may already work with us sends them
    //  down a claim path that does not apply and hides a fault nobody then
    //  investigates.
    $cMsg = strtolower((string)($cOwn['msg'] ?? ''));
    t_ok(strpos($cMsg, 'nothing has been saved') !== false,
         'C5b . and it says nothing was saved, in its own words');
    t_ok(strpos($cMsg, 'already works with us') === false,
         'C5c . it does NOT borrow the duplicate wording for a system failure');
    t_eq($b3parts($cBlock), 0, 'C6 . and the half-written organisation is rolled back - no orphan party');
    t_eq($b3accts('c5@blockade.test'), 0, 'C7 . and no orphan account');

    //  (b) INSIDE A CALLER TRANSACTION - it must NEVER commit or roll back
    //      somebody else's work. It re-throws so the caller unwinds. Returning a
    //      polite failure here would leave the caller committing the wreckage.
    $cThrew = false;
    db()->beginTransaction();
    try { connect_org_register(['name' => $cBlock, 'org_type' => 'ENTERPRISE', 'contact_name' => 'C Eight',
                                'contact_email' => 'c8@blockade.test', 'password' => 'blockade123']); }
    catch (Throwable $e) { $cThrew = true; }
    $cStillIn = db()->inTransaction();
    try { if ($cStillIn) db()->rollBack(); } catch (Throwable $e) {}
    t_ok($cThrew, 'C8 . inside a caller transaction a failure is RE-THROWN, so the caller unwinds');
    t_ok($cStillIn, 'C9 . and it neither committed nor rolled back the transaction it borrowed');
    t_eq($b3parts($cBlock), 0, 'C10 . after the caller rolls back, nothing survives');
    t_eq($b3accts('c8@blockade.test'), 0, 'C11 . including the account');

    try { db()->exec("DROP INDEX tmp_uq_cxorg_name ON cx_organisations"); }
    catch (Throwable $e) { try { db()->exec("DROP INDEX tmp_uq_cxorg_name"); } catch (Throwable $e2) {} }
} else { foreach (['C5','C6','C7','C8','C9','C10','C11'] as $c) t_ok(false, $c . ' . the probe index could not be built'); }

//  C4 — THE FIRST REGISTRATION IN A FRESH PROCESS.
//
//  Every migration is guarded by a run-once marker tied to the database epoch,
//  so in a long-running suite they have all already happened. A live first-run
//  has not. Bumping the epoch reproduces it honestly: the next call re-runs its
//  schema steps, and on MariaDB any DDL inside a transaction commits it early.
//  The registration must still succeed — and, above all, must never report
//  failure for an account it actually created.
$GLOBALS['__db_epoch'] = db_epoch() + 1;
$cName = 'Freshstart Engineering Pvt Ltd';
$cMail = 'c4@freshstart.test';
[$cOk, $cMsg, $cAcct] = connect_org_register([
    'name' => $cName, 'org_type' => 'ENTERPRISE', 'contact_name' => 'C Four',
    'contact_email' => $cMail, 'contact_mobile' => '9820000004', 'password' => 'freshstart123',
    'caps' => ['TPIA'],
]);
t_ok($cOk === true, 'C4 · the first registration in a fresh process succeeds  [' . $cMsg . ']');
t_eq($b3parts($cName), 1, 'C4b · exactly one organisation');
t_eq($b3accts($cMail), 1, 'C4c · and exactly one portal account');
//  The truthfulness rule cuts both ways: whatever it REPORTED must match what
//  the database holds. A committed account reported as a failure is the worse
//  half of this defect, because nobody ever looks for it.
t_ok(($cOk === true) === ($b3accts($cMail) === 1),
     'C4d · what it reported and what it wrote AGREE');

// =============================================================================
t_section('P6-B3 · D — agency cross-reference (F4 · Q19)');
// =============================================================================
$dHas = in_array('party_id', t_columns('agencies'), true);
t_ok($dHas, 'D1 · agencies carries an optional party_id');
$dPid = $b3partner('Sterling Manpower Pvt Ltd', ['gstin' => '24EEEFF3333E1Z9']);
if ($dHas) {
db()->prepare("INSERT INTO agencies (name,agency_type,active,created_at) VALUES ('Sterling Manpower Pvt Ltd','MANPOWER',1,?)")->execute([date('c')]);
$dA1 = (int)db()->lastInsertId();
t_eq((int)ops_val("SELECT COALESCE(party_id,0) FROM agencies WHERE id=?", [$dA1]), 0,
     'D2 · creating an agency NEVER infers a party, even with an identical name');
db()->prepare("INSERT INTO agencies (name,agency_type,gstin,active,created_at) VALUES ('Sterling Manpower Pvt Ltd','RECRUITMENT','24EEEFF3333E1Z9',1,?)")->execute([date('c')]);
$dA2 = (int)db()->lastInsertId();
t_eq((int)ops_val("SELECT COALESCE(party_id,0) FROM agencies WHERE id=?", [$dA2]), 0,
     'D3 · nor from an identical GSTIN — inference is never performed');
// A person may set it, and two contracts may point at one organisation.
db()->prepare("UPDATE agencies SET party_id=? WHERE id IN (?,?)")->execute([$dPid, $dA1, $dA2]);
t_eq((int)ops_val("SELECT COUNT(*) FROM agencies WHERE party_id=?", [$dPid]), 2,
     'D4 · TWO agency contracts may point at ONE organisation (no uniqueness)');
t_eq((int)ops_val("SELECT COUNT(*) FROM agencies WHERE COALESCE(party_id,0)=0"), (int)ops_val("SELECT COUNT(*) FROM agencies WHERE party_id IS NULL OR party_id=0"),
     'D5 · an agency with no mapping remains valid');
//  A person needs a way to SET it, or the column is decoration. It is offered
//  on the agency master screen as an optional picker — never required.
$dCfg = ops_masters()['agencies'] ?? [];
$dFld = null; foreach (($dCfg['fields'] ?? []) as $f) if ($f[0] === 'party_id') $dFld = $f;
t_ok(is_array($dFld), 'D6 · the agency screen offers the cross-reference');
if (is_array($dFld)) {
    t_eq((string)$dFld[2], 'ref', 'D7 · as a pick-from-the-list, not a typed id');
    t_ok(empty($dFld[3]['req']), 'D8 · and it is OPTIONAL — an agency with no mapping is valid');
}
//  A cross-reference must point at a company that is really there. Blank stays
//  valid - the mapping is optional - and no other master screen gains a rule.
t_ok(function_exists('master_row_problem'), 'D13 . the rule can be asked on its own, not only through the screen');
if (function_exists('master_row_problem')) {
    t_eq(master_row_problem('agencies', ['name','party_id'], ['Sterling', $dPid]), '',
         'D14 . a cross-reference to a real company is accepted');
    t_ok(master_row_problem('agencies', ['name','party_id'], ['Sterling', 987654321]) !== '',
         'D15 . a cross-reference to a company that does not exist is REFUSED');
    t_eq(master_row_problem('agencies', ['name','party_id'], ['Sterling', null]), '',
         'D16 . and leaving it blank is perfectly valid');
    t_eq(master_row_problem('offices', ['name','party_id'], ['Somewhere', 987654321]), '',
         'D17 . no other master screen gains a rule from this');
}

//  Setting it is an identity statement, so it is recorded against the company.
$dBefore = (int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$dPid]);
master_audit_agency_map('agencies', ['name','party_id'], ['Sterling Manpower Pvt Ltd', $dPid], $dA1);
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$dPid]) > $dBefore,
     'D9 · linking an agency contract to an organisation is audited');
$dOther = (int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER'");
master_audit_agency_map('offices', ['name','party_id'], ['Somewhere', $dPid], 1);
t_eq((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER'"), $dOther,
     'D10 · and NO other master screen writes organisation audit entries');
//  The mapping changes no commercial figure and Recruitment reads what it read.
$dFee = ops_val("SELECT COALESCE(one_time_fee,0) FROM agencies WHERE id=?", [$dA1]);
db()->prepare("UPDATE agencies SET party_id=? WHERE id=?")->execute([$dPid, $dA1]);
t_eq(ops_val("SELECT COALESCE(one_time_fee,0) FROM agencies WHERE id=?", [$dA1]), $dFee,
     'D11 · setting the cross-reference changes no commercial field');
t_ok(count(agencies_list()) > 0, 'D12 · and the agency list Recruitment reads still works');
} else { foreach (['D2','D3','D4','D5','D6','D7','D8','D9','D10','D11','D12'] as $d) t_ok(false, $d . ' · needs the party_id column'); }

// =============================================================================
t_section('P6-B3 · E — the CRM and lead writers are protected (F5)');
// =============================================================================
$ePid = $b3partner('Crestwood Industries Ltd', ['gstin' => '24GGGHH4444G1Z9']);
t_ok(function_exists('partner_find_or_problem'), 'E1 · a shared guard exists for the staff writers');
if (function_exists('partner_find_or_problem')) {
    //  A NAME match is POSSIBLE, never EXACT — owner decision Q20 reserves EXACT
    //  for an authoritative identifier. Two real companies share a name often
    //  enough that a name can warn but must never prove.
    $e2 = partner_find_or_problem('Crestwood Industries Ltd', '', '', '');
    t_eq((string)($e2['confidence'] ?? ''), 'POSSIBLE', 'E2 · an existing organisation matched BY NAME is POSSIBLE, not EXACT');
    $e2b = partner_find_or_problem('Anything Else Ltd', '24GGGHH4444G1Z9', '', '');
    t_eq((string)($e2b['confidence'] ?? ''), 'EXACT', 'E2b · and a tax-identifier match IS exact');
    t_ok(strpos((string)($e2b['message'] ?? ''), 'Crestwood') !== false,
         'E2c · the STAFF message names the record — staff are entitled to see the register');
    $e3 = partner_find_or_problem('Brand New Company Ltd', '', '', '');
    t_eq((string)($e3['confidence'] ?? ''), 'NONE', 'E3 · a new company is reported as NONE');
}

//  The guard is only worth having if the WRITERS call it. Written against the
//  behaviour required, not the behaviour found: at baseline both writers create
//  blind.
//    · an EXACT match (an authoritative tax identifier) must not produce a
//      second record — the quote path attaches to the record that exists,
//      the lead path refuses and names it, because a person is there to decide.
//    · a POSSIBLE match (a name) warns and ALLOWS the override — two real
//      companies do share a name — but the override is recorded.
$eActs = function ($pid) { return (int)ops_val(
    "SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$pid]); };

$e4 = crm_register_client_for_quote(['client_name' => 'Crestwood Industries Ltd']);
t_eq((int)$e4, $ePid, 'E4 · accepting a quote for a company already on file reuses that record');
t_eq(1, $b3parts('Crestwood Industries Ltd'), 'E4b · and creates no second record');

$e5 = crm_register_client_for_quote(['client_name' => 'Westmark Alloys', 'gstin' => '24GGGHH4444G1Z9']);
t_eq((int)$e5, $ePid, 'E5 · a quote carrying an existing TAX IDENTIFIER attaches to that organisation');
t_eq(0, $b3parts('Westmark Alloys'), 'E5b · under a different trading name, still no second record');

//  A near-duplicate NAME is allowed through — and recorded.
$e6 = crm_register_client_for_quote(['client_name' => 'Crestwood Industries Ltd.']);
t_ok((int)$e6 > 0 && (int)$e6 !== $ePid, 'E6 · a company matching only BY NAME is still allowed to be created');
$e6act = ops_one("SELECT * FROM activities WHERE entity_kind='PARTNER' AND entity_id=? ORDER BY id DESC LIMIT 1", [(int)$e6]);
t_ok(is_array($e6act), 'E6b · and the creation is audited');
t_ok(is_array($e6act) && stripos((string)$e6act['subject'], 'similar') !== false,
     'E6c · the entry records that it resembled an organisation already on file');

//  The lead path. An authoritative identifier REFUSES rather than duplicating.
$e7lead = lead_create(['company_name' => 'Crestwood Industries Limited']);
t_ok(!empty($e7lead['id']), 'E7 · a lead can be raised for a company with a similar name');
$e7 = lead_convert((int)$e7lead['id'], ['company_name' => 'Crestwood Trading Ltd', 'gstin' => '24GGGHH4444G1Z9']);
t_ok(!empty($e7['err']), 'E7b · converting it against an EXISTING tax identifier is refused');
t_ok(!empty($e7['err']) && stripos((string)$e7['err'], 'Crestwood Industries Ltd') !== false,
     'E7c · and the refusal NAMES the record — staff are entitled to see the register');
t_eq(0, $b3parts('Crestwood Trading Ltd'), 'E7d · no second organisation was created');
t_eq('OPEN', (string)ops_val("SELECT status FROM leads WHERE id=?", [(int)$e7lead['id']]),
     'E7e · and the lead is untouched — a refusal changes nothing');

//  A name match converts, but the override is recorded.
$e8 = lead_convert((int)$e7lead['id'], ['company_name' => 'Crestwood Industries Limited']);
t_ok(empty($e8['err']), 'E8 · a NAME match does not block the conversion');
$e8pid = (int)ops_val("SELECT id FROM business_partners WHERE legal_name=? ORDER BY id DESC LIMIT 1", ['Crestwood Industries Limited']);
t_ok($e8pid > 0, 'E8b · the customer was created');
t_ok($eActs($e8pid) > 0, 'E8c · and the creation is audited against it');

// =============================================================================
t_section('P6-B3 · F — the detector still serves its existing callers (F6 · Q20)');
// =============================================================================
$fPid = $b3partner('Ferrous Metals Ltd', ['gstin' => '24JJJKK5555J1Z9', 'pan' => 'JJJKK5555J', 'tan' => 'AHMF55555F']);
$f1 = find_duplicate_partner('Ferrous Metals Ltd', '', '', '', 0);
t_ok(is_array($f1) && isset($f1['row']) && isset($f1['by']), 'F1 · the original [row][by] shape is unchanged');
t_eq((int)$f1['row']['id'], $fPid, 'F2 · and it still finds the right organisation');
t_eq((string)($f1['confidence'] ?? ''), 'POSSIBLE', 'F3 · a NAME match is POSSIBLE');
$f4 = find_duplicate_partner('Anything At All', '24JJJKK5555J1Z9', '', '', 0);
t_eq((string)($f4['confidence'] ?? ''), 'EXACT', 'F4 · a GSTIN match is EXACT');
$f5 = find_duplicate_partner('Anything At All', '', 'JJJKK5555J', '', 0);
t_eq((string)($f5['confidence'] ?? ''), 'EXACT', 'F5 · a PAN match is EXACT');
$f6 = find_duplicate_partner('Anything At All', '', '', 'AHMF55555F', 0);
t_eq((string)($f6['confidence'] ?? ''), 'EXACT', 'F6 · a TAN match is EXACT');
t_ok(find_duplicate_partner('No Such Company Anywhere Ltd', '', '', '', 0) === null, 'F7 · no match still returns null');
t_ok(find_duplicate_partner('Ferrous Metals Ltd', '', '', '', $fPid) === null, 'F8 · the exclude-id argument still works');

//  PRECEDENCE. When a NAME matches one organisation and an authoritative
//  identifier matches a DIFFERENT one, the identifier wins. A name that
//  outranked a tax number would report POSSIBLE where the truth is EXACT, and
//  the staff path would warn where it should refuse.
//
//  THE ORDER MATTERS, and the first version of this probe got it wrong: it put
//  the identifier's record EARLIER in the register, so the scan met the
//  identifier first and answered EXACT whatever the precedence rule said. The
//  probe passed while proving nothing — mutation M7 walked straight through it.
//  The name-only record must come FIRST, so that a detector which returned on a
//  name would return the wrong answer before ever seeing the identifier.
$fName1 = $b3partner('Cobalt Works Ltd');                                  // name only, earlier
$fName2 = $b3partner('Cobalt Holdings Ltd', ['gstin' => '24PPPQQ8888P1Z9']); // identifier, later
$f8a = find_duplicate_partner('Cobalt Works Ltd', '24PPPQQ8888P1Z9', '', '', 0);
t_eq((string)($f8a['confidence'] ?? ''), 'EXACT', 'F9 . an identifier outranks a name match on an EARLIER record');
t_eq((int)($f8a['row']['id'] ?? 0), $fName2, 'F10 . and it points at the organisation the IDENTIFIER names');
$f8b = partner_find_or_problem('Cobalt Works Ltd', '24PPPQQ8888P1Z9', '', '');
t_eq((string)($f8b['confidence'] ?? ''), 'EXACT', 'F11 . the staff guard agrees');
//  And with no identifier in play, the same name is still only POSSIBLE.
t_eq((string)(find_duplicate_partner('Cobalt Works Ltd', '', '', '', 0)['confidence'] ?? ''), 'POSSIBLE',
     'F12 . the name on its own remains a POSSIBLE match, never proof');


// =============================================================================
t_section('P6-B3 · G — at most one primary contact per organisation (Q22)');
// =============================================================================
$gPid = $b3partner('Granite Works Ltd');
t_ok(function_exists('partner_contact_add'), 'G0 · a shared contact writer exists');
if (function_exists('partner_contact_add')) {
    $g1 = partner_contact_add($gPid, ['name' => 'First Person', 'email' => 'first@granite.test', 'is_primary' => 1]);
    $g2 = partner_contact_add($gPid, ['name' => 'Second Person', 'email' => 'second@granite.test', 'is_primary' => 1]);
    t_ok($g1 > 0 && $g2 > 0, 'G1 · both contacts are created');
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND is_primary=1", [$gPid]), 1,
         'G2 · exactly ONE primary remains');
    t_eq((int)ops_val("SELECT COALESCE(is_primary,0) FROM partner_contacts WHERE id=?", [$g2]), 1,
         'G3 · and it is the one most recently set');
    t_eq((int)ops_val("SELECT COALESCE(is_primary,0) FROM partner_contacts WHERE id=?", [$g1]), 0,
         'G4 · the previous primary was cleared, not deleted');
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE id=?", [$g1]), 1, 'G5 · and it is still there');
    // Zero primaries is a valid state.
    $gPid2 = $b3partner('Granite Two Ltd');
    partner_contact_add($gPid2, ['name' => 'Nobody Special', 'email' => 'nobody@granite2.test']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND is_primary=1", [$gPid2]), 0,
         'G6 · zero primaries is a valid state');
    // THE NEGATIVE THAT MATTERS: one person may be a contact at several organisations.
    partner_contact_add($gPid2, ['name' => 'First Person', 'email' => 'first@granite.test', 'is_primary' => 1]);
    t_eq((int)ops_val("SELECT COUNT(DISTINCT partner_id) FROM partner_contacts WHERE LOWER(email)='first@granite.test'"), 2,
         'G7 · ONE PERSON MAY BE A CONTACT AT SEVERAL ORGANISATIONS — no global e-mail rule');
}

//  WHAT IF THE DEMOTE ITSELF FAILS? The rule is only as good as its unhappy
//  path, so the failure is injected for real: a trigger that refuses the
//  demoting UPDATE. Whatever the function does then, the organisation must not
//  be left with two primary contacts.
$gTrg = false;
$gDrv = function_exists('t_driver') ? t_driver() : '';
try {
    if ($gDrv === 'sqlite')
        db()->exec("CREATE TRIGGER tmp_no_demote BEFORE UPDATE OF is_primary ON partner_contacts
                    BEGIN SELECT RAISE(ABORT, 'demote refused'); END");
    else
        db()->exec("CREATE TRIGGER tmp_no_demote BEFORE UPDATE ON partner_contacts FOR EACH ROW
                    BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'demote refused'; END");
    $gTrg = true;
} catch (Throwable $e) {}
if ($gTrg) {
    $gBefore = (int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)=1", [$gPid]);
    $gRes = 0;
    try { $gRes = partner_contact_add($gPid, ['name' => 'Third Person', 'email' => 'third@g.test', 'is_primary' => 1]); }
    catch (Throwable $e) { $gRes = 0; }
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)=1", [$gPid]),
         $gBefore, 'G8 . when the existing primary cannot be stood down, NO second primary appears');
    t_eq((int)$gRes, 0, 'G9 . and the caller is told it did not happen - no false success');
    try { db()->exec("DROP TRIGGER tmp_no_demote"); } catch (Throwable $e) {}
} else { t_ok(false, 'G8 . the failure could not be injected'); t_ok(false, 'G9 . same'); }

//  AND WHAT IF THE DEMOTE SAYS IT WORKED, BUT IT DID NOT?
//
//  "The UPDATE returned true" is not the same fact as "this organisation now
//  has one primary", and it is the second that the rule is about. Proved on
//  both engines with a trigger that quietly keeps the old primary: the
//  statement still reports success. Whatever else happens, two primaries must
//  not be the result.
$gTrg2 = false;
try {
    if ($gDrv === 'sqlite')
        db()->exec("CREATE TRIGGER tmp_restore_primary AFTER UPDATE OF is_primary ON partner_contacts
                    WHEN NEW.is_primary=0 BEGIN UPDATE partner_contacts SET is_primary=1 WHERE id=NEW.id; END");
    else
        db()->exec("CREATE TRIGGER tmp_restore_primary BEFORE UPDATE ON partner_contacts FOR EACH ROW
                    BEGIN SET NEW.is_primary = 1; END");
    $gTrg2 = true;
} catch (Throwable $e) {}
if ($gTrg2) {
    $gPid3 = $b3partner('Granite Three Ltd');
    db()->prepare("INSERT INTO partner_contacts (partner_id,name,email,is_primary) VALUES (?,?,?,1)")
        ->execute([$gPid3, 'Sitting Primary', 'sitting@g3.test']);
    $gRes2 = 0;
    try { $gRes2 = partner_contact_add($gPid3, ['name' => 'Would Be Primary', 'email' => 'would@g3.test', 'is_primary' => 1]); }
    catch (Throwable $e) { $gRes2 = 0; }
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)=1", [$gPid3]), 1,
         'G10 . a demote that REPORTS success but did not happen still leaves ONE primary');
    t_eq((int)$gRes2, 0, 'G11 . and the caller is told so, rather than given a success that is not true');
    try { db()->exec("DROP TRIGGER tmp_restore_primary"); } catch (Throwable $e) {}
} else { t_ok(false, 'G10 . the silent-restore probe could not be installed'); t_ok(false, 'G11 . same'); }

// =============================================================================
t_section('P6-B3 · H — the portal account boundary (Q23)');
// =============================================================================
// Established from the login query: WHERE LOWER(email)=? AND is_active=1, first
// row wins. So within ONE account table an ACTIVE e-mail must resolve to exactly
// one row. It is NOT a global person rule.
$hPid = $b3partner('Harbour Freight Ltd');
$hMail = 'ops@harbour.test';
$h1 = $b3one('invite', $hPid, $hMail, $uAdmin);
t_ok($h1['ok'] ?? false, 'H1 · a portal account is created  [' . ($h1['msg'] ?? '') . ']');
$h2 = $b3one('rawacct', $hPid, $hMail, $uAdmin);
t_ok(!($h2['ok'] ?? false), 'H2 · a RAW second active account with the same e-mail is rejected by the database');
t_eq($b3accts($hMail), 1, 'H3 · exactly one account carries that e-mail');
// A DEACTIVATED account keeps its e-mail — history is not constrained.
db()->prepare("UPDATE client_users SET is_active=0 WHERE LOWER(email)=?")->execute([$hMail]);
$h4 = $b3one('rawacct', $hPid, $hMail, $uAdmin);
t_ok($h4['ok'] ?? false, 'H4 · once the old account is deactivated the address may be used again');
t_eq($b3accts($hMail), 2, 'H5 · and the deactivated account is KEPT as history');
// The same address may hold a VENDOR account too — separate door, separate table.
$hV = $b3partner('Harbour Vendor Ltd');
db()->prepare("INSERT INTO vendor_users (vendor_id,email,name,password_hash,is_active,created_at) VALUES (?,?,?,?,1,?)")
    ->execute([$hV, $hMail, 'Same Human', password_hash('x', PASSWORD_DEFAULT), date('c')]);
t_eq((int)ops_val("SELECT COUNT(*) FROM vendor_users WHERE LOWER(email)=?", [$hMail]), 1,
     'H6 · THE SAME ADDRESS MAY ALSO HOLD A VENDOR ACCOUNT — no universal identity rule');

//  THE CONFLICT ITSELF. Inviting an address that already has an account is
//  answered by the check at the TOP of the invite, before the write - so
//  nothing above ever reaches the database rule, and nothing above proves that
//  hitting it produces a business answer rather than a crash. Only a race gets
//  there, so a race is what is used.
$hRaceMail = 'race@harbour.test';
$hRacePid  = $b3partner('Harbour Race Ltd');
$hRes = $b3race([['invite', $hRacePid, $hRaceMail, $uAdmin],
                 ['invite', $hRacePid, $hRaceMail, $uAdmin],
                 ['invite', $hRacePid, $hRaceMail, $uAdmin]]);
t_eq(count($hRes), 3, 'H7 . all three invitations reported a verdict');
t_eq($b3accts($hRaceMail), 1, 'H8 . three simultaneous invitations leave exactly ONE account');
$hOk = 0; $hCrash = 0;
foreach ($hRes as $r) { if (!empty($r['ok'])) $hOk++; if (($r['code'] ?? '') === 'EX') $hCrash++; }
t_eq($hOk, 1, 'H9 . exactly one succeeded');
t_eq($hCrash, 0, 'H10 . and NO process crashed - the database conflict became a business answer');
foreach ($hRes as $r)
    if (empty($r['ok'])) t_ok(trim((string)($r['msg'] ?? '')) !== '', 'H11 . a losing invitation was told something useful');

// =============================================================================
t_section('P6-B3 · I — organisation creation is audited (F8)');
// =============================================================================
$iName = 'Ironbridge Systems Ltd';
$i1 = $b3one('join', 0, json_encode(['name' => $iName, 'email' => 'i1@ironbridge.test']));
$iPid = (int)ops_val("SELECT id FROM business_partners WHERE legal_name=? ORDER BY id DESC LIMIT 1", [$iName]);
t_ok($iPid > 0, 'I0 · the organisation was created');
$iRow = ops_one("SELECT * FROM activities WHERE entity_kind='PARTNER' AND entity_id=? ORDER BY id DESC LIMIT 1", [$iPid]);
t_ok(is_array($iRow), 'I1 · it wrote an attributable activity against the organisation');
if (is_array($iRow)) t_ok(isset(ACT_KINDS[(string)$iRow['kind']]), 'I2 · with a registered activity kind');
// A refused registration is audited too — against the organisation it matched.
$iBefore = (int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$aPid]);
$b3one('join', 0, json_encode(['name' => 'Northwind Energy Ltd', 'email' => 'i3@nw.test']));
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$aPid]) > $iBefore,
     'I3 · a REFUSED public registration is audited against the organisation it matched');

// =============================================================================
t_section('P6-B3 · J — historical states are detected, never repaired (F9)');
// =============================================================================
$jA = $b3partner('Juniper Cables Ltd', ['gstin' => '24LLLMM6666L1Z9']);
$jB = $b3partner('Juniper Cables Limited', ['gstin' => '24LLLMM6666L1Z9']);   // same tax id, planted
$jSnap = (int)ops_val("SELECT COUNT(*) FROM business_partners");
$jRows = function_exists('identity_state_findings') ? identity_state_findings() : [];
$jKinds = array_column($jRows, 'kind');
t_ok(in_array('PARTNER_DUPLICATE_TAXID', $jKinds, true), 'J1 · two organisations sharing a tax identifier are detected');
$jOne = null; foreach ($jRows as $r) if (($r['kind'] ?? '') === 'PARTNER_DUPLICATE_TAXID') { $jOne = $r; break; }
if (is_array($jOne)) foreach (['kind','what','records','why','safe_to_repair','needs_human'] as $k)
    t_ok(array_key_exists($k, $jOne), "J2 · the finding states '$k'");
//  Having the keys is not the same as giving the right answers. Deciding that
//  two organisations are one company is a business judgement with invoices,
//  jobs and contracts hanging off it: this finding must never say it is safe to
//  put right on its own, and must always say a person is needed.
if (is_array($jOne)) {
    t_ok(empty($jOne['safe_to_repair']), 'J2a . a duplicate tax identifier is NEVER safe to repair automatically');
    t_ok(!empty($jOne['needs_human']),   'J2b . and it always says a person must decide');
}
//  Across the whole report: nothing that merges organisations may be marked
//  safe to do without a person.
foreach ($jRows as $r)
    if (in_array((string)($r['kind'] ?? ''), ['PARTNER_DUPLICATE_TAXID','PARTNER_POSSIBLE_DUPLICATE_NAME',
                                              'MARKETPLACE_MULTIPLE_FOR_PARTY','CONTACT_DUPLICATE',
                                              'CONTACT_MULTIPLE_PRIMARY','ACCOUNT_DUPLICATE_ACTIVE'], true)) {
        t_ok(empty($r['safe_to_repair']) && !empty($r['needs_human']),
             'J2c . every finding that would merge or choose between records needs a person: ' . (string)$r['kind']);
        break;
    }
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners"), $jSnap, 'J3 · DETECTION CHANGED NOTHING');
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners WHERE id IN (?,?)", [$jA, $jB]), 2,
     'J4 · both organisations are still there — nothing was merged');
//  LEGITIMATE MULTIPLES MUST NOT BE REPORTED AS DUPLICATES.
//
//  The first version of this assertion looked for the kind 'AGENCY_DUPLICATE',
//  which no code anywhere produces — so it could never fail, whatever the
//  report did. Mutation M26 proved that by planting exactly this defect and
//  walking past it. What matters is the PROPERTY, not one spelling: two agency
//  contracts for one organisation are two contracts, and nothing that names an
//  agency may be reported as a duplicate at all.
//  THE PROBE MUST HAVE SOMETHING TO LOOK AT. Section D maps both of its agency
//  contracts to their organisation, and the suggestion only fires for an
//  UNMAPPED one — so the loop below had nothing to walk and passed whatever the
//  report said. Mutation M26 went through it twice. An agency that really does
//  look like an organisation we know is planted here, and its presence is
//  asserted BEFORE anything is concluded from its absence.
$jAgGst = '24TTTUU1212T1Z9';
$jAgParty = $b3partner('Juniper Manpower Services Ltd', ['gstin' => $jAgGst]);
db()->prepare("INSERT INTO agencies (name,agency_type,gstin,active,created_at) VALUES (?, 'MANPOWER', ?, 1, ?)")
    ->execute(['Juniper Manpower Services Ltd', $jAgGst, date('c')]);
$jAgId = (int)db()->lastInsertId();
$jRows = identity_state_findings();                       // re-read, now that there is one
$jKinds = array_column($jRows, 'kind');
t_ok(in_array('AGENCY_POSSIBLE_ORGANISATION', $jKinds, true),
     'J5a · an unmapped agency that matches an organisation IS reported — the probe has something to judge');

$jAgencyBad = []; $jAgencyKinds = [];
foreach ($jRows as $r) {
    $rec = (array)($r['records'] ?? []);
    if (!array_key_exists('agency', $rec)) continue;
    $jAgencyKinds[] = (string)($r['kind'] ?? '');
    if (stripos((string)($r['kind'] ?? ''), 'DUPLICATE') !== false) $jAgencyBad[] = (string)$r['kind'];
}
t_eq(count($jAgencyBad), 0, 'J5 · NOTHING that names an agency contract is reported as a duplicate'
     . ($jAgencyBad ? ' [' . implode(',', $jAgencyBad) . ']' : ''));
foreach ($jAgencyKinds as $k)
    t_eq($k, 'AGENCY_POSSIBLE_ORGANISATION', 'J5b · the only thing said about an agency is that it MIGHT be one we know');
t_eq((int)ops_val("SELECT COALESCE(party_id,0) FROM agencies WHERE id=?", [$jAgId]), 0,
     'J5d · and reporting it did NOT map it — a suggestion is not an action');
//  And the two contracts planted in section D are both still whole.
t_eq((int)ops_val("SELECT COUNT(*) FROM agencies WHERE party_id=?", [$dPid]), 2,
     'J5c · both agency contracts survive the report untouched');

// =============================================================================
t_section('P6-B3 · K — authorisation and forged ids');
// =============================================================================
$kPid = $b3partner('Kingsley Marine Ltd');
$uField = 0;
db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active)
               VALUES ('b3.field',?,'B3','Field','b3field@x.test','INSPECTOR',0,1)")
    ->execute([password_hash('x', PASSWORD_DEFAULT)]);
$uField = (int)db()->lastInsertId();
$kBefore = (int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=?", [$kPid]);
$k1 = $b3one('invite', $kPid, 'forged@kingsley.test', $uField);
t_ok(!($k1['ok'] ?? false), 'K1 · an actor without the right cannot invite a portal account  [' . ($k1['msg'] ?? '') . ']');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=?", [$kPid]), $kBefore,
     'K2 · and no account was created (state, not return code)');
// A forged organisation id must not create an account against an organisation
// that does not exist.
$k3 = $b3one('invite', 987654321, 'ghost@nowhere.test', $uAdmin);
t_ok(!($k3['ok'] ?? false), 'K3 · a forged organisation id is refused  [' . ($k3['msg'] ?? '') . ']');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=987654321"), 0, 'K4 · and nothing was written');

//  THE OTHER AUTHORITY. A client's own admin may invite a colleague - into
//  THEIR OWN organisation and nowhere else. Nothing above ever acts as one, so
//  nothing above proves the boundary; this does, by becoming one.
$kOrgA = $b3partner('Kepler Marine Ltd');
$kOrgB = $b3partner('Larkspur Shipping Ltd');
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,is_org_admin,created_at)
               VALUES (?,?,?,?,1,1,?)")
    ->execute([$kOrgA, 'admin@kepler.test', 'Kepler Admin', password_hash('x', PASSWORD_DEFAULT), date('c')]);
$kAdminId = (int)db()->lastInsertId();

unset($_SESSION['uid']); current_user(true); ua(true);   // no longer staff
$_SESSION['cuid'] = $kAdminId;
t_ok(function_exists('cvp_client_is_admin') && cvp_client_is_admin(), 'K5 . the probe really is signed in as a client admin');
t_ok(!portal_can_manage(), 'K6 . and is NOT staff - so only the client-admin authority is in play');

$kOwn = portal_invite($kOrgA, 'colleague@kepler.test', 'A Colleague', 0);
t_ok(empty($kOwn['err']), 'K7 . a client admin CAN invite a colleague into their own organisation  [' . (string)($kOwn['err'] ?? '') . ']');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=? AND LOWER(email)='colleague@kepler.test'", [$kOrgA]), 1,
     'K8 . and the account belongs to their organisation');

$kOther = portal_invite($kOrgB, 'intruder@larkspur.test', 'Not Theirs', 0);
t_ok(!empty($kOther['err']), 'K9 . but NOT into somebody else organisation');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=?", [$kOrgB]), 0,
     'K10 . and nothing was written there - state, not return code');

unset($_SESSION['cuid']); $b3act($uAdmin);               // back to staff for what follows

// =============================================================================
t_section('P6-B3 · L — the duplicate detector is tenant-bounded');
// =============================================================================
// Tenancy is structural (one database per tenant) and was TESTED in Batch 1 with
// two real tenant databases. Here we assert the organisation paths add no way
// around it: every lookup goes through db(), and the detector reads one table.
$lSrc = (string)@file_get_contents($b3root . '/lib/ops.php');
t_ok(strpos($lSrc, 'function find_duplicate_partner') !== false, 'L1 · the detector lives in one place');
$lDetector = substr($lSrc, strpos($lSrc, 'function find_duplicate_partner'), 2000);
t_ok(strpos($lDetector, 'new PDO') === false && strpos($lDetector, 'db(true)') === false,
     'L2 · it opens no second connection and switches no tenant');

// =============================================================================
t_section('P6-B3 · M — the public route under attack');
// =============================================================================
//  The refusal must not become an ORACLE. Whatever matched — a tax identifier
//  or a name — the visitor must read the same sentence, or the difference
//  itself tells them which of the two they hit.
$mPid = $b3partner('Maybury Chemicals Ltd', ['gstin' => '24MMMNN6666M1Z9']);
$mExact  = $b3one('join', 0, json_encode(['name' => 'Some Other Name Ltd', 'email' => 'm1@mb.test', 'gstin' => '24MMMNN6666M1Z9']));
$mByName = $b3one('join', 0, json_encode(['name' => 'Maybury Chemicals Ltd', 'email' => 'm2@mb.test']));
t_ok(!($mExact['ok'] ?? false) && !($mByName['ok'] ?? false), 'M1 · both kinds of match are refused');
t_eq((string)($mByName['msg'] ?? 'x'), (string)($mExact['msg'] ?? 'y'),
     'M2 · and the visitor reads the SAME sentence — the reply is not an oracle');

//  A public visitor must not be able to award themselves a role, a status or an
//  approval by adding fields to the form.
$mName = 'Quarry Logistics Pvt Ltd';
$b3one('join', 0, json_encode(['name' => $mName, 'email' => 'm3@quarry.test',
       'org_type' => 'ENTERPRISE', 'is_vendor' => 1, 'is_subcontractor' => 1,
       'status' => 'PREMIUM', 'party_id' => 999999, 'approved_by' => 'me', 'perms' => 'admin']));
$mRow = ops_one("SELECT * FROM business_partners WHERE legal_name=? ORDER BY id DESC LIMIT 1", [$mName]);
t_ok(is_array($mRow), 'M3 · the organisation registered');
if (is_array($mRow)) {
    t_eq((int)$mRow['is_vendor'], 0, 'M4 · it could not award itself the vendor role');
    t_eq((int)$mRow['is_subcontractor'], 0, 'M5 · nor the sub-contractor role');
    t_eq((string)$mRow['status'], 'ACTIVE', 'M6 · nor a status of its own choosing');
    $mAcct = ops_one("SELECT * FROM client_users WHERE LOWER(email)='m3@quarry.test'");
    t_ok(is_array($mAcct) && (string)($mAcct['perms'] ?? '') !== 'admin', 'M7 · nor permissions of its own choosing');
    t_eq((int)($mAcct['partner_id'] ?? 0), (int)$mRow['id'], 'M8 · the account belongs to the organisation that was created, not the one it named');
}

//  An identifier the company gives us is KEPT, or the one check that cannot be
//  argued with has nothing to match against next time.
$mKeep = 'Redhill Ceramics Pvt Ltd';
$b3one('join', 0, json_encode(['name' => $mKeep, 'email' => 'm11@redhill.test', 'gstin' => '24RRRSS7777R1Z9']));
t_eq((string)ops_val("SELECT gstin FROM business_partners WHERE legal_name=? ORDER BY id DESC LIMIT 1", [$mKeep]),
     '24RRRSS7777R1Z9', 'M11 · a tax identifier given at registration is stored, not read and discarded');
$mAgain = $b3one('join', 0, json_encode(['name' => 'Redhill Ceramics Limited Trading', 'email' => 'm12@redhill.test', 'gstin' => '24RRRSS7777R1Z9']));
t_ok(!($mAgain['ok'] ?? false), 'M12 · so the SAME company under another name is refused the next time');

//  A name is data, never instruction — on both engines.
$mSqlName = "Bobby'); DROP TABLE business_partners;--";
$mInj = $b3one('join', 0, json_encode(['name' => $mSqlName, 'email' => 'm9@inj.test']));
t_ok((int)ops_val("SELECT COUNT(*) FROM business_partners") > 0, 'M9 · the table is still there');
t_eq($b3parts($mSqlName), ($mInj['ok'] ?? false) ? 1 : 0, 'M10 · the name was stored literally, as data');

$_SESSION = $b3sess; current_user(true); ua(true);   // restored LAST, deliberately
