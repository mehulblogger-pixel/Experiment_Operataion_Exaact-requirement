<?php
// ============================================================================
//  A new company starts EMPTY — no master data flows into it.
//
//  A fresh client company must not inherit EXAACT's demo/starter data: no
//  offices, no expense heads / travel modes, no demo clients & vendors, no
//  starter master lists. It gets a full schema and its own owner admin, and
//  it builds its own masters. This boots a company in a clean child process
//  (with the company chosen, so current_tenant() is set exactly as in the live
//  provisioning path) and checks the row counts.
// ============================================================================

t_section('SaaS isolation — a new company database starts empty (no master data)');

$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$canExec  = function_exists('exec') && !in_array('exec', $disabled, true);

if (!$canExec) {
    t_ok(true, 'exec() unavailable — clean-company child-process check skipped');
    return;
}

$root = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$tf = sys_get_temp_dir() . '/mgh_clean_' . getmypid() . '.sqlite';
@unlink($tf);
file_put_contents($regFile, "<?php return " . var_export([
    'base_domain' => 'ops.example.com', 'aliases' => [],
    'tenants' => ['cleanco' => ['company' => 'Clean Co', 'status' => 'active', 'sqlite' => $tf]],
], true) . ";\n");

$runner = sys_get_temp_dir() . '/mgh_clean_runner_' . getmypid() . '.php';
$code = '<?php error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);'
    . '$root=getenv("APP_ROOT");'
    . '$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="clean";$_SERVER["REQUEST_URI"]="/";'
    . '$_SERVER["HTTP_HOST"]="ops.example.com";'                 // the base domain
    . 'if(!isset($_SESSION))$_SESSION=[];$_SESSION["saas_tenant"]="cleanco";'   // company chosen → current_tenant() is set
    . '$idx=file_get_contents($root."/index.php");'
    . 'preg_match_all("#require __DIR__ \\\\. \x27(/lib/[a-z0-9_]+\\\\.php)\x27;#i",$idx,$mm);'
    . 'foreach($mm[1] as $rel){require_once $root.$rel;}'
    . 'db();'                                                    // resolves to the company (sqlite)
    . 'boot();'                                                  // builds schema; demo/starter seeds gated off for a company
    . '$c=function($t){try{return (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn();}catch(Throwable $e){return -1;}};'
    . 'echo json_encode(["tenant"=>(function_exists("current_tenant")?current_tenant():"?"),'
    . '"offices"=>$c("offices"),"partners"=>$c("business_partners"),"lookups"=>$c("lookup_types"),'
    . '"expense_heads"=>$c("expense_heads"),"admins"=>(int)db()->query("SELECT COUNT(*) FROM users WHERE is_superuser=1")->fetchColumn()]);';
file_put_contents($runner, $code);

$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$out = []; $rc = 1;
@exec('APP_ROOT=' . escapeshellarg($root) . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' 2>&1', $out, $rc);
@unlink($runner);
$res = json_decode((string) end($out), true);

t_ok(is_array($res), 'the company booted in a clean child process');
if (is_array($res)) {
    t_eq((string) $res['tenant'], 'cleanco', 'the boot ran in the company context (current_tenant is set)');
    t_eq((int) $res['offices'], 0, 'a new company has NO offices (EXAACT branches do not flow in)');
    t_eq((int) $res['partners'], 0, 'a new company has NO clients or vendors (no demo partners)');
    t_eq((int) $res['lookups'], 0, 'a new company has NO starter master lists');
    t_eq((int) $res['expense_heads'], 0, 'a new company has NO expense heads seeded');
    t_ok((int) $res['admins'] >= 1, 'but the company DOES get its own admin + full schema');
}

@unlink($tf);
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
