<?php
// ============================================================================
//  The books, on screen
//
//  Kept apart from lib/books.php so the rules and the routes do not grow into
//  one another: books.php can be called by the Tally export, Customer 360 and
//  the ageing report without dragging a request cycle behind it.
//
//  The screens follow the way the work actually happens rather than the way the
//  tables are laid out:
//
//    /invoices        the register — draft, open, overdue, paid
//    /invoice-new     start one, usually from a customer's unbilled deputations
//    /invoice         one invoice: lines, tax, what has settled it, what is left
//    /receipts        money in, including anything not yet matched to an invoice
//    /receipt         one receipt, and the screen that spreads it across invoices
//    /ledger          one customer, every entry, running balance
//    /to-bill         finished work nobody has invoiced — operations → books
// ============================================================================

// One workspace, five tabs. The billing screens are one continuous accounts
// flow — bill closed work, raise the invoice, match the money in, watch the
// ageing, hand it to Tally — so they carry a shared tab strip and read as a
// single screen instead of five separate nav tiles. Each tab still routes to
// its own handler, which keeps its own permission check; this only navigates.
function billing_tabs($active) {
    if (!function_exists('books_can') || !books_can()) return;
    $tabs = [
        'to-bill'     => ['⧗', 'To bill',   '/to-bill'],
        'invoices'    => ['🧾', 'Invoices',  '/invoices'],
        'receipts'    => ['💰', 'Money in',  '/receipts'],
        'receivables' => ['⏳', 'Ageing',    '/receivables'],
        'tally'       => ['📤', 'Export',    '/tally'],
    ];
    echo '<nav class="tabbar" aria-label="Billing" style="display:flex;gap:6px;flex-wrap:wrap;'
       . 'border-bottom:1px solid var(--line,#e5e7eb);margin:0 0 16px">';
    foreach ($tabs as $key => [$ic, $label, $href]) {
        $on = $key === $active;
        echo '<a href="' . e($href) . '" class="tab' . ($on ? ' on' : '') . '"'
           . ($on ? ' aria-current="page"' : '')
           . ' style="padding:9px 14px;text-decoration:none;font-size:14px;border-bottom:2px solid '
           . ($on ? 'var(--accent,#2563eb);font-weight:600;color:var(--accent,#2563eb)' : 'transparent;color:inherit')
           . '">' . e($ic) . ' ' . e($label) . '</a>';
    }
    echo '</nav>';
}

function books_dt_invoice_columns($today) {
    return [
        'no' => ['label' => 'Invoice', 'sort' => 'i.invoice_no', 'render' => fn($r) =>
            '<a href="/invoice?id=' . (int)$r['id'] . '"><b>'
            . e($r['invoice_no'] !== '' ? $r['invoice_no'] : 'draft #' . (int)$r['id']) . '</b></a>'
            . '<br><span class="muted" style="font-size:12px">' . e(fdate($r['invoice_date'])) . '</span>'],
        'customer' => ['label' => 'Customer', 'sort' => 'i.partner_name', 'render' => fn($r) =>
            '<a href="/ledger?id=' . (int)$r['partner_id'] . '">' . e($r['partner_name']) . '</a>'],
        'branch' => ['label' => 'Branch', 'sort' => 'o.name', 'optional' => true,
            'render' => fn($r) => e($r['office_name'] ?: '—')],
        'po' => ['label' => 'PO / contract', 'sort' => 'i.po_number', 'optional' => true,
            'render' => fn($r) => e(trim(($r['po_number'] ?: '') . ' ' . ($r['contract_number'] ?: '')) ?: '—')],
        'total' => ['label' => 'Invoice', 'sort' => 'i.total', 'num' => true,
            'render' => fn($r) => e(fmoney($r['total']))],
        'tax' => ['label' => 'Tax', 'sort' => 'i.igst', 'num' => true, 'optional' => true,
            'render' => fn($r) => (int)$r['is_igst']
                ? 'IGST ' . e(fmoney($r['igst']))
                : 'C+S ' . e(fmoney((float)$r['cgst'] + (float)$r['sgst']))],
        'settled' => ['label' => 'Settled', 'num' => true, 'render' => fn($r) =>
            e(fmoney((float)$r['settled_cash'] + (float)$r['credited']))],
        'due' => ['label' => 'Outstanding', 'num' => true, 'render' => function ($r) {
            $out = round((float)$r['total'] - (float)$r['settled_cash'] - (float)$r['credited'], 2);
            if ($r['status'] === 'DRAFT' || $r['status'] === 'CANCELLED') return '<span class="muted">—</span>';
            return $out <= 1 ? '<span class="muted">—</span>' : '<b>' . e(fmoney($out)) . '</b>';
        }],
        'when' => ['label' => 'Due', 'sort' => 'i.due_date', 'render' => function ($r) use ($today) {
            if ($r['due_date'] === '') return '<span class="muted">—</span>';
            $late = in_array($r['status'], ['ISSUED', 'PART_PAID'], true) && $r['due_date'] < $today;
            return '<span class="' . ($late ? 'pill p-bad' : 'muted') . '" style="font-size:12px">'
                 . e(fdate($r['due_date'])) . ($late ? ' — late' : '') . '</span>';
        }],
        'status' => ['label' => 'Status', 'sort' => 'i.status', 'render' => fn($r) =>
            '<span class="pill ' . ($r['status'] === 'PAID' ? 'p-ok'
                : ($r['status'] === 'CANCELLED' ? 'p-mut' : ($r['status'] === 'DRAFT' ? 'p-mut' : 'p-warn')))
            . '">' . e(INV_STATUS[$r['status']] ?? $r['status']) . '</span>'],
    ];
}

function ops_books($route, $method) {
    ops_require(books_can(), 'You cannot open the books.');
    books_migrate();
    $canIssue = books_can_issue();

    // ---- The register -------------------------------------------------------
    if ($route === 'invoices') {
        $f = $_GET['f'] ?? 'open';
        $cols = books_dt_invoice_columns(date('Y-m-d'));
        $dt = dt_state('invoices', $cols, ['default_sort' => 'when', 'default_dir' => 'asc', 'per' => 50]);
        $filter = ['q' => $dt['q']];
        if ($f === 'open')    $filter['open'] = 1;
        if ($f === 'draft')   $filter['status'] = 'DRAFT';
        if ($f === 'paid')    $filter['status'] = 'PAID';
        if ($f === 'cancelled') $filter['status'] = 'CANCELLED';

        if (wants_csv()) {
            $csv = [['Invoice','Date','Due','Customer','Branch','PO','Contract','Taxable','CGST','SGST','IGST','Round off','Total','Settled','Outstanding','Status']];
            foreach (books_invoices($filter) as $r) {
                $out = round((float)$r['total'] - (float)$r['settled_cash'] - (float)$r['credited'], 2);
                $csv[] = [$r['invoice_no'], $r['invoice_date'], $r['due_date'], $r['partner_name'], $r['office_name'],
                          $r['po_number'], $r['contract_number'], (float)$r['subtotal'], (float)$r['cgst'],
                          (float)$r['sgst'], (float)$r['igst'], (float)$r['round_off'], (float)$r['total'],
                          (float)$r['settled_cash'] + (float)$r['credited'], $out,
                          INV_STATUS[$r['status']] ?? $r['status']];
            }
            csv_download('invoices-' . $f . '-' . date('Y-m-d') . '.csv', $csv);
        }
        $total = books_invoices_count($filter);
        $rows  = books_invoices($filter, dt_sql_tail($dt, $cols,
                    "(i.status IN ('ISSUED','PART_PAID')) DESC, i.invoice_date DESC, i.id DESC"));
        view('ops/invoices', ['rows' => $rows, 'total' => $total, 'dt' => $dt, 'cols' => $cols,
                              'f' => $f, 'counts' => books_counts(), 'canIssue' => $canIssue]);
        return true;
    }

    // ---- Work finished and not yet billed -----------------------------------
    if ($route === 'to-bill') {
        $rows = books_billable_jobs((int)($_GET['partner'] ?? 0));
        // The three real billing rhythms all come off this one pool:
        //   • per call      — tick one job, draft its invoice
        //   • per month     — pick a month, tick a whole project's work for it
        //   • per project   — one contract's deputations, on one invoice
        // So the pool is filtered by the CLOSED month, and grouped customer →
        // contract/project. Each project draws its own invoice.
        $month  = trim((string)($_GET['month'] ?? ''));
        $months = [];
        foreach ($rows as $r) { $m = substr((string)($r['closed_at'] ?? ''), 0, 7); if ($m !== '') $months[$m] = true; }
        krsort($months); $months = array_keys($months);
        if ($month !== '') $rows = array_values(array_filter($rows, fn($r) => substr((string)($r['closed_at'] ?? ''), 0, 7) === $month));
        $by = [];
        foreach ($rows as $r) {
            $cid = (int)$r['client_id'];
            if (!isset($by[$cid])) $by[$cid] = ['name' => $r['client_name'] ?: 'Customer not set', 'value' => 0.0, 'projects' => []];
            $cn = trim((string)($r['contract_number'] ?? ''));
            $pk = $cn !== '' ? $cn : '~none';   // '~none' sorts last, shown as "no contract"
            if (!isset($by[$cid]['projects'][$pk])) $by[$cid]['projects'][$pk] = ['contract' => $cn, 'rows' => [], 'value' => 0.0];
            $v = (float)($r['billable_value'] ?: $r['invoice_value'] ?: 0);
            $by[$cid]['projects'][$pk]['rows'][] = $r;
            $by[$cid]['projects'][$pk]['value'] += $v;
            $by[$cid]['value'] += $v;
        }
        foreach ($by as &$g) { uasort($g['projects'], fn($a, $b) => $b['value'] <=> $a['value']); } unset($g);
        uasort($by, fn($a, $b) => $b['value'] <=> $a['value']);
        view('ops/to_bill', ['groups' => $by, 'canIssue' => $canIssue, 'month' => $month, 'months' => $months]);
        return true;
    }

    // Set the billing branch on a draft — clears "No branch is set" without
    // starting the invoice over.
    if ($route === 'invoice-set-branch' && $method === 'POST') {
        ops_require($canIssue, 'You cannot change the books.');
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $e = books_set_office($id, (int)($_POST['office_id'] ?? 0));
        flash($e !== '' ? $e : 'Billing branch set — the invoice can be issued now.', $e !== '' ? 'error' : 'success');
        redirect('/invoice?id=' . $id);
    }

    // ---- One invoice ---------------------------------------------------------
    if ($route === 'invoice') {
        $inv = books_invoice($_GET['id'] ?? 0);
        if (!$inv) { http_response_code(404); view('notfound'); return true; }
        // When a draft has no branch, offer the picker pre-set to the sensible
        // guess: the branch the work resolves to, else the one the user is in.
        $suggestOffice = 0;
        if ($inv['status'] === 'DRAFT' && empty($inv['office_id'])) {
            $ln = books_try(fn() => ops_one(
                "SELECT job_id FROM invoice_lines WHERE invoice_id=? AND job_id IS NOT NULL ORDER BY id LIMIT 1", [(int)$inv['id']]), null);
            if ($ln && function_exists('invoice_office_for')) {
                $j = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$ln['job_id']]);
                $c = $j ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$j['call_id']]) : null;
                $suggestOffice = $j ? (int)invoice_office_for($j, $c) : 0;
            }
            if (!$suggestOffice) $suggestOffice = (int)(current_user()['home_office_id'] ?? 0);
        }
        view('ops/invoice_detail', [
            'inv' => $inv, 'lines' => books_lines((int)$inv['id']),
            'settled' => books_settled((int)$inv['id']),
            'receipts' => books_invoice_receipts((int)$inv['id']),
            'notes' => books_invoice_credit_notes((int)$inv['id']),
            'missing' => books_issue_missing($inv),
            'billable' => $inv['status'] === 'DRAFT' ? books_billable_jobs((int)$inv['partner_id'], 60) : [],
            'canIssue' => $canIssue, 'canCancel' => books_can_cancel(),
            'reasons' => CN_REASONS, 'today' => date('Y-m-d'),
            // For the "set branch" fixer on a draft with no branch.
            'offices' => ($inv['status'] === 'DRAFT' && empty($inv['office_id']))
                ? ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name") : [],
            'suggestOffice' => $suggestOffice,
            // The Books view, when this invoice has been sent there.
            'booksConnected' => function_exists('books_connected') && books_connected(),
            'booksStatus' => function_exists('books_invoice_status') ? books_invoice_status((int)$inv['id']) : null,
        ]);
        return true;
    }

    // The printable / PDF tax invoice — the same bill the client gets, with its
    // lines grouped by contract when the invoice spans more than one project.
    if ($route === 'invoice-print') {
        $inv = books_invoice($_GET['id'] ?? 0);
        if (!$inv) { http_response_code(404); view('notfound'); return true; }
        // A standalone printable document — rendered directly, not through view(),
        // so it is not wrapped in the app chrome (same pattern as voucher-print).
        $lines     = books_lines((int)$inv['id']);
        $settled   = books_settled((int)$inv['id']);
        $office    = !empty($inv['office_id']) ? ops_one("SELECT * FROM offices WHERE id=?", [(int)$inv['office_id']]) : null;
        $buyerAddr = function_exists('partner_primary_address') ? partner_primary_address((int)$inv['partner_id']) : null;
        require __DIR__ . '/../views/ops/invoice_print.php';
        return true;
    }

    // Everything below writes.
    ops_require($canIssue || can('data.credit'), 'You cannot change the books.');

    if ($route === 'invoice-new') {
        if ($method === 'POST') {
            $r = books_invoice_create($_POST);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect_back('/invoices'); }
            // Starting an invoice from the to-bill list carries the work straight
            // onto it. Re-keying the deputation numbers is exactly where the
            // second version of a figure comes from.
            //
            // A line that is refused — usually because the deputation is already
            // on somebody else's invoice — has to SAY so. Ticking five and
            // silently getting four is how work goes unbilled for a month.
            $wanted = array_map('intval', (array)($_POST['jobs'] ?? []));
            // "Draft this project only" carries only=<contract number>: keep just
            // the ticked jobs on that contract, so one project bills on its own
            // even when the whole customer's month is on screen. Absent, every
            // ticked job across all the customer's projects goes on one combined
            // invoice (the lines stay grouped by contract on the bill itself).
            $only = trim((string)($_POST['only'] ?? ''));
            if ($only !== '' && $wanted) {
                $ph = implode(',', array_fill(0, count($wanted), '?'));
                $keep = ops_all("SELECT j.id FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                                 WHERE j.id IN ($ph) AND COALESCE(c.contract_number,'')=?", array_merge($wanted, [$only]));
                $keepIds = array_map(fn($x) => (int)$x['id'], $keep);
                $wanted = array_values(array_intersect($wanted, $keepIds));
            }
            $refused = [];
            foreach ($wanted as $jid)
                if (($e = books_line_add((int)$r['id'], ['job_id' => (int)$jid])) !== '') $refused[] = $e;
            $added = count($wanted) - count($refused);
            if ($refused) {
                flash('Draft started with ' . $added . ' of ' . count($wanted) . ' ticked. '
                    . implode(' ', array_unique($refused)), 'warning');
            } else {
                flash('Draft invoice started. Check the lines, then issue it.');
            }
            redirect('/invoice?id=' . $r['id']);
        }
        $pid = (int)($_GET['partner'] ?? 0);
        view('ops/invoice_form', [
            'partner' => $pid ? ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]) : null,
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 AND status='ACTIVE' ORDER BY COALESCE(display_name, legal_name) LIMIT 800"),
            'offices' => ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name"),
            'billable' => $pid ? books_billable_jobs($pid, 200) : [],
            'terms' => function_exists('lk_options') ? lk_options('payment_terms') : [],
            // Everything the invoice can already know from the customer and from
            // the work being billed, so nobody re-types a PO number that is
            // already in the system and gets it wrong.
            'carry' => $pid ? books_carry_for($pid) : [],
        ]);
        return true;
    }

    if ($route === 'invoice-line-add' && $method === 'POST') {
        $id = (int)($_POST['invoice_id'] ?? 0);
        if (($e = books_line_add($id, $_POST)) !== '') flash($e, 'error');
        redirect('/invoice?id=' . $id);
    }

    if ($route === 'invoice-line-delete' && $method === 'POST') {
        $id = (int)($_POST['invoice_id'] ?? 0);
        if (($e = books_line_delete((int)($_POST['line_id'] ?? 0))) !== '') flash($e, 'error');
        redirect('/invoice?id=' . $id);
    }

    if ($route === 'invoice-issue' && $method === 'POST') {
        ops_require($canIssue, 'Only accounts can issue an invoice.');
        $id = (int)($_POST['id'] ?? 0);
        $e = books_issue($id);
        flash($e !== '' ? $e : 'Invoice issued. It is now in the customer\'s ledger and in the ageing.', $e !== '' ? 'error' : 'success');
        redirect('/invoice?id=' . $id);
    }

    if ($route === 'invoice-cancel' && $method === 'POST') {
        ops_require(books_can_cancel(), 'Only accounts can cancel an invoice.');
        $id = (int)($_POST['id'] ?? 0);
        $e = books_cancel($id, (string)($_POST['reason'] ?? ''));
        flash($e !== '' ? $e : 'Invoice cancelled. The number stays in the series — a GST series may not have gaps.',
              $e !== '' ? 'error' : 'success');
        redirect('/invoice?id=' . $id);
    }

    // ---- Money in ------------------------------------------------------------
    if ($route === 'receipts') {
        $rows = books_receipts(['q' => (string)($_GET['q'] ?? ''),
                                'unallocated' => ($_GET['f'] ?? '') === 'unallocated' ? 1 : 0]);
        if (wants_csv()) {
            $csv = [['Receipt','Date','Customer','Mode','Reference','Amount','TDS','Allocated','Left to match']];
            foreach ($rows as $r)
                $csv[] = [$r['receipt_no'], $r['receipt_date'], $r['partner_name'],
                          RECEIPT_MODES[$r['mode']] ?? $r['mode'], $r['reference'],
                          (float)$r['amount'], (float)$r['tds_amount'], (float)$r['allocated'],
                          round((float)$r['amount'] + (float)$r['tds_amount'] - (float)$r['allocated'], 2)];
            csv_download('receipts-' . date('Y-m-d') . '.csv', $csv);
        }
        view('ops/receipts', ['rows' => $rows, 'f' => (string)($_GET['f'] ?? '')]);
        return true;
    }

    if ($route === 'receipt') {
        $r = books_receipt($_GET['id'] ?? 0);
        if (!$r) { http_response_code(404); view('notfound'); return true; }
        view('ops/receipt_detail', [
            'r' => $r, 'allocs' => books_receipt_allocations((int)$r['id']),
            'open' => books_open_invoices((int)$r['partner_id']),
            'allocated' => books_allocated((int)$r['id']),
            'allocCash' => books_allocated((int)$r['id'], 'CASH'),
            'allocTds' => books_allocated((int)$r['id'], 'TDS'),
        ]);
        return true;
    }

    if ($route === 'receipt-new') {
        if ($method === 'POST') {
            $r = books_receipt_create($_POST);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect_back('/receipts'); }
            flash('Receipt ' . $r['no'] . ' recorded. Now say which invoices it settles.');
            redirect('/receipt?id=' . $r['id']);
        }
        $pid = (int)($_GET['partner'] ?? 0);
        view('ops/receipt_form', [
            'partner' => $pid ? ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]) : null,
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 ORDER BY COALESCE(display_name, legal_name) LIMIT 800"),
            'offices' => ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name"),
            'open' => $pid ? books_open_invoices($pid) : [],
        ]);
        return true;
    }

    if ($route === 'receipt-allocate' && $method === 'POST') {
        $id = (int)($_POST['receipt_id'] ?? 0);
        // The form posts cash[invoiceId] and tds[invoiceId]; they are folded into
        // one map here so books_allocate() sees each invoice once.
        $rows = [];
        foreach (['cash', 'tds'] as $k)
            foreach ((array)($_POST[$k] ?? []) as $invId => $amt)
                if (trim((string)$amt) !== '') $rows[(int)$invId][$k] = $amt;
        $e = books_allocate($id, $rows);
        flash($e !== '' ? $e : 'Allocated.', $e !== '' ? 'error' : 'success');
        redirect('/receipt?id=' . $id);
    }

    if ($route === 'receipt-unallocate' && $method === 'POST') {
        $id = (int)($_POST['receipt_id'] ?? 0);
        if (($e = books_unallocate((int)($_POST['alloc_id'] ?? 0))) !== '') flash($e, 'error');
        redirect('/receipt?id=' . $id);
    }

    if ($route === 'credit-note-new' && $method === 'POST') {
        ops_require($canIssue, 'Only accounts can raise a credit note.');
        $r = books_credit_note($_POST);
        if (!empty($r['err'])) flash($r['err'], 'error');
        else flash('Credit note ' . $r['no'] . ' raised against the invoice.');
        redirect('/invoice?id=' . (int)($_POST['invoice_id'] ?? 0));
    }

    // ---- The ledger ----------------------------------------------------------
    if ($route === 'ledger') {
        $pid = (int)($_GET['id'] ?? 0);
        $p = $pid ? ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]) : null;
        if (!$p) { http_response_code(404); view('notfound'); return true; }
        $from = (string)($_GET['from'] ?? '');
        $to   = (string)($_GET['to'] ?? '');
        $led  = books_ledger($pid, $from, $to);
        if (wants_csv()) {
            $csv = [['Date','Type','Reference','Note','Debit','Credit','Balance']];
            foreach ($led['rows'] as $r)
                $csv[] = [$r['date'], $r['kind'], $r['ref'], $r['note'], $r['debit'], $r['credit'], $r['balance']];
            csv_download('ledger-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)($p['display_name'] ?: $p['legal_name'])) . '.csv', $csv);
        }
        view('ops/ledger', ['p' => $p, 'led' => $led, 'from' => $from, 'to' => $to,
                            'sum' => books_outstanding($pid)]);
        return true;
    }

    return false;
}
