<?php
// ============================================================================
//  TEST PACK — ready-made data for the owner's test book, loaded INSIDE the app
//  on the TEST COPY only (owner, 28 Nov 2026: "a complete data set duly installed
//  in the app so I can go step by step, screen by screen, button by button and
//  just check it without entering anything").
//
//  Same pattern as the DEMO-S01 scenario (lib/seed_scenario_s01.php): one engine
//  for the Admin → System Settings button and the command line
//  (tools/seed-testpack.php). What is different:
//    · it NEVER runs outside the test copy (app_is_test_copy()) and only for the
//      Master Admin — refused here in the engine, in the route and in the CLI;
//    · it creates no users and no passwords: it uses the opstest logins that
//      already exist (test.coord, test.inspector …) and tp_status() lists any
//      that are missing, for the owner to create;
//    · every row it makes is written to a ledger (setting tp_pack_ledger), and
//      tp_remove() deletes exactly those rows (plus what the owner then made on
//      the pack's own calls/jobs/clients) — nothing else is touched.
//
//  REGISTRY — each sheet group lives in its own file and registers itself:
//      tp_register('OPS-A', 'Operations · sheets 1–5, Stage 1, Industries', function (array $ctx) {
//          …create records with tp_insert() / tp_client() / tp_call() / tp_job() …
//          return ['OA-02' => 'Open call TP-C-0101 …', …];     // test id => what to open
//      });
//  tp_load() runs the seeders in key order and stores the merged map
//  (setting tp_pack_map), which the test book's "Ready-made record" column uses.
// ============================================================================

const TP_LEDGER_KEY = 'tp_pack_ledger';
const TP_MAP_KEY    = 'tp_pack_map';
const TP_AT_KEY     = 'tp_pack_loaded_at';
const TP_TAG        = 'TP';          // code / name prefix on everything the pack makes
const TP_BY         = 'test pack';   // created_by on rows that carry one

// The logins the Operations test book signs in as. Looked up, never created.
const TP_LOGINS = ['admin', 'test.coord', 'test.coord2', 'test.bam', 'test.bdm', 'test.inspector', 'test.inspector2',
                   'test.srinspector', 'test.finance', 'test.opsmgr', 'test.director', 'test.branchmgr', 'test.qualityhead'];

// What a test may have created ON a pack job / work order, removed with it.
const TP_JOB_CHILDREN = ['job_visits', 'report_docs', 'assignment_events', 'expenses', 'attendance', 'billable_events', 'cost_allocations',
                         'dep_att_approval', 'dep_checklist', 'dep_gate_pass', 'dep_site_history', 'dep_site_log', 'dep_status_events',
                         'dep_timesheet', 'hw_points', 'job_bills', 'job_delays', 'job_qaps', 'job_readiness', 'satisfaction_surveys',
                         'site_visits', 'witness_assessments'];
const TP_CALL_CHILDREN = ['call_status_events', 'call_po_chain', 'call_clarifications', 'call_nudges', 'contract_overrides',
                          'report_docs', 'hw_points', 'job_delays'];

$GLOBALS['TP_SEEDERS'] = $GLOBALS['TP_SEEDERS'] ?? [];

/** A later builder adds a sheet group here, without editing this file. */
function tp_register($key, $title, callable $seed) {
    $GLOBALS['TP_SEEDERS'][(string) $key] = ['title' => (string) $title, 'seed' => $seed];
}
/** Every sheet-group file is lib/seed_testpack_<group>.php — found here, so a new
 *  group needs no edit to this file or to index.php. */
function tp_seeders() {
    foreach (glob(__DIR__ . '/seed_testpack_*.php') ?: [] as $f) require_once $f;
    $s = $GLOBALS['TP_SEEDERS'] ?? []; ksort($s); return $s;
}

/** May the pack run here, for this person? '' = yes, else why not. */
function tp_refusal() {
    if (!function_exists('app_is_test_copy') || !app_is_test_copy())
        return 'The test pack is only for the test copy (opstest). It is refused on any other site.';
    //  The command line signs in as the Master Admin first (tools/seed-testpack.php),
    //  so this one question covers the button and the command alike.
    $master = false;
    try { $master = function_exists('is_master') && is_master(); } catch (Throwable $e) { $master = false; }
    if (!$master) return 'Only the Master Admin can load or remove the test pack.';
    return '';
}

function tp_d($days) { return date('Y-m-d', strtotime(date('Y-m-d') . ' ' . ((int) $days >= 0 ? '+' . (int) $days : (int) $days) . ' days')); }

// ---- Ledger -------------------------------------------------------------------
function tp_ledger() {
    $j = function_exists('setting_get') ? (string) setting_get(TP_LEDGER_KEY, '') : '';
    $l = $j !== '' ? json_decode($j, true) : [];
    return is_array($l) ? $l : [];
}
function tp_ledger_put(array $l) { setting_set(TP_LEDGER_KEY, json_encode($l)); }
function tp_track($table, $id) {
    $id = (int) $id; if ($id <= 0) return;
    $l = tp_ledger(); $l[$table] = $l[$table] ?? [];
    if (!in_array($id, $l[$table], true)) $l[$table][] = $id;
    tp_ledger_put($l);
}
function tp_tracked($table) { return array_map('intval', tp_ledger()[$table] ?? []); }

/** Insert a row with only the columns this install has; track it; return its id. */
function tp_insert($table, array $row) {
    $cols = function_exists('table_columns') ? table_columns($table) : [];
    if ($cols) $row = array_intersect_key($row, $cols);
    if (!$row) return 0;
    $k = array_keys($row);
    db()->prepare("INSERT INTO $table (" . implode(',', $k) . ") VALUES (" . implode(',', array_fill(0, count($k), '?')) . ")")
        ->execute(array_values($row));
    $id = (int) db()->lastInsertId();
    tp_track($table, $id);
    return $id;
}

// ---- Context: the logins, inspectors and offices the book names -------------
function tp_ctx() {
    $users = []; $missing = [];
    foreach (TP_LOGINS as $u) {
        $r = ops_one("SELECT id, username, first_name, last_name, inspector_id, home_office_id, role FROM users WHERE LOWER(username)=? AND is_active=1", [strtolower($u)]);
        if ($r) $users[$u] = $r; else $missing[] = $u;
    }
    $insp = function ($login, $emp, $name) use ($users) {
        if (!empty($users[$login]['inspector_id'])) return (int) $users[$login]['inspector_id'];
        $id = (int) (ops_val("SELECT id FROM inspectors WHERE emp_code=?", [$emp]) ?: 0);
        if (!$id) $id = (int) (ops_val("SELECT id FROM inspectors WHERE LOWER(name)=?", [strtolower($name)]) ?: 0);
        return $id;
    };
    $off = function ($like) { return (int) (ops_val("SELECT id FROM offices WHERE LOWER(name) LIKE ? OR LOWER(city) LIKE ? ORDER BY id LIMIT 1", ['%' . $like . '%', '%' . $like . '%']) ?: 0); };
    $ahm = $off('ahmedabad') ?: (int) (ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 ORDER BY id LIMIT 1") ?: 0);
    $mum = $off('mumbai');
    $ins = ['inspector' => $insp('test.inspector', 'EMP18', 'Test Inspector'),
            'inspector2' => $insp('test.inspector2', 'EMP19', 'Test Inspector2'),
            'srinspector' => $insp('test.srinspector', 'EMP17', 'Test SrInspector')];
    $missIns = array_keys(array_filter($ins, fn($v) => !$v));
    return ['users' => $users, 'missing_logins' => $missing, 'ins' => $ins, 'missing_inspectors' => $missIns,
            'ahm' => $ahm, 'mum' => $mum, 'today' => date('Y-m-d')];
}

/** A client by its exact legal name: reused if the owner already has it (not
 *  tracked, never deleted); else created with the pack tag and tracked. */
function tp_client($legalName, array $extra = []) {
    $id = (int) (ops_val("SELECT id FROM business_partners WHERE LOWER(legal_name)=? ORDER BY id LIMIT 1", [strtolower($legalName)]) ?: 0);
    if (!$id && !empty($extra['gstin']))
        $id = (int) (ops_val("SELECT id FROM business_partners WHERE UPPER(gstin)=? ORDER BY id LIMIT 1", [strtoupper($extra['gstin'])]) ?: 0);
    if ($id) {
        //  An existing client keeps its own data — only a hold the test needs is applied,
        //  and only on a client the pack made (see tp_client_state()).
        return $id;
    }
    $code = function_exists('gen_partner_code') ? gen_partner_code($extra['home_branch_id'] ?? null, $legalName) : (TP_TAG . '-' . substr(md5($legalName), 0, 6));
    return tp_insert('business_partners', array_merge([
        'code' => $code, 'legal_name' => $legalName, 'display_name' => $legalName, 'is_client' => 1, 'is_vendor' => 1,
        'status' => 'ACTIVE', 'created_at' => date('c'), 'description' => 'Test pack (' . TP_TAG . ') — made by the test pack; removed with it.',
    ], $extra));
}
/** Hold / block a client — only one the pack made (a reused client is left as the owner set it). */
function tp_client_state($id, $hold, $reason) {
    if (!in_array((int) $id, tp_tracked('business_partners'), true)) return false;
    db()->prepare("UPDATE business_partners SET hold_status=?, hold_reason=? WHERE id=?")->execute([$hold, $reason, (int) $id]);
    return true;
}

/** A work order the way the call form writes it (status OPEN / FORWARDED, op_status). */
function tp_call($code, array $f) {
    $ex = (int) (ops_val("SELECT id FROM calls WHERE call_code=?", [$code]) ?: 0);
    if ($ex) return $ex;
    $d = $f['date'] ?? tp_d(0);
    $row = array_merge([
        //  The book's calls are "at the client's own premises": the client is also the site.
        'call_code' => $code, 'client_id' => 0, 'vendor_id' => $f['client_id'] ?? 0, 'ibo_office_id' => $f['office_id'] ?? null,
        'contracting_office_id' => $f['office_id'] ?? null, 'executing_office_id' => $f['office_id'] ?? null,
        'sbu' => 'IND', 'call_received_date' => tp_d(0), 'inspection_required_date' => $d, 'inspection_dates' => $d,
        'inspection_type' => 'Third-party inspection', 'reporting_frequency' => 'DAILY', 'deliverables' => 'IR',
        'status' => 'OPEN', 'op_status' => 'ACCEPTED', 'notes' => 'Test pack (' . TP_TAG . ')',
        'created_by' => TP_BY, 'created_at' => date('c'),
    ], $f);
    unset($row['date'], $row['office_id']);
    $id = tp_insert('calls', $row);
    if (function_exists('touch_row_version')) touch_row_version('calls', $id);
    return $id;
}

/** A job on a call, with its visit days made by the app's own job_visits_sync(). */
function tp_job($code, $callId, array $f) {
    $ex = (int) (ops_val("SELECT id FROM jobs WHERE job_code=?", [$code]) ?: 0);
    if ($ex) return $ex;
    $call = ops_one("SELECT * FROM calls WHERE id=?", [(int) $callId]) ?: [];
    $d = $f['date'] ?? tp_d(0);
    $row = array_merge([
        'job_code' => $code, 'call_id' => (int) $callId, 'executing_office_id' => $call['executing_office_id'] ?? null,
        'contracting_office_id' => $call['contracting_office_id'] ?? null, 'inspector_id' => null,
        'scheduled_date' => $d, 'inspection_start_date' => $d, 'inspection_end_date' => $d, 'inspection_dates' => $d,
        'reporting_frequency' => $call['reporting_frequency'] ?? 'DAILY', 'deliverables' => $call['deliverables'] ?? 'IR',
        'sbu' => $call['sbu'] ?? 'IND', 'mandays' => 1, 'job_type' => 'INSPECTION', 'stage' => 'ALLOCATED',
        'chargeable_heads' => $call['chargeable_heads'] ?? '', 'created_by' => TP_BY, 'created_at' => date('c'),
    ], $f);
    unset($row['date']);
    $id = tp_insert('jobs', $row);
    db()->prepare("UPDATE calls SET status='ALLOCATED', allocated_at=? WHERE id=?")->execute([date('c'), (int) $callId]);
    if (function_exists('touch_row_version')) touch_row_version('jobs', $id);
    if (function_exists('job_visits_sync')) {
        try { job_visits_sync($id, (int) ($row['inspector_id'] ?? 0)); } catch (Throwable $e) {}
        foreach (ops_all("SELECT id FROM job_visits WHERE job_id=?", [$id]) ?: [] as $v) tp_track('job_visits', (int) $v['id']);
    }
    return $id;
}

// ---- Load / remove / status ---------------------------------------------------
function tp_is_loaded() { return tp_ledger() !== []; }

/** Load the pack. Already loaded: nothing is added (returns the status) unless
 *  $fresh — then the pack is removed first and made again from a clean start. */
function tp_load($fresh = false) {
    if (($why = tp_refusal()) !== '') return ['ok' => false, 'error' => $why];
    if (tp_is_loaded() && !$fresh) return ['ok' => true, 'already' => true] + tp_status();
    if (tp_is_loaded()) tp_remove();
    $ctx = tp_ctx(); $map = []; $errors = [];
    foreach (tp_seeders() as $key => $s) {
        try { $m = ($s['seed'])($ctx); if (is_array($m)) $map += $m; }
        catch (Throwable $e) { $errors[$key] = $e->getMessage(); }
    }
    setting_set(TP_MAP_KEY, json_encode($map));
    setting_set(TP_AT_KEY, date('c'));
    tp_audit('Test pack loaded', count($map));
    return ['ok' => !$errors, 'errors' => $errors, 'map' => $map] + tp_status();
}

/** Remove exactly the pack: the ledger's rows, plus whatever was then made ON the
 *  pack's own clients / calls / jobs (an allocation, a visit, a report …). */
function tp_remove() {
    if (($why = tp_refusal()) !== '') return ['ok' => false, 'error' => $why, 'deleted' => 0];
    $pdo = db(); $n = 0;
    $ids = fn($a) => implode(',', array_map('intval', array_values(array_unique($a))));
    $del = function ($table, $col, array $vals) use ($pdo, &$n, $ids) {
        $vals = array_filter(array_map('intval', $vals)); if (!$vals) return;
        $cols = function_exists('table_columns') ? table_columns($table) : [];
        if (!$cols || !isset($cols[$col])) return;
        try { $n += $pdo->exec("DELETE FROM $table WHERE $col IN (" . $ids($vals) . ")"); } catch (Throwable $e) {}
    };
    $partners = tp_tracked('business_partners');
    $calls = tp_tracked('calls');
    if ($partners) foreach (ops_all("SELECT id FROM calls WHERE client_id IN (" . $ids($partners) . ")") ?: [] as $r) $calls[] = (int) $r['id'];
    $jobs = tp_tracked('jobs');
    if ($calls) foreach (ops_all("SELECT id FROM jobs WHERE call_id IN (" . $ids($calls) . ")") ?: [] as $r) $jobs[] = (int) $r['id'];
    $jobs = array_values(array_unique($jobs)); $calls = array_values(array_unique($calls));
    $insp = tp_tracked('inspectors');
    //  The operational rows a test creates on a pack job / work order. Deliberately
    //  NOT here: numbered money documents (invoices, invoice lines, vouchers, Tally
    //  exports), quality records (complaints, NCRs) and the audit trail — those are
    //  kept, so a number series never gets a hole and history is never deleted.
    foreach (TP_JOB_CHILDREN as $t) $del($t, 'job_id', $jobs);
    if ($jobs) try { $n += $pdo->exec("DELETE FROM activities WHERE UPPER(entity_kind)='JOB' AND entity_id IN (" . $ids($jobs) . ")"); } catch (Throwable $e) {}
    $del('jobs', 'id', $jobs);
    foreach (TP_CALL_CHILDREN as $t) $del($t, 'call_id', $calls);
    if ($calls) try { $n += $pdo->exec("DELETE FROM activities WHERE UPPER(entity_kind)='CALL' AND entity_id IN (" . $ids($calls) . ")"); } catch (Throwable $e) {}
    $del('calls', 'id', $calls);
    //  People and customers: only ones the pack itself made.
    foreach (['inspector_certs', 'inspector_allowances', 'inspector_day_status', 'attendance', 'authorisations'] as $t) $del($t, 'inspector_id', $insp);
    $del('inspectors', 'id', $insp);
    foreach (['partner_contracts', 'partner_addresses', 'partner_contacts', 'partner_notes', 'partner_registrations', 'partner_relationships',
              'vendor_profiles', 'vendor_status_events', 'site_doc_requirements', 'activities'] as $t) $del($t, 'partner_id', $partners);
    //  What a tester adds to the pack's own claims and people (OV-08 "Mark paid", OP-08 an ID
    //  document) is cleared with them — by the claim / person, not only by the pack's own row ids.
    $vch = tp_tracked('vouchers');
    foreach (['voucher_entries', 'voucher_approval_log'] as $t) $del($t, 'voucher_id', $vch);
    if ($insp) try { $n += $pdo->exec("DELETE FROM person_documents WHERE person_kind='INSPECTOR' AND person_id IN (" . $ids($insp) . ")"); } catch (Throwable $e) {}
    // Anything else the ledger holds (contracts on a reused client, single rows …).
    foreach (array_reverse(tp_ledger(), true) as $table => $rows) {
        if (in_array($table, ['jobs', 'calls', 'inspectors', 'business_partners'], true)) continue;
        $del($table, 'id', $rows);
    }
    $del('business_partners', 'id', $partners);
    setting_set(TP_LEDGER_KEY, ''); setting_set(TP_MAP_KEY, ''); setting_set(TP_AT_KEY, '');
    tp_audit('Test pack removed', $n);
    return ['ok' => true, 'deleted' => $n];
}
/** One line in the audit trail for each load / remove. The audit rows the pack's
 *  own records wrote stay — history is never deleted. */
function tp_audit($what, $n) {
    if (!function_exists('idems_log')) return;
    try { idems_log('setting', 0, 'TEST_PACK', ['field' => 'test_pack', 'new' => $what . ' (' . (int) $n . ')', 'reason' => 'Test copy only']); } catch (Throwable $e) {}
}

function tp_map() { $j = (string) (setting_get(TP_MAP_KEY, '') ?: ''); $m = $j !== '' ? json_decode($j, true) : []; return is_array($m) ? $m : []; }

function tp_status() {
    $l = tp_ledger();
    $ctx = function_exists('ops_one') ? tp_ctx() : ['missing_logins' => [], 'missing_inspectors' => []];
    return [
        'loaded' => $l !== [], 'loaded_at' => (string) (setting_get(TP_AT_KEY, '') ?: ''),
        'counts' => array_map('count', $l), 'tests' => count(tp_map()),
        'missing_logins' => $ctx['missing_logins'], 'missing_inspectors' => $ctx['missing_inspectors'],
        'groups' => array_map(fn($s) => $s['title'], tp_seeders()),
        'allowed' => tp_refusal() === '',
    ];
}
