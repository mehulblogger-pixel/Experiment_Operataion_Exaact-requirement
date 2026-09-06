<?php
// Auto job-description / public posting generator. Works with no AI key (a
// deterministic template), reads the requisition's structured fields, flags
// what's missing, and honours the admin's configurable boilerplate. Additive.
t_section('auto job-description generator');

jd_migrate();
if (function_exists('req_migrate')) req_migrate();   // ensure discipline/skills/qualification columns exist
$pdo = db();

// A well-filled requisition.
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,grade,project_site,discipline,skills,qualification,experience_min,responsibilities,status,created_at)
               VALUES ('RQ-JD1','Senior QA Engineer','Quality','M2','Ahmedabad','Quality Assurance','Selenium, API testing','B.E. / B.Tech',5,?, 'OPEN', ?)")
    ->execute(["Design the test strategy\nBuild and maintain automation\nPartner with engineering on releases", date('c')]);
$rq = ops_one("SELECT * FROM requisitions WHERE req_code='RQ-JD1'");

// --- Template generation (no AI in tests → source is 'template') ---
$out = recruit_jd_generate($rq, ['template_only' => true]);
t_eq($out['source'], 'template', 'with no AI it falls back to the template generator');
$t = $out['text'];
t_ok(strpos($t, 'Senior QA Engineer') !== false, 'the posting names the role');
t_ok(strpos($t, 'Ahmedabad') !== false, 'the posting includes the location');
t_ok(strpos($t, 'Key responsibilities') !== false, 'the posting has a responsibilities section');
t_ok(strpos($t, 'Design the test strategy') !== false, 'the captured responsibilities are used as bullets');
t_ok(strpos($t, 'Selenium') !== false, 'the required skills appear');
t_ok(strpos($t, '5+ years') !== false || strpos($t, '5+ year') !== false, 'the minimum experience appears');
t_ok(strpos($t, 'What we offer') !== false, 'the offer/boilerplate section appears');
t_eq(count($out['missing']), 0, 'a well-filled requisition reports nothing missing');

// --- A sparse requisition still produces something, and flags the gaps ---
$pdo->prepare("INSERT INTO requisitions (req_code,designation,status,created_at) VALUES ('RQ-JD2','Store Keeper','OPEN', ?)")->execute([date('c')]);
$rq2 = ops_one("SELECT * FROM requisitions WHERE req_code='RQ-JD2'");
$out2 = recruit_jd_generate($rq2, ['template_only' => true]);
t_ok(strpos($out2['text'], 'Store Keeper') !== false, 'a sparse role still yields a usable draft');
t_ok(in_array('key responsibilities', $out2['missing'], true), 'missing responsibilities is reported');
t_ok(in_array('skills', $out2['missing'], true), 'missing skills is reported');
t_ok(count($out2['missing']) >= 3, 'several gaps are flagged for a sparse requisition');

// --- Configurable boilerplate is honoured ---
jd_config_save(['about' => 'Join {company} as a {role}.', 'offer' => 'Great perks and growth.', 'apply' => 'Send us your CV today.', 'tone' => 'friendly']);
$cfg = jd_config();
t_eq($cfg['tone'], 'friendly', 'the configured tone is saved');
$out3 = recruit_jd_generate($rq, ['template_only' => true]);
t_ok(strpos($out3['text'], 'Great perks and growth.') !== false, 'the configured "what we offer" text is used');
t_ok(strpos($out3['text'], 'Send us your CV today.') !== false, 'the configured how-to-apply footer is used');
$company = function_exists('app_name') ? app_name() : '';
if ($company !== '') t_ok(strpos($out3['text'], $company) !== false, 'the {company} token is expanded in the intro');

// --- responsibilities is persisted on the requisition (feeds the generator) ---
t_ok(in_array('responsibilities', req_extra_fields(), true), 'responsibilities is a saved requisition field');

// Clean up + restore default boilerplate.
if (function_exists('setting_set')) setting_set('jd_config', '');
$pdo->prepare("DELETE FROM requisitions WHERE req_code IN ('RQ-JD1','RQ-JD2')")->execute();
