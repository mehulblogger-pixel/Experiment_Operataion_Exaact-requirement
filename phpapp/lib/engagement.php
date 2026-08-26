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
