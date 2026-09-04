<?php
// Phase 1 — the compliance rule master (versioned, effective-dated) and the fee-rule
// engine. No value is hard-coded; every rate is a dated rule, history is immutable, and
// each computation is reproducible from a stored snapshot.
t_section('compliance rule master + fee engine');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    mkt_rules_migrate(); mkt_fees_migrate();

    // ---- rule master ----
    t_ok(mkt_rule_rate('GST', 'STD', '2026-09-04') == 18.0, 'seeded GST standard resolves to 18% today');
    t_ok(mkt_rule_rate('SAC', '998346', '2026-09-04') == 18.0, 'SAC 998346 (inspection) resolves to 18%');
    t_ok(mkt_rule_resolve('GST', 'STD', '2010-01-01') === null, 'a date before any version has no rule');

    // Supersede GST with a future version — old transactions must not move.
    mkt_rule_set('GST', 'STD', 20, '2027-01-01', ['label' => 'Standard GST', 'source_ref' => 'future notification']);
    t_ok(mkt_rule_rate('GST', 'STD', '2026-09-04') == 18.0, 'a past date still resolves to the OLD 18% (history immutable)');
    t_ok(mkt_rule_rate('GST', 'STD', '2027-06-01') == 20.0, 'a date after the change resolves to the NEW 20%');
    $v1 = ops_one("SELECT * FROM mkt_rules WHERE rule_type='GST' AND code='STD' AND version=1");
    t_eq((string)$v1['effective_until'], '2026-12-31', 'the old version is closed the day before the new one');

    // A snapshot captures the exact version used.
    $snap = mkt_rule_snapshot('GST', 'STD', '2026-09-04');
    t_eq((int)$snap['version'], 1, 'the snapshot for a 2026 date pins version 1');
    t_eq((float)$snap['rate'], 18.0, 'the snapshot carries the 18% rate');
    $snap2 = mkt_rule_snapshot('GST', 'STD', '2027-06-01');
    t_eq((int)$snap2['version'], 2, 'the snapshot for a 2027 date pins version 2');

    // Same-date supersede retires the previous row (no two live versions on one day).
    mkt_rule_set('TCS', 'S52', 0.75, '2025-04-01', ['label' => 'corrected TCS']);
    $live = ops_all("SELECT * FROM mkt_rules WHERE rule_type='TCS' AND code='S52' AND status='ACTIVE'");
    t_eq(count($live), 1, 'superseding on the same date leaves exactly one active version');
    t_ok(mkt_rule_rate('TCS', 'S52', '2025-06-01') == 0.75, 'the corrected TCS rate is in force');

    // ---- fee engine ----
    $fc = mkt_fee_compute('CLIENT', 50000, '2026-05-01');
    t_ok($fc !== null, 'a client fee resolves');
    t_eq((float)$fc['fee'], 1000.0, 'client fee = 2% of ₹50,000');
    t_eq((string)$fc['base'], 'EX_GST', 'the fee base (ex-GST) is recorded on the computation');
    t_eq((float)$fc['base_amount'], 50000.0, 'the base amount used is stored for reproducibility');
    $fp = mkt_fee_compute('PRO', 50000, '2026-05-01');
    t_eq((float)$fp['fee'], 1000.0, 'professional fee = 2% of ₹50,000');

    // No fee before the rule is effective.
    t_ok(mkt_fee_compute('CLIENT', 50000, '2020-01-01') === null, 'no fee applies before the rule is effective');

    // Floor and ceiling.
    mkt_fee_save(['code' => 'CAP', 'name' => 'Capped fee', 'payer' => 'CLIENT', 'method' => 'PERCENT', 'percent' => 2, 'base' => 'EX_GST', 'min_fee' => 500, 'max_fee' => 1500, 'effective_from' => '2026-01-01', 'priority' => 5]);
    $small = mkt_fee_compute('CLIENT', 1000, '2026-06-01');   // 2% = ₹20 → floored to ₹500
    t_eq((float)$small['fee'], 500.0, 'the minimum fee floor applies');
    $big = mkt_fee_compute('CLIENT', 200000, '2026-06-01');   // 2% = ₹4000 → capped at ₹1500
    t_eq((float)$big['fee'], 1500.0, 'the maximum fee ceiling applies');

    // Fixed method.
    mkt_fee_save(['code' => 'FLAT', 'name' => 'Flat pro fee', 'payer' => 'PRO', 'method' => 'FIXED', 'fixed' => 299, 'effective_from' => '2026-01-01', 'priority' => 5]);
    $flat = mkt_fee_compute('PRO', 99999, '2026-06-01');
    t_eq((float)$flat['fee'], 299.0, 'a fixed fee ignores the base amount');

    // ---- reproducibility snapshot store ----
    $sid = mkt_snapshot_save('ESCROW', 4242, ['gst' => $snap, 'fee' => $fc]);
    t_ok($sid > 0, 'a transaction snapshot persists');
    $got = mkt_snapshot_get('ESCROW', 4242);
    t_eq((float)$got['fee']['fee'], 1000.0, 'the stored snapshot reproduces the fee exactly');
    t_eq((int)$got['gst']['version'], 1, 'the stored snapshot reproduces the tax version exactly');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
