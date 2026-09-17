<?php
// ============================================================================
//  PHASE 3 · M6 — THE INTEGRATED EXECUTION GATE
//
//  M6 builds no new engine. It asks ONE question, composed from the authorities
//  that M1–M5 already established, at every point where recruitment SPENDS a
//  requirement:
//
//      M4  — is the hiring request behind this requisition approved, and not
//            awaiting re-approval?      (hreq_req_block_reason)
//      M3  — is the requirement itself still live, and does it still have a
//            seat?                      (reqf_counts / the requisition status)
//
//  The M6 attack found four ways past those authorities, and every one of them
//  was the same thing: a path that never asked.
//
//      · an OFFER could be created, approved, ISSUED and accepted against a
//        requisition whose approval had been invalidated — a commitment made to
//        a person for headcount nobody had approved;
//      · an INTERVIEW could be scheduled on the same blocked requisition;
//      · interviews and offers ran happily on a CANCELLED requirement;
//      · and three people joined a TWO-seat requirement, because the seat
//        ceiling was enforced when the requisition was raised and never again.
//
//  Nothing here re-decides anything. It asks the existing owners of each
//  question and reports the first refusal, in the words the person needs.
// ============================================================================

//  What is about to happen. The gate asks a different (larger) set of questions
//  for a joining than for a screening call, because they cost different things.
const REXEC_ACTIONS = [
    'ADVANCE'   => 'move this candidate forward',
    'INTERVIEW' => 'schedule an interview',
    'OFFER'     => 'make an offer',
    'JOIN'      => 'record a joining',
];

//  Requirement states in which recruitment may still spend the requisition.
//  Taken from the lifecycle M3 established; no status is added or renamed.
const REXEC_LIVE_STATES = ['OPEN', 'PROPOSED', 'OFFERED', 'PARTIALLY_FILLED', 'HIRED'];

//  Stages that mean the person is in a seat. One definition — reqfulfil's, the
//  M3 authority for counting a multi-vacancy requirement.
function rexec_filled_stages() {
    return defined('REQF_FILLED_STAGES') ? REQF_FILLED_STAGES : ['ACCEPTED'];
}

//  THE QUESTION. Returns '' when the action may proceed, or the refusal.
//
//  $requisitionId  the requirement being spent. 0 / null means there is none —
//                  ADR-001's direct path — and there is then no approval and no
//                  ceiling to respect, so the answer is ''. That is deliberate
//                  and is asserted by a test: inventing a refusal for a
//                  candidate nobody raised a requirement for would stop ordinary
//                  work that has always been allowed.
//  $action         one of REXEC_ACTIONS.
//  $candidateId    the person, when one is involved — so that a candidate who
//                  ALREADY holds a seat is not refused the seat they hold.
function rexec_block_reason($requisitionId, $action = 'ADVANCE', $candidateId = 0) {
    $rq = (int) $requisitionId;
    if ($rq <= 0) return '';                       // ADR-001: no requirement, no ceiling
    $action = isset(REXEC_ACTIONS[$action]) ? $action : 'ADVANCE';

    //  1 · THE REQUIREMENT ITSELF. A record that is not there cannot be spent,
    //  and an unreadable state is refused rather than assumed safe.
    try { $r = ops_one("SELECT id, status, quantity FROM requisitions WHERE id=?", [$rq]); }
    catch (Throwable $e) { $r = null; }
    if (!$r) return 'That requirement no longer exists.';
    $st = strtoupper(trim((string) ($r['status'] ?? '')));
    if ($st === '') return 'That requirement has no readable status, so recruitment cannot continue against it.';
    if (!in_array($st, REXEC_LIVE_STATES, true))
        return 'That requirement is ' . strtolower(str_replace('_', ' ', $st))
             . ', so you cannot ' . REXEC_ACTIONS[$action] . ' against it.';

    //  2 · M4's BOUNDARY, asked of M4. Not re-implemented, not second-guessed.
    if (function_exists('hreq_req_block_reason')) {
        $why = (string) hreq_req_block_reason($rq);
        if ($why !== '') return $why;
    }

    //  3 · THE SEAT. Only a joining consumes one. An offer does not: the business
    //  deliberately runs more offers than seats, because offers are declined —
    //  and refusing the fifth offer for four seats would stop normal recruitment.
    //  What must never happen is a person taking a seat that does not exist.
    if ($action === 'JOIN') {
        $seats = rexec_seats($rq, (int) $candidateId);
        if ($seats['remaining'] <= 0)
            return 'All ' . $seats['requested'] . ' approved position'
                 . ($seats['requested'] === 1 ? '' : 's') . ' on this requirement '
                 . ($seats['requested'] === 1 ? 'has' : 'have') . ' already been filled.';
    }
    return '';
}

//  Seats, counted through M3's own counter wherever it is available, so the gate
//  and the requirement screen can never disagree about how full something is.
//
//  $exceptCandidateId — a candidate who is ALREADY counted as filled is not
//  counted against themselves. Without this, re-saving a joined candidate would
//  refuse the seat they are sitting in.
function rexec_seats($requisitionId, $exceptCandidateId = 0) {
    $rq = (int) $requisitionId;
    $out = ['requested' => 0, 'filled' => 0, 'cancelled' => 0, 'remaining' => 0];
    if ($rq <= 0) return $out;

    //  The raw counts, from M3's counter where it exists. Deliberately NOT
    //  clamped here: an over-filled requirement must stay visible as over-filled
    //  long enough for the arithmetic below to see it. Clamping "remaining" to
    //  zero before subtracting the candidate being checked is what let a third
    //  joining look like it had a seat on a two-seat requirement.
    if (function_exists('reqf_counts')) {
        $c = reqf_counts($rq);
        $out['requested'] = (int) ($c['requested'] ?? 0);
        $out['filled']    = (int) ($c['filled'] ?? 0);
        $out['cancelled'] = (int) ($c['cancelled'] ?? 0);
    } else {
        try {
            $out['requested'] = max(0, (int) ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rq]));
            $ph = implode(',', array_fill(0, count(rexec_filled_stages()), '?'));
            $out['filled'] = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ($ph)",
                                           array_merge([$rq], rexec_filled_stages()));
        } catch (Throwable $e) {}
    }

    //  The person being asked about does not compete with themselves: re-saving
    //  somebody who already holds a seat must not refuse them the seat they are
    //  sitting in.
    if ((int) $exceptCandidateId > 0) {
        try {
            $ph = implode(',', array_fill(0, count(rexec_filled_stages()), '?'));
            $already = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=? AND requisition_id=? AND stage IN ($ph)",
                                     array_merge([(int) $exceptCandidateId, $rq], rexec_filled_stages()));
            if ($already > 0) $out['filled'] = max(0, $out['filled'] - 1);
        } catch (Throwable $e) {}
    }

    //  One computation, once, after every adjustment.
    $out['remaining'] = max(0, $out['requested'] - $out['cancelled'] - $out['filled']);
    return $out;
}

//  The same question asked about a CANDIDATE, which is how most callers hold it.
function rexec_cand_block_reason($candidateId, $action = 'ADVANCE') {
    $id = (int) $candidateId; if ($id <= 0) return '';
    try { $c = ops_one("SELECT id, requisition_id FROM candidates WHERE id=?", [$id]); }
    catch (Throwable $e) { return ''; }
    if (!$c) return 'That candidate no longer exists.';
    return rexec_block_reason((int) ($c['requisition_id'] ?? 0), $action, $id);
}

//  THE COMPENSATOR — the same shape M4 used for the headcount ceiling and M5 for
//  ownership, because check-then-write is not atomic and two processes recording
//  a joining at the same moment both pass the check.
//
//  Called immediately after a write that may have taken a seat. If the seat was
//  not there, the write is put back, the attempt is audited, and the caller is
//  told. A compensating revert is the only thing that holds under real
//  concurrency without wrapping every caller in a transaction they do not own.
function rexec_join_enforce_after_write($candidateId, $priorStage) {
    $id = (int) $candidateId; if ($id <= 0) return '';
    try { $c = ops_one("SELECT id, requisition_id, stage FROM candidates WHERE id=?", [$id]); }
    catch (Throwable $e) { return ''; }
    if (!$c) return '';
    $rq = (int) ($c['requisition_id'] ?? 0); if ($rq <= 0) return '';
    if (!in_array(strtoupper((string) $c['stage']), rexec_filled_stages(), true)) return '';   // no seat taken

    //  Seats counted WITHOUT this candidate: "was there a seat for them?"
    $seats = rexec_seats($rq, $id);
    if ($seats['remaining'] >= 1) return '';       // there was; nothing to do

    $back = (string) $priorStage !== '' ? (string) $priorStage : 'OFFERED';
    try { db()->prepare("UPDATE candidates SET stage=? WHERE id=?")->execute([$back, $id]); }
    catch (Throwable $e) { return ''; }
    if (function_exists('reqf_sync')) { try { reqf_sync($rq); } catch (Throwable $e) {} }
    if (function_exists('act_log'))
        act_log('CANDIDATE', $id, 'NOTE', 'Joining reverted — no approved seat remained',
            ['body' => 'Another joining took the last approved position on requirement #' . $rq
                     . ' first. This candidate was returned to ' . $back . '.']);
    return 'All approved positions on this requirement were filled while this was being saved, '
         . 'so the joining was not recorded.';
}
