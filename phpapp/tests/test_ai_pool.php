<?php
// ============================================================================
//  Platform-provided AI + monthly cap. The platform supplies AI centrally (a
//  server secret), a hosted workspace draws on it with NO setup, and every
//  workspace is hard-capped per month so the platform can never be over-charged.
//  A workspace's OWN key bypasses the pool and the cap (unlimited).
// ============================================================================

t_section('Platform AI pool — availability, metering and the monthly cap');

if (!function_exists('platform_ai_config') || !function_exists('ai_active')) { t_ok(true, 'platform AI not present — skipped'); return; }

// The platform key is a server secret (env / config.local.php), never a DB value.
putenv('PLATFORM_AI_PROVIDER=openai');
putenv('PLATFORM_AI_KEY=sk-platform-test');
$pc = platform_ai_config();
t_ok($pc && $pc['provider'] === 'openai' && $pc['key'] === 'sk-platform-test', 'the platform key is read from the server environment');
t_ok(($pc['model'] ?? '') !== '', 'a default model is chosen when none is set');

$saveTenant = $GLOBALS['__tenant'] ?? null;
$mk = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.com']; };
$saveCfg = (string) setting_get('ai_config', '');
$saveCap = (string) setting_get('ai_monthly_cap', '');
$saveUse = (string) setting_get(ai_usage_setting(), '');
setting_set('ai_config', ''); $mk();          // no own key on this workspace
setting_set('ai_monthly_cap', '3'); $mk();     // a small cap for the test
setting_set(ai_usage_setting(), '0'); $mk();

// --- On the CONTROL install the pool never applies (owner uses own keys, no cap). ---
$GLOBALS['__tenant'] = ['key' => '', 'company' => '', 'error' => '', 'saas' => false, 'base' => ''];
t_ok(!ai_pool_applies(), 'the platform pool does NOT apply to the control / owner install');

// --- On a hosted workspace with no own key, the pool is used. ---
$mk();
t_ok(ai_pool_applies(), 'the pool applies to a hosted workspace when a platform key is set');
t_ok(ai_enabled(), 'AI reads as available on the workspace with no setup (the wall is gone)');
$act = ai_active();
t_ok($act && ($act['source'] ?? '') === 'pool', 'a feature call is routed to the platform pool');
t_ok(($act['key'] ?? '') === 'sk-platform-test', 'the pool result carries the server key (not stored per-tenant)');

// --- The monthly cap: usage meters, and a call over the cap is refused. ---
t_eq(ai_monthly_cap(), 3, 'the workspace cap is read from its setting');
ai_usage_bump(1); $mk(); ai_usage_bump(1); $mk();
t_eq(ai_usage_month(), 2, 'each AI action increments the monthly meter');
t_eq(ai_pool_remaining(), 1, 'the remaining allowance is reported');
t_ok(!ai_pool_over(), 'under the cap, the pool is still open');
ai_usage_bump(1); $mk();
t_ok(ai_pool_over(), 'at the cap, the pool is closed for the month');
t_ok(ai_active() === null, 'over the cap, no provider is returned — the call is blocked before reaching the API');

// --- A workspace's OWN key wins and is not capped. ---
setting_set('ai_config', json_encode(['openai' => ['enabled' => 1, 'key' => 'sk-own', 'active' => ['gpt-4o'], 'models' => ['gpt-4o']]])); $mk();
$own = ai_active();
t_ok($own && ($own['source'] ?? '') === 'own', 'a workspace with its own key uses it, even when the pool cap is spent');
t_ok(!isset($own['key']), 'an own-key result does not carry a pool key (read from its own config, unlimited)');

// Restore.
setting_set('ai_config', $saveCfg);
setting_set('ai_monthly_cap', $saveCap);
setting_set(ai_usage_setting(), $saveUse);
putenv('PLATFORM_AI_PROVIDER'); putenv('PLATFORM_AI_KEY');
if ($saveTenant === null) { unset($GLOBALS['__tenant']); } else { $GLOBALS['__tenant'] = $saveTenant; }
t_ok(true, 'AI pool test state restored');
