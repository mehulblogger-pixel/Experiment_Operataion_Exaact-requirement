<?php
// The owner, 20 Oct 2026: "if lead is lost the responsible person must mark as
// lost with a valid reason and remarks. Reassigning shall be done only after
// manager approval."
t_section('leads: the owner marks lost with reason + remarks; reassignment waits for a manager');

$pdo = db();
leads_migrate();
$off = (int)(ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 0);
$mkU = function ($u, $role, $reportsTo = null) use ($pdo, $off) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, reports_to_id, permissions, is_active) VALUES (?,?,?,?,?,'',1)")
        ->execute([$u, $u, $role, $off ?: null, $reportsTo]);
    return (int)$pdo->lastInsertId();
};
$bm   = $mkU('llr_bm', 'BRANCH_MANAGER');
$exec = $mkU('llr_exec', 'MARKETING_EXECUTIVE', $bm);
$kam  = $mkU('llr_kam', 'KEY_ACCOUNTS_MANAGER', $bm);
$mm   = $mkU('llr_mm', 'MARKETING_MANAGER');
$stage = (int)ops_val("SELECT id FROM pipeline_stages WHERE kind='LOST' ORDER BY id LIMIT 1");
$pipe  = (int)ops_val("SELECT pipeline_id FROM pipeline_stages WHERE id=?", [$stage]);
$open  = (int)ops_val("SELECT id FROM pipeline_stages WHERE pipeline_id=? AND kind='OPEN' ORDER BY seq LIMIT 1", [$pipe]);
$mkL = function ($ref, $owner) use ($pdo, $pipe, $open, $off) {
    $pdo->prepare("INSERT INTO leads (ref, pipeline_id, stage_id, company_name, status, owner_user_id, owner_name, office_id, created_at, updated_at, stage_since)
                   VALUES (?,?,?,?,'OPEN',?,?,?,?,?,?)")->execute([$ref, $pipe, $open, 'Co ' . $ref, $owner ?: null, $owner ? 'owner' : '', $off ?: null, date('c'), date('c'), date('c')]);
    return (int)$pdo->lastInsertId();
};
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$reason = array_key_first(lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS));

// --- marking lost ------------------------------------------------------------
$L1 = $mkL('LLR-1', $exec);
$as($kam);  t_ok(!empty(lead_move($L1, $stage, ['lost_reason' => $reason, 'lost_note' => 'x'])['err']), 'only the responsible person marks a lead lost');
$as($exec); t_ok(!empty(lead_move($L1, $stage, ['lost_reason' => $reason, 'lost_note' => ''])['err']), 'remarks are required');
            t_ok(!empty(lead_move($L1, $stage, ['lost_reason' => 'NOT_ON_LIST', 'lost_note' => 'x'])['err']), 'a reason must be on the list');
            t_ok(empty(lead_move($L1, $stage, ['lost_reason' => $reason, 'lost_note' => 'Chose a competitor'])['err'])
                 && lead_row($L1)['status'] === 'LOST', 'the owner with a reason and remarks marks it lost');

// --- reassignment --------------------------------------------------------------
$L2 = $mkL('LLR-2', $exec);
$as($exec);
t_ok(!empty(lead_change_owner($L2, $kam, '')['err']), 'reassigning needs a reason');
t_ok((lead_change_owner($L2, $kam, 'Key account')['ok'] ?? '') === 'requested' && (int)lead_row($L2)['owner_user_id'] === $exec,
    'an executive asks; the lead stays with its owner');
t_ok(!empty(lead_change_owner($L2, $mm, 'again')['err']), 'one request at a time per lead');
$req = lead_reassign_pending($L2);
t_ok(!empty(lead_reassign_decide((int)$req['id'], true)['err']), 'the person who asked cannot approve it');
$as($bm);
t_ok(in_array((int)$req['id'], array_map(fn($r) => (int)$r['id'], lead_reassign_waiting_on_me()), true), 'it waits on the owner\'s reporting manager');
t_ok(!empty(lead_reassign_decide((int)$req['id'], false, '')['err']), 'rejecting needs a reason');
t_ok((lead_reassign_decide((int)$req['id'], true, 'ok')['ok'] ?? '') === 'approved' && (int)lead_row($L2)['owner_user_id'] === $kam, 'approved: it changes hands');

$L3 = $mkL('LLR-3', $exec);
$as($mm);   t_ok((lead_change_owner($L3, $kam, 'Rebalancing')['ok'] ?? '') === 'done', 'a sales manager over the lead reassigns it directly');
$L4 = $mkL('LLR-4', 0);
$as($exec); t_ok((lead_change_owner($L4, $kam, '')['ok'] ?? '') === 'done', 'a lead nobody owns is allocated without approval');

// The request goes stale if the lead closes first.
$L5 = $mkL('LLR-5', $exec);
$c5 = lead_change_owner($L5, $kam, 'please'); $r5 = lead_reassign_pending($L5);
t_ok(($c5['ok'] ?? '') === 'requested' && $r5, 'a request is made on another lead');
$pdo->prepare("UPDATE leads SET status='LOST' WHERE id=?")->execute([$L5]);
$as($bm); $d5 = lead_reassign_decide((int)$r5['id'], true); t_ok(!empty($d5['err']) && lead_reassign_pending($L5) === null && lead_row($L5)['owner_user_id'] == $exec, 'a request on a lead that closed meanwhile is cancelled, not applied');

// The inbox and the screens.
$hub = file_get_contents(__DIR__ . '/../lib/approvals_hub.php');
t_ok(strpos($hub, "'lead_reassign'") !== false && strpos($hub, "'quotes'") !== false, 'Approvals lists lead reassignments and quotations');
$sg = file_get_contents(__DIR__ . '/../lib/stagegate.php');
t_ok(strpos($sg, "'approval-lead-decide'") !== false, 'a manager decides from Approvals without needing the lead register');
$det = file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
t_ok(strpos($det, 'name="lost_note" required') !== false, 'the lost form asks for remarks');

unset($_SESSION['uid']); current_user(true); ua(true);
$pdo->exec("DELETE FROM lead_reassign_requests WHERE lead_id IN (SELECT id FROM leads WHERE ref LIKE 'LLR-%')");
$pdo->exec("DELETE FROM leads WHERE ref LIKE 'LLR-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'llr_%'");
