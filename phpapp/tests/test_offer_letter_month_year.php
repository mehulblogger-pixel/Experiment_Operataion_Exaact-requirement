<?php
// Owner, 11 Nov 2026 — "One column of month and yearly total beside it." Salary
// is kept per month; the letters called that figure the "annual" CTC.
t_section('Offer and appointment letters — the month, with the year beside it');
t_as_admin();
recruit_offer_migrate(); doc_tpl_migrate();
$tag = 'OLM' . getmypid();
db()->prepare("INSERT INTO candidates (first_name, last_name, stage, created_at) VALUES (?, 'Probe', 'OFFERED', ?)")->execute([$tag, date('c')]);
$cid = (int) db()->lastInsertId();
$lines = json_encode([
    ['code' => 'BASIC', 'name' => 'Basic', 'section' => 'EARNING', 'amount' => 20000],
    ['code' => 'HRA', 'name' => 'HRA', 'section' => 'EARNING', 'amount' => 8000],
    ['code' => 'PF_ER', 'name' => 'PF (employer)', 'section' => 'EMPLOYER', 'amount' => 2000, 'statutory' => 1],
]);
db()->prepare("INSERT INTO salary_structures (candidate_id, gross_ctc, net_pay, lines_json, created_at) VALUES (?, 30000, 26000, ?, ?)")->execute([$cid, $lines, date('c')]);
db()->prepare("INSERT INTO job_offers (candidate_id, ctc, status, joining_date, created_at) VALUES (?, 30000, 'DRAFT', '2026-12-01', ?)")->execute([$cid, date('c')]);
$oid = (int) db()->lastInsertId();
$cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
$sal = sal_current($cid); $offer = offer_get($oid);
$plain = fn($h) => preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</td>', '</th>'], ' ', $h)), ENT_QUOTES));

$ann = $plain(sal_annexure_html($sal, fn($v) => '₹' . number_format((float) $v, 0)));
t_ok(strpos($ann, 'Per month') !== false && strpos($ann, 'Per year') !== false, 'the salary table has a column for the month and one for the year');
t_ok(strpos($ann, 'Basic ₹20,000 ₹240,000') !== false, 'each line shows its month and its year: Basic 20,000 / 240,000');
t_ok(strpos($ann, 'Total CTC ₹30,000 ₹360,000') !== false, 'Total CTC 30,000 a month, 360,000 a year');

$letter = $plain(offer_letter_html($offer, $cand, $sal));
t_ok(strpos($letter, '30,000 a month (') !== false && strpos($letter, '360,000 a year)') !== false, 'the built-in offer letter says the CTC a month, with the year beside it');
t_ok(strpos($letter, 'annual compensation') === false, '… and no longer calls the monthly figure annual');

$tpl = doc_tpl_by_code('OFFER');
if ($tpl) {
    $old = ['body' => "Dear {name},\nYour total annual compensation (CTC) will be {ctc}, with a net take-home of {net_pay}.\n{salary_table}"];
    $r = $plain(doc_render_template($old, $cand)['html']);
    t_ok(strpos($r, 'total compensation (CTC) will be ₹30,000 a month (₹360,000 a year)') !== false, 'a letter template saved with the old wording reads correctly: ' . substr($r, strpos($r, 'Your'), 90));
    t_ok(strpos($r, 'net take-home of ₹26,000 a month') !== false, 'the take-home is said to be a month');
    t_ok(strpos($r, 'Per year') !== false, 'and its {salary_table} has the year beside the month');
}
$m = doc_token_map($cand);
t_eq([$m['ctc_monthly'], $m['ctc_annual']], ['₹30,000', '₹360,000'], 'templates can also use {ctc_monthly} and {ctc_annual} on their own');

foreach ([["DELETE FROM job_offers WHERE candidate_id=?", [$cid]], ["DELETE FROM salary_structures WHERE candidate_id=?", [$cid]], ["DELETE FROM candidates WHERE id=?", [$cid]]] as [$q, $a])
    try { db()->prepare($q)->execute($a); } catch (Throwable $e) {}
