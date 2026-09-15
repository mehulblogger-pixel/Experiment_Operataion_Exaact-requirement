<?php
// ============================================================================
//  SIGNING IN ON THE ONE SHARED ADDRESS — finding a person's company
//
//  Reported from a live installation: "the created user with its username that
//  is email id and password is unable to login." It was not the password.
//
//  On the single shared address the sign-in has to work out WHICH company an
//  e-mail belongs to before it can check the password, and it does that through
//  a directory (saas_logins) on the control database. That directory is written
//  in exactly one place — when a company is created, for its OWNER. Everybody the
//  owner adds afterwards was never in it, so the lookup found nothing, the live
//  database stayed on the control store where they do not exist, and they were
//  told their password was wrong.
//
//  saas_login_discover() repairs that by asking the companies themselves on a
//  MISS, then remembering the answer. These tests hold the behaviour that makes
//  it safe as well as the behaviour that makes it work.
// ============================================================================

t_section('single-address sign-in — finding a person\'s company');

t_ok(function_exists('saas_login_discover'), 'the discovery step exists');
t_ok(function_exists('saas_login_lookup'),   'and the fast directory lookup it backs up');

// It must be wired into the sign-in, and ONLY as a fallback — a person already
// in the directory must never pay for a search.
$idx = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../index.php'));
$p = strpos($idx, 'saas_login_lookup($loginId)');
t_ok($p !== false, 'the sign-in still starts with the fast lookup');
$after = substr($idx, $p, 400);
t_ok(strpos($after, 'saas_login_discover') !== false,
     'and falls back to discovery when that finds nothing');
t_ok(preg_match('/\$tk === \'\'\s*&&.*saas_login_discover/', $after) === 1,
     'the fallback runs ONLY on a miss, never on every sign-in');

// ---- the safety rules, read from the code that enforces them --------------
$src = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/saas_tenants.php'));
$fn  = substr($src, strpos($src, 'function saas_login_discover'));
$fn  = substr($fn, 0, strpos($fn, "\n}\n") + 3);

t_ok(strpos($fn, "current_tenant() !== ''") !== false,
     'it refuses to run from inside a company — the directory is control-side only');
t_ok(strpos($fn, 'suspended') !== false,
     'a suspended company is skipped, not searched');
t_ok(substr_count($fn, 'saas_leave_tenant()') >= 3,
     'every path leaves the company again — a probe can never strand the request in someone else\'s database');
t_ok(strpos($fn, 'count($hits) !== 1') !== false,
     'one e-mail in two companies is AMBIGUOUS and is refused, never guessed');
t_ok(strpos($fn, 'is_active = 1') !== false,
     'a deactivated person is not routed anywhere');
t_ok(strpos($fn, 'saas_login_index_set') !== false,
     'and a successful search is remembered, so the next sign-in is fast');
t_ok(defined('SAAS_DISCOVER_MAX') && SAAS_DISCOVER_MAX > 0,
     'the work done for one miss is bounded');

// ---- behaviour on this harness --------------------------------------------
// There is one database here and no company registry, so discovery must answer
// "I do not know" rather than throwing or guessing. That is the single-install
// case, and it must stay silent.
$before = $_SESSION['saas_tenant'] ?? null;
t_eq(saas_login_discover(''), '', 'an empty sign-in name discovers nothing');
t_eq(saas_login_discover('nobody@nowhere.invalid'), '',
     'an unknown e-mail discovers nothing — and says nothing about whether it exists');
t_eq($_SESSION['saas_tenant'] ?? null, $before,
     'and the search left the request exactly where it found it');

// With a registry present but no matching person, the answer is still "no".
if (function_exists('saas_tenant_upsert')) {
    saas_tenant_upsert('disco-test-co', ['company' => 'Discovery Test Co', 'status' => 'active']);
    t_eq(saas_login_discover('still-nobody@nowhere.invalid'), '',
         'a registered company that does not hold the person is not offered');
    t_eq($_SESSION['saas_tenant'] ?? null, $before,
         'and the request is still on the control database afterwards');
    try { db()->prepare("DELETE FROM saas_tenants WHERE tenant_key=?")->execute(['disco-test-co']); }
    catch (Throwable $e) {}
}

// The directory writer it depends on must still behave.
if (function_exists('saas_login_index_set')) {
    t_ok(saas_login_index_set('', 'x') === false, 'an empty e-mail is never indexed');
    t_ok(saas_login_index_set('a@b.test', '') === false, 'and neither is an empty company');
}
