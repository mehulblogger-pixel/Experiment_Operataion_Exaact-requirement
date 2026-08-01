<?php
// ============================================================================
//  Audit & compliance thread — command line.
//  php tools/trace-audit.php            build it and check
//  php tools/trace-audit.php --force    rebuild
//  php tools/trace-audit.php --remove   take it out again
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
foreach (['db','helpers','ops','lookups','licence','access','terms','compose','crm','pdf','ai','workforce',
          'orgadmin','contracts','security','compliance','costing','reset','partnerimport','mis','dedupe',
          'joblock','schedule','callprofit','bills','competence','preflight','equipment','impartiality',
          'identity','complaints','capa','ncr','audits','datacontrol','trust','portal','tally','receivables',
          'idems','books','booksui','leads','opportunities','crmdash','activity','customer360','packs',
          'seed_demo','trace_seed','trace_audit'] as $m) {
    $f = __DIR__ . "/../lib/$m.php"; if (is_file($f)) require $f;
}
if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, "Cannot reach the database: " . $e->getMessage() . "\n"); exit(1); }

$arg = $argv[1] ?? '';
function asay($s = '') { echo $s . "\n"; }
if ($arg === '--remove') { $r = trace_audit_remove(); asay('Removed ' . (int)($r['deleted'] ?? 0) . ' records of the audit thread.'); exit(0); }

$res = trace_audit_seed($arg === '--force');
if (!empty($res['skipped'])) { asay('Already built. Use --force to rebuild, or --remove first.'); exit(0); }
if (empty($res['ok'])) { fwrite(STDERR, 'Build stopped: ' . ($res['error'] ?? 'unknown') . "\n"); exit(1); }

asay('');
asay('  THE AUDIT & COMPLIANCE THREAD');
asay('  ' . str_repeat('-', 66));
$i = 0;
foreach ($res['steps'] as $s) { printf("  %2d. %-40s %s\n", ++$i, $s['title'], $s['ref']); asay('      ' . $s['detail']); }
asay('');
asay('  DOES THE PACK HOLD TOGETHER — links, and gates that fire?');
asay('  ' . str_repeat('-', 66));
$area = ''; $pass = 0; $fail = 0;
foreach ($res['checks'] as $c) {
    if ($c['area'] !== $area) { $area = $c['area']; asay('  ' . strtoupper($area)); }
    if ($c['ok']) $pass++; else $fail++;
    printf("    [%s] %-52s\n", $c['ok'] ? 'PASS' : 'FAIL', $c['proves']);
    if (!$c['ok']) printf("           expected [%s], found [%s]\n", $c['expected'], $c['actual']);
}
asay('  ' . str_repeat('-', 66));
printf("  %d checks — %d passed, %d failed.\n", count($res['checks']), $pass, $fail);
asay($fail === 0 ? '  ✔ The inspection & audit pack holds together end to end.' : '  ✘ Some links or gates did not hold — see the FAIL lines.');
