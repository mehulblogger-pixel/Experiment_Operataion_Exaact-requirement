<?php
// ============================================================================
//  EXPENSE VOUCHER APPROVAL CHAIN — owner, 16 Nov 2026 (branch batch R5, 0.4, C3)
//  + batch 2, owner 17 Nov 2026 (plan 29: Change 2, points 1–3, A, Q3)
// ============================================================================
//  Before: any coordinator-level person in the company could approve any
//  submitted voucher in one click (DEF-OPS-034), and Finance — who pays it —
//  could not even open it (DEF-OPS-003).
//
//  Now a voucher climbs the levels the Master Admin set and ends with Finance:
//
//      Amit (claimant) → Neha (N+1) → Sunil (N+2) → Business Director → Finance → APPROVED
//
//  * Reporting-line levels: N+1 is the claimant's team-member record
//    `inspectors.reports_to_id` (a user), falling back to the claimant login's
//    `users.reports_to_id`; N+2 is that person's `users.reports_to_id`; and so on.
//  * Role levels (Change 2, 17 Nov 2026): "Business Director", "Business Unit
//    Head", "Branch Manager" or any other role, the company's own custom roles
//    included — a custom role built on Business Director counts as one, the
//    same matching quotation approvals use (user_holds_approver_role()). Any
//    active holder whose office scope covers the voucher's office may sign it;
//    the first to approve signs the level.
//  * The Master Admin decides (Admin → Approvals → Expense vouchers) which levels
//    exist, the total above which each is needed ("needed above ₹", blank =
//    always) and their ORDER. Finance levels always stay last (office Finance,
//    then central Finance). Nothing is set when this ships, and the default —
//    N+1, then Finance — applies until it is. Amounts are never preset.
//  * Q1: reporting-line levels go up the line in order; a higher level includes
//    the ones below it, so when N+3 is needed, N+1, N+2 and N+3 each sign.
//  * Q2: a line shorter than the band stops at its highest person; a claimant
//    with no "Reports to" at all, or a role nobody holds for that office, waits
//    for an administrator, who is warned.
//  * Q3: any approver at the current level may send it back to DRAFT with a
//    reason; resubmitting starts the chain again. No new status exists.
//  * No double signing (Change 2): when a person who would sign a level has
//    already signed an earlier level of this voucher (the Director who is also
//    the claimant's N+2), that level counts as signed — History says "already
//    signed at N+2" — and they are not asked twice. Finance levels are the
//    paying check and are never counted this way: another Finance person signs.
//  * Maker ≠ checker: the claimant and the submitter never approve, and one
//    person approves one level of a voucher, not two.
//  * Delegation: the existing approval_delegations table, entity 'VOUCHER' (or
//    a company-wide delegation, as for every other chain). Recruitment and
//    quotation delegation are untouched.
//
//  The status column keeps its four values (docs/03 ## Voucher): the chain runs
//  inside SUBMITTED. Which levels are approved is in voucher_approval_log, per
//  submission round (`vouchers.appr_round`). A voucher that was SUBMITTED before
//  this existed has round 0 and no log rows, so it simply starts at the first
//  level of the chain on its next action — nothing to convert.
// ============================================================================

const VAPPR_TYPES = [
    'N1'          => 'Reporting line N+1',
    'N2'          => 'Reporting line N+2',
    'N3'          => 'Reporting line N+3',
    'N4'          => 'Reporting line N+4',
    'OFFICE_FIN'  => 'Office Finance',
    'CENTRAL_FIN' => 'Central Finance',
];
const VAPPR_LINE_DEPTH = ['N1' => 1, 'N2' => 2, 'N3' => 3, 'N4' => 4];
const VAPPR_FIN_TYPES = ['OFFICE_FIN', 'CENTRAL_FIN'];
const VAPPR_SETTING = 'voucher_appr_chain';

function vappr_migrate() {
    static $doneAt = -1;
    $ep = function_exists('db_epoch') ? db_epoch() : 0;
    if ($doneAt === $ep) return; $doneAt = $ep;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS voucher_approval_log (
            id $pk, voucher_id INT, round INT DEFAULT 0, level_key VARCHAR(20) DEFAULT '',
            level_label VARCHAR(160) DEFAULT '', action VARCHAR(20) DEFAULT '',
            user_id INT NULL, user_name VARCHAR(160) DEFAULT '',
            on_behalf_user_id INT NULL, on_behalf_name VARCHAR(160) DEFAULT '',
            reason TEXT, created_at VARCHAR(30) DEFAULT '')");
        ensure_column('vouchers', 'submitted_by', "VARCHAR(20) DEFAULT ''");
        ensure_column('vouchers', 'appr_round', 'INT DEFAULT 0');
        ensure_column('vouchers', 'sent_back_reason', 'TEXT');
        ensure_column('vouchers', 'sent_back_by', "VARCHAR(160) DEFAULT ''");
        ensure_column('vouchers', 'sent_back_at', "VARCHAR(30) DEFAULT ''");
        if (function_exists('act_index')) act_index('voucher_approval_log', 'idx_val_v', '(voucher_id)');
    } catch (Throwable $e) { /* never break boot */ }
}

// ---- Configuration ----------------------------------------------------------

//  The roles a role level may name: every role on the role list (built-in and
//  the company's custom ones) except the administrators — who already sign any
//  level nobody else can — and Finance, whose own levels always come last.
function vappr_level_roles() {
    $all = function_exists('roles_all') ? roles_all() : (defined('ORG_ROLES') ? ORG_ROLES : []);
    $out = [];
    foreach ($all as $k => $label) {
        $base = function_exists('role_effective_key') ? role_effective_key($k) : $k;
        if (in_array($base, ['MASTER_ADMIN', 'ADMIN', 'FINANCE'], true)) continue;
        $out[$k] = (string) $label;
    }
    return $out;
}
function vappr_role_label($role) {
    $role = strtoupper(trim((string) $role));
    return function_exists('role_name') ? role_name($role) : $role;
}
//  The key a role level is logged under: stable whatever its position (so a
//  reorder never "unsigns" a level), and short enough for the log column.
function vappr_role_key($role) {
    $role = strtoupper(trim((string) $role));
    return strlen($role) <= 18 ? 'R:' . $role : 'R:' . substr(md5($role), 0, 12);
}
function vappr_is_fin_key($key) { return in_array((string) $key, ['FIN', 'OFFICE_FIN', 'CENTRAL_FIN'], true); }

//  The levels the Master Admin saved, IN THEIR ORDER, or null when nothing is
//  saved (the default chain applies). Each level:
//    ['type' => 'N1'..'N4' | 'ROLE' | 'OFFICE_FIN' | 'CENTRAL_FIN',
//     'role' => the role key (ROLE only, else ''), 'above' => float|null]
//  Finance levels always come back last (office, then central).
//  Reads both shapes: {"v":2,"levels":[…]} (this batch) and the previous
//  batch's {"N1":null,"N2":5000,…}, which keeps its fixed order.
function vappr_config() {
    $raw = function_exists('setting_get') ? (string) setting_get(VAPPR_SETTING, '') : '';
    if ($raw === '') return null;
    $j = json_decode($raw, true);
    if (!is_array($j) || !$j) return null;
    $amt = fn($a) => ($a === null || $a === '') ? null : (float) $a;
    $list = [];
    if (isset($j['levels']) && is_array($j['levels'])) {
        foreach ($j['levels'] as $l) {
            if (!is_array($l)) continue;
            $t = strtoupper((string) ($l['type'] ?? ''));
            if ($t === 'ROLE') {
                $r = strtoupper(trim((string) ($l['role'] ?? '')));
                if ($r !== '') $list[] = ['type' => 'ROLE', 'role' => $r, 'above' => $amt($l['above'] ?? null)];
            } elseif (isset(VAPPR_TYPES[$t])) {
                $list[] = ['type' => $t, 'role' => '', 'above' => $amt($l['above'] ?? null)];
            }
        }
    } else {
        foreach (array_keys(VAPPR_TYPES) as $t)
            if (array_key_exists($t, $j)) $list[] = ['type' => $t, 'role' => '', 'above' => $amt($j[$t])];
    }
    return $list ? vappr_order_levels($list) : null;
}
//  Finance last (office, then central); every other level keeps its order.
function vappr_order_levels(array $list) {
    $main = []; $fin = [];
    foreach ($list as $l) {
        if (in_array($l['type'], VAPPR_FIN_TYPES, true)) $fin[$l['type']] = $l; else $main[] = $l;
    }
    foreach (VAPPR_FIN_TYPES as $t) if (isset($fin[$t])) $main[] = $fin[$t];
    return $main;
}
function vappr_level_name(array $l) {
    return ($l['type'] ?? '') === 'ROLE' ? vappr_role_label($l['role'] ?? '') : (VAPPR_TYPES[$l['type'] ?? ''] ?? (string) ($l['type'] ?? ''));
}

//  Only the Master Admin changes who approves money paid to staff (owner's rule
//  in R5: "the Master Admin sets the amounts and the levels").
function vappr_config_can() { return function_exists('is_master') && is_master(); }

//  Validate and save the posted levels. Returns [ok, message].
//  The screen posts one row per level, in the order shown:
//    $post['lv'][i] = ['type' => …, 'role' => …, 'use' => '1', 'above' => amount|'', 'order' => n]
//  and, to add a role level, $post['add_role'] (+ $post['add_above']), placed
//  after the other levels and before Finance. The previous batch's shape
//  ($post['use'][TYPE], $post['above'][TYPE]) is still accepted, in fixed order.
function vappr_config_save(array $post) {
    if (!vappr_config_can()) return [false, 'Only the Master Admin can change who approves expense vouchers.'];
    if (!empty($post['reset'])) {
        setting_set(VAPPR_SETTING, '');
        return [true, 'Back to the default: the person the claimant reports to (N+1), then Finance.'];
    }
    $roles = vappr_level_roles();
    $rows = [];
    if (isset($post['lv']) && is_array($post['lv'])) {
        $i = 0;
        foreach ($post['lv'] as $r) {
            $i++;
            if (!is_array($r) || empty($r['use'])) continue;
            $t = strtoupper((string) ($r['type'] ?? ''));
            if ($t !== 'ROLE' && !isset(VAPPR_TYPES[$t])) continue;
            $rows[] = ['type' => $t, 'role' => $t === 'ROLE' ? strtoupper(trim((string) ($r['role'] ?? ''))) : '',
                       'above' => $r['above'] ?? '', 'order' => is_numeric($r['order'] ?? null) ? (float) $r['order'] : (float) $i, 'seq' => $i];
        }
        $add = strtoupper(trim((string) ($post['add_role'] ?? '')));
        if ($add !== '') $rows[] = ['type' => 'ROLE', 'role' => $add, 'above' => $post['add_above'] ?? '', 'order' => INF, 'seq' => PHP_INT_MAX];
        usort($rows, fn($a, $b) => [$a['order'], $a['seq']] <=> [$b['order'], $b['seq']]);
    } else {
        $use = (array) ($post['use'] ?? []); $above = (array) ($post['above'] ?? []);
        foreach (array_keys(VAPPR_TYPES) as $t)
            if (!empty($use[$t])) $rows[] = ['type' => $t, 'role' => '', 'above' => $above[$t] ?? ''];
    }
    $list = []; $always = false; $seenRole = []; $lastDepth = 0;
    foreach ($rows as $r) {
        if ($r['type'] === 'ROLE') {
            if (!isset($roles[$r['role']])) return [false, 'Pick a role from the list for the new level.'];
            if (isset($seenRole[$r['role']])) return [false, vappr_role_label($r['role']) . ' is in the list twice — keep it once.'];
            $seenRole[$r['role']] = true;
        }
        $label = vappr_level_name($r);
        $a = trim(str_replace([',', '₹', ' '], '', (string) $r['above']));
        if ($a === '') { $a = null; $always = true; }
        elseif (!is_numeric($a) || (float) $a < 0) return [false, $label . ': the amount must be a number of rupees, or blank for "always".'];
        else $a = (float) $a;
        if (isset(VAPPR_LINE_DEPTH[$r['type']])) {
            if (VAPPR_LINE_DEPTH[$r['type']] < $lastDepth)
                return [false, 'The reporting line goes upwards: put N+1 before N+2, N+2 before N+3, and so on. Role levels may sit anywhere among them.'];
            $lastDepth = VAPPR_LINE_DEPTH[$r['type']];
        }
        $list[] = ['type' => $r['type'], 'role' => $r['role'], 'above' => $a];
    }
    if (!$list) return [false, 'Keep at least one level — a voucher must be approved by somebody.'];
    if (!$always) return [false, 'Leave the amount blank on at least one level, so every voucher, however small, has an approver.'];
    setting_set(VAPPR_SETTING, json_encode(['v' => 2, 'levels' => vappr_order_levels($list)]));
    return [true, 'Saved — it applies to vouchers from their next approval step.'];
}

// ---- Who is who --------------------------------------------------------------

function vappr_user($id) {
    $id = (int) $id; if ($id <= 0) return null;
    try { return ops_one("SELECT * FROM users WHERE id=?", [$id]) ?: null; } catch (Throwable $e) { return null; }
}
function vappr_name($u) {
    if (!$u) return '—';
    $n = trim(((string) ($u['first_name'] ?? '')) . ' ' . ((string) ($u['last_name'] ?? '')));
    return $n !== '' ? $n : (string) ($u['username'] ?? ('user #' . (int) ($u['id'] ?? 0)));
}
function vappr_role_base($u) {
    if (!$u) return '';
    if (!empty($u['is_superuser'])) return 'MASTER_ADMIN';
    $r = strtoupper(trim((string) ($u['role'] ?? '')));
    return function_exists('role_effective_key') ? role_effective_key($r) : $r;
}
function vappr_is_admin_user($u) { return in_array(vappr_role_base($u), ['MASTER_ADMIN', 'ADMIN'], true); }

//  The voucher's office: the one it was raised in, else the claimant's home office.
function voucher_office_id($v) {
    $o = (int) ($v['office_id'] ?? 0);
    if (!$o && !empty($v['inspector_id']))
        $o = (int) (ops_val("SELECT home_office_id FROM inspectors WHERE id=?", [(int) $v['inspector_id']]) ?: 0);
    return $o;
}
function voucher_sbu($v) {
    if (array_key_exists('sbu', $v)) return (string) $v['sbu'];
    return !empty($v['inspector_id']) ? (string) (ops_val("SELECT sbu FROM inspectors WHERE id=?", [(int) $v['inspector_id']]) ?: '') : '';
}
function voucher_claimant_name($v) {
    if (!empty($v['inspector_name'])) return (string) $v['inspector_name'];
    return !empty($v['inspector_id']) ? (string) (ops_val("SELECT name FROM inspectors WHERE id=?", [(int) $v['inspector_id']]) ?: 'the claimant') : 'the claimant';
}
//  Every login seated on the claimant's team-member record.
function voucher_claimant_uids($v) {
    $ins = (int) ($v['inspector_id'] ?? 0); if (!$ins) return [];
    try { return array_map('intval', array_column(ops_all("SELECT id FROM users WHERE inspector_id=?", [$ins]) ?: [], 'id')); }
    catch (Throwable $e) { return []; }
}

//  The claimant's reporting line, N+1 upwards, at most $max people. Each entry:
//  ['depth' => k, 'uid' => id, 'user' => row|null]. Stops at an empty
//  "Reports to", a loop, or the claimant themself.
function vappr_line($v, $max = 4) {
    $ins = (int) ($v['inspector_id'] ?? 0);
    $claim = voucher_claimant_uids($v);
    $first = $ins ? (int) (ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [$ins]) ?: 0) : 0;
    if (!$first) foreach ($claim as $cu) {
        $first = (int) (ops_val("SELECT reports_to_id FROM users WHERE id=?", [$cu]) ?: 0);
        if ($first) break;
    }
    $out = []; $seen = array_fill_keys($claim, true); $cur = $first;
    while ($cur > 0 && count($out) < $max && !isset($seen[$cur])) {
        $seen[$cur] = true;
        $u = vappr_user($cur);
        $out[] = ['depth' => count($out) + 1, 'uid' => $cur, 'user' => $u];
        if (!$u) break;
        $cur = (int) ($u['reports_to_id'] ?? 0);
    }
    return $out;
}

function vappr_active_users() {
    try { return ops_all("SELECT * FROM users WHERE is_active=1 ORDER BY first_name, username") ?: []; }
    catch (Throwable $e) { return []; }
}

//  Active Finance logins. $central: only those whose office scope is ALL
//  offices; otherwise those whose scope covers $officeId (which includes the
//  central ones — they cover every office). Works whether Finance users hold
//  all offices (today's default) or only some (the office-based change, 0.1).
function vappr_finance_users($officeId, $central) {
    $out = [];
    foreach (vappr_active_users() as $u) {
        if (!empty($u['is_superuser']) || vappr_role_base($u) !== 'FINANCE') continue;
        $sc = user_scope_of($u);
        if ($central) { if ($sc['offices'] === 'ALL') $out[] = $u; continue; }
        if (user_scope_covers($u, (int) $officeId ?: null, '')) $out[] = $u;
    }
    return $out;
}

//  Active holders of a role whose office scope covers $officeId (0 = any
//  office). A custom role built on that role counts (user_holds_approver_role,
//  as for quotation approvals); a custom role named itself counts only itself.
function vappr_role_holders($role, $officeId) {
    $out = [];
    foreach (vappr_active_users() as $u) {
        if (!empty($u['is_superuser'])) continue;
        $holds = function_exists('user_holds_approver_role')
            ? user_holds_approver_role((string) ($u['role'] ?? ''), $role)
            : strtoupper((string) ($u['role'] ?? '')) === strtoupper((string) $role);
        if (!$holds) continue;
        if ($officeId && !user_scope_covers($u, (int) $officeId, '')) continue;
        $out[] = $u;
    }
    return $out;
}
function vappr_office_name($officeId) {
    if (!$officeId) return '';
    return (string) (ops_val("SELECT name FROM offices WHERE id=?", [(int) $officeId]) ?: '');
}

// ---- The chain ---------------------------------------------------------------

//  One reporting-line level for the person at depth k.
function vappr_line_level(array $p) {
    $u = $p['user']; $ok = $u && (int) ($u['is_active'] ?? 0) === 1; $k = (int) $p['depth'];
    return ['key' => 'N' . $k, 'kind' => 'line', 'tag' => 'N+' . $k, 'label' => 'Reporting line N+' . $k,
        'short' => ($u ? vappr_name($u) : 'user #' . $p['uid']) . ' (N+' . $k . ')',
        'approvers' => $ok ? [$u] : [],
        'problem' => $ok ? '' : ($u ? vappr_name($u) . ' (N+' . $k . ') is switched off. An administrator can approve this level; correct "Reports to" for the next one.'
                                   : 'The N+' . $k . ' person no longer exists. An administrator can approve this level; correct "Reports to".')];
}
//  One role level ("Business Director"): every active holder covering the office.
function vappr_role_level($role, $officeId, $offName) {
    $name = vappr_role_label($role);
    $who = vappr_role_holders($role, $officeId);
    return ['key' => vappr_role_key($role), 'kind' => 'role', 'role' => $role, 'tag' => $name, 'label' => $name, 'short' => $name,
        'approvers' => $who,
        'problem' => $who ? '' : 'Nobody active holds the role "' . $name . '" for ' . ($offName !== '' ? $offName : 'this office')
                                . '. An administrator can approve this level; give the role (and this office) to the right person.'];
}

//  The levels THIS voucher needs, in order. Each level:
//    key        'N1'..'N4', 'LINE' (no Reports to), 'R:<ROLE>', 'OFFICE_FIN', 'CENTRAL_FIN', 'FIN'
//    kind       'line' | 'role' | 'fin'
//    tag        how History names it ("N+2", "Business Director", "Office Finance")
//    label      what the screen calls it ("Reporting line N+2")
//    short      for the "Waiting for" line ("Sunil Rao (N+2)", "Finance")
//    approvers  user rows who may approve it directly
//    problem    why nobody can, said to the administrator ('' when fine)
function vappr_chain($v) {
    vappr_migrate();
    $total = (float) ($v['total'] ?? 0);
    $office = voucher_office_id($v);
    $offName = vappr_office_name($office);
    $cfg = vappr_config();
    $who = voucher_claimant_name($v);
    $levels = [];
    $noLine = ['key' => 'LINE', 'kind' => 'line', 'tag' => 'reporting line', 'label' => 'Reporting line', 'short' => 'an administrator', 'approvers' => [],
        'problem' => $who . ' has no "Reports to" on their team-member record, so nobody in their line can approve. '
                   . 'An administrator can approve this one; fill in "Reports to" so the next voucher goes to the right person.'];

    if ($cfg === null) {
        $line = vappr_line($v, 1);
        if (!$line) $levels[] = $noLine;
        foreach ($line as $p) $levels[] = vappr_line_level($p);
        $fin = vappr_finance_users($office, false); $central = false;
        if (!$fin) { $fin = vappr_finance_users($office, true); $central = true; }
        $levels[] = ['key' => 'FIN', 'kind' => 'fin', 'tag' => 'Finance', 'label' => $central ? 'Central Finance' : 'Finance' . ($offName !== '' ? ' — ' . $offName : ''),
            'short' => 'Finance', 'approvers' => $fin,
            'problem' => $fin ? '' : 'Nobody in Finance covers ' . ($offName !== '' ? $offName : 'this office') . ' or all offices. An administrator can approve this level; give a Finance person this office.'];
        return $levels;
    }

    $needed = fn($l) => $l['above'] === null || $total > $l['above'];
    // How far up the reporting line this total must go (a higher level includes those below).
    $depth = 0;
    foreach ($cfg as $l) if (isset(VAPPR_LINE_DEPTH[$l['type']]) && $needed($l)) $depth = max($depth, VAPPR_LINE_DEPTH[$l['type']]);
    $line = $depth ? vappr_line($v, $depth) : [];
    $emitted = 0; $warned = false;
    foreach ($cfg as $l) {
        $t = $l['type'];
        if (isset(VAPPR_LINE_DEPTH[$t])) {
            $k = VAPPR_LINE_DEPTH[$t];
            if ($k > $depth) continue;                    // not needed at this total
            if (!$line) { if (!$warned) { $levels[] = $noLine; $warned = true; } continue; }
            foreach ($line as $p) if ($p['depth'] > $emitted && $p['depth'] <= $k) { $levels[] = vappr_line_level($p); $emitted = $p['depth']; }
        } elseif ($t === 'ROLE') {
            if ($needed($l)) $levels[] = vappr_role_level($l['role'], $office, $offName);
        } elseif ($t === 'OFFICE_FIN' && $needed($l)) {
            $fin = vappr_finance_users($office, false);
            $levels[] = ['key' => 'OFFICE_FIN', 'kind' => 'fin', 'tag' => 'Office Finance', 'label' => 'Office Finance' . ($offName !== '' ? ' — ' . $offName : ''),
                'short' => 'Office Finance', 'approvers' => $fin,
                'problem' => $fin ? '' : 'Nobody in Finance covers ' . ($offName !== '' ? $offName : 'this office') . '. An administrator can approve this level; tick this office on a Finance person.'];
        } elseif ($t === 'CENTRAL_FIN' && $needed($l)) {
            $fin = vappr_finance_users($office, true);
            $levels[] = ['key' => 'CENTRAL_FIN', 'kind' => 'fin', 'tag' => 'Central Finance', 'label' => 'Central Finance', 'short' => 'Central Finance', 'approvers' => $fin,
                'problem' => $fin ? '' : 'Nobody in Finance covers all offices. An administrator can approve this level; give the central Finance person all offices.'];
        }
    }
    return $levels;
}

//  The log of this voucher, oldest first.
function vappr_history($voucherId) {
    vappr_migrate();
    try { return ops_all("SELECT * FROM voucher_approval_log WHERE voucher_id=? ORDER BY id", [(int) $voucherId]) ?: []; }
    catch (Throwable $e) { return []; }
}
//  A log row in the name of a given person (the signed-in one by default).
function vappr_log_row($v, $action, $levelKey, $levelLabel, $reason, $uid, $uname, $onBehalf = null) {
    vappr_migrate();
    $ob = $onBehalf ? vappr_user($onBehalf) : null;
    db()->prepare("INSERT INTO voucher_approval_log (voucher_id,round,level_key,level_label,action,user_id,user_name,on_behalf_user_id,on_behalf_name,reason,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int) $v['id'], (int) ($v['appr_round'] ?? 0), (string) $levelKey, (string) $levelLabel, (string) $action,
                   $uid ? (int) $uid : null, (string) $uname,
                   $ob ? (int) $ob['id'] : null, $ob ? vappr_name($ob) : '', (string) $reason, date('c')]);
}
function vappr_log($v, $action, $levelKey = '', $levelLabel = '', $reason = '', $onBehalf = null) {
    $me = function_exists('current_user') ? current_user() : null;
    vappr_log_row($v, $action, $levelKey, $levelLabel, $reason, $me ? (int) $me['id'] : null, $me ? user_name($me) : 'System', $onBehalf);
}

//  The chain with progress: each level gets 'done' (the APPROVED — or "already
//  signed" — log row of this round, or null) and 'auto' (true when it counts as
//  signed because one of its signers already signed an earlier level);
//  'current' is the index of the first level not yet signed (null when all
//  are, or when the voucher is not SUBMITTED).
function vappr_progress($v) {
    $levels = vappr_chain($v);
    $round = (int) ($v['appr_round'] ?? 0);
    $done = [];
    foreach (vappr_history((int) ($v['id'] ?? 0)) as $h)
        if (in_array($h['action'], ['APPROVED', 'ALREADY_SIGNED'], true) && (int) $h['round'] === $round) $done[$h['level_key']] = $h;
    $current = null; $signed = [];     // uid => [the tag of the level they signed, when]
    foreach ($levels as $i => &$l) {
        $l['done'] = $done[$l['key']] ?? null;
        $l['auto'] = $l['done'] !== null && ($l['done']['action'] ?? '') === 'ALREADY_SIGNED';
        if ($l['auto'] && preg_match('/already signed at (.+)$/u', (string) ($l['done']['level_label'] ?? ''), $mm)) $l['done']['via'] = $mm[1];
        // No double signing: a reached level one of whose signers has already
        // signed an earlier level counts as signed (never a Finance level).
        if ($l['done'] === null && $current === null && $l['kind'] !== 'fin') {
            foreach ($l['approvers'] as $u) {
                $s = $signed[(int) $u['id']] ?? null; if (!$s) continue;
                $l['done'] = ['action' => 'ALREADY_SIGNED', 'synthetic' => true, 'via' => $s[0], 'user_id' => (int) $u['id'],
                              'user_name' => vappr_name($u), 'on_behalf_name' => '', 'created_at' => $s[1]];
                $l['auto'] = true;
                break;
            }
        }
        if ($l['done'] !== null) {
            $tag = $l['auto'] ? ($l['done']['via'] ?? $l['tag']) : $l['tag'];
            foreach (['user_id', 'on_behalf_user_id'] as $c)
                if (!empty($l['done'][$c]) && !isset($signed[(int) $l['done'][$c]])) $signed[(int) $l['done'][$c]] = [$tag, (string) ($l['done']['created_at'] ?? '')];
        }
        if ($l['done'] === null && $current === null) $current = $i;
    }
    unset($l);
    if (($v['status'] ?? '') !== 'SUBMITTED') $current = null;
    return ['levels' => $levels, 'current' => $current];
}
//  Write the "already signed" levels the chain has passed into History.
//  Returns the names of the levels written.
function vappr_settle_auto($v, array $p) {
    $out = [];
    foreach ($p['levels'] as $i => $l) {
        if ($p['current'] !== null && $i >= $p['current']) break;
        $d = $l['done'] ?? null;
        if (!$d || empty($d['synthetic'])) continue;
        vappr_log_row($v, 'ALREADY_SIGNED', $l['key'], $l['label'] . ' — already signed at ' . $d['via'], '', (int) $d['user_id'], (string) $d['user_name']);
        $out[] = $l['label'];
    }
    return $out;
}

//  People who may never approve this voucher: the claimant and the submitter,
//  plus — for one level — anyone who already approved another level this round.
function vappr_barred($v, array $progress = null, $levelIdx = null) {
    $b = array_fill_keys(voucher_claimant_uids($v), true);
    $sb = (int) ($v['submitted_by'] ?? 0); if ($sb) $b[$sb] = true;
    if ($progress !== null) foreach ($progress['levels'] as $i => $l)
        if ($i !== $levelIdx && !empty($l['done']['user_id'])) $b[(int) $l['done']['user_id']] = true;
    return $b;
}

//  Who could act at a level now: direct approvers not barred, plus their live
//  delegates (entity VOUCHER, or company-wide) not barred. Empty → an admin may.
function vappr_level_actors($v, array $progress, $idx) {
    $l = $progress['levels'][$idx] ?? null; if (!$l) return [];
    $bar = vappr_barred($v, $progress, $idx);
    $office = voucher_office_id($v) ?: null;
    $out = [];
    foreach ($l['approvers'] as $u) {
        $uid = (int) $u['id'];
        if (!isset($bar[$uid])) $out[$uid] = null;
        if (function_exists('appr_delegates_of'))
            foreach (appr_delegates_of($uid, 'VOUCHER', $office) as $d)
                if (!isset($bar[(int) $d]) && !array_key_exists((int) $d, $out)) $out[(int) $d] = $uid;
    }
    return $out;   // uid => on-behalf-of uid (null when acting in their own right)
}
function vappr_level_stuck($v, array $progress, $idx) { return !vappr_level_actors($v, $progress, $idx); }

//  May THIS person act on the voucher's current level? Returns
//  ['ok' => bool, 'on_behalf' => uid|null, 'as_admin' => bool, 'why' => text].
function vappr_can_act($v, array $progress = null, $u = null) {
    $u = $u ?: (function_exists('current_user') ? current_user() : null);
    $no = fn($why) => ['ok' => false, 'on_behalf' => null, 'as_admin' => false, 'why' => $why];
    if (!$u || ($v['status'] ?? '') !== 'SUBMITTED') return $no('This voucher is not waiting for approval.');
    $progress = $progress ?: vappr_progress($v);
    $idx = $progress['current']; if ($idx === null) return $no('Every level has approved.');
    $me = (int) $u['id'];
    $bar = vappr_barred($v, $progress, $idx);
    if (in_array($me, voucher_claimant_uids($v), true)) return $no('You cannot approve your own expense voucher — it must be checked by someone else.');
    if ($me === (int) ($v['submitted_by'] ?? 0)) return $no('This voucher must be approved by someone other than the person who submitted it (maker ≠ checker).');
    if (isset($bar[$me])) return $no('You approved an earlier level of this voucher; another person approves this one.');
    $actors = vappr_level_actors($v, $progress, $idx);
    if (array_key_exists($me, $actors)) return ['ok' => true, 'on_behalf' => $actors[$me], 'as_admin' => false, 'why' => ''];
    if (!$actors && vappr_is_admin_user($u)) return ['ok' => true, 'on_behalf' => null, 'as_admin' => true, 'why' => ''];
    return $no('This voucher is waiting for ' . vappr_waiting_text($progress) . '.');
}

//  "Sunil Rao (N+2) → Business Director → Finance" — who it waits for now, and what comes after.
function vappr_waiting_text(array $progress) {
    $idx = $progress['current']; if ($idx === null) return '';
    $parts = [];
    foreach ($progress['levels'] as $i => $l) {
        if ($i < $idx) continue;
        if ($i === $idx) {
            $names = array_map('vappr_name', $l['approvers']);
            if ($l['problem'] !== '' || !$names) $parts[] = 'an administrator (' . $l['label'] . ')';
            elseif ($l['kind'] === 'line') $parts[] = $l['short'];
            else $parts[] = $l['short'] . ' (' . implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' …' : '') . ')';
        } else $parts[] = $l['short'];
    }
    return implode(' → ', $parts);
}

//  Approve the current level. Returns [ok, message]. Levels the approver (or
//  an earlier signer) has already signed are counted and written to History;
//  the last level turns the voucher APPROVED and fills the printed signature
//  boxes from the chain.
function vappr_approve($v, $note = '') {
    vappr_migrate();
    $p = vappr_progress($v);
    $can = vappr_can_act($v, $p);
    if (!$can['ok']) return [false, $can['why']];
    $idx = $p['current']; $lvl = $p['levels'][$idx];
    vappr_log($v, 'APPROVED', $lvl['key'], $lvl['label'] . ($can['as_admin'] ? ' (approved by an administrator)' : ''), trim((string) $note), $can['on_behalf']);
    $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int) $v['id']]);
    $p = vappr_progress($v);
    $auto = vappr_settle_auto($v, $p);
    $also = $auto ? ' ' . implode(', ', $auto) . (count($auto) === 1 ? ' counts' : ' count') . ' as signed too — already signed at an earlier level.' : '';
    if ($p['current'] !== null) return [true, 'Approved at ' . $lvl['label'] . '.' . $also . ' It now waits for ' . vappr_waiting_text($p) . '.'];
    // Last level: APPROVED. Checked by = the first level, Approved by = the
    // last level before Finance, Authorized by = Finance.
    $first = ''; $line = ''; $fin = [];
    foreach ($p['levels'] as $l) {
        $n = (string) ($l['done']['user_name'] ?? '');
        if ($first === '') $first = $n;
        if ($l['kind'] === 'fin') $fin[] = $n; else $line = $n;
    }
    $me = current_user();
    db()->prepare("UPDATE vouchers SET status='APPROVED', checked_by=?, approved_by=?, authorized_by=?, approved_at=? WHERE id=? AND status='SUBMITTED'")
        ->execute([$first, $line !== '' ? $line : user_name($me), implode(', ', array_unique(array_filter($fin))), date('c'), (int) $v['id']]);
    return [true, 'Voucher approved — every level has signed it.' . $also . ' Finance can now mark it paid.'];
}

//  Q3 — send it back to the claimant with a reason; back to DRAFT. The reason
//  shows on the voucher and in its History; resubmitting restarts the chain.
function vappr_send_back($v, $reason) {
    vappr_migrate();
    $reason = trim((string) $reason);
    if ($reason === '') return [false, 'Say why you are sending it back — the claimant needs to know what to correct.'];
    $p = vappr_progress($v);
    $can = vappr_can_act($v, $p);
    if (!$can['ok']) return [false, $can['why']];
    $lvl = $p['levels'][$p['current']];
    vappr_log($v, 'SENT_BACK', $lvl['key'], $lvl['label'], $reason, $can['on_behalf']);
    db()->prepare("UPDATE vouchers SET status='DRAFT', submitted_by='', sent_back_reason=?, sent_back_by=?, sent_back_at=? WHERE id=? AND status='SUBMITTED'")
        ->execute([$reason, user_name(current_user()), date('c'), (int) $v['id']]);
    return [true, 'Sent back to ' . voucher_claimant_name($v) . ' with your reason. It comes back to the first level when resubmitted.'];
}

//  Submitting opens a new round: every level approves again.
function vappr_submit($v) {
    vappr_migrate();
    $round = (int) ($v['appr_round'] ?? 0) + 1;
    db()->prepare("UPDATE vouchers SET status='SUBMITTED', submitted_at=?, submitted_by=?, appr_round=?, sent_back_reason='', sent_back_by='', sent_back_at='' WHERE id=? AND status='DRAFT'")
        ->execute([date('c'), (string) (int) (current_user()['id'] ?? 0), $round, (int) $v['id']]);
    $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int) $v['id']]);
    vappr_log($v, 'SUBMITTED');
    return $v;
}

//  Section A (owner, 17 Nov 2026): a person sees a voucher through the chain
//  only when (b) they must approve it NOW — they, or the delegate of the
//  person, at its current level — or (c) they have approved, been counted as
//  signed, or sent it back, in any round (a delegate's signature counts for the
//  person they stood in for too). Being named at a later level, or holding a
//  role a level uses, shows nothing until the voucher reaches them.
function vappr_is_participant($v, $uid = null) {
    $uid = (int) ($uid ?? (current_user()['id'] ?? 0)); if (!$uid) return false;
    foreach (vappr_history((int) ($v['id'] ?? 0)) as $h) {
        if (!in_array($h['action'], ['APPROVED', 'ALREADY_SIGNED', 'SENT_BACK'], true)) continue;
        if ((int) $h['user_id'] === $uid || (int) ($h['on_behalf_user_id'] ?? 0) === $uid) return true;
    }
    if (($v['status'] ?? '') !== 'SUBMITTED') return false;
    $p = vappr_progress($v);
    return $p['current'] !== null && array_key_exists($uid, vappr_level_actors($v, $p, $p['current']));
}

//  Finance sees a voucher once it reaches a Finance level, or has passed one:
//  waiting at Finance now, approved or paid, or acted on by Finance (signed,
//  sent back) in any round, or reopened from Approved / Paid.
function vappr_finance_reached($v) {
    $st = (string) ($v['status'] ?? '');
    if (in_array($st, ['APPROVED', 'PAID'], true)) return true;
    foreach (vappr_history((int) ($v['id'] ?? 0)) as $h) {
        if (vappr_is_fin_key($h['level_key'] ?? '') || $h['action'] === 'PAID') return true;
        if ($h['action'] === 'REOPENED' && in_array((string) $h['level_key'], ['FROM_APPROVED', 'FROM_PAID'], true)) return true;
    }
    if ($st !== 'SUBMITTED') return false;
    $p = vappr_progress($v);
    return $p['current'] !== null && ($p['levels'][$p['current']]['kind'] ?? '') === 'fin';
}

//  The vouchers whose CURRENT level the signed-in person may approve now — the
//  approvals inbox and "Your pending tasks" (owner, 12 Nov 2026).
function voucher_waiting_on_me() {
    $u = function_exists('current_user') ? current_user() : null;
    if (!$u) return [];
    if (function_exists('licence_blocks') && licence_blocks('mod.vouchers.view')) return [];
    vappr_migrate();
    try { $rows = ops_all("SELECT v.*, i.name inspector_name, i.sbu FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id
                           WHERE v.status='SUBMITTED' ORDER BY v.submitted_at, v.id") ?: []; }
    catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $v) {
        $p = vappr_progress($v);
        $c = vappr_can_act($v, $p, $u);
        if (!$c['ok']) continue;
        $l = $p['levels'][$p['current']];
        $v['_level'] = $l['label']; $v['_waiting'] = vappr_waiting_text($p);
        $v['_warning'] = $c['as_admin'] ? $l['problem'] : '';
        $out[] = $v;
    }
    return $out;
}

// ---- Admin → Approvals → Expense vouchers -------------------------------------

function ops_voucher_approvals($method) {
    ops_require(vappr_config_can(), 'Only the Master Admin can change who approves expense vouchers.');
    vappr_migrate();
    if ($method === 'POST') {
        [$ok, $msg] = vappr_config_save($_POST);
        flash($msg, $ok ? 'success' : 'error');
        redirect('/voucher-approvals');
    }
    if (function_exists('nav_under')) nav_under('voucher-approvals', 'admin', ['label' => 'Approvals', 'url' => '/approvals-setup']);
    $noLine = [];
    try { $noLine = ops_all("SELECT i.id, i.name FROM inspectors i WHERE COALESCE(i.reports_to_id,0)=0 AND COALESCE(i.status,'ACTIVE')='ACTIVE'
                             AND NOT EXISTS (SELECT 1 FROM users u WHERE u.inspector_id=i.id AND COALESCE(u.reports_to_id,0)>0)
                             AND EXISTS (SELECT 1 FROM vouchers v WHERE v.inspector_id=i.id) ORDER BY i.name") ?: []; }
    catch (Throwable $e) { $noLine = []; }
    $cfg = vappr_config();
    $noHolder = [];
    foreach ((array) $cfg as $l) if ($l['type'] === 'ROLE' && !vappr_role_holders($l['role'], 0)) $noHolder[] = vappr_role_label($l['role']);
    view('ops/voucher_approvals', [
        'cfg' => $cfg, 'types' => VAPPR_TYPES, 'roles' => vappr_level_roles(), 'noLine' => $noLine, 'noHolder' => $noHolder,
        'finAll' => vappr_finance_users(0, true),
        'strip' => function_exists('apsetup_strip') ? apsetup_strip('vouchers') : '',
    ]);
    return true;
}

//  Who covers for a voucher approver who is away — the existing delegation
//  table and screen, limited to vouchers (as /quote-delegations is to quotations).
function ops_voucher_delegations($method) {
    ops_require(vappr_config_can(), 'Only the Master Admin can set who covers for a voucher approver.');
    if (function_exists('appr_migrate')) appr_migrate();
    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'save') { [$ok, $msg] = appr_delegation_save(0, ['entity' => 'VOUCHER'] + $_POST); flash($msg, $ok ? 'success' : 'error'); }
        elseif ($do === 'revoke') { [$ok, $msg] = appr_delegation_revoke((int) ($_POST['id'] ?? 0)); flash($msg, $ok ? 'success' : 'error'); }
        redirect('/voucher-delegations');
    }
    if (function_exists('nav_under')) nav_under('voucher-delegations', 'admin', ['label' => 'Expense vouchers', 'url' => '/voucher-approvals']);
    view('ops/approval_delegations', [
        'rows'       => array_values(array_filter(appr_delegations(false), fn($d) => strtoupper((string) $d['entity']) === 'VOUCHER')),
        'people'     => ops_all("SELECT id, first_name, last_name, username FROM users WHERE COALESCE(is_active,1)=1 ORDER BY first_name, last_name, username"),
        'offices'    => function_exists('offices_list') ? offices_list() : [],
        'entities'   => ['VOUCHER' => 'Expense vouchers'],
        'onlyEntity' => 'VOUCHER',
        'title'      => 'Who covers for an expense voucher approver',
    ]);
    return true;
}
