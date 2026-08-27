<?php
// ===========================================================================
//  Slice P4 — the Billable Event ledger
// ---------------------------------------------------------------------------
//  The operational→commercial bridge. EXAACT already knows when work is DONE
//  (a job closes, a report issues) and it already has a books ledger of money
//  (invoices/receipts/credit notes). What it lacked was the thing IN BETWEEN:
//  a persisted record that an approved operational occurrence is a billable
//  candidate — so operational work never disappears before it reaches billing.
//
//  Design (from docs/revamp/03-target-architecture.md §4, non-destructive):
//   * Additive only. One new table, keyed idempotently to the source occurrence.
//   * The books ledger stays the single money truth. A billable event is a
//     candidate; once its source is invoiced it is reconciled to BILLED and
//     carries the invoice link — it never invents or double-counts money.
//   * Populated by an idempotent SYNC pass over already-approved records
//     (closed, not-yet-invoiced billable jobs today). Inline hooks at
//     job-close / report-issue are a later step (P4b); the sync makes the
//     ledger correct without touching those critical write paths yet.
//   * D1: the "approve" step reuses the existing finance.reconcile permission;
//     no new permission is introduced.
// ===========================================================================

// The lifecycle (recorded in docs/03-object-lifecycles.md).
const BILLABLE_STATUS = [
    'PENDING'   => 'Pending review',
    'APPROVED'  => 'Approved to bill',
    'BILLED'    => 'Billed',
    'CANCELLED' => 'Cancelled',
    'DISPUTED'  => 'Disputed',
];

// Where a billable event came from.
const BILLABLE_SOURCES = [
    'JOB_CLOSED'         => 'Closed job',
    'TIMESHEET_APPROVED' => 'Approved deputation timesheet',
    'PLACEMENT_FEE'      => 'Confirmed placement fee',
];

function billable_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS billable_events (
        id $pk,
        source_module VARCHAR(24) DEFAULT '', source_kind VARCHAR(30) DEFAULT '', source_id INT DEFAULT 0,
        party_id INT DEFAULT 0, contract_number VARCHAR(120) DEFAULT '',
        office_id INT DEFAULT 0, sbu VARCHAR(40) DEFAULT '',
        service_type VARCHAR(80) DEFAULT '', qty REAL DEFAULT 0, unit VARCHAR(24) DEFAULT '',
        rate REAL DEFAULT 0, amount REAL DEFAULT 0, calc_rule VARCHAR(120) DEFAULT '',
        status VARCHAR(16) DEFAULT 'PENDING', status_reason VARCHAR(400) DEFAULT '',
        invoice_id INT DEFAULT 0, invoice_line_id INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '', created_by VARCHAR(150) DEFAULT '',
        approved_at VARCHAR(30) DEFAULT '', approved_by VARCHAR(150) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    // One event per operational occurrence. Plain CREATE UNIQUE INDEX (no
    // IF NOT EXISTS, which MySQL rejects) inside a guard, so it is created once
    // and the retry is harmlessly caught. The upsert also guards in code.
    try { db()->exec("CREATE UNIQUE INDEX ux_billable_source ON billable_events (source_module, source_kind, source_id)"); } catch (Throwable $e) {}
    // P4c — the invoice reference for an event billed by finance's attestation
    // (non-job sources have no auto invoice linkage). Additive for existing DBs.
    if (function_exists('ensure_column')) ensure_column('billable_events', 'bill_ref', "VARCHAR(80) DEFAULT ''");
}

// ---- Access (reuses finance rights — no new permission, D1) ----------------
function billable_can()        { return function_exists('can') && (can('finance.reconcile') || can('data.credit') || is_master()); }
function billable_can_manage() { return function_exists('can') && (can('finance.reconcile') || is_master()); }
function billable_actor()      { return function_exists('user_name') ? user_name(current_user()) : 'system'; }

// Office-scope clause (fail-closed, mirrors finevent/lists).
function billable_scope($col = 'office_id') {
    $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($scope === 'ALL' || !is_array($scope) || !$scope) return ['1', []];
    return [$col . ' IN (' . implode(',', array_map('intval', $scope)) . ')', []];
}

// ---- Lifecycle transitions -------------------------------------------------
// Manual transitions only. BILLED is NEVER a manual step — it is set solely by
// reconciliation (billable_events_sync) when the source is actually invoiced, so
// the ledger can never claim something is billed without an invoice behind it.
function billable_allowed_next($from) {
    return [
        'PENDING'   => ['APPROVED', 'CANCELLED'],
        'APPROVED'  => ['DISPUTED', 'CANCELLED'],
        'DISPUTED'  => ['APPROVED', 'CANCELLED'],
        'BILLED'    => [],
        'CANCELLED' => [],
    ][$from] ?? [];
}
function billable_can_transition($from, $to) { return in_array($to, billable_allowed_next($from), true); }

function billable_set_status($id, $to, $reason = '') {
    billable_migrate();
    $id = (int)$id;
    if (!isset(BILLABLE_STATUS[$to])) return false;
    $e = ops_one("SELECT * FROM billable_events WHERE id=?", [$id]);
    if (!$e) return false;
    $from = ($e['status'] ?? '') ?: 'PENDING';
    if (!billable_can_transition($from, $to)) return false;
    if ($to === 'APPROVED') {
        db()->prepare("UPDATE billable_events SET status=?, status_reason=?, approved_at=?, approved_by=?, updated_at=? WHERE id=?")
            ->execute([$to, (string)$reason, date('c'), billable_actor(), date('c'), $id]);
    } else {
        db()->prepare("UPDATE billable_events SET status=?, status_reason=?, updated_at=? WHERE id=?")
            ->execute([$to, (string)$reason, date('c'), $id]);
    }
    return true;
}

// P4c — the attested "billed" path for events with no automatic invoice linkage
// (timesheet, placement). Finance records the real invoice number they raised, so
// the ledger still never claims BILLED without an invoice behind it — the
// attestation IS the evidence. Only an APPROVED event can be billed this way.
function billable_mark_billed($id, $ref) {
    billable_migrate();
    $id = (int)$id; $ref = trim((string)$ref);
    if (!$id || $ref === '') return false;
    $e = ops_one("SELECT * FROM billable_events WHERE id=?", [$id]);
    if (!$e || ($e['status'] ?? '') !== 'APPROVED') return false;
    db()->prepare("UPDATE billable_events SET status='BILLED', bill_ref=?, updated_at=? WHERE id=?")
        ->execute([substr($ref, 0, 80), date('c'), $id]);
    return true;
}

// ---- Idempotent upsert (the derivation primitive) --------------------------
// One row per (source_module, source_kind, source_id). Re-running only refreshes
// the derived/descriptive fields WHILE the event is still PENDING — a human
// decision (APPROVED/BILLED/CANCELLED/DISPUTED) is never overwritten.
function billable_event_upsert($module, $kind, $sourceId, array $d) {
    billable_migrate();
    $module = (string)$module; $kind = (string)$kind; $sourceId = (int)$sourceId;
    if ($module === '' || $kind === '' || !$sourceId) return 0;
    $existing = ops_one("SELECT * FROM billable_events WHERE source_module=? AND source_kind=? AND source_id=?", [$module, $kind, $sourceId]);
    if ($existing) {
        if (($existing['status'] ?? 'PENDING') === 'PENDING') {
            db()->prepare("UPDATE billable_events SET party_id=?, contract_number=?, office_id=?, sbu=?, service_type=?, qty=?, unit=?, rate=?, amount=?, calc_rule=?, updated_at=? WHERE id=?")
                ->execute([(int)($d['party_id'] ?? 0), (string)($d['contract_number'] ?? ''), (int)($d['office_id'] ?? 0), (string)($d['sbu'] ?? ''),
                           (string)($d['service_type'] ?? ''), (float)($d['qty'] ?? 0), (string)($d['unit'] ?? ''), (float)($d['rate'] ?? 0),
                           (float)($d['amount'] ?? 0), (string)($d['calc_rule'] ?? ''), date('c'), (int)$existing['id']]);
        }
        return (int)$existing['id'];
    }
    db()->prepare("INSERT INTO billable_events
        (source_module,source_kind,source_id,party_id,contract_number,office_id,sbu,service_type,qty,unit,rate,amount,calc_rule,status,created_at,created_by,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'PENDING', ?,?,?)")
        ->execute([$module, $kind, $sourceId, (int)($d['party_id'] ?? 0), (string)($d['contract_number'] ?? ''), (int)($d['office_id'] ?? 0),
                   (string)($d['sbu'] ?? ''), (string)($d['service_type'] ?? ''), (float)($d['qty'] ?? 0), (string)($d['unit'] ?? ''),
                   (float)($d['rate'] ?? 0), (float)($d['amount'] ?? 0), (string)($d['calc_rule'] ?? ''),
                   date('c'), billable_actor(), date('c')]);
    return (int)db()->lastInsertId();
}

// ---- Inline hook (P4b) — create the candidate the moment a job closes ------
// The field map for a JOB_CLOSED event, read from the job + its call (same shape
// the sync derives, so the hook and the sync produce identical rows).
function billable_job_fields($jobId) {
    $j = ops_one("SELECT j.id, j.executing_office_id, j.sbu, j.inspection_type, j.invoice_value,
                         c.client_id, c.contract_number, c.billable_value, c.billable_rate, c.billable_qty
                  FROM jobs j LEFT JOIN calls c ON c.id = j.call_id WHERE j.id=?", [(int)$jobId]);
    if (!$j) return null;
    $amount = (float)($j['billable_value'] ?? 0);
    if ($amount <= 0 && (float)($j['invoice_value'] ?? 0) > 0) $amount = (float)$j['invoice_value'];
    return [
        'party_id'        => (int)($j['client_id'] ?? 0),
        'contract_number' => (string)($j['contract_number'] ?? ''),
        'office_id'       => (int)($j['executing_office_id'] ?? 0),
        'sbu'             => (string)($j['sbu'] ?? ''),
        'service_type'    => (string)($j['inspection_type'] ?? ''),
        'qty'             => (float)($j['billable_qty'] ?? 0),
        'rate'            => (float)($j['billable_rate'] ?? 0),
        'amount'          => $amount,
        'calc_rule'       => 'call.billable_value',
    ];
}

// Called from the job-close handler. Idempotent, and DELIBERATELY swallows every
// error: queuing a billable candidate must never affect whether a job can close.
function billable_on_job_closed($jobId) {
    try {
        billable_migrate();
        $jobId = (int)$jobId; if (!$jobId) return 0;
        // Nothing to queue if it is already invoiced.
        if (function_exists('books_job_invoiced') && books_job_invoiced($jobId)) return 0;
        $f = billable_job_fields($jobId);
        if ($f === null) return 0;
        return billable_event_upsert('job', 'JOB_CLOSED', $jobId, $f);
    } catch (Throwable $e) { return 0; }
}

// P4c — inline hook: an APPROVED deputation timesheet is the manpower billable
// occurrence. Amount is left advisory (0) — the rate is applied when finance
// invoices/attests — but the qty (man-days) and client are captured so the work
// cannot vanish before billing. Self-guarded; never throws to the caller.
function billable_on_timesheet_approved($approvalId) {
    try {
        billable_migrate();
        $approvalId = (int)$approvalId; if (!$approvalId) return 0;
        $a = ops_one("SELECT * FROM dep_att_approval WHERE id=?", [$approvalId]);
        if (!$a || ($a['status'] ?? '') !== 'APPROVED') return 0;
        $contract = ''; $office = 0; $sbu = '';
        if (!empty($a['job_id'])) {
            $j = ops_one("SELECT j.executing_office_id, j.sbu, c.contract_number
                          FROM jobs j LEFT JOIN calls c ON c.id=j.call_id WHERE j.id=?", [(int)$a['job_id']]);
            if ($j) { $contract = (string)($j['contract_number'] ?? ''); $office = (int)($j['executing_office_id'] ?? 0); $sbu = (string)($j['sbu'] ?? ''); }
        }
        $days = (float)($a['billable_days'] ?? 0);
        $qty  = $days > 0 ? $days : (float)($a['billable_hours'] ?? 0);
        $unit = (string)($a['basis'] ?? '') ?: ($days > 0 ? 'MANDAY' : 'HOUR');
        return billable_event_upsert('pdso', 'TIMESHEET_APPROVED', $approvalId, [
            'party_id'        => (int)($a['client_id'] ?? 0),
            'contract_number' => $contract, 'office_id' => $office, 'sbu' => $sbu,
            'service_type'    => 'DEPUTATION', 'qty' => $qty, 'unit' => $unit,
            'rate'            => 0, 'amount' => 0, 'calc_rule' => 'timesheet.approved (priced at invoice)',
        ]);
    } catch (Throwable $e) { return 0; }
}

// ---- The sync pass (derive + reconcile) ------------------------------------
// 1. Derive a PENDING event for every closed, not-yet-invoiced billable job.
// 2. Reconcile: any event whose source job is now invoiced → BILLED + linkage,
//    with the amount taken from the invoice (the books ledger wins on money).
function billable_events_sync($limit = 500) {
    billable_migrate();
    $created = 0; $billed = 0;

    if (function_exists('books_billable_jobs')) {
        foreach (books_billable_jobs(0, $limit) as $j) {
            $amount = (float)($j['billable_value'] ?? 0);
            if ($amount <= 0 && (float)($j['invoice_value'] ?? 0) > 0) $amount = (float)$j['invoice_value'];
            $id = billable_event_upsert('job', 'JOB_CLOSED', (int)$j['id'], [
                'party_id'        => (int)($j['client_id'] ?? 0),
                'contract_number' => (string)($j['contract_number'] ?? ''),
                'office_id'       => (int)($j['executing_office_id'] ?? 0),
                'sbu'             => (string)($j['sbu'] ?? ''),
                'service_type'    => (string)($j['inspection_type'] ?? ''),
                'qty'             => (float)($j['billable_qty'] ?? 0),
                'rate'            => (float)($j['billable_rate'] ?? 0),
                'amount'          => $amount,
                'calc_rule'       => 'call.billable_value',
            ]);
            if ($id) $created++;
        }
    }

    // Placement fees — a confirmed (payable) one-time recruitment fee for a hired
    // candidate is a billable occurrence with a real amount. WAIVED/PROVISIONAL
    // are not billable yet, so only CONFIRMED is derived.
    try {
        foreach (ops_all("SELECT i.id, i.placement_fee,
                                 (SELECT c.client_id FROM candidates c WHERE c.inspector_id=i.id AND COALESCE(c.client_id,0)>0 ORDER BY c.id DESC LIMIT 1) client_id
                          FROM inspectors i
                          WHERE COALESCE(i.fee_status,'')='CONFIRMED' AND COALESCE(i.placement_fee,0) > 0") ?: [] as $r) {
            $id = billable_event_upsert('recruit', 'PLACEMENT_FEE', (int)$r['id'], [
                'party_id'     => (int)($r['client_id'] ?? 0),
                'service_type' => 'PLACEMENT', 'qty' => 1, 'unit' => 'placement',
                'amount'       => (float)$r['placement_fee'], 'calc_rule' => 'inspector.placement_fee',
            ]);
            if ($id) $created++;
        }
    } catch (Throwable $e) {}

    if (function_exists('books_invoices_for_job')) {
        foreach (ops_all("SELECT * FROM billable_events WHERE source_module='job' AND status IN ('PENDING','APPROVED')") ?: [] as $e) {
            $inv = books_invoices_for_job((int)$e['source_id']);
            if ($inv) {
                $i = $inv[0];
                db()->prepare("UPDATE billable_events SET status='BILLED', invoice_id=?, amount=?, updated_at=? WHERE id=?")
                    ->execute([(int)$i['id'], (float)($i['total'] ?? $e['amount']), date('c'), (int)$e['id']]);
                $billed++;
            }
        }
    }
    return ['created' => $created, 'billed' => $billed];
}

// ---- Read models -----------------------------------------------------------
function billable_rollup() {
    billable_migrate();
    [$w, $a] = billable_scope();
    $t = ['pending' => 0, 'approved' => 0, 'billed' => 0, 'cancelled' => 0, 'disputed' => 0,
          'pending_amt' => 0.0, 'approved_amt' => 0.0, 'billed_amt' => 0.0, 'disputed_amt' => 0.0, 'cancelled_amt' => 0.0];
    try {
        foreach (ops_all("SELECT status, COUNT(*) n, COALESCE(SUM(amount),0) amt FROM billable_events WHERE $w GROUP BY status", $a) ?: [] as $r) {
            $k = strtolower((string)$r['status']);
            if (isset($t[$k])) { $t[$k] = (int)$r['n']; $t[$k . '_amt'] = (float)$r['amt']; }
        }
    } catch (Throwable $e) {}
    $t['unbilled_amt'] = round($t['pending_amt'] + $t['approved_amt'], 2);   // approved-or-pending, not yet invoiced
    return $t;
}

function billable_list(array $f = [], $limit = 300) {
    billable_migrate();
    [$w, $a] = billable_scope('be.office_id');
    if (!empty($f['status']) && isset(BILLABLE_STATUS[$f['status']])) { $w .= " AND be.status=?"; $a[] = $f['status']; }
    if (!empty($f['party_id'])) { $w .= " AND be.party_id=?"; $a[] = (int)$f['party_id']; }
    try {
        return ops_all("SELECT be.*, COALESCE(bp.display_name, bp.legal_name) party_name
                        FROM billable_events be LEFT JOIN business_partners bp ON bp.id = be.party_id
                        WHERE $w
                        ORDER BY (be.status='PENDING') DESC, (be.status='APPROVED') DESC, be.id DESC
                        LIMIT " . max(1, (int)$limit), $a) ?: [];
    } catch (Throwable $e) { return []; }
}

// Small counts for a nav badge (unbilled candidates awaiting review).
function billable_pending_count() {
    billable_migrate();
    [$w, $a] = billable_scope();
    try { return (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE status='PENDING' AND $w", $a); }
    catch (Throwable $e) { return 0; }
}

// Per-client unbilled figure for Customer-360: pending + approved candidates not
// yet invoiced. Read-only; honours office scope.
function billable_party_rollup($partyId) {
    billable_migrate();
    $partyId = (int)$partyId; if (!$partyId) return ['pending' => 0, 'approved' => 0, 'unbilled_amt' => 0.0];
    [$w, $a] = billable_scope();
    $t = ['pending' => 0, 'approved' => 0, 'unbilled_amt' => 0.0];
    try {
        foreach (ops_all("SELECT status, COUNT(*) n, COALESCE(SUM(amount),0) amt
                          FROM billable_events WHERE party_id=? AND status IN ('PENDING','APPROVED') AND $w
                          GROUP BY status", array_merge([$partyId], $a)) ?: [] as $r) {
            $k = strtolower((string)$r['status']);
            if (isset($t[$k])) $t[$k] = (int)$r['n'];
            $t['unbilled_amt'] += (float)$r['amt'];
        }
    } catch (Throwable $e) {}
    $t['unbilled_amt'] = round($t['unbilled_amt'], 2);
    return $t;
}

// ---- Screen ----------------------------------------------------------------
function ops_billable($route, $method) {
    ops_require(billable_can(), 'You cannot open the billable events board.');

    if ($route === 'billable-sync' && $method === 'POST') {
        ops_require(billable_can_manage(), 'You cannot run the billable sync.');
        $r = billable_events_sync();
        flash('Billable events synced — ' . $r['created'] . ' derived, ' . $r['billed'] . ' reconciled to invoices.');
        redirect('/billable-events');
    }
    if ($route === 'billable-bill' && $method === 'POST') {
        ops_require(billable_can_manage(), 'You cannot bill a billable event.');
        $id = (int)($_POST['id'] ?? 0);
        $ref = trim((string)($_POST['invoice_ref'] ?? ''));
        if ($ref === '') { flash('Enter the invoice number this was billed on.', 'error'); redirect('/billable-events'); }
        if (billable_mark_billed($id, $ref)) flash('Billable event marked billed against ' . $ref . '.');
        else flash('Only an approved event can be marked billed.', 'error');
        redirect('/billable-events');
    }
    if (in_array($route, ['billable-approve', 'billable-cancel', 'billable-dispute'], true) && $method === 'POST') {
        ops_require(billable_can_manage(), 'You cannot change a billable event.');
        $id = (int)($_POST['id'] ?? 0);
        $to = ['billable-approve' => 'APPROVED', 'billable-cancel' => 'CANCELLED', 'billable-dispute' => 'DISPUTED'][$route];
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (($to === 'CANCELLED' || $to === 'DISPUTED') && $reason === '') {
            flash('A reason is required to ' . ($to === 'CANCELLED' ? 'cancel' : 'dispute') . ' a billable event.', 'error');
            redirect('/billable-events');
        }
        if (billable_set_status($id, $to, $reason)) flash('Billable event ' . strtolower(BILLABLE_STATUS[$to]) . '.');
        else flash('That change is not allowed from the current status.', 'error');
        redirect('/billable-events');
    }

    $status = (string)($_GET['status'] ?? '');
    $party  = (int)($_GET['party'] ?? 0);
    $filter = [];
    if ($status !== '') $filter['status'] = $status;
    if ($party) $filter['party_id'] = $party;
    view('ops/billable_events', [
        'rows'      => billable_list($filter),
        'roll'      => billable_rollup(),
        'statuses'  => BILLABLE_STATUS,
        'status'    => $status,
        'canManage' => billable_can_manage(),
    ]);
    return true;
}
