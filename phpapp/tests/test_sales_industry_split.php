<?php
// ============================================================================
//  Applying an industry template — the owner's answer of 20 Oct 2026 (D-29,
//  "Yes"): applying one only adds its pipelines; the company's wording and its
//  accreditation rules are set separately, by an administrator, after a
//  confirmation that names what will change.
//  Before: one button built the pipelines AND re-worded the whole application
//  AND switched the accreditation pack — a test of the Manufacturing funnel by a
//  Business Director turned the ISO/IEC 17020 rules off (Sales audit SA-21).
// ============================================================================
t_section('Sales — an industry template adds pipelines; the company\'s wording and accreditation are an administrator\'s');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
// The worker writes settings; this process's settings cache would be stale, so read the table.
$set = fn($k) => (string) ops_val("SELECT svalue FROM settings WHERE skey=?", [$k]);
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('IT Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BD  = $mk('it_bd',  'BUSINESS_DIRECTOR');
$ADM = $mk('it_adm', 'ADMIN');
setting_set('packs_enabled', 'inspection');
setting_set('terms_pack', 'inspection');
setting_set('industry_template', 'inspection');
if (function_exists('opp_migrate')) opp_migrate();
$pipes = fn() => (int) ops_val("SELECT COUNT(*) FROM pipelines");

// ---- a Business Director adds a template's pipelines, and nothing else changes ---------------
$p0 = $pipes();
$r = $drive($BD, 'industry-apply', 'POST', [], ['key' => 'manufacturing']);
t_ok($pipes() > $p0, 'a Business Director adds the Manufacturing pipelines: ' . $said($r));
t_eq($set('packs_enabled'), 'inspection', 'the ISO/IEC 17020 inspection rules stay ON (this is what the old button turned off)');
t_eq($set('terms_pack'), 'inspection', 'the company\'s wording is unchanged');
t_eq($set('industry_template'), 'inspection', 'and the company\'s industry is unchanged');
$r = $drive($BD, 'industry-apply', 'POST', [], ['key' => 'manufacturing', 'do' => 'company', 'confirm' => '1']);
t_eq([$set('packs_enabled'), $set('terms_pack')], ['inspection', 'inspection'], 'a Business Director cannot change the wording or the accreditation rules: ' . $said($r));
$pg = $drive($BD, 'industry', 'GET', ['show' => 'manufacturing'], [], ['Add these pipelines', 'Make this our company']);
t_ok(!empty($pg['has']['Add these pipelines']) && empty($pg['has']['Make this our company']), '…and their screen offers only adding the pipelines');

// ---- an administrator sets the company's industry, after a confirmation that names the change ---
$pg = $drive($ADM, 'industry', 'GET', ['show' => 'manufacturing'], [], ['Make this our company', 'turned OFF', 'ISO/IEC 17020']);
t_ok(!empty($pg['has']['Make this our company']) && !empty($pg['has']['turned OFF']) && !empty($pg['has']['ISO/IEC 17020']),
     'an administrator is told, before confirming, that the ISO/IEC 17020 rules will be turned OFF');
$p1 = $pipes();
$drive($ADM, 'industry-apply', 'POST', [], ['key' => 'manufacturing', 'do' => 'company']);
t_eq($set('packs_enabled'), 'inspection', 'without ticking the confirmation, nothing changes');
$r = $drive($ADM, 'industry-apply', 'POST', [], ['key' => 'manufacturing', 'do' => 'company', 'confirm' => '1']);
t_eq([$set('terms_pack'), $set('packs_enabled'), $set('industry_template')], ['manufacturing', '', 'manufacturing'],
     'confirmed, the wording, the accreditation rules and the company\'s industry change: ' . $said($r));
t_eq($pipes(), $p1, '…and no pipeline is created by it');
$drive($ADM, 'industry-apply', 'POST', [], ['key' => 'inspection', 'do' => 'company', 'confirm' => '1']);
t_eq($set('packs_enabled'), 'inspection', 'setting it back to Inspection turns the ISO/IEC 17020 rules on again');

// ---- the first-time setup wizard still does the whole thing, as an administrator setting up -----
t_as_admin();
$p2 = $pipes();
industry_apply('labs');
t_eq([$set('terms_pack'), $set('packs_enabled')], ['labs', 'labs'], 'the first-time setup still sets wording and accreditation together');
t_ok($pipes() > $p2, '…and builds the pipelines');
industry_set_company('inspection');
setting_set('industry_template', '');
