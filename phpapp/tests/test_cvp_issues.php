<?php
// ============================================================================
//  CVP Slice 3 — the external nonconformity loop (client + vendor).
//  Proves: a party sees ONLY issues raised to them AND marked visible to their
//  audience (ownership + Slice-1 visibility gate, both required); a response is
//  validated (real kind, non-empty, theirs, not closed) and feeds the SAME
//  engine staff read — an issue_disputes row stamped with the party + an NCR
//  event; and the routes are wired into both portals.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('cvp_migrate'))   cvp_migrate();
if (function_exists('ncr_migrate'))   ncr_migrate();
if (function_exists('ncdca_migrate')) ncdca_migrate();

// A vendor, a client, and a second vendor (to prove cross-party isolation).
db()->prepare("INSERT INTO business_partners (legal_name,is_vendor,status,created_at) VALUES ('Acme Fasteners',1,'ACTIVE',?)")->execute([date('c')]);
$VEN = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at) VALUES ('Buyer Corp',1,'ACTIVE',?)")->execute([date('c')]);
$CLI = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,is_vendor,status,created_at) VALUES ('Other Vendor',1,'ACTIVE',?)")->execute([date('c')]);
$VEN2 = (int)db()->lastInsertId();

$mkNcr = function($ref,$pid,$vis,$status='OPEN',$title='Finding') {
    db()->prepare("INSERT INTO nonconformities (ref,partner_id,title,description,severity,visibility,status,detected_on,due_on,created_at)
        VALUES (?,?,?,?, 'MAJOR', ?, ?, ?, ?, ?)")
        ->execute([$ref,$pid,$title,'desc of '.$ref,$vis,$status,date('Y-m-d'),date('Y-m-d',strtotime('+14 days')),date('c')]);
    return (int)db()->lastInsertId();
};
$nVenVis  = $mkNcr('NCR/V/1', $VEN,  'VENDOR_VISIBLE');
$nVenInt  = $mkNcr('NCR/V/2', $VEN,  'INTERNAL');
$nCliVis  = $mkNcr('NCR/C/1', $CLI,  'CLIENT_VISIBLE');
$nVen2Vis = $mkNcr('NCR/O/1', $VEN2, 'VENDOR_VISIBLE');
$nVenClosed = $mkNcr('NCR/V/9', $VEN, 'VENDOR_VISIBLE', 'CLOSED');

head('1. A vendor sees only vendor-visible issues raised to THEM');
$vrows = cvp_issues_for('VENDOR', $VEN);
$vrefs = array_map(fn($r) => $r['ref'], $vrows);
ok(in_array('NCR/V/1', $vrefs, true), 'the vendor-visible open issue is listed');
ok(!in_array('NCR/V/2', $vrefs, true), 'an INTERNAL issue is NOT shown to the vendor');
ok(!in_array('NCR/O/1', $vrefs, true), "another vendor's issue is NOT shown");
ok(!in_array('NCR/C/1', $vrefs, true), "a client's issue is NOT shown to the vendor");

head('2. A client sees only client-visible issues raised to THEM');
$crows = cvp_issues_for('CLIENT', $CLI);
$crefs = array_map(fn($r) => $r['ref'], $crows);
ok(in_array('NCR/C/1', $crefs, true), 'the client-visible issue is listed');
ok(!in_array('NCR/V/1', $crefs, true), "a vendor's issue is NOT shown to the client");

head('3. Single-issue read is ownership + visibility gated');
ok(cvp_issue('VENDOR', $VEN, $nVenVis) !== null, 'the vendor can open their vendor-visible issue');
ok(cvp_issue('VENDOR', $VEN, $nVenInt) === null, 'the vendor cannot open an INTERNAL issue');
ok(cvp_issue('VENDOR', $VEN, $nVen2Vis) === null, "the vendor cannot open another vendor's issue");
ok(cvp_issue('CLIENT', $CLI, $nVenVis) === null, 'the client cannot open a vendor issue');

head('4. Responding is validated and feeds the engine');
ok(cvp_issue_respond('VENDOR', $VEN, $nVenVis, ['response_kind'=>'BOGUS','response'=>'x']) !== '', 'an unknown response kind is refused');
ok(cvp_issue_respond('VENDOR', $VEN, $nVenVis, ['response_kind'=>'ACCEPT','response'=>'']) !== '', 'an empty response is refused');
ok(cvp_issue_respond('VENDOR', $VEN, $nVenInt, ['response_kind'=>'ACCEPT','response'=>'ok']) !== '', 'a response to an issue not theirs is refused');
ok(cvp_issue_respond('VENDOR', $VEN, $nVenClosed, ['response_kind'=>'ACCEPT','response'=>'ok']) !== '', 'a response to a CLOSED issue is refused');
$err = cvp_issue_respond('VENDOR', $VEN, $nVenVis, [
    'response_kind'=>'PROPOSE', 'response'=>'We accept and will act.',
    'reason'=>'Operator error at heat treatment', 'proposed_action'=>'Retrain + add a check', 'evidence_ref'=>'CAR-2026-014']);
ok($err === '', 'a valid vendor response is accepted');
$d = ops_one("SELECT * FROM issue_disputes WHERE ncr_id=? ORDER BY id DESC", [$nVenVis]);
ok($d && $d['party'] === 'VENDOR' && $d['response_kind'] === 'PROPOSE', 'it wrote an issue_disputes row stamped VENDOR (same engine staff read)');
ok($d && $d['proposed_action'] === 'Retrain + add a check' && $d['evidence_ref'] === 'CAR-2026-014', 'root cause, corrective action and evidence are captured');
ok((int)ops_val("SELECT COUNT(*) FROM ncr_events WHERE ncr_id=? AND action='PARTY_RESPONSE'", [$nVenVis]) === 1, 'the NCR event trail records the party response');

head('5. The party can read back its own responses; count reflects it');
$resp = cvp_issue_responses('VENDOR', $nVenVis);
ok(count($resp) === 1, 'the vendor sees its own response in the thread');
$again = cvp_issues_for('VENDOR', $VEN);
$row = null; foreach ($again as $r) if ($r['ref'] === 'NCR/V/1') $row = $r;
ok($row && (int)$row['my_responses'] === 1, 'the list shows the response count for the vendor');

head('6. A client response is stamped CLIENT, kept apart from the vendor party');
ok(cvp_issue_respond('CLIENT', $CLI, $nCliVis, ['response_kind'=>'ACCEPT','response'=>'Agreed.']) === '', 'a valid client response is accepted');
ok((string)ops_val("SELECT party FROM issue_disputes WHERE ncr_id=?", [$nCliVis]) === 'CLIENT', 'the client response is stamped CLIENT');

head('7. Both portals are wired to the shared loop');
$psrc = file_get_contents(__DIR__ . '/../lib/portal.php');
ok(strpos($psrc, "case 'portal/issues':") !== false && strpos($psrc, "cvp_issues_for('CLIENT'") !== false, 'the client portal routes to the shared loop');
ok(strpos($psrc, "'issues'") !== false, "the client portal has an 'issues' permission");
$csrc = file_get_contents(__DIR__ . '/../lib/cvp.php');
ok(strpos($csrc, "case 'vendor/issues'") === false && strpos($csrc, "'vendor/issues'") !== false, 'the vendor portal routes issues via cvp_vendor_route_issues');

// cleanup
db()->exec("DELETE FROM issue_disputes WHERE ncr_id IN ($nVenVis,$nVenInt,$nCliVis,$nVen2Vis,$nVenClosed)");
db()->exec("DELETE FROM ncr_events WHERE ncr_id IN ($nVenVis,$nVenInt,$nCliVis,$nVen2Vis,$nVenClosed)");
db()->exec("DELETE FROM nonconformities WHERE id IN ($nVenVis,$nVenInt,$nCliVis,$nVen2Vis,$nVenClosed)");
db()->exec("DELETE FROM business_partners WHERE id IN ($VEN,$CLI,$VEN2)");

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== CVP ISSUES: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
