<?php
// Owner, 31 Oct 2026 (test book PLT-004 step 10): "That workspace key is already
// taken … this error vanished the entry filled up." A refused Add company now
// keeps what was typed (never the password), marks the key box, and names a
// free key. Checked end to end on a local copy; this guards the wiring.
t_section('Companies → Add company keeps what was typed when it is refused');
$res = saas_company_create(['company' => 'Admin Traders', 'key' => 'admin', 'owner_email' => 'ravi@example.com']);
t_ok(!$res['ok'] && strpos($res['error'], 'already taken') !== false && strpos($res['error'], '"admin-2" is free.') !== false,
     'a reserved key is refused, naming a free one: ' . $res['error']);
t_eq($res['field'] ?? '', 'key', 'and says the key box is the one to change');
$h = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
t_ok(strpos($h, "\$keepDraft(\$res['field'] ?? '')") !== false && strpos($h, "'company', 'new_key', 'owner_name', 'owner_email', 'new_plan'") !== false,
     'the handler keeps the typed fields (not the password)');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($v, "name=\"company\" required value=\"<?= \$dv('company') ?>\"") !== false && strpos($v, "unset(\$_SESSION['company_add_draft'])") !== false,
     'the form shows them once, then forgets them');
