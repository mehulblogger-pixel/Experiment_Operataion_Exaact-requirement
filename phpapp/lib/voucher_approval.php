<?php
// ============================================================================
//  EXPENSE VOUCHER APPROVAL CHAIN — owner, 16 Nov 2026 (branch batch R5, 0.4, C3)
// ============================================================================
//  Before: any coordinator-level person in the company could approve any
//  submitted voucher in one click (DEF-OPS-034), and Finance — who pays it —
//  could not even open it (DEF-OPS-003).
//
//  Now a voucher climbs the claimant's own reporting line and ends with Finance:
//
//      Amit (claimant) → Neha (N+1) → Sunil (N+2) → Finance → APPROVED
//
//  * The line is "Reports to": N+1 is the claimant's team-member record
//    `inspectors.reports_to_id` (a user), falling back to the claimant login's
//    `users.reports_to_id`; N+2 is that person's `users.reports_to_id`; and so on.
//  * The Master Admin decides (Admin → Approvals → Expense vouchers) which levels
//    exist and the total above which each is needed. Nothing is set when this
//    ships, and the default — N+1, then Finance — applies until it is.
//  * Q1: N+1 → N+2 → … up to the highest level the total needs, each in turn;
//    Finance always last (office Finance, then central Finance).
//  * Q2: a line shorter than the band stops at its highest person; a claimant
//    with no "Reports to" at all waits for an administrator, who is warned.
//  * Q3: any approver at the current level may send it back to DRAFT with a
//    reason; resubmitting starts the chain again. No new status exists.
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

//  The levels the Master Admin saved, in the fixed order Q1 requires, or null
//  when nothing is saved (the default chain applies). Each: type => above|null.
function vappr_config() {
    $raw = function_exists('setting_get') ? (string) setting_get(VAPPR_SETTING, '') : '';
    if ($raw === '') return null;
    $j = json_decode($raw, true);
    if (!is_array($j) || !$j) return null;
    $out = [];
    foreach (array_keys(VAPPR_TYPES) as $t) {
        if (!array_key_exists($t, $j)) continue;
        $a = $j[$t];
        $out[$t] = ($a === null || $a === '') ? null : (float) $a;
    }
    return $out ?: null;
}

//  Only the Master Admin changes who approves money paid to staff (owner's rule
//  in R5: "the Master Admin sets the amounts and the levels").
function vappr_config_can() { return function_exists('is_master') && is_master(); }

//  Validate and save the posted levels. Returns [ok, message].
//  $post['use'][TYPE] = '1' to use the level; $post['above'][TYPE] = amount or ''.
function vappr_config_save(array $post) {
    if (!vappr_config_can()) return [false, 'Only the Master Admin can change who approves expense vouchers.'];
    if (!empty($post['reset'])) {
        setting_set(VAPPR_SETTING, '');
        return [true, 'Back to the default: the person the claimant reports to (N+1), then Finance.'];
    }
    $use = (array) ($post['use'] ?? []); $above = (array) ($post['above'] ?? []);
    $cfg = []; $always = false;
    foreach (VAPPR_TYPES as $t => $label) {
        if (empty($use[$t])) continue;
        $a = trim(str_replace([',', '₹', ' '], '', (string) ($above[$t] ?? '')));
        if ($a === '') { $cfg[$t] = null; $always = true; continue; }
        if (!is_numeric($a) || (float) $a < 0) return [false, $label . ': the amount must be a number of rupees, or blank for "always".'];
        $cfg[$t] = (float) $a;
    }
    if (!$cfg) return [false, 'Keep at least one level — a voucher must be approved by somebody.'];
    if (!$always) return [false, 'Leave the amount blank on at least one level, so every voucher, however small, has an approver.'];
    setting_set(VAPPR_SETTING, json_encode($cfg));
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

//  Active Finance logins. $central: only those whose office scope is ALL
//  offices; otherwise those whose scope covers $officeId (which includes the
//  central ones — they cover every office). Works whether Finance users hold
//  all offices (today's default) or only some (the office-based change, 0.1).
function vappr_finance_users($officeId, $central) {
    $out = [];
    try { $rows = ops_all("SELECT * FROM users WHERE is_active=1 ORDER BY first_name, username") ?: []; }
    catch (Throwable $e) { $rows = []; }
    foreach ($rows as $u) {
        if (!empty($u['is_superuser']) || vappr_role_base($u) !== 'FINANCE') continue;
        $sc = user_scope_of($u);
        if ($central) { if ($sc['offices'] === 'ALL') $out[] = $u; continue; }
        if (user_scope_covers($u, (int) $officeId ?: null, '')) $out[] = $u;
    }
    return $out;
}
function vappr_office_name($officeId) {
    if (!$officeId) return '';
    return (string) (ops_val("SELECT name FROM offices WHERE id=?", [(int) $officeId]) ?: '');
}

// ---- The chain ---------------------------------------------------------------

//  The levels THIS voucher needs, in order. Each level:
//    key        'N1'..'N4', 'LINE' (no Reports to), 'OFFICE_FIN', 'CENTRAL_FIN', 'FIN'
//    label      what the screen calls it ("Reporting line N+2")
//    short      for the "Waiting for" line ("N+2", "Finance")
//    approvers  user rows who may approve it directly
//    problem    why nobody can, said to the administrator ('' when fine)
function vappr_chain($v) {
    vappr_migrate();
    $total = (float) ($v['total'] ?? 0);
    $office = voucher_office_id($v);
    $cfg = vappr_config();
    $who = voucher_claimant_name($v);
    $levels = [];

    // How far up the reporting line this total must go.
    if ($cfg === null) $depth = 1;
    else {
        $depth = 0;
        foreach (['N1' => 1, 'N2' => 2, 'N3' => 3, 'N4' => 4] as $t => $k)
            if (array_key_exists($t, $cfg) && ($cfg[$t] === null || $total > $cfg[$t])) $depth = max($depth, $k);
    }
    if ($depth > 0) {
        $line = vappr_line($v, $depth);
        if (!$line) {
            $levels[] = ['key' => 'LINE', 'label' => 'Reporting line', 'short' => 'an administrator', 'approvers' => [],
                'problem' => $who . ' has no "Reports to" on their team-member record, so nobody in their line can approve. '
                           . 'An administrator can approve this one; fill in "Reports to" so the next voucher goes to the right person.'];
        }
        foreach ($line as $p) {
            $u = $p['user']; $ok = $u && (int) ($u['is_active'] ?? 0) === 1;
            $levels[] = ['key' => 'N' . $p['depth'], 'label' => 'Reporting line N+' . $p['depth'],
                'short' => ($u ? vappr_name($u) : 'user #' . $p['uid']) . ' (N+' . $p['depth'] . ')',
                'approvers' => $ok ? [$u] : [],
                'problem' => $ok ? '' : ($u ? vappr_name($u) . ' (N+' . $p['depth'] . ') is switched off. An administrator can approve this level; correct "Reports to" for the next one.'
                                           : 'The N+' . $p['depth'] . ' person no longer exists. An administrator can approve this level; correct "Reports to".')];
        }
    }

    // Finance, always last: office Finance, then central Finance.
    $offName = vappr_office_name($office);
    if ($cfg === null) {
        $fin = vappr_finance_users($office, false); $central = false;
        if (!$fin) { $fin = vappr_finance_users($office, true); $central = true; }
        $levels[] = ['key' => 'FIN', 'label' => $central ? 'Central Finance' : 'Finance' . ($offName !== '' ? ' — ' . $offName : ''),
            'short' => 'Finance', 'approvers' => $fin,
            'problem' => $fin ? '' : 'Nobody in Finance covers ' . ($offName !== '' ? $offName : 'this office') . ' or all offices. An administrator can approve this level; give a Finance person this office.'];
    } else {
        if (array_key_exists('OFFICE_FIN', $cfg) && ($cfg['OFFICE_FIN'] === null || $total > $cfg['OFFICE_FIN'])) {
            $fin = vappr_finance_users($office, false);
            $levels[] = ['key' => 'OFFICE_FIN', 'label' => 'Office Finance' . ($offName !== '' ? ' — ' . $offName : ''),
                'short' => 'Office Finance', 'approvers' => $fin,
                'problem' => $fin ? '' : 'Nobody in Finance covers ' . ($offName !== '' ? $offName : 'this office') . '. An administrator can approve this level; tick this office on a Finance person.'];
        }
        if (array_key_exists('CENTRAL_FIN', $cfg) && ($cfg['CENTRAL_FIN'] === null || $total > $cfg['CENTRAL_FIN'])) {
            $fin = vappr_finance_users($office, true);
            $levels[] = ['key' => 'CENTRAL_FIN', 'label' => 'Central Finance', 'short' => 'Central Finance', 'approvers' => $fin,
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
function vappr_log($v, $action, $levelKey = '', $levelLabel = '', $reason = '', $onBehalf = null) {
    vappr_migrate();
    $me = function_exists('current_user') ? current_user() : null;
    $ob = $onBehalf ? vappr_user($onBehalf) : null;
    db()->prepare("INSERT INTO voucher_approval_log (voucher_id,round,level_key,level_label,action,user_id,user_name,on_behalf_user_id,on_behalf_name,reason,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int) $v['id'], (int) ($v['appr_round'] ?? 0), (string) $levelKey, (string) $levelLabel, (string) $action,
                   $me ? (int) $me['id'] : null, $me ? user_name($me) : 'System',
                   $ob ? (int) $ob['id'] : null, $ob ? vappr_name($ob) : '', (string) $reason, date('c')]);
}

//  The chain with progress: each level gets 'done' (the APPROVED log row of this
//  round, or null); 'current' is the index of the first level not yet approved
//  (null when all are, or when the voucher is not SUBMITTED).
function vappr_progress($v) {
    $levels = vappr_chain($v);
    $round = (int) ($v['appr_round'] ?? 0);
    $done = [];
    foreach (vappr_history((int) $v['id']) as $h)
        if ($h['action'] === 'APPROVED' && (int) $h['round'] === $round) $done[$h['level_key']] = $h;
    $current = null;
    foreach ($levels as $i => &$l) {
        $l['done'] = $done[$l['key']] ?? null;
        if ($l['done'] === null && $current === null) $current = $i;
    }
    unset($l);
    if (($v['status'] ?? '') !== 'SUBMITTED') $current = null;
    return ['levels' => $levels, 'current' => $current];
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

//  "Sunil Rao (N+2) → Finance" — who it waits for now, and what comes after.
function vappr_waiting_text(array $progress) {
    $idx = $progress['current']; if ($idx === null) return '';
    $parts = [];
    foreach ($progress['levels'] as $i => $l) {
        if ($i < $idx) continue;
        if ($i === $idx) {
            $names = array_map('vappr_name', $l['approvers']);
            if ($l['problem'] !== '' || !$names) $parts[] = 'an administrator (' . $l['label'] . ')';
            elseif (strpos($l['key'], 'N') === 0) $parts[] = $l['short'];
            else $parts[] = $l['short'] . ' (' . implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' …' : '') . ')';
        } else $parts[] = $l['short'];
    }
    return implode(' → ', $parts);
}

//  Approve the current level. Returns [ok, message]. The last level turns the
//  voucher APPROVED and fills the printed signature boxes from the chain.
function vappr_approve($v, $note = '') {
    vappr_migrate();
    $p = vappr_progress($v);
    $can = vappr_can_act($v, $p);
    if (!$can['ok']) return [false, $can['why']];
    $idx = $p['current']; $lvl = $p['levels'][$idx];
    vappr_log($v, 'APPROVED', $lvl['key'], $lvl['label'] . ($can['as_admin'] ? ' (approved by an administrator)' : ''), trim((string) $note), $can['on_behalf']);
    $p = vappr_progress(ops_one("SELECT * FROM vouchers WHERE id=?", [(int) $v['id']]));
    if ($p['current'] !== null) return [true, 'Approved at ' . $lvl['label'] . '. It now waits for ' . vappr_waiting_text($p) . '.'];
    // Last level: APPROVED. Checked by = N+1 (first level), Approved by = the
    // highest reporting-line approver, Authorized by = Finance.
    $first = ''; $line = ''; $fin = [];
    foreach ($p['levels'] as $l) {
        $n = (string) ($l['done']['user_name'] ?? '');
        if ($first === '') $first = $n;
        if (strpos($l['key'], 'FIN') !== false) $fin[] = $n; else $line = $n;
    }
    $me = current_user();
    db()->prepare("UPDATE vouchers SET status='APPROVED', checked_by=?, approved_by=?, authorized_by=?, approved_at=? WHERE id=? AND status='SUBMITTED'")
        ->execute([$first, $line !== '' ? $line : user_name($me), implode(', ', array_filter($fin)), date('c'), (int) $v['id']]);
    return [true, 'Voucher approved — every level has signed it. Finance can now mark it paid.'];
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

//  Everyone who has a part in this voucher's chain now, or acted on it before.
function vappr_is_participant($v, $uid = null) {
    $uid = (int) ($uid ?? (current_user()['id'] ?? 0)); if (!$uid) return false;
    foreach (vappr_history((int) $v['id']) as $h) if ((int) $h['user_id'] === $uid) return true;
    if (!in_array($v['status'] ?? '', ['SUBMITTED', 'APPROVED', 'PAID'], true)) return false;
    $p = vappr_progress($v);
    foreach ($p['levels'] as $l) foreach ($l['approvers'] as $u) if ((int) $u['id'] === $uid) return true;
    if ($p['current'] !== null && array_key_exists($uid, vappr_level_actors($v, $p, $p['current']))) return true;
    return false;
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
    view('ops/voucher_approvals', [
        'cfg' => vappr_config(), 'types' => VAPPR_TYPES, 'noLine' => $noLine,
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
