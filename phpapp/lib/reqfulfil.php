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

//  GATE 1B — WHAT THESE THREE LISTS NOW ARE.
//
//  They used to BE the classification: every consumer compared a candidate's
//  legacy stage value against them. D1/C14 made the configurable pipeline the
//  authority for current state, so classification is now taken from the
//  configured stage KIND (RPIPE_KIND_CLASS in lib/recruitpipe.php).
//
//  These lists survive as the ONE-WAY LEGACY COMPATIBILITY MAP: the translation
//  for candidates who have never been moved on a pipeline and therefore have no
//  kind to ask. They cannot override a pipeline answer, they produce values in
//  the same vocabulary the kinds produce, and migration drains them.
//
//  Do not add a consumer that compares a stage value against these directly.
//  Ask reqf_classify() for one candidate, or reqf_class_expr() for a set.
const REQF_FILLED_STAGES = ['ACCEPTED'];
const REQF_ACTIVE_STAGES = ['RECEIVED', 'SUBMITTED', 'SHORTLISTED', 'INTERVIEW', 'OFFERED', 'HOLD'];
const REQF_LOST_STAGES   = ['REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'];

// ---------------------------------------------------------------------------
//  THE SINGLE CLASSIFICATION CHOKE POINT
//
//  Every fulfilment, health, KPI and dashboard figure resolves through here.
//  There are exactly two renderings, and BOTH are generated from the one
//  mapping in lib/recruitpipe.php:
//
//    reqf_classify()   one candidate, in PHP — for detail screens and logic
//    reqf_class_expr() a set, in SQL — for aggregate reporting queries
//
//  Two renderings rather than one because a per-row PHP call across a KPI query
//  spanning every requisition in the workspace is an N+1 nobody would accept.
//  They are not two definitions: a test asserts they agree for every kind and
//  every legacy value, so drift between them fails the suite.
// ---------------------------------------------------------------------------

// The classification of ONE candidate: 'FILLED' | 'ACTIVE' | 'LOST' | ''.
// '' means no classification could be established — genuinely unknown, and a
// caller must never quietly treat it as zero.
function reqf_classify($cand) {
    if (!function_exists('rpipe_current_state')) return '';
    $st = rpipe_current_state($cand);
    return $st ? (string) $st['class'] : '';
}

// The JOINs that reqf_class_expr() needs. `$c` is the candidates alias.
//
// The two ON clauses ARE the Gate 1A guard, expressed in SQL: the candidate's
// locked pipeline must still be active, and the stage must belong to THAT
// pipeline. Without them a stage id left behind by a retired pipeline would be
// read as a live position.
function reqf_class_join($c = 'c') {
    return " LEFT JOIN recruit_pipelines rp_k ON rp_k.id = $c.pipeline_id AND rp_k.active = 1"
         . " LEFT JOIN recruit_stages rs_k ON rs_k.id = $c.pipeline_stage_id"
         . " AND rs_k.pipeline_id = rp_k.id AND rs_k.active = 1";
}

// The SQL expression yielding a candidate's classification. Requires the joins
// above. Built from RPIPE_KIND_CLASS and the legacy map, never hand-written, so
// adding a stage kind cannot leave a reporting query behind.
function reqf_class_expr($c = 'c') {
    $q = fn($v) => "'" . str_replace("'", "''", (string) $v) . "'";
    // Pipeline first — it is the authority.
    $kind = '';
    foreach (RPIPE_KIND_CLASS as $k => $cls) $kind .= " WHEN rs_k.kind = {$q($k)} THEN {$q($cls)}";
    // Then, only for candidates with no resolvable pipeline position, the
    // one-way legacy compatibility map.
    $legacy = '';
    foreach (['FILLED' => REQF_FILLED_STAGES, 'ACTIVE' => REQF_ACTIVE_STAGES, 'LOST' => REQF_LOST_STAGES] as $cls => $vals)
        foreach ($vals as $v) $legacy .= " WHEN $c.stage = {$q($v)} THEN {$q($cls)}";
    return "CASE WHEN rs_k.id IS NOT NULL THEN (CASE$kind ELSE '' END)"
         . " ELSE (CASE$legacy ELSE '' END) END";
}

// The SQL expression yielding a candidate's EFFECTIVE STAGE KIND. Requires the
// same joins. This is what a stage-specific question must ask — "how many offers
// are out" is a question about the offer KIND, not about the string 'OFFERED'.
function reqf_kind_expr($c = 'c') {
    $q = fn($v) => "'" . str_replace("'", "''", (string) $v) . "'";
    $legacy = '';
    foreach (RPIPE_LEGACY_KIND as $v => $k) $legacy .= " WHEN $c.stage = {$q($v)} THEN {$q($k)}";
    return "CASE WHEN rs_k.id IS NOT NULL THEN rs_k.kind ELSE (CASE$legacy ELSE '' END) END";
}

// COUNT of candidates in one classification, for use in a subquery or a HAVING.
function reqf_class_count_sql($class, $where, $c = 'c') {
    return "(SELECT COUNT(*) FROM candidates $c" . reqf_class_join($c)
         . " WHERE ($where) AND " . reqf_class_expr($c) . " = '" . str_replace("'", "''", (string) $class) . "')";
}

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

    //  GATE 1B — counted by CLASSIFICATION, not by legacy stage value.
    //  One expression, generated from the stage-kind mapping, used for all four
    //  numbers so they cannot disagree with each other or with any other screen.
    $J = reqf_class_join('c');
    $E = reqf_class_expr('c');
    try {
        $out['filled']      = (int) ops_val("SELECT COUNT(*) FROM candidates c $J WHERE c.requisition_id=? AND $E='FILLED'", [$id]);
        //  RB-2 — JOINED MEANS JOINED.
        //
        //  This used to count accepted people who had a team record, and call
        //  that "joined". It was never joining: it was paperwork. Worse, now
        //  that every accepted candidate gets a team record (RB-1) it would have
        //  become arithmetically identical to `filled`, so a screen printing it
        //  would report "10 of 10 joined" on the strength of ten acceptances.
        //
        //  It now counts the people somebody has explicitly marked as having
        //  joined. On existing data that is zero, which is the honest answer:
        //  the system genuinely does not know when anybody arrived.
        $out['joined']      = (int) ops_val("SELECT COUNT(*) FROM candidates c $J WHERE c.requisition_id=? AND $E='FILLED' AND COALESCE(c.joined_at,'') <> ''", [$id]);
        $out['in_progress'] = (int) ops_val("SELECT COUNT(*) FROM candidates c $J WHERE c.requisition_id=? AND $E='ACTIVE'", [$id]);
        $out['lost']        = (int) ops_val("SELECT COUNT(*) FROM candidates c $J WHERE c.requisition_id=? AND $E='LOST'", [$id]);
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
