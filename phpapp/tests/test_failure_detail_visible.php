<?php
// Owner, 8 Nov: a sign-in showed "Reference 47AEF95A — quote this ... Sign in
// and open the page again to see the technical detail." The detail WAS saved in
// the failure register, but no screen showed it and the reference was not saved
// with it, so the one code the owner could quote matched nothing.
t_section('the reference on the error page is saved with the failure');
$detail = 'TestFault: something broke at ' . uniqid('', true);
$ref = 'ABCD1234';
$id = failure_record_auto('Test failure for reference lookup', $detail, $ref);
t_ok($id > 0, 'the failure is recorded');
$row = ops_one("SELECT * FROM system_failures WHERE id=?", [(int)$id]);
t_ok($row && strpos((string)$row['summary'], 'reference ABCD1234') !== false,
     'its summary carries the reference the person was shown');
t_eq((string)($row['detail'] ?? ''), $detail, 'and the technical detail is stored in full');
t_eq((int)failure_record_auto('Test failure for reference lookup', $detail, 'ZZZZ9999'), 0,
     'the same fault again within the hour is not recorded twice (the hourly reference stays the same)');
db()->prepare("DELETE FROM system_failures WHERE id=?")->execute([(int)$id]);

t_section('the error page and the register agree on one reference');
$src = file_get_contents(__DIR__ . '/../index.php');
$a = strpos($src, '$ref = strtoupper(substr(md5($detail . date(\'YmdH\')), 0, 8));');
$b = strpos($src, 'failure_record_auto($title, $detail, $ref)');
t_ok($a !== false && $b !== false && $a < $b, 'the reference is worked out first and handed to the recorder');
t_ok(substr_count($src, "md5(\$detail . date('YmdH'))") === 1, 'there is one reference, not a second recomputed one');
t_ok(strpos($src, 'Sign in and open the page again') === false,
     'the page no longer promises a detail that a non-administrator could never see');

t_section('administrators can read the technical detail in Data control');
$v = file_get_contents(__DIR__ . '/../views/ops/data_control.php');
$i = strpos($v, 'Technical detail');
t_ok($i !== false, 'each failure row has a Technical detail box');
$guard = strrpos(substr($v, 0, (int)$i), "is_master() || (function_exists('is_admin_level') && is_admin_level())");
t_ok($guard !== false && $i - $guard < 400, 'shown only to administrators (it names files and SQL)');
t_ok(strpos($v, "e(\$f['detail'])") !== false, 'and the detail is escaped before it is shown');

t_section('the Data control page really shows it — to administrators only');
$probeId = failure_record_auto('Render probe', "PDOException: no such column probe_col\nin /app/index.php:123 " . uniqid('', true), 'REF0RNDR');
$renderDc = function () {
    extract(['ready' => datacontrol_readiness(), 'validations' => sw_all(), 'checks' => integrity_checks(),
             'runs' => integrity_runs(10), 'access' => access_report(), 'failures' => failures_all(),
             'canEdit' => dc_can_edit()]);
    ob_start();
    try { include __DIR__ . '/../views/ops/data_control.php'; } catch (Throwable $e) { echo 'THREW ' . $e->getMessage(); }
    return ob_get_clean();
};
t_as_admin();
$h = $renderDc();
t_as_nobody();
t_ok(strpos($h, 'REF0RNDR') !== false, 'the reference appears in "When it broke"');
t_ok(strpos($h, 'no such column probe_col') !== false, 'an administrator can read the technical detail');
$prev = $_SESSION ?? [];
$_SESSION['uid'] = 0;
if (function_exists('current_user')) current_user(true);
$h2 = $renderDc();
$_SESSION = $prev;
if (function_exists('current_user')) current_user(true);
t_ok(strpos($h2, 'no such column probe_col') === false, 'somebody who is not an administrator does not see it');
db()->prepare("DELETE FROM system_failures WHERE id=?")->execute([(int)$probeId]);
