<?php
// Marketplace escrow lifecycle (Step 1, gateway-OFF). Hold the client's money, release
// it to the professional when the work is proven done, refund on cancellation, park on
// a dispute. No real money moves yet — this is the state machine and its guards.
t_section('marketplace escrow lifecycle');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$en0 = (int)setting_get('escrow_enabled', 0); $pct0 = (float)setting_get('escrow_commission_pct', 0);
try {
    mkt_escrow_migrate();
    setting_set('escrow_commission_pct', 10);   // 10% platform commission at release

    // Open a hold — commission derived from the %.
    [$ok, $msg, $id] = mkt_escrow_open(5001, 10000, ['client_name' => 'Acme', 'pro_name' => 'R. Singh']);
    t_ok($ok && $id > 0, 'a hold opens: ' . $msg);
    $e = mkt_escrow_get($id);
    t_eq((string)$e['status'], 'HELD', 'a new hold is HELD');
    t_eq((float)$e['commission'], 1000.0, 'commission is 10% of the amount');
    t_eq((float)$e['net_to_pro'], 9000.0, 'the professional’s share is the remainder');

    // Idempotent — a second open for the same engagement returns the same hold.
    [$ok2, , $id2] = mkt_escrow_open(5001, 10000);
    t_eq($id2, $id, 'opening the same engagement twice does not duplicate the hold');

    // Transitions allowed from HELD.
    t_ok(in_array('RELEASED', mkt_escrow_allowed_next('HELD'), true), 'HELD can be released');
    t_ok(mkt_escrow_allowed_next('RELEASED') === [], 'RELEASED is terminal');

    // Release it.
    [$rok, $rmsg] = mkt_escrow_release($id, 'coordinator');
    t_ok($rok, 'the hold releases: ' . $rmsg);
    t_eq((string)mkt_escrow_get($id)['status'], 'RELEASED', 'the hold is now RELEASED');

    // A released hold cannot be refunded (guard).
    [$bad, $bmsg] = mkt_escrow_refund($id, 'coordinator');
    t_ok($bad === false, 'a released hold cannot be refunded: ' . $bmsg);

    // Refund path from a fresh hold.
    [, , $id3] = mkt_escrow_open(5002, 8000);
    [$fok] = mkt_escrow_refund($id3, 'coordinator', 'client cancelled');
    t_ok($fok, 'a held amount can be refunded');
    t_eq((string)mkt_escrow_get($id3)['status'], 'REFUNDED', 'the hold is REFUNDED');

    // Dispute → resolve (refund).
    [, , $id4] = mkt_escrow_open(5003, 6000);
    [$dok] = mkt_escrow_dispute($id4, 'client', 'no-show alleged');
    t_ok($dok, 'a held amount can be disputed');
    t_eq((string)mkt_escrow_get($id4)['status'], 'DISPUTED', 'the hold is DISPUTED');
    [$res] = mkt_escrow_resolve($id4, 'REFUND', 'manager');
    t_ok($res, 'a dispute resolves to a refund');
    t_eq((string)mkt_escrow_get($id4)['status'], 'REFUNDED', 'the disputed hold is REFUNDED');

    // Dispute → resolve (release).
    [, , $id5] = mkt_escrow_open(5004, 7000);
    mkt_escrow_dispute($id5, 'client', 'quality query');
    mkt_escrow_resolve($id5, 'RELEASE', 'manager');
    t_eq((string)mkt_escrow_get($id5)['status'], 'RELEASED', 'a dispute can resolve to a release');

    // Explicit commission override.
    [, , $id6] = mkt_escrow_open(5005, 5000, ['commission' => 250]);
    $e6 = mkt_escrow_get($id6);
    t_eq((float)$e6['commission'], 250.0, 'an explicit commission is honoured');
    t_eq((float)$e6['net_to_pro'], 4750.0, 'net is amount − explicit commission');

    // ---- the report-approval auto-release trigger ----
    setting_set('escrow_enabled', 0);
    [, , $id7] = mkt_escrow_open(5006, 9000);
    t_ok(mkt_escrow_on_report_approved(5006) === false, 'auto-release is a no-op while escrow is OFF');
    setting_set('escrow_enabled', 1);
    t_ok(mkt_escrow_on_report_approved(5006) === true, 'an approved report releases the held funds when escrow is ON');
    t_eq((string)mkt_escrow_get($id7)['status'], 'RELEASED', 'the auto-released hold is RELEASED');
    t_ok(mkt_escrow_on_report_approved(5006) === false, 'a second approval finds no live hold to release');

    // Totals reflect the money in each state.
    $tot = mkt_escrow_totals();
    t_ok(($tot['REFUNDED'] ?? 0) >= 14000, 'refunded total sums the refunded holds');
} finally {
    setting_set('escrow_enabled', $en0);
    setting_set('escrow_commission_pct', $pct0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
