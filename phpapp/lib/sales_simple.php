<?php
// ============================================================================
//  SIMPLE SALES — plan 42 step C, part 1 (owner, 18 Nov 2026)
// ============================================================================
//  "Pictures are ok … make it look more professional and it shall be clickable
//   and flow shall be same as excel with automatic data flowing as required."
//
//  The owner's Excel CRM (QA-REPORTS/V51/43-Excel-CRM-Design-Notes.md) is the
//  pattern: Sales home → Today → Pipeline board → Dashboard, with its KPI cards
//  and colour meaning (red overdue, amber today, yellow this week, green on
//  track, grey automatic). The look is EXAACT's.
//
//  NOTHING here is a second copy. Every figure is read from the records that
//  already exist — leads, opportunities (= deals), quotations, partner_contracts,
//  invoices (receivables ageing) — through their own readers (leads_all,
//  opp_all, opp_weighted, ar_rows, contract_warn_days …), so the office scope
//  of each register applies unchanged. Every row links to the existing screen
//  of its record. No new table, no new permission, no new status.
// ============================================================================

function sx_can() {
    if (function_exists('licence_enabled') && !licence_enabled('sales')) return false;
    return (function_exists('leads_can_view') && leads_can_view()) || (function_exists('opp_can_view') && opp_can_view());
}
//  A manager may look at anybody's list; everyone else sees their own.
//  Foundation A (owner, 21 Nov 2026, Q1): its own switch, "See other salespeople's
//  lists" — on day one exactly the management roles that had it (docs/02 19r).
function sx_can_see_all() { return is_master() || can('crm.sales.see_all'); }
//  Sales settings (Q9): their own switch, no longer "Manage system settings".
function sx_settings_can() { return is_master() || can('crm.settings.manage'); }
function sx_stale_days() { return max(1, (int) (setting_get('sales_stale_days', 15) ?? 15)); }
function sx_today() { return date('Y-m-d'); }
function sx_money($n) { return (function_exists('cur_sym') ? cur_sym() : '₹') . (function_exists('nf') ? nf((float) $n, 0) : number_format((float) $n)); }
//  Short form for a card that would not fit: ₹1.83 Cr / ₹18.4 L.
function sx_short($n) {
    $n = (float) $n; $s = function_exists('cur_sym') ? cur_sym() : '₹';
    if (abs($n) >= 1e7) return $s . rtrim(rtrim(number_format($n / 1e7, 2), '0'), '.') . ' Cr';
    if (abs($n) >= 1e5) return $s . rtrim(rtrim(number_format($n / 1e5, 1), '0'), '.') . ' L';
    return sx_money($n);
}
//  The person a list is for: 0 = everyone (managers only).
function sx_person() {
    $me = (int) (current_user()['id'] ?? 0);
    if (!sx_can_see_all()) {
        //  Owner, 21 Nov 2026: a sales manager sees their own list by default "but
        //  they can also see details of his reportee" — anyone below them in
        //  Reports to, never anyone else.
        $p = (string) ($_GET['who'] ?? 'me');
        if ($p !== '' && ctype_digit($p) && in_array((int) $p, sx_my_reportees(), true)) return (int) $p;
        return $me;
    }
    $p = $_GET['who'] ?? 'me';
    if ($p === 'all') return 0;
    if ($p === 'me' || $p === '') return $me;
    return (int) $p;
}
//  The people who report to me (the whole line below), for the "My team" picker.
//  None for a person set to "own records" (Q10): they would see nothing of them.
function sx_my_reportees() {
    $me = (int) (current_user()['id'] ?? 0);
    if (!$me || !function_exists('sales_reportee_ids')) return [];
    if (function_exists('sales_rec_mode') && sales_rec_mode() === 'own') return [];
    return sales_reportee_ids($me);
}
//  Owner, 26 Nov 2026 (R-11): "My team" is the people below me who do sales work — those
//  who own a lead or deal, or whose rights let them add / change leads or deals (the same
//  signal as leads_works_leads() for the person signed in). A coordinator or an assistant
//  manager in the reporting line, who never works a lead, is not listed with an empty list.
function sx_team_people() {
    $ids = sx_my_reportees(); if (!$ids) return [];
    $in = implode(',', array_map('intval', $ids)); $out = [];
    $owners = [];
    try { foreach (ops_all("SELECT DISTINCT owner_user_id u FROM leads WHERE owner_user_id IN ($in) UNION SELECT DISTINCT owner_user_id u FROM opportunities WHERE owner_user_id IN ($in)") ?: [] as $r) $owners[(int) $r['u']] = true; }
    catch (Throwable $e) {}
    try { foreach (ops_all("SELECT * FROM users WHERE id IN ($in) AND is_active=1 ORDER BY first_name, last_name") ?: [] as $u) {
        if (empty($owners[(int) $u['id']]) && !sx_user_works_sales($u)) continue;
        $out[(int) $u['id']] = user_name($u);
    } }
    catch (Throwable $e) {}
    return $out;
}
//  Does this user (a row, not the person signed in) work leads or deals?
function sx_user_works_sales(array $u) {
    if (!empty($u['is_superuser']) || strtoupper((string) ($u['role'] ?? '')) === 'MASTER_ADMIN') return true;
    if (!function_exists('user_effective_perms')) return true;
    $p = user_effective_perms($u);
    return (bool) array_intersect(['mod.leads.add', 'mod.leads.edit', 'crm.deal.edit'], $p);
}
function sx_people() {
    try {
        $ids = [];
        //  Only people who own leads or deals in offices I can see (a branch manager
        //  is not shown another branch's salespeople with empty counts).
        $sc = function_exists('scope_office_allows');
        foreach (ops_all("SELECT DISTINCT owner_user_id u, office_id o FROM leads WHERE owner_user_id IS NOT NULL UNION SELECT DISTINCT owner_user_id u, office_id o FROM opportunities WHERE owner_user_id IS NOT NULL") ?: [] as $r)
            if ((int) $r['u'] && (!$sc || scope_office_allows(((int) ($r['o'] ?? 0)) ?: null))) $ids[] = (int) $r['u'];
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $in = implode(',', array_map('intval', $ids));
        $out = [];
        foreach (ops_all("SELECT * FROM users WHERE id IN ($in) AND is_active=1 ORDER BY first_name, last_name") ?: [] as $u) $out[(int) $u['id']] = user_name($u);
        return $out;
    } catch (Throwable $e) { return []; }
}
//  When a follow-up is due, as the Excel colours it.
function sx_due_state($date, $today = null) {
    $today = $today ?: sx_today(); $d = substr((string) $date, 0, 10);
    if ($d === '') return 'none';
    if ($d < $today) return 'overdue';
    if ($d === $today) return 'today';
    if ($d <= date('Y-m-d', strtotime($today . ' +7 days'))) return 'week';
    return 'later';
}
function sx_due_label($date, $today = null) {
    $st = sx_due_state($date, $today); $today = $today ?: sx_today(); $d = substr((string) $date, 0, 10);
    if ($st === 'none') return ['❗ No next step', 'p-bad'];
    if ($st === 'overdue') { $n = (int) round((strtotime($today) - strtotime($d)) / 86400); return ['🔴 ' . $n . ' day' . ($n > 1 ? 's' : '') . ' late', 'p-bad']; }
    if ($st === 'today') return ['🟠 Today', 'p-warn'];
    return [($st === 'week' ? '🟡 ' : '') . date('D j M', strtotime($d)), $st === 'week' ? 'p-week' : 'p-mut'];
}
//  The open leads and deals of one person (0 = everyone), each with its due state.
function sx_open_items($who) {
    $leads = function_exists('leads_all') ? leads_all(['open' => 1] + ($who ? ['owner' => $who] : [])) : [];
    $deals = function_exists('opp_all') ? array_values(array_filter(opp_all(['open' => 1]), fn($o) => !$who || (int) $o['owner_user_id'] === $who)) : [];
    $leads = array_values(array_filter($leads, fn($l) => ($l['status'] ?? 'OPEN') === 'OPEN' && ($l['stage_kind'] ?? 'OPEN') === 'OPEN'));
    $deals = array_values(array_filter($deals, fn($o) => ($o['status'] ?? 'OPEN') === 'OPEN'));
    return [$leads, $deals];
}
//  Last time anybody did something on a deal (activities), else its stage date.
function sx_last_touch($kind, $id, $fallback) {
    try {
        $t = (string) (ops_val("SELECT MAX(occurred_at) FROM activities WHERE entity_kind=? AND entity_id=? AND COALESCE(auto,0)=0", [$kind, (int) $id]) ?: '');
        return $t !== '' ? $t : (string) $fallback;
    } catch (Throwable $e) { return (string) $fallback; }
}
function sx_days_since($ts) { $t = strtotime(substr((string) $ts, 0, 10)); return $t ? (int) floor((time() - $t) / 86400) : null; }

//  Overdue and soon-due invoices (receivables ageing — the register's own rows).
function sx_receivables() {
    if (!function_exists('ar_rows') || (function_exists('ar_can') && !ar_can())) return null;
    try { $rows = ar_rows('DUE'); } catch (Throwable $e) { return null; }
    //  R-05: the branch picked on this Sales screen (the rows carry the branch's name).
    $bn = sx_branch_name();
    if ($bn !== '' && is_array($rows)) $rows = array_values(array_filter($rows, fn($r) => (string) ($r['office_name'] ?? '') === $bn));
    return $rows;
}
//  The overdue list of Orders & payments. $invIds = the invoices of the orders on
//  screen when one person is picked (the same list "Received this month" and "Days
//  to get paid" follow, DEF-08); null = everyone, i.e. the person's branches. A row
//  from the older job-based register has no order behind it, so it is not one of
//  that person's orders and is not counted for them.
function sx_overdue_for($invIds = null) {
    $rows = sx_receivables();
    if ($rows === null || !is_array($invIds)) return $rows;
    $keep = array_flip(array_map('intval', $invIds));
    return array_values(array_filter($rows, fn($r) => !empty($r['from_books']) && isset($keep[(int) ($r['id'] ?? 0)])));
}
//  Contracts ending within the Sales look-ahead window. DEF-10 (owner, 21 Nov 2026):
//  ONE window for Renewals, Today and Sales home — Sales settings, starting at 60
//  days (sx_renewal_days()) — and only contracts that are open.
function sx_renewals($days = null) {
    $days = $days ?? (function_exists('sx_renewal_days') ? sx_renewal_days() : (function_exists('contract_warn_days') ? contract_warn_days() : 30));
    $t = sx_today(); $to = date('Y-m-d', strtotime("+$days days"));
    try {
        if (function_exists('contracts_migrate')) contracts_migrate();
        return sx_scope_contracts(ops_all("SELECT c.*, COALESCE(bp.display_name, bp.legal_name) client_name, bp.home_branch_id client_branch FROM partner_contracts c LEFT JOIN business_partners bp ON bp.id=c.partner_id
                        WHERE COALESCE(c.is_active,1)=1 AND COALESCE(NULLIF(c.open_status,''),'OPEN')='OPEN'
                          AND COALESCE(c.end_date,'')<>'' AND c.end_date>=? AND c.end_date<=? ORDER BY c.end_date", [$t, $to]) ?: []);
    } catch (Throwable $e) { return []; }
}

//  A contract belongs to its branch (else its client's home branch); a person
//  sees only those of the offices they cover — the contract register's own rule.
function sx_scope_contracts(array $rows) {
    $br = function_exists('sales_branch_narrow') ? sales_branch_narrow() : 0;   // R-05 — the branch picked on this screen
    if (!function_exists('scope_office_allows') && !$br) return $rows;
    return array_values(array_filter($rows, function ($r) use ($br) {
        $off = ($r['branch_id'] ?? null) ?: (($r['client_branch'] ?? null) ?: null);
        if ($br && (int) $off !== $br) return false;
        return !function_exists('scope_office_allows') || scope_office_allows($off);
    }));
}

// ---- R-05 (owner, 26 Nov 2026) — a Branch filter on the Sales screens --------------
//  "For Director or person having central data access like business head, directors
//   and so on … they shall have filter branch wise also." The Quotes register's Branch
//  filter, on Leads, Deals, Enquiries, Pipeline board, Sales dashboard, Sales home and
//  Today. Only for a person who sees more than one branch; it offers only their own
//  branches and only narrows (lib/access.php sales_branch_sql()). The last pick is
//  remembered per screen for the session; "All my branches" clears it.
const SX_BRANCH_SCREENS = ['leads', 'opportunities', 'inquiries', 'sales-board', 'sales-dashboard', 'sales-home', 'sales-today'];
function sx_branch_choices() {
    static $memo = [];
    $uid = (int) (current_user()['id'] ?? 0);
    if (isset($memo[$uid])) return $memo[$uid];
    $rows = [];
    try { $rows = ops_all("SELECT id, name FROM offices ORDER BY name") ?: []; } catch (Throwable $e) {}
    if (function_exists('scope_office_allows')) $rows = array_values(array_filter($rows, fn($o) => scope_office_allows((int) $o['id'])));
    return $memo[$uid] = count($rows) > 1 ? $rows : [];
}
//  Called by ops_dispatch() for every request: sets the pick for this one request.
function sx_branch_begin($route, $method) {
    $GLOBALS['__sales_branch'] = 0;
    if (strtoupper((string) $method) !== 'GET' || !in_array((string) $route, SX_BRANCH_SCREENS, true)) return 0;
    if (!function_exists('current_user') || !current_user()) return 0;
    $ch = sx_branch_choices();
    if (!$ch) return 0;                                   // one branch (or none): nothing to pick, a typed br changes nothing
    $ids = array_map('intval', array_column($ch, 'id'));
    $hasSess = isset($_SESSION) && is_array($_SESSION);
    if (array_key_exists('br', $_GET)) {
        $v = (string) $_GET['br'];
        //  A branch of theirs is kept; "" (All my branches) or a branch they do not see is ignored.
        $pick = ($v !== '' && ctype_digit($v) && in_array((int) $v, $ids, true)) ? (int) $v : 0;
        if ($hasSess) { if ($pick) $_SESSION['sx_br'][$route] = $pick; else unset($_SESSION['sx_br'][$route]); }
    } else {
        $pick = $hasSess ? (int) ($_SESSION['sx_br'][$route] ?? 0) : 0;
        if (!in_array($pick, $ids, true)) $pick = 0;
    }
    return $GLOBALS['__sales_branch'] = $pick;
}
//  The control: a small form that keeps the screen's other filters. '' when the person
//  has one branch.
function sx_branch_select($route) {
    $ch = sx_branch_choices();
    if (!$ch) return '';
    $cur = function_exists('sales_branch_narrow') ? sales_branch_narrow() : 0;
    $id = 'sxbr-' . preg_replace('/[^a-z]/', '', (string) $route);
    $h = '<form method="get" action="/' . e($route) . '" class="sxl-f sxbr">';
    foreach ($_GET as $k => $v) {
        if (in_array($k, ['br', 'page', 'export'], true) || is_array($v) || (string) $v === '' || ($k === 'id' && !(int) $v)) continue;
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $h .= '<label class="lab" for="' . $id . '">Branch</label><select class="form-control" id="' . $id . '" name="br" onchange="this.form.submit()">'
        . '<option value="">All my branches</option>';
    foreach ($ch as $o) $h .= '<option value="' . (int) $o['id'] . '"' . ((int) $o['id'] === $cur ? ' selected' : '') . '>' . e($o['name']) . '</option>';
    return $h . '</select><noscript><button class="btn small secondary">Show</button></noscript></form>';
}
//  The picked branch's name, for "Showing …" lines and the receivables filter.
function sx_branch_name() {
    $b = function_exists('sales_branch_narrow') ? sales_branch_narrow() : 0;
    if (!$b) return '';
    foreach (sx_branch_choices() as $o) if ((int) $o['id'] === $b) return (string) $o['name'];
    return '';
}
//  Owner, 26 Nov 2026 (LP-20): a lead, deal or enquiry is never handed to someone who could
//  not open it afterwards — the same branch (and business-unit) boundary the record's own page
//  asks. Returns '' when that person may have it, else the plain sentence to show.
//    sales_handover_problem(<user id or row>, <office id>, <sbu code>, 'lead')
function sales_handover_problem($to, $officeId, $sbu = '', $noun = 'record') {
    $u = is_array($to) ? $to : ops_one("SELECT * FROM users WHERE id=?", [(int) $to]);
    if (!$u) return 'Choose an active person to give it to.';
    if (!empty($u['is_superuser']) || strtoupper((string) ($u['role'] ?? '')) === 'MASTER_ADMIN') return '';
    if (!function_exists('user_scope_covers')) return '';
    $officeId = (int) $officeId; $sbu = (string) $sbu;
    $name = function_exists('user_name') ? user_name($u) : 'That person';
    if ($officeId && !user_scope_covers($u, $officeId, '')) {
        $br = function_exists('office_name') ? office_name($officeId) : '';
        $br = $br !== '' ? 'the ' . $br . ' branch' : 'that branch';
        return $name . ' does not work for ' . $br . ', so they could not open this ' . $noun . '. Choose someone from that branch.';
    }
    if ($sbu !== '' && !user_scope_covers($u, $officeId ?: null, $sbu)) {
        $bu = function_exists('sbu_label') ? sbu_label($sbu) : $sbu;
        return $name . ' does not work for the ' . $bu . ' business unit, so they could not open this ' . $noun . '. Choose someone from that unit.';
    }
    return '';
}
//  The pick-lists of who a record may go to: only people who could open it, plus whoever
//  holds it now (so the current holder is still shown, even if their branch has changed).
function sales_people_covering(array $people, $officeId, $sbu = '', $keepId = 0) {
    static $rows = [];
    $out = [];
    foreach ($people as $p) {
        $id = (int) ($p['id'] ?? 0);
        if ($id && $id === (int) $keepId) { $out[] = $p; continue; }
        if (!array_key_exists($id, $rows)) $rows[$id] = ops_one("SELECT * FROM users WHERE id=?", [$id]);
        if ($rows[$id] && sales_handover_problem($rows[$id], $officeId, $sbu) === '') $out[] = $p;
    }
    return $out;
}
//  Does this user's office scope cover that office? (Same rules as their own login.)
function sx_user_covers_office($uid, $officeId) {
    if (!(int) $officeId) return true;
    $u = ops_one("SELECT * FROM users WHERE id=?", [(int) $uid]); if (!$u) return false;
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper((string) ($u['role'] ?? ''));
    if ($role === 'MASTER_ADMIN') return true;
    $so = trim((string) ($u['scope_offices'] ?? ''));
    if ($so === 'ALL') return true;
    if ($so === '' && function_exists('role_defaults')) {
        $rk = function_exists('role_effective_key') ? role_effective_key($role) : $role;
        if ((role_defaults($rk)['offices'] ?? '') === 'ALL') return true;
    }
    if ($so !== '') return in_array((int) $officeId, array_map('intval', array_filter(explode(',', $so))), true);
    return (int) ($u['home_office_id'] ?? 0) === (int) $officeId;
}

//  Everything the Today screen and the Home cards need, for one person.
function sx_today_data($who) {
    [$leads, $deals] = sx_open_items($who);
    $t = sx_today(); $stale = sx_stale_days();
    $c = ['overdue' => 0, 'today' => 0, 'week' => 0, 'none' => 0, 'stale' => 0];
    $dealRows = []; $leadRows = []; $staleRows = [];
    foreach ($deals as $o) {
        $st = sx_due_state($o['next_action_on'] ?? '', $t);
        if (isset($c[$st])) $c[$st]++;
        $o['_touch'] = sx_last_touch('OPPORTUNITY', $o['id'], $o['stage_since'] ?: $o['updated_at']);
        $o['_idle'] = sx_days_since($o['_touch']);
        if ($o['_idle'] !== null && $o['_idle'] > $stale) { $c['stale']++; $staleRows[] = $o; }
        if (in_array($st, ['overdue', 'today', 'week', 'none'], true)) $dealRows[] = $o + ['_due' => $st];
    }
    foreach ($leads as $l) {
        $st = sx_due_state($l['next_action_on'] ?? '', $t);
        if (isset($c[$st])) $c[$st]++;
        if (in_array($st, ['overdue', 'today', 'week', 'none'], true)) $leadRows[] = $l + ['_due' => $st];
    }
    $order = ['overdue' => 0, 'none' => 1, 'today' => 2, 'week' => 3];
    $sort = function ($a, $b) use ($order) { return [$order[$a['_due']], (string) $a['next_action_on']] <=> [$order[$b['_due']], (string) $b['next_action_on']]; };
    usort($dealRows, $sort); usort($leadRows, $sort);
    usort($staleRows, fn($a, $b) => $b['_idle'] <=> $a['_idle']);
    $ar = sx_receivables(); $pay = []; $overdueAmt = 0.0; $overdueN = 0;
    if ($ar !== null) foreach ($ar as $r) {
        if ((int) $r['age_days'] > 0) { $overdueAmt += (float) $r['amount']; $overdueN++; }
        if ((int) $r['age_days'] > -7) $pay[] = $r;
    }
    usort($pay, fn($a, $b) => $b['age_days'] <=> $a['age_days']);
    $ren = sx_renewals();
    return ['counts' => $c, 'deals' => $dealRows, 'leads' => $leadRows, 'stale' => $staleRows, 'pay' => $pay,
            'overdue_amt' => $overdueAmt, 'overdue_n' => $overdueN, 'ar' => $ar !== null, 'renewals' => $ren,
            'open_deals' => $deals, 'open_leads' => $leads];
}
//  Who has what today — one row per salesperson (managers).
function sx_who_has_what() {
    $rows = [];
    foreach (sx_people() as $uid => $name) {
        $d = sx_today_data($uid);
        $rows[] = ['id' => $uid, 'name' => $name] + $d['counts'];
    }
    return $rows;
}

// ---- The dashboard -----------------------------------------------------------
function sx_period($p) {
    $t = sx_today();
    switch ($p) {
        case 'month':   return [date('Y-m-01'), $t, 'This month'];
        case 'quarter': $m = (int) date('n'); $q = (int) floor(($m - 1) / 3) * 3 + 1; return [date('Y') . '-' . sprintf('%02d', $q) . '-01', $t, 'This quarter'];
        case '90':      return [date('Y-m-d', strtotime('-90 days')), $t, 'Last 90 days'];
        default:        $fy = function_exists('active_fy') ? active_fy() : date('Y'); [$a] = function_exists('fy_range') ? fy_range($fy) : [date('Y-04-01')]; return [$a, $t, 'This financial year'];
    }
}
//  The last day the TARGET of a period runs to (owner, 19 Nov 2026 — "Full year target"): the whole
//  financial year, quarter or month, not only the months up to today. Achievement still counts to today.
function sx_target_end($period) {
    $t = sx_today();
    switch ($period) {
        case 'month':   return date('Y-m-t', strtotime($t));
        case 'quarter': $m = (int) date('n', strtotime($t)); $qe = (int) (floor(($m - 1) / 3) * 3 + 3); return date('Y-m-t', strtotime(date('Y', strtotime($t)) . '-' . sprintf('%02d', $qe) . '-01'));
        case '90':      return $t;
        default:        $fy = function_exists('active_fy') ? active_fy() : date('Y'); return function_exists('fy_range') ? (fy_range($fy)[1] ?? $t) : $t;
    }
}
function sx_dash_data($who, $period) {
    [$from, $to, $label] = sx_period($period);
    $tEnd = sx_target_end($period);
    $own = fn($r) => !$who || (int) ($r['owner_user_id'] ?? 0) === $who;
    $in = fn($d) => ($d = substr((string) $d, 0, 10)) !== '' && $d >= $from && $d <= $to;
    $allDeals = function_exists('opp_all') ? array_values(array_filter(opp_all([]), $own)) : [];
    $allLeads = function_exists('leads_all') ? array_values(array_filter(leads_all([]), $own)) : [];
    $open = array_values(array_filter($allDeals, fn($o) => $o['status'] === 'OPEN'));
    $won = array_values(array_filter($allDeals, fn($o) => $o['status'] === 'WON' && $in($o['won_at'])));
    $lost = array_values(array_filter($allDeals, fn($o) => $o['status'] === 'LOST' && $in($o['won_at'])));
    $newLeads = array_values(array_filter($allLeads, fn($l) => $in($l['created_at'])));
    $newDeals = array_values(array_filter($allDeals, fn($o) => $in($o['created_at'])));
    $wonV = array_sum(array_map(fn($o) => (float) $o['value'], $won));
    $openV = array_sum(array_map(fn($o) => (float) $o['value'], $open));
    $weighted = array_sum(array_map(fn($o) => function_exists('opp_weighted') ? opp_weighted($o) : 0, $open));
    $target = sx_target_total($who, $from, $tEnd);
    // Pipeline by stage (the deal pipeline's own open stages, in order).
    $stages = [];
    foreach ($open as $o) {
        $k = (int) $o['stage_seq'] . '|' . $o['stage_name'];
        $stages[$k] = ['name' => $o['stage_name'], 'n' => ($stages[$k]['n'] ?? 0) + 1, 'v' => ($stages[$k]['v'] ?? 0) + (float) $o['value'],
                       'w' => ($stages[$k]['w'] ?? 0) + (function_exists('opp_weighted') ? opp_weighted($o) : 0), 'p' => (int) ($o['stage_prob'] ?? 0)];
    }
    ksort($stages, SORT_NATURAL);
    // Won by month across the period.
    $months = [];
    for ($m = strtotime(substr($from, 0, 7) . '-01'); $m <= strtotime($to); $m = strtotime('+1 month', $m)) $months[date('Y-m', $m)] = ['won' => 0.0, 'target' => 0.0];
    foreach ($won as $o) { $k = substr((string) $o['won_at'], 0, 7); if (isset($months[$k])) $months[$k]['won'] += (float) $o['value']; }
    foreach (array_keys($months) as $k) $months[$k]['target'] = sx_target_total($who, $k . '-01', date('Y-m-t', strtotime($k . '-01')));
    // Lead sources: leads, deals started from them, value won.
    $srcLabels = function_exists('lk_options_or') && defined('LEAD_SOURCES') ? lk_options_or('lead_source', LEAD_SOURCES) : (defined('LEAD_SOURCES') ? LEAD_SOURCES : []);
    $dealByLead = []; foreach ($allDeals as $o) if ((int) $o['lead_id']) $dealByLead[(int) $o['lead_id']] = $o;
    $src = [];
    foreach ($newLeads as $l) {
        $k = (string) ($l['source'] ?: 'OTHER'); $d = $dealByLead[(int) $l['id']] ?? null;
        $src[$k] = $src[$k] ?? ['label' => $srcLabels[$k] ?? ucfirst(strtolower($k)), 'leads' => 0, 'deals' => 0, 'won' => 0.0];
        $src[$k]['leads']++;
        if ($d) { $src[$k]['deals']++; if ($d['status'] === 'WON') $src[$k]['won'] += (float) $d['value']; }
    }
    uasort($src, fn($a, $b) => $b['won'] <=> $a['won'] ?: $b['leads'] <=> $a['leads']);
    // Why we lose.
    $reasons = function_exists('opp_lost_reasons') ? opp_lost_reasons() : [];
    $lostBy = [];
    foreach ($lost as $o) { $k = (string) ($o['lost_reason'] ?: '—'); $lostBy[$k] = ['label' => $reasons[$k] ?? ($k === '—' ? 'No reason given' : ucfirst(strtolower(str_replace('_', ' ', $k)))), 'n' => ($lostBy[$k]['n'] ?? 0) + 1, 'v' => ($lostBy[$k]['v'] ?? 0) + (float) $o['value']]; }
    uasort($lostBy, fn($a, $b) => $b['n'] <=> $a['n']);
    // Collections ageing.
    $ageing = null; $ar = sx_receivables();
    if ($ar !== null) { $ageing = []; foreach (AR_BUCKETS as $b) $ageing[$b['key']] = ['label' => $b['label'], 'v' => 0.0];
        foreach ($ar as $r) if (isset($ageing[$r['bucket']])) $ageing[$r['bucket']]['v'] += (float) $r['amount']; }
    // Team leaderboard.
    $team = [];
    if (!$who) foreach (sx_people() as $uid => $name) {
        $mine = fn($o) => (int) $o['owner_user_id'] === $uid;
        $team[] = ['id' => $uid, 'name' => $name,
                   'open' => array_sum(array_map(fn($o) => (float) $o['value'], array_filter($open, $mine))),
                   'won' => array_sum(array_map(fn($o) => (float) $o['value'], array_filter($won, $mine))),
                   'acts' => sx_activity_count($uid, 30), 'target' => sx_target_total($uid, $from, $tEnd)];
    }
    usort($team, fn($a, $b) => $b['won'] <=> $a['won']);
    $top = $open; usort($top, fn($a, $b) => (float) $b['value'] <=> (float) $a['value']);
    return ['from' => $from, 'to' => $to, 'label' => $label,
            'k' => ['leads' => count($newLeads), 'deals_new' => count($newDeals), 'won_v' => $wonV, 'won_n' => count($won), 'lost_n' => count($lost),
                    'rate' => (count($won) + count($lost)) ? (int) round(100 * count($won) / (count($won) + count($lost))) : null,
                    'avg' => count($won) ? $wonV / count($won) : null, 'open_v' => $openV, 'open_n' => count($open), 'weighted' => $weighted,
                    'target' => $target, 'ach' => $target > 0 ? round(100 * $wonV / $target) : null],
            'stages' => array_values($stages), 'months' => $months, 'sources' => array_values($src), 'lost' => array_values($lostBy),
            'ageing' => $ageing, 'team' => $team, 'top' => array_slice($top, 0, 10)];
}
function sx_activity_count($uid, $days) {
    try {
        $u = ops_one("SELECT * FROM users WHERE id=?", [(int) $uid]); if (!$u) return 0;
        return (int) ops_val("SELECT COUNT(*) FROM activities WHERE owner=? AND COALESCE(auto,0)=0 AND occurred_at>=?", [user_name($u), date('c', strtotime("-$days days"))]);
    } catch (Throwable $e) { return 0; }
}
//  Sales targets arrive in part 3 (sales_targets). Until then: none set.
function sx_target_total($who, $from, $to) { return function_exists('sxt_target_total') ? (float) sxt_target_total($who, $from, $to) : 0.0; }

// ---- Sales home: data health (the Excel's "fix these to keep reports trustworthy")
function sx_health($who) {
    [$leads, $deals] = sx_open_items($who);
    $t = sx_today();
    $late = count(array_filter($deals, fn($o) => sx_due_state($o['next_action_on'] ?? '', $t) === 'overdue'))
          + count(array_filter($deals, fn($o) => $o['expected_close'] && substr($o['expected_close'], 0, 10) < $t));
    $noStep = count(array_filter(array_merge($deals, $leads), fn($r) => trim((string) ($r['next_action_on'] ?? '')) === ''));
    $q = 0; $lostNo = 0;
    //  Counted only within the offices (and, for one person, the records) this
    //  person can open — so "Fix ›" leads to a register that shows them.
    $sc = fn($o) => !function_exists('scope_office_allows') || scope_office_allows(((int) $o) ?: null);
    try {
        foreach (ops_all("SELECT office_id, owner_id FROM quotations WHERE COALESCE(is_current,1)=1 AND status IN ('PENDING_APPROVAL','EXPIRED')") ?: [] as $r)
            if ($sc($r['office_id'] ?? 0) && (!$who || (int) ($r['owner_id'] ?? 0) === (int) $who)) $q++;
        foreach (ops_all("SELECT office_id FROM opportunities WHERE status='LOST' AND COALESCE(lost_reason,'')=''" . ($who ? " AND owner_user_id=" . (int) $who : '')) ?: [] as $r)
            if ($sc($r['office_id'] ?? 0)) $lostNo++;
    } catch (Throwable $e) {}
    //  Customers only (a vendor's GSTIN is not ours to chase here), and the GSTIN's
    //  real check digit, not only its shape. D3 (owner, 21 Nov 2026): customers with
    //  no GSTIN and PAN — and no reason recorded — are listed, and government
    //  departments without a PAN are flagged for Finance.
    $gst = 0; $noId = 0; $govt = 0;
    if (function_exists('custid_migrate')) custid_migrate();
    try { foreach (ops_all("SELECT * FROM business_partners WHERE is_client=1 AND COALESCE(status,'') NOT IN ('MERGED','RETIRED')") ?: [] as $p) {
            if (!$sc($p['home_branch_id'] ?? 0)) continue;
            $g = strtoupper(trim((string) ($p['gstin'] ?? '')));
            $govtRow = strtoupper((string) ($p['tax_status'] ?? '')) === 'GOVT_NO_PAN';
            if ($g !== '' && ($govtRow ? (function_exists('custid_govt_gstin_problem') && custid_govt_gstin_problem($g) !== '')
                                       : (function_exists('custid_gstin_problem') ? custid_gstin_problem($g) !== '' : !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g)))) $gst++;
            if (function_exists('custid_complete') && !custid_complete($p)) $noId++;
            if ($govtRow) $govt++;
        } } catch (Throwable $e) {}
    $today = !function_exists('feat_on') || feat_on('/sales-today');   // switched off → the Pipeline board
    return [
        ['Deals with a ⚠ health warning (late follow-up or past close date)', $late, $today ? '/sales-today' : '/sales-board'],
        ['Open deals / leads with no next step', $noStep, $today ? '/sales-today#none' : '/sales-board'],
        ['Customers with a GSTIN that does not look right', $gst, '/clients'],
        [(function_exists('THP') ? THP('quote') : 'Quotes') . ' waiting for approval or expired', $q, '/quotes'],
        ['Lost deals without a reason', $lostNo, '/opportunities?status=LOST'],
        //  21 Nov 2026 (D3) — added at the end so the rows above keep their place.
        ['Customers without GSTIN and PAN (asked at their next invoice)', $noId, '/clients'],
        ['Government departments without a PAN (for Finance to check)', $govt, '/clients'],
    ];
}

// ---- Links ------------------------------------------------------------------
//  WhatsApp only for a mobile: an Indian 10-digit number starting 6–9 (a
//  landline such as 022 2345 6789 gets Call only). Other countries' numbers,
//  given with their own code (+44 …), are passed through.
function sx_wa($phone) {
    $raw = trim((string) $phone); $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);
    if (strlen($d) === 12 && substr($d, 0, 2) === '91') $d = substr($d, 2);
    if (strlen($d) === 10) return preg_match('/^[6-9]/', $d) ? 'https://wa.me/91' . $d : '';
    return ($raw !== '' && $raw[0] === '+' && strlen($d) >= 11) ? 'https://wa.me/' . $d : '';
}
function sx_tel($phone) { $d = preg_replace('/[^\d+]+/', '', (string) $phone); return strlen(preg_replace('/\D/', '', $d)) >= 10 ? 'tel:' . $d : ''; }

// ---- Route --------------------------------------------------------------------
function ops_sales_simple($route, $method) {
    ops_require(sx_can(), 'Sales is not open to you.');
    if (function_exists('nav_under')) nav_under($route, 'sales');
    $who = sx_person();
    //  seeAll: true = Me / Everyone / anyone; 'team' = Me / the people who report to me.
    $team = sx_can_see_all() ? [] : sx_team_people();
    $base = ['who' => $who, 'whoKey' => $_GET['who'] ?? 'me', 'people' => sx_can_see_all() ? sx_people() : $team,
             'seeAll' => sx_can_see_all() ? true : ($team ? 'team' : false)];
    switch ($route) {
        case 'sales-today':
            view('ops/sx_today', $base + ['d' => sx_today_data($who), 'whoRows' => sx_can_see_all() ? sx_who_has_what() : []]);
            return true;
        case 'sales-board':
            $pipe = function_exists('opp_board') ? opp_board((int) ($_GET['pipeline'] ?? 0)) : null;
            [$leads] = sx_open_items($who);
            $orders = ['n' => 0, 'v' => 0.0];
            //  The same orders, for the same people and offices, as Orders & payments.
            if (function_exists('sx_orders')) { try { [$fa, $fb] = fy_range(active_fy()); $or = sx_orders($who, $fa, $fb);
                  $orders = ['n' => count($or), 'v' => array_sum(array_map(fn($q) => (float) $q['subtotal'], $or))]; } catch (Throwable $e) {} }
            else try { [$fa, $fb] = fy_range(active_fy());
                  $r = ops_one("SELECT COUNT(*) n, COALESCE(SUM(subtotal),0) v FROM quotations WHERE COALESCE(is_current,1)=1 AND status='ACCEPTED' AND COALESCE(accepted_date, updated_at, '')>=? AND COALESCE(accepted_date, updated_at, '')<=?", [$fa, $fb . 'T23:59:59']);
                  $orders = ['n' => (int) $r['n'], 'v' => (float) $r['v']]; } catch (Throwable $e) {}
            view('ops/sx_board', $base + ['board' => $pipe, 'leads' => $leads, 'orders' => $orders,
                 'pipes' => function_exists('pipelines_all') ? pipelines_all('OPPORTUNITY') : []]);
            return true;
        case 'sales-dashboard':
            //  The same right as the existing Sales dashboard: Sales reports (docs/02 fn 19).
            ops_require(!function_exists('crmdash_can') || crmdash_can(), 'The sales dashboard needs "Sales reports".');
            $p = in_array($_GET['p'] ?? '', ['fy', 'quarter', 'month', '90'], true) ? $_GET['p'] : 'fy';
            view('ops/sx_dash', $base + ['dd' => sx_dash_data($who, $p), 'p' => $p]);
            return true;
        default: // sales-home
            //  DEF-12 (21 Nov 2026): "OK, seen" on the target-change notice is answered
            //  here, on Sales home itself — it used to post to Targets, so with Targets
            //  switched off the notice could never be put away. Only the person's own rows.
            if ($method === 'POST' && !empty($_POST['seen'])) {
                if (function_exists('sx_more_migrate')) sx_more_migrate();
                db()->prepare("UPDATE sales_targets SET seen_at=? WHERE user_id=? AND COALESCE(seen_at,'')=''")->execute([date('c'), (int) (current_user()['id'] ?? 0)]);
                redirect('/sales-home');
            }
            $d = sx_today_data($who);
            $wonFy = 0.0;
            try { [$fa] = fy_range(active_fy());
                  foreach (function_exists('opp_all') ? opp_all(['status' => 'WON']) : [] as $o)
                      if ((!$who || (int) $o['owner_user_id'] === $who) && substr((string) $o['won_at'], 0, 10) >= $fa) $wonFy += (float) $o['value']; } catch (Throwable $e) {}
            view('ops/sx_home', $base + ['d' => $d, 'wonFy' => $wonFy, 'health' => sx_health($who)]);
            return true;
    }
}

// ---- Sales view of the ONE client register (Directory → Clients) -------------
//  Per firm on the page: open deals and their value, won this financial year,
//  amount owed (only for people who may see receivables), last contact, and the
//  primary contact to WhatsApp or ring. Deals come through opp_all(), so the
//  office scope of the deal register applies; nothing is stored.
function sx_client_sales_cols(array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $out = array_fill_keys($ids, ['deals' => 0, 'pipe' => 0.0, 'won' => 0.0, 'owed' => null, 'touch' => '', 'contact' => '', 'phone' => '']);
    $fa = '';
    try { [$fa] = fy_range(active_fy()); } catch (Throwable $e) {}
    foreach (function_exists('opp_all') ? opp_all([]) : [] as $o) {
        $pid = (int) ($o['partner_id'] ?? 0); if (!isset($out[$pid])) continue;
        if ($o['status'] === 'OPEN') { $out[$pid]['deals']++; $out[$pid]['pipe'] += (float) $o['value']; }
        elseif ($o['status'] === 'WON' && ($fa === '' || substr((string) $o['won_at'], 0, 10) >= $fa)) $out[$pid]['won'] += (float) $o['value'];
    }
    $ar = function_exists('ar_can') && ar_can() && function_exists('books_outstanding');
    $in = implode(',', $ids);
    try {
        foreach (ops_all("SELECT partner_id, name, mobile, phone FROM partner_contacts WHERE partner_id IN ($in) ORDER BY is_primary DESC, id") ?: [] as $c) {
            $pid = (int) $c['partner_id'];
            if ($out[$pid]['contact'] === '') { $out[$pid]['contact'] = (string) $c['name']; $out[$pid]['phone'] = (string) (($c['mobile'] ?? '') ?: ($c['phone'] ?? '')); }
        }
        foreach (ops_all("SELECT partner_id, MAX(occurred_at) t FROM activities WHERE partner_id IN ($in) AND COALESCE(auto,0)=0 GROUP BY partner_id") ?: [] as $a)
            $out[(int) $a['partner_id']]['touch'] = (string) $a['t'];
    } catch (Throwable $e) {}
    if ($ar) foreach ($ids as $pid) { try { $bo = books_outstanding($pid, true); $out[$pid]['owed'] = (float) (is_array($bo) ? ($bo['outstanding'] ?? 0) : $bo); } catch (Throwable $e) {} }
    return $out;
}
//  Two small icons (WhatsApp, call) for a table cell outside the Sales screens.
function sx_contact_btns_plain($phone, $label = '') {
    $wa = sx_wa($phone); $tel = sx_tel($phone); $out = '';
    if ($wa !== '') $out .= '<a href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp ' . e($label) . '">💬</a> ';
    if ($tel !== '') $out .= '<a href="' . e($tel) . '" title="Call" aria-label="Call ' . e($label) . '">📞</a>';
    return $out;
}
