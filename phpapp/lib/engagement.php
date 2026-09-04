<?php
// Phase 2 §25 — the ENGAGEMENT, as a read-only grouping over the EXISTING contract_number.
//
// The whole sales→operations→finance spine already threads one string — contract_number — through
// quotations, calls, jobs and invoices (and reports hang off the jobs). But "show me the entire
// engagement behind this contract" was assembled ad-hoc in a few places (contract_pending_summary,
// contract_last_activity), each re-querying its own slice. This is the ONE resolver: given a
// contract_number it returns the full spine — every quote, call, job, report and invoice under it,
// with rollup counts. It reads; it never writes, and it introduces no new table or status. The
// canonical "engagement" is simply this view over the string that already links everything.

// The full engagement behind one contract_number. Each query is isolated so a missing column or table
// degrades that one dimension to empty rather than losing the whole picture.
function engagement($contractNumber, $partnerId = 0) {
    $no = trim((string)$contractNumber);
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $one = function ($sql, $a = []) { try { return ops_one($sql, $a); } catch (Throwable $e) { return null; } };
    if ($no === '') return ['contract_number' => '', 'contract' => null, 'members' => [], 'rollup' => engagement_empty_rollup()];

    $contract = $one("SELECT * FROM partner_contracts WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$no]);

    // Quotations may match by contract_number or (older rows) by contract_id.
    $cid = (int)($contract['id'] ?? 0);
    $quotes = $all(
        "SELECT id, quote_no, rev, status FROM quotations
          WHERE (contract_number<>'' AND contract_number=?)" . ($cid ? " OR contract_id=?" : "") . "
          ORDER BY id", $cid ? [$no, $cid] : [$no]);
    $calls   = $all("SELECT id, call_code, status, op_status FROM calls WHERE contract_number=? ORDER BY id", [$no]);
    $jobs    = $all("SELECT id, job_code, closed_flag FROM jobs WHERE contract_number=? ORDER BY id", [$no]);
    $jobIds  = array_map(fn($j) => (int)$j['id'], $jobs);
    $reports = [];
    if ($jobIds) {
        $ph = implode(',', array_fill(0, count($jobIds), '?'));
        $reports = $all("SELECT id, irn, type_code, status, job_id FROM report_docs
                          WHERE COALESCE(deleted,0)=0 AND job_id IN ($ph) ORDER BY id", $jobIds);
    }
    $invoices = $all("SELECT id, invoice_no, status, total FROM invoices WHERE contract_number=? ORDER BY id", [$no]);

    // Normalise to one member shape (kind/ref/status/url) — the spine as one list.
    $members = [];
    foreach ($quotes as $q)   $members[] = ['kind'=>'QUOTE',   'id'=>(int)$q['id'], 'ref'=>trim((string)($q['quote_no'] ?? '')) . ((int)($q['rev'] ?? 0) ? ' r' . (int)$q['rev'] : ''), 'status'=>strtoupper((string)($q['status'] ?? '')), 'url'=>'/quote?id=' . (int)$q['id']];
    foreach ($calls as $c)    $members[] = ['kind'=>'CALL',    'id'=>(int)$c['id'], 'ref'=>(string)($c['call_code'] ?? ''), 'status'=>strtoupper((string)(($c['op_status'] ?? '') ?: ($c['status'] ?? ''))), 'url'=>'/call?id=' . (int)$c['id']];
    foreach ($jobs as $j)     $members[] = ['kind'=>'JOB',     'id'=>(int)$j['id'], 'ref'=>(string)($j['job_code'] ?? ''), 'status'=>(!empty($j['closed_flag']) ? 'CLOSED' : 'OPEN'), 'url'=>'/job?id=' . (int)$j['id']];
    foreach ($reports as $r)  $members[] = ['kind'=>'REPORT',  'id'=>(int)$r['id'], 'ref'=>trim((string)(($r['irn'] ?? '') ?: $r['type_code'])), 'status'=>strtoupper((string)($r['status'] ?? '')), 'url'=>'/document?id=' . (int)$r['id']];
    foreach ($invoices as $v) $members[] = ['kind'=>'INVOICE', 'id'=>(int)$v['id'], 'ref'=>(string)($v['invoice_no'] ?? ''), 'status'=>strtoupper((string)($v['status'] ?? '')), 'url'=>'/invoice?id=' . (int)$v['id']];

    // Rollup — what is still live, and the billed total.
    $openCalls = 0; foreach ($calls as $c) { $s = strtoupper((string)(($c['op_status'] ?? '') ?: ($c['status'] ?? ''))); if (!in_array($s, ['CLOSED','CANCELLED','COMPLETED'], true)) $openCalls++; }
    $openJobs = 0;  foreach ($jobs as $j) if (empty($j['closed_flag'])) $openJobs++;
    $billed = 0.0;  foreach ($invoices as $v) if (strtoupper((string)($v['status'] ?? '')) !== 'CANCELLED') $billed += (float)($v['total'] ?? 0);
    $rollup = [
        'quotes' => count($quotes), 'calls' => count($calls), 'jobs' => count($jobs),
        'reports' => count($reports), 'invoices' => count($invoices),
        'open_calls' => $openCalls, 'open_jobs' => $openJobs, 'billed' => round($billed, 2),
    ];
    return ['contract_number' => $no, 'contract' => $contract, 'members' => $members, 'rollup' => $rollup];
}

function engagement_empty_rollup() {
    return ['quotes'=>0,'calls'=>0,'jobs'=>0,'reports'=>0,'invoices'=>0,'open_calls'=>0,'open_jobs'=>0,'billed'=>0.0];
}

// ===========================================================================
//  Revamp — the first-class Engagement entity (additive groundwork)
// ---------------------------------------------------------------------------
//  §80 deferred a real Engagement entity and threaded the spine by the
//  contract_number STRING. This introduces the entity WITHOUT abandoning the
//  string: `engagements` is keyed 1:1 to a contract_number, and a nullable
//  engagement_id is stamped onto calls/jobs/quotations/invoices. Everything is
//  additive and DUAL-READ — the string still links everything (engagement()
//  above is unchanged); the id is a stable, FK-able handle the string can't be.
//  The string is never dropped. There is deliberately NO status column, so no
//  new lifecycle is introduced.
// ===========================================================================
function engagement_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS engagements (
        id $pk, engagement_key VARCHAR(120) DEFAULT '', partner_id INT DEFAULT 0,
        title VARCHAR(200) DEFAULT '', opened_at VARCHAR(20) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // One engagement per contract_number. Plain CREATE UNIQUE INDEX (MySQL rejects
    // IF NOT EXISTS on an index) inside a guard, so the retry is harmlessly caught.
    try { db()->exec("CREATE UNIQUE INDEX ux_engagement_key ON engagements (engagement_key)"); } catch (Throwable $e) {}
    if (function_exists('ensure_column')) {
        foreach (['calls', 'jobs', 'quotations', 'invoices'] as $t) ensure_column($t, 'engagement_id', 'INT NULL');
    }
}

// Get-or-create the engagement for a contract_number. Idempotent (unique key).
function engagement_ensure($contractNumber, $partnerId = 0, $title = '') {
    engagement_migrate();
    $key = trim((string)$contractNumber); if ($key === '') return 0;
    $ex = ops_one("SELECT id FROM engagements WHERE engagement_key=?", [$key]);
    if ($ex) return (int)$ex['id'];
    try {
        db()->prepare("INSERT INTO engagements (engagement_key, partner_id, title, opened_at, created_at) VALUES (?,?,?,?,?)")
            ->execute([$key, (int)$partnerId, substr((string)$title, 0, 200), date('Y-m-d'), date('c')]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        // A concurrent insert lost the unique-key race — read the winner back.
        return (int)(ops_val("SELECT id FROM engagements WHERE engagement_key=?", [$key]) ?: 0);
    }
}

function engagement_id_for($contractNumber) {
    engagement_migrate();
    $key = trim((string)$contractNumber); if ($key === '') return 0;
    return (int)(ops_val("SELECT id FROM engagements WHERE engagement_key=?", [$key]) ?: 0);
}

// Dual-read: resolve an engagement_id back to the full spine (via its string key),
// so callers can hold the stable id and still get the same read-view.
function engagement_by_id($id) {
    engagement_migrate();
    $row = ops_one("SELECT * FROM engagements WHERE id=?", [(int)$id]);
    if (!$row) return null;
    return engagement((string)$row['engagement_key'], (int)$row['partner_id']);
}

// Backfill: create an engagement per distinct contract_number and stamp
// engagement_id onto the records that carry that number but have none yet.
// Idempotent and self-guarded — safe to run repeatedly (nightly from cron).
function engagement_backfill($limit = 2000) {
    engagement_migrate();
    $made = 0; $stamped = 0;
    $keys = [];
    foreach (['calls', 'jobs', 'quotations', 'invoices'] as $t) {
        try {
            foreach (ops_all("SELECT DISTINCT contract_number cn FROM $t WHERE COALESCE(contract_number,'')<>'' LIMIT " . max(1, (int)$limit)) ?: [] as $r)
                $keys[trim((string)$r['cn'])] = true;
        } catch (Throwable $e) {}
    }
    foreach (array_keys($keys) as $key) {
        if ($key === '') continue;
        $c = null;
        try { $c = ops_one("SELECT partner_id, title FROM partner_contracts WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$key]); } catch (Throwable $e) {}
        $before = engagement_id_for($key);
        $eid = engagement_ensure($key, (int)($c['partner_id'] ?? 0), (string)($c['title'] ?? ''));
        if (!$eid) continue;
        if (!$before) $made++;
        foreach (['calls', 'jobs', 'quotations', 'invoices'] as $t) {
            try {
                $st = db()->prepare("UPDATE $t SET engagement_id=? WHERE contract_number=? AND (engagement_id IS NULL OR engagement_id=0)");
                $st->execute([$eid, $key]);
                $stamped += $st->rowCount();
            } catch (Throwable $e) {}
        }
    }
    return ['engagements' => $made, 'stamped' => $stamped];
}

/**
 * Reconciliation check (the gate before any reader switches from the
 * contract_number STRING to the engagement_id). For calls/jobs/quotations/
 * invoices it counts, per table:
 *   threaded   — rows carrying a contract_number,
 *   unstamped  — those with no engagement_id yet (backfill not run / new rows),
 *   mismatched — engagement_id set but its engagement_key <> the row's number.
 * in_parity is true only when nothing is unstamped or mismatched. Read-only;
 * every probe is guarded so a DB missing the additive column degrades to 0.
 */
function engagement_parity() {
    engagement_migrate();
    $val = function ($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    $threaded = 0; $unstamped = 0; $mismatched = 0; $by = [];
    foreach (['calls', 'jobs', 'quotations', 'invoices'] as $t) {
        $th = $val("SELECT COUNT(*) FROM $t WHERE COALESCE(contract_number,'')<>''");
        $u  = $val("SELECT COUNT(*) FROM $t WHERE COALESCE(contract_number,'')<>'' AND (engagement_id IS NULL OR engagement_id=0)");
        $m  = $val("SELECT COUNT(*) FROM $t x JOIN engagements e ON e.id=x.engagement_id
                     WHERE COALESCE(x.engagement_id,0)>0 AND e.engagement_key <> COALESCE(x.contract_number,'')");
        $by[$t] = ['threaded' => $th, 'unstamped' => $u, 'mismatched' => $m];
        $threaded += $th; $unstamped += $u; $mismatched += $m;
    }
    return ['threaded' => $threaded, 'unstamped' => $unstamped, 'mismatched' => $mismatched,
            'in_parity' => ($unstamped === 0 && $mismatched === 0), 'by_table' => $by];
}

// A drop-in "Engagement" panel for the contract detail — the whole spine under this contract_number,
// grouped by kind, each row a link into its own module. Read-only; renders nothing without a number.
function engagement_render($contractNumber, $partnerId = 0) {
    if (!function_exists('engagement')) return;
    $eng = engagement($contractNumber, $partnerId);
    if ($eng['contract_number'] === '' || !$eng['members']) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $r = $eng['rollup'];
    $order = ['QUOTE','CALL','JOB','REPORT','INVOICE'];
    $label = ['QUOTE'=>'Quotes','CALL'=>ucfirst(Tlp('call')),'JOB'=>'Jobs','REPORT'=>'Reports','INVOICE'=>'Invoices'];
    $tone  = ['CLOSED'=>'p-ok','COMPLETED'=>'p-ok','ISSUED'=>'p-ok','PAID'=>'p-ok','CANCELLED'=>'p-mut','OPEN'=>'p-warn'];
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">The engagement '
       . '<span class="muted" style="font-weight:400;font-size:12px">— everything under ' . $e($eng['contract_number']) . '</span></h3>';
    echo '<p class="muted" style="margin:0 0 8px;font-size:12px">'
       . (int)$r['quotes'] . ' quotes · ' . (int)$r['calls'] . ' ' . $e(Tlp('call')) . ' (' . (int)$r['open_calls'] . ' open) · '
       . (int)$r['jobs'] . ' jobs (' . (int)$r['open_jobs'] . ' open) · ' . (int)$r['reports'] . ' reports · '
       . (int)$r['invoices'] . ' invoices' . ($r['billed'] > 0 ? ' · billed ' . $e(number_format($r['billed'], 2)) : '') . '</p>';
    foreach ($order as $k) {
        $rows = array_values(array_filter($eng['members'], fn($m) => $m['kind'] === $k));
        if (!$rows) continue;
        echo '<div style="margin-bottom:6px"><span class="muted" style="font-size:11.5px;display:inline-block;min-width:70px">' . $e($label[$k]) . '</span> ';
        foreach ($rows as $m) {
            echo '<a class="pill ' . ($tone[$m['status']] ?? 'p-mut') . '" href="' . $e($m['url']) . '">'
               . $e($m['ref'] !== '' ? $m['ref'] : ('#' . $m['id']))
               . ($m['status'] !== '' ? ' · ' . $e(ucfirst(strtolower($m['status']))) : '') . '</a> ';
        }
        echo '</div>';
    }
    echo '</div>';
}
