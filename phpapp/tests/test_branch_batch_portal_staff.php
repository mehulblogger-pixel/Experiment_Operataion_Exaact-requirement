<?php
// ============================================================================
//  Branch batch P4 (CP-021) and P5 (DEF-FLOW-007) — the staff side of the
//  client portal.
//
//  P4: a Coordinator saw and managed the portal people of EVERY client, and
//      "New link" put a password-setting link on the staff screen. Now the
//      register and its actions cover the same clients as the invite list (the
//      staff member's own office's clients; company-wide roles see all), and
//      "New link" is e-mailed to the client person — staff see only "sent".
//  P5: "Accept & raise the call" made a work order for a client ON HOLD. Now it
//      asks partner_trade_block() like every other door; the request stays open.
//
//  Route-level checks run in a separate process (tests/_branch_batch_portal_worker.php):
//  the handlers end in redirect(), which exits.
// ============================================================================
t_section('Branch batch P4/P5 — portal people of your own clients; "New link" by e-mail; hold rule on portal requests');

require_once __DIR__ . '/_branch_batch_portal_lib.php';
$w = $GLOBALS['__bbp_worker'];
$flashOf = fn($r) => implode(' ', array_column((array) ($r['flash'] ?? []), 'text'));

// ---- P4 · the register covers the staff member's own office's clients -------
$s = $w('scope');
$emails = (array) ($s['registerEmails'] ?? []);
$tag = (string) ($s['tag'] ?? '#');
t_ok(!empty($s['ok']), 'the portal register opens for a Coordinator' . (!empty($s['error']) ? ' (' . $s['error'] . ')' : ''));
t_ok(in_array($tag . '.target@example.test', $emails, true), 'it lists the portal people of the Coordinator\'s own office\'s clients');
t_ok(!in_array($tag . '.otheroffice@example.test', $emails, true), 'it does not list the portal people of another office\'s client');
t_ok(strpos((string) ($s['html'] ?? ''), $tag . '.otheroffice@example.test') === false, 'and the screen does not show them');
t_eq((int) ($s['otherAfter']['is_active'] ?? -1), 1, 'Withdraw on another office\'s client person changes nothing');
t_ok(stripos($flashOf($s), 'not one of your clients') !== false, 'and says so');

$sw = $w('scope_wide');
$swTag = (string) ($sw['tag'] ?? '#');
t_ok(in_array($swTag . '.otheroffice@example.test', (array) ($sw['registerEmails'] ?? []), true)
    && in_array($swTag . '.target@example.test', (array) ($sw['registerEmails'] ?? []), true),
    'a company-wide role (Admin) still sees every client\'s portal people');

$o = $w('perms_get_other');
t_ok(strpos((string) ($o['html'] ?? ''), 'BBP Other Office') === false && (int) ($o['status'] ?? 0) === 404,
    '"Change" on another office\'s client person is "not found"');

$ri = $w('scope_reinvite');
t_eq((string) ($ri['otherAfter']['password_hash'] ?? ''), 'h', '"New link" on another office\'s client person changes nothing');
t_eq((string) ($ri['otherAfter']['invite_token'] ?? 'x'), '', 'and makes no link for them');
t_eq(count((array) ($ri['mail'] ?? [])), 0, 'and e-mails nobody');

// ---- P4 · "New link" goes to the client person's own e-mail, never on screen --
$n = $w('reinvite');
$ta = (array) ($n['targetAfter'] ?? []);
$tok = (string) ($ta['invite_token'] ?? '');
t_ok(!empty($n['ok']) && $tok !== '' && (string) ($ta['password_hash'] ?? 'x') === '', '"New link" cancels the old password and makes a fresh link' . (!empty($n['error']) ? ' (' . $n['error'] . ')' : ''));
$mail = (array) ($n['mail'] ?? []);
t_ok(count($mail) === 1 && ($mail[0]['to'] ?? '') === ($ta['email'] ?? '#'), 'the link is e-mailed to the client person\'s own address');
t_ok(count($mail) === 1 && strpos((string) $mail[0]['body'], '/portal/accept?t=' . $tok) !== false, 'the e-mail carries the link');
t_ok(count($mail) === 1 && ($mail[0]['kind'] ?? '') === 'portal_reset', 'it is sent as a sign-in-link message (kind portal_reset)');
t_ok($tok !== '' && strpos($flashOf($n), $tok) === false, 'the message staff see does not contain the link');
t_ok(($n['sessionLink'] ?? null) === null, 'the link is not left in the staff member\'s session for the register to show');
t_ok($tok !== '' && strpos((string) ($n['registerHtml'] ?? ''), $tok) === false && strpos((string) ($n['registerHtml'] ?? ''), '/portal/accept?t=') === false,
    'the register drawn straight afterwards shows no sign-in link');
t_ok(count((array) ($n['logBodies'] ?? [])) >= 1, 'the e-mail is recorded in the e-mail log');
$logLeak = false; foreach ((array) ($n['logBodies'] ?? []) as $b) if ($tok !== '' && strpos((string) $b, $tok) !== false) $logLeak = true;
t_ok(!$logLeak, 'the e-mail log keeps no copy of the link');
t_ok(preg_match('/sent to|could not be sent/i', $flashOf($n)) === 1, 'staff are told whether it was sent');

// ---- P5 · a client on hold: "Accept & raise the call" is refused ------------
$h = $w('hold');
t_ok(!empty($h['ok']), 'the request answer ran' . (!empty($h['error']) ? ' (' . $h['error'] . ')' : ''));
t_eq((int) ($h['calls'] ?? -1), 0, 'no work order is created for a client on hold');
t_eq((string) ($h['reqAfter']['status'] ?? ''), 'NEW', 'the request stays open');
t_ok(stripos($flashOf($h), 'Nothing new can be raised') !== false, 'staff see the usual hold message');

// In-process: the write defends itself, whoever calls it.
portal_migrate();
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status, hold_status, hold_reason, created_at)
                   VALUES ('BBP Held','BBP Held Ltd',1,'ACTIVE','HOLD','unpaid',?)")->execute([date('c')]);
    $held = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO portal_requests (partner_id, subject, detail, status, created_at) VALUES (?,?,?,'NEW',?)")
        ->execute([$held, 'Held request', 'x', date('c')]);
    $req = ops_one("SELECT * FROM portal_requests WHERE id=?", [(int) db()->lastInsertId()]);
    [$cid, $err] = portal_request_to_call($req);
    t_ok($cid === 0 && $err === partner_trade_block($held), 'portal_request_to_call() refuses a client on hold with the hold message');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE client_id=?", [$held]), 0, 'and creates nothing');
    db()->prepare("UPDATE business_partners SET hold_status='' WHERE id=?")->execute([$held]);
    [$cid2, $err2] = portal_request_to_call($req);
    t_ok($cid2 > 0 && $err2 === '', 'once the hold is cleared the same request raises its call');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
