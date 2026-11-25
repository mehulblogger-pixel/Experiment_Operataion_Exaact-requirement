<?php
// Order chain — other POs on an inspection call (owner, 25 Nov 2026: Q1 "Option B",
// Q2 "Yes"). IOCL (end user) → L&T (EPC contractor) → KSB (manufacturer) → ABC
// Castings (sub-vendor, where we inspect). The call's "Client PO no. (placed on the
// manufacturer)" stays the main PO; the other links are optional lines:
//   - saved, edited and cleared with the call; History records old → new;
//   - every chain PO no. is searchable and opens the same Inspection history;
//   - "PO references" prints on reports / Release Notes only when a chain exists,
//     and an ISSUED report keeps what it was issued with;
//   - the role list is editable; the PO lock (D4) is unchanged; no new right.
// Made-up data only.
t_section('TPIA — order chain (other POs) on an inspection call');

t_ok(function_exists('pochain_save') && function_exists('pochain_print_lines'), 'the order-chain helpers exist');
t_ok(in_array('call_id', t_columns('call_po_chain'), true) && in_array('po_no', t_columns('call_po_chain'), true), 'table call_po_chain exists');
t_ok(in_array('po_chain_snap', t_columns('report_docs'), true), 'report_docs.po_chain_snap exists (empty on every older report)');
t_ok(!in_array('pc_by', call_save_fields(), true), 'the chain is not a calls column — supply_po_ref stays the main PO');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    t_as_admin();
    $pdo->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['PCA', 'Chain office A']);
    $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('PC Larsen Projects',1,?)")->execute([date('c')]);
    $cli = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_vendor, created_at) VALUES ('PC ABC Castings',1,?)")->execute([date('c')]);
    $abc = (int)$pdo->lastInsertId();
    $mkCall = function ($code, $po) use ($pdo, $off, $cli, $abc) {
        $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, executing_office_id, ibo_office_id, supply_po_ref, call_received_date, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?)")->execute([$code, $cli, $abc, $off, $off, $po, '2026-11-02', 'OPEN', 'x']);
        return (int)$pdo->lastInsertId();
    };
    $c1 = $mkCall('PC-C1', 'PO-LT-2002');
    $c2 = $mkCall('PC-C2', 'PO-LT-2999');      // another call whose CHAIN carries PO-IOC-1001
    $c3 = $mkCall('PC-C3', '');                // no main PO, chain only

    // ---- Roles: an editable list -------------------------------------------
    $roles = pochain_roles();
    t_eq($roles['EPC'] ?? '', 'EPC contractor', 'the role list starts with the six defaults (EPC contractor …)');
    t_ok(isset($roles['SUB_VENDOR'], $roles['TRADER'], $roles['END_USER'], $roles['MANUFACTURER'], $roles['OTHER']), '… end user, manufacturer, trader, sub-vendor, other');
    $lt = function_exists('lk_type') ? lk_type('call_po_role') : null;
    t_ok((bool)$lt, 'the role list is a Masters list (call_po_role) — editable');
    if ($lt) {
        lk_add_value((int)$lt['id'], null, 'LSTK', 'LSTK contractor', 99);
        // (pochain_roles() reads the same list on the next page load; read it fresh here.)
        t_ok(in_array('LSTK', array_column(lk_root_values((int)$lt['id']), 'code'), true), 'a value added in Masters is in the role list');
    }

    // ---- Save / edit / clear + History --------------------------------------
    $post = ['pc_by' => ['IOCL', 'KSB', '', ''], 'pc_on' => ['L&T', 'ABC Castings', '', ''], 'pc_po' => ['PO-IOC-1001', 'PO-KSB-3003', '', ''],
             'pc_role' => ['EPC', 'SUB_VENDOR', 'OTHER', 'NOT_A_ROLE']];
    $rows = pochain_from_post($post);
    t_eq(count($rows), 2, 'empty lines are ignored (a role alone is not a line)');
    t_ok(pochain_save($c1, $rows), 'saving the chain stores it');
    $got = pochain_lines($c1);
    t_eq(array_column($got, 'po_no'), ['PO-IOC-1001', 'PO-KSB-3003'], 'two lines, in the order typed');
    t_eq(pochain_line_text($got[0]), 'IOCL → L&T · PO PO-IOC-1001 · EPC contractor', 'a line reads "IOCL → L&T · PO … · EPC contractor"');
    t_eq((string)ops_val("SELECT supply_po_ref FROM calls WHERE id=?", [$c1]), 'PO-LT-2002', 'the main client PO is untouched');
    $hist = (string)ops_val("SELECT body FROM activities WHERE entity_kind='CALL' AND entity_id=? AND subject LIKE 'Order chain changed%' ORDER BY id DESC LIMIT 1", [$c1]);
    t_ok(strpos($hist, '(none) → IOCL → L&T') !== false, 'History records the change (old → new)');
    t_ok(!pochain_save($c1, $rows), 'saving the same chain again changes nothing and writes no History');
    $rows2 = pochain_from_post(['pc_by' => ['IOCL'], 'pc_on' => ['L&T'], 'pc_po' => ['PO-IOC-1001'], 'pc_role' => ['EPC']]);
    t_ok(pochain_save($c1, $rows2) && count(pochain_lines($c1)) === 1, 'editing replaces the lines (one left)');
    $hist = (string)ops_val("SELECT body FROM activities WHERE entity_kind='CALL' AND entity_id=? AND subject LIKE 'Order chain changed%' ORDER BY id DESC LIMIT 1", [$c1]);
    t_ok(strpos($hist, 'PO-KSB-3003') !== false && strpos($hist, '→ IOCL') !== false, 'History shows the line that went and what is left');
    t_ok(pochain_save($c1, []) && count(pochain_lines($c1)) === 0, 'clearing every line removes the chain');
    pochain_save($c1, $rows);
    pochain_save($c2, pochain_from_post(['pc_by' => ['IOCL'], 'pc_on' => ['L&T'], 'pc_po' => ['po-ioc-1001 '], 'pc_role' => ['EPC']]));
    pochain_save($c3, pochain_from_post(['pc_by' => ['KSB'], 'pc_on' => ['ABC Castings'], 'pc_po' => ['PO-KSB-3003'], 'pc_role' => ['SUB_VENDOR']]));

    // ---- Search --------------------------------------------------------------
    if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; $GLOBALS['__nv_tpl'] = $tpl; } }
    $GLOBALS['__nv'] = null; $_GET = ['q' => 'PO-KSB-3003'];
    try { ops_calls('calls', 'GET'); } catch (Throwable $e) {}
    $ids = array_map('intval', array_column($GLOBALS['__nv']['rows'] ?? [], 'id'));
    t_ok(in_array($c1, $ids, true) && in_array($c3, $ids, true) && !in_array($c2, $ids, true), 'the calls register finds a PO that is only on the chain');
    $_GET = [];
    [$pcSql, $pcArg] = pochain_search_sql('c', '%PO-KSB-3003%');
    $n = (int)ops_val("SELECT COUNT(*) FROM calls c WHERE 1=0$pcSql", $pcArg);
    t_eq($n, 2, 'the top search clause finds both calls carrying PO-KSB-3003');
    $src = file_get_contents(__DIR__ . '/../lib/search.php');
    t_ok(strpos($src, 'pochain_search_sql') !== false, 'the top search box uses the chain clause');

    // ---- Inspection history by a chain PO -----------------------------------
    $call1 = ops_one("SELECT * FROM calls WHERE id=?", [$c1]);
    $h = chain_history_for_call($c1, 'po', 'PO-IOC-1001');
    t_eq($h['value'], 'PO-IOC-1001', 'opened from a chain PO, the history matches on that PO');
    t_eq(array_map('intval', array_column($h['calls'], 'id')), [$c1, $c2], '… and lists every call carrying it in its chain (any spelling of case/spaces)');
    $h = chain_history_for_call($c1, 'po');
    t_eq($h['value'], 'PO-LT-2002', 'opened from the main PO it still matches on the main PO');
    $h = chain_history_for_call($c1, 'po', 'PO-NOT-MINE');
    t_eq($h['value'], 'PO-LT-2002', 'a PO that is not on this call is ignored (falls back to the main PO)');
    $h = chain_history_for_call($c3, 'po');
    t_eq([$h['by'], $h['value']], ['po', 'PO-KSB-3003'], 'a call with no main PO matches on its first chain PO');
    t_eq(array_map('intval', array_column($h['calls'], 'id')), [$c1, $c3], '… and finds the other call carrying it in its chain');
    $html = chain_history_panel($call1, 'po', 'PO-KSB-3003');
    t_ok(strpos($html, 'PO-KSB-3003') !== false && strpos($html, 'order chain') !== false, 'the panel says it matched a PO on the order chain');

    // ---- Screens ---------------------------------------------------------------
    $tpl = __DIR__ . '/../views/ops/call_form.php';
    $vars = call_form_vars($call1); $vars['cur'] = 'call-edit';
    ob_start();
    try { (function () use ($tpl, $vars) { extract($vars); include $tpl; })(); } catch (Throwable $e) { echo 'THREW ' . $e->getMessage(); }
    $f = (string)ob_get_clean();
    t_ok(strpos($f, 'THREW') === false, 'the call form renders');
    t_ok(strpos($f, 'Order chain — other POs') !== false && substr_count($f, 'name="pc_po[]"') === 3, 'the form shows the two saved lines plus one empty line');
    t_ok(strpos($f, 'value="PO-KSB-3003"') !== false && strpos($f, 'name="pc_present"') !== false, '… filled with the saved values');
    t_ok(strpos($f, '@media (max-width:700px)') !== false && strpos($f, 'grid-template-columns:1fr;') !== false, 'on a phone the four boxes stack in one column');
    $dsrc = file_get_contents(__DIR__ . '/../views/ops/call_detail.php');
    t_ok(strpos($dsrc, 'pochain_lines') !== false && strpos($dsrc, "'po&po='") !== false, 'the call page lists the chain, each PO a link to the history');
    $jsrc = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
    t_ok(strpos($jsrc, '$tpChain') !== false && strpos($jsrc, 'hist=po&amp;po=') !== false, "the job's What was offered block lists the chain");

    // ---- Print: "PO references" ---------------------------------------------
    $pdo->prepare("INSERT INTO report_docs (call_id, irn, type_code, status, finalized, client_id, po_ref, deleted, data, created_at) VALUES (?,?,?,?,?,?,?,0,'[]',?)")
        ->execute([$c1, 'PC-IR-1', 'IR', 'DRAFT', 0, $cli, 'PO-LT-2002', date('c')]);
    $draft = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
    $pl = pochain_print_lines($draft);
    t_eq(count($pl), 3, 'a draft report prints the main PO plus the two chain lines');
    t_ok(strpos($pl[0], 'PO PO-LT-2002') !== false && strpos($pl[1], 'IOCL → L&T') !== false, '… main PO first, then the chain');
    $plain = $mkCall('PC-C4', 'PO-PLAIN-1');
    $pdo->prepare("INSERT INTO report_docs (call_id, irn, type_code, status, finalized, client_id, po_ref, deleted, data, created_at) VALUES (?,?,?,?,?,?,?,0,'[]',?)")
        ->execute([$plain, 'PC-IR-2', 'IR', 'DRAFT', 0, $cli, 'PO-PLAIN-1', date('c')]);
    t_eq(pochain_print_lines(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()])), [], 'a call without a chain prints no block (print unchanged)');
    // A Release Note raised under a job of the call reads the call through the job.
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, created_at) VALUES ('PC-J1',?,?,?)")->execute([$c1, $off, date('c')]);
    $j1 = (int)$pdo->lastInsertId();
    t_eq(count(pochain_print_lines(['job_id' => $j1, 'finalized' => 0])), 3, 'a Release Note on a job of the call prints the same block');
    $isrc = file_get_contents(__DIR__ . '/../lib/idems.php');
    t_ok(substr_count($isrc, 'pochain_print_lines($doc)') === 2, 'both the PDF and the Word print use the block');
    // Rendered PDF carries the heading (content streams are not compressed).
    if (function_exists('report_pdf_build')) {
        try {
            $bin = report_pdf_build($draft + ['client_name' => 'PC Larsen Projects'], [], [], [], [], null, [], '');
            $bin = is_array($bin) ? (string)($bin[0] ?? '') : (string)$bin;
            t_ok(strpos($bin, 'PO references') !== false && strpos($bin, 'IOCL -> L&T') !== false, 'the PDF prints "PO references" with the chain');
        } catch (Throwable $e) { t_ok(false, 'the PDF builds: ' . $e->getMessage()); }
    }

    // ---- An issued report is never changed -----------------------------------
    $pdo->prepare("UPDATE report_docs SET finalized=1, status='ISSUED' WHERE id=?")->execute([(int)$draft['id']]);
    pochain_snapshot_doc((int)$draft['id']);
    $issued = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$draft['id']]);
    $before = pochain_print_lines($issued);
    t_eq(count($before), 3, 'issuing freezes the chain onto the report');
    pochain_save($c1, pochain_from_post(['pc_by' => ['X'], 'pc_on' => ['Y'], 'pc_po' => ['PO-CHANGED'], 'pc_role' => ['']]));
    $issued = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$draft['id']]);
    t_eq(pochain_print_lines($issued), $before, 'a later change to the call does not change the issued report');
    $pdo->prepare("INSERT INTO report_docs (call_id, irn, type_code, status, finalized, client_id, deleted, data, created_at) VALUES (?,?,?,?,1,?,0,'[]',?)")
        ->execute([$c1, 'PC-IR-OLD', 'IR', 'ISSUED', $cli, date('c')]);
    t_eq(pochain_print_lines(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()])), [], 'a report issued before this feature prints exactly as before');
    t_ok(strpos($isrc, 'pochain_snapshot_doc($doc)') !== false && strpos($isrc, 'pochain_snapshot_doc($doc);') < strpos($isrc, 'idems_seal_content($doc[\'id\']);'), 'the issue step freezes the chain before sealing');

    // ---- PO lock (D4) unchanged -------------------------------------------------
    $lsrc = substr($isrc, strpos($isrc, 'function idems_po_locked'), 1400);
    t_ok(strpos($lsrc, 'pochain') === false && strpos($lsrc, 'po_ref=?') !== false, 'the PO lock still keys on the report P.O. and the call — the chain plays no part');

    // ---- No new right -----------------------------------------------------------
    $osrc = file_get_contents(__DIR__ . '/../lib/ops.php');
    t_eq(substr_count($osrc, 'pochain_save((int)'), 2, 'the chain is saved only by the call save (new and edit)');
    $gate = strpos($osrc, "ops_require(can('ops.call.create') || is_master(), 'Only operations / coordinators can create '");
    $save = strpos($osrc, 'pochain_save((int)$call[\'id\']');
    t_ok($gate !== false && $save !== false && $gate < $save, '… behind the existing "create / edit call" check (no new permission)');
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('PC Inspector', ?)")->execute([$off]);
    $ins = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id, inspector_id) VALUES (?,?,?,1,?,?)")
        ->execute(['pc_insp', 'PC', 'INSPECTOR', $off, $ins]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    t_ok(!can('ops.call.create') && !is_master(), 'an inspector cannot open the call edit form, so cannot change the chain');
    t_as_nobody();
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    $_GET = [];
}
