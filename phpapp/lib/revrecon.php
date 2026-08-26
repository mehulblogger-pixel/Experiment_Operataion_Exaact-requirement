<?php
// Phase 2 §29 — recognised-revenue reconciliation, READ-ONLY, between the two money truths that
// already coexist (see §80 legacy register). A job carries a legacy single-invoice snapshot
// (jobs.invoice_amount / invoice_raised / payment_received) that MIS, boss_profit and some dashboards
// still read; the books ledger (invoices -> invoice_lines.job_id) is the real bill. These can drift —
// a job re-invoiced, a credit note, a correction booked only in the ledger. This surfaces WHERE they
// disagree so someone can look; it changes NO displayed number and touches no row, which is exactly
// why it needs no §28 sign-off. (Converging the readers onto one engine is §28 and is sign-off-gated.)

// How far apart (in currency units) the two figures may sit before it counts as a divergence. The
// legacy field's tax basis is ambiguous, so we accept a match against EITHER the net or the gross
// ledger total; a divergence means it matches neither.
function revrecon_tolerance() { $t = (float)setting_get('revrecon_tolerance', 1); return $t > 0 ? $t : 1; }

// Reconcile one job. Returns the legacy figure, the ledger net (pre-tax) and gross (incl-tax) totals
// attributed to the job through non-cancelled invoices, which basis the legacy figure matches, and
// whether it diverges from both.
function revrecon_job($jobId) {
    $jobId = (int)$jobId;
    $one = function ($sql, $a = []) { try { return ops_one($sql, $a); } catch (Throwable $e) { return null; } };
    $j = $one("SELECT id, invoice_amount, invoice_raised, payment_received FROM jobs WHERE id=?", [$jobId]);
    $legacy = (float)($j['invoice_amount'] ?? 0);
    // Ledger totals for this job, excluding cancelled invoices.
    $led = $one(
        "SELECT COALESCE(SUM(il.amount),0) net, COALESCE(SUM(il.line_total),0) gross
           FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id
          WHERE il.job_id=? AND COALESCE(i.status,'') <> 'CANCELLED'", [$jobId]);
    $net = (float)($led['net'] ?? 0); $gross = (float)($led['gross'] ?? 0);
    $tol = revrecon_tolerance();
    $matchNet   = abs($legacy - $net)   <= $tol;
    $matchGross = abs($legacy - $gross) <= $tol;
    $basis = $matchGross ? 'gross' : ($matchNet ? 'net' : 'none');
    // Only meaningful once there is something on at least one side.
    $has = ($legacy > 0) || ($net > 0) || ($gross > 0);
    return [
        'job_id' => $jobId, 'legacy' => round($legacy, 2), 'ledger_net' => round($net, 2),
        'ledger_gross' => round($gross, 2), 'basis' => $basis,
        'diverges' => $has && !$matchNet && !$matchGross,
        'legacy_only' => $legacy > 0 && $net == 0 && $gross == 0,
        'ledger_only' => $legacy == 0 && ($net > 0 || $gross > 0),
    ];
}

// The set of jobs whose legacy invoice figure reconciles against neither ledger basis. Only jobs that
// carry a legacy figure or a ledger line are considered. Read-only, capped.
function revrecon_scan($limit = 100) {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    // Candidate jobs: those with a legacy amount, OR those that appear on an invoice line.
    $ids = [];
    foreach ($all("SELECT id FROM jobs WHERE COALESCE(invoice_amount,0) <> 0 OR COALESCE(invoice_raised,0)=1") as $r) $ids[(int)$r['id']] = 1;
    foreach ($all("SELECT DISTINCT job_id FROM invoice_lines WHERE COALESCE(job_id,0) > 0") as $r) $ids[(int)$r['job_id']] = 1;
    $out = [];
    foreach (array_keys($ids) as $jid) {
        $rc = revrecon_job($jid);
        if ($rc['diverges']) { $out[] = $rc; if (count($out) >= $limit) break; }
    }
    return $out;
}

// How many jobs diverge (for a health/advisory count). Cheap-ish: reuses the scan with a high cap.
function revrecon_count() { return count(revrecon_scan(100000)); }
