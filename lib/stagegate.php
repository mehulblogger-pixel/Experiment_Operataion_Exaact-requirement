<?php
// ============================================================================
//  Stage gates — an approval standing between a deal and the stage it is
//  being moved into
//
//  THE HOLE THIS FILLS. A quotation for ₹40 lakh cannot be approved by whoever
//  typed it; it walks an approval chain built from value bands (quote_approval_rules).
//  A deal for the same ₹40 lakh could be dragged to Won by anybody who could
//  see it. The forecast every manager reads is built from those stage moves, so
//  the least controlled act in the system was the one feeding the most-read
//  number. Discounts get approved and forecasts do not, which is backwards.
//
//  WHAT A GATE IS. A rule that says: moving a deal of this size, in this
//  business unit, into this stage, needs this person's yes first. Until they
//  say yes the deal does not move. It is not a warning and not a notification —
//  a gate that can be walked through is decoration.
//
//  WHAT IT IS NOT. It is not a second workflow engine. There is exactly one
//  pending request per deal, it stores the move it is holding, and approving it
//  replays that same move through the same function a person would have used.
//  Nothing about a gated move differs from an ungated one except who pressed
//  the button and when.
//
//  DEFAULT: NO RULES, NO GATES. An install with an empty rule table behaves
//  precisely as it did before this file existed. A gate is a thing a business
//  chooses, and a CRM that ships with mandatory approvals is a CRM people learn
//  to route around.
//
//  WHY THE REQUEST STORES A PAYLOAD. Moving to a Lost stage carries a reason,
//  and the reason is written when the request is raised, not when it is
//  approved — otherwise the approver would be inventing why somebody else's
//  deal was lost. The payload is replayed verbatim on approval.
// ============================================================================

const GATE_KINDS  = ['OPPORTUNITY' => 'Opportunity', 'LEAD' => 'Lead'];
const GATE_STATUS = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected',
                     'CANCELLED' => 'Withdrawn'];

function gate_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function gate_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // stage_id 0 means "any stage of this kind", so one rule can cover Won on
    // every pipeline a company runs — including the ones it has not built yet.
    $pdo->exec("CREATE TABLE IF NOT EXISTS stage_gates (
        id $pk, name VARCHAR(140) DEFAULT '', entity_kind VARCHAR(20) DEFAULT 'OPPORTUNITY',
        pipeline_id INT DEFAULT 0, stage_id INT DEFAULT 0, stage_kind VARCHAR(10) DEFAULT 'WON',
        sbu VARCHAR(40) DEFAULT '', min_amount DECIMAL(14,2) DEFAULT 0, max_amount DECIMAL(14,2) DEFAULT 0,
        approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
        active INT DEFAULT 1, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stage_gate_requests (
        id $pk, entity_kind VARCHAR(20) DEFAULT 'OPPORTUNITY', entity_id INT,
        gate_id INT NULL, from_stage_id INT NULL, to_stage_id INT NULL,
        amount DECIMAL(14,2) DEFAULT 0, sbu VARCHAR(40) DEFAULT '',
        payload TEXT, note VARCHAR(500) DEFAULT '',
        approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
        status VARCHAR(20) DEFAULT 'PENDING',
        requested_by VARCHAR(150) DEFAULT '', requested_by_id INT NULL, requested_at VARCHAR(30) DEFAULT '',
        acted_by VARCHAR(150) DEFAULT '', acted_at VARCHAR(30) DEFAULT '', remarks VARCHAR(500) DEFAULT '')");
    act_index('stage_gate_requests', 'sgr_open', '(entity_kind, entity_id, status)');
    act_index('stage_gate_requests', 'sgr_status', '(status, requested_at)');
    act_index('stage_gates', 'sg_live', '(active, entity_kind)');
}

function gate_can_manage() { return can('settings.manage') || can('crm.quote.approve') || is_master_of('leads'); }
function gate_can_view()   { return can('mod.leads.view') || is_master_of('leads'); }

// ---- Matching ---------------------------------------------------------------
// The narrowest rule wins, and "narrowest" is defined rather than felt: a rule
// naming one stage beats one naming a kind, a rule naming a business unit beats
// one that takes any, and a rule with an upper limit beats an open-ended one.
// Two rules that are equally narrow are ordered by id, so the answer is stable.
function gate_specificity($r) {
    $s = 0;
    if ((int)($r['stage_id'] ?? 0) > 0)        $s += 4;
    if (trim((string)($r['sbu'] ?? '')) !== '') $s += 2;
    if ((float)($r['max_amount'] ?? 0) > 0)     $s += 1;
    return $s;
}

function gate_match($entityKind, $toStage, $amount, $sbu, $pipelineId = 0) {
    gate_migrate();
    if (!$toStage) return null;
    $rules = gate_try(fn() => ops_all(
        "SELECT * FROM stage_gates WHERE active = 1 AND entity_kind = ? ORDER BY id", [$entityKind]), []) ?: [];
    $hit = null;
    foreach ($rules as $r) {
        if ((int)$r['pipeline_id'] > 0 && (int)$r['pipeline_id'] !== (int)$pipelineId) continue;
        if ((int)$r['stage_id'] > 0) {
            if ((int)$r['stage_id'] !== (int)$toStage['id']) continue;
        } elseif (trim((string)$r['stage_kind']) !== '') {
            if ($r['stage_kind'] !== (string)$toStage['kind']) continue;
        }
        if (trim((string)$r['sbu']) !== '' && strcasecmp((string)$r['sbu'], (string)$sbu) !== 0) continue;
        if ((float)$amount < (float)$r['min_amount']) continue;
        if ((float)$r['max_amount'] > 0 && (float)$amount > (float)$r['max_amount']) continue;
        if ($hit === null || gate_specificity($r) > gate_specificity($hit)) $hit = $r;
    }
    return $hit;
}

// ---- Requests ---------------------------------------------------------------
function gate_open_request($entityKind, $entityId) {
    gate_migrate();
    return gate_try(fn() => ops_one(
        "SELECT * FROM stage_gate_requests WHERE entity_kind = ? AND entity_id = ? AND status = 'PENDING'
         ORDER BY id DESC LIMIT 1", [$entityKind, (int)$entityId]), null) ?: null;
}

function gate_request($entityKind, $entityId, $gate, $fromStageId, $toStage, $amount, $sbu, array $payload, $note = '') {
    gate_migrate();
    if (gate_open_request($entityKind, $entityId))
        return ['err' => 'This one is already waiting for approval. Withdraw that request before raising another.'];
    $u = current_user();
    db()->prepare("INSERT INTO stage_gate_requests
        (entity_kind, entity_id, gate_id, from_stage_id, to_stage_id, amount, sbu, payload, note,
         approver_role, approver_user_id, status, requested_by, requested_by_id, requested_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'PENDING',?,?,?)")
      ->execute([$entityKind, (int)$entityId, (int)$gate['id'], (int)$fromStageId, (int)$toStage['id'],
                 (float)$amount, (string)$sbu, json_encode($payload), substr(trim((string)$note), 0, 500),
                 (string)($gate['approver_role'] ?? ''), ($gate['approver_user_id'] ?? null) ?: null,
                 $u ? user_name($u) : '', $u ? (int)$u['id'] : null, date('c')]);
    if (function_exists('act_log'))
        act_log($entityKind, (int)$entityId, 'SYSTEM',
                'Sent for approval: move to ' . $toStage['name'] . ' (' . $gate['name'] . ')', ['auto' => 1]);
    return ['pending' => true, 'gate' => $gate, 'stage' => $toStage];
}

// Who may act on a request. A named user is the answer on their own; a role
// resolves to everybody holding it; and a rule that names neither is open to
// anybody who may approve quotations — the same people, so an approval matrix
// does not need to be built twice.
function gate_approvers($req) {
    if (!empty($req['approver_user_id'])) {
        $u = gate_try(fn() => ops_one("SELECT id, username, first_name, last_name, email, role
                                       FROM users WHERE id = ? AND is_active = 1", [(int)$req['approver_user_id']]), null);
        return $u ? [$u] : [];
    }
    if (trim((string)($req['approver_role'] ?? '')) !== '')
        return gate_try(fn() => ops_all("SELECT id, username, first_name, last_name, email, role
                                         FROM users WHERE role = ? AND is_active = 1
                                         ORDER BY first_name, username", [$req['approver_role']]), []) ?: [];
    $roles = [];
    foreach (ORG_ROLES as $rk => $rl) {
        $p = role_perms($rk);
        if ($p && in_array('crm.quote.approve', $p, true)) $roles[] = $rk;
    }
    if (!$roles) return [];
    $ph = implode(',', array_fill(0, count($roles), '?'));
    return gate_try(fn() => ops_all("SELECT id, username, first_name, last_name, email, role
                                     FROM users WHERE role IN ($ph) AND is_active = 1
                                     ORDER BY first_name, username", $roles), []) ?: [];
}

function gate_waiting_on($req) {
    $names = [];
    foreach (gate_approvers($req) as $u) $names[] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
    if (!$names) return 'nobody — no active user holds the role this rule names';
    return count($names) <= 3 ? implode(', ', $names) : $names[0] . ' and ' . (count($names) - 1) . ' others';
}

function gate_can_act($req) {
    if (!$req || $req['status'] !== 'PENDING') return false;
    $u = current_user(); if (!$u) return false;
    // The person who asked cannot be the person who agrees. This is the whole
    // point of the gate, and it holds even for an administrator — an approval
    // an admin can grant themselves is a field, not a control.
    if (!empty($req['requested_by_id']) && (int)$req['requested_by_id'] === (int)$u['id']) return false;
    foreach (gate_approvers($req) as $a) if ((int)$a['id'] === (int)$u['id']) return true;
    return false;
}

// Approving replays the move through the ordinary function, with the gate
// suppressed. There is deliberately no second code path: a gated move and a
// plain one end up in the same place by the same route.
function gate_apply($req) {
    $payload = gate_try(fn() => json_decode((string)$req['payload'], true), []) ?: [];
    // Credit the win to whoever raised the request, not to whoever agreed to it.
    $payload['_actor'] = (string)$req['requested_by'];
    if ($req['entity_kind'] === 'OPPORTUNITY' && function_exists('opp_move'))
        return opp_move((int)$req['entity_id'], (int)$req['to_stage_id'], $payload, true);
    return ['err' => 'This request is for something this install no longer has.'];
}

function gate_act($reqId, $decision, $remarks = '') {
    gate_migrate();
    $req = gate_try(fn() => ops_one("SELECT * FROM stage_gate_requests WHERE id = ?", [(int)$reqId]), null);
    if (!$req) return ['err' => 'That request no longer exists.'];
    if ($req['status'] !== 'PENDING') return ['err' => 'That request was already ' . strtolower(GATE_STATUS[$req['status']] ?? $req['status']) . '.'];
    $u = current_user();
    $mine = $u && !empty($req['requested_by_id']) && (int)$req['requested_by_id'] === (int)$u['id'];

    if ($decision === 'CANCELLED') {
        // Withdrawing is the requester's own right, and an approver may also
        // clear a request that has gone stale.
        if (!$mine && !gate_can_act($req) && !is_master()) return ['err' => 'You cannot withdraw that request.'];
    } else {
        if (!gate_can_act($req))
            return ['err' => $mine
                ? 'You raised this request, so you cannot approve it yourself. That is what the rule is for.'
                : 'This is not waiting on you.'];
        if ($decision === 'REJECTED' && trim((string)$remarks) === '')
            return ['err' => 'Say why it is being sent back — the person who raised it has to know what to change.'];
    }

    $applied = null;
    if ($decision === 'APPROVED') {
        $applied = gate_apply($req);
        if (!empty($applied['err'])) return ['err' => $applied['err']];
    }
    db()->prepare("UPDATE stage_gate_requests SET status = ?, acted_by = ?, acted_at = ?, remarks = ? WHERE id = ?")
       ->execute([$decision, $u ? user_name($u) : '', date('c'), substr(trim((string)$remarks), 0, 500), (int)$req['id']]);
    if (function_exists('act_log'))
        act_log((string)$req['entity_kind'], (int)$req['entity_id'], 'SYSTEM',
                'Approval ' . strtolower(GATE_STATUS[$decision] ?? $decision)
                . ($remarks !== '' ? ' — ' . substr(trim((string)$remarks), 0, 200) : ''), ['auto' => 1]);
    return ['ok' => true, 'decision' => $decision, 'applied' => $applied];
}

// ---- The queue --------------------------------------------------------------
// Everything waiting on the person looking, plus what they themselves have
// raised — a screen that shows only other people's requests leaves the person
// who raised one with nowhere to see it.
function gate_queue($status = 'PENDING') {
    gate_migrate();
    $rows = gate_try(fn() => ops_all(
        "SELECT r.*, o.name AS deal_name, o.value AS deal_value, o.partner_name,
                fs.name AS from_name, ts.name AS to_name, ts.kind AS to_kind
         FROM stage_gate_requests r
         LEFT JOIN opportunities o ON o.id = r.entity_id AND r.entity_kind = 'OPPORTUNITY'
         LEFT JOIN pipeline_stages fs ON fs.id = r.from_stage_id
         LEFT JOIN pipeline_stages ts ON ts.id = r.to_stage_id
         WHERE r.status = ? ORDER BY r.requested_at DESC LIMIT 300", [$status]), []) ?: [];
    $u = current_user();
    $mine = []; $others = [];
    foreach ($rows as $r) {
        $r['can_act']    = gate_can_act($r);
        $r['waiting_on'] = gate_waiting_on($r);
        $r['is_mine']    = $u && !empty($r['requested_by_id']) && (int)$r['requested_by_id'] === (int)$u['id'];
        if ($r['can_act']) $mine[] = $r; else $others[] = $r;
    }
    return ['act' => $mine, 'watch' => $others];
}

function gate_pending_count() {
    $q = gate_queue('PENDING');
    return count($q['act']);
}

// ---- Rules admin ------------------------------------------------------------
function gate_rules() {
    gate_migrate();
    $rows = gate_try(fn() => ops_all(
        "SELECT g.*, u.username, u.first_name, u.last_name, p.name AS pipeline_name, s.name AS stage_name
         FROM stage_gates g
         LEFT JOIN users u ON u.id = g.approver_user_id
         LEFT JOIN pipelines p ON p.id = g.pipeline_id
         LEFT JOIN pipeline_stages s ON s.id = g.stage_id
         ORDER BY g.entity_kind, g.min_amount, g.id"), []) ?: [];
    foreach ($rows as &$r) $r['used'] = (int)(gate_try(fn() => ops_val(
        "SELECT COUNT(*) FROM stage_gate_requests WHERE gate_id = ?", [(int)$r['id']]), 0) ?: 0);
    return $rows;
}

function gate_rule_save(array $b, $id = 0) {
    gate_migrate();
    $name = trim((string)($b['name'] ?? ''));
    if ($name === '') return 'Give the rule a name — it is what appears on the deal when the gate stops it.';
    $min = max(0, (float)($b['min_amount'] ?? 0));
    $max = max(0, (float)($b['max_amount'] ?? 0));
    if ($max > 0 && $max < $min) return 'The upper limit is below the lower one, so nothing could ever match this rule.';
    $kind = isset(GATE_KINDS[$b['entity_kind'] ?? '']) ? $b['entity_kind'] : 'OPPORTUNITY';
    $stageId = (int)($b['stage_id'] ?? 0);
    $stageKind = in_array((string)($b['stage_kind'] ?? ''), ['', 'OPEN', 'WON', 'LOST'], true) ? (string)$b['stage_kind'] : 'WON';
    if ($stageId <= 0 && $stageKind === '')
        return 'Say which stage this gate guards — either one named stage, or every stage of one kind.';
    $args = [$name, $kind, (int)($b['pipeline_id'] ?? 0), $stageId, $stageKind,
             trim((string)($b['sbu'] ?? '')), $min, $max,
             trim((string)($b['approver_role'] ?? '')), ((int)($b['approver_user_id'] ?? 0)) ?: null,
             empty($b['active']) ? 0 : 1];
    if ($id > 0) {
        $args[] = (int)$id;
        db()->prepare("UPDATE stage_gates SET name=?, entity_kind=?, pipeline_id=?, stage_id=?, stage_kind=?,
                       sbu=?, min_amount=?, max_amount=?, approver_role=?, approver_user_id=?, active=? WHERE id=?")
           ->execute($args);
    } else {
        $u = current_user();
        $args[] = $u ? user_name($u) : ''; $args[] = date('c');
        db()->prepare("INSERT INTO stage_gates (name, entity_kind, pipeline_id, stage_id, stage_kind,
                       sbu, min_amount, max_amount, approver_role, approver_user_id, active, created_by, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($args);
    }
    return '';
}

// A rule that has held real requests is switched off rather than deleted, so
// the history of what was approved under it keeps its explanation.
function gate_rule_delete($id) {
    gate_migrate();
    $used = (int)(gate_try(fn() => ops_val("SELECT COUNT(*) FROM stage_gate_requests WHERE gate_id = ?", [(int)$id]), 0) ?: 0);
    if ($used > 0) {
        db()->prepare("UPDATE stage_gates SET active = 0 WHERE id = ?")->execute([(int)$id]);
        return 'That rule has held ' . $used . ' ' . ($used === 1 ? 'move' : 'moves') . ', so it was switched off rather than deleted — the history keeps its reason.';
    }
    db()->prepare("DELETE FROM stage_gates WHERE id = ?")->execute([(int)$id]);
    return 'Rule removed.';
}

// What the rules would do, said in words, so somebody can check the matrix
// without raising a test deal against it.
function gate_explain($r) {
    $band = (float)$r['max_amount'] > 0
        ? 'between ' . fmoney((float)$r['min_amount']) . ' and ' . fmoney((float)$r['max_amount'])
        : ((float)$r['min_amount'] > 0 ? 'of ' . fmoney((float)$r['min_amount']) . ' and above' : 'of any value');
    $where = (int)$r['stage_id'] > 0
        ? 'into ' . ($r['stage_name'] ?: 'one named stage')
        : 'into any ' . strtolower((string)$r['stage_kind']) . ' stage';
    $who = !empty($r['approver_user_id'])
        ? (trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: (string)$r['username'])
        : (trim((string)$r['approver_role']) !== '' ? 'anyone with the role ' . $r['approver_role']
                                                    : 'anyone who may approve quotations');
    $plural = ['OPPORTUNITY' => 'Opportunities', 'LEAD' => 'Leads'][$r['entity_kind']] ?? (GATE_KINDS[$r['entity_kind']] ?? $r['entity_kind']);
    return $plural . ' ' . $band
         . (trim((string)$r['sbu']) !== '' ? ' in ' . $r['sbu'] : '')
         . ((int)$r['pipeline_id'] > 0 ? ' on the ' . ($r['pipeline_name'] ?: 'chosen') . ' pipeline' : '')
         . ' moving ' . $where . ' need ' . $who . ' to agree first.';
}

// ---- Routes -----------------------------------------------------------------
function ops_stage_gates($route, $method) {
    ops_require(gate_can_manage(), 'You cannot manage approval rules.');
    gate_migrate();

    if ($route === 'stage-gate-delete' && $method === 'POST') {
        flash(gate_rule_delete((int)($_POST['id'] ?? 0)));
        redirect('/stage-gates');
    }
    if ($route === 'stage-gate-save' && $method === 'POST') {
        $err = gate_rule_save($_POST, (int)($_POST['id'] ?? 0));
        if ($err) { flash($err, 'error'); redirect('/stage-gates?edit=' . (int)($_POST['id'] ?? 0)); }
        flash('Approval rule saved.', 'success');
        redirect('/stage-gates');
    }

    $editId = (int)($_GET['edit'] ?? 0);
    $edit = $editId > 0 ? gate_try(fn() => ops_one("SELECT * FROM stage_gates WHERE id = ?", [$editId]), null) : null;
    view('ops/stage_gates', [
        'rules'     => gate_rules(),
        'edit'      => $edit,
        'new'       => isset($_GET['new']),
        'pipelines' => function_exists('pipe_all') ? gate_try(fn() => pipe_all(), []) ?: [] : [],
        'stages'    => gate_try(fn() => ops_all(
                         "SELECT s.id, s.name, s.kind, p.name AS pipeline_name
                          FROM pipeline_stages s JOIN pipelines p ON p.id = s.pipeline_id
                          WHERE p.entity_kind = 'OPPORTUNITY' AND s.active = 1
                          ORDER BY p.name, s.seq"), []) ?: [],
        'users'     => gate_try(fn() => ops_all(
                         "SELECT id, username, first_name, last_name, role FROM users
                          WHERE is_active = 1 ORDER BY first_name, username"), []) ?: [],
        'roles'     => ORG_ROLES,
        'sbus'      => gate_try(fn() => lk_options_or('sbu', OPS_SBUS), []) ?: [],
    ]);
    return true;
}

function ops_approvals($route, $method) {
    ops_require(gate_can_view(), 'You cannot open the approvals queue.');
    gate_migrate();

    if ($route === 'approval-act' && $method === 'POST') {
        $r = gate_act((int)($_POST['id'] ?? 0), (string)($_POST['decision'] ?? ''), (string)($_POST['remarks'] ?? ''));
        if (!empty($r['err'])) flash($r['err'], 'error');
        else flash($r['decision'] === 'APPROVED' ? 'Approved — the deal has moved.'
                 : ($r['decision'] === 'REJECTED' ? 'Sent back, with your reason recorded.' : 'Request withdrawn.'), 'success');
        redirect((string)($_POST['back'] ?? '/approvals'));
    }

    $status = (string)($_GET['f'] ?? 'PENDING');
    if (!isset(GATE_STATUS[$status])) $status = 'PENDING';
    $q = gate_queue($status);
    view('ops/approvals', ['q' => $q, 'status' => $status, 'labels' => GATE_STATUS,
                           'can_manage' => gate_can_manage()]);
    return true;
}
