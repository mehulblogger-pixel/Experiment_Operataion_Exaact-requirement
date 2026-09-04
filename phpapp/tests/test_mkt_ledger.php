<?php
// Phase 2 — the financial ledger keeps GMV, Connect revenue and provider cost strictly
// apart (never summed into each other), and the canonical financial state machine
// replaces scattered paid=true flags. Posting is idempotent per (context, ref, category).
t_section('financial ledger + state machine');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    mkt_ledger_migrate();

    // Post the three different monies for one hypothetical deal.
    mkt_ledger_post('GMV',             50000, ['context' => 'ESCROW', 'ref_id' => 9001, 'note' => 'service value']);
    mkt_ledger_post('CONNECT_REVENUE',  1000, ['context' => 'ESCROW', 'ref_id' => 9001, 'subtype' => 'TXN_FEE']);
    mkt_ledger_post('PRO_PAYABLE',     49000, ['context' => 'ESCROW', 'ref_id' => 9001]);
    mkt_ledger_post('PROVIDER_FEE',      120, ['context' => 'ESCROW', 'ref_id' => 9001]);

    $t = mkt_ledger_totals();
    t_eq((float)$t['GMV'], 50000.0, 'GMV is recorded as the facilitated value');
    t_eq((float)$t['CONNECT_REVENUE'], 1000.0, 'Connect revenue is ONLY our fee — not the ₹50,000 service');
    t_eq((float)$t['PROVIDER_FEE'], 120.0, 'the provider fee is its own line, never revenue');
    t_eq((float)$t['PRO_PAYABLE'], 49000.0, 'the professional payable is separate');
    t_eq(mkt_take_rate(), 2.0, 'take rate = revenue ÷ GMV = 2%');

    // GMV is a metric, not our cash — the categories say so.
    $cats = mkt_ledger_categories();
    t_ok($cats['GMV']['metric'] === true, 'GMV is flagged a metric, not cash');
    t_ok($cats['CONNECT_REVENUE']['metric'] === false, 'Connect revenue is a real financial line');

    // Idempotency — the same event posted twice does not double-count.
    $skip = mkt_ledger_post('GMV', 50000, ['context' => 'ESCROW', 'ref_id' => 9001]);
    t_eq($skip, 0, 'a duplicate (context,ref,category) post is skipped');
    t_eq((float)mkt_ledger_totals()['GMV'], 50000.0, 'GMV did not double after the duplicate');

    // Revenue by stream.
    mkt_ledger_post('CONNECT_REVENUE', 2499, ['context' => 'ORDER', 'ref_id' => 5, 'subtype' => 'SUBSCRIPTION']);
    mkt_ledger_post('CONNECT_REVENUE',  499, ['context' => 'ORDER', 'ref_id' => 6, 'subtype' => 'CREDIT_PACK']);
    $bs = mkt_ledger_revenue_by_stream();
    t_eq((float)$bs['SUBSCRIPTION'], 2499.0, 'subscription revenue is streamed');
    t_eq((float)$bs['CREDIT_PACK'], 499.0, 'credit-pack revenue is streamed');
    t_eq((float)$bs['TXN_FEE'], 1000.0, 'marketplace-fee revenue is streamed');

    // An unknown category is refused.
    t_eq(mkt_ledger_post('NONSENSE', 100, []), 0, 'an unknown category is refused');

    // ---- escrow release posts the ledger, keeping the monies apart ----
    setting_set('escrow_commission_pct', 2);
    [, , $eid] = mkt_escrow_open(9100, 30000, ['client_name' => 'Acme', 'pro_name' => 'Pro X', 'client_party_id' => 77, 'pro_id' => 88]);
    $before = mkt_ledger_totals()['CONNECT_REVENUE'];
    mkt_escrow_release($eid, 'coordinator');
    $after = mkt_ledger_totals();
    t_eq((float)($after['GMV']) - 50000.0, 30000.0, 'release books GMV of the deal amount');
    t_eq(round((float)$after['CONNECT_REVENUE'] - (float)$before, 2), 600.0, 'release books ONLY the ₹600 commission as revenue');
    t_ok((float)$after['PRO_PAYABLE'] >= 49000 + 29400, 'release books the ₹29,400 professional payable');

    // Releasing again does not double-post (idempotent per escrow ref).
    $rev1 = mkt_ledger_totals()['CONNECT_REVENUE'];
    mkt_ledger_post('CONNECT_REVENUE', 600, ['context' => 'ESCROW', 'ref_id' => $eid, 'subtype' => 'TXN_FEE']);
    t_eq((float)mkt_ledger_totals()['CONNECT_REVENUE'], $rev1, 'a repeated escrow revenue post is ignored');

    // ---- financial state machine ----
    t_ok(in_array('PAID', mkt_finstate_next('PAYMENT_PENDING'), true), 'PAYMENT_PENDING can become PAID');
    t_ok(mkt_finstate_can('PAID', 'ELIGIBLE_FOR_SETTLEMENT'), 'PAID can become eligible for settlement');
    t_ok(mkt_finstate_can('PAID', 'SETTLED') === false, 'PAID cannot jump straight to SETTLED');
    t_ok(mkt_finstate_is_terminal('SETTLED'), 'SETTLED is terminal');
    t_ok(mkt_finstate_is_terminal('REFUNDED'), 'REFUNDED is terminal');
    t_ok(mkt_finstate_next('CREATED') !== [], 'CREATED has forward transitions');
} finally {
    setting_set('escrow_commission_pct', 0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
