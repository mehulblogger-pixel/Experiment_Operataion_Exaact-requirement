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

const APPR_ENTITIES = ['REQUISITION' => 'Requisition (SRF)', 'OFFER' => 'Offer', 'SALARY' => 'Salary structure'];

function appr_migrate() {
    static $done = false; if ($done) return; $done = true;
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

function appr_rule_save($id, $post) {
    appr_migrate();
    $entity = array_key_exists($post['entity'] ?? '', APPR_ENTITIES) ? $post['entity'] : 'OFFER';
    $cols = ['code','name','applies_department','applies_sbu','applies_grade','applies_position'];
    $v = []; foreach ($cols as $c) $v[$c] = trim((string)($post[$c] ?? ''));
    $min = (float)($post['min_amount'] ?? 0); $max = (float)($post['max_amount'] ?? 0);
    if ((int)$id > 0) {
        db()->prepare("UPDATE recruit_approval_rules SET code=?,name=?,entity=?,applies_department=?,applies_sbu=?,applies_grade=?,applies_position=?,min_amount=?,max_amount=?,sort=? WHERE id=?")
            ->execute([$v['code'],$v['name'],$entity,$v['applies_department'],$v['applies_sbu'],$v['applies_grade'],$v['applies_position'],$min,$max,(int)($post['sort']??0),(int)$id]);
        return (int)$id;
    }
    db()->prepare("INSERT INTO recruit_approval_rules (code,name,entity,applies_department,applies_sbu,applies_grade,applies_position,min_amount,max_amount,active,sort,created_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)")
        ->execute([$v['code'],$v['name']?:'New rule',$entity,$v['applies_department'],$v['applies_sbu'],$v['applies_grade'],$v['applies_position'],$min,$max,(int)($post['sort']??0),_appr_now()]);
    return (int)db()->lastInsertId();
}
function appr_rule_set_active($id, $on) { appr_migrate(); db()->prepare("UPDATE recruit_approval_rules SET active=? WHERE id=?")->execute([$on?1:0,(int)$id]); }
function appr_level_save($post) {
    appr_migrate();
    $rid = (int)($post['rule_id'] ?? 0); if (!$rid) return;
    $role = in_array($post['approver_role'] ?? '', array_keys(ORG_ROLES), true) ? $post['approver_role'] : '';
    $esc  = in_array($post['escalate_role'] ?? '', array_keys(ORG_ROLES), true) ? $post['escalate_role'] : '';
    $data = [(int)($post['seq']??0), trim((string)($post['label']??'')), $role, (int)($post['approver_user_id']??0)?:null,
             max(0,(int)($post['sla_days']??2)), max(0,(int)($post['reminder_days']??1)), $esc, (int)($post['escalate_user_id']??0)?:null];
    $lid = (int)($post['level_id'] ?? 0);
    if ($lid > 0) { $data[] = $lid;
        db()->prepare("UPDATE recruit_approval_levels SET seq=?,label=?,approver_role=?,approver_user_id=?,sla_days=?,reminder_days=?,escalate_role=?,escalate_user_id=? WHERE id=?")->execute($data);
    } else { array_unshift($data, $rid);
        db()->prepare("INSERT INTO recruit_approval_levels (rule_id,seq,label,approver_role,approver_user_id,sla_days,reminder_days,escalate_role,escalate_user_id) VALUES (?,?,?,?,?,?,?,?,?)")->execute($data);
    }
}
function appr_level_delete($id) { appr_migrate(); db()->prepare("DELETE FROM recruit_approval_levels WHERE id=?")->execute([(int)$id]); }

// ---- Matching (narrowest wins) ---------------------------------------------
function appr_match($entity, $ctx) {
    $best = null; $bestScore = -1;
    $map = ['applies_department'=>'department','applies_sbu'=>'sbu','applies_grade'=>'grade','applies_position'=>'position'];
    foreach (appr_rules($entity, true) as $r) {
        $ok = true; $score = 0;
        foreach ($map as $col => $k) {
            $f = trim((string)($r[$col] ?? '')); if ($f === '') continue;
            $vals = array_map('strtolower', array_map('trim', explode(',', $f)));
            if (in_array(strtolower(trim((string)($ctx[$k] ?? ''))), $vals, true)) $score++;
            else { $ok = false; break; }
        }
        if (!$ok) continue;
        $amt = (float)($ctx['amount'] ?? 0);
        if ((float)$r['min_amount'] > 0 && $amt < (float)$r['min_amount']) continue;
        if ((float)$r['max_amount'] > 0 && $amt > (float)$r['max_amount']) continue;
        if ((float)$r['min_amount'] > 0 || (float)$r['max_amount'] > 0) $score++;   // a band that matched adds specificity
        if ($score > $bestScore) { $best = $r; $bestScore = $score; }
    }
    return $best;
}

// ---- Requests & steps ------------------------------------------------------
function appr_open($entity, $entityId) {
    appr_migrate();
    return ops_one("SELECT * FROM recruit_approval_requests WHERE entity=? AND entity_id=? AND status='PENDING' ORDER BY id DESC LIMIT 1", [$entity, (int)$entityId]) ?: null;
}
function appr_request($id) { appr_migrate(); return ops_one("SELECT * FROM recruit_approval_requests WHERE id=?", [(int)$id]) ?: null; }
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
    foreach ($levels as $lv) {
        db()->prepare("INSERT INTO recruit_approval_steps (request_id,seq,label,approver_role,approver_user_id,escalate_role,escalate_user_id,sla_due,reminder_at,status) VALUES (?,?,?,?,?,?,?,?,?, 'PENDING')")
            ->execute([$reqId,(int)$lv['seq'],(string)$lv['label'],(string)$lv['approver_role'],$lv['approver_user_id']?:null,(string)$lv['escalate_role'],$lv['escalate_user_id']?:null,_appr_days($lv['sla_days']),_appr_days($lv['reminder_days'])]);
    }
    // Notify the first-level approvers.
    $first = appr_current_step(appr_request($reqId));
    if ($first) appr_email_approver($first, appr_request($reqId), 'requested');
    return [true, $reqId];
}

function appr_can_act($step, $user = null) {
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return false;
    if (function_exists('is_master') && is_master()) return true;
    if ((int)($step['approver_user_id'] ?? 0) > 0) return (int)$step['approver_user_id'] === (int)$user['id'];
    return (string)($step['approver_role'] ?? '') !== '' && (string)$user['role'] === (string)$step['approver_role'];
}

// Approve or reject the given step. Returns [ok, message].
function appr_act($stepId, $decision, $remarks = '') {
    appr_migrate();
    $step = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$stepId]);
    if (!$step || $step['status'] !== 'PENDING') return [false, 'This step is not pending.'];
    $req = appr_request((int)$step['request_id']);
    if (!$req || $req['status'] !== 'PENDING') return [false, 'This request is closed.'];
    if ((int)$step['seq'] !== (int)$req['current_seq']) return [false, 'An earlier level is still pending.'];
    if (!appr_can_act($step)) return [false, 'You are not the approver for this step.'];

    $now = _appr_now(); $actor = _appr_actor();
    if ($decision === 'reject') {
        db()->prepare("UPDATE recruit_approval_steps SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([$actor,$now,substr((string)$remarks,0,400),(int)$stepId]);
        db()->prepare("UPDATE recruit_approval_requests SET status='REJECTED', closed_at=? WHERE id=?")->execute([$now,(int)$req['id']]);
        appr_callback($req['entity'], (int)$req['entity_id'], 'REJECTED', $req);
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
    appr_callback($req['entity'], (int)$req['entity_id'], 'APPROVED', $req);
    appr_email_requester($req, 'approved', $remarks);
    return [true, 'Approved — fully cleared.'];
}

// Update the underlying entity when a chain completes.
function appr_callback($entity, $entityId, $result, $req = null) {
    try {
        if ($entity === 'OFFER') {
            if ($result === 'APPROVED') db()->prepare("UPDATE job_offers SET status='APPROVED', approved_by=?, approved_at=? WHERE id=? AND status IN ('PENDING_APPROVAL','DRAFT')")->execute(['Approval chain', _appr_now(), (int)$entityId]);
            else db()->prepare("UPDATE job_offers SET status='DRAFT' WHERE id=? AND status='PENDING_APPROVAL'")->execute([(int)$entityId]);
        } elseif ($entity === 'REQUISITION') {
            if ($result === 'APPROVED') db()->prepare("UPDATE requisitions SET status='approved', approved_by=? WHERE id=?")->execute(['Approval chain', (int)$entityId]);
            else db()->prepare("UPDATE requisitions SET status='on_hold' WHERE id=?")->execute([(int)$entityId]);
        }
    } catch (Throwable $e) { /* callback is best-effort */ }
}

// ---- Inbox (My approvals) --------------------------------------------------
function appr_inbox($user = null) {
    appr_migrate();
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return [];
    $rows = ops_all("SELECT s.*, r.entity, r.entity_id, r.subject, r.amount, r.rule_name, r.requester, r.created_at rcreated
                     FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                     WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq ORDER BY s.sla_due");
    return array_values(array_filter($rows, fn($s) => appr_can_act($s, $user)));
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
    view('ops/approval_rules', [
        'rules'  => appr_rules(null, false),
        'sel'    => $sel,
        'levels' => $sel ? appr_levels($sel['id']) : [],
        'roles'  => ORG_ROLES,
        'entities' => APPR_ENTITIES,
    ]);
    return true;
}

// My approvals inbox + act.
function ops_my_approvals($route, $method) {
    appr_migrate();
    ops_require(function_exists('current_user') && current_user(), 'Sign in.');
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'act') { [$ok, $m] = appr_act((int)($_POST['step_id'] ?? 0), (string)($_POST['decision'] ?? 'approve'), $_POST['remarks'] ?? ''); flash($m, $ok ? 'success' : 'error'); }
        redirect('/my-approvals'); return true;
    }
    view('ops/my_approvals', ['inbox' => appr_inbox()]);
    return true;
}
