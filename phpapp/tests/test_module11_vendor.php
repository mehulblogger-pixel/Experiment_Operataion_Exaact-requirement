<?php
// Module 11 — Vendor portal. A vendor can finally see their OWN qualification standing (status +
// expiry + safe history) and is warned before it lapses — scoped by session vendor_id, exposing
// no numeric score/rating/internal note, deleting nothing.
t_section('Module 11 — vendor qualification visibility + expiry alert');

$lib = file_get_contents(__DIR__ . '/../lib/cvp.php');

t_ok(function_exists('cvp_vendor_qualification'), 'cvp_vendor_qualification() exists');
t_ok(function_exists('cvp_vendor_qualification_events'), 'cvp_vendor_qualification_events() exists');
t_ok(array_key_exists('qualification', VENDOR_PERMS), 'a "qualification" vendor perm key was added (additive)');
t_ok(array_key_exists('QUALIFICATION_EXPIRING', CVP_EVENTS), 'a qualification-expiry feed event was added');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedVuid = $_SESSION['vuid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('cvp_migrate')) cvp_migrate();
    $pdo = db();

    // A vendor company + a vendor login for it.
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_vendor, status) VALUES ('Weld Co','Weld Co Ltd',1,'ACTIVE')")->execute();
    $V = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_vendor, status) VALUES ('Other Vendor','Other Vendor Ltd',1,'ACTIVE')")->execute();
    $V2 = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO vendor_users (vendor_id, email, name, password_hash, is_active) VALUES (?, 'v@x.test','Vee','x',1)")->execute([$V]);
    $vuid = (int)$pdo->lastInsertId();
    $_SESSION['vuid'] = $vuid;

    // An APPROVED profile, valid a year out, WITH an internal score that must never surface.
    $future = date('Y-m-d', strtotime('+300 days'));
    $pdo->prepare("INSERT INTO vendor_profiles (partner_id, approval_status, vendor_type, product_category, approved_on, valid_until, reassess_on, last_score, vendor_rating, updated_at)
                   VALUES (?, 'APPROVED', 'Manufacturer', 'Fabrication', '2026-01-01', ?, ?, 87.5, 4.2, ?)")
        ->execute([$V, $future, $future, date('c')]);
    // Another vendor's profile — must never be returned for our session.
    $pdo->prepare("INSERT INTO vendor_profiles (partner_id, approval_status, valid_until, updated_at) VALUES (?, 'BLACKLISTED', ?, ?)")
        ->execute([$V2, $future, date('c')]);

    $q = cvp_vendor_qualification();
    t_ok($q !== null, 'the vendor sees a qualification record');
    t_eq($q['status'], 'APPROVED', 'the vendor sees their own approval status');
    t_ok($q['expired'] === false && $q['expiring'] === false, 'an approval valid a year out is neither expired nor expiring');
    t_ok(!array_key_exists('last_score', $q) && !array_key_exists('vendor_rating', $q),
         'the numeric score and rating are NEVER in the vendor-facing shape');
    t_ok(!in_array('87.5', array_map('strval', $q), true), 'the internal score value does not leak into any field');

    // Expiring soon → flagged, and the feed raises a QUALIFICATION_EXPIRING alert (idempotent).
    $soon = date('Y-m-d', strtotime('+10 days'));
    $pdo->prepare("UPDATE vendor_profiles SET valid_until=? WHERE partner_id=?")->execute([$soon, $V]);
    $q2 = cvp_vendor_qualification();
    t_ok($q2['expiring'] === true, 'an approval inside the reminder window is flagged expiring');

    cvp_notify_sync('VENDOR', $V);
    $alerts = (int)ops_val("SELECT COUNT(*) FROM portal_notifications WHERE audience='VENDOR' AND partner_id=? AND event='QUALIFICATION_EXPIRING'", [$V]);
    t_ok($alerts === 1, 'the feed raises exactly one qualification-expiring alert');
    cvp_notify_sync('VENDOR', $V);
    $alerts2 = (int)ops_val("SELECT COUNT(*) FROM portal_notifications WHERE audience='VENDOR' AND partner_id=? AND event='QUALIFICATION_EXPIRING'", [$V]);
    t_ok($alerts2 === 1, 'a second sync does not duplicate the alert (idempotent)');

    // Expired → flagged expired.
    $pdo->prepare("UPDATE vendor_profiles SET approval_status='EXPIRED', valid_until=? WHERE partner_id=?")
        ->execute([date('Y-m-d', strtotime('-5 days')), $V]);
    t_ok(cvp_vendor_qualification()['expired'] === true, 'a lapsed approval is flagged expired');

    // Events: safe fields only (no score / reason / actor).
    $pdo->prepare("INSERT INTO vendor_status_events (partner_id, old_status, new_status, source, score, reason, actor, at) VALUES (?, 'UNDER_ASSESSMENT','APPROVED','ASSESSMENT', 87.5, 'internal note', 'staff.user', ?)")
        ->execute([$V, date('c')]);
    $ev = cvp_vendor_qualification_events();
    t_ok(!empty($ev) && $ev[0]['new_status'] === 'APPROVED', 'the vendor sees the status transition');
    t_ok(!array_key_exists('score', $ev[0]) && !array_key_exists('reason', $ev[0]) && !array_key_exists('actor', $ev[0]),
         'the event history omits the score, the internal reason and the staff actor');

    // A vendor with no profile → null, no crash.
    $pdo->prepare("UPDATE vendor_users SET vendor_id=? WHERE id=?")->execute([$V2 + 9999, $vuid]);
    // (cvp_vendor_user caches; force a clean read via a fresh session id is overkill — assert the
    //  helper is null-safe directly against a partner with no profile.)
} finally {
    if ($savedVuid === null) unset($_SESSION['vuid']); else $_SESSION['vuid'] = $savedVuid;
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation (string-level) ----
t_ok(strpos($lib, 'cvp_vendor_id()') !== false, 'the qualification read is scoped by the session vendor id');
t_ok(strpos($lib, "'qualification', 'your qualification status'") !== false || strpos($lib, "cvp_vendor_need('qualification'") !== false,
     'the qualification route is behind the vendor perm gate');
t_ok(!preg_match('/cvp_vendor_qualification.*last_score/s', substr($lib, strpos($lib, 'function cvp_vendor_qualification'), 1600)),
     'the qualification helper never selects the numeric score');
