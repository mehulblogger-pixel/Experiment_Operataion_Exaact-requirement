<?php
// ============================================================================
//  Golden-thread traceability check — command line.
//
//  Builds ONE record and follows it the whole way through the business, then
//  reads the database back and confirms every link was recorded. Prints a
//  step-by-step trace and a pass/fail traceability table.
//
//  Usage, from the phpapp directory:
//      php tools/trace-thread.php            build it and check
//      php tools/trace-thread.php --force    rebuild even if already built
//      php tools/trace-thread.php --remove   take the thread out again
//      php tools/trace-thread.php --check    re-run the checks on what is there
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }

chdir(__DIR__ . '/..');
foreach (['db','helpers','ops','lookups','licence','access','terms','compose','crm','pdf','ai','workforce',
          'orgadmin','contracts','security','compliance','costing','reset','partnerimport','mis','dedupe',
          'joblock','schedule','callprofit','bills','competence','preflight','equipment','impartiality',
          'identity','complaints','capa','audits','datacontrol','trust','portal','tally','receivables',
          'idems','books','booksui','leads','opportunities','crmdash','activity','customer360',
          'seed_demo','trace_seed'] as $m) {
    $f = __DIR__ . "/../lib/$m.php";
    if (is_file($f)) require $f;
}

if (session_status() !== PHP_SESSION_ACTIVE) { $_SESSION = []; }

try { boot(); }
catch (Throwable $e) { fwrite(STDERR, "Cannot reach the database: " . $e->getMessage() . "\n"); exit(1); }

$arg = $argv[1] ?? '';
function tsay($s = '') { echo $s . "\n"; }

if ($arg === '--remove') {
    $r = trace_seed_remove();
    tsay('Removed ' . (int)($r['deleted'] ?? 0) . ' records of the golden thread.');
    exit(0);
}

if ($arg === '--check') {
    trace_become_admin();
    $cid = trace_client_id();
    if (!$cid) { tsay('No golden thread is loaded. Run it first: php tools/trace-thread.php'); exit(0); }
    // Rebuild the id map from the anchors so the checks can run on their own.
    $ids = trace_ids_from_db($cid);
    print_checks(trace_checks($ids));
    exit(0);
}

$res = trace_seed($arg === '--force');
if (!empty($res['skipped'])) { tsay('Already built. Use --force to rebuild, or --remove first.'); exit(0); }
if (empty($res['ok'])) { fwrite(STDERR, 'Build stopped: ' . ($res['error'] ?? 'unknown') . "\n"); exit(1); }

tsay('');
tsay('  THE GOLDEN THREAD — one record, followed the whole way through');
tsay('  ' . str_repeat('-', 66));
$i = 0;
foreach ($res['steps'] as $s) {
    printf("  %2d. %-32s %s\n", ++$i, $s['title'], $s['ref']);
    tsay('      ' . $s['detail']);
}
tsay('');
print_checks($res['checks']);

// Rebuild the id map from the anchor customer, for --check runs.
function trace_ids_from_db($clientId) {
    $ids = ['client' => $clientId];
    $ids['vendor']  = (int) ops_val("SELECT id FROM business_partners WHERE code=?", [TRACE_VENDOR_CODE]);
    $ids['lead']    = (int) ops_val("SELECT id FROM leads WHERE partner_id=? OR converted_partner_id=? ORDER BY id DESC LIMIT 1", [$clientId, $clientId]);
    $ids['opp']     = (int) ops_val("SELECT id FROM opportunities WHERE partner_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    $ids['quote']   = (int) ops_val("SELECT id FROM quotations WHERE client_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    $ids['call']    = (int) ops_val("SELECT id FROM calls WHERE client_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    $ids['job']     = (int) ops_val("SELECT id FROM jobs WHERE call_id=? ORDER BY id DESC LIMIT 1", [$ids['call']]);
    $ids['report']  = (int) ops_val("SELECT id FROM report_docs WHERE client_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    $ids['invoice'] = (int) ops_val("SELECT id FROM invoices WHERE partner_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    $ids['receipt'] = (int) ops_val("SELECT id FROM receipts WHERE partner_id=? ORDER BY id DESC LIMIT 1", [$clientId]);
    return $ids;
}

function print_checks($checks) {
    tsay('  TRACEABILITY — is every link recorded?');
    tsay('  ' . str_repeat('-', 66));
    $area = ''; $pass = 0; $fail = 0;
    foreach ($checks as $c) {
        if ($c['area'] !== $area) { $area = $c['area']; tsay('  ' . strtoupper($area)); }
        $mark = $c['ok'] ? 'PASS' : 'FAIL';
        if ($c['ok']) $pass++; else $fail++;
        printf("    [%s] %-46s\n", $mark, $c['proves']);
        if (!$c['ok']) printf("           expected [%s], found [%s]\n", $c['expected'], $c['actual']);
    }
    tsay('  ' . str_repeat('-', 66));
    printf("  %d checks — %d passed, %d failed.\n", count($checks), $pass, $fail);
    tsay($fail === 0
        ? '  ✔ The data flows from start to end and is recorded at every place.'
        : '  ✘ Some links are missing — see the FAIL lines above.');
}
