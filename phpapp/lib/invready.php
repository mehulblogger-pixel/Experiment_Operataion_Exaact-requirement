<?php
// Phase 2 §33 — invoice readiness. Raising an invoice is the moment a job turns into money owed, and
// it can go wrong quietly: billed before the report is issued, before the client accepted a release,
// without the PO on file, or beyond the contract value. This assembles a READY / NOT-READY verdict
// from signals that already exist (report_docs status, the rn-acceptance setting, calls.po_id, the
// contract value, the books ledger). Advisory by default — it surfaces the blockers; it blocks the
// billing action only when `invoice_gate_strict` is set, exactly like the §10 issuance gate. It reads;
// it changes nothing.

function invoice_gate_strict() { return (string)setting_get('invoice_gate_strict', '') === '1'; }

// The readiness of one job for invoicing. Returns ['ready'=>bool, 'checks'=>[...],
// 'blockers'=>[failed], 'warnings'=>[soft]]. Each check: [code,label,ok,detail,severity('block'|'warn')].
function invoice_readiness($job) {
    $job = (array)$job;
    $jobId = (int)($job['id'] ?? 0);
    $val = function ($sql, $a = []) { try { return ops_val($sql, $a); } catch (Throwable $e) { return null; } };
    $checks = [];
    $add = function ($code, $label, $ok, $detail, $sev = 'block') use (&$checks) {
        $checks[] = ['code' => $code, 'label' => $label, 'ok' => (bool)$ok, 'detail' => (string)$detail, 'severity' => $sev];
    };

    // 1. The job must be closed — the same rule /job-bill already enforces.
    $add('closed', 'Job is closed', !empty($job['closed_flag']),
         !empty($job['closed_flag']) ? 'Closed.' : 'Close the job before billing.', 'block');

    // 2. Every report on the job is issued — nothing still in draft/vetting/review.
    $reports = (int)$val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND COALESCE(deleted,0)=0", [$jobId]);
    if ($reports > 0) {
        $unissued = (int)$val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND COALESCE(deleted,0)=0
                               AND UPPER(COALESCE(status,'')) NOT IN ('ISSUED','APPROVED','CLOSED')", [$jobId]);
        $add('reports_issued', 'Reports issued', $unissued === 0,
             $unissued === 0 ? "All $reports report(s) issued." : "$unissued of $reports report(s) not yet issued.", 'block');
    }

    // 3. Client acceptance for a release, when the installation requires it.
    if ($reports > 0 && (string)setting_get('rn_require_client_acceptance', '') === '1') {
        $rn = (int)$val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND COALESCE(deleted,0)=0 AND type_code IN ('RN','IRN')", [$jobId]);
        if ($rn > 0) {
            $accepted = strtoupper((string)($job['credit_direction'] ?? '')) === 'ACCEPTED'
                || (int)$val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND type_code IN ('RN','IRN') AND UPPER(COALESCE(client_acceptance,''))='ACCEPTED'", [$jobId]) > 0;
            $add('client_acceptance', 'Client acceptance recorded', $accepted,
                 $accepted ? 'Release accepted by the client.' : 'A release is present but client acceptance is not recorded.', 'block');
        }
    }

    // 4. PO on file (via the call). A warning, not a block — some clients bill against a contract only.
    if (!empty($job['call_id'])) {
        $po = (int)$val("SELECT COALESCE(po_id,0) FROM calls WHERE id=?", [(int)$job['call_id']]);
        $add('po', 'Purchase order on file', $po > 0,
             $po > 0 ? 'PO linked.' : 'No PO linked to the call — confirm the client bills without one.', 'warn');
    }

    // 5. Within the contract value — the billed-so-far plus this job must not exceed it.
    $cno = trim((string)($job['contract_number'] ?? ''));
    if ($cno !== '') {
        $cval = (float)$val("SELECT value FROM partner_contracts WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$cno]);
        if ($cval > 0) {
            $billed = (float)$val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE contract_number=? AND COALESCE(status,'')<>'CANCELLED'", [$cno]);
            $thisJob = (float)($job['invoice_value'] ?? $job['invoice_amount'] ?? 0);
            $projected = $billed + $thisJob;
            $ok = $projected <= $cval + 0.01;
            $add('contract_value', 'Within contract value', $ok,
                 $ok ? 'Billed ' . round($billed, 2) . ' of ' . round($cval, 2) . '.'
                     : 'Billing would reach ' . round($projected, 2) . ' against a contract value of ' . round($cval, 2) . '.', 'warn');
        }
    }

    $blockers = array_values(array_filter($checks, fn($c) => !$c['ok'] && $c['severity'] === 'block'));
    $warnings = array_values(array_filter($checks, fn($c) => !$c['ok'] && $c['severity'] === 'warn'));
    return ['ready' => empty($blockers), 'checks' => $checks, 'blockers' => $blockers, 'warnings' => $warnings];
}

// A message to hard-block the billing action — only when the installation set invoice_gate_strict AND a
// blocking check fails. Returns '' when billing may proceed (the default, advisory posture).
function invoice_readiness_block($job) {
    if (!invoice_gate_strict()) return '';
    $r = invoice_readiness($job);
    if ($r['ready']) return '';
    $first = $r['blockers'][0] ?? null;
    return $first ? ('Not ready to invoice: ' . $first['label'] . ' — ' . $first['detail']) : '';
}

// A read-only readiness panel for the job screen.
function invoice_readiness_render($job) {
    if (!function_exists('invoice_readiness')) return;
    $r = invoice_readiness($job);
    if (empty($r['checks'])) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $tone = $r['ready'] ? ($r['warnings'] ? 'p-warn' : 'p-ok') : 'p-bad';
    $head = $r['ready'] ? ($r['warnings'] ? 'Ready to invoice — with notes' : 'Ready to invoice') : 'Not ready to invoice';
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">Invoice readiness '
       . '<span class="pill ' . $tone . '" style="font-size:11px;vertical-align:middle">' . $e($head) . '</span></h3>'
       . '<p class="muted" style="margin:0 0 8px;font-size:12px">'
       . ($r['ready'] ? 'The billable checks pass' . ($r['warnings'] ? ', but note the warnings below.' : '.')
                      : 'Resolve the blockers below before raising the invoice.')
       . (invoice_gate_strict() ? ' Strict gate is ON — billing is blocked until ready.' : ' Advisory only — billing is not blocked.')
       . '</p><div style="display:flex;flex-direction:column;gap:4px">';
    foreach ($r['checks'] as $c) {
        $ico = $c['ok'] ? '✓' : ($c['severity'] === 'block' ? '✗' : '!');
        $col = $c['ok'] ? 'p-ok' : ($c['severity'] === 'block' ? 'p-bad' : 'p-warn');
        echo '<div style="font-size:12.5px"><span class="pill ' . $col . '" style="font-size:10px">' . $ico . '</span> '
           . '<strong>' . $e($c['label']) . '</strong> <span class="muted">— ' . $e($c['detail']) . '</span></div>';
    }
    echo '</div></div>';
}
