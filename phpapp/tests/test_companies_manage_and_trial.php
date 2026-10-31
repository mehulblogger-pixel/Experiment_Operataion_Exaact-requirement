<?php
// Owner, 31 Oct 2026 (test book ONB-048): "Manage button … is not working. Also
// to test this whole module we need to expire the trial." Manage did open its
// panel — below the whole list, out of sight. It now jumps to it and marks the
// row; and a self sign-up company's trial end date can be set (yesterday ends it).
// Checked end to end on a local copy; this guards the wiring.
t_section('Companies → Manage opens in view; a trial can be ended or extended');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($v, 'href="/companies?key=<?= $k ?>#manage"') !== false && strpos($v, 'id="manage"') !== false, 'Manage jumps to its panel');
t_ok(strpos($v, 'outline:2px solid var(--brand') !== false, 'the chosen company is marked in the list');
t_ok(strpos($v, 'name="do" value="company_trial"') !== false && strpos($v, "(\$sel['source'] ?? '') === 'self'") !== false,
     'a self sign-up company has a "Free trial ends on" date');
$h = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
$i = strpos($h, "if (\$do === 'company_trial'"); $blk = substr($h, $i, 2800);
t_ok($i !== false && strpos($blk, "\$push = ['saas_trial_until' => \$tu];") !== false && strpos($blk, 'pricing_push_to_tenant($key, $push)') !== false, 'the date is written into the company, which decides trial or view-only');
t_ok(strpos($blk, "!== 'self'") !== false && strpos($blk, 'must be a date written YYYY-MM-DD') !== false, 'only self sign-up companies; a bad date is refused');
t_ok(strpos($h, 'is on this console — it may have been removed') !== false, 'an unknown company in the address says so instead of showing nothing');
t_ok(strpos($v, 'name="trial_storage_mb"') !== false && strpos($blk, "\$push['saas_storage_limit_mb']") !== false,
     "ONB-050: this company's trial storage limit can be set from Manage (a tiny one tries the \"storage is full\" message)");
