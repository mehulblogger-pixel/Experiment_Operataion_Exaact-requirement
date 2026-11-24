<?php
// ============================================================================
//  OPERATIONS FIGURES — ONE DEFINITION EACH (Stage 1 B1 / B2 / B4)
//
//  Owner, 24 Nov 2026: "approve B0–B5, all as recommended". Stage 0 found the
//  same figure worked out in up to four places, each slightly differently:
//  "not yet scheduled" read 7 / 0 / 0 / 20 on four screens at the same moment,
//  "overdue" 27 / 13 / 0, "reports pending" 0 / 29 / 2. Each card opened an
//  unfiltered register, so the number on the card could never be checked.
//
//  This file is the one place each figure is defined. Every card, register
//  filter, MIS line and digest that shows the figure asks the SAME predicate
//  here, and each card opens its register filtered by that predicate under the
//  SAME branch scope — so the number on the card is the number of rows behind
//  it. Nothing here adds a status, a rule or a permission; it reads the
//  existing fields:
//
//    work order (calls)   op_status (lifecycle) or, when blank, the legacy
//                         status read the way tosrm_derive_status() reads it
//    job                  stage = CANCELLED (tosrm_assign_cancel), closed_flag,
//                         assign_state (TENTATIVE / CONFIRMED / RELEASED),
//                         accept_state (… / DECLINED), report fields
//
//  Definitions (the names the owner approved):
//    No engineer yet       a live work order, not done, with no job that has a
//                          person or a sub-contractor on it. A declined or
//                          released assignment does not count as "on it" (B4).
//    Work orders overdue   a live work order, not done, whose client's required
//                          date has passed.
//    Jobs overdue          an open, not-cancelled job whose planned end (else its
//                          scheduled date) has passed.
//    Reports awaiting      a closed job whose report still waits for the
//    sign-off              reporting manager's sign-off.
//    Reports to write      an open, not-cancelled job that needs a report
//                          (a frequency other than "No report") with none
//                          uploaded and none issued yet.
//
//  "Live" = not closed, cancelled or rejected. "Done" = closed, or every
//  (not-cancelled) job under it closed — the rule the work-order page uses.
// ============================================================================

// The canonical work-order status as SQL — tosrm_call_status() in SQL form, so a
// count and the status pill on the row can never read two different statuses.
// A legacy open order that already has a person on a job reads ASSIGNED (B2: it
// is no longer "awaiting triage").
function opk_status_sql($a = 'c') {
    return "(CASE WHEN COALESCE($a.op_status,'')<>'' THEN $a.op_status"
         . " WHEN $a.status='CLOSED' THEN 'CLOSED'"
         . " WHEN $a.status='CANCELLED' THEN 'CANCELLED'"
         . " WHEN $a.status='ALLOCATED' THEN 'ASSIGNED'"
         . " WHEN $a.status='FORWARDED' THEN 'READY_TO_SCHEDULE'"
         . " WHEN " . opk_has_person_sql($a) . " THEN 'ASSIGNED'"
         . " ELSE 'RECEIVED' END)";
}

// A job that still counts: not cancelled.
function opk_job_live_sql($j = 'j') { return "COALESCE($j.stage,'')<>'CANCELLED'"; }

// A job somebody is actually on: a person or a sub-contractor firm, the hold not
// released and the acceptance not declined (B4 — declined work goes back to the queue).
function opk_job_staffed_sql($j = 'j') {
    return "(" . opk_job_live_sql($j) . " AND ($j.inspector_id IS NOT NULL OR $j.subcon_id IS NOT NULL)"
         . " AND COALESCE($j.assign_state,'')<>'RELEASED' AND COALESCE($j.accept_state,'')<>'DECLINED')";
}
function opk_has_person_sql($a = 'c') {
    return "EXISTS (SELECT 1 FROM jobs jp WHERE jp.call_id=$a.id AND " . opk_job_staffed_sql('jp') . ")";
}

// Live = not closed / cancelled / rejected, on either status field.
function opk_call_live_sql($a = 'c') {
    return "($a.status NOT IN ('CLOSED','CANCELLED') AND COALESCE($a.op_status,'') NOT IN ('CLOSED','CANCELLED','REJECTED'))";
}
// Done = every not-cancelled job under it is closed (and it has at least one).
function opk_call_done_sql($a = 'c') {
    return "(EXISTS (SELECT 1 FROM jobs jd WHERE jd.call_id=$a.id AND " . opk_job_live_sql('jd') . ")"
         . " AND NOT EXISTS (SELECT 1 FROM jobs jd2 WHERE jd2.call_id=$a.id AND " . opk_job_live_sql('jd2') . " AND COALESCE(jd2.closed_flag,0)=0))";
}

// The work-order figures: key => [card label, what it counts, SQL predicate on calls c].
function opk_call_defs() {
    $live = opk_call_live_sql('c'); $done = opk_call_done_sql('c'); $st = opk_status_sql('c');
    $today = date('Y-m-d');
    return [
        'new'           => ['New requests', 'awaiting triage, nobody on it yet',
                            "$live AND $st IN ('RECEIVED','DRAFT','UNDER_REVIEW') AND NOT " . opk_has_person_sql('c')],
        'no_engineer'   => ['No engineer yet', 'work orders with nobody on a job',
                            "$live AND NOT $done AND NOT " . opk_has_person_sql('c')],
        'clarification' => ['Clarification open', 'waiting on client / vendor',
                            "$live AND EXISTS (SELECT 1 FROM call_clarifications cc WHERE cc.call_id=c.id AND cc.status='OPEN')"],
        'ready'         => ['Ready to schedule', 'all inputs complete',
                            "$live AND $st='READY_TO_SCHEDULE'"],
        'wo_overdue'    => ['Work orders overdue', "client's required date passed",
                            "$live AND NOT $done AND COALESCE(c.inspection_required_date,'')<>'' AND c.inspection_required_date<'" . $today . "'"],
        'critical'      => ['Critical', 'flagged priority, still open',
                            "$live AND NOT $done AND c.priority='CRITICAL'"],
    ];
}

// The job figures: key => [label, what it counts, SQL predicate on jobs j].
function opk_job_defs() {
    $today = date('Y-m-d');
    $live = opk_job_live_sql('j');
    return [
        'today'        => ["Today's services", 'scheduled for today', "$live AND j.scheduled_date='" . $today . "'"],
        'jobs_overdue' => ['Jobs overdue', 'planned end passed, still open',
                           "$live AND COALESCE(j.closed_flag,0)=0 AND COALESCE(NULLIF(j.inspection_end_date,''), j.scheduled_date,'')<>''"
                           . " AND COALESCE(NULLIF(j.inspection_end_date,''), j.scheduled_date)<'" . $today . "'"],
        'signoff'      => ['Reports awaiting sign-off', 'closed jobs, report not yet signed off',
                           "COALESCE(j.closed_flag,0)=1 AND j.report_approval='PENDING'"],
        'to_write'     => ['Reports to write', 'open jobs still needing a report',
                           "$live AND COALESCE(j.closed_flag,0)=0 AND COALESCE(j.reporting_frequency,'') NOT IN ('','NOREPORT')"
                           . " AND COALESCE(j.report_upload_date,'')=''"
                           . " AND NOT EXISTS (SELECT 1 FROM report_docs rdw WHERE rdw.job_id=j.id AND COALESCE(rdw.deleted,0)=0 AND COALESCE(rdw.finalized,0)=1)"],
    ];
}

// Branch scope used by BOTH the Operations-home card and the filtered register it
// opens — the executing office's desk (DEF-OPS-002 rule), exactly as the cards
// always counted. A register opened without a figure keeps its own wider scope.
function opk_calls_scope() {
    $off = function_exists('tosrm_office_scope') ? tosrm_office_scope() : 'ALL';
    return function_exists('tosrm_call_office_sql') ? tosrm_call_office_sql($off, 'c') : '1=1';
}
// What a card may count = what the register it opens may show: the executing desk
// above AND the register's own visibility rule (office + business unit). Grants
// nothing — a card can only ever be the same or smaller than before.
function opk_calls_visible_scope($offices = null) {
    $parts = [$offices === null ? opk_calls_scope()
              : (function_exists('tosrm_call_office_sql') ? tosrm_call_office_sql($offices, 'c') : '1=1')];
    if (function_exists('call_office_clause')) {
        [$w] = call_office_clause('c.executing_office_id', 'c.ibo_office_id');
        if ($w !== '1=1') $parts[] = $w;
    }
    $sbu = function_exists('scope_sbus') ? scope_sbus() : 'ALL';
    if ($sbu !== 'ALL' && is_array($sbu) && $sbu) {
        $parts[] = "c.sbu IN (" . implode(',', array_map(function ($s) { return db()->quote((string) $s); }, $sbu)) . ")";
    }
    return '(' . implode(' AND ', $parts) . ')';
}
// Jobs: the jobs register's own scope (office + business unit), card and list alike.
function opk_jobs_scope() {
    return function_exists('scope_clause') ? scope_clause('j.executing_office_id', 'j.sbu') : ['1=1', []];
}

function opk_call_pred($key) { $d = opk_call_defs(); return $d[$key][2] ?? null; }
function opk_job_pred($key)  { $d = opk_job_defs();  return $d[$key][2] ?? null; }

// $offices: the executing-office desk the caller already resolved (the Operations
// home passes tosrm_office_scope(), which is what the filtered register uses).
function opk_count_calls($key, $offices = null) {
    $p = opk_call_pred($key); if ($p === null) return 0;
    try { return (int) ops_val("SELECT COUNT(*) FROM calls c WHERE " . opk_calls_visible_scope($offices) . " AND $p"); } catch (Throwable $e) { return 0; }
}
function opk_count_jobs($key) {
    $p = opk_job_pred($key); if ($p === null) return 0;
    [$w, $a] = opk_jobs_scope();
    try { return (int) ops_val("SELECT COUNT(*) FROM jobs j WHERE $w AND $p", $a); } catch (Throwable $e) { return 0; }
}
// One inspector's own jobs (no branch scope — they are theirs).
function opk_count_my_jobs($key, $inspectorId) {
    $p = opk_job_pred($key); if ($p === null || (int) $inspectorId <= 0) return 0;
    try { return (int) ops_val("SELECT COUNT(*) FROM jobs j WHERE j.inspector_id=? AND $p", [(int) $inspectorId]); } catch (Throwable $e) { return 0; }
}

// Where each card opens: the register, filtered by the same predicate.
function opk_href($key) {
    if (array_key_exists($key, opk_call_defs())) return '/calls?f=' . $key;
    if (array_key_exists($key, opk_job_defs()))  return '/jobs?f=' . $key;
    return '/operations';
}
function opk_label($key) {
    $d = opk_call_defs() + opk_job_defs();
    return $d[$key][0] ?? $key;
}
function opk_hint($key) {
    $d = opk_call_defs() + opk_job_defs();
    return $d[$key][1] ?? '';
}

// Work-order progress, in words — ONE reading for the register row, its chips, the
// CSV and the detail page (Stage 0 S1-07: the screen said "In progress" while the CSV
// and the counts each read it differently). Uses the flags the register selects
// (opk_status / opk_done / opk_no_engineer); falls back to the row's own fields.
function opk_progress_label(array $c) {
    $st = (string)($c['opk_status'] ?? (function_exists('tosrm_call_status') ? tosrm_call_status($c) : ($c['status'] ?? '')));
    if ($st === 'CANCELLED' || ($c['status'] ?? '') === 'CANCELLED') return 'Cancelled';
    if ($st === 'REJECTED') return 'Rejected';
    $done = isset($c['opk_done']) ? (int)$c['opk_done'] === 1
          : ((int)($c['job_count'] ?? 0) > 0 && (int)($c['closed_count'] ?? 0) === (int)($c['job_count'] ?? 0));
    if ($st === 'CLOSED' || ($c['status'] ?? '') === 'CLOSED' || $done) return 'Closed';
    if (isset($c['opk_no_engineer']) ? (int)$c['opk_no_engineer'] === 1 : (int)($c['job_count'] ?? 0) === 0) return 'No engineer yet';
    return 'In progress';
}
