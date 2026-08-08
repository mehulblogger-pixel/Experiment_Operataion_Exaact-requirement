<?php
// #2 — the smart inspector suggestion for allocation. Proves the priority ladder
// the owner asked for, and that a date clash is detected (but never removes
// anyone from the list — the coordinator always sees everybody).
//
//   4  the engineer who did the LAST inspection for the same client + vendor
//   2  anyone who has worked for this client before
//   0  every other active engineer (the full fallback)
//
// All against the real boot()-migrated schema, so a column rename here fails loud.
t_section('smart inspector suggestion (#2)');

$now = date('c');

// Three active inspectors on file, plus one inactive who must never be suggested.
$ins = function ($name, $status = 'ACTIVE') use ($now) {
    db()->prepare("INSERT INTO inspectors (name, emp_code, staff_kind, status, created_at)
                   VALUES (?,?,?,?,?)")->execute([$name, '', 'ASSET', $status, $now]);
    return (int)db()->lastInsertId();
};
$ann   = $ins('Anita Continuity');   // will be the last-inspection engineer
$bob   = $ins('Bob Returning');      // has worked for the client before
$cara  = $ins('Cara Fresh');         // never worked for this client
$dave  = $ins('Dave Retired', 'INACTIVE');

// One client, one vendor.
$bp = function ($name, $client, $vendor) use ($now) {
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, is_vendor, status, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$name, $name, strtoupper(substr($name,0,3)), $client, $vendor, 'ACTIVE', $now]);
    return (int)db()->lastInsertId();
};
$client = $bp('Acme Steel', 1, 0);
$vendor = $bp('Bharat Forge', 0, 1);
$other  = $bp('Zeta Ltd', 1, 0);   // a different client, to prove scoping

// A helper to make a call + an allocated (closed) job so history exists.
$hist = function ($clientId, $vendorId, $inspectorId, $date) use ($now) {
    db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, created_at)
                   VALUES (?,?,?,?,?)")->execute(['C'.substr(md5($date.$inspectorId),0,5), $clientId, $vendorId, 'CLOSED', $now]);
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, closed_flag, created_at)
                   VALUES (?,?,?,?,?,?)")->execute(['J'.substr(md5($date.$inspectorId),0,5), $cid, $inspectorId, $date, 1, $now]);
    return $cid;
};

// History: Bob once served the client (older). Anita did the most recent
// inspection for the SAME client + vendor. Cara only ever served another client.
$hist($client, 0,       $bob,  '2026-01-10');
$hist($client, $vendor, $ann,  '2026-05-20');
$hist($other,  $vendor, $cara, '2026-06-01');

// The call we are now allocating: same client + vendor, needs 12 Aug 2026.
$call = ['client_id' => $client, 'vendor_id' => $vendor, 'contract_number' => '', 'id' => 0];
$sugg = inspector_suggestions($call, ['2026-08-12']);

$by = [];
foreach ($sugg as $s) $by[$s['name']] = $s;

t_ok(isset($by['Anita Continuity']) && isset($by['Bob Returning']) && isset($by['Cara Fresh']),
    'all three active engineers are suggested');
t_ok(!isset($by['Dave Retired']), 'an inactive engineer is never suggested');

t_eq($by['Anita Continuity']['score'], 4, 'the last-inspection engineer for this client+vendor scores 4 (top)');
t_eq($by['Bob Returning']['score'],    2, 'an engineer who has worked for the client scores 2');
t_eq($by['Cara Fresh']['score'],       0, 'an engineer new to this client scores 0');

// Order: highest score first, so Anita leads the list.
t_eq($sugg[0]['name'], 'Anita Continuity', 'the best fit is ranked first');

// A date clash is detected but does not drop anyone. Book Cara on the required
// date via an OPEN job, then re-rank: she must still appear, now flagged busy.
db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, created_at)
               VALUES (?,?,?,?,?)")->execute(['CBUSY', $other, $vendor, 'OPEN', $now]);
$busyCall = (int)db()->lastInsertId();
db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, closed_flag, created_at)
               VALUES (?,?,?,?,?,?)")->execute(['JBUSY', $busyCall, $cara, '2026-08-12', 0, $now]);

$sugg2 = inspector_suggestions($call, ['2026-08-12']);
$by2 = []; foreach ($sugg2 as $s) $by2[$s['name']] = $s;
t_ok(isset($by2['Cara Fresh']), 'a busy engineer is still shown (never removed)');
t_ok(!$by2['Cara Fresh']['available'] && in_array('2026-08-12', $by2['Cara Fresh']['clash'], true),
    'the busy engineer is flagged with the clashing date');
t_ok($by2['Anita Continuity']['available'], 'a free engineer is flagged available');

// pending_allocation_siblings: another un-allocated call for the same client+vendor
// should surface; a closed one, and one with a job already, should not.
db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, inspection_required_date, created_at)
               VALUES (?,?,?,?,?,?)")->execute(['CSIB', $client, $vendor, 'OPEN', '2026-08-15', $now]);
$sibId = (int)db()->lastInsertId();
$sibs = pending_allocation_siblings(['client_id' => $client, 'vendor_id' => $vendor, 'id' => 999999]);
$sibCodes = array_column($sibs, 'call_code');
t_ok(in_array('CSIB', $sibCodes, true), 'an un-allocated sibling call for the same client+vendor is surfaced');
t_ok(!in_array('CBUSY', $sibCodes, true), 'a call that already has a job is not listed as pending');
