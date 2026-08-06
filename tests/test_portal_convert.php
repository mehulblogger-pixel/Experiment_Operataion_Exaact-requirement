<?php
// Accepting a portal request raises the actual call (open item P1).
t_section('portal request -> call (P1)');

portal_migrate();

// A client, and a request they raised through the portal.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, created_at)
    VALUES (?,?,?,?)")->execute(['Nirma Ltd', 'Nirma Limited', 1, date('c')]);
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO portal_requests (partner_id, subject, detail, site, wanted_on, contact_name, contact_phone, status, created_at)
    VALUES (?,?,?,?,?,?,?,'NEW',?)")
    ->execute([$pid, 'Third-party inspection of pressure vessels', 'Two vessels at the Bharuch plant, hydro + NDT.',
               'Bharuch works', '2026-09-15', 'R. Shah', '9800000000', date('c')]);
$reqId = (int)db()->lastInsertId();
$req = ops_one("SELECT * FROM portal_requests WHERE id=?", [$reqId]);

// Convert it.
[$callId, $err] = portal_request_to_call($req);
t_ok($callId > 0 && $err === '', 'a call is created from the request');

$call = ops_one("SELECT * FROM calls WHERE id=?", [$callId]);
t_ok(is_array($call), 'the call row exists');
t_eq((int)$call['client_id'], $pid, 'the call carries the client from the request');
t_eq($call['status'], 'OPEN', 'the call is left OPEN for a coordinator to complete');
t_eq($call['inspection_required_date'], '2026-09-15', 'the wanted-by date carries across');
t_ok(strpos((string)$call['notes'], 'pressure vessels') !== false, 'the client brief is folded into the call notes');
t_ok(strpos((string)$call['notes'], 'Bharuch works') !== false, 'the site is recorded on the call');
t_ok(strpos((string)$call['notes'], 'R. Shah') !== false, 'the client contact is recorded on the call');
t_ok(strncmp((string)$call['call_code'], 'CALL', 4) === 0, 'the call gets a normal call code');

// Link the request, as the handler does, then confirm idempotency.
db()->prepare("UPDATE portal_requests SET status='CONVERTED', call_id=? WHERE id=?")->execute([$callId, $reqId]);
$req2 = ops_one("SELECT * FROM portal_requests WHERE id=?", [$reqId]);
[$again, $err2] = portal_request_to_call($req2);
t_eq($again, $callId, 'converting an already-linked request reuses the same call — never a duplicate');
t_eq((int)ops_val("SELECT COUNT(*) FROM calls WHERE client_id=?", [$pid]), 1, 'only one call exists for the client');

// A null/missing request is refused cleanly.
[$z, $ze] = portal_request_to_call(null);
t_ok($z === 0 && $ze !== '', 'a missing request is refused, not fatal');
