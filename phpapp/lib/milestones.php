<?php
// ===========================================================================
//  Billing by milestones (owner, 12 Nov 2026 — "Milestone billing — yes, go
//  ahead as proposed"; test book Profile F, construction / EPC)
// ---------------------------------------------------------------------------
//  The proposal the owner approved:
//    1. The schedule is set on the QUOTATION ("10% advance, 40% on design
//       approval, 40% on delivery, 10% after warranty") and carried to the
//       CONTRACT when the quotation is won.
//    2. Each milestone is PLANNED → ACHIEVED (someone confirms it is reached,
//       says what proves it, and may attach the proof). Nothing is billed before
//       it is confirmed.
//    3. The contract's coordinator or a manager marks it reached — the people
//       who may close a job (`ops.job.close`: Coordinator, Branch Manager,
//       Operation Manager; a master). Finance bills it, as today. No new
//       permission.
//    4. Retention: a percentage of each milestone bill is held back by the
//       customer and becomes payable when a chosen milestone is reached (e.g.
//       after the warranty period).
//
//  Built ON the billing-unit ledger (lib/billunits.php), never beside it: a
//  reached milestone is ONE billing unit (key MSC|<contract>|<milestone>), due
//  from the day it was reached. The ledger already guarantees a unit is billed
//  exactly once, only through a real invoice line, and that cancelling the
//  invoice returns it to "to be billed" — so milestones inherit all of that.
//
//  Amounts are a share of the price BEFORE GST (the quotation's subtotal); GST
//  is added on each invoice as for any other line.
//
//  Retention and GST: retention is a deferred PAYMENT, not a discount. The
//  invoice carries the full milestone value and its GST (the usual Indian
//  practice — retention money forms part of the value of supply); the retained
//  part is shown on the invoice as "payable on <milestone>" and Receivables
//  does not count it as due, or overdue, until that milestone is reached.
//  The owner is asked to confirm this treatment with the company's CA.
// ===========================================================================

const MS_STATUS = ['PLANNED' => 'Planned', 'ACHIEVED' => 'Reached'];

function ms_migrate() {
    static $doneAt = -1;
    if (function_exists('db_epoch')) { if ($doneAt === db_epoch()) return; $doneAt = db_epoch(); }
    elseif ($doneAt !== -1) return; else $doneAt = 0;
    // Milestones ride on the billing-unit ledger and the invoice register:
    // make sure both exist first, on a company that has never billed yet.
    if (function_exists('billunit_migrate')) billunit_migrate();
    elseif (function_exists('books_migrate')) books_migrate();
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS billing_milestones (
        id $pk, quote_id INT NULL, contract_id INT NULL, seq INT DEFAULT 0,
        title VARCHAR(200) DEFAULT '', pct DECIMAL(9,4) DEFAULT 0,
        releases_retention INT DEFAULT 0,
        status VARCHAR(12) DEFAULT 'PLANNED',
        achieved_on VARCHAR(10) DEFAULT '', achieved_by VARCHAR(150) DEFAULT '', achieved_by_id INT NULL,
        achieved_at VARCHAR(30) DEFAULT '', achieved_note VARCHAR(600) DEFAULT '',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        billable_event_id INT NULL,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_ms_quote ON billing_milestones (quote_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_ms_contract ON billing_milestones (contract_id)"); } catch (Throwable $e) {}
    if (function_exists('ensure_column')) {
        foreach (['quotations', 'partner_contracts'] as $t) {
            ensure_column($t, 'ms_retention_pct', 'DECIMAL(7,3) DEFAULT 0');
        }
        // The price before GST the contract's milestones are a share of — the
        // accepted quotation's subtotal, or typed for a contract with no quotation.
        ensure_column('partner_contracts', 'ms_base', 'DECIMAL(16,2) NULL');
    }
}

// ---------------------------------------------------------------------------
//  Reading a schedule
// ---------------------------------------------------------------------------
function ms_rows_quote($qid) {
    ms_migrate();
    return ops_all("SELECT id, quote_id, contract_id, seq, title, pct, releases_retention, status FROM billing_milestones WHERE quote_id=? AND contract_id IS NULL ORDER BY seq, id", [(int)$qid]) ?: [];
}
function ms_rows_contract($cid) {
    ms_migrate();
    return ops_all("SELECT id, quote_id, contract_id, seq, title, pct, releases_retention, status, achieved_on, achieved_by, achieved_at,
                           achieved_note, file_name, billable_event_id
                    FROM billing_milestones WHERE contract_id=? ORDER BY seq, id", [(int)$cid]) ?: [];
}
// Each row with its amount: pct × base, the last row taking the rounding so the
// schedule always adds up to the base exactly.
function ms_with_amounts(array $rows, $base) {
    $base = round((float)$base, 2); $sum = 0.0; $n = count($rows);
    $totPct = array_sum(array_map(fn($r) => (float)$r['pct'], $rows));
    foreach ($rows as $i => $r) {
        $a = round($base * (float)$r['pct'] / 100, 2);
        if ($i === $n - 1 && abs($totPct - 100) < 0.001) $a = round($base - $sum, 2);
        $rows[$i]['amount'] = $a; $sum += $a;
    }
    return $rows;
}
function ms_quote_base(array $q) { return round((float)($q['subtotal'] ?? 0), 2); }
function ms_contract_base(array $c) {
    if (($c['ms_base'] ?? null) !== null && $c['ms_base'] !== '') return round((float)$c['ms_base'], 2);
    $qid = function_exists('contract_quote_id') ? contract_quote_id($c) : (int)($c['quotation_id'] ?? 0);
    if ($qid) return round((float)ops_val("SELECT subtotal FROM quotations WHERE id=?", [$qid]), 2);
    return round((float)($c['value'] ?? 0), 2);
}

// ---------------------------------------------------------------------------
//  Checking and saving a schedule
// ---------------------------------------------------------------------------
// From a posted form: ms_title[], ms_pct[], ms_release (row index), ms_retention.
// Returns [rows, retentionPct]. Blank rows are dropped.
function ms_from_post(array $p) {
    $rows = []; $titles = (array)($p['ms_title'] ?? []); $pcts = (array)($p['ms_pct'] ?? []);
    $rel = (string)($p['ms_release'] ?? '');
    foreach ($titles as $i => $t) {
        $t = trim(preg_replace('/\s+/', ' ', (string)$t)); $pc = trim((string)($pcts[$i] ?? ''));
        if ($t === '' && $pc === '') continue;
        $rows[] = ['title' => mb_substr($t, 0, 200), 'pct' => $pc === '' ? 0.0 : round((float)$pc, 4),
                   'releases_retention' => ($rel !== '' && (string)$i === $rel) ? 1 : 0, '_idx' => (string)$i];
    }
    $ret = trim((string)($p['ms_retention'] ?? ''));
    return [$rows, $ret === '' ? 0.0 : round((float)$ret, 3)];
}
// '' when the schedule is sound, else what is wrong — said so a person can fix it.
// $fixed: milestones already reached (contract only), counted in the 100%.
function ms_problem(array $rows, $retention, array $fixed = []) {
    if (!$rows && !$fixed) return '';                       // no schedule: billing as before
    foreach ($rows as $r) {
        if ($r['title'] === '') return 'Every milestone needs a name — what has to happen before it is billed.';
        if ($r['pct'] <= 0) return '"' . $r['title'] . '" needs a share above 0%.';
    }
    $tot = array_sum(array_map(fn($r) => (float)$r['pct'], array_merge($fixed, $rows)));
    if (abs($tot - 100) > 0.001)
        return 'The milestones add up to ' . rtrim(rtrim(number_format($tot, 3, '.', ''), '0'), '.') . '% — they must add up to 100% of the price.';
    if ($retention < 0 || $retention >= 100) return 'Retention must be at least 0% and below 100%.';
    $rel = count(array_filter(array_merge($fixed, $rows), fn($r) => !empty($r['releases_retention'])));
    if ($retention > 0 && $rel !== 1) return 'With retention, choose the one milestone that releases it (for example "After the warranty period").';
    if ($retention <= 0 && $rel > 0) return 'A milestone is marked to release retention, but no retention % is set.';
    return '';
}
function ms_save_quote($qid, array $rows, $retention) {
    ms_migrate();
    $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM billing_milestones WHERE quote_id=? AND contract_id IS NULL")->execute([(int)$qid]);
        $ins = $pdo->prepare("INSERT INTO billing_milestones (quote_id, seq, title, pct, releases_retention, status, created_at, updated_at) VALUES (?,?,?,?,?, 'PLANNED', ?,?)");
        foreach (array_values($rows) as $i => $r) $ins->execute([(int)$qid, $i + 1, $r['title'], $r['pct'], (int)$r['releases_retention'], date('c'), date('c')]);
        $pdo->prepare("UPDATE quotations SET ms_retention_pct=? WHERE id=?")->execute([$rows ? (float)$retention : 0, (int)$qid]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own) $pdo->rollBack(); throw $e; }
}
// A revision carries the schedule, like its lines and sites.
function ms_copy_quote($fromQid, $toQid) {
    ms_migrate();
    $rows = ms_rows_quote($fromQid);
    if (!$rows) return;
    ms_save_quote($toQid, $rows, (float)ops_val("SELECT ms_retention_pct FROM quotations WHERE id=?", [(int)$fromQid]));
}
// The schedule as one comparable string — part of the quotation's commercial
// fingerprint, so changing it cancels an approval like changing a price.
function ms_print_quote($qid) {
    $rows = ms_rows_quote($qid);
    if (!$rows) return '';
    return json_encode([array_map(fn($r) => [(string)$r['title'], round((float)$r['pct'], 4), (int)$r['releases_retention']], $rows),
                        round((float)ops_val("SELECT ms_retention_pct FROM quotations WHERE id=?", [(int)$qid]), 3)]);
}

// The contract takes the won quotation's schedule the first time it is looked
// at (idempotent: a contract that already has milestones is left alone).
function ms_contract_ensure(array $c) {
    ms_migrate();
    $cid = (int)$c['id'];
    if ((int)ops_val("SELECT COUNT(*) FROM billing_milestones WHERE contract_id=?", [$cid]) > 0) return false;
    $qid = function_exists('contract_quote_id') ? contract_quote_id($c) : (int)($c['quotation_id'] ?? 0);
    if (!$qid) return false;
    $rows = ms_rows_quote($qid);
    if (!$rows) return false;
    $q = ops_one("SELECT subtotal, ms_retention_pct FROM quotations WHERE id=?", [$qid]);
    $pdo = db();
    $ins = $pdo->prepare("INSERT INTO billing_milestones (quote_id, contract_id, seq, title, pct, releases_retention, status, created_at, updated_at) VALUES (?,?,?,?,?,?, 'PLANNED', ?,?)");
    foreach ($rows as $r) $ins->execute([$qid, $cid, (int)$r['seq'], $r['title'], (float)$r['pct'], (int)$r['releases_retention'], date('c'), date('c')]);
    $pdo->prepare("UPDATE partner_contracts SET ms_retention_pct=?, ms_base=COALESCE(ms_base, ?) WHERE id=?")
        ->execute([(float)($q['ms_retention_pct'] ?? 0), round((float)($q['subtotal'] ?? 0), 2), $cid]);
    if (function_exists('act_log')) try { act_log('CONTRACT', $cid, 'SYSTEM', 'Billing milestones taken from the quotation (' . count($rows) . ')', ['auto' => 1]); } catch (Throwable $e) {}
    return true;
}

// Replace a contract's PLANNED milestones (reached ones never change), with its
// retention and base. Used directly on a contract not yet open, and by an
// approved amendment on an open one.
function ms_contract_replace_planned($cid, array $rows, $retention, $base) {
    ms_migrate();
    $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM billing_milestones WHERE contract_id=? AND status='PLANNED'")->execute([(int)$cid]);
        $max = (int)ops_val("SELECT COALESCE(MAX(seq),0) FROM billing_milestones WHERE contract_id=?", [(int)$cid]);
        $ins = $pdo->prepare("INSERT INTO billing_milestones (contract_id, seq, title, pct, releases_retention, status, created_at, updated_at) VALUES (?,?,?,?,?, 'PLANNED', ?,?)");
        foreach (array_values($rows) as $i => $r) $ins->execute([(int)$cid, $max + $i + 1, $r['title'], (float)$r['pct'], (int)$r['releases_retention'], date('c'), date('c')]);
        $pdo->prepare("UPDATE partner_contracts SET ms_retention_pct=?, ms_base=? WHERE id=?")->execute([(float)$retention, round((float)$base, 2), (int)$cid]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own) $pdo->rollBack(); throw $e; }
}
// The schedule as stored on an amendment (old / new values).
function ms_contract_snapshot(array $c) {
    return ['base' => ms_contract_base($c), 'retention' => round((float)($c['ms_retention_pct'] ?? 0), 3),
            'rows' => array_map(fn($r) => ['title' => (string)$r['title'], 'pct' => round((float)$r['pct'], 4), 'releases_retention' => (int)$r['releases_retention'], 'status' => (string)$r['status']],
                                ms_rows_contract((int)$c['id']))];
}
function ms_snapshot_words(array $s) {
    $rows = $s['rows'] ?? [];
    if (!$rows) return 'no milestones';
    $w = implode(', ', array_map(fn($r) => $r['title'] . ' ' . rtrim(rtrim(number_format((float)$r['pct'], 2, '.', ''), '0'), '.') . '%', $rows));
    return $w . ((float)($s['retention'] ?? 0) > 0 ? ' · retention ' . rtrim(rtrim(number_format((float)$s['retention'], 2, '.', ''), '0'), '.') . '%' : '');
}

// Is this contract (also) billed another way — man-day / man-month units, a
// closed-job event, or an invoice line not from a milestone? Billing it by
// milestones as well would charge the customer twice for the same work.
// Returns how many such items there are (0 = none).
function ms_contract_other_billing(array $c) {
    ms_migrate();
    $cno = trim((string)($c['contract_number'] ?? ''));
    if ($cno === '') return 0;
    try {
        $ev = (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE contract_number=? AND status<>'CANCELLED'
                            AND (unit_key IS NULL OR unit_key NOT LIKE 'MSC|%')", [$cno]);
        $ln = (int)ops_val("SELECT COUNT(*) FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id
                            LEFT JOIN billable_events e ON e.id=l.billable_event_id
                            WHERE l.contract_number=? AND COALESCE(i.status,'')<>'CANCELLED'
                            AND (e.id IS NULL OR e.unit_key IS NULL OR e.unit_key NOT LIKE 'MSC|%')", [$cno]);
        return $ev + $ln;
    } catch (Throwable $e) { return 0; }
}

// ---------------------------------------------------------------------------
//  Who may do what (no new permission)
// ---------------------------------------------------------------------------
// The schedule on a quotation: whoever may edit the quotation, while it may be
// edited (never once it has gone to the customer — that is a revision).
function ms_can_edit_quote(array $q) {
    return (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'))
        && !quote_is_locked($q) && in_array((string)$q['status'], ['DRAFT', 'PENDING_APPROVAL', 'REJECTED', 'APPROVED'], true);
}
// The schedule on a contract: whoever registers contracts (as for its billing terms).
function ms_can_edit_contract() {
    // Billing — only where Money (invoicing) is bought, as for the contract's billing terms.
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    return is_master() || can('crm.contract.register');
}
// Marking a milestone reached: the contract's coordinator or a manager — the
// holders of ops.job.close — inside their own branch and business unit.
function ms_can_mark(array $c) {
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    if (is_master()) return true;
    if (!can('ops.job.close')) return false;
    return !function_exists('user_scope_covers') || user_scope_covers(current_user(), $c['branch_id'] ?? null, (string)($c['sbu'] ?? ''));
}
function ms_contract_open(array $c) { return strtoupper((string)($c['open_status'] ?? '') ?: 'OPEN') === 'OPEN'; }

// ---------------------------------------------------------------------------
//  Reached → a billing unit, due that day
// ---------------------------------------------------------------------------
function ms_unit_key($cid, $mid) { return 'MSC|' . (int)$cid . '|' . (int)$mid; }

function ms_mark_reached($mid, $date, $note, $file = null) {
    ms_migrate();
    $m = ops_one("SELECT * FROM billing_milestones WHERE id=?", [(int)$mid]);
    if (!$m || !(int)$m['contract_id']) return 'That milestone no longer exists.';
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$m['contract_id']]);
    if (!$c) return 'Its contract no longer exists.';
    if (!ms_contract_open($c)) return 'The contract is not open yet — it needs its two signatures before work, and billing, can start.';
    if ($m['status'] !== 'PLANNED') return '"' . $m['title'] . '" is already marked reached.';
    $date = trim((string)$date) ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) return 'Give the date it was reached.';
    if ($date > date('Y-m-d')) return 'A milestone cannot be reached in the future — give the date it actually happened.';
    $note = trim((string)$note);
    if (mb_strlen($note) < 5) return 'Say what shows it is reached (for example "Design approved by the client\'s letter of 10 Nov").';
    $bytes = ''; $fname = ''; $mime = '';
    if ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name'])) {
        $bytes = (string)file_get_contents($file['tmp_name']);
        if (($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '') return $why;
        $fname = mb_substr((string)($file['name'] ?? 'proof'), 0, 255); $mime = (string)($file['type'] ?? '');
    }
    $amount = 0.0;
    foreach (ms_with_amounts(ms_rows_contract((int)$c['id']), ms_contract_base($c)) as $r) if ((int)$r['id'] === (int)$m['id']) $amount = (float)$r['amount'];

    $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
        // The one moment that decides it: only a PLANNED row becomes reached, so
        // two people pressing at once reach it once.
        $u = current_user();
        $st = $pdo->prepare("UPDATE billing_milestones SET status='ACHIEVED', achieved_on=?, achieved_by=?, achieved_by_id=?, achieved_at=?, achieved_note=?, updated_at=?"
            . ($bytes !== '' ? ", file_name=?, mime=?, file_data=?" : '') . " WHERE id=? AND status='PLANNED'");
        $args = [$date, user_name($u), (int)($u['id'] ?? 0) ?: null, date('c'), mb_substr($note, 0, 600), date('c')];
        if ($bytes !== '') array_push($args, $fname, $mime, base64_encode($bytes));
        $args[] = (int)$m['id'];
        $st->execute($args);
        if ($st->rowCount() !== 1) { if ($own) $pdo->rollBack(); return '"' . $m['title'] . '" was marked reached by someone else a moment ago.'; }
        $eid = 0;
        if ($amount > 0 && function_exists('billunit_upsert')) {
            $eid = billunit_upsert('MILESTONE', ms_unit_key($c['id'], $m['id']), [
                'party_id' => (int)$c['partner_id'], 'contract_number' => (string)$c['contract_number'],
                'office_id' => (int)($c['branch_id'] ?? 0), 'sbu' => (string)($c['sbu'] ?? ''),
                'qty' => 1, 'unit' => 'milestone', 'rate' => $amount, 'amount' => $amount,
                'calc_rule' => mb_substr($c['contract_number'] . ' — ' . $m['title'] . ' (' . rtrim(rtrim(number_format((float)$m['pct'], 2, '.', ''), '0'), '.') . '%)', 0, 120),
                'evidence' => mb_substr($note, 0, 400), 'period_from' => $date, 'period_to' => $date,
            ]);
            if ($eid) {
                $pdo->prepare("UPDATE billable_events SET due_on=?, due_reason=? WHERE id=? AND status NOT IN ('BILLED','CANCELLED')")
                    ->execute([$date, mb_substr('Milestone reached: ' . $m['title'], 0, 160), $eid]);
                $pdo->prepare("UPDATE billing_milestones SET billable_event_id=? WHERE id=?")->execute([$eid, (int)$m['id']]);
            }
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); return 'It could not be saved: ' . $e->getMessage(); }
    ms_log($c, 'Milestone reached — ' . $m['title'] . ' on ' . fdate($date) . ($amount > 0 ? ', ' . fmoney($amount) . ' (before GST) is ready to bill' : '')
        . (!empty($m['releases_retention']) ? '; the retention held on this contract is now payable' : '') . '. ' . $note);
    return '';
}

// Undo a mistaken "reached" — only while it is on no invoice. The unit stays on
// the ledger (its key is unique) but is no longer due, so it cannot be billed.
function ms_undo($mid, $reason) {
    ms_migrate();
    $m = ops_one("SELECT * FROM billing_milestones WHERE id=?", [(int)$mid]);
    if (!$m || $m['status'] !== 'ACHIEVED') return 'That milestone is not marked reached.';
    $reason = trim((string)$reason);
    if (mb_strlen($reason) < 5) return 'Say why it is being undone.';
    $eid = (int)($m['billable_event_id'] ?? 0);
    if ($eid) {
        $e = ops_one("SELECT status FROM billable_events WHERE id=?", [$eid]);
        if (($e['status'] ?? '') === 'BILLED' || (function_exists('billunit_on_invoice') && billunit_on_invoice($eid)))
            return '"' . $m['title'] . '" is already on an invoice. Cancel that invoice first (with a credit note if it was issued); then it can be undone.';
    }
    if (!empty($m['releases_retention'])) {
        $c0 = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$m['contract_id']]);
        // Retention already collected against this release cannot be "un-released".
        if ($c0 && ms_retention_collected((int)$c0['id']) > 0.5)
            return 'Retention has already been collected against "' . $m['title'] . '" — it cannot be undone.';
    }
    $pdo = db();
    $pdo->prepare("UPDATE billing_milestones SET status='PLANNED', achieved_on='', achieved_by='', achieved_by_id=NULL, achieved_at='', updated_at=? WHERE id=? AND status='ACHIEVED'")
        ->execute([date('c'), (int)$m['id']]);
    if ($eid) $pdo->prepare("UPDATE billable_events SET due_on='', due_reason=?, updated_at=? WHERE id=? AND status NOT IN ('BILLED','CANCELLED')")
        ->execute([mb_substr('Milestone undone: ' . $reason, 0, 160), date('c'), $eid]);
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$m['contract_id']]);
    if ($c) ms_log($c, 'Milestone "' . $m['title'] . '" set back to planned — ' . $reason . '.');
    return '';
}

function ms_log(array $c, $text) {
    if (function_exists('act_log')) try { act_log('CONTRACT', (int)$c['id'], 'SYSTEM', mb_substr($text, 0, 250)); } catch (Throwable $e) {}
    $qid = function_exists('contract_quote_id') ? contract_quote_id($c) : 0;
    if ($qid && function_exists('crm_log_change')) try { crm_log_change($qid, 'Contract ' . $c['contract_number'] . ': ' . $text); } catch (Throwable $e) {}
}

// Where a reached milestone stands with finance, in words.
function ms_bill_state(array $m) {
    if ($m['status'] !== 'ACHIEVED') return ['Planned', 'p-mut', 0, ''];
    $eid = (int)($m['billable_event_id'] ?? 0);
    if (!$eid) return ['Reached', 'p-ok', 0, ''];
    $inv = function_exists('billunit_on_invoice') ? billunit_on_invoice($eid) : 0;
    if ($inv) {
        $row = ops_one("SELECT invoice_no, status FROM invoices WHERE id=?", [$inv]);
        $no = (string)($row['invoice_no'] ?? '') ?: ('#' . $inv);
        return ($row['status'] ?? '') === 'DRAFT' ? ['On a draft invoice', 'p-info', $inv, $no] : ['Billed — ' . $no, 'p-ok', $inv, $no];
    }
    return ['Reached — ready to bill', 'p-warn', 0, ''];
}

// ---------------------------------------------------------------------------
//  Retention
// ---------------------------------------------------------------------------
// For each invoice: the retention it holds and when that becomes payable.
// [invoice_id => ['amount' => x, 'due_on' => 'YYYY-MM-DD'|'', 'pct' => p, 'release' => title]]
function ms_invoice_retention(array $invoiceIds) {
    $ids = array_values(array_filter(array_map('intval', $invoiceIds)));
    if (!$ids) return [];
    ms_migrate();
    $out = [];
    try {
        $rows = ops_all("SELECT l.invoice_id, l.amount, e.unit_key FROM invoice_lines l JOIN billable_events e ON e.id=l.billable_event_id
                         WHERE l.invoice_id IN (" . implode(',', $ids) . ") AND e.unit_key LIKE 'MSC|%'") ?: [];
    } catch (Throwable $e) { return []; }
    $contracts = [];
    foreach ($rows as $r) {
        $cid = (int)(explode('|', (string)$r['unit_key'])[1] ?? 0);
        if (!$cid) continue;
        if (!isset($contracts[$cid])) {
            $c = ops_one("SELECT id, ms_retention_pct FROM partner_contracts WHERE id=?", [$cid]);
            $rel = ops_one("SELECT title, status, achieved_on FROM billing_milestones WHERE contract_id=? AND releases_retention=1 ORDER BY id LIMIT 1", [$cid]);
            $contracts[$cid] = ['pct' => (float)($c['ms_retention_pct'] ?? 0), 'release' => (string)($rel['title'] ?? ''),
                                'due_on' => ($rel && $rel['status'] === 'ACHIEVED') ? (string)$rel['achieved_on'] : ''];
        }
        $k = $contracts[$cid];
        if ($k['pct'] <= 0) continue;
        $iid = (int)$r['invoice_id'];
        $out[$iid] = $out[$iid] ?? ['amount' => 0.0, 'due_on' => $k['due_on'], 'pct' => $k['pct'], 'release' => $k['release']];
        $out[$iid]['amount'] += round((float)$r['amount'] * $k['pct'] / 100, 2);
    }
    foreach ($out as $i => $v) $out[$i]['amount'] = round($v['amount'], 2);
    return $out;
}
// Retention on one invoice that is NOT yet payable (its release not reached), as
// far as it is still outstanding — payments settle the payable part first.
function ms_invoice_retention_not_due($invoiceId, $outstanding, $map = null, $today = null) {
    $map = $map ?? ms_invoice_retention([(int)$invoiceId]);
    $r = $map[(int)$invoiceId] ?? null;
    if (!$r || $r['amount'] <= 0) return 0.0;
    $today = $today ?: date('Y-m-d');
    if ($r['due_on'] !== '' && $r['due_on'] <= $today) return 0.0;
    return round(min((float)$outstanding, (float)$r['amount']), 2);
}
// A contract's retention: held on its invoices, how much of it is still owed,
// and whether it is payable yet.
function ms_contract_retention(array $c) {
    ms_migrate();
    $pct = (float)($c['ms_retention_pct'] ?? 0);
    $rel = ops_one("SELECT title, status, achieved_on FROM billing_milestones WHERE contract_id=? AND releases_retention=1 ORDER BY id LIMIT 1", [(int)$c['id']]);
    $inv = ops_all("SELECT DISTINCT l.invoice_id FROM invoice_lines l JOIN billable_events e ON e.id=l.billable_event_id JOIN invoices i ON i.id=l.invoice_id
                    WHERE e.unit_key LIKE ? AND i.status NOT IN ('DRAFT','CANCELLED')", ['MSC|' . (int)$c['id'] . '|%']) ?: [];
    $map = ms_invoice_retention(array_column($inv, 'invoice_id'));
    $held = 0.0; $owed = 0.0;
    foreach ($map as $iid => $r) {
        $held += $r['amount'];
        $s = function_exists('books_settled') ? books_settled($iid) : ['outstanding' => 0];
        $owed += min((float)$r['amount'], (float)($s['outstanding'] ?? 0));
    }
    return ['pct' => $pct, 'held' => round($held, 2), 'owed' => round($owed, 2),
            'release' => (string)($rel['title'] ?? ''), 'payable' => $rel && $rel['status'] === 'ACHIEVED', 'payable_on' => (string)($rel['achieved_on'] ?? '')];
}
// Retention already received (held minus still owed) — once collected it cannot be un-released.
function ms_retention_collected($cid) {
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$cid]);
    if (!$c) return 0.0;
    $r = ms_contract_retention($c);
    return round($r['held'] - $r['owed'], 2);
}

// Reached milestones not yet on any invoice, in the viewer's branches — Finance's to-do.
function ms_ready_to_bill_count() {
    ms_migrate();
    [$w, $a] = function_exists('billable_scope') ? billable_scope('e.office_id') : ['1', []];
    try {
        $rows = ops_all("SELECT e.id FROM billable_events e WHERE e.unit_key LIKE 'MSC|%' AND e.status IN ('PENDING','APPROVED')
                         AND COALESCE(e.due_on,'')<>'' AND e.due_on<=? AND $w", array_merge([date('Y-m-d')], $a)) ?: [];
    } catch (Throwable $e) { return 0; }
    $n = 0; foreach ($rows as $r) if (!billunit_on_invoice((int)$r['id'])) $n++;
    return $n;
}

// The schedule as printed on the quotation PDF: lines of text.
function ms_pdf_lines(array $q) {
    $rows = ms_rows_quote((int)$q['id']);
    if (!$rows) return [];
    $out = [];
    foreach (ms_with_amounts($rows, ms_quote_base($q)) as $i => $r)
        $out[] = ($i + 1) . '. ' . $r['title'] . ' — ' . rtrim(rtrim(number_format((float)$r['pct'], 2), '0'), '.') . '% — '
               . number_format((float)$r['amount'], 2) . ' plus GST'
               . (!empty($r['releases_retention']) ? ' (retention released on this milestone)' : '');
    $ret = (float)($q['ms_retention_pct'] ?? 0);
    if ($ret > 0) {
        $relT = ''; foreach ($rows as $r) if (!empty($r['releases_retention'])) $relT = $r['title'];
        $out[] = 'Retention: ' . rtrim(rtrim(number_format($ret, 2), '0'), '.') . '% of each milestone bill is held back by you and paid when "' . $relT . '" is reached. GST is charged on the full value of each bill.';
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Handlers
// ---------------------------------------------------------------------------
function ops_milestones($route, $method) {
    ms_migrate();
    if ($route === 'milestone-file') {
        $m = ops_one("SELECT * FROM billing_milestones WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$m || (string)$m['file_data'] === '') { http_response_code(404); view('notfound'); return true; }
        $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$m['contract_id']]);
        // Whoever may open the contract may open the proof of its milestones.
        ops_require($c && (is_master() || can('mod.clients.view') || can('crm.contract.register') || can('finance.reconcile') || can('data.credit')
            || (function_exists('is_coordinator_level') && is_coordinator_level())), 'You cannot open this file.');
        send_uploaded_file(base64_decode((string)$m['file_data']), $m['file_name'] ?: 'proof', $m['mime'] ?: 'application/octet-stream');
        return true;
    }
    if ($method !== 'POST') redirect('/');

    if ($route === 'quote-milestones') {
        $q = crm_quote_get((int)($_POST['quote_id'] ?? 0));
        if (!$q) { http_response_code(404); view('notfound'); return true; }
        ops_require(ms_can_edit_quote($q), 'This ' . Tl('quote') . "'s billing schedule cannot be changed now — once it has gone to the customer, a change is a revision.");
        $back = '/quote?id=' . (int)$q['id'] . '#milestones';
        [$rows, $ret] = !empty($_POST['ms_clear']) ? [[], 0.0] : ms_from_post($_POST);
        if (($why = ms_problem($rows, $ret)) !== '') { flash($why . ' Nothing was saved.', 'error'); redirect($back); }
        $before = crm_quote_commercial_print($q['id']);
        ms_save_quote((int)$q['id'], $rows, $ret);
        $msg = $rows ? 'Billing schedule saved — ' . count($rows) . ' milestone(s).' : 'Billing schedule removed — this ' . Tl('quote') . ' bills as before.';
        if (in_array($q['status'], ['PENDING_APPROVAL', 'APPROVED'], true) && crm_quote_commercial_print($q['id']) !== $before) {
            db()->prepare("DELETE FROM quote_approvals WHERE quote_id=?")->execute([(int)$q['id']]);
            db()->prepare("UPDATE quotations SET status='DRAFT', submitted_at='' WHERE id=?")->execute([(int)$q['id']]);
            $msg .= ' The billing schedule is part of what is approved, so the approval was cancelled — submit it again.';
        }
        crm_log_change((int)$q['id'], $rows ? 'Billing schedule set: ' . ms_snapshot_words(['rows' => $rows, 'retention' => $ret]) : 'Billing schedule removed.');
        flash($msg, strpos($msg, 'cancelled') !== false ? 'warning' : 'success');
        redirect($back);
    }

    if ($route === 'contract-milestones') {
        $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)($_POST['contract_id'] ?? 0)]);
        if (!$c) { http_response_code(404); view('notfound'); return true; }
        ms_contract_ensure($c);
        $back = '/contract?id=' . (int)$c['id'] . '#milestones';
        $act = (string)($_POST['act'] ?? '');
        if ($act === 'reach' || $act === 'undo') {
            ops_require(ms_can_mark($c), 'Only the coordinator or a manager of this contract\'s branch can mark a milestone reached.');
            $err = $act === 'reach'
                ? ms_mark_reached((int)($_POST['milestone_id'] ?? 0), (string)($_POST['achieved_on'] ?? ''), (string)($_POST['note'] ?? ''), $_FILES['proof'] ?? null)
                : ms_undo((int)($_POST['milestone_id'] ?? 0), (string)($_POST['reason'] ?? ''));
            flash($err !== '' ? $err : ($act === 'reach' ? 'Marked reached. Finance sees it under Billing → Due to bill.' : 'Set back to planned — it is no longer due to bill.'), $err !== '' ? 'error' : 'success');
            redirect($back);
        }
        if ($act === 'save') {
            ops_require(ms_can_edit_contract(), 'Only the people who register contracts can change a contract\'s billing schedule.');
            [$rows, $ret] = !empty($_POST['ms_clear']) ? [[], 0.0] : ms_from_post($_POST);
            $fixed = array_values(array_filter(ms_rows_contract((int)$c['id']), fn($r) => $r['status'] === 'ACHIEVED'));
            if ($rows && !ms_rows_contract((int)$c['id']) && ($n = ms_contract_other_billing($c)) > 0) {
                flash('This contract is already billed another way (' . $n . ' man-day / job / invoice item' . ($n === 1 ? '' : 's') . '). Billing it by milestones as well would charge the customer twice — nothing was saved.', 'error');
                redirect($back);
            }
            if (!empty($_POST['ms_clear']) && $fixed) { flash('Milestones already reached cannot be removed.', 'error'); redirect($back); }
            $base = trim((string)($_POST['ms_base'] ?? '')) === '' ? ms_contract_base($c) : round((float)$_POST['ms_base'], 2);
            if ($rows && $base <= 0) { flash('Give the price before GST that the milestones are a share of.', 'error'); redirect($back); }
            if (($why = ms_problem($rows, $ret, $fixed)) !== '') { flash($why . ' Nothing was saved.', 'error'); redirect($back); }
            $new = ['base' => $base, 'retention' => $ret,
                    'rows' => array_map(fn($r) => ['title' => $r['title'], 'pct' => $r['pct'], 'releases_retention' => (int)$r['releases_retention'], 'status' => 'PLANNED'], $rows)];
            $old = ms_contract_snapshot($c);
            $oldPlanned = array_values(array_filter($old['rows'], fn($r) => $r['status'] === 'PLANNED'));
            if ($oldPlanned == $new['rows'] && abs($old['retention'] - $ret) < 0.0001 && abs($old['base'] - $base) < 0.005) { flash('Nothing changed.', 'warning'); redirect($back); }
            if (ms_contract_open($c)) {
                // An open contract's billing schedule changes only as an amendment
                // with two signatures, like its value, dates and billing terms (D-25).
                if (function_exists('contract_pending_amendment') && contract_pending_amendment((int)$c['id'])) { flash('An amendment to this contract is already waiting for its signatures — decide it first.', 'error'); redirect($back); }
                $reason = trim((string)($_POST['amend_reason'] ?? ''));
                if ($reason === '') { flash('Changing the billing schedule of an open contract is an amendment — say why, in "Reason for the change". Nothing was changed.', 'error'); redirect($back); }
                $u = current_user();
                db()->prepare("INSERT INTO contract_amendments (contract_id,status,old_values,new_values,reason,requested_by,requested_by_id,requested_at) VALUES (?, 'PENDING', ?,?,?,?,?,?)")
                    ->execute([(int)$c['id'], json_encode(['milestones' => $old]), json_encode(['milestones' => $new]), mb_substr($reason, 0, 400),
                               user_name($u), (int)($u['id'] ?? 0) ?: null, date('c')]);
                ms_log($c, 'Amendment requested to the billing schedule — ' . $reason . '. ' . ucfirst(contract_rule_sentence(true)) . '.');
                flash('Amendment requested. It needs ' . contract_rule_sentence(true) . '; until then the current schedule stays in force.', 'warning');
                redirect('/contract?id=' . (int)$c['id'] . '#amendments');
            }
            ms_contract_replace_planned((int)$c['id'], $rows, $ret, $base);
            // A contract still awaiting its signatures: an endorsement given for
            // other terms no longer stands.
            if (strtoupper((string)($c['open_status'] ?? '')) === 'PENDING' && trim((string)($c['mgr_endorsed_at'] ?? '')) !== '')
                db()->prepare("UPDATE partner_contracts SET mgr_endorsed_by='', mgr_endorsed_by_id=NULL, mgr_endorsed_at='' WHERE id=?")->execute([(int)$c['id']]);
            ms_log($c, 'Billing schedule set: ' . ms_snapshot_words($new));
            flash('Billing schedule saved.');
            redirect($back);
        }
        redirect($back);
    }
    redirect('/');
}
