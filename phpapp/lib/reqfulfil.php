<?php
// ============================================================================
//  EXAACT — REQUISITION FULFILMENT  (Phase 2 · M3)
//
//  Ten vacancies must behave as ten vacancies.
//
//  The defect this replaces: the hire action set `status='HIRED'` on the
//  requisition the moment ONE candidate was taken on, whatever the quantity. A
//  request for five people reached a terminal status after the first, and
//  `hired_inspector_id` — a single column overwritten by every hire — named only
//  the most recent person.
//
//  WHAT ALREADY EXISTED, and is therefore reused rather than rebuilt:
//    · `requisitions.quantity`        how many were asked for
//    · `candidates.requisition_id`    which requirement a person is against
//    · `candidates.stage`             where that person got to
//    · `candidates.inspector_id`      set when they actually joined the workforce
//
//  Every individual hire was ALREADY recorded, one candidate row each. Only the
//  requisition-level summary was wrong. So M3 adds no candidate table and no
//  hire table — it derives the summary from the records that already exist.
//
//  The one thing that genuinely could not be represented is a vacancy CANCELLED
//  without anybody occupying it ("we needed 10, hired 6, dropped the other 4").
//  No candidate row can stand for that, so it is a count on the requisition.
//
//  THREE DIMENSIONS, kept apart — they are not the same question:
//    Pipeline stage       where a CANDIDATE got to        (Interview round 2)
//    Fulfilment           what a SEAT came to             (filled / open / cancelled)
//    Requisition status   what the WHOLE REQUIREMENT is   (partially filled)
// ============================================================================

// Stages that mean a seat is taken. Matches what the Recruitment Command Centre
// has always counted as `filled`, so no existing number changes meaning.
const REQF_FILLED_STAGES = ['ACCEPTED'];
// Stages where somebody is still in play — not filled, but not lost either.
const REQF_ACTIVE_STAGES = ['RECEIVED', 'SUBMITTED', 'SHORTLISTED', 'INTERVIEW', 'OFFERED', 'HOLD'];
// Stages that came to nothing.
const REQF_LOST_STAGES   = ['REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'];

// Statuses a person has deliberately set, which counting must never overrule.
const REQF_MANUAL_STATUSES = ['CLOSED', 'CANCELLED', 'DRAFT'];

function reqf_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    // A workspace that has already customised its requisition status list needs
    // the partly-filled state too, or a partially filled requirement would show
    // a code the list cannot name. Additive; an existing list is not reset.
    if (function_exists('lk_type') && function_exists('lk_ensure_value')) {
        try { if (lk_type('requisition_status')) lk_ensure_value('requisition_status', 'PARTIALLY_FILLED', 'Partly filled (still hiring)'); }
        catch (Throwable $e) {}
    }
    if (!function_exists('ensure_column')) return;
    foreach ([
        // Vacancies given up on, with nobody in them. The one thing a candidate
        // row cannot express.
        ['cancelled_qty',   'INT DEFAULT 0'],
        ['cancel_reason',   "VARCHAR(255) DEFAULT ''"],
        // An explicit closure, so "we stopped looking" is distinguishable from
        // "everybody joined".
        ['closed_at',       "VARCHAR(30) DEFAULT ''"],
        ['closed_by',       "VARCHAR(150) DEFAULT ''"],
        ['closure_reason',  "VARCHAR(255) DEFAULT ''"],
    ] as $c) { try { ensure_column('requisitions', $c[0], $c[1]); } catch (Throwable $e) {} }
}

function reqf_row($req) {
    if (is_array($req)) return $req;
    $id = (int) $req; if ($id <= 0) return null;
    try { return ops_one("SELECT * FROM requisitions WHERE id=?", [$id]) ?: null; }
    catch (Throwable $e) { return null; }
}

// How many were asked for. A requisition with nothing recorded is one person —
// the same assumption the Command Centre has always made.
function reqf_requested($req) {
    $r = reqf_row($req); if (!$r) return 0;
    return max(1, (int) ($r['quantity'] ?? 1));
}

// ---------------------------------------------------------------------------
//  The counts. One query, four numbers, and the arithmetic stated once.
// ---------------------------------------------------------------------------
// Deliberately NOT cached. An earlier version memoised this for the life of a
// request, to save a detail screen asking for the same numbers three times. The
// saving measured 0.11ms per call and there is no N+1 anywhere — the requisition
// list does not use it — while the cache introduced a far worse failure: any
// write that did not remember to drop it served stale counts. Correct numbers
// matter more than a tenth of a millisecond.
function reqf_counts($req) {
    reqf_migrate();
    $r = reqf_row($req);
    $out = ['requested' => 0, 'filled' => 0, 'joined' => 0, 'in_progress' => 0,
            'lost' => 0, 'cancelled' => 0, 'remaining' => 0, 'id' => 0];
    if (!$r) return $out;
    $id = (int) $r['id'];
    $out['id'] = $id;
    $out['requested'] = reqf_requested($r);
    // Clamped to what was asked for: the quantity can be edited down after some
    // vacancies were cancelled, and "2 requested, 6 cancelled" is not a number
    // anybody can act on.
    $out['cancelled'] = min($out['requested'], max(0, (int) ($r['cancelled_qty'] ?? 0)));

    $ph = implode(',', array_fill(0, count(REQF_FILLED_STAGES), '?'));
    $pa = implode(',', array_fill(0, count(REQF_ACTIVE_STAGES), '?'));
    $pl = implode(',', array_fill(0, count(REQF_LOST_STAGES), '?'));
    try {
        $out['filled']      = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ($ph)",
                                            array_merge([$id], REQF_FILLED_STAGES));
        $out['joined']      = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ($ph) AND inspector_id IS NOT NULL",
                                            array_merge([$id], REQF_FILLED_STAGES));
        $out['in_progress'] = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ($pa)",
                                            array_merge([$id], REQF_ACTIVE_STAGES));
        $out['lost']        = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ($pl)",
                                            array_merge([$id], REQF_LOST_STAGES));
    } catch (Throwable $e) { /* a database without candidates yet */ }

    // Requested − filled − cancelled. Someone still in the pipeline has NOT
    // filled a seat, so they never reduce what is remaining — §15: a person
    // selected but not yet joined must not vanish from the requirement.
    $out['remaining'] = max(0, $out['requested'] - $out['filled'] - $out['cancelled']);
    return $out;
}

// The status the counts imply. Returns null when counting has no opinion —
// an explicit decision by a person always wins.
function reqf_derive_status($req) {
    $r = reqf_row($req); if (!$r) return null;
    $cur = strtoupper(trim((string) ($r['status'] ?? '')));
    if (in_array($cur, REQF_MANUAL_STATUSES, true)) return null;   // somebody decided; leave it
    $c = reqf_counts($r);
    if ($c['requested'] <= 0) return null;
    if ($c['filled'] + $c['cancelled'] >= $c['requested']) return 'HIRED';       // existing status, label "Hired (filled)"
    if ($c['filled'] > 0)                                  return 'PARTIALLY_FILLED';
    // Nothing filled. Leave an in-flight status (OPEN / PROPOSED / OFFERED) alone —
    // but a requisition that counting itself put into HIRED or PARTIALLY_FILLED has
    // to be able to come back when the hires behind it are reversed, or it would
    // sit for ever saying "partly filled" with nobody in it.
    if (in_array($cur, ['HIRED', 'PARTIALLY_FILLED'], true)) return 'OPEN';
    return null;
}

// Recompute and persist. Called wherever a fulfilment changes, so the status is
// a consequence of the records rather than something set by hand at one moment.
function reqf_sync($req) {
    reqf_migrate();
    $r = reqf_row($req); if (!$r) return null;
    $want = reqf_derive_status($r);
    if ($want === null) return (string) ($r['status'] ?? '');
    if (strtoupper((string) ($r['status'] ?? '')) === $want) return $want;
    $c = reqf_counts($r);
    try {
        db()->prepare("UPDATE requisitions SET status=? WHERE id=?")->execute([$want, (int) $r['id']]);
        // Same dead call as the hiring-request layer carried: activity_log()
        // does not exist, so this audit never happened (M1 finding G).
        if (function_exists('act_log'))
            act_log('REQUISITION', (int) $r['id'], 'SYSTEM',
                'Status now ' . $want . ' (' . $c['filled'] . ' of ' . $c['requested'] . ' filled)');
    } catch (Throwable $e) {}
    return $want;
}

// Cancel some of the vacancies nobody filled (§17). Never pretends they were
// hired, and can never cancel more than are actually open.
function reqf_cancel($req, $qty, $reason = '') {
    reqf_migrate();
    $r = reqf_row($req); if (!$r) return [false, 'That requisition no longer exists.'];
    $qty = (int) $qty;
    if ($qty <= 0) return [false, 'Enter how many vacancies to cancel.'];
    $c = reqf_counts($r);
    if ($qty > $c['remaining'])
        return [false, 'Only ' . $c['remaining'] . ' vacanc' . ($c['remaining'] === 1 ? 'y is' : 'ies are') . ' still open.'];
    try {
        db()->prepare("UPDATE requisitions SET cancelled_qty=?, cancel_reason=? WHERE id=?")
            ->execute([$c['cancelled'] + $qty, trim((string) $reason), (int) $r['id']]);
    } catch (Throwable $e) { return [false, 'That could not be saved.']; }
    reqf_sync((int) $r['id']);
    return [true, $qty . ' vacanc' . ($qty === 1 ? 'y' : 'ies') . ' cancelled.'];
}

// Everyone recorded against this requirement, newest first — the individual
// fulfilments, which are the candidate rows themselves.
function reqf_people($req) {
    $r = reqf_row($req); if (!$r) return [];
    try {
        return ops_all("SELECT id, cand_code, first_name, middle_name, last_name, stage, inspector_id, created_at
                        FROM candidates WHERE requisition_id=? ORDER BY id DESC", [(int) $r['id']]);
    } catch (Throwable $e) { return []; }
}

// A plain-language summary for a screen: "10 requested — 3 filled — 4 remaining".
function reqf_summary_text($req) {
    $c = reqf_counts($req);
    if ($c['requested'] <= 0) return '';
    $bits = [$c['requested'] . ' requested', $c['filled'] . ' filled'];
    if ($c['in_progress'] > 0) $bits[] = $c['in_progress'] . ' in progress';
    if ($c['cancelled'] > 0)   $bits[] = $c['cancelled'] . ' cancelled';
    $bits[] = $c['remaining'] . ' remaining';
    return implode(' — ', $bits);
}


// ---- Route: give up on the vacancies nobody filled ------------------------
function ops_requisition_cancel_vacancies($route, $method) {
    if (function_exists('req_scope_gate')) req_scope_gate();   // M14 — branch scope, before anything reads an id
    ops_require(function_exists('is_coordinator_level') && is_coordinator_level(),
                'Only coordinators / managers can close vacancies.');
    $id = (int) ($_GET['id'] ?? 0);
    $req = reqf_row($id);
    if (!$req) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        [$ok, $msg] = reqf_cancel($id, (int) ($_POST['qty'] ?? 0), (string) ($_POST['reason'] ?? ''));
        flash($msg, $ok ? 'success' : 'error');
    }
    redirect('/requisition?id=' . $id);
    return true;
}
