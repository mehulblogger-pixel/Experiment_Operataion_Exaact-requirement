<?php
// Regression cover for the UAT failures the user reported on the recruitment
// playbook: terminology (9.1/9.2) and the requirement profit/cost maths (10.1).

t_section('Playbook fixes — terminology (9.1 / 9.2)');

if (!function_exists('term_groups_licensed') || !function_exists('term_save')) {
    t_ok(true, 'terminology helpers not present — skipped');
} else {
    $savedOff  = (string) setting_get('modules_off', '');
    $savedKey  = (string) setting_get('licence_key', '');
    $savedTerm = (string) setting_get('terms', '');
    setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);
    setting_set('modules_off', 'sales,operations,money,reporting');
    if (function_exists('licence_disabled')) licence_disabled(true);

    // 9.1 — a recruitment plan now HAS its own editable vocabulary.
    $g = term_groups_licensed();
    t_ok(isset($g['Recruitment']), 'a Recruitment word-group is shown for a recruitment plan');
    t_ok(isset($g['Recruitment']['candidate']) && isset($g['Recruitment']['requisition']) && isset($g['Recruitment']['offer']),
        'Candidate, Requisition and Offer are editable words for a recruiter');
    t_ok(!isset($g['Operations']) && !isset($g['Sales']) && !isset($g['Money']) && !isset($g['Reporting']),
        'other trades\' word-groups stay hidden');

    // 9.2 (mechanism B) — saving from a screen that only shows SOME groups must
    // NOT wipe the words it isn't displaying. Seed an Operations override (hidden
    // for recruitment), then save a recruitment-screen POST that omits it.
    term_overrides(['call' => ['Site Visit', 'Site Visits']]);      // prime the cache + as if saved
    setting_set('terms', json_encode(['call' => ['Site Visit', 'Site Visits']]));
    term_save(['t_candidate_s' => 'Applicant', 't_candidate_p' => 'Applicants']);  // form without t_call_*
    $ov = term_overrides();
    t_ok(($ov['call'][0] ?? '') === 'Site Visit', 'a hidden-group override survives a save from the filtered screen');
    t_ok(($ov['candidate'][0] ?? '') === 'Applicant', 'the word that WAS on the form is still updated');

    // read-back is live (no stale cache): T()/TP() reflect the just-saved value.
    t_ok(TP('candidate') === 'Applicants', 'a renamed word is read back immediately by TP()');

    setting_set('terms', $savedTerm); term_overrides($savedTerm !== '' ? (json_decode($savedTerm, true) ?: []) : []);
    setting_set('modules_off', $savedOff);
    setting_set('licence_key', $savedKey);
    if (function_exists('lk_state')) lk_state(true);
    if (function_exists('licence_disabled')) licence_disabled(true);
    t_ok(true, 'terminology state restored');
}

t_section('Playbook fixes — requirement profit / cost (10.1)');

if (!function_exists('req_commercials')) {
    t_ok(true, 'req_commercials not present — skipped');
} else {
    // An unspecified duration must NOT zero the P&L — it is quoted per month.
    $c = req_commercials(['quantity' => 1, 'billing_rate' => 100000, 'rate_basis' => 'MONTHLY', 'budgeted_cost' => 70000]);
    t_ok(abs($c['months'] - 1) < 0.001, 'an unspecified duration defaults to one month, not zero');
    t_ok(abs($c['revenue'] - 100000) < 0.01, 'revenue is the monthly bill, not zero');
    t_ok(abs($c['cost'] - 70000) < 0.01, 'cost is the monthly cost, not zero');
    t_ok(abs($c['profit'] - 30000) < 0.01, 'profit = bill − cost (30,000), not zero');

    // The flat "Est. cost / person / month" the user sees wins over the build-up
    // (what is shown on the form is what gets saved).
    $c2 = req_commercials(['quantity' => 1, 'billing_rate' => 100000, 'rate_basis' => 'MONTHLY',
        'budgeted_cost' => 50000, 'sourcing_model' => 'OWN_PAYROLL', 'cost_wage' => 60000, 'cost_statutory_pct' => 25]);
    t_ok(abs($c2['monthly_cost'] - 50000) < 0.01, 'a manual flat cost overrides the build-up figure');

    // With no flat cost, the build-up is used (JavaScript-off / server fallback).
    $c3 = req_commercials(['quantity' => 1, 'billing_rate' => 100000, 'rate_basis' => 'MONTHLY',
        'sourcing_model' => 'OWN_PAYROLL', 'cost_wage' => 60000, 'cost_statutory_pct' => 25]);
    t_ok(abs($c3['monthly_cost'] - 75000) < 0.01, 'with no flat cost, the built-up monthly (75,000) is used');

    // Multi-person, multi-month scales both sides correctly.
    $c4 = req_commercials(['quantity' => 3, 'billing_rate' => 100000, 'rate_basis' => 'MONTHLY',
        'duration_months' => 6, 'budgeted_cost' => 70000, 'cost_oneoff' => 5000]);
    t_ok(abs($c4['revenue'] - 1800000) < 0.01, '3 people × 100k × 6 months = 18,00,000 revenue');
    t_ok(abs($c4['cost'] - (3 * 70000 * 6 + 3 * 5000)) < 0.01, 'cost = recurring + one-off across all people');
}

t_section('Playbook fixes — document generation & one-pager (10.3)');

if (!function_exists('doc_tpl_by_code') || !function_exists('doc_render_template')) {
    t_ok(true, 'document studio not present — skipped');
} else {
    doc_tpl_migrate();
    // The standard letters and the new one-pager are all seeded and generatable.
    t_ok(doc_tpl_by_code('OFFER') !== null, 'the offer letter template exists');
    t_ok(doc_tpl_by_code('APPOINTMENT') !== null, 'the appointment letter template exists');
    $one = doc_tpl_by_code('ONE_PAGER');
    t_ok($one !== null, 'the candidate one-pager template exists');

    // The token catalogue advertises the new candidate-profile tokens.
    $help = doc_tokens_help();
    t_ok(isset($help['experience']) && isset($help['skills']) && isset($help['expected_rate']),
        'one-pager profile tokens are documented');

    // Render the one-pager for a real candidate row — the merge fills the name and
    // does not fatal on absent optional columns.
    try {
        db()->prepare("INSERT INTO candidates (first_name,last_name,designation,mobile,email,cand_code,experience_years,cv_keywords,stage,created_at)
                       VALUES ('Asha','Rao','Recruiter','9800000000','asha@example.com','CV-T01','6','sourcing, screening','APPLIED',?)")
            ->execute([function_exists('now_iso') ? now_iso() : date('c')]);
        $cid = (int) db()->lastInsertId();
        $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
        $r = doc_render_template($one, $cand);
        t_ok(strpos($r['html'], 'Asha Rao') !== false, 'the one-pager renders the candidate name from their data');
        t_ok(strpos($r['html'], '6 years') !== false, 'experience is merged into the one-pager');
        db()->prepare("DELETE FROM candidates WHERE id=?")->execute([$cid]);
    } catch (Throwable $e) {
        t_ok(false, 'one-pager render raised: ' . $e->getMessage());
    }
}
