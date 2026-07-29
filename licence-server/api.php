<?php
// ============================================================================
//  The two machine endpoints. Everything a person touches is in index.php.
//
//    ?action=licence&install=<id>   an installation asking what it is entitled
//                                   to. Called every day, and every fifteen
//                                   minutes when an expiry is close.
//
//    ?action=razorpay_webhook       Razorpay saying a payment succeeded. This
//                                   is the endpoint that makes renewal instant:
//                                   money clears, expiry moves, and the next
//                                   check-in — at most fifteen minutes later,
//                                   or immediately if the customer presses the
//                                   button — collects the new key.
// ============================================================================

require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function ls_out($code, $data) { http_response_code($code); echo json_encode($data); exit; }

$action = $_GET['action'] ?? '';

// ---------------------------------------------------------------------------
//  An installation collecting its key
// ---------------------------------------------------------------------------
if ($action === 'licence') {
    $id = trim((string)($_GET['install'] ?? ''));
    if ($id === '') ls_out(400, ['error' => 'No installation id given.']);

    $row = ls_install($id);
    // Deliberately the same answer for "no such installation" as for a
    // malformed one. Anything more helpful is a way to discover valid ids.
    if (!$row) ls_out(404, ['error' => 'This installation is not registered. Contact MGH.']);

    // Record the visit before deciding anything. A support call that starts
    // "it says my licence lapsed" is answered by knowing whether the machine
    // has been asking at all — a silent installation is usually a broken cron,
    // not a broken licence.
    ls_db()->prepare("UPDATE installs SET last_seen_at = ?, last_seen_ip = ?, seen_count = seen_count + 1
                      WHERE install_id = ?")
           ->execute([ls_now(), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), $id]);

    if (($row['status'] ?? '') === 'SUSPENDED')
        ls_out(403, ['error' => 'This subscription is suspended. Contact MGH.']);

    if (trim((string)$row['expires_on']) === '')
        ls_out(409, ['error' => 'This installation has no subscription dates set yet. Contact MGH.']);

    $m = ls_mint($row);
    if (!empty($m['err'])) ls_out(500, ['error' => $m['err']]);

    ls_out(200, [
        'key'     => $m['key'],
        'expires' => $m['claims']['exp'],
        'seats'   => $m['claims']['seats'],
        'mods'    => $m['claims']['mods'],
        // So a customer's own licence screen can say something true about
        // where their renewal stands without anybody telephoning.
        'state'   => ls_state($row),
        'days'    => ls_days_left($row),
    ]);
}

// ---------------------------------------------------------------------------
//  Razorpay: a payment succeeded
// ---------------------------------------------------------------------------
if ($action === 'razorpay_webhook') {
    $secret = ls_webhook_key();
    if ($secret === '') ls_out(503, ['error' => 'No webhook secret configured on this server.']);

    $raw = file_get_contents('php://input') ?: '';
    $sig = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

    // Verify BEFORE parsing. An unsigned request must not reach any code that
    // could act on its contents — this endpoint moves subscription dates, and
    // it is on the public internet.
    $expect = hash_hmac('sha256', $raw, $secret);
    if ($sig === '' || !hash_equals($expect, $sig)) ls_out(401, ['error' => 'Bad signature.']);

    $b = json_decode($raw, true);
    if (!is_array($b)) ls_out(400, ['error' => 'Body was not JSON.']);

    $event = (string)($b['event'] ?? '');
    if (!in_array($event, ['payment.captured', 'subscription.charged', 'order.paid'], true))
        ls_out(200, ['ignored' => $event]);      // 200, or Razorpay retries forever

    // The installation and the term travel in the notes on the payment. They
    // are put there when the payment link or subscription is created, so this
    // endpoint never has to guess who paid for what.
    $ent    = $b['payload']['payment']['entity'] ?? ($b['payload']['order']['entity'] ?? []);
    $notes  = $ent['notes'] ?? [];
    $inst   = trim((string)($notes['install'] ?? ''));
    $months = (int)($notes['months'] ?? 12);
    $refId  = (string)($ent['id'] ?? '');
    $amount = ((float)($ent['amount'] ?? 0)) / 100;

    if ($inst === '') {
        // Answer 200 so Razorpay stops retrying, but keep the evidence: this is
        // a real payment that nobody can match, and it needs a human.
        ls_db()->prepare("INSERT INTO ledger (install_id, kind, amount, reference, detail, at)
                          VALUES ('', 'PAYMENT', ?, ?, ?, ?)")
               ->execute([$amount, $refId, 'Payment received with no installation id in its notes — needs matching by hand.', ls_now()]);
        ls_out(200, ['ok' => true, 'warning' => 'no install id in notes; recorded for manual matching']);
    }

    // Razorpay retries on any doubt, so the same payment WILL arrive twice.
    // Extending twice would hand out a free year.
    $seen = ls_db()->prepare("SELECT COUNT(*) FROM ledger WHERE reference = ? AND kind = 'PAYMENT'");
    $seen->execute([$refId]);
    if ((int)$seen->fetchColumn() > 0) ls_out(200, ['ok' => true, 'duplicate' => true]);

    $r = ls_extend($inst, $months, 'PAYMENT', $amount, $refId, $event);
    if (!empty($r['err'])) {
        ls_db()->prepare("INSERT INTO ledger (install_id, kind, amount, reference, detail, at)
                          VALUES (?, 'PAYMENT', ?, ?, ?, ?)")
               ->execute([$inst, $amount, $refId, 'Payment received but could not be applied: ' . $r['err'], ls_now()]);
        ls_out(200, ['ok' => false, 'error' => $r['err']]);
    }
    ls_out(200, ['ok' => true, 'customer' => $r['customer'], 'was' => $r['was'], 'now' => $r['now']]);
}

// The public half, so a new installation can be pointed at this server without
// anybody copying a key by hand.
if ($action === 'pubkey') {
    $p = ls_public_key();
    if ($p === '') ls_out(503, ['error' => 'No signing key on this server yet.']);
    header('Content-Type: text/plain; charset=utf-8');
    echo $p; exit;
}

ls_out(404, ['error' => 'Unknown action.']);
