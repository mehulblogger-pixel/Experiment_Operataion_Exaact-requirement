<?php
// ============================================================================
//  EXAACT Recruitment — Configurable Approval matrix + SLA + reminders +
//  escalations (Phase 6). Additive and non-destructive.
//
//  Everything is DATA an administrator configures — never hardcoded:
//   • Approval RULES matched by entity (requisition / offer / salary), by
//     department / business unit / grade / position and by a value band
//     (e.g. offers above ₹X need a Director). The narrowest match wins.
//   • Each rule has a multi-LEVEL chain: approver role (or a named user), an
//     SLA (days), a reminder cadence and an escalation target.
//   • A runtime REQUEST + STEPS drive the chain; approve advances, reject
//     closes. On completion a callback updates the entity (offer/requisition).
//   • A cron TICK sends reminders and escalations when an SLA is breached.
//   • Every notification goes out through the platform mailer (ops_mail).
// ============================================================================

//  Phase 3 · M1 — HIRING_REQUEST joins the list. The engine was already
//  entity-agnostic (recruit_approval_requests carries entity + entity_id), so
//  this constant is the whole of what it needed to know. No table, no column.
const APPR_ENTITIES = [
    'HIRING_REQUEST' => 'Hiring Request',
    'REQUISITION'    => 'Requisition (SRF)',
    'OFFER'          => 'Offer',
    'SALARY'         => 'Salary structure',
];

function appr_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_rules (
            id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(160) DEFAULT '',
            entity VARCHAR(20) DEFAULT 'OFFER',
            applies_department VARCHAR(160) DEFAULT '', applies_sbu VARCHAR(120) DEFAULT '',
            applies_grade VARCHAR(120) DEFAULT '', applies_position VARCHAR(160) DEFAULT '',
            min_amount DECIMAL(14,2) DEFAULT 0, max_amount DECIMAL(14,2) DEFAULT 0,
            active INT DEFAULT 1, sort INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_levels (
            id $pk, rule_id INT, seq INT DEFAULT 0, label VARCHAR(120) DEFAULT '',
            approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            sla_days INT DEFAULT 2, reminder_days INT DEFAULT 1,
            escalate_role VARCHAR(40) DEFAULT '', escalate_user_id INT NULL)");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_requests (
            id $pk, entity VARCHAR(20) DEFAULT '', entity_id INT DEFAULT 0,
            rule_id INT DEFAULT 0, rule_name VARCHAR(160) DEFAULT '', subject VARCHAR(240) DEFAULT '',
            amount DECIMAL(14,2) DEFAULT 0, status VARCHAR(20) DEFAULT 'PENDING',
            current_seq INT DEFAULT 0, requester VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '', closed_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_steps (
            id $pk, request_id INT, seq INT DEFAULT 0, label VARCHAR(120) DEFAULT '',
            approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            escalate_role VARCHAR(40) DEFAULT '', escalate_user_id INT NULL,
            sla_due VARCHAR(30) DEFAULT '', reminder_at VARCHAR(30) DEFAULT '',
            status VARCHAR(20) DEFAULT 'PENDING', acted_by VARCHAR(160) DEFAULT '',
            acted_at VARCHAR(30) DEFAULT '', remarks VARCHAR(400) DEFAULT '',
            reminded_at VARCHAR(30) DEFAULT '', escalated INT DEFAULT 0)");
        // Phase 3 · M2 — three additive, nullable columns on the EXISTING rules
        // table. No new rule table: a policy is still one row here.
        //   applies_office_id  the branch dimension the matrix could not express
        //   effective_from/to  so a policy can be scheduled and retired rather
        //                      than only switched off
        // Priority is NOT a new column: `sort` already exists and the screen
        // already calls it "Match order".
        try { ensure_column('recruit_approval_rules', 'applies_office_id', 'INT NULL'); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_rules', 'effective_from', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_rules', 'effective_to', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        // Phase 3 · M2 — DELEGATION. The one new table, and the audit says why:
        // nothing in the application can represent a standing, dated, scoped
        // transfer of approval authority. IDEMS has `report_approvals.delegated_to`
        // — a single nullable integer on one step of a different module's engine,
        // with no delegator, no dates, no scope and no revocation — which is a
        // per-step hand-off, not "while I am away, B acts for me".
        db()->exec("CREATE TABLE IF NOT EXISTS approval_delegations (
            id $pk, delegator_user_id INT, delegate_user_id INT,
            entity VARCHAR(20) DEFAULT '',
            office_id INT NULL,
            effective_from VARCHAR(30) DEFAULT '', effective_to VARCHAR(30) DEFAULT '',
            reason VARCHAR(240) DEFAULT '', active INT DEFAULT 1,
            created_by VARCHAR(160) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
            revoked_by VARCHAR(160) DEFAULT '', revoked_at VARCHAR(30) DEFAULT '')");
        if (function_exists('act_index')) {
            act_index('recruit_approval_levels', 'idx_al_rule', '(rule_id)');
            act_index('recruit_approval_steps', 'idx_as_req', '(request_id)');
            act_index('recruit_approval_requests', 'idx_ar_ent', '(entity,entity_id)');
        }
    } catch (Throwable $e) { /* never break boot */ }
}

function _appr_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function _appr_actor() { return function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'system'; }
function _appr_days($n) { return date('c', strtotime('+' . max(0, (int)$n) . ' days')); }

// ---- Rules & levels (config) -----------------------------------------------
function appr_rules($entity = null, $activeOnly = true) {
    appr_migrate();
    $w = []; $a = [];
    if ($activeOnly) $w[] = 'active=1';
    if ($entity) { $w[] = 'entity=?'; $a[] = $entity; }
    $wc = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    return ops_all("SELECT * FROM recruit_approval_rules $wc ORDER BY entity, sort, id", $a);
}
function appr_rule($id) { appr_migrate(); return ops_one("SELECT * FROM recruit_approval_rules WHERE id=?", [(int)$id]) ?: null; }
function appr_levels($ruleId) { appr_migrate(); return ops_all("SELECT * FROM recruit_approval_levels WHERE rule_id=? ORDER BY seq, id", [(int)$ruleId]); }

//  M2 §34 — A PARTIAL UPDATE MUST NOT ERASE WHAT IT DOES NOT CARRY.
//  This previously read every column as trim($post[$c] ?? '') on an UPDATE, so a
//  post that simply did not include a field blanked it — a condition, a branch
//  or an effective date could be silently cleared by a form that never showed it.
//  Now only the keys actually present in the post are written; clearing a value
//  means posting it empty, which is a deliberate act.
function appr_rule_save($id, $post) {
    appr_migrate();
    $id = (int) $id;
    $existing = $id > 0 ? appr_rule($id) : null;
    $text = ['code','name','applies_department','applies_sbu','applies_grade','applies_position','effective_from','effective_to'];
    $num  = ['min_amount','max_amount'];
    $int  = ['sort','applies_office_id'];

    $val = function ($k, $default = '') use ($post, $existing) {
        if (array_key_exists($k, $post)) return trim((string) $post[$k]);
        if ($existing && array_key_exists($k, $existing)) return (string) $existing[$k];
        return $default;
    };
    $entity = array_key_exists($post['entity'] ?? '', APPR_ENTITIES)
        ? $post['entity'] : ($existing['entity'] ?? 'OFFER');

    $cols = ['entity' => $entity];
    foreach ($text as $c) $cols[$c] = $val($c);
    foreach ($num  as $c) $cols[$c] = (float) $val($c, '0');
    foreach ($int  as $c) $cols[$c] = ($c === 'applies_office_id') ? ((int) $val($c, '0') ?: null) : (int) $val($c, '0');
    foreach (['effective_from','effective_to'] as $d)            // dates, or nothing
        if ($cols[$d] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($cols[$d], 0, 10))) $cols[$d] = '';
        else $cols[$d] = substr($cols[$d], 0, 10);

    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE recruit_approval_rules SET $set WHERE id=?")
            ->execute([...array_values($cols), $id]);
        appr_audit_policy($id, 'Approval policy updated: ' . ($cols['name'] ?: '#' . $id));
        return $id;
    }
    if (trim((string) $cols['name']) === '') $cols['name'] = 'New rule';
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO recruit_approval_rules (" . implode(',', $keys) . ",active,created_at) VALUES ("
        . implode(',', array_fill(0, count($keys), '?')) . ",1,?)")
        ->execute([...array_values($cols), _appr_now()]);
    $new = (int) db()->lastInsertId();
    appr_audit_policy($new, 'Approval policy created: ' . $cols['name']);
    return $new;
}

//  M2 §35 — approval configuration is sensitive, so every change goes on the
//  EXISTING audit spine. No second audit system.
function appr_audit_policy($ruleId, $what) {
    if (function_exists('act_log')) act_log('APPROVAL_POLICY', (int) $ruleId, 'SYSTEM', $what, ['auto' => 1]);
}
function appr_rule_set_active($id, $on) {
    appr_migrate();
    db()->prepare("UPDATE recruit_approval_rules SET active=? WHERE id=?")->execute([$on?1:0,(int)$id]);
    appr_audit_policy($id, $on ? 'Approval policy activated' : 'Approval policy deactivated');
}
function appr_level_save($post) {
    appr_migrate();
    $rid = (int)($post['rule_id'] ?? 0); if (!$rid) return;
    // Validate the posted roles against the roles THIS workspace's plan uses, so a
    // switched-off module's role (Inspector, Marketing, Finance…) can never be saved.
    $roleSet = array_keys(function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES);
    $okApprover = fn($v) => in_array($v, $roleSet, true) || (function_exists('appr_is_org_approver') && appr_is_org_approver($v));
    $role = $okApprover($post['approver_role'] ?? '') ? $post['approver_role'] : '';
    $esc  = $okApprover($post['escalate_role'] ?? '') ? $post['escalate_role'] : '';
    $data = [(int)($post['seq']??0), trim((string)($post['label']??'')), $role, (int)($post['approver_user_id']??0)?:null,
             max(0,(int)($post['sla_days']??2)), max(0,(int)($post['reminder_days']??1)), $esc, (int)($post['escalate_user_id']??0)?:null];
    $lid = (int)($post['level_id'] ?? 0);
    if ($lid > 0) { $data[] = $lid;
        db()->prepare("UPDATE recruit_approval_levels SET seq=?,label=?,approver_role=?,approver_user_id=?,sla_days=?,reminder_days=?,escalate_role=?,escalate_user_id=? WHERE id=?")->execute($data);
    } else { array_unshift($data, $rid);
        db()->prepare("INSERT INTO recruit_approval_levels (rule_id,seq,label,approver_role,approver_user_id,sla_days,reminder_days,escalate_role,escalate_user_id) VALUES (?,?,?,?,?,?,?,?,?)")->execute($data);
    }
    appr_audit_policy($rid, 'Approval level saved: ' . (trim((string)($post['label'] ?? '')) ?: 'level ' . (int)($post['seq'] ?? 0))
        . ($role !== '' ? ' → ' . $role : ''));
}
function appr_level_delete($id) {
    appr_migrate();
    $lv = ops_one("SELECT rule_id, label FROM recruit_approval_levels WHERE id=?", [(int)$id]);
    db()->prepare("DELETE FROM recruit_approval_levels WHERE id=?")->execute([(int)$id]);
    if ($lv) appr_audit_policy((int) $lv['rule_id'], 'Approval level removed: ' . ($lv['label'] ?: '#' . (int) $id));
}

// ---- Matching (narrowest wins) ---------------------------------------------
//  PRECEDENCE — deterministic, and now written down (M2 §7).
//
//    1. SPECIFICITY   how many conditions the rule actually pinned down:
//                     department, business unit, grade, position, branch, and
//                     one point for a matched amount band. A rule that names
//                     more wins.
//    2. MATCH ORDER   `sort` on the rule — what the screen calls "Match order".
//    3. ID            creation order.
//
//  The loop keeps a rule only on a STRICTLY greater score while iterating in
//  ORDER BY sort, id — so an equal-specificity tie falls to `sort`, then to the
//  older rule. Never random, never a silent coin-toss. appr_match_all() exposes
//  the whole ranked list so an administrator can be shown the runners-up and any
//  ambiguity, rather than just the winner.
function appr_match($entity, $ctx) {
    $ranked = appr_match_all($entity, $ctx);
    return $ranked ? $ranked[0]['rule'] : null;
}

//  Every rule that matches, best first, each with the score that ranked it and
//  whether it ties with the one above. This is what the preview and the conflict
//  warnings read.
function appr_match_all($entity, $ctx) {
    $out = [];
    foreach (appr_rules($entity, true) as $r) {
        $score = appr_rule_score($r, $ctx);
        if ($score === null) continue;                      // a condition failed
        $out[] = ['rule' => $r, 'score' => $score];
    }
    // Stable: score desc, then the order appr_rules() already returns them in
    // (sort, id). usort is not stable across engines, so rank is carried.
    foreach ($out as $i => $row) $out[$i]['seen'] = $i;
    usort($out, function ($a, $b) {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return $a['seen'] <=> $b['seen'];
    });
    foreach ($out as $i => $row)
        $out[$i]['ties_with_previous'] = $i > 0 && $out[$i - 1]['score'] === $row['score'];
    return $out;
}

//  How well one rule fits, or null when it does not apply at all.
function appr_rule_score($r, $ctx) {
    $map = ['applies_department'=>'department','applies_sbu'=>'sbu','applies_grade'=>'grade','applies_position'=>'position'];
    {
        $ok = true; $score = 0;
        foreach ($map as $col => $k) {
            $f = trim((string)($r[$col] ?? '')); if ($f === '') continue;
            $vals = array_map('strtolower', array_map('trim', explode(',', $f)));
            $have = strtolower(trim((string)($ctx[$k] ?? '')));
            $hit  = in_array($have, $vals, true);
            // M3 — a rule keyed on Department must match the requisition even when
            // the two were written in different words. The rule box is free text
            // and the requisition stores a coded value, so a rule for "Quality"
            // never fired on a requisition filed as "QAQC". Compare through the
            // canonical department instead. This can only ever ADD a match that
            // the customer has APPROVED: an unrecognised term canonicalises to
            // itself, so nothing matches by guesswork.
            if (!$hit && $k === 'department' && $have !== '' && function_exists('dept_canon')) {
                try {
                    $mine = strtolower(dept_canon($have));
                    foreach ($vals as $v) if ($v !== '' && strtolower(dept_canon($v)) === $mine) { $hit = true; break; }
                } catch (Throwable $e) {}
            }
            if ($hit) $score++;
            else { $ok = false; break; }
        }
        if (!$ok) return null;
        // M2 — BRANCH. A rule may be pinned to one office; an unpinned rule is
        // the customer's global policy and still applies. A rule for another
        // branch does not apply at all.
        $ruleOffice = (int) ($r['applies_office_id'] ?? 0);
        if ($ruleOffice > 0) {
            if ((int) ($ctx['office_id'] ?? 0) !== $ruleOffice) return null;
            $score++;
        }
        // M2 — EFFECTIVE DATES. Before it starts or after it ends, a rule is not
        // in force. Blank means "no limit", which is what every existing rule has.
        $today = date('Y-m-d');
        $from = substr(trim((string) ($r['effective_from'] ?? '')), 0, 10);
        $to   = substr(trim((string) ($r['effective_to']   ?? '')), 0, 10);
        if ($from !== '' && $today < $from) return null;
        if ($to   !== '' && $today > $to)   return null;

        $amt = (float)($ctx['amount'] ?? 0);
        if ((float)$r['min_amount'] > 0 && $amt < (float)$r['min_amount']) return null;
        if ((float)$r['max_amount'] > 0 && $amt > (float)$r['max_amount']) return null;
        if ((float)$r['min_amount'] > 0 || (float)$r['max_amount'] > 0) $score++;   // a band that matched adds specificity
        return $score;
    }
}

// ---- Delegation administration (Phase 3 · M2) ------------------------------
//  Configuring a delegation is a privileged act: it moves approval authority
//  from one person to another. It is gated exactly like the rest of approval
//  configuration, and every change is audited.
function appr_delegations($activeOnly = false) {
    appr_migrate();
    $w = $activeOnly ? 'WHERE d.active=1' : '';
    try {
        return ops_all("SELECT d.*, du.first_name dfn, du.last_name dln, du.username dun,
                               eu.first_name efn, eu.last_name eln, eu.username eun
                        FROM approval_delegations d
                        LEFT JOIN users du ON du.id=d.delegator_user_id
                        LEFT JOIN users eu ON eu.id=d.delegate_user_id
                        $w ORDER BY d.id DESC");
    } catch (Throwable $e) { return []; }
}
function appr_delegation($id) {
    appr_migrate();
    try { return ops_one("SELECT * FROM approval_delegations WHERE id=?", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }
}

//  Returns [ok, message, id]. Validates against the real register rather than
//  trusting the form: a dropdown is not a security boundary.
function appr_delegation_save($id, array $post) {
    // The right is asked HERE, at the write, and not only on the route — the
    // discipline M1 established after branch scope was found living on the route
    // alone. Moving approval authority is privileged; a helper called directly
    // must not reach the table around the gate.
    if (function_exists('hiring_admin_can') && !hiring_admin_can())
        return [false, 'Only an administrator can configure approval delegation.', 0];
    appr_migrate();
    $id = (int) $id;
    $existing = $id > 0 ? appr_delegation($id) : null;
    if ($id > 0 && !$existing) return [false, 'That delegation no longer exists.', 0];

    $val = function ($k, $d = '') use ($post, $existing) {
        if (array_key_exists($k, $post)) return trim((string) $post[$k]);
        if ($existing && array_key_exists($k, $existing)) return (string) $existing[$k];
        return $d;
    };
    $from_u = (int) $val('delegator_user_id', '0');
    $to_u   = (int) $val('delegate_user_id', '0');
    if ($from_u <= 0 || $to_u <= 0) return [false, 'Choose who is delegating and who is acting for them.', 0];
    if ($from_u === $to_u) return [false, 'A person cannot delegate their approval authority to themselves.', 0];
    foreach ([[$from_u, 'delegating'], [$to_u, 'acting']] as $u) {
        try { $row = ops_one("SELECT id, is_active FROM users WHERE id=?", [$u[0]]); } catch (Throwable $e) { $row = null; }
        if (!$row) return [false, 'That is not someone in this workspace.', 0];
        if ((int) ($row['is_active'] ?? 0) !== 1) return [false, 'That user is switched off, so they cannot be ' . $u[1] . '.', 0];
    }
    $entity = strtoupper($val('entity'));
    if ($entity !== '' && !isset(APPR_ENTITIES[$entity])) return [false, 'That is not something this workspace approves.', 0];
    $office = (int) $val('office_id', '0') ?: null;
    if ($office !== null && function_exists('scope_allows') && !scope_allows($office, null))
        return [false, 'That branch is outside your office / branch scope.', 0];
    $dates = [];
    foreach (['effective_from', 'effective_to'] as $d) {
        $v = substr($val($d), 0, 10);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return [false, 'That is not a date.', 0];
        $dates[$d] = $v;
    }
    if ($dates['effective_from'] !== '' && $dates['effective_to'] !== '' && $dates['effective_to'] < $dates['effective_from'])
        return [false, 'The delegation cannot end before it starts.', 0];

    $cols = [
        'delegator_user_id' => $from_u, 'delegate_user_id' => $to_u,
        'entity' => $entity, 'office_id' => $office,
        'effective_from' => $dates['effective_from'], 'effective_to' => $dates['effective_to'],
        'reason' => substr($val('reason'), 0, 240),
        'active' => array_key_exists('active', $post) ? (int) !!$post['active'] : (int) ($existing['active'] ?? 1),
    ];
    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE approval_delegations SET $set WHERE id=?")->execute([...array_values($cols), $id]);
    } else {
        $keys = array_keys($cols);
        db()->prepare("INSERT INTO approval_delegations (" . implode(',', $keys) . ",created_by,created_at) VALUES ("
            . implode(',', array_fill(0, count($keys), '?')) . ",?,?)")
            ->execute([...array_values($cols), _appr_actor(), _appr_now()]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('act_log'))
        act_log('APPROVAL_DELEGATE', $id, 'SYSTEM',
                ($existing ? 'Delegation updated' : 'Delegation created') . ': user #' . $from_u . ' → user #' . $to_u
                . ($cols['effective_from'] !== '' || $cols['effective_to'] !== '' ? ' (' . ($cols['effective_from'] ?: '…') . ' to ' . ($cols['effective_to'] ?: '…') . ')' : ''),
                ['auto' => 1]);
    return [true, $existing ? 'Delegation saved.' : 'Delegation created.', $id];
}

function appr_delegation_revoke($id) {
    if (function_exists('hiring_admin_can') && !hiring_admin_can())
        return [false, 'Only an administrator can revoke an approval delegation.'];
    appr_migrate();
    $d = appr_delegation($id); if (!$d) return [false, 'That delegation no longer exists.'];
    db()->prepare("UPDATE approval_delegations SET active=0, revoked_by=?, revoked_at=? WHERE id=?")
        ->execute([_appr_actor(), _appr_now(), (int) $id]);
    if (function_exists('act_log'))
        act_log('APPROVAL_DELEGATE', (int) $id, 'SYSTEM', 'Delegation revoked', ['auto' => 1, 'outcome' => 'REVOKED']);
    return [true, 'Delegation revoked.'];
}

// ---- Who could actually approve a level (Phase 3 · M2) ---------------------
//  M1's adversarial audit found a configuration trap: a level may name a role
//  that no active user holds, and nothing said so. The administrator believed
//  the workflow was configured; the request simply parked.
//
//  Returns the active users eligible for a level, so the screen can warn and the
//  preview can show real names. An org-chart token resolves per request, not per
//  rule, so it is reported as such rather than counted as zero.
function appr_level_eligible($level, $ctx = []) {
    appr_migrate();
    $uid = (int) ($level['approver_user_id'] ?? 0);
    if ($uid > 0) {
        try { $u = ops_one("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE id=?", [$uid]); }
        catch (Throwable $e) { $u = null; }
        return ['kind' => 'USER', 'users' => ($u && (int) $u['is_active'] === 1) ? [$u] : [], 'role' => ''];
    }
    $role = trim((string) ($level['approver_role'] ?? ''));
    if ($role === '') return ['kind' => 'NONE', 'users' => [], 'role' => ''];
    if (function_exists('appr_is_org_approver') && appr_is_org_approver($role)) {
        // Resolved against the position tree when a request exists; a hiring
        // admin can always act, so this can never strand a step.
        $who = [];
        if (!empty($ctx['position_id']) && function_exists('appr_resolve_org_approver')) {
            $rid = (int) appr_resolve_org_approver($role, (int) $ctx['position_id'], (string) ($ctx['department'] ?? ''));
            if ($rid > 0) { try { $u = ops_one("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE id=?", [$rid]); if ($u) $who[] = $u; } catch (Throwable $e) {} }
        }
        return ['kind' => 'ORG', 'users' => $who, 'role' => $role];
    }
    try { $rows = ops_all("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE role=? AND is_active=1 ORDER BY first_name", [$role]); }
    catch (Throwable $e) { $rows = []; }
    return ['kind' => 'ROLE', 'users' => $rows, 'role' => $role];
}

//  Levels of a rule that nobody can currently action. ORG tokens are excluded:
//  they resolve per request and fall back to a hiring admin by design.
function appr_orphan_levels($ruleId, $ctx = []) {
    $out = [];
    foreach (appr_levels($ruleId) as $lv) {
        $e = appr_level_eligible($lv, $ctx);
        if ($e['kind'] === 'ORG') continue;
        if (!$e['users']) $out[] = ['level' => $lv, 'why' => $e['kind'] === 'NONE' ? 'no approver configured' : 'no active user holds this role'];
    }
    return $out;
}

// ---- "Why this approval?" — the deterministic resolver (M2 §14) ------------
//  Given the facts of a request, show the administrator exactly what the policy
//  would do: which rule wins, which runners-up matched, whether anything ties,
//  the levels, and who could actually act at each one.
function appr_preview($entity, $ctx) {
    $ranked = appr_match_all($entity, $ctx);
    $win = $ranked ? $ranked[0]['rule'] : null;
    $levels = [];
    if ($win) {
        foreach (appr_levels($win['id']) as $lv)
            $levels[] = ['level' => $lv, 'eligible' => appr_level_eligible($lv, $ctx)];
    }
    return [
        'matched'   => $win,
        'ranked'    => $ranked,
        'ambiguous' => count($ranked) > 1 && !empty($ranked[1]['ties_with_previous']),
        'levels'    => $levels,
        'orphans'   => $win ? appr_orphan_levels($win['id'], $ctx) : [],
        'no_match'  => $win === null,
    ];
}

// ---- Requests & steps ------------------------------------------------------
function appr_open($entity, $entityId) {
    appr_migrate();
    return ops_one("SELECT * FROM recruit_approval_requests WHERE entity=? AND entity_id=? AND status='PENDING' ORDER BY id DESC LIMIT 1", [$entity, (int)$entityId]) ?: null;
}
function appr_request($id) { appr_migrate(); return ops_one("SELECT * FROM recruit_approval_requests WHERE id=?", [(int)$id]) ?: null; }

//  M1 CORRECTION — close any chain still open against an entity, because the
//  entity itself has gone away (cancelled). This is not a new cancellation
//  mechanism: every query in this engine that decides whether a chain is live
//  already asks for status='PENDING' — appr_open(), appr_inbox(), appr_tick()
//  and appr_act() itself — so moving the request row off PENDING closes it
//  everywhere at once, with nothing else to change.
//
//  Returns the number of chains closed.
function appr_cancel_open($entity, $entityId, $reason = '') {
    appr_migrate();
    $n = 0;
    try {
        foreach (ops_all("SELECT id FROM recruit_approval_requests WHERE entity=? AND entity_id=? AND status='PENDING'",
                         [(string) $entity, (int) $entityId]) as $r) {
            db()->prepare("UPDATE recruit_approval_steps SET status='CANCELLED', acted_by=?, acted_at=?, remarks=? WHERE request_id=? AND status='PENDING'")
                ->execute([_appr_actor(), _appr_now(), substr(trim((string) $reason), 0, 400), (int) $r['id']]);
            db()->prepare("UPDATE recruit_approval_requests SET status='CANCELLED', closed_at=? WHERE id=?")
                ->execute([_appr_now(), (int) $r['id']]);
            $n++;
        }
    } catch (Throwable $e) { /* never break the cancellation itself */ }
    return $n;
}
function appr_steps($requestId) { appr_migrate(); return ops_all("SELECT * FROM recruit_approval_steps WHERE request_id=? ORDER BY seq, id", [(int)$requestId]); }
function appr_current_step($request) {
    $s = ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=? AND seq=? AND status='PENDING' ORDER BY id LIMIT 1", [(int)$request['id'], (int)$request['current_seq']]);
    return $s ?: null;
}

// Start a chain if a rule matches. Returns [started(bool), requestId|0].
function appr_start($entity, $entityId, $ctx, $subject = '', $amount = 0) {
    appr_migrate();
    if (appr_open($entity, $entityId)) return [true, (int)appr_open($entity, $entityId)['id']];
    $rule = appr_match($entity, $ctx);
    if (!$rule) return [false, 0];
    $levels = appr_levels($rule['id']);
    if (!$levels) return [false, 0];
    db()->prepare("INSERT INTO recruit_approval_requests (entity,entity_id,rule_id,rule_name,subject,amount,status,current_seq,requester,created_at) VALUES (?,?,?,?,?,?, 'PENDING', ?,?,?)")
        ->execute([$entity,(int)$entityId,(int)$rule['id'],(string)$rule['name'],(string)$subject,(float)$amount, (int)$levels[0]['seq'], _appr_actor(), _appr_now()]);
    $reqId = (int)db()->lastInsertId();
    // An org-chart approver token ("reporting manager", "HOD", …) is resolved to a
    // real person here, from the requisition's position walked up the reporting
    // line. If it resolves, the step is pinned to that user; if not, the token is
    // kept and appr_can_act lets a hiring admin act so a step is never stranded.
    $orgResolve = function ($role) use ($ctx) {
        if ($role === '' || !function_exists('appr_is_org_approver') || !appr_is_org_approver($role)) return 0;
        $posId = (int) ($ctx['position_id'] ?? 0);
        if (!$posId && !empty($ctx['position'])) {
            try { $pp = ops_one("SELECT id FROM positions WHERE LOWER(name)=LOWER(?) OR LOWER(code)=LOWER(?) LIMIT 1", [$ctx['position'], $ctx['position']]); if ($pp) $posId = (int) $pp['id']; }
            catch (Throwable $e) {}
        }
        return function_exists('appr_resolve_org_approver') ? (int) appr_resolve_org_approver($role, $posId, (string) ($ctx['department'] ?? '')) : 0;
    };
    foreach ($levels as $lv) {
        $role = (string) $lv['approver_role']; $uid = $lv['approver_user_id'] ?: null;
        $esc = (string) $lv['escalate_role']; $euid = $lv['escalate_user_id'] ?: null;
        if (!$uid && ($rid = $orgResolve($role)) > 0) { $uid = $rid; $role = ''; }   // pinned to the resolved manager
        if (!$euid && ($eid = $orgResolve($esc)) > 0) { $euid = $eid; $esc = ''; }
        db()->prepare("INSERT INTO recruit_approval_steps (request_id,seq,label,approver_role,approver_user_id,escalate_role,escalate_user_id,sla_due,reminder_at,status) VALUES (?,?,?,?,?,?,?,?,?, 'PENDING')")
            ->execute([$reqId,(int)$lv['seq'],(string)$lv['label'],$role,$uid,$esc,$euid,_appr_days($lv['sla_days']),_appr_days($lv['reminder_days'])]);
    }
    // Notify the first-level approvers.
    $first = appr_current_step(appr_request($reqId));
    if ($first) appr_email_approver($first, appr_request($reqId), 'requested');
    return [true, $reqId];
}

// ---- Delegation (Phase 3 · M2) ---------------------------------------------
//  Who is currently acting FOR whom. A delegation transfers the authority its
//  delegator actually holds — never more — for a stated period and, optionally,
//  one entity and one branch.
//
//  Returns the delegator user ids this person may currently act for.
function appr_delegators_for($userId, $entity = '', $officeId = null) {
    appr_migrate();
    $userId = (int) $userId; if ($userId <= 0) return [];
    $today = date('Y-m-d');
    try {
        $rows = ops_all("SELECT * FROM approval_delegations WHERE delegate_user_id=? AND active=1", [$userId]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $d) {
        $from = substr(trim((string) $d['effective_from']), 0, 10);
        $to   = substr(trim((string) $d['effective_to']), 0, 10);
        if ($from !== '' && $today < $from) continue;         // not started
        if ($to   !== '' && $today > $to)   continue;         // expired
        $de = trim((string) ($d['entity'] ?? ''));
        if ($de !== '' && strtoupper($de) !== strtoupper((string) $entity)) continue;   // wrong entity

        // M2 CORRECTION · FINDING A — a delegation that NAMES a branch must not
        // apply where a branch cannot be established. This read
        // "$do > 0 && $officeId !== null && ..." which treated a missing branch
        // context as "do not filter", so a delegation scoped to one office was
        // UNSCOPED for every entity that carries no office (offer, salary,
        // requisition). The administrator said "my Ahmedabad work while I'm
        // away"; they also got company-wide offer approvals.
        $do = (int) ($d['office_id'] ?? 0);
        if ($do > 0) {
            if ($officeId === null) continue;                // no branch to check against → does not apply
            if ((int) $officeId !== $do) continue;            // wrong branch
        }

        // M2 CORRECTION · FINDING B — the DELEGATOR must still be active. This
        // was asked on the role path only, so the two paths disagreed: with the
        // delegator switched off a role step refused and a named-user step
        // still granted, and somebody who had left the company kept lending
        // their approval authority. Asked ONCE here, so both paths inherit it.
        $dl = (int) $d['delegator_user_id'];
        try { $du = ops_one("SELECT is_active FROM users WHERE id=?", [$dl]); } catch (Throwable $e) { $du = null; }
        if (!$du || (int) ($du['is_active'] ?? 0) !== 1) continue;

        $out[] = $dl;
    }
    return array_values(array_unique($out));
}

//  NO CHAINING. A delegate may act for their delegator, and that is where it
//  stops: A→B→C is refused, because B cannot pass on an authority that was only
//  lent to them. Nothing walks the table twice, and this test says so out loud.
function appr_delegation_chains($userId, $entity = '', $officeId = null) {
    foreach (appr_delegators_for($userId, $entity, $officeId) as $d)
        if (appr_delegators_for($d, $entity, $officeId)) return true;
    return false;
}

//  A step on its own does not know which entity or branch it belongs to, and
//  delegation scope needs both. This decorates it from its request — one place,
//  so every caller asks the same question.
function appr_step_context($step, $req = null) {
    $req = $req ?: appr_request((int) ($step['request_id'] ?? 0));
    if (!$req) return $step;
    $step['_entity'] = (string) ($req['entity'] ?? '');
    $step['_office_id'] = null;
    if (strtoupper((string) $req['entity']) === 'HIRING_REQUEST' && function_exists('hreq_get')) {
        $r = hreq_get((int) $req['entity_id']);
        if ($r) $step['_office_id'] = $r['office_id'] ?? null;
    }
    return $step;
}

//  ---- Approval queue visibility (M1 Finding 2, closed in M2) ---------------
//  What a person may SEE in the queue is now the same question as what they may
//  ACT on. It is asked per entity, so the fix could not empty an Offer or Salary
//  queue whose approvers legitimately have no branch relationship to the record.
//
//  Returns true when this row belongs in this person's queue.
function appr_visible($step, $req, $user = null) {
    if (!appr_can_act($step, $user)) return false;                     // never widens
    $entity = strtoupper((string) ($req['entity'] ?? ''));
    if ($entity !== 'HIRING_REQUEST') return true;                     // unchanged for every other entity
    // For a hiring request, seeing it and deciding it are the same question, so
    // the queue asks the guard that governs the decision.
    return appr_guard($req) === '';
}

function appr_can_act($step, $user = null) {
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return false;
    // M1 FINDING B. This was a bare is_master(), which walks straight past the
    // module licence: a master on a workspace that had NOT bought recruitment
    // could act on recruitment approval steps (proved with a probe). is_master_of()
    // is the existing licence-aware master helper — master, but only for a module
    // this installation actually has. Same rule the rest of the app already uses.
    if (function_exists('is_master_of') ? is_master_of('hiring') : (function_exists('is_master') && is_master())) return true;
    if ((int)($step['approver_user_id'] ?? 0) > 0) {
        if ((int)$step['approver_user_id'] === (int)$user['id']) return true;
        // M2 — or this person is currently acting for that named approver.
        return in_array((int) $step['approver_user_id'],
                        appr_delegators_for((int) $user['id'], (string) ($step['_entity'] ?? ''), $step['_office_id'] ?? null), true);
    }
    $role = (string)($step['approver_role'] ?? '');
    // An org-chart approver that could not be pinned to a person: a hiring admin
    // acts so the chain is never stranded.
    if ($role !== '' && function_exists('appr_is_org_approver') && appr_is_org_approver($role))
        return function_exists('hiring_admin_can') ? hiring_admin_can() : (function_exists('is_admin_level') && is_admin_level());
    if ($role !== '' && (string)$user['role'] === $role) return true;
    // M2 — acting for somebody who holds the configured role. The delegator must
    // genuinely hold it: a delegation cannot manufacture an authority its
    // delegator never had.
    if ($role !== '') {
        foreach (appr_delegators_for((int) $user['id'], (string) ($step['_entity'] ?? ''), $step['_office_id'] ?? null) as $dl) {
            // Active status is settled in appr_delegators_for() — one rule, two
            // readers. This asks only the question that is specific to a role
            // step: does the delegator genuinely hold the role being delegated?
            try { $du = ops_one("SELECT role FROM users WHERE id=?", [$dl]); } catch (Throwable $e) { $du = null; }
            if ($du && (string) $du['role'] === $role) return true;
        }
    }
    return false;
}

// ---- The decision guard (Phase 3 · M1) -------------------------------------
//  Asked at the mutation choke point, before anything is written, and returns a
//  reason or an empty string so it can be tested without a redirect.
//
//  ENTITY-SCOPED ON PURPOSE. M1 was asked to secure the Hiring Request path, not
//  to change how offers, salary structures and requisitions have behaved since
//  Phase 6. Applying segregation of duties to every entity is a customer-visible
//  policy change and is recorded as a Phase-3 question, not slipped in here.
function appr_guard($req) {
    $entity = strtoupper((string) ($req['entity'] ?? ''));
    if ($entity !== 'HIRING_REQUEST' || !function_exists('hreq_get')) return '';
    // 1. ENTITLEMENT first — the workspace must have bought recruitment. This is
    //    asked before anything about the person, and a master does not escape it.
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view'))
        return 'The recruitment module is not switched on for this installation.';
    $r = hreq_get((int) ($req['entity_id'] ?? 0));
    if (!$r) return 'That hiring request no longer exists.';
    // 2. BRANCH SCOPE — asked here, at the decision, not only on a route.
    if (function_exists('hreq_in_scope') && !hreq_in_scope($r))
        return 'This hiring request is outside your office / branch scope.';
    // 3. SEGREGATION OF DUTIES — the requestor may not approve their own request.
    //    One rule, two readers: the same helper the direct decision path uses,
    //    including its single stated master exception, neither broadened nor
    //    narrowed here.
    if (function_exists('hreq_segregation_blocks') && hreq_segregation_blocks($r))
        return 'You raised this request, so somebody else has to decide it.';
    return '';
}

// Approve or reject the given step. Returns [ok, message].
function appr_act($stepId, $decision, $remarks = '') {
    appr_migrate();
    $step = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$stepId]);
    if (!$step || $step['status'] !== 'PENDING') return [false, 'This step is not pending.'];
    $req = appr_request((int)$step['request_id']);
    if (!$req || $req['status'] !== 'PENDING') return [false, 'This request is closed.'];
    if ((int)$step['seq'] !== (int)$req['current_seq']) return [false, 'An earlier level is still pending.'];
    $step = appr_step_context($step, $req);
    if (!appr_can_act($step)) return [false, 'You are not the approver for this step.'];
    $why = appr_guard($req);
    if ($why !== '') return [false, $why];

    $now = _appr_now(); $actor = _appr_actor();
    if ($decision === 'reject') {
        db()->prepare("UPDATE recruit_approval_steps SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([$actor,$now,substr((string)$remarks,0,400),(int)$stepId]);
        db()->prepare("UPDATE recruit_approval_requests SET status='REJECTED', closed_at=? WHERE id=?")->execute([$now,(int)$req['id']]);
        $cb = appr_callback($req['entity'], (int)$req['entity_id'], 'REJECTED', $req);
        if ($cb !== true) return appr_undo_step($stepId, $req, $cb);
        appr_email_requester($req, 'rejected', $remarks);
        return [true, 'Rejected.'];
    }
    db()->prepare("UPDATE recruit_approval_steps SET status='APPROVED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([$actor,$now,substr((string)$remarks,0,400),(int)$stepId]);
    // Next pending step?
    $next = ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=? AND status='PENDING' AND seq>? ORDER BY seq LIMIT 1", [(int)$req['id'], (int)$step['seq']]);
    if ($next) {
        db()->prepare("UPDATE recruit_approval_requests SET current_seq=? WHERE id=?")->execute([(int)$next['seq'],(int)$req['id']]);
        appr_email_approver($next, appr_request((int)$req['id']), 'requested');
        return [true, 'Approved — sent to the next approver.'];
    }
    db()->prepare("UPDATE recruit_approval_requests SET status='APPROVED', closed_at=? WHERE id=?")->execute([$now,(int)$req['id']]);
    $cb = appr_callback($req['entity'], (int)$req['entity_id'], 'APPROVED', $req);
    if ($cb !== true) return appr_undo_step($stepId, $req, $cb);
    appr_email_requester($req, 'approved', $remarks);
    return [true, 'Approved — fully cleared.'];
}

//  M1 CORRECTION — put the step and its chain back the way they were, and tell
//  the approver what actually happened.
//
//  Before this, appr_act() wrote the step, closed the chain, called the callback
//  and DISCARDED its result — so when the underlying record refused the decision
//  (a cancelled hiring request, say) the approver was told "Approved — fully
//  cleared" and the history kept an APPROVED step against a record that had been
//  approved of nothing. A decision that could not be applied is not a decision,
//  so nothing about it is left behind.
function appr_undo_step($stepId, $req, $why) {
    try {
        db()->prepare("UPDATE recruit_approval_steps SET status='PENDING', acted_by='', acted_at='', remarks='' WHERE id=?")
            ->execute([(int) $stepId]);
        db()->prepare("UPDATE recruit_approval_requests SET status='PENDING', closed_at='' WHERE id=?")
            ->execute([(int) $req['id']]);
    } catch (Throwable $e) { /* the refusal below is what matters */ }
    return [false, is_string($why) && $why !== '' ? $why : 'That decision could not be applied.'];
}

// Update the underlying entity when a chain completes.
//
//  M1 CORRECTION — returns TRUE when the decision was applied, or a STRING
//  giving the reason it could not be. Only the HIRING_REQUEST branch can return
//  a reason: the other three keep their original best-effort semantics exactly,
//  because their SQL legitimately affects no rows in ordinary cases (an offer
//  already approved, say) and treating that as a failure would change behaviour
//  this correction was told not to touch.
function appr_callback($entity, $entityId, $result, $req = null) {
    try {
        if ($entity === 'HIRING_REQUEST') {
            // The hiring-request layer owns its own state machine and refuses a
            // decision on a request that is no longer open for one. That refusal
            // must reach the approver instead of being discarded.
            if (!function_exists('hreq_apply_decision')) return true;
            [$ok, $msg] = hreq_apply_decision((int) $entityId, $result === 'APPROVED' ? 'APPROVED' : 'REJECTED',
                                              _appr_actor(), (string) ($req['rule_name'] ?? ''), 'CHAIN');
            return $ok ? true : (string) $msg;
        }
        if ($entity === 'OFFER') {
            if ($result === 'APPROVED') db()->prepare("UPDATE job_offers SET status='APPROVED', approved_by=?, approved_at=? WHERE id=? AND status IN ('PENDING_APPROVAL','DRAFT')")->execute(['Approval chain', _appr_now(), (int)$entityId]);
            else db()->prepare("UPDATE job_offers SET status='DRAFT' WHERE id=? AND status='PENDING_APPROVAL'")->execute([(int)$entityId]);
        } elseif ($entity === 'REQUISITION') {
            if ($result === 'APPROVED') db()->prepare("UPDATE requisitions SET status='approved', approved_by=? WHERE id=?")->execute(['Approval chain', (int)$entityId]);
            else db()->prepare("UPDATE requisitions SET status='on_hold' WHERE id=?")->execute([(int)$entityId]);
        }
    } catch (Throwable $e) { /* callback is best-effort */ }
    return true;
}

// ---- Inbox (My approvals) --------------------------------------------------
function appr_inbox($user = null) {
    appr_migrate();
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return [];
    $rows = ops_all("SELECT s.*, r.entity, r.entity_id, r.subject, r.amount, r.rule_name, r.requester, r.created_at rcreated
                     FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                     WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq ORDER BY s.sla_due");
    // M2 — visibility now matches actionability. appr_visible() calls
    // appr_can_act() first, so this can only ever narrow the queue, never widen
    // it, and it is entity-aware so Offer / Salary / Requisition queues are
    // untouched.
    return array_values(array_filter($rows, function ($s) use ($user) {
        $req = ['entity' => $s['entity'], 'entity_id' => $s['entity_id'], 'status' => 'PENDING'];
        return appr_visible(appr_step_context($s, $req), $req, $user);
    }));
}
function appr_inbox_count($user = null) { return count(appr_inbox($user)); }

// ---- Reminders + escalations (cron tick) -----------------------------------
function appr_tick() {
    appr_migrate();
    $now = time(); $acted = 0;
    $steps = ops_all("SELECT s.*, r.subject, r.entity, r.rule_name FROM recruit_approval_steps s
                      JOIN recruit_approval_requests r ON r.id=s.request_id
                      WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq");
    foreach ($steps as $s) {
        $sla = $s['sla_due'] ? strtotime($s['sla_due']) : 0;
        if ($sla && $now >= $sla && (int)$s['escalated'] === 0) {
            appr_email_escalate($s); db()->prepare("UPDATE recruit_approval_steps SET escalated=1 WHERE id=?")->execute([(int)$s['id']]); $acted++;
            continue;
        }
        $rem = $s['reminder_at'] ? strtotime($s['reminder_at']) : 0;
        if ($rem && $now >= $rem) {
            appr_email_approver($s, ['id'=>$s['request_id'],'subject'=>$s['subject'],'entity'=>$s['entity']], 'reminder');
            db()->prepare("UPDATE recruit_approval_steps SET reminded_at=?, reminder_at=? WHERE id=?")->execute([_appr_now(), date('c', $now + 86400), (int)$s['id']]);
            $acted++;
        }
    }
    return $acted;
}

// ---- Email helpers ---------------------------------------------------------
function appr_role_emails($role, $userId = null) {
    if ((int)$userId > 0) { $e = ops_val("SELECT email FROM users WHERE id=? AND is_active=1 AND email<>''", [(int)$userId]); return $e ? [$e] : []; }
    if ((string)$role === '') return [];
    return array_values(array_filter(array_map(fn($r) => $r['email'], ops_all("SELECT email FROM users WHERE role=? AND is_active=1 AND email<>''", [$role]))));
}
function appr_mail($to, $subject, $body) {
    if (!$to || !function_exists('ops_mail')) return;
    foreach ((array)$to as $addr) if ($addr) { try { ops_mail($addr, $subject, $body, '', 'recruit_approval'); } catch (Throwable $e) {} }
}
function appr_email_approver($step, $req, $kind) {
    $to = appr_role_emails($step['approver_role'] ?? '', $step['approver_user_id'] ?? null);
    $sub = ($kind === 'reminder' ? 'Reminder: ' : '') . 'Approval needed — ' . ($req['subject'] ?? $req['entity'] ?? 'item');
    $body = '<p>An approval is awaiting your action' . ($kind === 'reminder' ? ' (reminder)' : '') . ':</p>'
        . '<p><b>' . e((string)($req['subject'] ?? '')) . '</b>' . (isset($step['label']) && $step['label'] ? ' — ' . e($step['label']) : '') . '</p>'
        . '<p>Open <b>My approvals</b> in the app to approve or reject.</p>';
    appr_mail($to, $sub, $body);
}
function appr_email_escalate($step) {
    $to = appr_role_emails($step['escalate_role'] ?? '', $step['escalate_user_id'] ?? null);
    if (!$to) $to = array_values(array_filter(array_map(fn($r) => $r['email'], ops_all("SELECT email FROM users WHERE role IN ('MASTER_ADMIN','ADMIN','BRANCH_MANAGER','SBU_HEAD') AND is_active=1 AND email<>''"))));
    appr_mail($to, 'ESCALATION: approval overdue — ' . ($step['subject'] ?? ''),
        '<p>An approval step has passed its SLA and needs attention:</p><p><b>' . e((string)($step['subject'] ?? '')) . '</b> — level ' . (int)$step['seq'] . '</p>');
}
function appr_email_requester($req, $result, $remarks = '') {
    $who = trim((string)($req['requester'] ?? '')); if ($who === '') return;
    $email = '';
    try {
        $email = (string)ops_val("SELECT email FROM users WHERE email<>'' AND (username=? OR TRIM((first_name || ' ' || last_name))=?) LIMIT 1", [$who, $who]);
    } catch (Throwable $e) { return; }
    if (!$email) return;
    appr_mail([$email], 'Your approval was ' . strtoupper($result) . ' — ' . ($req['subject'] ?? ''),
        '<p>Your request <b>' . e((string)($req['subject'] ?? '')) . '</b> was <b>' . e(strtoupper($result)) . '</b>.' . ($remarks ? ' Remark: ' . e($remarks) : '') . '</p>');
}

// ============================================================================
//  Admin screen — configure rules + levels
// ============================================================================
function ops_recruit_approvals($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure approval rules.');
    appr_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'rule_save') { $id = appr_rule_save((int)($_POST['id'] ?? 0), $_POST); flash('Rule saved.'); redirect('/recruit-approvals?id=' . $id); return true; }
        if ($do === 'rule_toggle') { $r = appr_rule((int)($_POST['id'] ?? 0)); if ($r) appr_rule_set_active($r['id'], (int)$r['active'] === 0); flash('Rule updated.'); redirect('/recruit-approvals'); return true; }
        if ($do === 'level_save') { appr_level_save($_POST); flash('Level saved.'); redirect('/recruit-approvals?id=' . (int)($_POST['rule_id'] ?? 0)); return true; }
        if ($do === 'level_delete') { appr_level_delete((int)($_POST['level_id'] ?? 0)); flash('Level removed.'); redirect('/recruit-approvals?id=' . (int)($_POST['rule_id'] ?? 0)); return true; }
    }
    $selId = (int)($_GET['id'] ?? 0);
    $sel = $selId ? appr_rule($selId) : null;
    // M2 — what the administrator needs to see to trust the configuration: which
    // levels nobody can action, and whether this rule ties with another.
    $orphans = $sel ? appr_orphan_levels($sel['id']) : [];
    $ties = [];
    if ($sel) {
        foreach (appr_rules($sel['entity'], true) as $other) {
            if ((int) $other['id'] === (int) $sel['id']) continue;
            if ((int) $other['sort'] === (int) $sel['sort']
                && (string) $other['applies_department'] === (string) $sel['applies_department']
                && (string) $other['applies_sbu'] === (string) $sel['applies_sbu']
                && (string) $other['applies_grade'] === (string) $sel['applies_grade']
                && (string) $other['applies_position'] === (string) $sel['applies_position']
                && (int) ($other['applies_office_id'] ?? 0) === (int) ($sel['applies_office_id'] ?? 0)) $ties[] = $other;
        }
    }
    view('ops/approval_rules', [
        'rules'  => appr_rules(null, false),
        'sel'    => $sel,
        'levels' => $sel ? appr_levels($sel['id']) : [],
        'roles'  => function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES,
        'entities' => APPR_ENTITIES,
        'orphans'  => $orphans,
        'ties'     => $ties,
        'offices'  => function_exists('offices_list') ? offices_list() : [],
        'preview'  => (isset($_GET['pv']) && $sel) ? appr_preview($sel['entity'], [
            'department'  => (string) ($_GET['pv_department'] ?? ''),
            'sbu'         => (string) ($_GET['pv_sbu'] ?? ''),
            'grade'       => (string) ($_GET['pv_grade'] ?? ''),
            'position'    => (string) ($_GET['pv_position'] ?? ''),
            'office_id'   => (int) ($_GET['pv_office_id'] ?? 0),
            'amount'      => (float) ($_GET['pv_amount'] ?? 0),
        ]) : null,
    ]);
    return true;
}

//  Delegation administration. Same gate as the rest of approval configuration —
//  moving approval authority is at least as privileged as writing the rule that
//  demands it.
function ops_approval_delegations($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure approval delegation.');
    appr_migrate();
    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'save') {
            [$ok, $msg] = appr_delegation_save((int) ($_POST['id'] ?? 0), $_POST);
            flash($msg, $ok ? 'success' : 'error');
        } elseif ($do === 'revoke') {
            [$ok, $msg] = appr_delegation_revoke((int) ($_POST['id'] ?? 0));
            flash($msg, $ok ? 'success' : 'error');
        }
        redirect('/approval-delegations'); return true;
    }
    view('ops/approval_delegations', [
        'rows'     => appr_delegations(false),
        'people'   => function_exists('rcc_users') ? rcc_users() : ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name"),
        'offices'  => function_exists('offices_list') ? offices_list() : [],
        'entities' => APPR_ENTITIES,
    ]);
    return true;
}

// My approvals inbox + act.
function ops_my_approvals($route, $method) {
    appr_migrate();
    ops_require(function_exists('current_user') && current_user(), 'Sign in.');
    // M1 FINDING A. This route is in neither ops_module_gate()'s route map nor
    // ops_module_family()'s prefix table, so NO module question was ever asked —
    // the recruitment approval inbox opened on a workspace that had not bought
    // recruitment (proved with a probe).
    //
    // What is asked here is the LICENCE, not mod.hiring.view. Entitlement is the
    // tenant's contract; capability is the person's role. Requiring the hiring
    // permission would lock out a configured approver who legitimately holds no
    // recruitment module — a Finance approver on an offer chain, say — and
    // narrowing the approver population is a policy change M1 was not asked for.
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) {
        if (function_exists('access_deny')) access_deny('hiring');
        ops_require(false, 'The recruitment module is not switched on for this installation.');
    }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'act') { [$ok, $m] = appr_act((int)($_POST['step_id'] ?? 0), (string)($_POST['decision'] ?? 'approve'), $_POST['remarks'] ?? ''); flash($m, $ok ? 'success' : 'error'); }
        redirect('/my-approvals'); return true;
    }
    view('ops/my_approvals', ['inbox' => appr_inbox()]);
    return true;
}
