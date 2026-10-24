<?php
// ============================================================================
//  Super Admin's own address (owner, 22 Oct 2026: "Super admin shall have
//  complete different login"). The platform owner gives Super Admin its own web
//  address and locks it there; the lock only engages from that address; an
//  emergency file undoes it; company names can never take the address.
// ============================================================================

t_section('Super Admin address — own address, lock, emergency unlock, reserved names');

$root = dirname(__DIR__);
$keepHost = $_SERVER['HTTP_HOST'] ?? null;
$setHost = function ($h) { if ($h === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $h; };
$unlockFile = rtrim((string) tenant_safe_data_dir()[0], '/') . '/superadmin-unlock';
@unlink($unlockFile);
putenv('OPS_SUPERADMIN_ANY_HOST');

t_as_admin();
$setHost(null);
t_ok(superadmin_can(), 'the platform owner is Super Admin on the main site (nightly job and tests: no address)');

// ---- single-company install: kept in the main site's settings --------------------
t_eq(superadmin_cfg_store(), 'settings', 'with no cloud registry the address is kept in the main site\'s settings');
t_eq(superadmin_host_save('https://Admin.Example.test/'), '', 'an address typed with https:// and a slash is cleaned and saved');
t_eq(superadmin_host(), 'admin.example.test', 'and stored lower-case, without https:// or slash');
t_ok(superadmin_host_save('not an address') !== '', 'something that is not a web address is refused, in plain words');
t_eq(superadmin_host(), 'admin.example.test', 'and the saved address is unchanged');

$setHost('ops.example.test');
t_ok(superadmin_can(), 'saved but not locked: Super Admin still opens on the main address');
$err = superadmin_lock_set(true);
t_ok($err !== '' && strpos($err, 'https://admin.example.test/platform-setup') !== false,
    'locking from the main address is refused, and says to lock it from the new address');
t_ok(!superadmin_locked(), 'so it is not locked');

$setHost('admin.example.test:443');
t_ok(on_superadmin_host(), 'a request at the new address (any port) is recognised');
t_eq(superadmin_lock_set(true), '', 'locking FROM the new address works');
t_ok(superadmin_locked(), 'it is locked');
t_ok(superadmin_can() && can_manage_tenants(), 'at the Super Admin address the owner has everything');
t_ok(superadmin_host_save('other.example.test') !== '', 'while locked the address cannot be changed (unlock first)');

$setHost('ops.example.test');
t_ok(!superadmin_can(), 'locked: on the main address Super Admin is refused');
t_ok(!can_manage_tenants(), 'and so is managing company workspaces');
t_ok(in_array('platform-setup', SUPERADMIN_ROUTES, true) && in_array('companies', SUPERADMIN_ROUTES, true),
    'the owner screens are sent on to the Super Admin address');
t_ok(strpos(superadmin_wrong_door_msg(), 'only for the platform owner') !== false,
    'a company user at the Super Admin address is told plainly where to sign in instead');

// Emergency unlock: an empty file in the protected data folder…
@file_put_contents($unlockFile, '');
t_ok(superadmin_unlock_override() && superadmin_can(), 'emergency: an empty "superadmin-unlock" file lets Super Admin open on the main address again');
@unlink($unlockFile);
t_ok(!superadmin_can(), 'deleting the file puts the lock back');
// …or the server setting.
putenv('OPS_SUPERADMIN_ANY_HOST=1');
t_ok(superadmin_can(), 'or the server setting OPS_SUPERADMIN_ANY_HOST=1');
putenv('OPS_SUPERADMIN_ANY_HOST');

$setHost('admin.example.test');
t_eq(superadmin_lock_set(false), '', 'unlock');
$setHost('ops.example.test');
t_ok(superadmin_can(), 'unlocked: Super Admin opens on the main address again');
$setHost(null);
t_eq(superadmin_host_save(''), '', 'the address can be removed');
t_ok(superadmin_host() === '' && !superadmin_locked(), 'and with no address nothing is locked');

// ---- cloud: kept in the workspace registry, listed as a main-site address --------
$regFile = $root . '/tenants.php'; $hadReg = is_file($regFile); $regBak = $hadReg ? file_get_contents($regFile) : null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.test', 'aliases' => ['old.example.test'], 'tenants' => []], true) . ";\n");
if (function_exists('opcache_invalidate')) @opcache_invalidate($regFile, true);
try {
    t_eq(superadmin_cfg_store(), 'registry', 'in cloud mode the address lives in the workspace registry (read the same in every database)');
    t_eq(superadmin_host_suggest(), 'ops-admin.example.test', 'the main address is a sub-domain, so the suggestion is a one-level sibling (ops-admin.…), not admin.ops.… which many hosts never create');
    t_ok(superadmin_host_save('ops.example.test') !== '', 'the address every company uses cannot be the Super Admin address');
    t_eq(superadmin_host_save('admin.ops.example.test'), '', 'admin.ops.example.test is saved');
    $reg = tenant_registry();
    t_ok(in_array('admin.ops.example.test', $reg['aliases'], true) && in_array('old.example.test', $reg['aliases'], true),
        'it opens the platform\'s own site, never a company: listed among the main site\'s own addresses (others kept)');
    t_eq(superadmin_host(), 'admin.ops.example.test', 'and read back from the registry');
    t_eq(superadmin_host_save('owner.ops.example.test'), '', 'changing the address');
    $reg = tenant_registry();
    t_ok(!in_array('admin.ops.example.test', $reg['aliases'], true) && in_array('owner.ops.example.test', $reg['aliases'], true),
        'replaces the old address in the list');
    t_ok(in_array('owner', platform_reserved_keys(), true) && in_array('admin', platform_reserved_keys(), true),
        'the address\'s name (and admin, www, mail…) is reserved');
    $r = saas_company_create(['company' => 'Owner', 'owner_email' => 'resv+' . getmypid() . '@mail.test', 'plan' => 'STARTER', 'source' => 'console', 'key' => 'owner']);
    t_ok(!$r['ok'], 'a company cannot be given the Super Admin address\'s name');
    $r2 = saas_company_create(['company' => 'Admin', 'owner_email' => 'resv2+' . getmypid() . '@mail.test', 'plan' => 'STARTER', 'source' => 'console']);
    t_ok($r2['ok'] && $r2['key'] !== 'admin', 'a company called "Admin" gets a different workspace name (' . ($r2['key'] ?? '?') . ')');
    if (!empty($r2['ok'])) saas_company_purge($r2['key'], 'test clean-up');
    $keys = array_column(platform_checklist(), 'key');
    t_ok(in_array('admin', $keys, true), 'the go-live checklist has the Super Admin address step');
    superadmin_host_save('');
    t_ok(!in_array('owner.ops.example.test', tenant_registry()['aliases'], true), 'removing the address takes it off the list');
} finally {
    if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
    if (function_exists('opcache_invalidate')) @opcache_invalidate($regFile, true);
    @unlink($unlockFile);
    $setHost($keepHost);
    t_as_nobody();
}
