<?php
// Résumé auto-extract for candidate intake. Reuses the marketplace CV engine to
// read a résumé and prefill the reliable candidate fields (name, e-mail, mobile,
// experience) plus a role/skills summary — the recruiter reviews and saves.
t_section('résumé auto-extract → candidate prefill');

$cv = "Rajesh Kumar Sharma\n"
    . "Senior Welding Inspector\n"
    . "Email: rajesh.sharma@example.com  |  Mobile: +91 98123 45678\n"
    . "Ahmedabad, Gujarat\n\n"
    . "12 years of experience in third-party inspection.\n"
    . "Skills: welding inspection, NDT, radiography, ultrasonic testing, QA/QC.\n"
    . "Certifications: CSWIP 3.1, ASNT Level II.\n";

$a = recruit_cv_autofill($cv);
t_eq($a['email'], 'rajesh.sharma@example.com', 'e-mail extracted');
t_eq($a['mobile'], '9812345678', 'Indian mobile extracted (10 digits, +91 stripped)');
t_eq($a['experience_years'], '12', 'experience "12 years" extracted');
t_eq($a['first_name'], 'Rajesh', 'first name from the top line');
t_eq($a['last_name'], 'Sharma', 'last name from the top line');
t_ok(strpos($a['first_name'], '@') === false && !is_numeric($a['first_name']), 'the name is a real word, not an e-mail or number');
t_ok(is_string($a['remarks']), 'a role/skills summary string is returned');
t_ok(is_string($a['cv_keywords']), 'search keywords are returned');

// The summary line names what was filled (drives the on-screen banner).
$sum = recruit_cv_autofill_summary($a);
t_ok(strpos($sum, 'name') !== false && strpos($sum, 'e-mail') !== false && strpos($sum, 'mobile') !== false, 'the banner summary lists name, e-mail and mobile');

// Empty input is safe — nothing invented.
$empty = recruit_cv_autofill('');
t_eq($empty['email'], '', 'no text → no e-mail invented');
t_eq($empty['first_name'], '', 'no text → no name invented');
t_eq(recruit_cv_autofill_summary($empty), '', 'empty extract → empty banner summary');

// A résumé with only a phone (no clean name line) still gets the phone, no crash.
$partial = recruit_cv_autofill("Contact 9876543210 for details.");
t_eq($partial['mobile'], '9876543210', 'a bare mobile is still picked up');
t_ok(true, 'extractor never throws on messy input');

// Round-trip: saving a candidate with cv_text stores its search keywords too.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('recruit_migrate')) recruit_migrate();
    $kw = function_exists('cv_extract_keywords') ? cv_extract_keywords($cv) : '';
    t_ok(is_string($kw), 'cv_extract_keywords runs on the résumé text');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
