<?php
// ============================================================================
//  Branch batch P1 — DEF-DIR-005 (P1), and DEF-QUA-010 with it.
//
//  THE DEFECT: view() required the page frame (views/layout_top.php) in the
//  screen's OWN scope, so every variable the frame set for itself landed on top
//  of what the handler gave the screen:
//    · $u = current_user()  — "Change permissions" on a client portal user showed
//      the signed-in STAFF member, and Save wrote the permissions and "Access
//      ends on" onto whichever portal user had the staff member's id number;
//    · $m (the flash loop)  — the method screens opened blank after a save;
//    · $office              — the audit log's office filter showed the wrong one.
//
//  Everything here renders through the PRODUCTION view() in a separate process
//  (tests/_branch_batch_portal_worker.php) — a stub or an imitation of view()
//  cannot show this defect at all.
// ============================================================================
t_section('Branch batch P1 — the page frame never overwrites a screen\'s own variables (DEF-DIR-005, DEF-QUA-010)');

require_once __DIR__ . '/_branch_batch_portal_lib.php';
$w = $GLOBALS['__bbp_worker'];

// ---- A · every name the frame uses is the screen's own, in both frames ------
$p = $w('probe');
t_ok(!empty($p['ok']), 'the probe rendered through the production view()' . (!empty($p['error']) ? ' (' . $p['error'] . ')' : ''));
t_ok(!empty($p['fullHasShell']) && !empty($p['fullHasFlash']) && !empty($p['fullHasOffice']),
    'the frame still draws: the menu, the waiting message and the signed-in person\'s office chip');
$expect = ['u' => 'VIEW-U', 'office' => 'VIEW-OFFICE', 'm' => 'VIEW-M', 'isInsp' => 'VIEW-ISINSP', 'navOn' => 'VIEW-NAVON',
           'grp' => 'VIEW-GRP', 'endgrp' => 'VIEW-ENDGRP', 'grpN' => 'VIEW-GRPN', 'curGrp' => 'VIEW-CURGRP', 'lg' => 'VIEW-LG',
           'embed' => 'VIEW-EMBED', 'viewFile' => 'VIEW-VIEWFILE', '__sb' => 'VIEW-SB', '__trail' => 'VIEW-TRAIL', '__back' => 'VIEW-BACK'];
foreach (['full' => 'the full frame', 'embedded' => 'the in-page popup frame'] as $k => $label) {
    $got = (array) ($p[$k] ?? []);
    $bad = [];
    foreach ($expect as $var => $val) if (($got[$var] ?? null) !== $val) $bad[] = '$' . $var . '=' . json_encode($got[$var] ?? null);
    t_ok($got && !$bad, "$label leaves every variable the handler passed untouched" . ($bad ? ' — overwritten: ' . implode(', ', $bad) : ''));
}
t_eq($p['bare']['cur'] ?? null, 'portal-users', 'a screen that was not given $cur still gets the current address for its back link');
$bare = (array) ($p['bare'] ?? []);
t_ok(array_key_exists('u', $bare) && $bare['u'] === null, 'the frame no longer hands its own $u (the signed-in staff member) to the screen');
t_ok(array_key_exists('m', $bare) && $bare['m'] === null, 'nor the last flash message as $m');
t_eq($p['ownCur']['cur'] ?? null, 'VIEW-CUR', 'a $cur the handler passes is kept, not replaced by the address');

// ---- B · "Change permissions" shows, and saves, THAT portal user -------------
$g = $w('perms_get');
$html = (string) ($g['html'] ?? '');
t_ok(!empty($g['ok']) && $html !== '', 'the Change permissions screen opens' . (!empty($g['error']) ? ' (' . $g['error'] . ')' : ''));
t_ok(strpos($html, '<h1>BBP Target</h1>') !== false, 'its heading is the portal user that was clicked, not the staff member');
t_ok(strpos($html, (string) ($g['staffEmail'] ?? '#')) === false || strpos($html, '<h1>' . ($g['staffEmail'] ?? '#')) === false,
    'the staff member\'s own e-mail is not the heading');
t_ok(strpos($html, 'name="id" value="' . (int) ($g['target'] ?? 0) . '"') !== false,
    'the form carries the clicked portal user\'s id');
t_ok(strpos($html, 'name="id" value="' . (int) ($g['staffId'] ?? 0) . '"') === false,
    'the form does not carry the staff member\'s id (the number another client\'s user happened to have)');
t_ok(strpos($html, 'name="partner_id" value="') !== false, 'the form names the client it was drawn for');

$s = $w('perms_post');
$ta = (array) ($s['targetAfter'] ?? []); $ca = (array) ($s['clashAfter'] ?? []);
t_eq((string) ($ta['perms'] ?? ''), 'invoices,complaint', 'Save writes the permissions to the intended portal user');
t_eq((string) ($ta['access_expires'] ?? ''), '2026-12-21', 'and the "Access ends on" date');
t_eq((string) ($ca['perms'] ?? 'x') . '|' . (string) ($ca['access_expires'] ?? 'x'), '|',
    'the other client\'s user with the staff member\'s id number is not touched');

$x = $w('perms_post_mismatch');
$ta = (array) ($x['targetAfter'] ?? []);
t_eq((string) ($ta['perms'] ?? 'x') . '|' . (string) ($ta['access_expires'] ?? 'x'), '|',
    'a save whose form names a different client is refused whole — nothing is written');
$flashText = implode(' ', array_column((array) ($x['flash'] ?? []), 'text'));
t_ok(stripos($flashText, 'Nothing was saved') !== false, 'and staff are told nothing was saved');

// ---- C · the method screens right after a save (DEF-QUA-010, the $m twin) ----
$m = $w('method');
$code = (string) ($m['methodCode'] ?? '');
$det  = (string) ($m['detailHtml'] ?? '');
t_ok(!empty($m['ok']) && $code !== '', 'a method was saved for the check' . (!empty($m['error']) ? ' (' . $m['error'] . ')' : ''));
t_ok($code !== '' && strpos($det, '<h1>' . e($code)) !== false && strpos($det, '>DRAFT<') !== false,
    'the method page drawn with a message waiting shows the method and its DRAFT badge');
t_ok(strpos($det, 'name="id" value="' . (int) ($m['methodId'] ?? -1) . '"') !== false
    && strpos($det, 'name="id" value="0"') === false,
    'its buttons carry the method\'s id, never id=0');
t_ok(strpos((string) ($m['formHtml'] ?? ''), 'BBP method') !== false, 'the edit form drawn with a message waiting is filled with the method');

// ---- D · the audit log's office filter ($office twin) ------------------------
$a = $w('audit');
t_ok(!empty($a['ok']), 'the audit log opens' . (!empty($a['error']) ? ' (' . $a['error'] . ')' : ''));
t_ok(preg_match('/<option value="' . (int) ($a['offB'] ?? -1) . '"\s+selected>/', (string) ($a['html'] ?? '')) === 1,
    'the office the filter asked for is the one shown selected, not the viewer\'s own office');
