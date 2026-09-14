<?php
// Phase 2 — the financial ledger keeps GMV, Connect revenue and provider cost strictly
// apart (never summed into each other), and the canonical financial state machine
// replaces scattered paid=true flags. Posting is idempotent per (context, ref, category).
t_section('financial ledger + state machine');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    mkt_ledger_migrate();

    // M15 — measure this test's OWN contribution, not the whole table.
    //
    // The suite shares one database and seven other tests also post to this
    // ledger. That was invisible on SQLite, where the transaction above rolled
    // their rows back; on MySQL a CREATE TABLE inside a transaction is an
    // IMPLICIT COMMIT, so the transaction is already over by the time the first
    // migrate() returns and earlier rows are still there. Absolute totals were
    // therefore never safe — they only looked safe on one engine. Deltas assert
    // exactly what these lines mean ("posting 50,000 books 50,000 of GMV") on
    // either engine and in any order.
    $base   = mkt_ledger_totals();
    $bsBase = mkt_ledger_revenue_by_stream();
    $g = function ($tot, $k) use ($base) { return (float)($tot[$k] ?? 0) - (float)($base[$k] ?? 0); };

    // Post the three different monies for one hypothetical deal.
    mkt_ledger_post('GMV',             50000, ['context' => 'ESCROW', 'ref_id' => 9001, 'note' => 'service value']);
    mkt_ledger_post('CONNECT_REVENUE',  1000, ['context' => 'ESCROW', 'ref_id' => 9001, 'subtype' => 'TXN_FEE']);
    mkt_ledger_post('PRO_PAYABLE',     49000, ['context' => 'ESCROW', 'ref_id' => 9001]);
    mkt_ledger_post('PROVIDER_FEE',      120, ['context' => 'ESCROW', 'ref_id' => 9001]);

    $t = mkt_ledger_totals();
    t_eq($g($t,'GMV'), 50000.0, 'GMV is recorded as the facilitated value');
    t_eq($g($t,'CONNECT_REVENUE'), 1000.0, 'Connect revenue is ONLY our fee — not the ₹50,000 service');
    t_eq($g($t,'PROVIDER_FEE'), 120.0, 'the provider fee is its own line, never revenue');
    t_eq($g($t,'PRO_PAYABLE'), 49000.0, 'the professional payable is separate');
    // The take rate is a whole-ledger ratio, so it is asserted as the ratio it
    // claims to be rather than against a figure only a pristine table can give.
    t_eq(mkt_take_rate(), round((float)$t['CONNECT_REVENUE'] / max(0.01, (float)$t['GMV']) * 100, 2),
         'take rate = revenue ÷ GMV');

    // GMV is a metric, not our cash — the categories say so.
    $cats = mkt_ledger_categories();
    t_ok($cats['GMV']['metric'] === true, 'GMV is flagged a metric, not cash');
    t_ok($cats['CONNECT_REVENUE']['metric'] === false, 'Connect revenue is a real financial line');

    // Idempotency — the same event posted twice does not double-count.
    $skip = mkt_ledger_post('GMV', 50000, ['context' => 'ESCROW', 'ref_id' => 9001]);
    t_eq($skip, 0, 'a duplicate (context,ref,category) post is skipped');
    t_eq($g(mkt_ledger_totals(),'GMV'), 50000.0, 'GMV did not double after the duplicate');

    // Revenue by stream.
    mkt_ledger_post('CONNECT_REVENUE', 2499, ['context' => 'ORDER', 'ref_id' => 5, 'subtype' => 'SUBSCRIPTION']);
    mkt_ledger_post('CONNECT_REVENUE',  499, ['context' => 'ORDER', 'ref_id' => 6, 'subtype' => 'CREDIT_PACK']);
    $bs = mkt_ledger_revenue_by_stream();
    t_eq((float)$bs['SUBSCRIPTION'] - (float)($bsBase['SUBSCRIPTION'] ?? 0), 2499.0, 'subscription revenue is streamed');
    t_eq((float)$bs['CREDIT_PACK'] - (float)($bsBase['CREDIT_PACK'] ?? 0), 499.0, 'credit-pack revenue is streamed');
    t_eq((float)$bs['TXN_FEE'] - (float)($bsBase['TXN_FEE'] ?? 0), 1000.0, 'marketplace-fee revenue is streamed');

    // An unknown category is refused.
    t_eq(mkt_ledger_post('NONSENSE', 100, []), 0, 'an unknown category is refused');

    // ---- escrow release posts the ledger, keeping the monies apart ----
    setting_set('escrow_commission_pct', 2);
    [, , $eid] = mkt_escrow_open(9100, 30000, ['client_name' => 'Acme', 'pro_name' => 'Pro X', 'client_party_id' => 77, 'pro_id' => 88]);
    $before = mkt_ledger_totals()['CONNECT_REVENUE'];
    mkt_escrow_release($eid, 'coordinator');
    $after = mkt_ledger_totals();
    t_eq($g($after,'GMV') - 50000.0, 30000.0, 'release books GMV of the deal amount');
    t_eq(round((float)$after['CONNECT_REVENUE'] - (float)$before, 2), 600.0, 'release books ONLY the ₹600 commission as revenue');
    t_ok($g($after,'PRO_PAYABLE') >= 49000 + 29400, 'release books the ₹29,400 professional payable');

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
