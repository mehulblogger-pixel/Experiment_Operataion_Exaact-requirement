<?php
// ============================================================================
//  Customer 360 — everything about one customer, on one screen
//
//  Blueprint 001 P3 / 002 U3, and the last item in the CRM plan. It is built
//  LAST on purpose: it is not a feature so much as an assembly, and it could
//  not be honest until the things it assembles existed. Six months ago this
//  screen would have shown a name, an address and a list of calls.
//
//  It now answers the question somebody actually has, which is never "show me
//  the customer record". It is **"what do I need to know before I ring them?"**
//  and the answer has five parts:
//
//    1. Do they owe us money, and how late is it?
//    2. What are we trying to sell them, and what is it worth?
//    3. What work is in flight, and is any of it late?
//    4. Have they complained, or rejected anything?
//    5. When did anybody last speak to them, and what was said?
//
//  Each of those already has a home — the ledger, the pipeline, the order and
//  deputation registers, the complaint and nonconformity registers, the
//  activity spine. This screen does not re-implement any of them. It reads
//  them, shows the headline, and links through.
//
//  THREE THINGS IT DELIBERATELY DOES NOT DO.
//
//   * **It does not compute anything twice.** The outstanding figure comes from
//     books_outstanding(), the same function the ledger and the ageing use. A
//     360 screen with its own arithmetic is a 360 screen that disagrees with
//     the ledger, and then nobody believes either.
//   * **It does not assume the modules are installed.** A Sales-only customer
//     has no deputations and no reports. Every section is behind a licence and
//     a permission check, and an absent module leaves NO trace — not an empty
//     panel headed "Deputations".
//   * **It does not fail as a whole.** Every section is wrapped. One register
//     with a missing table on an older install must not take the screen down;
//     it drops out and the rest is still there.
// ============================================================================

function c360_can() { return can('mod.clients.view') || is_master_of('clients'); }

function c360_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }
function c360_rows($sql, $args = []) { return c360_try(fn() => ops_all($sql, $args), []) ?: []; }
function c360_one($sql, $args = []) { return c360_try(fn() => ops_one($sql, $args), null); }

// Is a module both licensed and permitted? Used everywhere below, because
// "the table exists" and "this person should see it" are different questions.
function c360_on($accessModule, $perm = null) {
    if (function_exists('licence_owner')) {
        $owner = licence_owner($accessModule);
        if ($owner !== null && function_exists('licence_enabled') && !licence_enabled($owner)) return false;
    }
    return can($perm ?: "mod.$accessModule.view") || is_master_of($accessModule);
}

// ---- The sections -----------------------------------------------------------
function c360_money($pid) {
    if (!c360_on('invoicing') || !function_exists('books_outstanding')) return null;
    $sum = c360_try(fn() => books_outstanding($pid));
    if ($sum === null) return null;
    // Ageing of THIS customer's open invoices, using the same buckets the
    // receivables report uses so the two cannot disagree.
    $today = date('Y-m-d');
    $buckets = [];
    $oldest = null; $overdue = 0.0;
    foreach (c360_rows("SELECT id, invoice_no, invoice_date, due_date, total FROM invoices
                        WHERE partner_id=? AND status IN ('ISSUED','PART_PAID')
                        ORDER BY due_date, id", [(int)$pid]) as $inv) {
        $s = c360_try(fn() => books_settled((int)$inv['id']), null);
        $out = $s ? $s['outstanding'] : (float)$inv['total'];
        if ($out <= 1) continue;
        $from = $inv['due_date'] !== '' ? $inv['due_date'] : $inv['invoice_date'];
        if ($from === '') continue;
        $days = (int)floor((strtotime($today) - strtotime($from)) / 86400);
        $b = function_exists('ar_bucket_of') ? ar_bucket_of($days) : ($days > 0 ? 'b30' : 'notdue');
        $buckets[$b] = ($buckets[$b] ?? 0) + $out;
        if ($days > 0) { $overdue += $out; if ($oldest === null || $days > $oldest) $oldest = $days; }
    }
    return $sum + ['buckets' => $buckets, 'overdue' => round($overdue, 2), 'oldest_days' => $oldest,
                   'invoices' => c360_rows(
                        "SELECT id, invoice_no, invoice_date, due_date, total, status FROM invoices
                         WHERE partner_id=? AND status <> 'DRAFT' ORDER BY invoice_date DESC, id DESC LIMIT 8", [(int)$pid])];
}

function c360_pipeline($pid) {
    if (!c360_on('leads') || !function_exists('opp_all')) return null;
    $open = c360_try(fn() => opp_all(['partner' => $pid, 'open' => 1]), []);
    $value = $weighted = 0.0;
    foreach ($open as $o) { $value += (float)$o['value']; $weighted += opp_weighted($o); }
    return [
        'open' => $open, 'value' => $value, 'weighted' => $weighted,
        'recent' => c360_rows("SELECT o.*, s.name stage_name FROM opportunities o
                               LEFT JOIN pipeline_stages s ON s.id=o.stage_id
                               WHERE o.partner_id=? AND o.status <> 'OPEN'
                               ORDER BY o.won_at DESC, o.id DESC LIMIT 6", [(int)$pid]),
        'leads' => c360_rows("SELECT id, ref, company_name, status FROM leads WHERE partner_id=? ORDER BY id DESC LIMIT 5", [(int)$pid]),
    ];
}

function c360_quotes($pid) {
    if (!c360_on('quotes')) return null;
    return c360_rows("SELECT id, quote_no, rev, subject, status, total_amount, created_at
                      FROM quotations WHERE client_id=? AND is_current=1
                      ORDER BY id DESC LIMIT 8", [(int)$pid]);
}

function c360_work($pid) {
    if (!c360_on('calls') && !c360_on('jobs')) return null;
    $calls = c360_rows("SELECT id, call_code, status, call_received_date, inspection_required_date
                        FROM calls WHERE client_id=? ORDER BY id DESC LIMIT 8", [(int)$pid]);
    $jobs = c360_rows("SELECT j.id, j.job_code, j.closed_flag, j.scheduled_date, j.closed_at, i.name inspector_name
                       FROM jobs j
                       LEFT JOIN calls c ON c.id = j.call_id
                       LEFT JOIN inspectors i ON i.id = j.inspector_id
                       WHERE c.client_id=? ORDER BY j.id DESC LIMIT 8", [(int)$pid]);
    $openCalls = (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM calls WHERE client_id=? AND status <> 'CLOSED'", [(int)$pid]), 0);
    $openJobs  = (int)c360_try(fn() => ops_val(
        "SELECT COUNT(*) FROM jobs j LEFT JOIN calls c ON c.id=j.call_id WHERE c.client_id=? AND j.closed_flag=0", [(int)$pid]), 0);
    // Work finished and never billed, for this customer. The single most useful
    // number on the screen for anybody in accounts.
    $unbilled = (int)c360_try(fn() => ops_val(
        "SELECT COUNT(*) FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
         WHERE c.client_id=? AND j.closed_flag=1
           AND NOT EXISTS (SELECT 1 FROM invoice_lines l JOIN invoices v ON v.id=l.invoice_id
                           WHERE l.job_id=j.id AND v.status <> 'CANCELLED')", [(int)$pid]), 0);
    return ['calls' => $calls, 'jobs' => $jobs, 'open_calls' => $openCalls,
            'open_jobs' => $openJobs, 'unbilled' => $unbilled];
}

function c360_quality($pid) {
    $out = ['complaints' => [], 'ncrs' => [], 'rejections' => 0, 'total' => 0, 'open' => 0, 'any' => false];
    // The lists are capped at five so the panel stays readable. The COUNTS are
    // not — a headline that says 10 because the list stops at 5 is a lie, and
    // it is the kind of lie somebody repeats in a meeting.
    if (c360_on('complaints')) {
        $out['complaints'] = c360_rows("SELECT id, ref, kind, subject, status, received_on FROM complaints
                                        WHERE partner_id=? ORDER BY id DESC LIMIT 5", [(int)$pid]);
        $out['total'] += (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM complaints WHERE partner_id=?", [(int)$pid]), 0);
        $out['open']  += (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM complaints WHERE partner_id=? AND status <> 'CLOSED'", [(int)$pid]), 0);
    }
    if (c360_on('ncr')) {
        $out['ncrs'] = c360_rows("SELECT id, ref, title, severity, status FROM nonconformities
                                  WHERE partner_id=? ORDER BY id DESC LIMIT 5", [(int)$pid]);
        $out['total'] += (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=?", [(int)$pid]), 0);
        $out['open']  += (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED'", [(int)$pid]), 0);
    }
    if (c360_on('idems')) {
        $out['rejections'] = (int)c360_try(fn() => ops_val(
            "SELECT COUNT(*) FROM report_docs WHERE client_id=? AND client_decision='REJECTED'", [(int)$pid]), 0);
        $out['total'] += $out['rejections'];
    }
    $out['any'] = $out['total'] > 0;
    return $out;
}

// Module 15 — the reports actually issued to this client (the biggest 360 gap: only a
// rejected-report COUNT existed before). Gated by the reporting module; crash-safe.
function c360_reports($pid) {
    if (!c360_on('idems')) return null;
    $rows = c360_rows("SELECT id, irn, type_code, status, finalized, issue_date
                       FROM report_docs WHERE client_id=? AND COALESCE(deleted,0)=0 AND COALESCE(finalized,0)=1
                       ORDER BY id DESC LIMIT 8", [(int)$pid]);
    $total = (int)c360_try(fn() => ops_val("SELECT COUNT(*) FROM report_docs WHERE client_id=? AND COALESCE(deleted,0)=0 AND COALESCE(finalized,0)=1", [(int)$pid]), 0);
    return ['rows' => $rows, 'total' => $total];
}

// Module 15 — every site for the client (the 360 loaded only the primary address).
function c360_sites($pid) {
    return c360_rows("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [(int)$pid]);
}

// Module 15 — the client's satisfaction, when the CSAT module is on and permitted.
// Returns latest + average score over recent surveys, or null (card is skipped).
function c360_satisfaction($pid) {
    if (!function_exists('sat_enabled') || !sat_enabled()) return null;
    if (function_exists('sat_can_view') && !sat_can_view()) return null;
    $rows = c360_rows("SELECT score, recommend, received_on FROM satisfaction_surveys
                       WHERE client_id=? AND score IS NOT NULL ORDER BY received_on DESC, id DESC LIMIT 12", [(int)$pid]);
    if (!$rows) return null;
    $scores = array_map(fn($r) => (int)$r['score'], $rows);
    return [
        'latest'    => (int)$rows[0]['score'],
        'avg'       => round(array_sum($scores) / count($scores), 1),
        'count'     => count($scores),
        'recommend' => (string)($rows[0]['recommend'] ?? ''),
        'scale'     => function_exists('sat_scale') ? sat_scale() : 5,
    ];
}

function c360_timeline($pid, $limit = 25) {
    if (!function_exists('act_migrate')) return [];
    return c360_rows("SELECT * FROM activities WHERE partner_id=?
                      ORDER BY occurred_at DESC, id DESC LIMIT " . max(1, (int)$limit), [(int)$pid]);
}

// The one line at the top: how long since anybody spoke to them, and how bad
// that is. Silence is the thing nobody notices until the customer has gone.
function c360_last_touch($pid) {
    $when = function_exists('act_last_touch') ? (string)c360_try(fn() => act_last_touch($pid), '') : '';
    if ($when === '') return ['when' => '', 'days' => null];
    $d = (int)floor((time() - strtotime($when)) / 86400);
    return ['when' => $when, 'days' => max(0, $d)];
}

// Everything, assembled. Each part is independent, so a missing register drops
// out rather than taking the screen with it.
function c360_load($pid) {
    $pid = (int)$pid;
    $p = c360_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
    if (!$p) return null;
    return [
        'p'        => $p,
        'contacts' => c360_rows("SELECT * FROM partner_contacts WHERE partner_id=? ORDER BY is_primary DESC, name", [$pid]),
        'address'  => c360_one("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id LIMIT 1", [$pid]),
        'contracts'=> c360_rows("SELECT * FROM partner_contracts WHERE partner_id=? AND is_active=1 ORDER BY end_date DESC LIMIT 5", [$pid]),
        'money'    => c360_money($pid),
        'billable' => (c360_on('invoicing') && function_exists('billable_party_rollup')) ? billable_party_rollup($pid) : null,
        'pipeline' => c360_pipeline($pid),
        'quotes'   => c360_quotes($pid),
        'work'     => c360_work($pid),
        'quality'  => c360_quality($pid),
        'reports'  => c360_reports($pid),
        'sites'    => c360_sites($pid),
        'csat'     => c360_satisfaction($pid),
        'timeline' => c360_timeline($pid),
        'touch'    => c360_last_touch($pid),
        'group'    => c360_group($pid, $p),
    ];
}

// The customer's place in a group: its parent (if any), the companies under it,
// and the candidates that could be its parent. This is what makes the group
// quotation history on the quote screen actually populate — without a parent
// link set here, a customer is a group of one.
function c360_group($pid, $p) {
    $pid = (int)$pid;
    $parent = !empty($p['parent_id'])
        ? c360_one("SELECT id, legal_name, display_name FROM business_partners WHERE id=?", [(int)$p['parent_id']])
        : null;
    $subs = c360_rows("SELECT id, legal_name, display_name FROM business_partners
                       WHERE parent_id=? ORDER BY COALESCE(display_name, legal_name)", [$pid]);
    // Anyone could be the parent EXCEPT this company and its own subsidiaries —
    // putting a child above its parent is the loop we must not allow.
    $subIds = array_map(fn($s) => (int)$s['id'], $subs);
    $opts = c360_rows("SELECT id, legal_name, display_name FROM business_partners
                       WHERE is_client=1 AND status='ACTIVE' AND id<>?
                       ORDER BY COALESCE(display_name, legal_name) LIMIT 800", [$pid]);
    $opts = array_values(array_filter($opts, fn($o) => !in_array((int)$o['id'], $subIds, true)));
    return ['parent' => $parent, 'subs' => $subs, 'opts' => $opts];
}

function ops_customer360($route, $method) {
    ops_require(c360_can(), 'You cannot open customer records.');

    // Setting the parent (group) company. Its own screen action, so the group
    // that drives the quotation history can be built where the customer is read.
    if ($route === 'customer-parent' && $method === 'POST') {
        ops_require(can('mod.clients.edit') || is_master_of('clients'), 'You cannot change customer records.');
        $pid = (int)($_POST['id'] ?? 0);
        if (!c360_one("SELECT id FROM business_partners WHERE id=?", [$pid])) {
            flash('That customer no longer exists.', 'error'); redirect('/clients');
        }
        $parent = (int)($_POST['parent_id'] ?? 0);
        if ($parent === $pid) { flash('A company cannot be its own parent.', 'error'); redirect('/customer?id=' . $pid); }
        if ($parent) {
            if (!c360_one("SELECT id FROM business_partners WHERE id=?", [$parent])) {
                flash('That company is not on the master.', 'error'); redirect('/customer?id=' . $pid);
            }
            // Walking up from the chosen parent must never arrive back at this
            // company, or the group would be a loop nothing could read.
            $cur = $parent; $seen = [];
            for ($i = 0; $i < 20 && $cur; $i++) {
                if ($cur === $pid) { flash('That would make a loop — the company you picked is already under this one.', 'error'); redirect('/customer?id=' . $pid); }
                if (isset($seen[$cur])) break;
                $seen[$cur] = 1;
                $row = c360_one("SELECT parent_id FROM business_partners WHERE id=?", [$cur]);
                $cur = (int)($row['parent_id'] ?? 0);
            }
        }
        db()->prepare("UPDATE business_partners SET parent_id=? WHERE id=?")->execute([$parent ?: null, $pid]);
        if (function_exists('act_log'))
            act_log('PARTNER', $pid, 'SYSTEM', $parent ? 'Placed under a parent company.' : 'Removed from its group.', ['auto' => 1]);
        flash($parent ? 'Group updated — earlier quotations across the group now show together.' : 'Removed from the group.');
        redirect('/customer?id=' . $pid);
    }

    $pid = (int)($_GET['id'] ?? 0);
    $d = $pid ? c360_load($pid) : null;
    if (!$d) { http_response_code(404); view('notfound'); return true; }

    if (wants_csv()) {
        // The timeline is the part somebody asks for by e-mail before a meeting.
        $csv = [['When','Kind','What happened','About','Who','Outcome']];
        foreach ($d['timeline'] as $a)
            $csv[] = [substr((string)$a['occurred_at'], 0, 16), ACT_KINDS[$a['kind']] ?? $a['kind'],
                      $a['subject'], function_exists('act_entity_label') ? act_entity_label($a) : '',
                      $a['owner'], $a['outcome']];
        csv_download('customer-' . preg_replace('/[^A-Za-z0-9]+/', '-',
            (string)($d['p']['display_name'] ?: $d['p']['legal_name'])) . '.csv', $csv);
    }

    view('ops/customer360', $d + ['canWrite' => can('mod.clients.edit') || is_master_of('clients')]);
    return true;
}
