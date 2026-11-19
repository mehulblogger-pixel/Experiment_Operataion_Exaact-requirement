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
function sx_can_see_all() { return is_master() || (function_exists('is_admin_level') && is_admin_level()); }
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
    if (!sx_can_see_all()) return $me;
    $p = $_GET['who'] ?? 'me';
    if ($p === 'all') return 0;
    if ($p === 'me' || $p === '') return $me;
    return (int) $p;
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
    try { return ar_rows('DUE'); } catch (Throwable $e) { return null; }
}
//  Contracts ending within the warning window (the existing contract setting).
function sx_renewals($days = null) {
    $days = $days ?? (function_exists('contract_warn_days') ? contract_warn_days() : 30);
    $t = sx_today(); $to = date('Y-m-d', strtotime("+$days days"));
    try {
        return sx_scope_contracts(ops_all("SELECT c.*, COALESCE(bp.display_name, bp.legal_name) client_name, bp.home_branch_id client_branch FROM partner_contracts c LEFT JOIN business_partners bp ON bp.id=c.partner_id
                        WHERE COALESCE(c.is_active,1)=1 AND COALESCE(c.end_date,'')<>'' AND c.end_date>=? AND c.end_date<=? ORDER BY c.end_date", [$t, $to]) ?: []);
    } catch (Throwable $e) { return []; }
}

//  A contract belongs to its branch (else its client's home branch); a person
//  sees only those of the offices they cover — the contract register's own rule.
function sx_scope_contracts(array $rows) {
    if (!function_exists('scope_office_allows')) return $rows;
    return array_values(array_filter($rows, fn($r) => scope_office_allows(($r['branch_id'] ?? null) ?: (($r['client_branch'] ?? null) ?: null))));
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
    $gst = 0;
    try { foreach (ops_all("SELECT gstin, home_branch_id FROM business_partners WHERE COALESCE(gstin,'')<>''") ?: [] as $p)
            if ($sc($p['home_branch_id'] ?? 0) && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper(trim($p['gstin'])))) $gst++; } catch (Throwable $e) {}
    $today = !function_exists('feat_on') || feat_on('/sales-today');   // switched off → the Pipeline board
    return [
        ['Deals with a ⚠ health warning (late follow-up or past close date)', $late, $today ? '/sales-today' : '/sales-board'],
        ['Open deals / leads with no next step', $noStep, $today ? '/sales-today#none' : '/sales-board'],
        ['Customers with a GSTIN that does not look right', $gst, '/clients'],
        ['Quotes waiting for approval or expired', $q, '/quotes'],
        ['Lost deals without a reason', $lostNo, '/opportunities?status=LOST'],
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
    $base = ['who' => $who, 'whoKey' => $_GET['who'] ?? 'me', 'people' => sx_can_see_all() ? sx_people() : [], 'seeAll' => sx_can_see_all()];
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
    if ($ar) foreach ($ids as $pid) { try { $bo = books_outstanding($pid); $out[$pid]['owed'] = (float) (is_array($bo) ? ($bo['outstanding'] ?? 0) : $bo); } catch (Throwable $e) {} }
    return $out;
}
//  Two small icons (WhatsApp, call) for a table cell outside the Sales screens.
function sx_contact_btns_plain($phone, $label = '') {
    $wa = sx_wa($phone); $tel = sx_tel($phone); $out = '';
    if ($wa !== '') $out .= '<a href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp ' . e($label) . '">💬</a> ';
    if ($tel !== '') $out .= '<a href="' . e($tel) . '" title="Call" aria-label="Call ' . e($label) . '">📞</a>';
    return $out;
}
