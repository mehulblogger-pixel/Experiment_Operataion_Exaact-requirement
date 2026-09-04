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
    t_ok(count($steps) === 3, 'there are three getting-started steps');
    foreach ($steps as $s) t_ok(isset($s['label'], $s['why'], $s['url'], $s['icon']) && array_key_exists('done', $s), 'each step is fully described: ' . $s['key']);

    // cloud wording leans on the marketplace; licence leans on your own people
    $cloudLabels = implode(' | ', array_column($steps, 'label'));
    t_ok(strpos($cloudLabels, 'Post your first requirement') !== false, 'cloud step: post to the marketplace');
    install_mode_set('licence');
    $licLabels = implode(' | ', array_column(onboarding_steps(), 'label'));
    t_ok(strpos($licLabels, 'Add your people') !== false && strpos($licLabels, 'Create your first job') !== false, 'licence steps: add your own people + create a job');
    install_mode_set('cloud');

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
