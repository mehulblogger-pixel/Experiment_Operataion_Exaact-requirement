<?php
// ============================================================================
//  PHASE 6 · BATCH 3 — CORRECTIVE IMPLEMENTATION
//
//  Six defects found by the post-gate adversarial audit, and the owner's
//  decisions Q24–Q28 with rules R1–R6. Every assertion here states a behaviour
//  the audit proved was NOT true of the shipped code.
//
//    CA  one primary contact, enforced by the database    A1 · Q24 · §4 · §5
//    CB  the canonical form of an e-mail address          A2 · Q25 · §8
//    CC  the public form is not a lookup service          A3 · Q26 · §9
//    CD  security evidence is not customer activity       A4 · Q27 · §10
//    CE  company status                                   A5 · Q28 · R1–R6
//    CF  merge safety and survivor resolution             R4 · §12
//    CG  the migration is idempotent and honest           A6 · §6 · §7
//    CH  authorisation and tenant isolation               §15 · §16
//
//  Rules this file holds itself to, learned the hard way in this batch:
//    · every probe must have a SUBJECT. An assertion with nothing to act on
//      passes while proving nothing, and seven of my own instruments did
//      exactly that. Where a probe needs data, it creates it and then proves
//      the data is there.
//    · the DATABASE is the evidence. A return code is not.
// ============================================================================

$ccSess = $_SESSION;

$ccRoot = dirname(__DIR__);
$ccEnv = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
//  Real operating-system processes, released together on a wall-clock barrier.
//  Threads inside one PHP process would share a connection and prove nothing.
$ccRace = function (array $specs, $leadMs = 1100) use ($ccRoot, $ccEnv) {
    $t = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $ccEnv() . 'php ' . escapeshellarg($ccRoot . '/tests/_p6b3_worker.php') . ' '
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
$ccPartner = function ($name, $opt = []) {
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,gstin,pan,created_at)
                   VALUES (?,?,?,1,?,?,?,?)")
        ->execute([$opt['code'] ?? ('CC-' . random_int(100000, 999999)), $name, $name,
                   $opt['status'] ?? 'ACTIVE', $opt['gstin'] ?? '', $opt['pan'] ?? '', date('c')]);
    return (int)db()->lastInsertId();
};
$ccPrimaries = function ($pid) {
    return (int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)<>0", [(int)$pid]);
};
$ccContacts = function ($pid) {
    return (int)ops_val("SELECT COUNT(*) FROM partner_contacts WHERE partner_id=?", [(int)$pid]);
};

// =============================================================================
t_section('P6-B3C · CA — one primary contact, enforced by the database (A1 · Q24)');
// =============================================================================
partner_contact_migrate();
$caGuard = schema_guard_read('uq_pcont_primary');
t_ok(is_array($caGuard), 'CA0 · the guard recorded what it did — the protection is not a matter of faith');
t_eq((string)($caGuard['state'] ?? ''), 'OK', 'CA1 · and it reports the rule is actually installed here');
t_ok(in_array('uq_pcont_primary', table_index_names('partner_contacts'), true),
     'CA2 · a UNIQUE index really exists on the table — not merely a fast lookup');
t_ok(in_array('uq_primary', table_columns_incl_generated('partner_contacts'), true),
     'CA3 · and the key it covers is computed by the database, so no writer can forget it');

//  SEQUENTIAL — the case the old PHP-only code did handle.
$caP = $ccPartner('Ardent Marine Ltd');
partner_contact_add($caP, ['name' => 'First Person', 'is_primary' => 1]);
partner_contact_add($caP, ['name' => 'Second Person', 'is_primary' => 1]);
t_eq($ccPrimaries($caP), 1, 'CA4 · setting a second main contact in sequence leaves exactly one');
t_eq($ccContacts($caP), 2, 'CA5 · and nobody was deleted to achieve it — both people are still on file');

//  RAW SQL — the case no amount of application code can cover.
$caRaw = false;
try { db()->prepare("INSERT INTO partner_contacts (partner_id,name,is_primary) VALUES (?,?,1)")->execute([$caP, 'Straight To The Table']); }
catch (Throwable $e) { $caRaw = true; }
t_ok($caRaw, 'CA6 · *** even a direct INSERT claiming to be primary is REFUSED by the database ***');
t_eq($ccPrimaries($caP), 1, 'CA7 · so the organisation still has exactly one main contact');

//  ORDINARY CONTACTS must stay unlimited — a guard that over-reaches is a bug.
for ($i = 0; $i < 4; $i++) partner_contact_add($caP, ['name' => 'Ordinary ' . $i]);
t_eq($ccPrimaries($caP), 1, 'CA8 · adding ordinary contacts does not disturb the main one');
t_ok($ccContacts($caP) >= 6, 'CA9 · and an organisation may hold as many ordinary contacts as it likes');

//  TWO CONCURRENT WRITERS (Scenario A) — real processes, released together.
$caP2 = $ccPartner('Belmont Survey Ltd');
$caR2 = $ccRace([['setprimary', $caP2, 'Racer A'], ['setprimary', $caP2, 'Racer B']]);
t_eq(count($caR2), 2, 'CA10 · both processes reported a verdict');
t_eq($ccPrimaries($caP2), 1, 'CA11 · *** two concurrent writers leave exactly ONE main contact ***');
t_ok($ccContacts($caP2) >= 1, 'CA12 · and the probe had a real subject — contacts were written');

//  THREE CONCURRENT WRITERS (Scenario B) — the audit produced THREE primaries.
$caP3 = $ccPartner('Cranfield Testing Ltd');
$caR3 = $ccRace([['setprimary', $caP3, 'Racer A'], ['setprimary', $caP3, 'Racer B'], ['setprimary', $caP3, 'Racer C']]);
t_eq(count($caR3), 3, 'CA13 · all three processes reported a verdict');
t_eq($ccPrimaries($caP3), 1, 'CA14 · *** three concurrent writers leave exactly ONE main contact ***');
$caCrash = 0; foreach ($caR3 as $r) if (($r['code'] ?? '') === 'EX') $caCrash++;
t_eq($caCrash, 0, 'CA15 · and no process crashed — losing the race is an answer, not an error');

//  THREE RAW WRITERS — no application code at all.
$caP4 = $ccPartner('Dunmore Inspection Ltd');
$ccRace([['rawprimary', $caP4, 'Raw A'], ['rawprimary', $caP4, 'Raw B'], ['rawprimary', $caP4, 'Raw C']]);
t_eq($ccPrimaries($caP4), 1, 'CA16 · *** three concurrent RAW inserts still leave exactly ONE ***');

//  DIRTY DATA (Scenario C) — a workspace that already breaks the rule.
//  Built by dropping the guard, planting the mess, and putting it back — which
//  is exactly what an upgrade from the shipped build looks like.
$caP5 = $ccPartner('Eastgate Marine Ltd');
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX uq_pcont_primary" : "DROP INDEX uq_pcont_primary ON partner_contacts"); } catch (Throwable $e) {}
foreach (['Dirty One', 'Dirty Two', 'Dirty Three'] as $n)
    db()->prepare("INSERT INTO partner_contacts (partner_id,name,is_primary) VALUES (?,?,1)")->execute([$caP5, $n]);
t_eq($ccPrimaries($caP5), 3, 'CA17 · the dirty-data probe really does have three main contacts to repair');
$caLowest = (int)ops_val("SELECT MIN(id) FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)<>0", [$caP5]);
//  Re-run the migration over the mess.
$caRes = ensure_unique_generated_index('partner_contacts', 'uq_primary', PARTNER_PRIMARY_KEY_EXPR,
                                       'uq_pcont_primary', 'partner_contact_reconcile_primaries');
t_eq((string)$caRes, 'OK', 'CA18 · the migration reconciled the mess and then installed the rule');
t_eq($ccPrimaries($caP5), 1, 'CA19 · exactly one main contact survives');
t_eq($ccContacts($caP5), 3, 'CA20 · *** and NOBODY was deleted — all three people are still on file ***');
t_eq((int)ops_val("SELECT id FROM partner_contacts WHERE partner_id=? AND COALESCE(is_primary,0)<>0", [$caP5]), $caLowest,
     'CA21 · the survivor was chosen deterministically — the earliest record, never at random');
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=? AND subject LIKE '%main contact%'", [$caP5]) > 0,
     'CA22 · and the repair was written to the organisation trail, not done silently');

// =============================================================================
t_section('P6-B3C · CB — the canonical form of an e-mail address (A2 · Q25)');
// =============================================================================
t_eq(email_key('  USER@Example.COM  '), 'user@example.com', 'CB0 · the canonical rule is LOWER(TRIM(...))');
t_eq(email_key("\tuser@example.com\n"), 'user@example.com', 'CB1 · and it also clears tabs and newlines before they reach the database');

$cbP = $ccPartner('Fenwick Logistics Ltd');
t_as_admin();
$cbBase = 'ann.fenwick@example.test';
$cb1 = portal_invite($cbP, $cbBase, 'Ann Fenwick', 0);
t_ok(empty($cb1['err']), 'CB2 · the first invitation is accepted — the probe has a subject  [' . ($cb1['err'] ?? '') . ']');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=?", [$cbBase]), 1, 'CB3 · exactly one account exists for that address');

//  The four disguises the audit used to create shadow accounts.
$cbTries = [' ' . $cbBase => 'a leading space', $cbBase . ' ' => 'a trailing space',
            strtoupper($cbBase) => 'capitals', "  " . strtoupper($cbBase) . "  " => 'capitals and spaces at both ends'];
foreach ($cbTries as $cbTry => $cbWhat) {
    $r = portal_invite($cbP, $cbTry, 'Impostor', 0);
    t_ok(!empty($r['err']), 'CB · ' . $cbWhat . ' is recognised as the SAME address and refused');
}
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=?", [$cbBase]), 1,
     'CB4 · *** still exactly ONE account — no shadow account was created ***');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE email <> TRIM(email)"), 0,
     'CB5 · and no account anywhere was stored with whitespace around its address');

//  The stored value is canonical, so the sign-in door and the database agree.
t_eq((string)ops_val("SELECT email FROM client_users WHERE LOWER(TRIM(email))=?", [$cbBase]), $cbBase,
     'CB6 · the address is stored in its canonical form');

//  A CONTACT saved with whitespace is still seen as the same person.
partner_contact_add($cbP, ['name' => 'Ann Fenwick', 'email' => '  ANN.FENWICK@example.test ']);
t_eq((string)ops_val("SELECT email FROM partner_contacts WHERE partner_id=? ORDER BY id DESC LIMIT 1", [$cbP]), $cbBase,
     'CB7 · a contact address is canonicalised on the way in too');

//  ACCOUNT CREATION through the public door.
[$cbOk] = connect_org_register(['name' => 'Garrick Freight Ltd', 'org_type' => 'COMPANY',
                                'contact_email' => '  BOB@garrick.test  ', 'password' => 'password123']);
t_ok($cbOk, 'CB8 · a public sign-up with a padded address is accepted');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE email='bob@garrick.test'"), 1,
     'CB9 · and the account it creates is stored canonically');
t_ok(is_array(portal_login('  BOB@garrick.test ', 'password123')) || portal_login('bob@garrick.test', 'password123') !== null,
     'CB10 · the person can sign in however they type their own address');

//  CM8 · §6 — THE DATABASE, ON ITS OWN.
//
//  Everything above goes through the application, so it proves email_key() works
//  — which was never in doubt. The battery showed that removing TRIM from the
//  DATABASE key broke nothing any test could see, because no probe ever wrote to
//  the table without the application's help. The database key exists precisely
//  for the writer who forgets, so it is tested without the writer.
//
//  A LEADING space, deliberately: MariaDB ignores TRAILING spaces when comparing
//  strings, so a trailing-space probe would pass on that engine for a reason
//  that has nothing to do with our key.
$cbRaw = 'raw.backstop@example.test';
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at)
               VALUES (?,?,?,'',1,?)")->execute([$cbP, $cbRaw, 'Genuine', date('c')]);
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=?", [$cbRaw]), 1,
     'CB11 · the backstop probe has a subject — one genuine account exists');
$cbRefused = false;
try {
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at)
                   VALUES (?,?,?,'',1,?)")->execute([$cbP, ' ' . $cbRaw, 'Impostor', date('c')]);
} catch (Throwable $e) { $cbRefused = true; }
t_ok($cbRefused, 'CB12 · *** the DATABASE ITSELF refuses a padded duplicate written straight to the table ***');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=?", [$cbRaw]), 1,
     'CB13 · so still exactly one account for that address, with no application code involved');

//  The upgrade case: a workspace that already holds a padded row written before
//  this rule existed. The protection must still hold when the clean form arrives.
$cbLegacy = 'legacy.padded@example.test';
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX ux_client_users_active_email"
                                         : "DROP INDEX ux_client_users_active_email ON client_users"); } catch (Throwable $e) {}
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at)
               VALUES (?,?,?,'',1,?)")->execute([$cbP, ' ' . $cbLegacy, 'Legacy', date('c')]);
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE email=?", [' ' . $cbLegacy]), 1,
     'CB14 · the legacy probe has a subject — a padded row really is on file');
//  The guard is rebuilt through the helper, NOT through portal_acct_migrate():
//  that door keeps a static epoch marker and has already run in this file, so it
//  would return without doing anything and leave this probe with no subject —
//  which is exactly what it did on the first attempt.
$cbExpr = "CASE WHEN COALESCE(is_active,0)=1 AND TRIM(COALESCE(email,''))<>'' THEN LOWER(TRIM(email)) ELSE NULL END";
$cbGuard = ensure_unique_generated_index('client_users', 'uq_active_email', $cbExpr, 'ux_client_users_active_email');
t_eq((string)$cbGuard, 'OK', 'CB14a · and the uniqueness key really was rebuilt over that data');
$cbLegacyRefused = false;
try {
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,created_at)
                   VALUES (?,?,?,'',1,?)")->execute([$cbP, $cbLegacy, 'Clean', date('c')]);
} catch (Throwable $e) { $cbLegacyRefused = true; }
t_ok($cbLegacyRefused,
     'CB15 · *** a legacy padded row still blocks the clean duplicate — the key reads both the same ***');
//  Put the workspace back as it was found, so later sections are not judging a
//  database this probe dirtied.
db()->prepare("DELETE FROM client_users WHERE email IN (?,?)")->execute([' ' . $cbLegacy, $cbLegacy]);
ensure_unique_generated_index('client_users', 'uq_active_email', $cbExpr, 'ux_client_users_active_email');

//  §8 — the vendor door uses the same rule as every other door. The battery
//  cannot see a difference today, because the hand-rolled copy agreed with the
//  canonical rule character for character. That is what made it worth removing:
//  the next change to email_key() would have moved every other call site and
//  quietly left this one behind.
$cbVend = $ccPartner('Halifax Supplies Ltd');
db()->prepare("UPDATE business_partners SET is_vendor=1 WHERE id=?")->execute([$cbVend]);
$cbV1 = cvp_vendor_invite($cbVend, 'buyer@halifax.test', 'Buyer', 0);
t_ok(empty($cbV1['err']), 'CB16 · a vendor invitation is accepted — the probe has a subject  [' . ($cbV1['err'] ?? '') . ']');
$cbV2 = cvp_vendor_invite($cbVend, '  BUYER@halifax.test ', 'Impostor', 0);
t_ok(!empty($cbV2['err']), 'CB17 · and the same address padded and capitalised is recognised as the same person');
t_eq((int)ops_val("SELECT COUNT(*) FROM vendor_users WHERE LOWER(TRIM(email))=?", ['buyer@halifax.test']), 1,
     'CB18 · so the vendor door creates exactly one account, like every other door');

// =============================================================================
t_section('P6-B3C · CC — the public form is not a lookup service (A3 · Q26)');
// =============================================================================
$ccKnown = $ccPartner('Hawkridge Energy Ltd', ['gstin' => '24HHHKK1111H1Z9', 'pan' => 'HHHKK1111H']);
$ccNew  = connect_org_register(['name' => 'Ilford Marine Services Ltd', 'org_type' => 'COMPANY',
                                'contact_email' => 'new@ilford.test', 'password' => 'password123']);
$ccByName = connect_org_register(['name' => 'Hawkridge Energy Ltd', 'org_type' => 'COMPANY',
                                  'contact_email' => 'x1@hawk.test', 'password' => 'password123']);
$ccByTax  = connect_org_register(['name' => 'Totally Unrelated Ltd', 'org_type' => 'COMPANY',
                                  'contact_email' => 'x2@hawk.test', 'gstin' => '24HHHKK1111H1Z9', 'password' => 'password123']);
t_ok($ccNew[0], 'CC0 · a genuinely new company registers — the probe has a positive case');
t_eq((string)$ccByName[1], (string)$ccNew[1], 'CC1 · *** a KNOWN company by name reads exactly like a new one ***');
t_eq((string)$ccByTax[1],  (string)$ccNew[1], 'CC2 · *** and a KNOWN company by tax identifier does too ***');
t_eq((bool)$ccByName[0], (bool)$ccNew[0], 'CC3 · the verdict is the same, so the SHAPE of the answer says nothing either');
t_eq((bool)$ccByTax[0],  (bool)$ccNew[0], 'CC4 · for both kinds of match');
//  The payload is what the page is built from, so it must not differ in KIND.
t_eq(implode(',', array_keys((array)$ccByTax[2])), implode(',', array_keys((array)$ccNew[2])),
     'CC5 · the page is built from the same fields, so it cannot differ in size');
//  Nothing created.
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name=?", ['Totally Unrelated Ltd']), 0,
     'CC6 · and NOTHING was created for the matched attempt');
t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE email='x2@hawk.test'"), 0, 'CC7 · not even a portal account');
//  Nothing disclosed.
$ccBlob = strtolower(json_encode([$ccByTax[1], $ccByTax[2]]));
foreach (['hawkridge', '24hhhkk1111h1z9', 'hhhkk1111h', (string)$ccKnown, 'exact', 'possible', 'gstin', 'merged', 'active']
         as $ccSecret)
    t_ok(strpos($ccBlob, $ccSecret) === false, 'CC · the public answer does not disclose "' . $ccSecret . '"');
//  The request was nonetheless captured for a human.
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_access_requests WHERE email='x2@hawk.test' AND status='PENDING'"), 1,
     'CC8 · a controlled access request was raised instead — the person is not simply lost');
t_eq((int)ops_val("SELECT partner_id FROM cx_access_requests WHERE email='x2@hawk.test'"), $ccKnown,
     'CC9 · and it names the organisation for the staff member who will deal with it');

// =============================================================================
t_section('P6-B3C · CD — security evidence is not customer activity (A4 · Q27)');
// =============================================================================
$cdFeed = fn() => (int)ops_val("SELECT COUNT(*) FROM activities WHERE partner_id=?", [$ccKnown]);
$cdBefore = $cdFeed();
$cdSecBefore = (int)ops_val("SELECT COUNT(*) FROM portal_audit WHERE action='JOIN_BLOCKED'");
for ($i = 0; $i < 12; $i++)
    connect_org_register(['name' => 'Hawkridge Energy Ltd', 'org_type' => 'COMPANY',
                          'contact_email' => 'flood' . $i . '@hawk.test', 'password' => 'password123']);
t_eq($cdFeed(), $cdBefore, 'CD0 · *** twelve anonymous attempts wrote NOTHING to the customer-visible feed ***');
t_ok((int)ops_val("SELECT COUNT(*) FROM portal_audit WHERE action='JOIN_BLOCKED'") >= $cdSecBefore + 12,
     'CD1 · but every one of them IS retained as security evidence');
t_ok((int)ops_val("SELECT COUNT(*) FROM portal_audit WHERE action='JOIN_BLOCKED' AND partner_id=?", [$ccKnown]) > 0,
     'CD2 · the evidence carries the tenant context an investigator needs');
t_eq((int)ops_val("SELECT COUNT(*) FROM portal_audit WHERE action='JOIN_BLOCKED' AND COALESCE(client_user_id,0)<>0"), 0,
     'CD3 · and never pretends an unauthenticated stranger was a signed-in user');
//  The genuine history is still visible — the thing the flood used to destroy.
$cdGen = (int)ops_val("SELECT COUNT(*) FROM activities WHERE partner_id=?", [$ccKnown]);
act_log('PARTNER', $ccKnown, 'NOTE', 'A genuine business note');
t_eq((int)ops_val("SELECT COUNT(*) FROM activities WHERE partner_id=?", [$ccKnown]), $cdGen + 1,
     'CD4 · and real business activity still records normally');

// =============================================================================
t_section('P6-B3C · CE — company status (A5 · Q28 · R1–R6)');
// =============================================================================
//  A5 recorded: the earlier probe planted CLOSED and SUSPENDED, which are NOT
//  company statuses in this product. The real vocabulary is below. Testing
//  values the product cannot produce is how an instrument passes while proving
//  nothing, so the values used here are the ones a person can actually choose,
//  plus the one only the merge writes, plus the two that must fail safe.
t_ok(!array_key_exists('CLOSED', STATUSES) && !array_key_exists('SUSPENDED', STATUSES),
     'CE0 · CLOSED and SUSPENDED are confirmed NOT to be company statuses');
foreach (['ACTIVE', 'INACTIVE', 'ON_HOLD', 'BLACKLISTED', 'PROSPECT'] as $ceKnown)
    t_ok(array_key_exists($ceKnown, STATUSES), 'CE · "' . $ceKnown . '" IS a real company status');

//  R2 · R3 · R5 — every status except MERGED still protects the organisation.
$ceN = 0;
//  'CUSTOM_CODE' stands for a status a tenant invented in their own master
//  list. Kept within the column width on purpose — MariaDB silently truncates a
//  longer one, which would make the probe assert something the database never
//  stored. An instrument that tests a value the system cannot hold proves
//  nothing, which is the lesson this whole batch keeps teaching.
foreach (['ACTIVE', 'INACTIVE', 'ON_HOLD', 'BLACKLISTED', 'PROSPECT', 'CUSTOM_CODE', ''] as $ceS) {
    $ceN++;
    $ceGst = '24CE' . str_pad((string)$ceN, 3, '0', STR_PAD_LEFT) . 'ZZ99Z' . $ceN;
    $cePid = $ccPartner('Statusprobe ' . $ceN . ' Ltd', ['status' => $ceS, 'gstin' => $ceGst]);
    t_eq((string)ops_val("SELECT COALESCE(status,'') FROM business_partners WHERE id=?", [$cePid]), $ceS,
         'CE · the "' . ($ceS ?: 'blank') . '" probe really is stored with that status');
    $ceHit = find_duplicate_partner('Quite A Different Name Ltd', $ceGst, '', '', 0);
    t_ok($ceHit && (int)$ceHit['row']['id'] === $cePid,
         'CE · a company with status "' . ($ceS ?: 'blank') . '" is STILL matched — status never allows more');
    [$ceOk] = connect_org_register(['name' => 'Second Go ' . $ceN . ' Ltd', 'org_type' => 'COMPANY',
                                    'contact_email' => 'ce' . $ceN . '@probe.test', 'gstin' => $ceGst, 'password' => 'password123']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name=?", ['Second Go ' . $ceN . ' Ltd']), 0,
         'CE · and a public sign-up under status "' . ($ceS ?: 'blank') . '" still creates nothing');
}
//  A NULL status must fail safe too.
$ceNull = $ccPartner('Nullstatus Ltd', ['gstin' => '24NULL9999N1Z9']);
db()->prepare("UPDATE business_partners SET status=NULL WHERE id=?")->execute([$ceNull]);
t_ok(ops_val("SELECT status FROM business_partners WHERE id=?", [$ceNull]) === null,
     'CE1 · the NULL-status probe really does hold NULL');
$ceNullHit = find_duplicate_partner('Another Name Entirely Ltd', '24NULL9999N1Z9', '', '', 0);
t_ok($ceNullHit && (int)$ceNullHit['row']['id'] === $ceNull,
     'CE2 · *** a NULL status is treated as live — an unknown status never switches protection off ***');

// =============================================================================
t_section('P6-B3C · CF — merge safety and survivor resolution (R4 · §12)');
// =============================================================================
$cfGst = '24MRG00001M1Z9';
$cfKeep = $ccPartner('Kingsley Marine Services Pvt Ltd', ['gstin' => $cfGst]);
$cfDrop = $ccPartner('Kingsley Marine Services Private Limited', ['gstin' => $cfGst]);
$cfFindings = fn() => count(array_filter(identity_state_findings(),
    fn($f) => ($f['kind'] ?? '') === 'PARTNER_DUPLICATE_TAXID'
              && in_array($cfKeep, (array)($f['records']['organisations'] ?? []), true)));
t_ok($cfFindings() > 0, 'CF0 · before the merge the dashboard DOES report the pair — the probe has a subject');
$cfRes = dd_merge($cfKeep, $cfDrop, 'one company, two records');
t_ok(!empty($cfRes['ok']), 'CF1 · the merge succeeded');
t_eq((string)ops_val("SELECT status FROM business_partners WHERE id=?", [$cfDrop]), 'MERGED', 'CF2 · the retired record is marked MERGED');
t_eq((int)ops_val("SELECT merged_into_id FROM business_partners WHERE id=?", [$cfDrop]), $cfKeep,
     'CF3 · *** and says, in a form a machine can read, where it went ***');
t_eq((string)ops_val("SELECT gstin FROM business_partners WHERE id=?", [$cfDrop]), $cfGst,
     'CF4 · its identifiers are NOT wiped — the evidence of why they were linked survives');
t_eq($cfFindings(), 0, 'CF5 · *** the merge CLEARS the warning it was raised to resolve ***');

//  A new registration on that identifier must be sent to the living company.
$cfHit = find_duplicate_partner('Kingsley Marine Ltd', $cfGst, '', '', 0);
t_ok(is_array($cfHit), 'CF6 · a new registration on that tax identifier is still matched — nothing was weakened');
t_eq((int)$cfHit['row']['id'], $cfKeep, 'CF7 · *** and is pointed at the SURVIVOR, not the retired record ***');
//  The case that actually matters, and the one the audit caught: an identifier
//  carried ONLY by the record that was retired. Above, both records held the
//  same GSTIN, so the scan met the living one first and never had to resolve
//  anything — a probe that passes without exercising the thing it names. This
//  one leaves the survivor with a different identifier, so the ONLY way to match
//  is through the retired record.
$cfOnlyGst = '24RETIRED9X1Z9';
$cfLive = $ccPartner('Langley Freight Ltd', ['gstin' => '24LIVEONE1L1Z9']);
$cfGone = $ccPartner('Langley Freight Limited', ['gstin' => $cfOnlyGst]);
dd_merge($cfLive, $cfGone, 'same company under two registrations');
t_eq((string)ops_val("SELECT status FROM business_partners WHERE id=?", [$cfGone]), 'MERGED',
     'CF8 · the probe has its subject — a retired record holding an identifier of its own');
t_eq((string)ops_val("SELECT gstin FROM business_partners WHERE id=?", [$cfLive]), '24LIVEONE1L1Z9',
     'CF8b · and the survivor does NOT carry it, so only the retired record can match');
$cfOnly = find_duplicate_partner('Langley Haulage Ltd', $cfOnlyGst, '', '', 0);
t_ok(is_array($cfOnly), 'CF8c · that identifier is still matched — a retired record never becomes a way back in');
t_eq((int)$cfOnly['row']['id'], $cfLive, 'CF9 · *** and the caller is handed the SURVIVOR, never the retired record ***');
t_ok(!empty($cfOnly['retired']), 'CF9b · while the result still says the match was made on a retired record');
t_eq((int)$cfOnly['matched']['id'], $cfGone, 'CF9c · and names which one, so nothing is hidden from staff');

//  A chain: the survivor is itself merged away later.
$cfThird = $ccPartner('Kingsley Group Ltd');
dd_merge($cfThird, $cfKeep, 'group consolidation');
t_eq(partner_survivor($cfDrop), $cfThird, 'CF10 · a chain of merges resolves all the way to the company that trades');
//  A cycle must terminate rather than hang.
db()->prepare("UPDATE business_partners SET merged_into_id=? WHERE id=?")->execute([$cfDrop, $cfThird]);
t_ok(partner_survivor($cfDrop) > 0, 'CF11 · a corrupt pointer cycle terminates instead of hanging');
db()->prepare("UPDATE business_partners SET merged_into_id=NULL WHERE id=?")->execute([$cfThird]);
//  You cannot merge INTO a record that is itself retired.
$cfBad = dd_merge($cfDrop, $ccPartner('Somebody Else Ltd'), 'nonsense');
t_ok(empty($cfBad['ok']), 'CF12 · a retired record cannot be chosen as the survivor of another merge');

//  The claim flow must route to the survivor too.
connect_org_register(['name' => 'Kingsley Marine Ltd', 'org_type' => 'COMPANY',
                      'contact_email' => 'cf@kingsley.test', 'gstin' => $cfGst, 'password' => 'password123']);
t_eq((int)ops_val("SELECT partner_id FROM cx_access_requests WHERE email='cf@kingsley.test'"), $cfThird,
     'CF13 · *** an access request names the company that trades, never a record that was retired ***');

// =============================================================================
t_section('P6-B3C · CG — the migration is idempotent and honest (A6 · §6 · §7)');
// =============================================================================
//  A6 — the detector that could not see its own column.
t_ok(!in_array('uq_primary', array_keys(table_columns('partner_contacts')), true) || t_driver() !== 'sqlite',
     'CG0 · on SQLite the write-path column list correctly omits the generated key');
t_ok(in_array('uq_primary', table_columns_incl_generated('partner_contacts'), true),
     'CG1 · *** while the MIGRATION detector can see it — the A6 blind spot is closed ***');

//  Idempotence: run it again and again, including after the index is torn off.
$cgExpr = PARTNER_PRIMARY_KEY_EXPR;
for ($i = 1; $i <= 3; $i++)
    t_eq(ensure_unique_generated_index('partner_contacts', 'uq_primary', $cgExpr, 'uq_pcont_primary', 'partner_contact_reconcile_primaries'),
         'OK', 'CG · run ' . $i . ' of the migration converges on the same schema');
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX uq_pcont_primary" : "DROP INDEX uq_pcont_primary ON partner_contacts"); } catch (Throwable $e) {}
t_ok(!in_array('uq_pcont_primary', table_index_names('partner_contacts'), true), 'CG2 · the index really was removed — the recovery probe has a subject');
t_eq(ensure_unique_generated_index('partner_contacts', 'uq_primary', $cgExpr, 'uq_pcont_primary', 'partner_contact_reconcile_primaries'),
     'OK', 'CG3 · *** after a partial failure the migration REBUILDS the protection ***');
t_ok(in_array('uq_pcont_primary', table_index_names('partner_contacts'), true), 'CG4 · and the index is verified present, not merely attempted');

//  Honesty: a guard that cannot be installed must say so rather than pass.
$cgFake = ensure_unique_generated_index('a_table_that_is_not_there', 'x', '1', 'ix_nope');
t_eq((string)$cgFake, 'ABSENT', 'CG5 · a table that does not exist is reported as absent, not as success');
t_eq((string)(schema_guard_read('uq_pcont_primary')['state'] ?? ''), 'OK', 'CG6 · and the ledger still reflects the real state of the real guard');

//  §7 · CM6 — DDL MUST NOT RUN INSIDE A TRANSACTION THE MIGRATION DID NOT OPEN.
//
//  The probe this replaces compared index names before and after, in this
//  process, and proved nothing twice over. partner_contact_migrate() keeps a
//  static epoch marker and had already run in this file, so it returned at once
//  and the guarded path was never entered; and even had it run, an index list
//  cannot show an implicit commit, which is the actual danger. The mutation
//  battery caught that: CM6 removed the guard and every assertion still passed.
//
//  This asks the question that matters. A business row is written inside a
//  caller-owned transaction, the migration is invited in, the caller rolls back,
//  and the row must be gone. On MariaDB, DDL inside that transaction would
//  commit it implicitly and the row would survive. A fresh process is used so no
//  static marker can make the probe vacuous a second time.
$cgTx = $ccRace([['txddl', 0, 'Rollback Probe Ltd']], 300);
t_eq(count($cgTx), 1, 'CG7 · the transaction-safety worker reported a verdict');
if ($cgTx) {
    t_ok(strpos((string)($cgTx[0]['code'] ?? ''), 'WORK:') === 0,
         'CG7a · and it had a real subject — the protection was removed first, so DDL was genuinely pending'
         . '  [' . (string)($cgTx[0]['msg'] ?? '') . ']');
    t_ok(!empty($cgTx[0]['ok']),
         'CG7b · *** a business row written in a caller-owned transaction does NOT survive its rollback ***'
         . '  [' . (string)($cgTx[0]['code'] ?? '') . ']');
}
partner_contact_migrate();
ensure_unique_generated_index('partner_contacts', 'uq_primary', PARTNER_PRIMARY_KEY_EXPR,
                              'uq_pcont_primary', 'partner_contact_reconcile_primaries');
t_ok(in_array('uq_pcont_primary', table_index_names('partner_contacts'), true),
     'CG7c · and the protection is restored afterwards, so later sections still have it');

//  CM3 · §4 — A GUARD THAT CANNOT INSTALL ITS PROTECTION MUST SAY SO.
//
//  Everything above proves the guard works when the database co-operates.
//  Nothing proved the other half: the battery removed the verification step and
//  every test still passed, because no probe ever put the guard in a position
//  where CREATE UNIQUE INDEX fails. A guard that cannot fail out loud can lie.
//
//  The failure is forced at the real operation, for a reason each engine
//  genuinely has: MariaDB rejects an identifier longer than 64 characters;
//  SQLite keeps indexes and tables in one namespace, so an index cannot take a
//  name a table already holds.
//  The guard name is kept short on purpose. The first version of this probe used
//  a 70-character name to make MariaDB reject the index — and MariaDB then also
//  rejected the LEDGER row, because schema_guards.guard is VARCHAR(64). The
//  probe proved FAILED was returned but destroyed the very record it was
//  checking for. An instrument must not disturb what it measures.
db()->exec("CREATE TABLE IF NOT EXISTS cg_guard_probe (id INTEGER, flag INT DEFAULT 0, owner INT DEFAULT 0)");
$cgBadName = 'ux_cg_probe_cannot_exist';
$cgFail = ensure_unique_generated_index('cg_guard_probe', 'uq_probe',
            "CASE WHEN COALESCE(no_such_column_anywhere,0)<>0 THEN owner ELSE NULL END", $cgBadName);
t_eq((string)$cgFail, 'FAILED', 'CG8 · *** a guard whose index cannot be created reports FAILED, never OK ***');
t_eq((string)(schema_guard_read($cgBadName)['state'] ?? ''), 'FAILED',
     'CG9 · and the ledger records it where an operator can read it');
t_ok(trim((string)(schema_guard_read($cgBadName)['detail'] ?? '')) !== '',
     'CG10 · with a reason, so the failure is diagnosable rather than merely known');
t_ok(!in_array($cgBadName, table_index_names('cg_guard_probe'), true),
     'CG11 · and the index really is absent — the probe had a genuine failure to observe');
t_ok(in_array($cgBadName, array_column(schema_guards_not_ok(), 'guard'), true),
     'CG12 · a failed safety migration is visible in the not-OK list, never silently skipped');
//  The probe above fails while CREATING THE KEY. On SQLite the failure can also
//  be forced one step later, at the INDEX itself, because indexes and tables
//  share one namespace there — so an index cannot take a name a table holds.
//  MariaDB scopes index names per table and has no equivalent short-name
//  refusal, so that half is asserted on SQLite alone rather than faked.
if (t_driver() === 'sqlite') {
    db()->exec("CREATE TABLE IF NOT EXISTS cg_name_taken_by_a_table (id INTEGER)");
    $cgIxFail = ensure_unique_generated_index('cg_guard_probe', 'uq_probe2',
                  "CASE WHEN COALESCE(flag,0)<>0 THEN owner ELSE NULL END", 'cg_name_taken_by_a_table');
    t_eq((string)$cgIxFail, 'FAILED', 'CG12a · a guard whose INDEX cannot be created also reports FAILED');
    t_eq((string)(schema_guard_read('cg_name_taken_by_a_table')['state'] ?? ''), 'FAILED',
         'CG12b · and that failure is recorded too');
} else {
    t_ok(true, 'CG12a · index-step refusal asserted on SQLite, which can force it with a legal name');
    t_ok(true, 'CG12b · (MariaDB scopes index names per table, so it has no equivalent short-name refusal)');
}

//  CM20 · §5 — THE LOSING SIDE OF A CONCURRENT BOOT.
//
//  Two processes start together, both find the uniqueness key missing, both run
//  the ALTER, and one loses with "duplicate column". The loser's column is
//  present all the same, and the index behind it still has to be built — so the
//  loser must ask the schema rather than believe the error. Nothing tested that
//  path, so a mutant that made a failed ALTER give up immediately survived.
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX uq_pcont_primary"
                                         : "DROP INDEX uq_pcont_primary ON partner_contacts"); } catch (Throwable $e) {}
try { db()->exec("ALTER TABLE partner_contacts DROP COLUMN uq_primary"); } catch (Throwable $e) {}
t_ok(!in_array('uq_primary', table_columns_incl_generated('partner_contacts'), true),
     'CG13 · the key was removed, so the racing boots have something to install');
$cgRace = $ccRace([['guardrace', 0, ''], ['guardrace', 0, ''], ['guardrace', 0, '']]);
t_eq(count($cgRace), 3, 'CG14 · three concurrent boots reported a verdict');
$cgSaw = 0; $cgFailed = 0; $cgOk = 0;
foreach ($cgRace as $r) {
    if (strpos((string)($r['msg'] ?? ''), 'missing on entry') !== false) $cgSaw++;
    if ((string)($r['code'] ?? '') === 'FAILED') $cgFailed++;
    if ((string)($r['code'] ?? '') === 'OK') $cgOk++;
}
t_ok($cgSaw >= 2, 'CG15 · at least two of them really did race the same ALTER — the probe has its subject  [' . $cgSaw . ' saw it missing]');
t_eq($cgFailed, 0, 'CG16 · *** losing the ALTER race is not a failure — no boot reported FAILED ***');
t_ok($cgOk >= 1, 'CG17 · and at least one reported the protection installed');
partner_contact_migrate();
ensure_unique_generated_index('partner_contacts', 'uq_primary', PARTNER_PRIMARY_KEY_EXPR,
                              'uq_pcont_primary', 'partner_contact_reconcile_primaries');
t_ok(in_array('uq_pcont_primary', table_index_names('partner_contacts'), true),
     'CG18 · and the protection is in place after the race, however it was won');

//  The portal account guard is installed by the same mechanism.
portal_acct_migrate();
foreach (PORTAL_ACCT_TABLES as $cgT => $cgIx) {
    $g = schema_guard_read($cgIx);
    t_ok(is_array($g) && in_array((string)$g['state'], ['OK', 'DIRTY'], true),
         'CG · the ' . $cgT . ' account guard reports its real state (' . (string)($g['state'] ?? 'MISSING') . ')');
}

// =============================================================================
t_section('P6-B3C · CH — authorisation and tenant isolation (§15 · §16)');
// =============================================================================
//  The access-request queue is staff-only, and nothing in it hands over access.
//
//  The gate is asserted at the SCREEN'S OWN DOOR rather than by calling it:
//  ops_require() refuses by redirecting and ending the request, which would end
//  this test run too. So the door is read, the same way the recruitment control
//  test reads its route — and the gate is the one that already guards the
//  organisations screen next door, not a new permission invented for this queue.
$chSrc  = (string)@file_get_contents(dirname(__DIR__) . '/lib/connect_org.php');
$chPos  = strpos($chSrc, 'function ops_connect_access_requests');
t_ok($chPos !== false, 'CH0 · the access-request screen exists to be checked');
$chDoor = substr($chSrc, $chPos, 320);
t_ok(strpos($chDoor, 'ops_require(') !== false && strpos($chDoor, 'is_master()') !== false,
     'CH1 · and it refuses anyone who is not a master admin, on the server, before anything is read');
t_ok(strpos($chDoor, 'connect_access_requests_all') === false || strpos($chDoor, 'ops_require') < strpos($chDoor, 'connect_access_requests_all'),
     'CH1b · the check happens BEFORE the queue is read, not after');
t_as_admin();
t_ok(is_array(connect_access_requests_all('PENDING')), 'CH1c · a master admin can read the queue');

//  Closing a request grants nothing by itself.
$chId = (int)ops_val("SELECT id FROM cx_access_requests WHERE status='PENDING' ORDER BY id LIMIT 1");
if ($chId > 0) {
    $chPartner = (int)ops_val("SELECT partner_id FROM cx_access_requests WHERE id=?", [$chId]);
    $chEmail   = (string)ops_val("SELECT email FROM cx_access_requests WHERE id=?", [$chId]);
    $chAccts   = (int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=?", [$chPartner]);
    connect_access_request_close($chId, 'APPROVED', 'looks genuine');
    t_eq((string)ops_val("SELECT status FROM cx_access_requests WHERE id=?", [$chId]), 'APPROVED', 'CH2 · approving records the decision');
    t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE partner_id=?", [$chPartner]), $chAccts,
         'CH3 · *** but creates NO account — approval is not a takeover ***');
    t_eq((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(TRIM(email))=?", [$chEmail]), 0,
         'CH4 · the person still has no login until somebody invites them the ordinary way');
    t_ok((string)ops_val("SELECT handled_by FROM cx_access_requests WHERE id=?", [$chId]) !== '',
         'CH5 · and the decision is attributed to whoever made it');
} else {
    foreach (['CH2','CH3','CH4','CH5'] as $k) t_ok(false, $k . ' · no pending access request to act on — INVALID EXPERIMENT');
}

//  §15 — isolation is structural here: one database per tenant. Assert the
//  property that makes it true rather than claiming it.
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_access_requests r
                    WHERE r.partner_id IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM business_partners p WHERE p.id = r.partner_id)"), 0,
     'CH6 · every access request names an organisation in THIS workspace — an id is never a credential');
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners p
                    WHERE COALESCE(p.merged_into_id,0)<>0
                      AND NOT EXISTS (SELECT 1 FROM business_partners q WHERE q.id = p.merged_into_id)"), 0,
     'CH7 · and every survivor pointer names an organisation in THIS workspace too');

$_SESSION = $ccSess; current_user(true); ua(true);
