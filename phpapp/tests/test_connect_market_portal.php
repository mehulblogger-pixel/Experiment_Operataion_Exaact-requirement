<?php
// Connect K2b — external self-service marketplace. Asserts the engine pieces the
// client portal (post) and vendor portal (apply) rely on: a party-scoped apply
// that dedupes on the applying party, the "own requirements" and "open board"
// helpers, and that the two new portal permissions exist in their masters.
t_section('connect marketplace external portals (K2b)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A client party posts; a vendor party applies.
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Acme Fabricators', 1, 'ACTIVE')")->execute();
    $clientPid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name, is_vendor, status) VALUES ('Gulf Inspection Agency', 1, 'ACTIVE')")->execute();
    $vendorPid = (int)db()->lastInsertId();

    // Client posts a requirement (as the portal route does: poster_party_id + OPEN).
    $rid = cx_requirement_create(['title' => 'Painting inspector — tank farm', 'poster_party_id' => $clientPid, 'poster_name' => 'Acme Fabricators'], true);
    t_eq('OPEN', cx_requirement_get($rid)['status'], 'a portal-posted requirement is OPEN');

    // "Your requirements" helper scopes to the poster party.
    $mine = cx_requirements_for_party($clientPid);
    t_ok(count($mine) === 1 && (int)$mine[0]['id'] === $rid, 'a client sees only its own postings');
    t_ok(count(cx_requirements_for_party($vendorPid)) === 0, 'a different party sees none of them');

    // The open board a supplier browses includes it.
    $open = cx_open_requirements();
    t_ok(in_array($rid, array_map(fn($r) => (int)$r['id'], $open), true), 'the open board lists the requirement');

    // Vendor applies as a PARTY (no pool inspector) — and cannot apply twice.
    t_ok(!cx_party_applied($rid, $vendorPid), 'the vendor has not applied yet');
    $a1 = cx_application_add($rid, ['applicant_party_id' => $vendorPid, 'applicant_name' => 'Gulf Inspection Agency', 'proposed_rate' => 5000]);
    t_ok($a1 > 0, 'the vendor applies');
    t_ok(cx_party_applied($rid, $vendorPid), 'the vendor now shows as applied');
    t_eq(0, cx_application_add($rid, ['applicant_party_id' => $vendorPid, 'applicant_name' => 'Gulf Inspection Agency']), 'the same vendor cannot apply twice');
    t_eq($vendorPid, (int)cx_application_get($a1)['applicant_party_id'], 'the application records the applying party');

    // The two new portal permissions exist in their masters.
    t_ok(array_key_exists('market.post', PORTAL_PERMS), 'client portal has the market.post permission');
    t_ok(array_key_exists('market.apply', VENDOR_PERMS), 'vendor portal has the market.apply permission');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
