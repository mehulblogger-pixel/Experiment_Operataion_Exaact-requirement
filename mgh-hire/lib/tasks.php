<?php
// =========================================================================
//  MGH Hire — "what needs my attention" engine.
//
//  Computes the live pending-action worklist, filtered to what the current
//  user's role can actually act on. Every query is guarded so a fresh or
//  half-migrated database yields an empty group, never an error.
// =========================================================================

function tasks_groups() {
    $groups = [];

    // 1) Requisitions awaiting approval — approvers only.
    if (can('req.approve')) {
        $rows = q_all("SELECT id,code,title FROM requisitions WHERE status='pending_approval' ORDER BY id");
        $groups[] = task_group('approvals', 'Requisitions awaiting your approval', 'a', $rows,
            fn($r) => ['?p=requisition&id='.(int)$r['id'], $r['code'].' — '.$r['title']]);
    }

    // 2) New applications sitting at the first stage — recruiters.
    if (can('cand.manage')) {
        $first = first_stage();
        if ($first) {
            $rows = q_all("SELECT id,code,name FROM candidates WHERE status='active' AND stage_id=? AND source='Careers page' ORDER BY id", [$first['id']]);
            $groups[] = task_group('new_apps', 'New applications to screen', 'n', $rows,
                fn($r) => ['?p=candidate&id='.(int)$r['id'], $r['code'].' — '.$r['name']]);
        }
    }

    // 3) Interviews past their date with no outcome — interviewers/recruiters.
    if (can('interview.log')) {
        $today = date('Y-m-d');
        $rows = q_all(
            "SELECT i.id, i.round, i.scheduled_at, c.id cid, c.code, c.name
             FROM interviews i JOIN candidates c ON c.id=i.candidate_id
             WHERE (i.result IS NULL OR i.result='') AND i.scheduled_at<>'' AND i.scheduled_at < ?
               AND c.status='active'
             ORDER BY i.scheduled_at", [$today.'T99']);
        $groups[] = task_group('overdue_int', 'Interviews awaiting an outcome', 'r', $rows,
            fn($r) => ['?p=candidate&id='.(int)$r['cid'], $r['code'].' — '.$r['name'].' ('.$r['round'].', '.fdate($r['scheduled_at']).')']);
    }

    // 4) Candidates stalled at a stage too long — recruiters.
    if (can('cand.move')) {
        $days = max(1, (int)setting('stall_days','7'));
        $cut  = date('c', strtotime("-$days days"));
        // Last event time per candidate = how long they've sat.
        $rows = q_all(
            "SELECT c.id, c.code, c.name, s.name stage,
                    (SELECT MAX(created_at) FROM candidate_events e WHERE e.candidate_id=c.id) AS last_move
             FROM candidates c LEFT JOIN stages s ON s.id=c.stage_id
             WHERE c.status='active'
             ORDER BY last_move");
        $rows = array_values(array_filter($rows, fn($r) => $r['last_move'] && $r['last_move'] < $cut));
        $groups[] = task_group('stalled', "Candidates with no movement for $days+ days", 'a', $rows,
            fn($r) => ['?p=candidate&id='.(int)$r['id'], $r['code'].' — '.$r['name'].' · '.($r['stage'] ?: '—')]);
    }

    // 5) Offers issued but not yet accepted — recruiters.
    if (can('offer.manage')) {
        $rows = q_all(
            "SELECT o.id, c.id cid, c.code, c.name FROM offers o JOIN candidates c ON c.id=o.candidate_id
             WHERE o.status='issued' ORDER BY o.issued_at");
        $groups[] = task_group('offers', 'Offers awaiting acceptance', 'a', $rows,
            fn($r) => ['?p=candidate&id='.(int)$r['cid'], $r['code'].' — '.$r['name']]);
    }

    // Drop empty groups.
    return array_values(array_filter($groups, fn($g) => $g['count'] > 0));
}

function tasks_total() {
    $n = 0; foreach (tasks_groups() as $g) $n += $g['count']; return $n;
}

// ---- helpers --------------------------------------------------------------
function task_group($key, $label, $tone, $rows, $mapper) {
    $items = [];
    foreach ($rows as $r) { [$link,$text] = $mapper($r); $items[] = ['link'=>$link,'text'=>$text]; }
    return ['key'=>$key, 'label'=>$label, 'tone'=>$tone, 'count'=>count($items), 'items'=>$items];
}
function q_all($sql, $args = []) {
    try { $s = db()->prepare($sql); $s->execute($args); return $s->fetchAll(); }
    catch (Throwable $e) { return []; }
}
