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
t_ok(!($c1['ok'] ?? false), 'C1 · a registration whose account cannot be created is refused');
t_eq($b3parts($cName), $cPartsBefore, 'C2 · and NO orphan business partner was left behind');
t_eq($b3orgs($cName), $cOrgsBefore, 'C3 · and NO orphan marketplace organisation was left behind');

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
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners"), $jSnap, 'J3 · DETECTION CHANGED NOTHING');
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners WHERE id IN (?,?)", [$jA, $jB]), 2,
     'J4 · both organisations are still there — nothing was merged');
// Legitimate multiples must NOT be reported as duplicates.
t_ok(!in_array('AGENCY_DUPLICATE', $jKinds, true),
     'J5 · two agency contracts for one organisation are not reported as a duplicate');

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

$_SESSION = $b3sess; current_user(true); ua(true);   // restored LAST, deliberately
