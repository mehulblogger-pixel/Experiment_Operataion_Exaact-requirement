<?php
// ============================================================================
//  FEATURE GATES  (Phase 3 — OFF / TEST / LIVE per monetisation feature, spec §76)
//
//  Each money feature rolls out through three states, not a single on/off:
//    • OFF  — invisible; behaves exactly as before it existed (the safe default).
//    • TEST — on for staff/testers only, so it can be exercised without charging users.
//    • LIVE — on for everyone.
//  One registry is the single place the platform owner controls the rollout. Existing
//  per-feature switches (charging, escrow…) still do their specific job; this sits above
//  them as the intended-rollout control, and is what new features check going forward.
//
//  Default OFF for everything — turning a feature on is always a deliberate act.
// ============================================================================

function mkt_gates_catalog() {
    return [
        'subscriptions' => 'Subscriptions (client & freelancer plans)',
        'credit_packs'  => 'Credit-pack top-ups',
        'reporting_fee' => 'Reporting monetisation',
        'ranking_fee'   => 'Ranking & visibility',
        'payments'      => 'Online payment collection (Razorpay)',
        'route'         => 'Razorpay Route (linked accounts)',
        'settlement'    => 'Settlement to professionals',
        'transaction_fee' => 'Marketplace transaction fee',
        'escrow'        => 'Escrow (hold & release)',
        'gst_tcs'       => 'GST-TCS (s.52)',
        'income_tds'    => 'Income-tax TDS',
        'refunds'       => 'Refunds & reversals',
    ];
}
function mkt_gate_states() { return ['OFF' => 'Off', 'TEST' => 'Test (staff only)', 'LIVE' => 'Live (everyone)']; }

/** The rollout state of a feature. Default OFF. Unknown features read OFF. */
function mkt_gate($feature) {
    $feature = (string)$feature;
    if (!isset(mkt_gates_catalog()[$feature])) return 'OFF';
    $v = strtoupper((string)setting_get('gate_' . $feature, 'OFF'));
    return isset(mkt_gate_states()[$v]) ? $v : 'OFF';
}
function mkt_gate_set($feature, $state) {
    $feature = (string)$feature; $state = strtoupper((string)$state);
    if (!isset(mkt_gates_catalog()[$feature]) || !isset(mkt_gate_states()[$state])) return false;
    setting_set('gate_' . $feature, $state);
    return true;
}
function mkt_gate_is_live($feature) { return mkt_gate($feature) === 'LIVE'; }
function mkt_gate_is_test($feature) { return mkt_gate($feature) === 'TEST'; }
/** Is a feature usable for THIS viewer? LIVE → everyone; TEST → staff only; OFF → no. */
function mkt_gate_active($feature) {
    $s = mkt_gate($feature);
    if ($s === 'LIVE') return true;
    if ($s === 'TEST') return function_exists('current_user') && (bool)current_user(); // a logged-in staff member
    return false;
}

/** Route handler — the feature-gate board (master only). */
function ops_mkt_gates($method) {
    ops_require(function_exists('is_master') && is_master(), 'Only the Super Admin can set feature gates.');
    if ($method === 'POST') {
        foreach (array_keys(mkt_gates_catalog()) as $f) {
            if (isset($_POST['gate_' . $f])) mkt_gate_set($f, (string)$_POST['gate_' . $f]);
        }
        flash('Feature gates saved.');
        redirect('/feature-gates');
    }
    $gates = []; foreach (mkt_gates_catalog() as $f => $lbl) $gates[$f] = mkt_gate($f);
    view('ops/mkt_gates', ['catalog' => mkt_gates_catalog(), 'states' => mkt_gate_states(), 'gates' => $gates]);
    return true;
}
