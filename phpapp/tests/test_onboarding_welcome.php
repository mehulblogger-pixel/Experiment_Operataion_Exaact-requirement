<?php
// Guided onboarding — the getting-started steps a new company sees. Each step is computed from
// real data (done / not done) and the wording adapts to the install mode (cloud vs licence).
// This guards that logic so a fresh company is guided and a set-up one is left alone.
t_section('guided onboarding steps');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $mode0 = function_exists('install_mode') ? install_mode() : 'cloud';
    connect_market_migrate();

    // shape: every step carries a label, why, url, icon and a done flag
    install_mode_set('cloud');
    $steps = onboarding_steps();
    t_ok(count($steps) >= 3, 'there are getting-started steps');
    foreach ($steps as $s) t_ok(isset($s['label'], $s['why'], $s['url'], $s['icon']) && array_key_exists('done', $s), 'each step is fully described: ' . $s['key']);

    // 22 Oct 2026 — the steps follow the modules THIS company has, not the install
    // mode: an operations company is asked to add its people and raise a work
    // order; one with the marketplace to post a requirement and build a bench.
    $labels = implode(' | ', array_column($steps, 'label'));
    t_ok(strpos($labels, 'Add your people') !== false && strpos($labels, 'Raise your first work order') !== false,
        'with Operations on: add your people + raise a work order');
    t_ok(strpos($labels, 'Post your first requirement') !== false, 'with the marketplace on: post a requirement');
    $byKey = array_column($steps, 'url', 'key');
    t_eq($byKey['people'] ?? '', '/m/inspectors', '"Add your people" opens the real team register (it used to point at /inspectors, which does not exist)');
    t_eq($byKey['call'] ?? '', '/call-new', '"Raise your first work order" opens the new work order screen');
    install_mode_set('licence');
    t_eq(count(onboarding_steps()), count($steps), 'the install mode no longer changes the steps');
    install_mode_set('cloud');
    $offBefore = (string) setting_get('modules_off', '');
    setting_set('modules_off', trim($offBefore . ',operations', ','));
    if (function_exists('licence_disabled')) licence_disabled(true);
    $noOps = implode(' | ', array_column(onboarding_steps(), 'label'));
    t_ok(strpos($noOps, 'Add your people') === false && strpos($noOps, 'work order') === false, 'without Operations, no Operations steps are offered');
    setting_set('modules_off', $offBefore);
    if (function_exists('licence_disabled')) licence_disabled(true);
    // The setup page's checklist uses the same links (it used to read the wrong field and send every "Do this" home).
    $ck = function_exists('cockpit_checklist') ? cockpit_checklist() : [];
    $fixes = array_column($ck, 'fix');
    t_ok(in_array('/users', $fixes, true) && in_array('/m/inspectors', $fixes, true), 'the setup page\'s "Do this" buttons open the right screens, not the home page');

    // done-state reflects real data: post a requirement → that step flips to done
    $before = onboarding_has_requirement();
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Onb Co',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();
    $rid = (int)cx_requirement_create(['title' => 'Onb Req', 'poster_party_id' => $party, 'poster_name' => 'Onb Co',
        'discipline_code' => 'MECH', 'location' => 'X', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+10 days')), 'rate_min' => 1, 'rate_max' => 2, 'rate_unit' => 'day'], true);
    t_ok(onboarding_has_requirement() === true, 'posting a requirement marks that step done');
    $reqStep = null; foreach (onboarding_steps() as $s) if ($s['key'] === 'req') $reqStep = $s;
    t_ok($reqStep && !empty($reqStep['done']), 'the "post requirement" step now reads done');

    // remaining / incomplete track the done count
    t_ok(onboarding_remaining() === count(array_filter(onboarding_steps(), fn($s) => empty($s['done']))), 'remaining counts the not-done steps');
    t_ok(is_bool(onboarding_incomplete()), 'incomplete returns a boolean');

    install_mode_set($mode0);
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('install_mode_set')) install_mode_set($mode0 ?? 'cloud');
}
