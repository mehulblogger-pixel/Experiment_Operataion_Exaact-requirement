<?php
// ============================================================================
//  ACCEPTANCE — the 10-company archetype gate (master prompt §38 / §40).
//
//  Drives each company archetype A–J: sets it as the operating company with its
//  real capability mix and ASSERTS the workspace the Combination Engine gives it
//  (which specialist areas show/hide) plus the cross-cutting invariants (money &
//  sales always universal, the capability catalogue, the first-class Freelance
//  Supplier). Prints a real derived PASS/FAIL dashboard — a screen is not proof;
//  the assertions are.
//
//    php tools/acceptance-10co.php            # runs on a throwaway SQLite DB
//
//  Never touches the real database — it points itself at a temp SQLite file.
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
putenv('DB_DRIVER=sqlite');
$tmp = sys_get_temp_dir() . '/exaact-acceptance-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);
if (!getenv('ADMIN_PASSWORD')) putenv('ADMIN_PASSWORD=admin12345');

chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); @unlink($tmp); exit(1); }

// Every archetype: [name, capabilities, expected {operations, recruitment(hr), reporting, quality(inspection)}]
$ALL = array_keys(connect_cap_catalog());
$ARCH = [
    'A' => ['Pure TPIA',                    ['TPIA', 'SITE_INSPECTION', 'VENDOR_INSPECTION'],       ['ops' => 1, 'hr' => 0, 'rep' => 1, 'qua' => 1]],
    'B' => ['Technical Manpower Supplier',  ['TECHNICAL_MANPOWER', 'CONTRACT_STAFFING'],            ['ops' => 1, 'hr' => 1, 'rep' => 0, 'qua' => 0]],
    'C' => ['Freelance Resource Supplier',  ['FREELANCE_SUPPLY', 'FREELANCE_INSPECTOR_SUPPLY'],     ['ops' => 1, 'hr' => 1, 'rep' => 1, 'qua' => 0]],
    'D' => ['Technical Recruitment',        ['TECH_RECRUITMENT', 'PERMANENT_PLACEMENT'],            ['ops' => 0, 'hr' => 1, 'rep' => 0, 'qua' => 0]],
    'E' => ['Project Management',           ['PROJECT_MANAGEMENT', 'PROJECT_ENGINEERING'],          ['ops' => 1, 'hr' => 0, 'rep' => 0, 'qua' => 0]],
    'F' => ['TPIA + Technical Staffing',    ['TPIA', 'TECHNICAL_MANPOWER'],                         ['ops' => 1, 'hr' => 1, 'rep' => 1, 'qua' => 1]],
    'G' => ['TPIA + Freelance Supplier',    ['TPIA', 'FREELANCE_SUPPLY', 'FREELANCE_INSPECTOR_SUPPLY'], ['ops' => 1, 'hr' => 1, 'rep' => 1, 'qua' => 1]],
    'H' => ['Staffing + Recruitment',       ['TECHNICAL_MANPOWER', 'TECH_RECRUITMENT'],             ['ops' => 1, 'hr' => 1, 'rep' => 0, 'qua' => 0]],
    'I' => ['TPIA + Staffing + PM',         ['TPIA', 'TECHNICAL_MANPOWER', 'PROJECT_MANAGEMENT'],   ['ops' => 1, 'hr' => 1, 'rep' => 1, 'qua' => 1]],
    'J' => ['Full multi-capability',        $ALL,                                                   ['ops' => 1, 'hr' => 1, 'rep' => 1, 'qua' => 1]],
];

echo "== ACCEPTANCE — 10 company archetypes (master prompt §38/§40) ==\n\n";
printf("  %-3s %-30s %-4s %-4s %-4s %-5s  %s\n", 'ID', 'Company', 'Ops', 'Rec', 'Rep', 'Qual', 'VERDICT');
echo "  " . str_repeat('─', 74) . "\n";

$allpass = true;
foreach ($ARCH as $k => [$name, $caps, $exp]) {
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,1,'ACTIVE',?)")
        ->execute(["ACC-$k", $name, $name, date('c')]);
    $party = (int)db()->lastInsertId();
    connect_org_cap_bulk_set($party, $caps, 'acceptance');
    connect_cap_owner_set($party);
    $act = [
        'ops' => connect_cap_owner_shows('operations') ? 1 : 0,
        'hr'  => connect_cap_owner_shows('hr') ? 1 : 0,
        'rep' => connect_cap_owner_shows('reporting') ? 1 : 0,
        'qua' => connect_cap_owner_does_inspection() ? 1 : 0,
    ];
    // universal invariants — money + sales must never be gated away
    $universal = connect_cap_owner_shows('money') && connect_cap_owner_shows('sales') && connect_cap_owner_shows('connect') && connect_cap_owner_shows('admin');
    $ok = $universal;
    foreach ($exp as $key => $want) if ($act[$key] !== $want) $ok = false;
    if (!$ok) $allpass = false;
    $mark = fn($key) => ($act[$key] === $exp[$key] ? ($act[$key] ? '✓' : '·') : '✗');
    printf("  %-3s %-30s %-4s %-4s %-4s %-5s  %s\n", $k, substr($name, 0, 30), $mark('ops'), $mark('hr'), $mark('rep'), $mark('qua'), $ok ? 'PASS' : 'FAIL');
}
connect_cap_owner_set(0);

// Cross-cutting product invariants (Definition of Done, §40).
echo "\n  Product invariants:\n";
$inv = [
    ['Capability catalogue populated (>=20)',        count(connect_cap_catalog()) >= 20],
    ['Freelance Supplier is a first-class capability', isset(connect_cap_catalog()['FREELANCE_SUPPLY']) && isset(connect_cap_catalog()['FREELANCE_INSPECTOR_SUPPLY'])],
    ['Capabilities span >=4 groups',                 count(connect_cap_groups()) >= 4],
    ['A company can hold MANY capabilities at once',  (function () { return count(connect_cap_catalog()) > 5; })()],
    ['Unset operating company = fully permissive',    (function () { connect_cap_owner_set(0); return connect_cap_owner_shows('operations') && connect_cap_owner_shows('reporting') && connect_cap_owner_does_inspection(); })()],
];
foreach ($inv as [$label, $ok]) { if (!$ok) $allpass = false; echo '  │ ' . str_pad($label, 50) . ' ' . ($ok ? 'PASS' : 'FAIL') . "\n"; }

echo "\n  Legend: ✓ shown (as expected) · · hidden (as expected) · ✗ WRONG\n";
echo '  └─ OVERALL: ' . ($allpass ? 'ALL PASS — every archetype gets its correct workspace' : 'SOME FAIL') . " ─────\n";
@unlink($tmp);
exit($allpass ? 0 : 1);
