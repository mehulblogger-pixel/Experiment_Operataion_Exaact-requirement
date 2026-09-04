<?php
// ============================================================================
//  DEMO-S05 — Convergence & reconciliation (the read-only dual-truth family).
//
//  Fifth in the progressive program (S01 freelancer · S02 agency · S03 client
//  foundation · S04 marketplace lifecycle → S05 the CONVERGENCE detectors). It
//  seeds one namespaced thread that lights up every read-only dual-truth detector
//  built this revamp so they can be clicked through with live data:
//
//    • P9  Revenue reconciliation  (/revenue-reconciliation) — a job whose legacy
//          invoice figure disagrees with the books ledger, and one legacy-only.
//    • P10 Cost reconciliation     (/cost-reconciliation)    — a job whose legacy
//          sub-contractor cost disagrees with the committed cost ledger, + legacy-only.
//    • P11 Candidate pool          (/candidate-pool)         — a recruitment
//          candidate who is also a marketplace professional (same person, two pools).
//
//  Each detector is READ-ONLY — it flags drift/overlap and moves no figure. The
//  seed also plants RECONCILED control rows, so the dashboard proves each detector
//  is specific (flags the drift, leaves the matching rows alone). Idempotent
//  (purge-first), on the existing tables, with a real derived PASS/FAIL dashboard.
//  Reuses s01_d() from DEMO-S01.
// ============================================================================

function seed_s05_status() {
    return [
        'loaded' => function_exists('setting_get') ? (bool)setting_get('demo_s05_seed') : false,
        'jobs'   => (int)ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'DEMO-S05-%'"),
        'pros'   => (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s05pro@demo.test'"),
        'cands'  => (int)ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code LIKE 'DEMO-S05-%'"),
    ];
}

function seed_s05_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    // invoices + their lines (by our namespaced invoice_no)
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM invoices WHERE invoice_no LIKE 'DEMO-S05-%'") ?: []) as $iid) {
        $del("DELETE FROM invoice_lines WHERE invoice_id=?", [$iid]);
        $del("DELETE FROM invoices WHERE id=?", [$iid]);
    }
    // jobs + their committed cost-ledger rows
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S05-%'") ?: []) as $jid) {
        $del("DELETE FROM invoice_lines WHERE job_id=?", [$jid]);
        $del("DELETE FROM cost_allocations WHERE job_id=?", [$jid]);
        $del("DELETE FROM jobs WHERE id=?", [$jid]);
    }
    $del("DELETE FROM calls WHERE call_code LIKE 'DEMO-S05-%'");
    $del("DELETE FROM candidates WHERE cand_code LIKE 'DEMO-S05-%'");
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_professionals WHERE email LIKE '%s05pro@demo.test'") ?: []) as $pid)
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    $del("DELETE FROM business_partners WHERE code LIKE 'DEMO-S05-%'");
    if (function_exists('setting_set')) setting_set('demo_s05_seed', '');
    return $n;
}

function seed_s05_load() {
    seed_s05_remove();
    try { db()->exec("SET SESSION sql_mode=''"); } catch (Throwable $e) {}
    $now = date('c'); $log = []; $say = function ($s) use (&$log) { $log[] = $s; };
    foreach (['costing_migrate', 'connect_pro_migrate', 'candpool_pro_index'] as $mg) if (function_exists($mg)) { try { $mg === 'candpool_pro_index' ? null : $mg(); } catch (Throwable $e) {} }
    $office = (int)(ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 1);

    // A client + a call to hang the jobs on (so the worklists show a client name).
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES ('DEMO-S05-CLIENT','DEMO-S05 Meridian Refinery Ltd','DEMO-S05 Meridian Refinery Ltd',1,'ACTIVE',?)")->execute([$now]);
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,status,created_by,created_at) VALUES ('DEMO-S05-CALL',?, 'Inspection','OPEN','demo-seed',?)")->execute([$client, $now]);
    $call = (int)db()->lastInsertId();

    $mkJob = function ($code, $cols) use ($call, $now) {
        $cols = array_merge(['job_code' => $code, 'call_id' => $call, 'sbu' => 'IND', 'created_at' => $now], $cols);
        $k = array_keys($cols); $ph = implode(',', array_fill(0, count($k), '?'));
        db()->prepare("INSERT INTO jobs (" . implode(',', $k) . ") VALUES ($ph)")->execute(array_values($cols));
        return (int)db()->lastInsertId();
    };
    $mkInvoice = function ($jobId, $net, $gross) use ($client, $now) {
        db()->prepare("INSERT INTO invoices (invoice_no,partner_id,status,invoice_date,created_at) VALUES (?,?, 'ISSUED', ?, ?)")
            ->execute(['DEMO-S05-INV-' . $jobId, $client, s01_d(-10), $now]);
        $inv = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO invoice_lines (invoice_id,job_id,amount,line_total) VALUES (?,?,?,?)")->execute([$inv, $jobId, $net, $gross]);
    };
    $mkLedgerCost = function ($jobId, $amount) use ($office, $now) {
        db()->prepare("INSERT INTO cost_allocations (yr,mon,office_id,sbu,source_kind,source_id,job_id,source_label,basis,amount,created_at)
                       VALUES (?,?,?, 'IND','SUBCON',?,?, 'DEMO-S05 sub-contractor','THE_JOB_IT_WAS_FOR',?,?)")
            ->execute([(int)date('Y'), (int)date('n'), $office, $jobId, $jobId, $amount, $now]);
    };

    // ---- P9 revenue reconciliation --------------------------------------------
    // (a) legacy 50,000 but the ledger says 20,000 → DIVERGES.
    $revBad = $mkJob('DEMO-S05-REV-DRIFT', ['closed_flag' => 1, 'invoice_raised' => 1, 'invoice_amount' => 50000]);
    $mkInvoice($revBad, 20000, 23600);
    // (b) legacy 18,000 but nothing in the ledger → LEGACY-ONLY.
    $revLeg = $mkJob('DEMO-S05-REV-LEGACY', ['closed_flag' => 1, 'invoice_raised' => 1, 'invoice_amount' => 18000]);
    // (c) legacy 30,000 matches the ledger net 30,000 → RECONCILED control.
    $revOk = $mkJob('DEMO-S05-REV-OK', ['closed_flag' => 1, 'invoice_raised' => 1, 'invoice_amount' => 30000]);
    $mkInvoice($revOk, 30000, 35400);

    // ---- P10 cost reconciliation ----------------------------------------------
    // (a) job subcon 40,000, committed ledger 15,000 (edited after the run) → DIVERGES.
    $costBad = $mkJob('DEMO-S05-COST-DRIFT', ['closed_flag' => 1, 'subcon_cost' => 40000]);
    $mkLedgerCost($costBad, 15000);
    // (b) job subcon 12,000, no committed run → LEGACY-ONLY.
    $costLeg = $mkJob('DEMO-S05-COST-LEGACY', ['closed_flag' => 1, 'subcon_cost' => 12000]);
    // (c) job subcon 25,000 matches committed ledger 25,000 → RECONCILED control.
    $costOk = $mkJob('DEMO-S05-COST-OK', ['closed_flag' => 1, 'subcon_cost' => 25000]);
    $mkLedgerCost($costOk, 25000);

    // ---- P11 candidate pool convergence ---------------------------------------
    // The SAME human in both pools — recruitment candidate + marketplace professional.
    // Mobile written with a leading 0 on the marketplace side (last-10 still matches).
    db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,mobile,email,stage,client_id,created_at) VALUES ('DEMO-S05-CAND-1','Farhan','Qureshi','9825501122','farhan.q.s05@demo.test','INTERVIEW',?,?)")->execute([$client, $now]);
    $candOverlap = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (name,email,mobile,headline,verification_tier,availability,is_active,created_at) VALUES ('Farhan Qureshi','farhan.diff.s05pro@demo.test','09825501122','Piping & welding QA/QC','verified','AVAILABLE',1,?)")->execute([$now]);
    $proOverlap = (int)db()->lastInsertId();
    // A candidate who is NOT on the marketplace → no overlap (specificity control).
    db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,mobile,email,stage,client_id,created_at) VALUES ('DEMO-S05-CAND-2','Solo','Aspirant','9000500500','solo.s05@demo.test','RECEIVED',?,?)")->execute([$client, $now]);
    $candSolo = (int)db()->lastInsertId();

    $say('Client + call · 3 revenue jobs (drift/legacy-only/reconciled) · 3 cost jobs (drift/legacy-only/reconciled)');
    $say('1 candidate who is also a marketplace professional (same person) + 1 candidate with no twin');

    // ---- DASHBOARD (real, derived — asserts each detector flags drift, not the controls) ----
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);  // refresh after seeding pros
    $rev = fn($jid) => function_exists('revrecon_job') ? revrecon_job($jid) : ['diverges' => null, 'legacy_only' => null];
    $cost = fn($jid) => function_exists('costrecon_job') ? costrecon_job($jid) : ['diverges' => null, 'legacy_only' => null];
    $candRow = ops_one("SELECT * FROM candidates WHERE id=?", [$candOverlap]);
    $soloRow = ops_one("SELECT * FROM candidates WHERE id=?", [$candSolo]);
    $proRow  = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$proOverlap]);
    $poolM   = function_exists('candpool_pro_matches') ? candpool_pro_matches($candRow) : [];
    $poolBack = function_exists('candpool_cand_matches') ? candpool_cand_matches($proRow) : [];
    $poolSolo = function_exists('candpool_pro_matches') ? candpool_pro_matches($soloRow) : [];
    $bestReason = $poolM ? $poolM[0]['reason'] : '';

    $dash = [
        ['Revenue: drifting job flagged (legacy ≠ ledger)',        !empty($rev($revBad)['diverges'])],
        ['Revenue: legacy-only job flagged (no ledger invoice)',    !empty($rev($revLeg)['legacy_only']) && !empty($rev($revLeg)['diverges'])],
        ['Revenue: reconciled control job NOT flagged',             empty($rev($revOk)['diverges'])],
        ['Cost: drifting job flagged (subcon ≠ committed ledger)',  !empty($cost($costBad)['diverges'])],
        ['Cost: legacy-only job flagged (no committed run)',        !empty($cost($costLeg)['legacy_only']) && !empty($cost($costLeg)['diverges'])],
        ['Cost: reconciled control job NOT flagged',                empty($cost($costOk)['diverges'])],
        ['Candidate pool: candidate matched to a professional',     count($poolM) >= 1],
        ['Candidate pool: matched by mobile (strong key)',          $bestReason === 'mobile'],
        ['Candidate pool: reverse lookup finds the candidate',      (bool)array_filter($poolBack, fn($c) => (int)$c['id'] === $candOverlap)],
        ['Candidate pool: a candidate with no twin matches nothing', count($poolSolo) === 0],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s05_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass];
}
