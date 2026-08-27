<?php
// Phase 3 §27 — the financial-event stream.
//
// Money moves through the app as records in several tables — an accepted quotation, an issued invoice,
// a receipt, a credit note, a cancellation. Each screen that wanted "what happened with the money"
// re-read and re-shaped those tables its own way. This projects them into ONE uniform, time-ordered
// stream, so a client-360, an engagement, or the coming Command Centre all read the same money feed.
//
// It is a READ MODEL, not a new source of truth: it reads the existing books ledger and quotations and
// can therefore never drift from them (the mistake §29 exists to catch). Nothing is written; no table
// is added. Every projection is guarded so a missing books module simply contributes no events.

// Office-scope clause for a column, honouring the viewer's branch scope (fail-closed, like every list).
function _finevent_scope($col) {
    $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($scope === 'ALL' || !is_array($scope) || !$scope) return ['1', []];
    return [$col . ' IN (' . implode(',', array_map('intval', $scope)) . ')', []];
}

// The uniform event stream. Filter keys: partner_id, contract_number, office, sbu, from, to, limit.
// Each event: [date, kind, label, dir(COMMIT|BILLED|IN|CREDIT|REVERSE), amount, party, ref,
//              office_id, sbu, entity_kind, entity_id, url].
function financial_events(array $f = []) {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $pid = (int)($f['partner_id'] ?? 0);
    $cno = trim((string)($f['contract_number'] ?? ''));
    $from = trim((string)($f['from'] ?? ''));
    $to   = trim((string)($f['to'] ?? ''));
    $ev = [];
    $push = function ($date, $kind, $label, $dir, $amount, $party, $ref, $office, $sbu, $ek, $eid, $url) use (&$ev, $from, $to) {
        $date = substr(trim((string)$date), 0, 10);
        if ($date === '') return;
        if ($from !== '' && $date < $from) return;
        if ($to   !== '' && $date > $to)   return;
        $ev[] = ['date'=>$date, 'kind'=>$kind, 'label'=>$label, 'dir'=>$dir, 'amount'=>(float)$amount,
                 'party'=>(string)$party, 'ref'=>(string)$ref, 'office_id'=>(int)$office, 'sbu'=>(string)$sbu,
                 'entity_kind'=>$ek, 'entity_id'=>(int)$eid, 'url'=>$url . (int)$eid];
    };

    // 1. Accepted quotations — a commitment won (not cash yet).
    [$qs, $qa] = _finevent_scope('office_id');
    $qw = ["UPPER(COALESCE(status,''))='ACCEPTED'", $qs];
    if ($pid) { $qw[] = 'client_id=' . $pid; }
    if ($cno !== '') { $qw[] = 'contract_number=' . db()->quote($cno); }
    foreach ($all("SELECT id, quote_no, client_name, total_amount, accepted_date, created_at, office_id, sbu FROM quotations WHERE " . implode(' AND ', $qw), $qa) as $r)
        $push($r['accepted_date'] ?: $r['created_at'], 'QUOTE_ACCEPTED', 'Quotation accepted', 'COMMIT',
              $r['total_amount'], $r['client_name'], $r['quote_no'], $r['office_id'], $r['sbu'], 'QUOTE', $r['id'], '/quote?id=');

    // 2. Invoices — issued (billed) and, separately, cancelled (reversed).
    [$is, $ia] = _finevent_scope('office_id');
    $iw = ["1", $is];
    if ($pid) { $iw[] = 'partner_id=' . $pid; }
    if ($cno !== '') { $iw[] = 'contract_number=' . db()->quote($cno); }
    foreach ($all("SELECT id, invoice_no, partner_name, total, status, invoice_date, issued_at, cancelled_at, office_id, sbu FROM invoices WHERE " . implode(' AND ', $iw), $ia) as $r) {
        $st = strtoupper((string)$r['status']);
        if ($st === 'CANCELLED')
            $push($r['cancelled_at'] ?: $r['invoice_date'], 'INVOICE_CANCELLED', 'Invoice cancelled', 'REVERSE',
                  $r['total'], $r['partner_name'], $r['invoice_no'], $r['office_id'], $r['sbu'], 'INVOICE', $r['id'], '/invoice?id=');
        elseif ($st !== 'DRAFT' && $st !== '')
            $push($r['invoice_date'] ?: $r['issued_at'], 'INVOICE_ISSUED', 'Invoice issued', 'BILLED',
                  $r['total'], $r['partner_name'], $r['invoice_no'], $r['office_id'], $r['sbu'], 'INVOICE', $r['id'], '/invoice?id=');
    }

    // 3. Receipts — cash in. (No contract_number column; a contract-scoped view omits these by design.)
    if ($cno === '') {
        [$rs, $ra] = _finevent_scope('office_id');
        $rw = ["1", $rs];
        if ($pid) { $rw[] = 'partner_id=' . $pid; }
        foreach ($all("SELECT id, receipt_no, partner_name, amount, receipt_date, office_id FROM receipts WHERE " . implode(' AND ', $rw), $ra) as $r)
            $push($r['receipt_date'], 'RECEIPT_RECEIVED', 'Payment received', 'IN',
                  $r['amount'], $r['partner_name'], $r['receipt_no'], $r['office_id'], '', 'RECEIPT', $r['id'], '/receipt?id=');
    }

    // 4. Credit notes — value returned to the client.
    if ($cno === '') {
        [$cs, $ca] = _finevent_scope('office_id');
        $cw = ["UPPER(COALESCE(status,''))<>'CANCELLED'", $cs];
        if ($pid) { $cw[] = 'partner_id=' . $pid; }
        foreach ($all("SELECT id, cn_no, partner_name, total, cn_date, office_id FROM credit_notes WHERE " . implode(' AND ', $cw), $ca) as $r)
            $push($r['cn_date'], 'CREDIT_NOTE', 'Credit note', 'CREDIT',
                  $r['total'], $r['partner_name'], $r['cn_no'], $r['office_id'], '', 'CREDIT_NOTE', $r['id'], '/credit-note?id=');
    }

    // Newest first; a stable tie-break keeps same-day events in a sensible order.
    usort($ev, function ($a, $b) { return [$b['date'], $b['kind']] <=> [$a['date'], $a['kind']]; });
    $limit = (int)($f['limit'] ?? 0);
    return $limit > 0 ? array_slice($ev, 0, $limit) : $ev;
}

// The totals behind the stream. committed (accepted quotes) · billed (issued invoices) · cancelled ·
// received (receipts) · credited (credit notes) · net_billed · outstanding.
function financial_rollup(array $f = []) {
    $ev = financial_events($f + ['limit' => 0]);
    $t = ['committed'=>0.0, 'billed'=>0.0, 'cancelled'=>0.0, 'received'=>0.0, 'credited'=>0.0, 'count'=>count($ev)];
    foreach ($ev as $e) {
        if ($e['kind'] === 'QUOTE_ACCEPTED')      $t['committed'] += $e['amount'];
        elseif ($e['kind'] === 'INVOICE_ISSUED')  $t['billed']    += $e['amount'];
        elseif ($e['kind'] === 'INVOICE_CANCELLED') $t['cancelled'] += $e['amount'];
        elseif ($e['kind'] === 'RECEIPT_RECEIVED') $t['received']  += $e['amount'];
        elseif ($e['kind'] === 'CREDIT_NOTE')     $t['credited']  += $e['amount'];
    }
    $t['net_billed']  = round($t['billed'] - $t['cancelled'], 2);
    $t['outstanding'] = round($t['net_billed'] - $t['received'] - $t['credited'], 2);
    return $t;
}

// A read-only "Money timeline" panel — the rollup chips plus the recent events. Renders nothing when
// there are no money events to show.
function financial_events_render(array $f = [], $title = 'Money timeline') {
    if (!function_exists('financial_events')) return;
    $ev = financial_events($f + ['limit' => (int)($f['limit'] ?? 12)]);
    if (!$ev) return;
    $roll = financial_rollup($f);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $m = function ($n) { return function_exists('fmoney_short') ? fmoney_short($n) : number_format((float)$n); };
    $tone = ['COMMIT'=>'p-mut', 'BILLED'=>'p-info', 'IN'=>'p-ok', 'CREDIT'=>'p-warn', 'REVERSE'=>'p-bad'];
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">— quotes, invoices, receipts &amp; credits in one line</span></h3>';
    echo '<div style="display:flex;flex-wrap:wrap;gap:14px;margin:0 0 12px;font-size:13px">';
    foreach ([['Committed','committed'],['Billed (net)','net_billed'],['Received','received'],['Outstanding','outstanding']] as [$lab,$k])
        echo '<div><div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">' . $e($lab) . '</div>'
           . '<div style="font-weight:600;font-variant-numeric:tabular-nums">' . $e($m($roll[$k])) . '</div></div>';
    echo '</div><div style="display:flex;flex-direction:column;gap:3px">';
    foreach ($ev as $x) {
        echo '<div style="display:flex;align-items:center;gap:10px;font-size:13px;padding:3px 0">'
           . '<span class="muted mono" style="min-width:82px;font-size:11.5px">' . $e($x['date']) . '</span>'
           . '<span class="pill ' . ($tone[$x['dir']] ?? 'p-mut') . '" style="font-size:10px;min-width:74px;text-align:center">' . $e($x['label']) . '</span>'
           . '<a href="' . $e($x['url']) . '" style="text-decoration:none">' . $e($x['ref'] ?: '—') . '</a>'
           . '<span style="margin-left:auto;font-weight:600;font-variant-numeric:tabular-nums">' . $e($m($x['amount'])) . '</span></div>';
    }
    echo '</div></div>';
}
