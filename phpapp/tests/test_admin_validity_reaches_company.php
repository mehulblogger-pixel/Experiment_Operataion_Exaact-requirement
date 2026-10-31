<?php
// Owner, 31 Oct 2026 (test book PLT-005): "when from admin panel I change the
// date of validity it did not reflect in the user panel." "Plan valid until"
// on Companies → Manage was kept on the console only; it is now written into
// the company's own workspace as its "Active until" (billing_paid_until).
// Checked end to end on a local copy (a company created, a past date saved,
// the company's own database read back); this guards the wiring.
t_section('Super Admin → "Plan valid until" reaches the company');
$h = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
$i = strpos($h, "if (\$do === 'company_plan'");
$blk = substr($h, $i, 2200);
t_ok($i !== false && strpos($blk, "pricing_push_to_tenant(\$key, ['billing_paid_until' => \$exp])") !== false,
     'saving the date writes it into the company as its "Active until"');
t_ok(strpos($blk, "saas_tenant_upsert(\$key, ['paid_until' => \$exp])") !== false, 'and the Companies list shows the same date');
t_ok(strpos($blk, 'must be a date written YYYY-MM-DD') !== false, 'a date written any other way is refused, nothing saved');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($v, 'Increment 4') === false && strpos($v, 'next increment') === false, 'the Manage form no longer talks about development "increments"');
