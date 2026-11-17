<?php
// ============================================================================
//  Batch 2 (owner, 17 Nov 2026 — plan 29, points 10 and 11). The staff side of
//  the vendor portal and of client requests.
//
//  Point 10: "New link" on the vendor portal register put the password-setting
//            link on the staff screen (through the staff session), and the
//            register listed every office's vendor people. Now — the same fix
//            the client side got (CP-021) — the link is e-mailed to the vendor
//            person (kind portal_reset, so the e-mail log keeps no copy), staff
//            are told only "sent" / "could not be sent", and the register and
//            its actions cover the vendors of the staff member's own office
//            (company-wide roles: all).
//  Point 11: "What clients have asked for" and /portal-request let any office
//            answer or convert any client's request. Now the list and every
//            action cover requests from the staff member's own office's
//            clients (company-wide roles: all).
//
//  Through the production dispatcher in a separate process (bb_drive); every
//  committed row is removed at the end.
// ============================================================================
t_section('Batch 2 — vendor "New link" by e-mail and own-office vendors; client requests of your own office (points 10, 11)');

require_once __DIR__ . '/_branch_batch_helpers.php';
portal_migrate();
if (function_exists('cvp_migrate')) cvp_migrate();
$pdo = db();
$tag = 'b2p' . getmypid();
$made = ['vendor_users' => [], 'portal_requests' => [], 'business_partners' => []];
$q = function ($sql, $a = []) use ($pdo) { $pdo->prepare($sql)->execute($a); return (int)$pdo->lastInsertId(); };
$vu = fn($id) => ops_one("SELECT * FROM vendor_users WHERE id=?", [(int)$id]);
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), (array)($r['flash'] ?? [])));

try {
    $A = bb_office('B2P Ahmedabad'); $B = bb_office('B2P Mumbai');
    $coordA = bb_user('COORDINATOR', $A);
    $admin  = bb_user('ADMIN', $A);                 // company-wide
    $party = function ($name, $off, $vendor) use ($q, &$made) {
        $id = $q("INSERT INTO business_partners (display_name, legal_name, is_client, is_vendor, status, home_branch_id) VALUES (?,?,?,?,'ACTIVE',?)",
                 [$name, $name . ' Ltd', $vendor ? 0 : 1, $vendor ? 1 : 0, $off]);
        $made['business_partners'][] = $id; return $id;
    };
    $VA = $party($tag . ' Vendor Own', $A, true);
    $VB = $party($tag . ' Vendor Other', $B, true);
    $mkVu = function ($vid, $who) use ($q, $tag, &$made) {
        $id = $q("INSERT INTO vendor_users (vendor_id, email, name, password_hash, is_active, must_change, invite_token, created_at) VALUES (?,?,?,'h',1,0,'',?)",
                 [$vid, $tag . '.' . $who . '@example.test', 'B2P ' . $who, date('c')]);
        $made['vendor_users'][] = $id; return $id;
    };
    $uA = $mkVu($VA, 'vown'); $uB = $mkVu($VB, 'vother');
    $eA = $tag . '.vown@example.test'; $eB = $tag . '.vother@example.test';

    // ---- point 10 · the vendor register covers the staff member's own office's vendors ----------
    $pg = bb_drive($coordA, 'vendor-users', 'GET', [], [], [$eA, $eB, 'Who can sign in']);
    t_ok(!empty($pg['has']['Who can sign in']), 'the vendor portal register opens for a Coordinator' . (!empty($pg['error']) ? ' (' . $pg['error'] . ')' : ''));
    t_ok(!empty($pg['has'][$eA]), 'it lists the vendor people of their own office\'s vendors');
    t_ok(empty($pg['has'][$eB]), 'it does not list another office\'s vendor people (point 10)');
    $pg = bb_drive($admin, 'vendor-users', 'GET', [], [], [$eA, $eB]);
    t_ok(!empty($pg['has'][$eA]) && !empty($pg['has'][$eB]), 'a company-wide role (Admin) still sees every vendor\'s people');

    $r = bb_drive($coordA, 'vendor-user-toggle', 'POST', [], ['id' => $uB]);
    t_eq((int)($vu($uB)['is_active'] ?? -1), 1, 'Withdraw on another office\'s vendor person changes nothing');
    t_ok(stripos($said($r), 'not one of your vendors') !== false, '…and says so (' . $said($r) . ')');

    $r = bb_drive($coordA, 'vendor-user-reinvite', 'POST', [], ['id' => $uB]);
    t_eq((string)($vu($uB)['password_hash'] ?? ''), 'h', '"New link" on another office\'s vendor person keeps their password');
    t_eq((string)($vu($uB)['invite_token'] ?? 'x'), '', '…makes no link for them');
    t_eq((int)ops_val("SELECT COUNT(*) FROM email_log WHERE to_addr LIKE ?", ['%' . $eB . '%']), 0, '…and e-mails nobody');

    // ---- point 10 · "New link" is e-mailed to the vendor person, never shown to staff ------------
    $r = bb_drive($coordA, 'vendor-user-reinvite', 'POST', [], ['id' => $uA]);
    $after = $vu($uA); $tok = (string)($after['invite_token'] ?? '');
    t_ok($tok !== '' && (string)($after['password_hash'] ?? 'x') === '', '"New link" cancels the old password and makes a fresh link (' . $said($r) . ')');
    $log = ops_all("SELECT * FROM email_log WHERE to_addr LIKE ? ORDER BY id DESC", ['%' . $eA . '%']) ?: [];
    t_eq(count($log), 1, 'one e-mail goes to the vendor person\'s own address');
    t_eq((string)($log[0]['kind'] ?? ''), 'portal_reset', '…sent as a sign-in-link message (kind portal_reset)');
    t_ok($tok !== '' && strpos((string)($log[0]['body'] ?? ''), $tok) === false, 'the e-mail log keeps no copy of the link');
    t_ok($tok !== '' && strpos($said($r), $tok) === false && strpos($said($r), '/vendor/accept') === false, 'the message staff see does not contain the link');
    t_ok(preg_match('/sent to|could not be sent/i', $said($r)) === 1, 'staff are told whether it was sent');
    $src = (string)file_get_contents(dirname(__DIR__) . '/lib/cvp.php');
    t_ok(strpos($src, "\$_SESSION['vendor_invite_link'] =") === false, 'the link is no longer put in the staff member\'s session');
    $view = (string)file_get_contents(dirname(__DIR__) . '/views/ops/vendor_users.php');
    t_ok(strpos($view, "\$_SESSION['vendor_invite_link'] ??") === false, 'and the register no longer reads one from it');
    $pg = bb_drive($coordA, 'vendor-users', 'GET', [], [], ['/vendor/accept?t=' . $tok]);
    t_ok(empty($pg['has']['/vendor/accept?t=' . $tok]), 'the register drawn afterwards shows no sign-in link');

    // In-process: the mail helper itself.
    $GLOBALS['__mail'] = [];
    $GLOBALS['__mail_capture'] = function ($to, $subject, $body, $kind) { $GLOBALS['__mail'][] = compact('to', 'subject', 'body', 'kind'); };
    if (function_exists('cvp_vendor_send_new_link'))
        cvp_vendor_send_new_link(['email' => $eA, 'name' => 'B2P vown'], 'https://x.test/vendor/accept?t=abc123');
    unset($GLOBALS['__mail_capture']);
    $m = $GLOBALS['__mail'][0] ?? [];
    t_ok(($m['to'] ?? '') === $eA && strpos((string)($m['body'] ?? ''), '/vendor/accept?t=abc123') !== false && ($m['kind'] ?? '') === 'portal_reset',
        'cvp_vendor_send_new_link() e-mails the link to the person, as a portal_reset message');

    // ---- point 11 · client requests of your own office's clients ------------------------------
    $CA = $party($tag . ' Client Own', $A, false);
    $CB = $party($tag . ' Client Other', $B, false);
    $mkReq = function ($pid, $subj) use ($q, &$made) {
        $id = $q("INSERT INTO portal_requests (partner_id, subject, detail, site, wanted_on, status, created_at) VALUES (?,?,?, 'Works', '2026-12-01', 'NEW', ?)",
                 [$pid, $subj, 'Please inspect ' . $subj, date('c')]);
        $made['portal_requests'][] = $id; return $id;
    };
    $sA = $tag . '-req-own'; $sB = $tag . '-req-other';
    $rA = $mkReq($CA, $sA); $rB = $mkReq($CB, $sB);
    $pg = bb_drive($coordA, 'portal-users', 'GET', [], [], [$sA, $sB, 'What clients have asked for']);
    t_ok(!empty($pg['has']['What clients have asked for']), 'the client register opens with "What clients have asked for"' . (!empty($pg['error']) ? ' (' . $pg['error'] . ')' : ''));
    t_ok(!empty($pg['has'][$sA]), 'it lists a request from their own office\'s client');
    t_ok(empty($pg['has'][$sB]), 'it does not list a request from another office\'s client (point 11)');
    $pg = bb_drive($admin, 'portal-users', 'GET', [], [], [$sA, $sB]);
    t_ok(!empty($pg['has'][$sA]) && !empty($pg['has'][$sB]), 'a company-wide role (Admin) sees every client\'s requests');

    $reqRow = fn($id) => ops_one("SELECT * FROM portal_requests WHERE id=?", [(int)$id]);
    $r = bb_drive($coordA, 'portal-request', 'POST', [], ['id' => $rB, 'status' => 'DECLINED', 'reply' => 'No']);
    t_eq((string)$reqRow($rB)['status'], 'NEW', 'declining another office\'s client request changes nothing');
    t_ok(stripos($said($r), 'not from one of your office') !== false, '…and says so (' . $said($r) . ')');
    $r = bb_drive($coordA, 'portal-request', 'POST', [], ['id' => $rB, 'status' => 'CONVERTED', 'reply' => 'ok']);
    t_eq((string)$reqRow($rB)['status'], 'NEW', 'converting another office\'s client request changes nothing');
    t_eq((int)ops_val("SELECT COUNT(*) FROM calls WHERE client_id=?", [$CB]), 0, '…and raises no work order');
    $r = bb_drive($coordA, 'portal-request', 'POST', [], ['id' => $rA, 'status' => 'ACCEPTED', 'reply' => 'We will call you']);
    t_eq((string)$reqRow($rA)['status'], 'ACCEPTED', 'control: answering their own office\'s client request works (' . $said($r) . ')');

    // The Directory tile's "new requests" count asks the same list.
    bb_as($coordA);
    $newIds = array_map('intval', array_column(portal_requests_all('NEW'), 'id'));
    t_ok(in_array($rB, $newIds, true) === false, 'the "new requests" count a Coordinator sees leaves out another office\'s client');
    t_ok(function_exists('portal_staff_request') && portal_staff_request($rB) === null && portal_staff_request($rA) !== null,
        'portal_staff_request(): own office\'s client only');
    bb_as($admin);
    t_ok(in_array($rB, array_map('intval', array_column(portal_requests_all('NEW'), 'id')), true), '…while Admin\'s count includes it');
    bb_as_nobody();
} finally {
    $del = function ($sql, $a = []) use ($pdo) { try { $pdo->prepare($sql)->execute($a); } catch (Throwable $e) {} };
    $del("DELETE FROM email_log WHERE to_addr LIKE ?", ['%' . $tag . '.%']);
    $parties = $made['business_partners'] ? implode(',', array_map('intval', $made['business_partners'])) : '0';
    $del("DELETE FROM calls WHERE client_id IN ($parties)");
    foreach ($made as $t => $ids) foreach (array_reverse($ids) as $id) $del("DELETE FROM $t WHERE id=?", [(int)$id]);
    bb_cleanup();
    bb_as_nobody();
}
