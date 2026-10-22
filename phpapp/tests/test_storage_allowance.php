<?php
// ============================================================================
//  Storage allowance for every hosted company — owner, 22 Oct 2026:
//  "integrate storage usage and limits on the paid users also".
//
//   allowance = base GB + GB per login × logins + bought packs × pack GB
//               + extra GB the platform owner granted
//   (trial: the trial MB). Nothing set = no limit. At the limit only NEW FILES
//   are refused; owners are told at 80 / 95 / 100 %.
// ============================================================================

t_section('Storage allowance — every hosted company, trial and paid');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$prevSess = $_SESSION['saas_tenant'] ?? null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");
selfserve_migrate();

t_eq(saas_storage_limit_bytes(), 0, 'the platform owner\'s own install (and a self-hosted one) is never storage-limited');
setting_set('saas_storage_gb_base', '2');      // the platform owner's number at the moment the company is created

$res = saas_company_create(['company' => 'Storage Co ' . getmypid(), 'owner_email' => 'store+' . getmypid() . '@storage.test',
    'owner_name' => 'S Owner', 'plan' => 'PRO', 'source' => 'console']);
t_ok($res['ok'], 'a console-managed (paid) test company is created');
$key = $res['key'];
saas_enter_tenant($key);
saas_tenant_ensure_ready($key);
$GB = 1073741824;
t_eq((string) setting_get('saas_storage_gb_base', ''), '2', 'a new company starts with the storage allowance in force when it was created');
foreach (['saas_storage_gb_base', 'saas_storage_gb_per_login', 'saas_storage_pack_gb', 'saas_storage_packs', 'saas_storage_extra_gb'] as $k) setting_set($k, '');
setting_set('saas_seat_limit', '4');

t_eq(saas_storage_limit_bytes(), 0, 'nothing set by the platform owner: no limit (no number is invented)');
setting_set('saas_storage_cache', time() . '|' . (50 * $GB));
t_ok(!saas_storage_full() && saas_storage_pct() === null, 'with no limit, storage is never full');

setting_set('saas_storage_gb_base', '1'); setting_set('saas_storage_gb_per_login', '0.5');
t_eq(saas_storage_limit_bytes(), 3 * $GB, 'base 1 GB + 0.5 GB × 4 logins = 3 GB');
setting_set('saas_storage_pack_gb', '5'); setting_set('saas_storage_packs', '2');
t_eq(saas_storage_limit_bytes(), 13 * $GB, '+ 2 bought packs of 5 GB = 13 GB');
setting_set('saas_storage_extra_gb', '1');
t_eq(saas_storage_limit_bytes(), 14 * $GB, '+ 1 GB granted by the platform owner = 14 GB');

setting_set('saas_storage_cache', time() . '|' . (int) (10 * $GB));
t_eq(saas_storage_pct(), 71, '10 of 14 GB is 71% used');
t_ok(!saas_storage_full(), 'not full: files are accepted');
setting_set('saas_storage_cache', time() . '|' . (int) (12 * $GB));
$b = saas_storage_banner();
t_ok($b === null || $b['tone'] !== 'error', 'at 85% there is no refusal');
setting_set('saas_storage_cache', time() . '|' . (14 * $GB));
t_ok(saas_storage_full(), 'at 100% storage is full — new files are refused');
$b = saas_storage_banner();
t_ok($b && $b['tone'] === 'error' && strpos($b['text'], 'Storage is full') !== false, 'every screen says storage is full and that stored files are safe');
t_ok(!saas_blocks_write('lead-save'), 'a full store does NOT block saving records (that is only for view-only)');

// The trial keeps its own (MB) limit.
setting_set('saas_billing_mode', 'self');
setting_set('saas_trial_until', date('Y-m-d', strtotime('+5 days')));
setting_set('billing_paid_until', '');
setting_set('saas_storage_limit_mb', '100');
t_eq(saas_storage_limit_bytes(), 100 * 1048576, 'during a free trial the trial limit applies');

// Where the space goes.
$bd = saas_storage_breakdown();
t_ok(array_key_exists('Records and everything else', $bd), 'the breakdown always shows the rest of the data');
db()->prepare("INSERT INTO lead_files (lead_id,kind,file_name,mime,file_data,uploaded_at) VALUES (1,'DOC','x.pdf','application/pdf',?,?)")
    ->execute([str_repeat('A', 300000), date('c')]);
$bd = saas_storage_breakdown();
t_ok(($bd['Sales documents'] ?? 0) >= 300000, 'a sales attachment is counted under "Sales documents"');

// Extra storage packs in the plan (self-serve company buying its plan).
setting_set('saas_storage_pack_price_month', '500'); setting_set('saas_storage_pack_price_year', '5000');
setting_set('saas_storage_pack_gb', '5');
$mods = array_slice(selfserve_all_modules(), 0, 1);
$q0 = saas_company_quote($mods, 2, 'month', 0);
$q2 = saas_company_quote($mods, 2, 'month', 2);
t_eq($q2['total'] - $q0['total'], 1000, 'two extra storage packs add 2 × the pack price to the plan');
t_ok((bool) array_filter($q2['lines'], fn($l) => strpos($l['label'], 'GB extra storage') !== false), 'and show as their own line');
setting_set('saas_storage_pack_price_month', '0');
t_eq(saas_company_quote($mods, 2, 'month', 2)['total'], $q0['total'], 'with no pack price set, packs are not sold (nothing invented)');
setting_set('saas_storage_pack_price_month', '500');
$q = saas_plan_change_quote($mods, 2, 'month', null, 2);
saas_plan_change_apply($q, 'p', 'o');
t_eq((int) setting_get('saas_storage_packs', 0), 2, 'buying a plan with 2 packs records 2 packs');
t_eq(saas_plan_change_quote($mods, 2, 'month', null, 2)['type'], 'RENEW', 'the same plan with the same packs is a renewal');
t_eq(saas_plan_change_quote($mods, 2, 'month', null, 3)['type'], 'UPGRADE', 'one more pack is an upgrade, starting today with credit');
t_eq(saas_plan_change_quote($mods, 2, 'month', null, 1)['type'], 'DOWNGRADE', 'one pack fewer is a downgrade, at the end of the period');

saas_leave_tenant();
if (function_exists('db_reset')) db_reset();
require $root . '/config.php';

// ---- The nightly run tells the owner once per warning level ----------------------
$mails = [];
$mailer = function ($to, $s, $b) use (&$mails) { $mails[] = [$to, $s]; return true; };
$reader = function ($used, $lim) { return function () use ($used, $lim) {
    return ['ok' => true, 'last_seen' => date('c'), 'paid_until' => '', 'trial_until' => '', 'modules' => [], 'seat_limit' => 0,
            'storage_used' => $used, 'storage_limit' => $lim]; }; };
$mine = function () use (&$mails) { return array_values(array_filter($mails, fn($m) => $m[0] === 'store+' . getmypid() . '@storage.test')); };
$GB = 1073741824;
selfserve_sweep(['reader' => $reader((int) (5 * $GB), 10 * $GB), 'mailer' => $mailer]);
t_eq(count($mine()), 0, 'at 50% nobody is e-mailed');
t_eq((string) saas_tenant_get($key)['storage_used'], (string) (int) (5 * $GB), 'the console records how much each company stores');
selfserve_sweep(['reader' => $reader((int) (8.5 * $GB), 10 * $GB), 'mailer' => $mailer]);
t_eq(count($mine()), 1, 'at 85% the owner is e-mailed once');
selfserve_sweep(['reader' => $reader((int) (8.7 * $GB), 10 * $GB), 'mailer' => $mailer]);
t_eq(count($mine()), 1, 'and not again the next night at the same level');
selfserve_sweep(['reader' => $reader((int) (9.6 * $GB), 10 * $GB), 'mailer' => $mailer]);
t_eq(count($mine()), 2, 'crossing 95% tells them again');
selfserve_sweep(['reader' => $reader(10 * $GB, 10 * $GB), 'mailer' => $mailer]);
$m = $mine();
t_ok(count($m) === 3 && strpos($m[2][1], 'Storage is full') !== false, 'reaching 100% tells them storage is full');
selfserve_sweep(['reader' => $reader((int) (4 * $GB), 10 * $GB), 'mailer' => $mailer]);
t_eq((int) saas_tenant_get($key)['storage_notice'], 0, 'after space is freed (or added), a later rise is told again');
selfserve_sweep(['reader' => $reader((int) (9 * $GB), 10 * $GB), 'mailer' => $mailer, 'dry' => true]);
t_eq(count($mine()), 3, '"Check now" on the console sends nothing');

saas_company_purge($key, 'test clean-up');
setting_set('saas_storage_gb_base', '');
if ($prevSess === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prevSess; }
if (function_exists('db_reset')) db_reset();
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
require $root . '/config.php';
