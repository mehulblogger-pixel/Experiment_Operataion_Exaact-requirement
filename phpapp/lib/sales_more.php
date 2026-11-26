<?php
// ============================================================================
//  SIMPLE SALES — parts 2 and 3 (owner, 18 Nov 2026: "proceed with part 2 and 3")
// ============================================================================
//  Part 2 — the two daily actions of the owner's Excel CRM:
//    · Quick new lead  (/sales-lead)  company, mobile, source → saved; the owner
//      is set by an office → salesperson setting, else whoever types it; a
//      possible duplicate is shown first with "Save anyway".
//    · Log activity    (/sales-log)   what happened + an outcome; the outcome
//      fills the next follow-up date in WORKING days (weekly off and holidays
//      skipped). Leads go through lead_log_contact(); deals through act_log()
//      and their own next_action fields — the same records the lead and deal
//      pages already write. Nothing is a second copy.
//  Part 3 — the money side and the settings:
//    · Orders & payments (/sales-orders)  accepted quotations → billed → received
//    · Renewals          (/sales-renewals) contracts by days left
//    · Targets           (/sales-targets)  per person per month (sales_targets)
//    · Sales settings    (/sales-settings) only the NEW settings are edited here;
//      the existing lists (sources, stages, lost reasons, holidays, buying role)
//      are linked to their own editors.
//
//  Nothing hard-coded: outcome → days, weekly off, stale days and the office →
//  salesperson map are settings; the starting values are marked as EXAMPLES on
//  the settings page. Targets are never overwritten — each change is a new row,
//  the latest one counts, and the earlier figure stays in the history.
// ============================================================================

//  EXAMPLE starting values — each company edits them on Sales settings.
//  days = working days to the next follow-up; 0 = "you pick the date".
const SX_OUTCOMES_EXAMPLE = [
    ['k' => 'INTERESTED',  'label' => 'Spoke — interested',            'days' => 3],
    ['k' => 'CALLBACK',    'label' => 'Asked us to call back later',   'days' => 7],
    ['k' => 'NO_ANSWER',   'label' => 'No answer / not reachable',     'days' => 1],
    ['k' => 'QUOTE_ASKED', 'label' => 'Asked for a quotation',         'days' => 2],
    ['k' => 'MEETING',     'label' => 'Meeting fixed',                 'days' => 0],
    ['k' => 'NOT_NOW',     'label' => 'Not interested now',            'days' => 30],
];
const SX_WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

function sx_more_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = db_driver() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    //  Reuse Gate: kpi_targets has no person column, so a per-person monthly
    //  target cannot live there without changing what every KPI reads.
    db()->exec("CREATE TABLE IF NOT EXISTS sales_targets (
        id $pk, user_id INT NOT NULL, ym VARCHAR(7) NOT NULL, amount DECIMAL(16,2) DEFAULT 0,
        set_by VARCHAR(150) DEFAULT '', set_at VARCHAR(40) DEFAULT '')");
    try { db()->exec("CREATE INDEX idx_sales_targets_u ON sales_targets (user_id, ym)"); } catch (Throwable $e) {}
    //  The previous figure, kept on the row so every change reads "before → after",
    //  and when the salesperson saw it (their Sales home notice, owner 19 Nov 2026).
    ensure_column('sales_targets', 'prev_amount', 'DECIMAL(16,2) NULL');
    ensure_column('sales_targets', 'seen_at', "VARCHAR(40) DEFAULT ''");
}

// ---- Settings ----------------------------------------------------------------
function sx_outcomes() {
    $raw = setting_get('sales_outcomes', '');
    $rows = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($rows) || !$rows) $rows = SX_OUTCOMES_EXAMPLE;
    $out = [];
    foreach ($rows as $r) {
        $k = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) ($r['k'] ?? '')));
        $l = trim((string) ($r['label'] ?? ''));
        if ($k === '' || $l === '') continue;
        $out[$k] = ['k' => $k, 'label' => $l, 'days' => max(0, min(365, (int) ($r['days'] ?? 0)))];
    }
    return $out;
}
function sx_outcomes_are_example() { return (string) (setting_get('sales_outcomes', '') ?? '') === ''; }
function sx_weekly_off() {
    $v = setting_get('sales_weekly_off', null);
    if ($v === null || $v === false) $v = '0';            // example: Sunday
    if (trim((string) $v) === '') return [];              // the company works every day
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $v)), fn($d) => $d >= 0 && $d <= 6)));
}
function sx_owner_map() {
    $m = json_decode((string) (setting_get('sales_owner_by_office', '') ?? ''), true);
    return is_array($m) ? array_map('intval', $m) : [];
}
//  Who a new lead belongs to: the office's salesperson (setting), else the person typing.
function sx_owner_for_office($officeId) {
    $m = sx_owner_map(); $uid = (int) ($m[(string) (int) $officeId] ?? 0);
    if ($uid && ops_val("SELECT id FROM users WHERE id=? AND is_active=1", [$uid])) return $uid;
    return (int) (current_user()['id'] ?? 0);
}

// ---- Working days ------------------------------------------------------------
//  The date $n working days after $from: the weekly off day(s) and the public
//  holidays (national, or of this office) are skipped. $n = 0 → ''.
function sx_add_working_days($n, $officeId = null, $from = null) {
    $n = (int) $n; if ($n <= 0) return '';
    $off = sx_weekly_off();
    $t = strtotime(($from ?: date('Y-m-d')) . ' 12:00:00');
    $hol = [];
    try {
        $to = date('Y-m-d', $t + 86400 * ($n * 2 + 30));
        $rows = ops_all("SELECT hol_date FROM holidays WHERE hol_date>? AND hol_date<=? AND (office_id IS NULL" . ($officeId ? " OR office_id=" . (int) $officeId : '') . ")",
                        [date('Y-m-d', $t), $to]) ?: [];
        foreach ($rows as $r) $hol[substr((string) $r['hol_date'], 0, 10)] = 1;
    } catch (Throwable $e) {
        try { foreach (ops_all("SELECT hol_date FROM holidays WHERE hol_date>?", [date('Y-m-d', $t)]) ?: [] as $r) $hol[substr((string) $r['hol_date'], 0, 10)] = 1; } catch (Throwable $e2) {}
    }
    $guard = 0;
    while ($n > 0 && $guard++ < 800) {
        $t += 86400; $d = date('Y-m-d', $t);
        if (in_array((int) date('w', $t), $off, true) || isset($hol[$d])) continue;
        $n--;
    }
    return date('Y-m-d', $t);
}

// ---- Quick new lead ----------------------------------------------------------
//  Possible duplicates by name (the lead register's own check) and by mobile
//  (an open lead or any firm's contact with the same last 10 digits).
//  $exceptPartnerId — the client the lead is being raised for. Owner test LF-01 (23 Nov 2026):
//  picking an existing client and typing its own contact's mobile was flagged as "a contact
//  with this mobile at <that same client>", so "Save anyway" was needed for an ordinary lead.
//  That client's own contacts are no longer counted; contacts at OTHER firms still are.
function sx_lead_dupes($company, $phone, $exceptPartnerId = 0) {
    $out = [];
    $hit = function_exists('lead_possible_duplicate') ? lead_possible_duplicate($company) : null;
    if ($hit) {
        $r = $hit['row'];
        $out[] = $hit['kind'] === 'partner'
            ? ['text' => 'A ' . Tl('client') . ' on file: ' . trim(($r['code'] ?? '') . ' — ' . ($r['legal_name'] ?? '')) . ' (same ' . $hit['by'] . ')', 'url' => '/partner?id=' . (int) $r['id']]
            : ['text' => 'An open lead: ' . ($r['ref'] ?? '') . ' — ' . ($r['company_name'] ?? '') . ' (same name)', 'url' => '/lead?id=' . (int) $r['id']];
    }
    $d = substr(preg_replace('/\D+/', '', (string) $phone), -10);
    if (strlen($d) === 10) {
        try {
            foreach (ops_all("SELECT id, ref, company_name, contact_phone FROM leads WHERE status='OPEN' AND COALESCE(contact_phone,'')<>''") ?: [] as $l)
                if (substr(preg_replace('/\D+/', '', (string) $l['contact_phone']), -10) === $d)
                    $out[] = ['text' => 'An open lead with this mobile: ' . $l['ref'] . ' — ' . $l['company_name'], 'url' => '/lead?id=' . (int) $l['id']];
        } catch (Throwable $e) {}
        if (function_exists('partner_contact_clash'))
            foreach (partner_contact_clash($phone, '', (int) $exceptPartnerId) as $txt) $out[] = ['text' => 'A contact with this mobile at ' . $txt, 'url' => ''];
    }
    return array_slice($out, 0, 6);
}

function sx_lead_save(array $b, $confirm) {
    $company = trim((string) ($b['company_name'] ?? ''));
    if ($company === '') return ['err' => 'Type the company (or person) to follow up.'];
    $phone = trim((string) ($b['contact_phone'] ?? ''));
    //  D4 (owner, 21 Nov 2026): the quick lead needs a mobile — 10 digits.
    if (($mp = function_exists('sales_mobile_problem') ? sales_mobile_problem($phone, true) : '') !== '') return ['err' => $mp];
    if (!$confirm && ($d = sx_lead_dupes($company, $phone))) return ['dupes' => $d];
    $u = current_user();
    $officeId = (int) ($b['office_id'] ?? 0) ?: (int) ($u['home_office_id'] ?? 0);
    if ($officeId && function_exists('scope_allows') && !scope_allows($officeId)) $officeId = (int) ($u['home_office_id'] ?? 0);
    //  A manager may hand it to someone; otherwise the office's salesperson, else me.
    $owner = sx_can_see_all() && (int) ($b['owner_user_id'] ?? 0) ? (int) $b['owner_user_id'] : sx_owner_for_office($officeId);
    //  Never hand a lead to someone who cannot open that office's leads.
    if ($owner && $officeId && !sx_user_covers_office($owner, $officeId)) {
        if ((int) ($b['owner_user_id'] ?? 0) === $owner) return ['err' => 'That person does not work for this office, so they could not open the lead. Pick someone of this office.'];
        $owner = (int) ($u['id'] ?? 0);
    }
    $next = trim((string) ($b['next_action_on'] ?? ''));
    if ($next !== '' && !sx_is_date($next)) return ['err' => 'The first follow-up date is not a real date.'];
    if ($next !== '' && $next < date('Y-m-d')) return ['err' => 'The first follow-up cannot be in the past.'];
    if ($next === '') $next = sx_add_working_days(1, $officeId);
    $r = lead_create([
        'company_name' => $company, 'contact_name' => trim((string) ($b['contact_name'] ?? '')), 'contact_phone' => $phone,
        'contact_email' => trim((string) ($b['contact_email'] ?? '')), 'source' => (string) ($b['source'] ?? ''),
        'value' => max(0, (float) str_replace(',', '', (string) ($b['value'] ?? 0))), 'office_id' => $officeId ?: '',
        'owner_user_id' => $owner ?: '', 'next_action_on' => $next,
        'next_action' => trim((string) ($b['next_action'] ?? '')) ?: 'First call',
    ]);
    if (!empty($r['err'])) return ['err' => $r['err']];
    return ['ok' => true, 'id' => (int) $r['id'], 'ref' => (string) ($r['ref'] ?? ''), 'owner' => $owner, 'next' => $next];
}

// ---- Log activity --------------------------------------------------------------
function sx_log_target() {
    if (($id = (int) ($_GET['lead'] ?? $_POST['lead'] ?? 0)) > 0) {
        $l = lead_row($id); if (!$l) return null;
        return ['kind' => 'LEAD', 'id' => $id, 'row' => $l, 'name' => (string) $l['company_name'], 'ref' => (string) $l['ref'],
                'stage' => (string) ($l['stage_name'] ?? ''), 'phone' => (string) ($l['contact_phone'] ?? ''), 'who' => (string) ($l['contact_name'] ?? ''),
                'office' => (int) ($l['office_id'] ?? 0), 'url' => '/lead?id=' . $id, 'can' => leads_can_edit() && (!function_exists('leads_works_leads') || leads_works_leads()),
                'owner' => (int) ($l['owner_user_id'] ?? 0),
                'open' => ($l['status'] ?? '') === 'OPEN', 'next' => (string) ($l['next_action'] ?? ''), 'next_on' => (string) ($l['next_action_on'] ?? '')];
    }
    if (($id = (int) ($_GET['deal'] ?? $_POST['deal'] ?? 0)) > 0) {
        $o = opp_row($id); if (!$o) return null;
        return ['kind' => 'OPPORTUNITY', 'id' => $id, 'row' => $o, 'name' => (string) ($o['client_name'] ?: $o['partner_name'] ?: $o['name']), 'ref' => (string) $o['ref'],
                'stage' => (string) ($o['stage_name'] ?? ''), 'phone' => (string) ($o['contact_phone'] ?? ''), 'who' => (string) ($o['contact_name'] ?? ''),
                'office' => (int) ($o['office_id'] ?? 0), 'url' => '/opportunity?id=' . $id, 'can' => opp_can_edit() && (!function_exists('leads_works_leads') || leads_works_leads()),
                'owner' => (int) ($o['owner_user_id'] ?? 0),
                'open' => ($o['status'] ?? '') === 'OPEN', 'next' => (string) ($o['next_action'] ?? ''), 'next_on' => (string) ($o['next_action_on'] ?? '')];
    }
    return null;
}
function sx_log_save(array $t, array $b) {
    $method = (string) ($b['method'] ?? 'CALL');
    if (!isset(LEAD_CONTACT_METHODS[$method])) $method = 'CALL';
    $outs = sx_outcomes(); $oc = (string) ($b['outcome'] ?? '');
    if ($oc !== '' && !isset($outs[$oc])) $oc = '';
    $note = trim((string) ($b['note'] ?? ''));
    if ($oc === '' && $note === '') return ['err' => 'Pick what happened, or write a line about it.'];
    $nextOn = trim((string) ($b['next_action_on'] ?? ''));
    if ($nextOn !== '' && !sx_is_date($nextOn)) return ['err' => 'The next follow-up date is not a real date.'];
    if ($nextOn === '' && $oc !== '' && $outs[$oc]['days'] > 0) $nextOn = sx_add_working_days($outs[$oc]['days'], $t['office']);
    //  "0 days" in Sales settings means the person picks the date (e.g. the meeting's own date).
    if ($nextOn === '' && $oc !== '' && $outs[$oc]['days'] === 0 && !empty($t['open']))
        return ['err' => 'For "' . $outs[$oc]['label'] . '" pick the date in "By when" (for a meeting: the meeting date).'];
    if ($nextOn !== '' && $nextOn < date('Y-m-d')) return ['err' => 'The next follow-up cannot be in the past.'];
    $nextAct = trim((string) ($b['next_action'] ?? ''));
    if ($nextAct === '' && $nextOn !== '') $nextAct = 'Follow up';
    $outLabel = $oc !== '' ? $outs[$oc]['label'] : '';
    //  The "More details" of the one form: which way, when, time it took.
    $dir = in_array(($b['direction'] ?? 'OUT'), ['IN', 'OUT'], true) ? (string) ($b['direction'] ?? 'OUT') : 'OUT';
    $when = trim((string) ($b['occurred_on'] ?? ''));
    if ($when !== '' && !sx_is_date($when)) return ['err' => 'When it happened is not a real date.'];
    if ($when !== '' && $when > date('Y-m-d')) return ['err' => 'When it happened cannot be in the future.'];
    $mins = max(0, min(1440, (int) ($b['time_spent'] ?? 0)));
    if ($t['kind'] === 'LEAD') {
        $r = lead_log_contact($t['id'], ['method' => $method, 'note' => $note, 'outcome' => $outLabel, 'with_whom' => trim((string) ($b['with_whom'] ?? '')),
                                         'next_action' => $nextAct, 'next_action_on' => $nextOn, 'direction' => $dir,
                                         'occurred_on' => $when, 'time_spent' => $mins, 'pref_contact' => (string) ($b['pref_contact'] ?? '')]);
        if (!empty($r['err'])) return $r;
    } else {
        $o = $t['row'];
        $who = trim((string) ($b['with_whom'] ?? ''));
        //  The line's title: who it was with, else what happened, else the first words of the note.
        $subj = $who !== '' ? $who : ($outLabel !== '' ? $outLabel : mb_substr(strtok($note, "\n") ?: '', 0, 80));
        act_log('OPPORTUNITY', $t['id'], $method, $subj !== '' ? $subj : LEAD_CONTACT_METHODS[$method], [
            'body' => $note, 'outcome' => $outLabel, 'with_whom' => $who, 'direction' => $dir, 'duration_mins' => $mins,
            'occurred_at' => ($when === '' || $when === date('Y-m-d')) ? date('c') : $when . 'T12:00:00',
            'partner_id' => ($o['partner_id'] ?? null) ?: null, 'office_id' => $o['office_id'] ?? '', 'sbu' => $o['sbu'] ?? '']);
        if ($nextAct !== '' || $nextOn !== '') {
            try {
                $aid = (int) ops_val("SELECT MAX(id) FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND COALESCE(auto,0)=0", [$t['id']]);
                if ($aid) db()->prepare("UPDATE activities SET next_step=?, next_step_on=? WHERE id=?")->execute([mb_substr($nextAct, 0, 255), $nextOn, $aid]);
            } catch (Throwable $e) {}
            db()->prepare("UPDATE opportunities SET next_action=?, next_action_on=?, updated_at=? WHERE id=?")
                ->execute([mb_substr($nextAct, 0, 255), $nextOn, date('c'), $t['id']]);
        }
    }
    return ['ok' => true, 'next' => $nextOn, 'next_action' => $nextAct];
}
//  A real calendar date (30 Feb is refused, not rolled into March).
function sx_is_date($d) {
    return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

//  A return address from the page itself: only a path on this site (a
//  "#section" is allowed), never another site — else the default.
function sx_safe_back($back, $default) {
    $back = (string) $back;
    return ($back !== '' && preg_match('~^/[a-z0-9\-/?=&%._#]*$~i', $back) && strpos($back, '//') === false) ? $back : $default;
}
//  Where "📝 Log" goes: the record's own page, its log form open, and back
//  to where the person came from once saved.
function sx_log_url($kind, $id, $back = '/sales-today') {
    $q = '&log=1&back=' . rawurlencode((string) $back);
    return $kind === 'LEAD' ? '/lead?id=' . (int) $id . $q . '#lead=contacts' : '/opportunity?id=' . (int) $id . $q . '#log';
}

// ---- Orders & payments -------------------------------------------------------------
//  Accepted quotations (= confirmed orders) → what has been invoiced against
//  them → what has been received. Billing figures only for people who may see
//  receivables (ar_can), exactly as the receivables screen decides.
function sx_orders($who, $from, $to) {
    $rows = [];
    try {
        $rows = ops_all("SELECT q.*, COALESCE(NULLIF(bp.display_name,''), bp.legal_name, q.client_name) cname FROM quotations q
                         LEFT JOIN business_partners bp ON bp.id=q.client_id
                         WHERE COALESCE(q.is_current,1)=1 AND q.status='ACCEPTED'
                           AND substr(COALESCE(NULLIF(q.accepted_date,''), q.updated_at, ''),1,10) BETWEEN ? AND ?
                         ORDER BY COALESCE(NULLIF(q.accepted_date,''), q.updated_at) DESC", [$from, $to]) ?: [];
    } catch (Throwable $e) { return []; }
    if ($who) $rows = array_values(array_filter($rows, fn($q) => (int) ($q['owner_id'] ?? 0) === $who));
    //  Office scope: the quotation register's own rule, when the office is set.
    if (function_exists('scope_allows')) $rows = array_values(array_filter($rows, fn($q) => empty($q['office_id']) || scope_allows((int) $q['office_id'])));
    //  Q10 — "own" / "team" (owner, 21 Nov 2026): the order's quotation owner, as on its register.
    if (function_exists('sales_rec_ids') && sales_rec_ids() !== null && function_exists('crm_quote_owner_user_id'))
        $rows = array_values(array_filter($rows, fn($q) => sales_owner_allows((int) (crm_quote_owner_user_id($q) ?? 0), false)));
    $ar = function_exists('ar_can') && ar_can();
    foreach ($rows as &$q) {
        $q['_billed'] = null; $q['_recv'] = null; $q['_cash'] = null; $q['_tds'] = null; $q['_credit'] = null; $q['_out'] = null; $q['_invs'] = [];
        //  DEF-06 (owner, 21 Nov 2026, option A): the order's value BEFORE GST is the
        //  main figure; the total with GST is shown beside it.
        $q['_value'] = function_exists('crm_quote_before_gst') ? crm_quote_before_gst($q) : (float) ($q['subtotal'] ?? $q['total_amount'] ?? 0);
        if (!$ar) continue;
        $args = [(int) $q['id']]; $w = 'quotation_id=?';
        if (trim((string) ($q['contract_number'] ?? '')) !== '') { $w .= ' OR (partner_id=? AND contract_number=?)'; $args[] = (int) $q['client_id']; $args[] = $q['contract_number']; }
        try {
            $invs = ops_all("SELECT id, invoice_no, invoice_date, due_date, total, status FROM invoices WHERE ($w) AND status NOT IN ('DRAFT','CANCELLED')", $args) ?: [];
        } catch (Throwable $e) { $invs = []; }
        $b = 0.0; $cash = 0.0; $tds = 0.0; $cn = 0.0;
        //  DEF-07 (owner, 21 Nov 2026, option A; Finance: "some payments are without TDS
        //  and some deduct TDS"): RECEIVED = the payments received PLUS the TDS the
        //  customer actually deducted on those receipts (a receipt with no TDS adds
        //  none — no rate is ever assumed). Credit notes reduce what is owed but are
        //  not money received, so they are kept apart. books_settled() is the same
        //  breakdown the ledger and the ageing use.
        foreach ($invs as $i) {
            $b += (float) $i['total'];
            if (!function_exists('books_settled')) continue;
            $st = books_settled((int) $i['id']);
            if (!is_array($st)) continue;
            $cash += (float) ($st['cash'] ?? 0); $tds += (float) ($st['tds'] ?? 0); $cn += (float) ($st['credit'] ?? 0);
        }
        $q['_billed'] = $b; $q['_cash'] = round($cash, 2); $q['_tds'] = round($tds, 2);
        $q['_recv'] = round($cash + $tds, 2); $q['_credit'] = round($cn, 2);
        $q['_out'] = round(max(0.0, $b - $cash - $tds - $cn), 2);
        $q['_invs'] = $invs;
    }
    unset($q);
    return $rows;
}
//  Average days from invoice to the last payment, invoices fully paid in the last 180 days.
//  DEF-08 (21 Nov 2026): it was company-wide whatever the person and branch. Now:
//  $invIds = the invoices of the orders on screen (a person picked) — the same
//  match as the "Received" column; null = every invoice the person's branches
//  may see, by the invoice register's own rule (scope_clause on office + unit).
function sx_avg_days_to_pay($invIds = null) {
    $w = ''; $a = [date('c', strtotime('-180 days'))];
    if (is_array($invIds)) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $invIds))));
        if (!$ids) return null;
        $w = ' AND i.id IN (' . implode(',', $ids) . ')';
    } elseif (function_exists('scope_clause')) {
        [$sw, $sa] = scope_clause('i.office_id', 'i.sbu');
        $w = " AND $sw"; $a = array_merge($a, $sa);
    }
    try {
        $rows = ops_all("SELECT i.invoice_date d, MAX(a.allocated_at) p FROM invoices i JOIN receipt_allocations a ON a.invoice_id=i.id
                         WHERE i.status='PAID' AND a.allocated_at>=?$w GROUP BY i.id, i.invoice_date", $a) ?: [];
    } catch (Throwable $e) { return null; }
    $n = 0; $s = 0;
    foreach ($rows as $r) { $a = strtotime(substr((string) $r['d'], 0, 10)); $b = strtotime(substr((string) $r['p'], 0, 10)); if ($a && $b && $b >= $a) { $s += ($b - $a) / 86400; $n++; } }
    return $n ? (int) round($s / $n) : null;
}

// ---- Renewals -----------------------------------------------------------------
//  Contracts by days left, including those that ended in the last 30 days
//  (still worth a call). $days = how far ahead to look.
//  DEF-10 (owner, 21 Nov 2026): only contracts that are OPEN — not waiting to be
//  opened, rejected or closed — and one look-ahead window for every Sales screen
//  (Renewals, Today, Sales home), set in Sales settings (sx_renewal_days()).
function sx_renewals_list($days = null) {
    $days = $days === null ? sx_renewal_days() : (int) $days;
    $t = date('Y-m-d');
    try {
        if (function_exists('contracts_migrate')) contracts_migrate();
        return sx_scope_contracts(ops_all("SELECT c.*, COALESCE(NULLIF(bp.display_name,''), bp.legal_name) client_name, bp.home_branch_id client_branch FROM partner_contracts c
                        LEFT JOIN business_partners bp ON bp.id=c.partner_id
                        WHERE COALESCE(c.is_active,1)=1 AND COALESCE(NULLIF(c.open_status,''),'OPEN')='OPEN'
                          AND COALESCE(c.end_date,'')<>'' AND c.end_date>=? AND c.end_date<=? ORDER BY c.end_date",
                        [date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime("+$days days"))]) ?: []);
    } catch (Throwable $e) { return []; }
}

//  The look-ahead window, in days. A setting (Sales settings → "Renewals: look ahead");
//  60 is the starting value the owner chose, marked as an example until changed.
const SX_RENEWAL_DAYS_DEFAULT = 60;
function sx_renewal_days() {
    $v = (int) (function_exists('setting_get') ? setting_get('sales_renewal_days', '') : 0);
    return $v >= 7 && $v <= 365 ? $v : SX_RENEWAL_DAYS_DEFAULT;
}
function sx_renewal_days_is_example() { return (string) (function_exists('setting_get') ? setting_get('sales_renewal_days', '') : '') === ''; }
//  Contract money (owner Q2, 21 Nov 2026): a contract's value is shown on the
//  Renewals list only to people the contract page shows it to.
function sx_contract_money_can() {
    return is_master() || (function_exists('can') && (can('data.credit') || can('data.revenue') || can('finance.reconcile')));
}
//  Owner Q3, 21 Nov 2026: a renewal call is logged on the client's own timeline,
//  naming the contract. Whoever may log a conversation on a lead or a deal may log
//  this one; the contract must be one of the person's branches.
function sx_renewal_log_can() {
    return (function_exists('leads_can_edit') && leads_can_edit() && (!function_exists('leads_works_leads') || leads_works_leads()))
        || (function_exists('opp_can_edit') && opp_can_edit() && (!function_exists('leads_works_leads') || leads_works_leads()));
}
function sx_renewal_log(array $b) {
    $id = (int) ($b['contract_id'] ?? 0);
    $c = $id ? ops_one("SELECT c.*, bp.home_branch_id client_branch FROM partner_contracts c LEFT JOIN business_partners bp ON bp.id=c.partner_id WHERE c.id=?", [$id]) : null;
    if (!$c || !(int) $c['partner_id']) return ['err' => 'That contract was not found.'];
    if (!sx_scope_contracts([$c])) return ['err' => 'That contract belongs to another office / branch.'];
    $note = trim((string) ($b['note'] ?? ''));
    if ($note === '') return ['err' => 'Write what was said — one line is enough.'];
    $method = isset(ACT_KINDS[$b['method'] ?? '']) && in_array($b['method'], ['CALL', 'MEETING', 'VISIT', 'EMAIL', 'WHATSAPP'], true) ? $b['method'] : 'CALL';
    $no = trim((string) ($c['contract_number'] ?? '')) ?: ('#' . $id);
    if (!function_exists('act_log')) return ['err' => 'The client timeline is not available here.'];
    act_log('PARTNER', (int) $c['partner_id'], $method, 'Renewal — contract ' . $no . ($c['end_date'] ? ' (ends ' . fdate($c['end_date']) . ')' : ''),
            ['body' => mb_substr($note, 0, 2000), 'partner_id' => (int) $c['partner_id'], 'outcome' => mb_substr(trim((string) ($b['outcome'] ?? '')), 0, 60)]);
    return ['ok' => true, 'no' => $no];
}

// ---- Targets ------------------------------------------------------------------------
//  Foundation A (Q2): "Set sales targets for others" is its own switch. On day one
//  it is on for exactly the people who had it — a management role holding Sales
//  reports (docs/02 19r). A Master Admin still needs Sales reports switched on.
function sxt_can_set() {
    if (is_master()) return !function_exists('crmdash_can') || crmdash_can();
    return can('crm.targets.set') && (!function_exists('licence_module_live') || licence_module_live('crm_reports'));
}
//  The FY's months as YYYY-MM. DEF-11 (owner, 21 Nov 2026): any financial year may
//  be picked (the year picker); the active year when none is asked for.
function sxt_fy($asked = null) {
    $asked = trim((string) $asked);
    return preg_match('/^\d{4}(-\d{2})?$/', $asked) ? $asked : (function_exists('active_fy') ? active_fy() : date('Y'));
}
function sxt_fy_options() {
    $cur = (int) substr(sxt_fy(), 0, 4); $out = [];
    for ($y = $cur + 1; $y >= $cur - 4; $y--) $out[] = function_exists('fy_label') ? fy_label($y) : (string) $y;
    return $out;
}
function sxt_months($fy = null) {
    try { [$fa] = fy_range(sxt_fy($fy)); } catch (Throwable $e) { $fa = date('Y') . '-04-01'; }
    $out = []; $t = strtotime(substr($fa, 0, 7) . '-01');
    for ($i = 0; $i < 12; $i++) $out[] = date('Y-m', strtotime("+$i months", $t));
    return $out;
}
//  Latest target per person and month (an earlier figure stays as history).
function sxt_grid(array $months) {
    sx_more_migrate();
    $g = [];
    if (!$months) return $g;
    $in = implode(',', array_fill(0, count($months), '?'));
    //  DEF-11: a box cleared on purpose is stored as a row with NO amount — "no
    //  target" — so it reads as empty, not as a target of ₹0, and the history keeps it.
    foreach (ops_all("SELECT * FROM sales_targets WHERE ym IN ($in) ORDER BY id", $months) ?: [] as $r) {
        if ($r['amount'] === null) unset($g[(int) $r['user_id']][$r['ym']]);
        else $g[(int) $r['user_id']][$r['ym']] = (float) $r['amount'];
    }
    return $g;
}
function sxt_target_total($who, $from, $to) {
    sx_more_migrate();
    $months = [];
    $t = strtotime(substr($from, 0, 7) . '-01'); $end = substr($to, 0, 7);
    for ($i = 0; $i < 36 && date('Y-m', $t) <= $end; $i++) { $months[] = date('Y-m', $t); $t = strtotime('+1 month', $t); }
    $g = sxt_grid($months); $sum = 0.0;
    foreach ($g as $uid => $row) if (!$who || $uid === (int) $who) $sum += array_sum($row);
    return $sum;
}
//  Save only the cells that changed; each change is a new row (history kept).
//  Targets are TYPED in ₹ lakh on the page (owner, 21 Nov 2026: "Yes in lakhs but
//  mention somewhere that amount to be input in Lakhs") and STORED in rupees as
//  before: 12.5 → ₹12,50,000. History, e-mails, the dashboard and every other
//  screen keep reading rupees. A form that says unit=lakh is converted here; one
//  without it (older callers) is rupees, unchanged.
const SXT_LAKH = 100000;
function sxt_lakh_text($rupees) {
    if ($rupees === null) return '';
    return rtrim(rtrim(number_format((float) $rupees / SXT_LAKH, 5, '.', ''), '0'), '.');
}
function sxt_from_lakh($v) {
    $v = trim(str_replace([',', '₹', ' '], '', (string) $v));
    if ($v === '' || !is_numeric($v)) return $v;              // empty = no target; text → refused below
    return (string) (0 + round((float) $v * SXT_LAKH));
}
function sxt_save(array $post, array $months) {
    sx_more_migrate();
    if (($post['unit'] ?? '') === 'lakh') {
        foreach (['t', 'o'] as $k) foreach ((array) ($post[$k] ?? []) as $uid => $row)
            foreach ((array) $row as $ym => $v) $post[$k][$uid][$ym] = sxt_from_lakh($v);
    }
    $cur = sxt_grid($months); $n = 0; $me = user_name(current_user()); $changed = [];
    $people = sxt_people_for_setter(sx_target_people());
    $GLOBALS['sxt_skipped'] = ['bad' => [], 'clash' => []];
    $fmt = fn($x) => $x === null ? '' : (string) (0 + $x);
    foreach ((array) ($post['t'] ?? []) as $uid => $row) {
        $uid = (int) $uid; if (!isset($people[$uid])) continue;
        foreach ((array) $row as $ym => $v) {
            if (!in_array($ym, $months, true)) continue;
            $v = trim(str_replace([',', '₹', ' '], '', (string) $v));
            $label = $people[$uid] . ' ' . date('M Y', strtotime($ym . '-01'));
            //  The figure the page showed when it was opened. Unchanged on this
            //  page → leave it alone (someone else may have changed it since).
            $shown = isset($post['o'][$uid][$ym]) ? trim((string) $post['o'][$uid][$ym]) : null;
            if ($shown !== null && $v === $shown) continue;
            if ($v === '' && !isset($cur[$uid][$ym])) continue;
            //  DEF-11: emptying a box that had a figure means "no target" — a new row
            //  with no amount; the earlier figure stays in the history.
            if ($v === '') {
                if ($shown !== null && $fmt($cur[$uid][$ym] ?? null) !== $shown) { $GLOBALS['sxt_skipped']['clash'][] = $label; continue; }
                $prev = (float) $cur[$uid][$ym];
                db()->prepare("INSERT INTO sales_targets (user_id, ym, amount, prev_amount, set_by, set_at, seen_at) VALUES (?,?,NULL,?,?,?,'')")
                    ->execute([$uid, $ym, $prev, $me, date('c')]);
                $changed[$uid][] = [$ym, $prev, null];
                $n++;
                continue;
            }
            //  Whole rupees, never negative, never text.
            if ($v !== '' && (!is_numeric($v) || (float) $v < 0)) { $GLOBALS['sxt_skipped']['bad'][] = $label; continue; }
            $amt = (float) round((float) $v);
            if (isset($cur[$uid][$ym]) && abs($cur[$uid][$ym] - $amt) < 0.005) continue;
            //  Changed here, but also changed by someone else since this page was opened.
            if ($shown !== null && $fmt($cur[$uid][$ym] ?? null) !== $shown) { $GLOBALS['sxt_skipped']['clash'][] = $label; continue; }
            if (!isset($cur[$uid][$ym]) && $amt == 0.0) continue;
            $prev = isset($cur[$uid][$ym]) ? (float) $cur[$uid][$ym] : null;
            db()->prepare("INSERT INTO sales_targets (user_id, ym, amount, prev_amount, set_by, set_at, seen_at) VALUES (?,?,?,?,?,?,'')")
                ->execute([$uid, $ym, $amt, $prev, $me, date('c')]);
            $changed[$uid][] = [$ym, $prev, $amt];
            $n++;
        }
    }
    $GLOBALS['sxt_mail_notes'] = [];
    foreach ($changed as $uid => $rows) $GLOBALS['sxt_mail_notes'][] = sxt_mail_change((int) $uid, $rows, $me);
    return $n;
}
//  One e-mail per person per save, listing every month that changed.
//  Returns one plain sentence for the person who saved: whether it went, and if
//  not, why (the same reason the Notification log records).
function sxt_mail_change($uid, array $rows, $by) {
    $u = ops_one("SELECT * FROM users WHERE id=? AND is_active=1", [(int) $uid]);
    $nm = $u ? user_name($u) : 'that person';
    if (!function_exists('ops_mail')) return "Could not e-mail $nm: e-mail is not available here.";
    $to = trim((string) ($u['email'] ?? ''));
    if (!$u || $to === '') return "Could not e-mail $nm: no e-mail address on their login (Admin → Users).";
    $lines = [];
    foreach ($rows as [$ym, $prev, $amt])
        $lines[] = date('M Y', strtotime($ym . '-01')) . ': ' . ($amt === null ? sx_money($prev) . ' → no target'
                 : ($prev === null ? 'set to ' . sx_money($amt) : sx_money($prev) . ' → ' . sx_money($amt)));
    $body = 'Hello ' . user_name($u) . ",\n\nYour sales target was changed by $by on " . date('j M Y') . ":\n\n  " . implode("\n  ", $lines)
          . "\n\nSee all your targets under Sales → Targets.\n";
    try { $ok = ops_mail($to, 'Your sales target was changed', $body, '', 'sales_target'); }
    catch (Throwable $e) { return "Could not e-mail $nm: " . $e->getMessage(); }
    if ($ok) return "E-mailed $nm.";
    $why = (string) (ops_val("SELECT error FROM email_log ORDER BY id DESC LIMIT 1") ?: 'see Settings → Notification log');
    return "Could not e-mail $nm: $why.";
}
//  Changes to MY targets I have not yet acknowledged (Sales home notice).
function sxt_unseen_for($uid) {
    sx_more_migrate();
    try { return ops_all("SELECT * FROM sales_targets WHERE user_id=? AND COALESCE(seen_at,'')='' AND set_by<>? ORDER BY ym, id",
                         [(int) $uid, user_name(ops_one("SELECT * FROM users WHERE id=?", [(int) $uid]) ?: [])]) ?: []; }
    catch (Throwable $e) { return []; }
}
//  A month whose target has been changed: its earlier figures, newest first.
function sxt_changes(array $months) {
    sx_more_migrate();
    $out = [];
    if (!$months) return $out;
    $in = implode(',', array_fill(0, count($months), '?'));
    foreach (ops_all("SELECT * FROM sales_targets WHERE ym IN ($in) ORDER BY id", $months) ?: [] as $r) $out[(int) $r['user_id']][$r['ym']][] = $r;
    return $out;
}
//  DEF-11 (owner, 21 Nov 2026, A): a setter who is not management sets targets only
//  for the people of their own branches (the person's home branch within the
//  setter's office scope). Management and the Master Admin set them for anyone.
function sxt_people_for_setter(array $people) {
    if (!function_exists('sx_can_see_all') || sx_can_see_all() || is_master()) return $people;
    if (!function_exists('scope_offices') || scope_offices() === 'ALL') return $people;
    $out = [];
    foreach ($people as $uid => $nm) {
        $home = (int) (ops_val("SELECT COALESCE(home_office_id,0) FROM users WHERE id=?", [(int) $uid]) ?: 0);
        if ($home && function_exists('scope_office_allows') && scope_office_allows($home)) $out[$uid] = $nm;
    }
    return $out;
}
//  Who can carry a target: the Sales roles plus anyone who owns a lead or deal.
function sx_target_people() {
    $p = sx_people();
    try {
        $roles = defined('SALES_ROLES') ? SALES_ROLES : [];
        if ($roles) {
            $in = implode(',', array_fill(0, count($roles), '?'));
            foreach (ops_all("SELECT * FROM users WHERE is_active=1 AND role IN ($in)", array_values($roles)) ?: [] as $u) $p[(int) $u['id']] = user_name($u);
        }
    } catch (Throwable $e) {}
    asort($p);
    return $p;
}
//  Won value per person per month (the achievement row of the grid).
function sxt_won(array $months) {
    $w = [];
    foreach (function_exists('opp_all') ? opp_all(['status' => 'WON']) : [] as $o) {
        $ym = substr((string) $o['won_at'], 0, 7);
        if (in_array($ym, $months, true)) $w[(int) $o['owner_user_id']][$ym] = ($w[(int) $o['owner_user_id']][$ym] ?? 0) + (float) $o['value'];
    }
    return $w;
}

// ---- Quote: CGST + SGST or IGST (display only) --------------------------------------
//  The quotation keeps ONE GST figure. This only shows how that figure splits
//  on the invoice, using the same rule the invoice uses (books_is_igst). No
//  total changes. Unknown state → says so instead of guessing.
function sx_quote_gst_split(array $q) {
    $amt = (float) ($q['gst_amount'] ?? 0); $pct = (float) ($q['gst_pct'] ?? 0);
    if ($amt <= 0 || empty($q['client_id']) || !function_exists('books_is_igst')) return null;
    $ours = function_exists('books_our_state') ? (string) books_our_state($q['office_id'] ?? null) : '';   // the branch's own state when it has its own GSTIN
    //  A customer billed at one of its other states (a billing location) is taxed by
    //  that state (owner, 21 Nov 2026: D1).
    $loc = function_exists('custid_location_id_for') ? custid_location_id_for((int) $q['client_id'], (int) ($q['billing_address_id'] ?? 0)) : 0;
    $theirs = function_exists('books_their_state') ? (string) books_their_state((int) $q['client_id'], $loc) : '';
    //  Say which state is missing: ours (the invoice's own setting) or the client's.
    if ($ours === '' || $theirs === '') return ['mode' => 'UNKNOWN', 'rows' => [], 'missing' => $ours === '' ? 'ours' : 'theirs'];
    $nm = function ($c) { $m = defined('GST_STATES') ? GST_STATES : []; return (string) ($m[$c] ?? $m[str_pad($c, 2, '0', STR_PAD_LEFT)] ?? $c); };
    $ours = $nm($ours); $theirs = $nm($theirs);
    if (books_is_igst((int) $q['client_id'], $q['office_id'] ?? null, $loc))
        return ['mode' => 'IGST', 'rows' => [['IGST ' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '% · ' . Tl('client') . ' in ' . $theirs . ', another state', $amt]]];
    $h = round($amt / 2, 2); $hp = rtrim(rtrim(number_format($pct / 2, 2), '0'), '.');
    return ['mode' => 'CGST_SGST', 'rows' => [['CGST ' . $hp . '% · same state (' . $ours . ')', $h], ['SGST ' . $hp . '%', round($amt - $h, 2)]]];
}

// ---- Routes --------------------------------------------------------------------------
function ops_sales_more($route, $method) {
    ops_require(sx_can(), 'Sales is not open to you.');
    sx_more_migrate();
    if (function_exists('nav_under')) nav_under($route, 'sales');
    switch ($route) {
        case 'sales-lead':
            //  Owner, 26 Nov 2026 (R-04 / R-06): the same rule as "+ New lead" on the Leads
            //  register — the Branch Manager and the SBU Head read and download leads but never
            //  add them (owner, 21 Nov 2026), so this quick form refuses them too.
            ops_require(leads_can_edit() && (!function_exists('leads_works_leads') || leads_works_leads()),
                'Adding a lead needs the right to add leads. You can open and download the leads of your branch, but not add one.');
            $old = []; $dupes = []; $err = '';
            if ($method === 'POST') {
                $r = sx_lead_save($_POST, !empty($_POST['confirm']));
                if (!empty($r['ok'])) {
                    [$fn, $fsk] = function_exists('lead_attach_uploads') ? lead_attach_uploads((int) $r['id'], $_FILES['files'] ?? null, (string) ($_POST['file_kind'] ?? 'REQUIREMENT'), '') : [0, []];
                    $who = $r['owner'] && (int) $r['owner'] !== (int) (current_user()['id'] ?? 0) ? ' It is with ' . user_name(ops_one("SELECT * FROM users WHERE id=?", [$r['owner']])) . '.' : '';
                    flash('Lead ' . $r['ref'] . ' saved.' . $who . ' First follow-up ' . fdate($r['next']) . '.'
                        . ($fn ? ' ' . $fn . ' file(s) attached.' : '') . ($fsk ? ' Not attached: ' . implode(', ', $fsk) . '.' : ''), $fsk ? 'warning' : 'success');
                    redirect(!empty($_POST['another']) ? '/sales-lead' : '/lead?id=' . $r['id']);
                }
                $old = $_POST; $dupes = $r['dupes'] ?? []; $err = $r['err'] ?? '';
                if (!empty($_FILES['files']['name'][0])) $err = trim($err . ' Choose the file(s) again — a browser never keeps a chosen file when the page comes back.');
            }
            $u = current_user();
            view('ops/sx_lead', ['old' => $old, 'dupes' => $dupes, 'err' => $err,
                'sources' => lk_options_or('lead_source', LEAD_SOURCES), 'offices' => function_exists('offices_list') ? array_values(array_filter(offices_list(), fn($o) => !function_exists('scope_allows') || scope_allows((int) $o['id']))) : [],
                'myOffice' => (int) ($u['home_office_id'] ?? 0), 'people' => sx_can_see_all() ? sx_target_people() : [],
                'next' => sx_add_working_days(1, (int) ($u['home_office_id'] ?? 0))]);
            return true;

        case 'sales-log':
            //  ONE log form (owner, 19 Nov 2026): it lives on the lead and deal
            //  pages (views/ops/_log_form.php). This address only saves it, and
            //  sends a visit to the record's own form.
            $t = sx_log_target();
            if (!$t) { http_response_code(404); view('notfound'); return true; }
            //  The same branch boundary as opening the record itself, asked FIRST (as when
            //  opening it): a record of another office is not logged on from here either, and
            //  the person is told where it belongs rather than that they lack a right.
            ops_require(!function_exists('scope_office_allows') || scope_office_allows($t['office'] ?: null),
                'This ' . ($t['kind'] === 'LEAD' ? 'lead' : Tl('opportunity')) . ' belongs to another office / branch.');
            ops_require($t['can'], 'Logging on this record needs the right to edit it.');
            //  D2 (owner, 21 Nov 2026): only the person handling it, the people above them
            //  and an administrator log on a lead or deal; everybody else reads it.
            if (function_exists('sales_record_can_work') && !sales_record_can_work((int) $t['owner'])) {
                flash(sales_record_lock_words((int) $t['owner']), 'error'); redirect($t['url']);
            }
            $back = (string) ($_POST['back'] ?? $_GET['back'] ?? '/sales-today');
            $back = sx_safe_back($back, '/sales-today');
            if ($method === 'POST') {
                $r = sx_log_save($t, $_POST);
                if (!empty($r['ok'])) { flash('Logged on ' . $t['ref'] . '.' . ($r['next'] !== '' ? ' Next follow-up ' . fdate($r['next']) . '.' : (($r['next_action'] ?? '') !== '' ? ' Next step noted, with no date.' : ' No next step set.'))); redirect($back); }
                flash($r['err'], 'error');
            }
            redirect(sx_log_url($t['kind'], $t['id'], $back));
            return true;

        case 'sales-orders':
            $who = sx_person();
            //  Accounts follow every order of their offices, not only their own (they own none).
            if (!sx_can_see_all() && function_exists('ar_can') && ar_can()) $who = 0;
            $p = in_array($_GET['p'] ?? '', ['fy', 'quarter', 'month', '90'], true) ? $_GET['p'] : 'fy';
            [$from, $to, $plab] = sx_period($p);
            $orders = sx_orders($who, $from, $to);
            //  DEF-08: one person's list → the two collection figures follow that
            //  person's orders (their invoices); everyone → the person's branches.
            $invIds = null;
            if ($who) { $invIds = []; foreach ($orders as $o) foreach ($o['_invs'] ?? [] as $i) $invIds[] = (int) $i['id']; }
            $ar = function_exists('ar_can') && ar_can();
            //  Backend item 15 (owner, 21 Nov 2026): the orders on screen as a CSV. The
            //  billing and payment columns only for people who may see receivables —
            //  exactly as on the screen.
            if (($_GET['export'] ?? '') === 'csv') {
                $head = [T('quote'), 'Revision', T('client'), 'PO', 'PO date', 'Won on', 'Order before GST', 'Order with GST'];
                if ($ar) array_push($head, 'Billed (with GST)', 'Payments received', 'TDS deducted', 'Received (payments + TDS)', 'Credit notes', 'Still owed', 'To bill (with GST)', 'Invoices');
                $csv = [$head];
                foreach ($orders as $o) {
                    $row = [$o['quote_no'], (int) $o['rev'], $o['cname'], $o['po_number'] ?? '', $o['po_date'] ?? '',
                            substr((string) ($o['accepted_date'] ?: $o['updated_at']), 0, 10), round((float) $o['_value'], 2), round((float) $o['total_amount'], 2)];
                    if ($ar) array_push($row, round((float) $o['_billed'], 2), round((float) $o['_cash'], 2), round((float) $o['_tds'], 2), round((float) $o['_recv'], 2),
                                        round((float) $o['_credit'], 2), round((float) $o['_out'], 2), round(max(0, (float) $o['total_amount'] - (float) $o['_billed']), 2),
                                        implode(' ', array_column($o['_invs'] ?? [], 'invoice_no')));
                    $csv[] = $row;
                }
                csv_download('orders-and-payments-' . date('Y-m-d') . '.csv', $csv);
            }
            view('ops/sx_orders', ['rows' => $orders, 'p' => $p, 'plab' => $plab, 'ar' => $ar,
                'avgPay' => sx_avg_days_to_pay($invIds), 'who' => $who, 'whoKey' => $_GET['who'] ?? 'me', 'people' => sx_can_see_all() ? sx_people() : [], 'seeAll' => sx_can_see_all(),
                'recvMonth' => sx_received_this_month($invIds), 'collectHint' => $who ? 'these orders' : (sx_scope_is_all() ? 'all customers' : 'your branches'),
                //  "Overdue" follows the person picked too (owner, 21 Nov 2026): only the
                //  invoices of the orders on screen. Everyone → the person's branches, as before.
                'overdue' => sx_overdue_for($invIds)]);
            return true;

        case 'sales-renewals':
            if ($method === 'POST' && ($_POST['do'] ?? '') === 'log') {
                ops_require(sx_renewal_log_can(), 'Logging a renewal call needs the right to work leads or deals.');
                $r = sx_renewal_log($_POST);
                flash(!empty($r['ok']) ? 'Logged on the ' . Tl('client') . '\'s timeline — contract ' . $r['no'] . '.' : $r['err'], !empty($r['ok']) ? 'success' : 'error');
                redirect('/sales-renewals' . (isset($_POST['d']) ? '?d=' . (int) $_POST['d'] : ''));
            }
            $def = sx_renewal_days();
            $opts = array_values(array_unique(array_merge([30, 60, 90, 180], [$def]))); sort($opts);
            $days = in_array((int) ($_GET['d'] ?? $def), $opts, true) ? (int) ($_GET['d'] ?? $def) : $def;
            //  R-03 (owner, 26 Nov 2026): a card narrows the list to its own band.
            $band = in_array((string) ($_GET['band'] ?? ''), ['late', 'm', 'later'], true) ? (string) $_GET['band'] : '';
            view('ops/sx_renewals', ['rows' => sx_renewals_list($days), 'days' => $days, 'opts' => $opts, 'def' => $def, 'band' => $band,
                'example' => sx_renewal_days_is_example(), 'money' => sx_contract_money_can(), 'canLog' => sx_renewal_log_can(),
                'canQuote' => can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'),
                'canContract' => is_master() || can('mod.clients.view') || can('mod.vendors.view') || can('crm.contract.register') || can('data.credit') || can('finance.reconcile')]);   // ops_contract_360()'s own gate
            return true;

        case 'sales-targets':
            $fy = sxt_fy($_GET['fy'] ?? $_POST['fy'] ?? null);
            $months = sxt_months($fy); $can = sxt_can_set();
            if ($method === 'POST' && !empty($_POST['seen'])) {
                //  The salesperson acknowledges the change notice on their Sales home.
                db()->prepare("UPDATE sales_targets SET seen_at=? WHERE user_id=? AND COALESCE(seen_at,'')=''")->execute([date('c'), (int) (current_user()['id'] ?? 0)]);
                redirect('/sales-home');
            }
            if ($method === 'POST') {
                ops_require($can, 'Only a sales manager sets targets.');
                $n = sxt_save($_POST, $months);
                $notes = $GLOBALS['sxt_mail_notes'] ?? [];
                $bad = array_values(array_filter($notes, fn($x) => strncmp($x, 'Could not', 9) === 0));
                $sk = $GLOBALS['sxt_skipped'] ?? ['bad' => [], 'clash' => []];
                if ($sk['bad']) $notes[] = $bad[] = 'Not saved — type a whole amount of 0 or more for: ' . implode(', ', $sk['bad']) . '.';
                if ($sk['clash']) $notes[] = $bad[] = 'Not saved — someone else changed these after you opened the page: ' . implode(', ', $sk['clash']) . '. Check the figures now shown and change them again if needed.';
                $back = '/sales-targets?fy=' . rawurlencode($fy);
                if (!$n && ($sk['bad'] || $sk['clash'])) { flash(implode(' ', $notes), 'warning'); redirect($back); }
                flash($n ? "$n target" . ($n === 1 ? '' : 's') . ' saved. Earlier figures stay in the history. ' . implode(' ', $notes) : 'Nothing changed.', $bad ? 'warning' : 'success');
                redirect($back);
            }
            $people = sx_target_people();
            $me = (int) (current_user()['id'] ?? 0);
            if (!$can) $people = isset($people[$me]) ? [$me => $people[$me]] : [];
            else $people = sxt_people_for_setter($people);
            //  DEF-11: the FULL history, a page at a time (it showed only the last 30),
            //  limited to the people on this page.
            $ids = array_map('intval', array_keys($people));
            $hw = $ids ? 't.user_id IN (' . implode(',', $ids) . ')' : '1=0';
            $per = 30; $hp = max(1, (int) ($_GET['hp'] ?? 1));
            $hn = (int) (ops_val("SELECT COUNT(*) FROM sales_targets t WHERE $hw") ?: 0);
            $hp = min($hp, max(1, (int) ceil($hn / $per)));
            view('ops/sx_targets', ['months' => $months, 'people' => $people, 'grid' => sxt_grid($months), 'won' => sxt_won($months), 'can' => $can, 'changes' => sxt_changes($months),
                'fy' => $fy, 'fys' => sxt_fy_options(), 'histN' => $hn, 'histPage' => $hp, 'histPer' => $per,
                'hist' => ops_all("SELECT t.*, u.first_name, u.last_name FROM sales_targets t LEFT JOIN users u ON u.id=t.user_id WHERE $hw ORDER BY t.id DESC LIMIT $per OFFSET " . (($hp - 1) * $per)) ?: []]);
            return true;

        case 'sales-settings':
            ops_require(sx_settings_can(), 'Sales settings need the "Change Sales settings" permission. Ask your administrator.');
            if ($method === 'POST') {
                $rows = [];
                foreach ((array) ($_POST['oc_label'] ?? []) as $i => $l) {
                    $l = trim((string) $l); if ($l === '') continue;
                    $k = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_POST['oc_key'][$i] ?? ''))) ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', $l), 0, 24));
                    //  A label in Hindi/Gujarati gives no letters for a key: number it instead.
                    $k = trim($k, '_'); if ($k === '') $k = 'OUTCOME_' . (count($rows) + 1);
                    $base = $k; $j = 2; while (in_array($k, array_column($rows, 'k'), true)) $k = $base . '_' . $j++;
                    $rows[] = ['k' => $k, 'label' => mb_substr($l, 0, 80), 'days' => max(0, min(365, (int) ($_POST['oc_days'][$i] ?? 0)))];
                }
                if (!$rows) { flash('Keep at least one outcome.', 'error'); redirect('/sales-settings'); }
                setting_set('sales_outcomes', json_encode($rows, JSON_UNESCAPED_UNICODE));
                setting_set('sales_weekly_off', implode(',', array_map('intval', array_filter((array) ($_POST['off'] ?? []), fn($d) => (int) $d >= 0 && (int) $d <= 6))));
                setting_set('sales_stale_days', (string) max(1, min(365, (int) ($_POST['stale'] ?? 15))));
                //  8a — the printed quotation title; blank = the industry's (quote_print_title()).
                if (isset($_POST['quote_print_title']))
                    setting_set('quote_print_title', mb_substr(trim(strip_tags((string) $_POST['quote_print_title'])), 0, 60));
                //  DEF-10 (owner, 21 Nov 2026): the one renewal look-ahead window, in days
                //  (Renewals, Today and Sales home). Saved only when the form sends it, so a
                //  settings form without the field leaves the window as it is.
                if (isset($_POST['renew_days']) && trim((string) $_POST['renew_days']) !== '')
                    setting_set('sales_renewal_days', (string) max(7, min(365, (int) $_POST['renew_days'])));
                $map = [];
                $skipped = [];
                foreach ((array) ($_POST['own'] ?? []) as $oid => $uid) {
                    if (!(int) $uid) continue;
                    //  Only someone who works for that office (their own office scope) can own its leads.
                    if (!sx_user_covers_office((int) $uid, (int) $oid)) { $skipped[] = (int) $oid; continue; }
                    $map[(string) (int) $oid] = (int) $uid;
                }
                setting_set('sales_owner_by_office', json_encode($map));
                if ($skipped) {
                    $nm = []; foreach ((function_exists('offices_list') ? offices_list() : []) as $o) if (in_array((int) $o['id'], $skipped, true)) $nm[] = $o['name'] ?? ('#' . $o['id']);
                    flash('Saved — but not for ' . implode(', ', $nm ?: $skipped) . ': the person picked does not work for that office (check their office access in Users).', 'warning');
                    redirect('/sales-settings');
                }
                //  setting_set() records who changed which setting on the audit chain.
                flash('Sales settings saved.');
                redirect('/sales-settings');
            }
            $pipe = pipeline_default('OPPORTUNITY');
            view('ops/sx_settings', ['renewDays' => sx_renewal_days(), 'renewExample' => sx_renewal_days_is_example(), 'outcomes' => sx_outcomes(), 'example' => sx_outcomes_are_example(), 'off' => sx_weekly_off(), 'stale' => sx_stale_days(),
                'map' => sx_owner_map(), 'offices' => function_exists('offices_list') ? offices_list() : [], 'people' => sx_target_people(),
                'stages' => $pipe ? pipeline_stages((int) $pipe['id']) : [], 'stageEditorOn' => !function_exists('feat_on') || feat_on('/pipelines')]);
            return true;
    }
    return false;
}
//  DEF-08 (21 Nov 2026): was every receipt in the company. Now $invIds (a person
//  picked) = cash received this month against the invoices of that person's
//  orders; null = receipts of the person's branches, by the receipt register's
//  own rule (scope_office_clause on the receipt's office).
function sx_received_this_month($invIds = null) {
    if (!function_exists('ar_can') || !ar_can()) return null;
    $from = date('Y-m-01'); $to = date('Y-m-d');
    try {
        if (is_array($invIds)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $invIds))));
            if (!$ids) return 0.0;
            return (float) ops_val("SELECT COALESCE(SUM(a.amount),0) FROM receipt_allocations a JOIN receipts r ON r.id=a.receipt_id
                                    WHERE a.kind='CASH' AND a.invoice_id IN (" . implode(',', $ids) . ") AND r.receipt_date>=? AND r.receipt_date<=?", [$from, $to]);
        }
        [$sw, $sa] = scope_office_clause('r.office_id');
        return (float) ops_val("SELECT COALESCE(SUM(r.amount),0) FROM receipts r WHERE r.receipt_date>=? AND r.receipt_date<=? AND $sw", array_merge([$from, $to], $sa));
    } catch (Throwable $e) { return null; }
}
//  Does the signed-in person see every branch? (Only changes a hint's wording.)
function sx_scope_is_all() {
    if (function_exists('is_master') && is_master()) return true;
    $off = function_exists('scope_offices') ? scope_offices() : 'ALL';
    return $off === 'ALL' || !is_array($off);
}
