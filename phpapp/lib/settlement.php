<?php
// Phase 2 §32 — inter-office settlement, as a READ-ONLY aggregation of what the profit engine already
// computes per job. On a cross-office job the executing office earns the credit (job_money()'s
// `expected_credit`) and the contracting office owes it; each job already carries a `credit_received`
// flag saying whether that has been settled. What was missing was the roll-up: "office A owes office B
// this much across N jobs, of which this much is still unsettled." This assembles that matrix from the
// SAME fields and the SAME cross/credit rule job_money() uses, so no new number is invented and no row
// is changed.

// The settlement matrix, scoped to what the viewer may see. Returns one row per
// (contracting office -> executing office) pair with the owed / settled / outstanding totals and job
// counts. Only cross-office CLOSED jobs with a credit contribute.
function settlement_matrix() {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    [$jw, $ja] = function_exists('scope_clause') ? scope_clause('j.executing_office_id', 'j.sbu') : ['1=1', []];
    // Every closed job with the fields the cross/credit rule needs. Contracting office
    // falls back to the call, exactly as job_money() resolves it.
    $rows = $all(
        "SELECT j.id, j.executing_office_id,
                COALESCE(j.contracting_office_id,
                         (SELECT COALESCE(c.contracting_office_id, c.ibo_office_id) FROM calls c WHERE c.id=j.call_id)) contracting_office_id,
                COALESCE(j.expected_credit,0) expected_credit, COALESCE(j.credit_received,0) credit_received
           FROM jobs j
          WHERE $jw AND COALESCE(j.closed_flag,0)=1", $ja);

    $names = [];
    foreach ($all("SELECT id, name FROM offices") as $o) $names[(int)$o['id']] = (string)$o['name'];

    $pairs = [];
    foreach ($rows as $r) {
        $exe = (int)$r['executing_office_id']; $hold = (int)$r['contracting_office_id'];
        // Same cross rule as job_money(): only across two distinct offices.
        if (!$exe || !$hold || $exe === $hold) continue;
        $credit = (float)$r['expected_credit'];
        if ($credit <= 0) continue;                        // no money moving
        $key = $hold . '>' . $exe;
        if (!isset($pairs[$key])) $pairs[$key] = [
            'from_office_id' => $hold, 'to_office_id' => $exe,
            'from_office' => $names[$hold] ?? ('Office #' . $hold),
            'to_office'   => $names[$exe]  ?? ('Office #' . $exe),
            'owed' => 0.0, 'settled' => 0.0, 'outstanding' => 0.0, 'jobs' => 0, 'jobs_open' => 0];
        $pairs[$key]['owed'] += $credit;
        $pairs[$key]['jobs']++;
        if (!empty($r['credit_received'])) { $pairs[$key]['settled'] += $credit; }
        else { $pairs[$key]['outstanding'] += $credit; $pairs[$key]['jobs_open']++; }
    }
    foreach ($pairs as &$p) { $p['owed'] = round($p['owed'], 2); $p['settled'] = round($p['settled'], 2); $p['outstanding'] = round($p['outstanding'], 2); }
    unset($p);
    // Most outstanding first.
    usort($pairs, fn($a, $b) => $b['outstanding'] <=> $a['outstanding']);
    return array_values($pairs);
}

// Total inter-office credit still unsettled within the viewer's scope (a one-figure health signal).
function settlement_outstanding_total() {
    $t = 0.0; foreach (settlement_matrix() as $p) $t += (float)$p['outstanding']; return round($t, 2);
}

// A read-only settlement panel for the invoicing / finance screen.
function settlement_render($title = 'Inter-office settlement') {
    if (!function_exists('settlement_matrix')) return;
    $m = settlement_matrix();
    if (!$m) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $money = fn($n) => function_exists('fmoney') ? fmoney($n) : number_format((float)$n, 2);
    $out = array_sum(array_map(fn($p) => $p['outstanding'], $m));
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">(' . count($m) . ' office pair' . (count($m) === 1 ? '' : 's') . ')</span></h3>'
       . '<p class="muted" style="margin:0 0 8px;font-size:12px">On a cross-office job the branch that did the work is owed a credit by the branch that holds the contract. This rolls up that credit and what is still unsettled — from the same figures the job P&amp;L uses.</p>'
       . '<div class="dt-scroll"><table class="dt"><thead><tr>'
       . '<th>Owing office</th><th>Owed to</th><th class="num">Credit</th><th class="num">Settled</th><th class="num">Outstanding</th><th class="num">Open jobs</th></tr></thead><tbody>';
    foreach ($m as $p) {
        echo '<tr><td>' . $e($p['from_office']) . '</td><td>' . $e($p['to_office']) . '</td>'
           . '<td class="num">' . $e($money($p['owed'])) . '</td>'
           . '<td class="num">' . $e($money($p['settled'])) . '</td>'
           . '<td class="num">' . ($p['outstanding'] > 0 ? '<strong>' . $e($money($p['outstanding'])) . '</strong>' : $e($money(0))) . '</td>'
           . '<td class="num">' . (int)$p['jobs_open'] . ' / ' . (int)$p['jobs'] . '</td></tr>';
    }
    echo '</tbody><tfoot><tr><th colspan="4" class="num">Total outstanding</th><th class="num">' . $e($money($out)) . '</th><th></th></tr></tfoot>';
    echo '</table></div></div>';
}
