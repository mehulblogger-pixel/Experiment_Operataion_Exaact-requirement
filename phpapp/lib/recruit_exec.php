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

//  AN ID IS A NUMBER OR IT IS NOTHING.
//
//  The M5 adversarial audit found that PHP's cast turns a non-empty array into
//  the integer 1 and a word into 0, so an identity could be decided by a type
//  conversion before any control ran. That lesson was learned in the ownership
//  door — and then not applied to the gate written after it. An adversarial
//  probe posted an array as the REQUIREMENT id: it became requisition #1, which
//  was live, and the gate answered "allowed" for a requirement nobody had named.
//
//  The rule is the same one, and it is asked of the SAME implementation
//  (rasg_person_id) so the two cannot drift: nothing, a run of digits, or a
//  positive integer. Anything else is invalid, and invalid fails closed.
//  DELIBERATELY STRICTER THAN THE OWNERSHIP DOOR, and this is the reason:
//  there, "nothing" means UNASSIGN, a legitimate choice, so a value that reduces
//  to nothing is accepted. Here "nothing" means THERE IS NO REQUIREMENT — the
//  ADR-001 direct path — and that ANSWER IS "ALLOW". A value that quietly
//  reduces to nothing would therefore be a way of being allowed everything. So
//  only a genuinely empty value counts as "no record"; a negative number, a
//  word, an array or anything else is INVALID, and invalid fails closed.
function rexec_id($v, &$ok) {
    $ok = true;
    if ($v === null || $v === '' || $v === 0 || $v === '0') return null;   // genuinely no record
    if (is_int($v)) { if ($v > 0) return $v; $ok = false; return null; }   // -5 is not "nothing"
    if (is_string($v) && ctype_digit($v)) {                                 // "007" is the number 7
        $n = (int) $v; if ($n > 0) return $n; $ok = false; return null;
    }
    $ok = false;
    return null;
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
    //  Validated BEFORE it is used to look anything up — a malformed id must not
    //  become a different requirement's answer.
    $rq = rexec_id($requisitionId, $idOk);
    if (!$idOk) return 'That requirement could not be identified, so recruitment cannot continue against it.';
    $rq = (int) $rq;
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
        //  Passed through WITHOUT a cast: rexec_seats() validates it, and casting
        //  here would hand it a number that had already lost the evidence of being
        //  malformed — the helper fixed, the caller not, which is how the same
        //  defect survives its own repair.
        $seats = rexec_seats($rq, $candidateId);
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
    $out = ['requested' => 0, 'filled' => 0, 'cancelled' => 0, 'remaining' => 0];
    $rq = rexec_id($requisitionId, $idOk);
    if (!$idOk) return $out;                       // an unidentifiable requirement has no seats
    $rq = (int) $rq;
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
    //
    //  This is the one place where a bad value would CREATE capacity rather than
    //  refuse it, so it is the one that matters most: an adversarial probe passed
    //  an array here, it became candidate #1, and a requirement with no seats left
    //  reported one free. A value that is not an id releases nothing.
    $except = rexec_id($exceptCandidateId, $exOk);
    $except = $exOk ? (int) $except : 0;
    if ($except > 0) {
        try {
            $ph = implode(',', array_fill(0, count(rexec_filled_stages()), '?'));
            $already = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=? AND requisition_id=? AND stage IN ($ph)",
                                     array_merge([$except, $rq], rexec_filled_stages()));
            if ($already > 0) $out['filled'] = max(0, $out['filled'] - 1);
        } catch (Throwable $e) {}
    }

    //  One computation, once, after every adjustment.
    $out['remaining'] = max(0, $out['requested'] - $out['cancelled'] - $out['filled']);
    return $out;
}

//  WHICH ACTION A SAVE IS REALLY PERFORMING.
//
//  An adversarial probe moved a candidate who was ALREADY JOINED on one
//  requirement onto another that was already full. The edit path asked the gate
//  with ADVANCE — which does not look at seats, because advancing does not take
//  one — and the target ended up holding two people against one approved seat.
//
//  Moving somebody who already occupies a seat onto a different requirement is
//  not an advance. It is a JOINING on the requirement they are arriving at, and
//  it has to be asked as one.
function rexec_move_action($cand, $destinationRequisitionId) {
    if (!is_array($cand)) return 'ADVANCE';
    $here = (int) ($cand['requisition_id'] ?? 0);
    $dest = (int) $destinationRequisitionId;
    if ($dest <= 0 || $dest === $here) return 'ADVANCE';                 // not a move
    return in_array(strtoupper((string) ($cand['stage'] ?? '')), rexec_filled_stages(), true)
        ? 'JOIN' : 'ADVANCE';
}

//  THE COMPENSATOR FOR A MOVE. The gate above is a check, and check-then-write is
//  not atomic, so the destination can fill between the question and the answer.
//  This runs straight after the write: if this candidate is now sitting in a seat
//  the destination does not have, they are put back where they came from and the
//  attempt is audited. Nothing is invented — the move simply does not stand.
function rexec_move_enforce_after_write($candidateId, $priorRequisitionId) {
    $id = rexec_id($candidateId, $ok); if (!$ok) return '';
    $id = (int) $id; if ($id <= 0) return '';
    try { $c = ops_one("SELECT id, requisition_id, stage FROM candidates WHERE id=?", [$id]); }
    catch (Throwable $e) { return ''; }
    if (!$c) return '';
    $now = (int) ($c['requisition_id'] ?? 0);
    $was = (int) $priorRequisitionId;
    if ($now <= 0 || $now === $was) return '';                            // no move happened
    if (!in_array(strtoupper((string) $c['stage']), rexec_filled_stages(), true)) return '';   // no seat taken
    //  DELIBERATELY *NOT* THE RANK RULE THE JOINING COMPENSATOR USES, and the
    //  difference matters: a joining is several people arriving at the SAME
    //  requirement at the same moment, where ranking them is the fair way to
    //  decide who keeps the seat. A move is one person arriving where others are
    //  already established — and ranking would let the arriving person, whose
    //  decision is older because it was made on a different requirement, DISPLACE
    //  somebody who was already sitting there. A test caught exactly that.
    //
    //  So the question here is the plain one: was there a seat for them, not
    //  counting themselves? If not, the move does not stand.
    if (rexec_seats($now, $id)['remaining'] >= 1) return '';              // there was a seat

    try { db()->prepare("UPDATE candidates SET requisition_id=? WHERE id=?")->execute([$was ?: null, $id]); }
    catch (Throwable $e) { return ''; }
    if (function_exists('reqf_sync')) { foreach (array_unique(array_filter([$now, $was])) as $r) { try { reqf_sync($r); } catch (Throwable $e) {} } }
    if (function_exists('act_log'))
        act_log('CANDIDATE', $id, 'NOTE', 'Move reverted — the requirement had no approved seat',
            ['body' => 'This person already held a seat, so moving them to requirement #' . $now
                     . ' would have taken a seat it does not have. They remain on requirement #' . $was . '.']);
    return 'That requirement has no approved seat left, so this person was not moved onto it.';
}

//  The same question asked about a CANDIDATE, which is how most callers hold it.
function rexec_cand_block_reason($candidateId, $action = 'ADVANCE') {
    $id = rexec_id($candidateId, $cOk);
    if (!$cOk) return 'That candidate could not be identified.';
    $id = (int) $id; if ($id <= 0) return '';
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
function rexec_join_enforce_after_write($candidateId, $priorStage, $priorDecidedAt = '') {
    $id = rexec_id($candidateId, $cOk);
    if (!$cOk) return '';                          // nothing identifiable was written
    $id = (int) $id; if ($id <= 0) return '';
    try { $c = ops_one("SELECT id, requisition_id, stage FROM candidates WHERE id=?", [$id]); }
    catch (Throwable $e) { return ''; }
    if (!$c) return '';
    $rq = (int) ($c['requisition_id'] ?? 0); if ($rq <= 0) return '';
    if (!in_array(strtoupper((string) $c['stage']), rexec_filled_stages(), true)) return '';   // no seat taken

    //  WAS THERE A SEAT FOR THEM, NOT COUNTING THEMSELVES?
    //
    //  This is deliberately the simple question, and it is the third answer I
    //  tried. Two attempts to make it "fairer" under a dead heat — ranking the
    //  seat-holders so that one of two simultaneous claims survives — each
    //  introduced a worse defect than the one they fixed, and a test caught each:
    //
    //    · ranking by decision time let a person ARRIVING from another
    //      requirement, whose decision was older, displace somebody already
    //      established in the seat;
    //    · sorting missing decision times first let an unstamped late claim
    //      outrank properly stamped earlier ones; sorting them last inverted it.
    //
    //  Displacing an established holder is worse than refusing a contested claim,
    //  so the plain question stands. Its cost is stated rather than hidden: under
    //  a genuine dead heat both claimants may be refused and the seat is left for
    //  whoever tries next. It is never over-filled and nobody is ever displaced —
    //  and M4 recorded the same pessimism, for the same reason, on allocation.
    $seats = rexec_seats($rq, $id);
    if ($seats['remaining'] >= 1) return '';       // there was a seat; nothing to do

    $back = (string) $priorStage !== '' ? (string) $priorStage : 'OFFERED';
    //  …and no decision stamp for a decision that was undone. The stage route
    //  stamps decided_at when somebody is marked as joined; reverting the stage
    //  and leaving the stamp behind says a decision was taken at that moment when
    //  none stands, and "time to hire" is computed from exactly that column.
    //  Restored to what it was, or cleared when the stage returned to is not one
    //  that carries a decision.
    $priorDecided = (string) $priorDecidedAt;
    $keepStamp = in_array(strtoupper($back), ['REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'], true);
    try {
        db()->prepare("UPDATE candidates SET stage=?, decided_at=? WHERE id=?")
            ->execute([$back, $keepStamp ? $priorDecided : '', $id]);
    } catch (Throwable $e) {
        try { db()->prepare("UPDATE candidates SET stage=? WHERE id=?")->execute([$back, $id]); }
        catch (Throwable $e2) { return ''; }
    }
    if (function_exists('reqf_sync')) { try { reqf_sync($rq); } catch (Throwable $e) {} }
    //  PHASE 5 — and say so in the stage ledger. Reverting the stage without
    //  writing here left the ledger ENDING at the joined stage: it claimed a
    //  person had joined who had not, and any hire count read from it
    //  over-reported. Recorded as a REVERT, never as a plain move, so a stage
    //  duration is never computed across an undoing.
    if (function_exists('rkpi_stage_log'))
        rkpi_stage_log($id, (string) $c['stage'], $back, [
            'from_code' => strtoupper((string) $c['stage']), 'to_code' => strtoupper($back), 'track' => 'LEGACY',
            'kind' => 'REVERT', 'remark' => 'Joining reverted — no approved seat remained']);
    if (function_exists('act_log'))
        act_log('CANDIDATE', $id, 'NOTE', 'Joining reverted — no approved seat remained',
            ['body' => 'Another joining took the last approved position on requirement #' . $rq
                     . ' first. This candidate was returned to ' . $back . '.']);
    return 'All approved positions on this requirement were filled while this was being saved, '
         . 'so the joining was not recorded.';
}
