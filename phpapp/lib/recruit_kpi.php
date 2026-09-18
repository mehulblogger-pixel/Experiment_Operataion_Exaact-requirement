<?php
// ============================================================================
//  PHASE 5 — RECRUITMENT KPI, SLA & PERFORMANCE ENGINE
//
//  ONE authoritative calculation per KPI. Every screen, export, dashboard and
//  API reads THIS file. Nothing recomputes demand, fulfilment, ageing or
//  ownership for itself — that is the defect this phase exists to remove.
//
//  WHAT THIS FILE IS NOT
//  ---------------------
//  It is not a second fulfilment engine. It does not decide what "filled" means
//  (M3 does, in REQF_FILLED_STAGES), what is authorised (M4), who is accountable
//  (M5, in the recruiter_assignments ledger), whether recruitment may execute
//  (M6), or how much is promised to which source (Phase 4, in rful_summary()).
//  It ASKS those owners and presents the answer. Where it aggregates over many
//  requirements it does so set-based for speed, and a reconciliation probe
//  proves the aggregate equals the sum of the per-record owners — never assumes.
//
//  THE RULE THAT GOVERNS EVERY NUMBER HERE
//  ---------------------------------------
//  A figure that cannot be computed truthfully is NO DATA — null — never zero.
//  Zero is a measurement. Null is the absence of one. A screen that shows "0
//  days late" for a requirement that has no target date is lying, and the lie is
//  the kind a business acts on.
// ============================================================================

//  The dashboard's live-demand definition, named ONCE in M5 and read here — the
//  same list, never a copy. A literal list here is how the Command Centre came
//  to report 7 open positions where the records said 3.
function rkpi_live_req() {
    return defined('RASG_LIVE_REQ') ? RASG_LIVE_REQ
         : ['OPEN', 'PROPOSED', 'OFFERED', 'PARTIALLY_FILLED', 'HIRED'];
}
function rkpi_filled_stages() { return defined('REQF_FILLED_STAGES') ? REQF_FILLED_STAGES : ['ACCEPTED']; }
function rkpi_active_stages() { return defined('REQF_ACTIVE_STAGES') ? REQF_ACTIVE_STAGES : ['RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD']; }
function rkpi_lost_stages()   { return defined('REQF_LOST_STAGES')   ? REQF_LOST_STAGES   : ['REJECTED','WITHDRAWN','OFFER_DECLINED']; }

function rkpi_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function rkpi_in($list) { return "'" . implode("','", array_map(fn($s) => str_replace("'", "''", (string) $s), $list)) . "'"; }

// ---------------------------------------------------------------------------
//  MIGRATION — additive only, forward-only, idempotent.
//
//  The ONLY schema change Phase 5 makes: four nullable columns that turn
//  candidate_events from a human-readable note into a ledger a KPI may read.
//  The pre-implementation audit measured why (docs/phase5, section 5a):
//    · `to_stage` carries pipeline stage NAMES, legacy stage CODES and literal
//      "Workflow: …" strings in one column, so grouping by it silently splits
//      or merges stages;
//    · nothing distinguished a forward move from a reverted one.
//  Existing rows keep their words untouched and simply carry no code — which is
//  honest, and which every reader below treats as NO DATA rather than guessing.
// ---------------------------------------------------------------------------
function rkpi_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    try {
        //  The unambiguous identity of the stage moved FROM and TO. For a
        //  configured pipeline this is recruit_stages.stage_key; for the legacy
        //  ladder it is the CAND_STAGES code. Never a display name.
        ensure_column('candidate_events', 'from_code', "VARCHAR(60) DEFAULT ''");
        ensure_column('candidate_events', 'to_code',   "VARCHAR(60) DEFAULT ''");
        //  WHICH vocabulary the two codes above belong to, so the two ladders are
        //  never averaged together: 'LEGACY' or 'PIPELINE'.
        ensure_column('candidate_events', 'track',     "VARCHAR(12) DEFAULT ''");
        //  WHAT happened: a normal MOVE, a REVERT (the execution gate undoing a
        //  joining that had no seat), or a SWITCH of workflow. A revert is not a
        //  stage transition and must never be timed as one.
        ensure_column('candidate_events', 'event_kind', "VARCHAR(20) DEFAULT ''");
    } catch (Throwable $e) { /* never break boot */ }
    if (function_exists('act_index')) {
        try { act_index('candidate_events', 'idx_ce_cand', '(candidate_id, id)'); } catch (Throwable $e) {}
    }
}

// ---------------------------------------------------------------------------
//  THE ONE STAGE-LEDGER WRITER
//
//  Every stage movement writes through here, so the ledger speaks one language.
//  Before Phase 5 three paths wrote it and two more moved a candidate's stage
//  without writing it at all — issuing an offer, and the execution gate
//  reverting a joining that had no seat. The second was the worse of the two:
//  the candidate went back to OFFERED and the ledger still ended at ACCEPTED,
//  so the ledger said a person had joined who had not.
// ---------------------------------------------------------------------------
function rkpi_stage_log($candidateId, $fromLabel, $toLabel, array $o = []) {
    rkpi_migrate();
    $cid = (int) $candidateId; if ($cid <= 0) return false;
    try {
        db()->prepare("INSERT INTO candidate_events
                       (candidate_id,from_stage,to_stage,remark,actor,created_at,from_code,to_code,track,event_kind)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$cid, (string) $fromLabel, (string) $toLabel,
                       substr((string) ($o['remark'] ?? ''), 0, 500),
                       substr((string) ($o['actor'] ?? (function_exists('user_name') ? (string) user_name(current_user()) : '')), 0, 150),
                       rkpi_now(),
                       substr((string) ($o['from_code'] ?? ''), 0, 60),
                       substr((string) ($o['to_code'] ?? ''), 0, 60),
                       in_array(($o['track'] ?? ''), ['LEGACY', 'PIPELINE'], true) ? $o['track'] : '',
                       in_array(($o['kind'] ?? 'MOVE'), ['MOVE', 'REVERT', 'SWITCH'], true) ? ($o['kind'] ?? 'MOVE') : 'MOVE']);
        return true;
    } catch (Throwable $e) {
        //  A ledger write must never lose a business action that already
        //  succeeded. It is recorded as a failure to observe, not a failure to
        //  transact — the stage change itself stands.
        return false;
    }
}

//  One candidate's movement, oldest first. Reverts are RETURNED, not hidden:
//  a reader that wants durations excludes them; a reader showing history to a
//  human must show that the joining was undone.
function rkpi_stage_history($candidateId) {
    rkpi_migrate();
    try {
        $st = db()->prepare("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id ASC");
        $st->execute([(int) $candidateId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------------------
//  CANONICAL AGEING (§7)
//
//  Three ageing questions the business asks, kept apart on purpose because
//  averaging them produces a number that answers none of them:
//    · CALENDAR  — how long the candidate has actually been waiting. This is
//                  what a person feels, and what a client is told.
//    · BUSINESS  — how long WE had to act. Sundays and the branch's own holiday
//                  list do not count against a recruiter.
//    · SLA       — measured by the approval engine, which already owns it.
//
//  Every one of these returns null when it cannot be computed. Two bad dates
//  produce NO DATA, never 0, and never "today".
// ---------------------------------------------------------------------------
const RKPI_BASES = ['calendar', 'business'];

function rkpi_days($from, $to = null) {
    $f = trim((string) $from); if ($f === '') return null;
    $t = trim((string) ($to ?? ''));
    if ($t === '') $t = date('Y-m-d');
    $a = strtotime(substr($f, 0, 10)); $b = strtotime(substr($t, 0, 10));
    if ($a === false || $b === false) return null;
    return (int) round(($b - $a) / 86400);
}

//  Working days between two dates, counted through the office's OWN calendar —
//  the same is_working_day() the scheduling module uses, never a second opinion
//  about which days this branch works. The start date is not counted; the end
//  date is, when it is a working day — "raised Monday, filled Tuesday" is one
//  working day of effort, not zero and not two.
function rkpi_business_days($from, $to = null, $officeId = null) {
    $f = trim((string) $from); if ($f === '') return null;
    $t = trim((string) ($to ?? '')); if ($t === '') $t = date('Y-m-d');
    $a = strtotime(substr($f, 0, 10)); $b = strtotime(substr($t, 0, 10));
    if ($a === false || $b === false) return null;
    if (!function_exists('is_working_day')) return rkpi_days($f, $t);
    $sign = 1; if ($b < $a) { $sign = -1; [$a, $b] = [$b, $a]; }
    //  A guard, not a business rule: a corrupt date a century out must not spin.
    if (($b - $a) / 86400 > 3650) return null;
    $n = 0;
    for ($d = $a + 86400; $d <= $b; $d += 86400)
        if (is_working_day(date('Y-m-d', $d), $officeId)) $n++;
    return $sign * $n;
}

//  The one entry point. A caller names the basis it means; it cannot get one
//  silently substituted for the other.
function rkpi_age($from, $to = null, $basis = 'calendar', $officeId = null) {
    $b = strtolower(trim((string) $basis));
    if (!in_array($b, RKPI_BASES, true)) return null;      // fail closed on an unknown basis
    return $b === 'business' ? rkpi_business_days($from, $to, $officeId) : rkpi_days($from, $to);
}

// ---------------------------------------------------------------------------
//  THE TARGET DATE (§8)
//
//  A KPI measured against an invented target is worse than no KPI. Measured in
//  the audit: the business's own "needed by" date lives on
//  hiring_requests.required_by and reaches a requisition through
//  hiring_request_id. `requisitions` has NO target column of its own, and a
//  requirement raised on the DIRECT path has no hiring request — so it has no
//  target date at all.
//
//  For that requirement the answer is NO_TARGET. Not today. Not its creation
//  date. Not zero days late.
// ---------------------------------------------------------------------------
function rkpi_target($req) {
    $out = ['date' => null, 'source' => null, 'days_late' => null, 'state' => 'NO_TARGET'];
    $r = is_array($req) ? $req : null;
    if ($r === null) {
        $id = (int) $req; if ($id <= 0) return $out;
        try { $r = ops_one("SELECT * FROM requisitions WHERE id=?", [$id]) ?: null; } catch (Throwable $e) { $r = null; }
    }
    if (!$r) return $out;

    //  Asked THROUGH the M4 layer, never by reading its table. Only
    //  lib/hiringreq.php may touch hiring_requests — a boundary M4 set and its
    //  own suite enforces, and which this engine reaching in with a SELECT would
    //  have quietly broken.
    $date = '';
    $hr = (int) ($r['hiring_request_id'] ?? 0);
    if ($hr > 0 && function_exists('hreq_get')) {
        $req = hreq_get($hr);
        $date = $req ? trim((string) ($req['required_by'] ?? '')) : '';
        if ($date !== '') $out['source'] = 'hiring request — needed by';
    }
    if ($date === '' || strtotime($date) === false) return $out;   // no target, or not a date
    $out['date'] = substr($date, 0, 10);

    //  Measured against the day the requirement was SETTLED where it has been —
    //  otherwise a requirement filled last month keeps ageing on the screen.
    $closed = trim((string) ($r['closed_at'] ?? ''));
    $asAt   = $closed !== '' ? substr($closed, 0, 10) : date('Y-m-d');
    $late = rkpi_days($out['date'], $asAt);
    if ($late === null) return $out;
    $out['days_late'] = $late;
    $out['state'] = $late > 0 ? 'LATE' : ($late === 0 ? 'DUE' : 'ON_TIME');
    return $out;
}

// ---------------------------------------------------------------------------
//  DEMAND — the authoritative figures, for ONE requirement (§4)
//
//  Every number here comes from its owner. Nothing is counted twice and nothing
//  is counted here that somebody else already counts.
// ---------------------------------------------------------------------------
function rkpi_requisition($req) {
    $r = is_array($req) ? $req : null;
    $id = (int) (is_array($req) ? ($req['id'] ?? 0) : $req);
    $out = ['id' => $id, 'requested' => 0, 'cancelled' => 0, 'authorised' => 0,
            'filled' => 0, 'in_progress' => 0, 'lost' => 0, 'remaining' => 0,
            'allocated' => 0, 'unallocated' => 0, 'committed' => 0, 'over_committed' => 0,
            'sources' => [], 'target' => ['date' => null, 'days_late' => null, 'state' => 'NO_TARGET']];
    if ($id <= 0) return $out;

    if (function_exists('reqf_counts')) {
        $c = reqf_counts($id);
        $out['requested']   = (int) ($c['requested'] ?? 0);
        $out['cancelled']   = (int) ($c['cancelled'] ?? 0);
        $out['filled']      = (int) ($c['filled'] ?? 0);
        $out['in_progress'] = (int) ($c['in_progress'] ?? 0);
        $out['lost']        = (int) ($c['lost'] ?? 0);
        $out['remaining']   = (int) ($c['remaining'] ?? 0);
        $out['authorised']  = max(0, $out['requested'] - $out['cancelled']);
    }
    if (function_exists('rful_summary')) {
        $s = rful_summary($id);
        //  AUTHORISED is asked of Phase 4 too, and the two MUST agree — Phase 4
        //  derives it from the same M3 counters. A reconciliation probe proves
        //  it; this is not a place to prefer one over the other quietly.
        $out['allocated']      = (int) ($s['allocated'] ?? 0);
        $out['unallocated']    = (int) ($s['unallocated'] ?? 0);
        $out['committed']      = (int) ($s['committed'] ?? 0);
        $out['over_committed'] = (int) ($s['over_committed'] ?? 0);
        $out['sources']        = (array) ($s['sources'] ?? []);
    }
    $out['target'] = rkpi_target($r ?: $id);
    return $out;
}

// ---------------------------------------------------------------------------
//  DEMAND — across a scoped set of requirements (§4, §24)
//
//  A dashboard cannot afford one round trip per requirement, so this aggregates
//  set-based. It uses the SAME definitions the per-record owners use —
//  REQF_FILLED_STAGES, cancelled_qty, RASG_LIVE_REQ — and the reconciliation
//  battery proves the totals equal the sum of reqf_counts() row by row. That
//  proof is the licence to aggregate; without it this would be exactly the
//  private arithmetic Phase 5 exists to delete.
//
//  $opt: ['where' => extra SQL on alias r, 'args' => [], 'no_scope' => bool]
// ---------------------------------------------------------------------------
function rkpi_demand(array $opt = []) {
    if (function_exists('reqf_migrate')) reqf_migrate();
    if (function_exists('rful_migrate')) { try { rful_migrate(); } catch (Throwable $e) {} }
    $out = ['requisitions' => 0, 'requested' => 0, 'cancelled' => 0, 'authorised' => 0,
            'filled' => 0, 'remaining' => 0, 'allocated' => 0, 'unallocated' => 0,
            'over_committed' => 0, 'in_progress' => 0, 'lost' => 0];

    [$sw, $sa] = (function_exists('scope_clause') && empty($opt['no_scope']))
        ? scope_clause('r.office_id', 'r.sbu') : ['1=1', []];
    $live  = rkpi_in(rkpi_live_req());
    $extra = trim((string) ($opt['where'] ?? ''));
    $args  = array_merge($sa, (array) ($opt['args'] ?? []));
    $where = "$sw AND r.status IN ($live)" . ($extra !== '' ? " AND ($extra)" : '');

    $fill = rkpi_in(rkpi_filled_stages());
    $act  = rkpi_in(rkpi_active_stages());
    $lost = rkpi_in(rkpi_lost_stages());
    $lst  = defined('RFUL_LIVE_STATES') ? rkpi_in(RFUL_LIVE_STATES) : "'PLANNED','ACTIVE','FULFILLED'";

    //  ONE round trip, and every figure computed PER REQUIREMENT before it is
    //  summed. That distinction is the whole correctness argument: one
    //  requirement over-filled must never pay for another that is short, and a
    //  ceiling breached on one must not be hidden by headroom on another.
    //
    //  Each row-level rule is M3's or Phase 4's, restated in SQL and PROVED
    //  equal to it by the reconciliation battery — never a second opinion:
    //    q     — quantity, a missing or zero quantity meaning one person
    //    canc  — cancelled vacancies, clamped to q (reqf_counts clamps it too:
    //            "2 requested, 6 cancelled" is not a number anybody can act on)
    //    auth  — q − canc, the approved ceiling (M4)
    //    fl    — people at a FILLED stage, clamped to q
    //    alloc — seats promised on LIVE source allocations (Phase 4)
    //    dir   — people who arrived through no source at all
    $sql = "SELECT
              COUNT(*) reqs,
              COALESCE(SUM(q),0)                                  requested,
              COALESCE(SUM(canc),0)                               cancelled,
              COALESCE(SUM(q - canc),0)                           authorised,
              COALESCE(SUM(fl),0)                                 filled,
              COALESCE(SUM(inprog),0)                             in_progress,
              COALESCE(SUM(lostn),0)                              lost,
              COALESCE(SUM(CASE WHEN q - canc - fl   > 0 THEN q - canc - fl   ELSE 0 END),0) remaining,
              COALESCE(SUM(alloc),0)                              allocated,
              COALESCE(SUM(CASE WHEN q - canc - alloc - dir > 0 THEN q - canc - alloc - dir ELSE 0 END),0) unallocated,
              COALESCE(SUM(CASE WHEN alloc + dir - (q - canc) > 0 THEN alloc + dir - (q - canc) ELSE 0 END),0) over_committed
            FROM (
              SELECT
                q, canc,
                CASE WHEN fl > q THEN q ELSE fl END fl,
                inprog, lostn, alloc,
                CASE WHEN dir > q THEN q ELSE dir END dir
              FROM (
                SELECT
                  CASE WHEN COALESCE(r.quantity,0) > 0 THEN r.quantity ELSE 1 END q,
                  CASE WHEN COALESCE(r.cancelled_qty,0) > (CASE WHEN COALESCE(r.quantity,0) > 0 THEN r.quantity ELSE 1 END)
                       THEN (CASE WHEN COALESCE(r.quantity,0) > 0 THEN r.quantity ELSE 1 END)
                       WHEN COALESCE(r.cancelled_qty,0) > 0 THEN r.cancelled_qty ELSE 0 END canc,
                  (SELECT COUNT(*) FROM candidates c1 WHERE c1.requisition_id=r.id AND c1.stage IN ($fill)) fl,
                  (SELECT COUNT(*) FROM candidates c2 WHERE c2.requisition_id=r.id AND c2.stage IN ($act))  inprog,
                  (SELECT COUNT(*) FROM candidates c3 WHERE c3.requisition_id=r.id AND c3.stage IN ($lost)) lostn,
                  COALESCE((SELECT SUM(CASE WHEN a.allocated_qty > 0 THEN a.allocated_qty ELSE 0 END)
                            FROM requisition_allocations a
                            WHERE a.requisition_id=r.id AND UPPER(a.status) IN ($lst)),0) alloc,
                  (SELECT COUNT(*) FROM candidates c4 WHERE c4.requisition_id=r.id AND c4.stage IN ($fill)
                                                        AND COALESCE(c4.allocation_id,0)=0) dir
                FROM requisitions r WHERE $where
              ) row1
            ) t";
    try {
        $row = ops_one($sql, $args) ?: [];
    } catch (Throwable $e) {
        //  An installation with no allocations table yet (or no candidates)
        //  must not take a dashboard down — but it must not invent figures
        //  either. Everything stays at its NO-MEASUREMENT zero and the caller
        //  sees an empty set, which is what it is.
        return $out;
    }
    foreach (['requisitions'=>'reqs','requested'=>'requested','cancelled'=>'cancelled',
              'authorised'=>'authorised','filled'=>'filled','in_progress'=>'in_progress',
              'lost'=>'lost','remaining'=>'remaining','allocated'=>'allocated',
              'unallocated'=>'unallocated','over_committed'=>'over_committed'] as $k => $c)
        $out[$k] = max(0, (int) ($row[$c] ?? 0));
    return $out;
}

// ---------------------------------------------------------------------------
//  RECRUITER ACCOUNTABILITY (§5, §11)
//
//  Two questions that look alike and are not, and a dashboard that answers one
//  with the other is a dashboard that pays the wrong person:
//
//    · WHAT AM I CARRYING NOW?  — current responsibility. The `recruiter_id`
//      column IS that answer, because it is the current state. M5's
//      rasg_workload() already computes it against the same scope rules the
//      registers use, so it is asked, not re-implemented.
//
//    · WHO DELIVERED THIS?      — historical credit. The current column CANNOT
//      answer it. Reassign a requirement today and every hire made under its
//      previous recruiter silently moves to the new one — last month's
//      performance table rewrites itself. The recruiter_assignments ledger
//      exists precisely so history survives reassignment, and this is read from
//      the ledger or not at all.
// ---------------------------------------------------------------------------

//  WHO HELD THIS RECORD, AND ON WHAT EVIDENCE.
//
//  Returns [user_id|null, basis]. The basis is returned because the caller — and
//  ultimately the person reading a performance table — is entitled to know how
//  strong the claim is. A number whose provenance cannot be stated is a number
//  that should not be on a screen.
//
//  The order below is the whole argument, and the middle case is the one that
//  matters most:
//
//    ledger                     — a recorded assignment at or before the moment
//                                 asked about. The strongest answer there is.
//
//    ledger_before_first_change — every recorded change happened AFTER the
//                                 moment asked about, so the FIRST change tells
//                                 us who held it beforehand: its from_user_id.
//                                 Without this, a record assigned before the
//                                 ledger existed and reassigned today would
//                                 credit today's owner with yesterday's work —
//                                 the exact rewriting of history the ledger
//                                 exists to prevent, arriving through the back
//                                 door of an empty search result.
//
//    current_no_history         — the ledger has NOTHING for this record, so
//                                 nothing can have been rewritten. The current
//                                 column is then the only evidence there is, and
//                                 it is used and LABELLED, not used and hidden.
//                                 Every installation upgrading into Phase 5
//                                 carries records like this; refusing to read
//                                 them would blank every recruiter's delivery
//                                 figure on the day of the upgrade.
//
//    null                       — genuinely unattributable. Said, not guessed.
function rkpi_owner_basis($subject, $entityId, $whenIso) {
    if (!function_exists('rasg_history')) return [null, 'no_ledger'];
    $hist = rasg_history($subject, (int) $entityId);
    //  rasg_history returns newest first; ordered oldest-first here so "earliest"
    //  means earliest.
    $rows = [];
    foreach ($hist as $h) {
        $t = strtotime((string) ($h['created_at'] ?? ''));
        if ($t !== false) $rows[] = ['t' => $t, 'from' => (int) ($h['from_user_id'] ?? 0), 'to' => (int) ($h['to_user_id'] ?? 0)];
    }
    usort($rows, fn($a, $b) => $a['t'] <=> $b['t']);

    $cur = rkpi_current_owner($subject, $entityId);
    if (!$rows) return [$cur, $cur === null ? 'no_ledger' : 'current_no_history'];

    $t = $whenIso === null ? null : strtotime((string) $whenIso);
    if ($whenIso !== null && $t !== false && $t !== null) {
        $at = null;
        foreach ($rows as $r) if ($r['t'] <= $t) $at = $r;
        if ($at !== null) return [$at['to'], 'ledger'];
        return [$rows[0]['from'], 'ledger_before_first_change'];
    }

    //  No moment to ask about. The ledger can still answer IF ownership never
    //  actually moved — one owner throughout cannot be the wrong owner at any
    //  moment. If it did move, we do not know which side of the move this
    //  outcome falls on, and saying so is the only honest answer.
    $owners = [];
    foreach ($rows as $r) { if ($r['from']) $owners[$r['from']] = 1; if ($r['to']) $owners[$r['to']] = 1; }
    if (count($owners) === 1) return [(int) array_key_first($owners), 'ledger_single_owner'];
    return [null, 'ambiguous_no_date'];
}

//  The CURRENT holder, straight from the subject's own column. Used only where
//  the rules above say it may be, and never as a silent fallback.
function rkpi_current_owner($subject, $entityId) {
    $s = defined('RASG_SUBJECTS') ? (RASG_SUBJECTS[$subject] ?? null) : null;
    if (!$s) return null;
    try { $r = ops_one("SELECT {$s['col']} uid FROM {$s['table']} WHERE id=?", [(int) $entityId]); }
    catch (Throwable $e) { return null; }
    if (!$r) return null;
    $uid = (int) ($r['uid'] ?? 0);
    return $uid > 0 ? $uid : null;
}

//  The narrow question, for callers that only want the answer.
function rkpi_owner_at($subject, $entityId, $whenIso) {
    [$uid, $basis] = rkpi_owner_basis($subject, $entityId, $whenIso);
    return in_array($basis, ['no_ledger', 'ambiguous_no_date'], true) ? null : $uid;
}

//  What this person is carrying NOW. M5's own counter, unchanged.
function rkpi_recruiter_current($uid, array $opt = []) {
    if (!function_exists('rasg_workload')) return [];
    return rasg_workload((int) $uid, $opt);
}

//  What this person DELIVERED, attributed from the ledger at the moment each
//  outcome was SETTLED — so it does not change when the record is reassigned.
//
//  Only settled outcomes are credited: somebody joined, or was lost. An offer in
//  flight is not an outcome, it is work in hand, and rkpi_recruiter_current()
//  already reports it. Counting in-flight work as delivery is how a performance
//  table comes to reward activity instead of results.
//
//  A settled outcome the ledger cannot attribute is NOT quietly given to whoever
//  holds the record now — that is the defect this replaces. It is reported by
//  rkpi_unattributed() as a number the business can see and go and fix.
function rkpi_recruiter_credit($uid, array $opt = []) {
    $uid = (int) $uid;
    $out = ['hires' => 0, 'lost' => 0, 'tth_days' => null, 'tth_n' => 0];
    if (!function_exists('rasg_history')) return $out;
    foreach (rkpi_settled_rows($opt) as $c) {
        $at = trim((string) ($c['decided_at'] ?? ''));
        //  A settled outcome with no decision stamp still has an owner when
        //  ownership never moved — see rkpi_owner_basis(). Only a record that
        //  BOTH lacks a date AND changed hands is genuinely unattributable, and
        //  rkpi_unattributed() reports exactly those.
        [$held, $basis] = rkpi_owner_basis('CAND_RECRUITER', (int) $c['id'], $at !== '' ? $at : null);
        if ($held === null || $held !== $uid) continue;
        if (in_array((string) $c['stage'], rkpi_lost_stages(), true)) { $out['lost']++; continue; }
        $out['hires']++;
        //  TIME TO HIRE, on the ONE definition this engine publishes: from the
        //  day the CV was received — falling back to the day the record was
        //  opened, which is the earliest moment we can honestly claim to have
        //  had the person — to the day the joining was decided. CALENDAR days,
        //  because that is the wait the candidate and the business both lived
        //  through. The business-day variant is a different question and has its
        //  own basis argument; the two are never averaged together.
        if ($at === '') continue;             // credited, but it cannot be timed
        $from = trim((string) ($c['cv_received_date'] ?? '')) ?: trim((string) ($c['created_at'] ?? ''));
        $d = rkpi_age($from, $at, 'calendar');
        if ($d !== null && $d >= 0) { $out['tth_n']++; $out['_sum'] = ($out['_sum'] ?? 0) + $d; }
    }
    if ($out['tth_n'] > 0) $out['tth_days'] = (int) round($out['_sum'] / $out['tth_n']);
    unset($out['_sum']);
    return $out;
}

//  The candidates whose outcome is settled, in the caller's scope.
//
//  NOT CACHED, deliberately, and this was found the hard way. A static cache here
//  returned rows fetched earlier in the same process, so a caller that read after
//  a write got the state from before it. In production each request is its own
//  process and it would have looked harmless for a long time; in one long-running
//  process it was immediately, visibly wrong. A cache whose invalidation nobody
//  can state is a defect waiting for a quiet afternoon.
//
//  The cost it was there to avoid is avoided properly instead: a caller with many
//  recruiters to report reads this ONCE and passes the rows in through
//  $opt['rows'], so a performance table is one query and not one per person.
function rkpi_settled_rows(array $opt = []) {
    if (isset($opt['rows']) && is_array($opt['rows'])) return $opt['rows'];
    $fill = rkpi_in(rkpi_filled_stages());
    $lost = rkpi_in(rkpi_lost_stages());
    [$cw, $ca] = (function_exists('rasg_cand_scope') && empty($opt['no_scope']))
        ? rasg_cand_scope('c') : ['1=1', []];
    $extra = trim((string) ($opt['where'] ?? ''));
    $where = $cw . ($extra !== '' ? " AND ($extra)" : '');
    $args  = array_merge($ca, (array) ($opt['args'] ?? []));
    try {
        $rows = ops_all("SELECT c.id, c.stage, c.created_at, c.decided_at, c.cv_received_date
                         FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
                         WHERE $where AND (c.stage IN ($fill) OR c.stage IN ($lost))", $args) ?: [];
    } catch (Throwable $e) { $rows = []; }
    return $rows;
}

//  Settled outcomes that NOBODY can be credited with, and WHY — because the two
//  gaps need different fixing, and a single lump figure tells nobody what to do:
//    · ambiguous_no_date — the record says the outcome but not when, AND it has
//      changed hands, so there is no moment at which to ask who held it;
//    · no_ledger         — no assignment history and no current owner either:
//      the work belonged to nobody. A legitimate state (M5 invariant I7) that
//      must stay VISIBLE rather than be quietly shared out.
//  Also returned: how many credits rest on the weaker evidence, so the screen
//  can say so instead of implying the ledger proved them.
function rkpi_unattributed(array $opt = []) {
    $out = ['ambiguous_no_date' => 0, 'no_ledger' => 0, 'total' => 0, 'from_current_field' => 0];
    foreach (rkpi_settled_rows($opt) as $c) {
        $at = trim((string) ($c['decided_at'] ?? ''));
        [$uid, $basis] = rkpi_owner_basis('CAND_RECRUITER', (int) $c['id'], $at !== '' ? $at : null);
        if ($basis === 'ambiguous_no_date') { $out['ambiguous_no_date']++; continue; }
        if ($basis === 'no_ledger' || $uid === null) { $out['no_ledger']++; continue; }
        if ($basis === 'current_no_history') $out['from_current_field']++;
    }
    $out['total'] = $out['ambiguous_no_date'] + $out['no_ledger'];
    return $out;
}

// ---------------------------------------------------------------------------
//  STAGE PERFORMANCE (§7)
//
//  How long people actually sit at each step, read from the completed ledger.
//  Three rules that keep the number honest:
//    · a REVERT is not a transition and is never timed as one;
//    · a SWITCH of workflow is not a transition either;
//    · the two ladders (configured pipeline, legacy stage) are never averaged
//      together — a caller asks for one track and gets that track.
//  Rows written before Phase 5 carry no code and are reported as `uncoded`
//  rather than guessed at from their display text.
// ---------------------------------------------------------------------------
function rkpi_stage_durations($candidateId, $track = 'PIPELINE', $basis = 'calendar', $officeId = null) {
    $hist = rkpi_stage_history($candidateId);
    $out = ['track' => $track, 'basis' => $basis, 'steps' => [], 'uncoded' => 0, 'reverted' => 0];
    $prev = null;
    foreach ($hist as $h) {
        $kind = strtoupper((string) ($h['event_kind'] ?? ''));
        if ($kind === 'REVERT') { $out['reverted']++; $prev = null; continue; }
        if ($kind === 'SWITCH') { $prev = null; continue; }
        if (strtoupper((string) ($h['track'] ?? '')) !== strtoupper((string) $track)) {
            if ((string) ($h['to_code'] ?? '') === '') $out['uncoded']++;
            continue;
        }
        $code = (string) ($h['to_code'] ?? '');
        if ($code === '') { $out['uncoded']++; continue; }
        if ($prev !== null) {
            $d = rkpi_age($prev['at'], (string) $h['created_at'], $basis, $officeId);
            if ($d !== null) $out['steps'][] = ['code' => $prev['code'], 'label' => $prev['label'], 'days' => $d];
        }
        $prev = ['code' => $code, 'label' => (string) ($h['to_stage'] ?? $code), 'at' => (string) $h['created_at']];
    }
    //  The step the candidate is sitting in RIGHT NOW is open, not closed, and is
    //  reported separately. Counting it as a completed step would make every
    //  average drift downwards the moment somebody stalls.
    if ($prev !== null) {
        $d = rkpi_age($prev['at'], null, $basis, $officeId);
        $out['current'] = ['code' => $prev['code'], 'label' => $prev['label'], 'days' => $d, 'since' => $prev['at']];
    }
    return $out;
}

//  A configured stage's own SLA, which recruitpipe has carried since Phase 2 —
//  read, never re-invented. Returns null when the stage sets none, because "no
//  SLA configured" is not "zero days allowed".
function rkpi_stage_sla_days($stageId) {
    if (!function_exists('recruitpipe_migrate')) return null;
    try { $r = ops_one("SELECT sla_days FROM recruit_stages WHERE id=?", [(int) $stageId]); }
    catch (Throwable $e) { return null; }
    if (!$r) return null;
    $n = (int) ($r['sla_days'] ?? 0);
    return $n > 0 ? $n : null;
}

// ---------------------------------------------------------------------------
//  THE METRIC REGISTRY (§3)
//
//  Recruitment joins the analytics layer the rest of the product already uses —
//  it does not get one of its own. TAPI already carries office/SBU scoping,
//  period filtering, units, aggregation, a documented `method` per metric and
//  the NO-DATA convention, and it explicitly does not recompute what the domain
//  modules compute. Every resolver below therefore calls THIS file's
//  authoritative functions and nothing else.
//
//  Entitlement (§15) is TAPI's existing rule, not a new one: a metric's `source`
//  names a table, TAPI_SOURCE_MODULES maps that to an access module, and an
//  unknown lineage is WITHHELD rather than published. Recruitment's lineage is
//  registered there against `hiring`, so a company without People & hiring sees
//  NO DATA — not a zero it might act on.
// ---------------------------------------------------------------------------
function rkpi_metrics() {
    //  Demand figures answer "as things stand", so they are scoped but NOT
    //  period-filtered on the requirement's creation date — a requirement raised
    //  in March is still open demand in June, and dropping it from June's board
    //  would hide live work. Period applies to things that HAPPEN: joinings.
    $demand = function ($key) {
        return function ($ctx) use ($key) {
            $d = rkpi_demand();
            return (int) ($d[$key] ?? 0);
        };
    };
    return [
        'hiring.requisitions.live' => ['label'=>'Live requirements','unit'=>'count','agg'=>'count','source'=>'recruit/requisitions',
            'method'=>'COUNT(requisitions) in RASG_LIVE_REQ, office+SBU scoped', 'resolve'=>$demand('requisitions')],
        'hiring.demand.authorised' => ['label'=>'Approved headcount','unit'=>'count','agg'=>'sum','source'=>'recruit/requisitions',
            'method'=>'SUM per requirement of (quantity − cancelled vacancies) — M3/M4 authorised ceiling', 'resolve'=>$demand('authorised')],
        'hiring.demand.filled' => ['label'=>'Positions filled','unit'=>'count','agg'=>'sum','source'=>'recruit/candidates',
            'method'=>'SUM per requirement of candidates at REQF_FILLED_STAGES, clamped to the quantity', 'resolve'=>$demand('filled')],
        'hiring.demand.open' => ['label'=>'Open positions','unit'=>'count','agg'=>'sum','source'=>'recruit/requisitions',
            'method'=>'SUM per requirement of max(0, authorised − filled) — never netted across requirements', 'resolve'=>$demand('remaining')],
        'hiring.demand.allocated' => ['label'=>'Promised to sources','unit'=>'count','agg'=>'sum','source'=>'recruit/requisitions',
            'method'=>'SUM of allocated_qty on LIVE allocations (Phase 4)', 'resolve'=>$demand('allocated')],
        'hiring.demand.unallocated' => ['label'=>'Not yet sourced','unit'=>'count','agg'=>'sum','source'=>'recruit/requisitions',
            'method'=>'SUM per requirement of max(0, authorised − allocated − direct arrivals)', 'resolve'=>$demand('unallocated')],
        'hiring.pipeline.active' => ['label'=>'Candidates in process','unit'=>'count','agg'=>'count','source'=>'recruit/candidates',
            'method'=>'COUNT(candidates) at REQF_ACTIVE_STAGES on live requirements', 'resolve'=>$demand('in_progress')],
        'hiring.hires' => ['label'=>'People joined','unit'=>'count','agg'=>'count','source'=>'recruit/candidates',
            'method'=>'COUNT(candidates) at REQF_FILLED_STAGES whose joining was decided in the period',
            'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('r.office_id', 'r.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.decided_at,''), c.created_at),1,10)", $ctx);
                $fill = rkpi_in(rkpi_filled_stages());
                try {
                    return (int) ops_val("SELECT COUNT(*) FROM candidates c
                                          LEFT JOIN requisitions r ON r.id=c.requisition_id
                                          WHERE c.stage IN ($fill) AND $sw AND $pw", array_merge($sa, $pa));
                } catch (Throwable $e) { return null; }
            }],
        'hiring.tth_avg_days' => ['label'=>'Average time to hire','unit'=>'days','agg'=>'avg','source'=>'recruit/candidates',
            'method'=>'AVG(joining decided − CV received) in calendar days, over joinings decided in the period',
            'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('r.office_id', 'r.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.decided_at,''), c.created_at),1,10)", $ctx);
                $fill = rkpi_in(rkpi_filled_stages());
                try {
                    $rows = ops_all("SELECT c.cv_received_date, c.created_at, c.decided_at FROM candidates c
                                     LEFT JOIN requisitions r ON r.id=c.requisition_id
                                     WHERE c.stage IN ($fill) AND COALESCE(c.decided_at,'')<>'' AND $sw AND $pw",
                                     array_merge($sa, $pa));
                } catch (Throwable $e) { return null; }
                if (!$rows) return null;                      // NO DATA — not zero
                $sum = 0; $n = 0;
                foreach ($rows as $r) {
                    $from = trim((string) ($r['cv_received_date'] ?? '')) ?: trim((string) ($r['created_at'] ?? ''));
                    $d = rkpi_age($from, (string) $r['decided_at'], 'calendar');
                    if ($d !== null && $d >= 0) { $sum += $d; $n++; }
                }
                return $n ? round($sum / $n, 1) : null;
            }],
        'hiring.approval.overdue' => ['label'=>'Approvals overdue','unit'=>'count','agg'=>'count','source'=>'recruit/requisitions',
            'method'=>'appr_sla_summary().overdue — the approval engine owns this, it is not recounted here',
            'resolve'=>function ($ctx) {
                if (!function_exists('appr_sla_summary')) return null;
                $s = appr_sla_summary();
                return isset($s['overdue']) ? (int) $s['overdue'] : null;
            }],
        'hiring.late_vs_target' => ['label'=>'Requirements past their needed-by date','unit'=>'count','agg'=>'count','source'=>'recruit/requisitions',
            'method'=>'COUNT(live requirements) whose hiring request set a needed-by date now passed; requirements WITHOUT a target date are excluded, never counted as on time',
            'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('r.office_id', 'r.sbu');
                $live = rkpi_in(rkpi_live_req());
                try {
                    $rows = ops_all("SELECT r.id, r.closed_at, r.hiring_request_id FROM requisitions r
                                     WHERE $sw AND r.status IN ($live) AND COALESCE(r.hiring_request_id,0)<>0", $sa);
                } catch (Throwable $e) { return null; }
                if (!$rows) return null;                      // NO DATA — nothing carries a target
                $n = 0; $seen = 0;
                foreach ($rows as $r) {
                    $t = rkpi_target($r);
                    if ($t['state'] === 'NO_TARGET') continue;
                    $seen++;
                    if ($t['state'] === 'LATE') $n++;
                }
                return $seen ? $n : null;
            }],
    ];
}
