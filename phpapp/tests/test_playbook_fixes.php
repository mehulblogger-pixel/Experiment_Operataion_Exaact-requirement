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

t_section('Playbook fixes — interview panel multi-select (10.3)');

if (!function_exists('iv_panel_from_post') || !function_exists('iv_candidate_department')) {
    t_ok(true, 'interview panel helpers not present — skipped');
} else {
    recruit_iv_migrate();
    try {
        $now = function_exists('now_iso') ? now_iso() : date('c');
        db()->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,department) VALUES ('iv_a','Meera','Nair','COORDINATOR',1,'Engineering')")->execute();
        $u1 = (int) db()->lastInsertId();
        db()->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,department) VALUES ('iv_b','Rohit','Sen','BRANCH_MANAGER',1,'Engineering')")->execute();
        $u2 = (int) db()->lastInsertId();
        db()->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,department) VALUES ('iv_c','Sana','Ali','COORDINATOR',1,'Finance')")->execute();
        $u3 = (int) db()->lastInsertId();

        // Panel is built from the picked user ids (names), with free-text appended.
        $panel = iv_panel_from_post(['panel_users' => [$u1, $u2], 'panel_extra' => 'Client-side lead']);
        t_ok(strpos($panel, 'Meera Nair') !== false && strpos($panel, 'Rohit Sen') !== false, 'selected interviewers are stored by name');
        t_ok(strpos($panel, 'Client-side lead') !== false, 'an external panelist typed in the box is included');

        // Legacy free-text still works when no users are picked.
        t_ok(iv_panel_from_post(['panel' => 'Old Panel Text']) === 'Old Panel Text', 'a legacy plain panel field still works');
        t_ok(iv_panel_from_post([]) === '', 'an empty panel is empty, not an error');

        // Department is resolved from the candidate's requisition, to pre-tick the panel.
        db()->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_at) VALUES ('REQ-T1','Engineer','Engineering','OPEN',?)")->execute([$now]);
        $rid = (int) db()->lastInsertId();
        db()->prepare("INSERT INTO candidates (first_name,last_name,designation,requisition_id,stage,created_at) VALUES ('Test','Cand','Engineer',?,'APPLIED',?)")->execute([$rid, $now]);
        $cid2 = (int) db()->lastInsertId();
        $cand2 = ops_one("SELECT * FROM candidates WHERE id=?", [$cid2]);
        t_ok(strtolower(iv_candidate_department($cand2)) === 'engineering', 'the candidate department comes from the requisition (used to pre-select the panel)');

        db()->prepare("DELETE FROM candidates WHERE id=?")->execute([$cid2]);
        db()->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rid]);
        db()->prepare("DELETE FROM users WHERE id IN (?,?,?)")->execute([$u1, $u2, $u3]);
    } catch (Throwable $e) {
        t_ok(false, 'interview panel test raised: ' . $e->getMessage());
    }
}

t_section('Playbook fixes — per-stage capture (10.3)');

if (!function_exists('cand_stage_note_save') || !function_exists('docs_for_stage')) {
    t_ok(true, 'per-stage capture helpers not present — skipped');
} else {
    recruitpipe_migrate(); recruit_iv_migrate();
    try {
        $now = function_exists('now_iso') ? now_iso() : date('c');
        db()->prepare("INSERT INTO candidates (first_name,last_name,designation,stage,created_at) VALUES ('Stage','Test','Engineer','APPLIED',?)")->execute([$now]);
        $sc = (int) db()->lastInsertId();
        $stageId = 4242;   // arbitrary stage id — the capture is keyed by (candidate, stage)

        // Notes are stored per (candidate, stage) and updated in place, not duplicated.
        cand_stage_note_save($sc, $stageId, 'Screened — strong fit, proceed to L1', 'Tester');
        $n = cand_stage_note($sc, $stageId);
        t_ok($n && strpos((string)$n['notes'], 'strong fit') !== false, 'stage notes are saved and read back');
        cand_stage_note_save($sc, $stageId, 'Updated note', 'Tester');
        $cnt = (int) ops_val("SELECT COUNT(*) FROM candidate_stage_data WHERE candidate_id=? AND stage_id=?", [$sc, $stageId]);
        t_ok($cnt === 1, 'saving again updates the same stage row (no duplicate)');

        // A document uploaded with a stage_id is tagged to that stage and listed for it.
        db()->prepare("INSERT INTO candidate_docs (candidate_id,doc_type,file_name,status,pipeline_stage_id,created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$sc, 'Educational certificate', 'degree.pdf', 'UPLOADED', $stageId, $now]);
        $sd = docs_for_stage($sc, $stageId);
        t_ok(count($sd) === 1 && $sd[0]['doc_type'] === 'Educational certificate', 'a document captured against a stage is listed for that stage only');
        t_ok(count(docs_for_stage($sc, 9999)) === 0, 'another stage does not see it');

        db()->prepare("DELETE FROM candidate_docs WHERE candidate_id=?")->execute([$sc]);
        db()->prepare("DELETE FROM candidate_stage_data WHERE candidate_id=?")->execute([$sc]);
        db()->prepare("DELETE FROM candidates WHERE id=?")->execute([$sc]);
    } catch (Throwable $e) {
        t_ok(false, 'per-stage capture test raised: ' . $e->getMessage());
    }
}

t_section('Playbook fixes — per-interviewer scorecards (10.3)');

if (!function_exists('iv_score_save') || !function_exists('iv_score_summary')) {
    t_ok(true, 'per-interviewer scorecard helpers not present — skipped');
} else {
    recruit_iv_migrate();
    try {
        $now = function_exists('now_iso') ? now_iso() : date('c');
        db()->prepare("INSERT INTO interviews (candidate_id,round,mode,panel,result,created_at) VALUES (0,'L1','In person','Meera Nair, Rohit Sen','SCHEDULED',?)")->execute([$now]);
        $iv = (int) db()->lastInsertId();

        // Two panel members score individually.
        iv_score_save(['iv_id' => $iv, 'member' => 'Meera Nair', 'srating' => 4, 'srecommendation' => 'Hire', 'scomments' => 'Solid on fundamentals']);
        iv_score_save(['iv_id' => $iv, 'member' => 'Rohit Sen', 'srating' => 2, 'srecommendation' => 'No hire']);
        $sum = iv_score_summary($iv);
        t_ok($sum['n'] === 2, 'two individual panel-member scores are recorded');
        t_ok(abs($sum['avg'] - 3.0) < 0.01, 'the panel average is computed across members (4 and 2 → 3.0)');

        // Saving the same member again updates their row, not a duplicate.
        iv_score_save(['iv_id' => $iv, 'member' => 'Meera Nair', 'srating' => 5, 'srecommendation' => 'Strong hire']);
        $sum2 = iv_score_summary($iv);
        t_ok($sum2['n'] === 2, 'a second save for the same interviewer updates in place (no duplicate)');
        t_ok(abs($sum2['avg'] - 3.5) < 0.01, 'the average reflects the updated score (5 and 2 → 3.5)');

        // Panel members are parsed from the interview panel for the picker.
        $ivrow = ops_one("SELECT * FROM interviews WHERE id=?", [$iv]);
        $mem = iv_panel_members($ivrow);
        t_ok(in_array('Meera Nair', $mem, true) && in_array('Rohit Sen', $mem, true), 'the panel members are offered for scoring');

        db()->prepare("DELETE FROM interview_scores WHERE interview_id=?")->execute([$iv]);
        db()->prepare("DELETE FROM interviews WHERE id=?")->execute([$iv]);
    } catch (Throwable $e) {
        t_ok(false, 'per-interviewer scorecard test raised: ' . $e->getMessage());
    }
}
