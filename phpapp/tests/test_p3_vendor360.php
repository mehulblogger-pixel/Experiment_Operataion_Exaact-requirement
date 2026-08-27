<?php
// Phase 3 §16 — Vendor-360 depth. The vendor screen was rich on quality (assessments, audits, scorecard,
// CAPAs) but lacked what client-360 has: the vendor's people recognised as one person across the system
// (§23/24) and the full activity history (the one spine, §17). This adds exactly those, reusing the
// existing engines. Self-contained.
t_section('Phase 3 §16 — vendor-360 depth (contacts + §23/24 party + history)');

t_ok(function_exists('vendor360_contacts') && function_exists('vendor360_contact_also') && function_exists('vendor360_render'),
     'the vendor-360 helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/vendor360.php'") !== false, 'the vendor360 lib is loaded by the front controller');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/vendor_detail.php');
t_ok(strpos($view, 'vendor360_render') !== false, 'the vendor detail screen renders the parity panels');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('crm_migrate')) crm_migrate();
    $pdo = db();
    // A vendor and one of its contacts.
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_vendor, status) VALUES ('Weldtech Pvt Ltd','Weldtech',1,'ACTIVE')")->execute();
    $vid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_contacts (partner_id, name, designation, mobile, email, is_primary) VALUES (?, 'Ravi Kumar','QA Head','9876500011','ravi@weldtech.example',1)")->execute([$vid]);
    $cid = (int)$pdo->lastInsertId();
    // A second contact with no cross-links.
    $pdo->prepare("INSERT INTO partner_contacts (partner_id, name, designation, mobile, email) VALUES (?, 'Sara Iyer','Purchase','9000000000','sara@weldtech.example')")->execute([$vid]);

    // The SAME person (by mobile) also exists as a candidate — §23/24 should link them.
    $pdo->prepare("INSERT INTO candidates (first_name, last_name, mobile) VALUES ('Ravi','Kumar','9876500011')")->execute();

    $contacts = vendor360_contacts($vid);
    t_eq(count($contacts), 2, 'both vendor contacts are listed');
    t_ok($contacts[0]['is_primary'] == 1, 'the primary contact sorts first');

    // The primary contact is recognised across the system (as the candidate), the contact row itself excluded.
    $ravi = null; foreach ($contacts as $c) if ($c['name'] === 'Ravi Kumar') $ravi = $c;
    $also = vendor360_contact_also($ravi);
    $kinds = array_column($also, 'kind');
    t_ok(in_array('CANDIDATE', $kinds, true), 'the contact is linked to their candidate record (§23/24)');
    t_ok(!in_array('CONTACT', $kinds, true), 'the contact record does not link to itself');

    // A contact with no shared identifier has no cross-links.
    $sara = null; foreach ($contacts as $c) if ($c['name'] === 'Sara Iyer') $sara = $c;
    t_ok(vendor360_contact_also($sara) === [], 'a contact with no shared identifier has no cross-links');

    // The render produces the two parity panels.
    ob_start(); vendor360_render($vid); $html = ob_get_clean();
    t_ok(strpos($html, 'Contacts') !== false && strpos($html, 'Ravi Kumar') !== false, 'the contacts panel renders');
    t_ok(strpos($html, 'Also appears as') !== false, 'the cross-system link is shown on the screen');
    t_ok(strpos($html, 'Vendor history') !== false, 'the full activity history renders (parity with client-360)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
