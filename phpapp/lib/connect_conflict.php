<?php
// ============================================================================
//  CONNECT — Resource conflict & availability (Stage 7, part 1)  · additive
//
//  A resource must never be silently committed to overlapping work. This is the
//  marketplace / one-person side of that guard: given a professional and a date
//  window, it reports every clashing commitment and every blocking credential,
//  by CONNECTING the pieces that already exist rather than duplicating them —
//
//    * cx_engagements  — the marketplace bookings that carry real date ranges
//                        (subject_kind professional | inspector | bench).
//    * cx_identity_link — the same person's operational inspector identity.
//    * schedule.php     — inspector_busy_on() : the operations job calendar.
//    * competence.php   — inspector_eligibility() : the lapsed-credential gate.
//
//  Read-only: no new table, no status written, no workflow changed. Every probe
//  is guarded so missing data degrades to "clear", never an error. It ADVISES
//  the shortlist / offer step; it does not itself block a save. (§24 / §37)
// ============================================================================

/** Enumerate the calendar dates in [from,to] inclusive, capped to keep the scan
 *  bounded on a very long engagement. Returns Y-m-d strings. */
function connect_conflict_days($from, $to, $cap = 62) {
    $from = trim((string)$from); $to = trim((string)$to) ?: $from;
    if ($from === '') return [];
    $t0 = strtotime($from); $t1 = strtotime($to);
    if ($t0 === false) return [];
    if ($t1 === false || $t1 < $t0) $t1 = $t0;
    $out = []; $n = 0;
    for ($t = $t0; $t <= $t1 && $n < $cap; $t += 86400, $n++) $out[] = date('Y-m-d', $t);
    return $out;
}

/** Gap-4 — do two shifts occupy the same time of day? '' (full-day / unspecified)
 *  occupies everything, so it clashes with any shift; two NAMED shifts clash only
 *  when equal, so DAY vs NIGHT on the same date is NOT a conflict. */
function connect_conflict_shift_overlap($a, $b) {
    $a = strtoupper(trim((string)$a)); $b = strtoupper(trim((string)$b));
    if ($a === '' || $b === '') return true;
    return $a === $b;
}

/** Overlapping bookings for one engagement subject (professional | inspector |
 *  bench) in [from,to]. status BOOKED/ACTIVE only; open-ended dates handled.
 *  $exceptId excludes a booking being edited. $shift narrows a date overlap to a
 *  time-of-day overlap: with a named shift, a same-date booking on a DIFFERENT
 *  named shift is not returned. '' (the default) preserves the whole-day behaviour. */
function connect_conflict_engagements($subjectKind, $subjectId, $from, $to, $exceptId = 0, $shift = '') {
    $subjectId = (int)$subjectId; if (!$subjectId) return [];
    $from = trim((string)$from); $to = trim((string)$to) ?: $from;
    if ($from === '') return [];
    if (function_exists('connect_engage_migrate')) { try { connect_engage_migrate(); } catch (Throwable $e) {} }
    try {
        $rows = ops_all(
            "SELECT id, requirement_id, subject_name, start_date, end_date, status, poster_name, COALESCE(shift,'') shift
               FROM cx_engagements
              WHERE subject_kind=? AND subject_id=? AND id<>?
                AND status IN ('BOOKED','ACTIVE')
                AND COALESCE(NULLIF(start_date,''),'0000-00-00') <= ?
                AND COALESCE(NULLIF(end_date,''),'9999-12-31')  >= ?
              ORDER BY start_date",
            [(string)$subjectKind, $subjectId, (int)$exceptId, $to, $from]
        ) ?: [];
    } catch (Throwable $e) { return []; }
    if (trim((string)$shift) === '') return $rows;  // a full-day query clashes with everything (legacy behaviour)
    return array_values(array_filter($rows, fn($r) => connect_conflict_shift_overlap($shift, (string)($r['shift'] ?? ''))));
}

/** The professional's linked operational inspector id (0 if none). */
function connect_conflict_inspector_of($professionalId) {
    $professionalId = (int)$professionalId; if (!$professionalId) return 0;
    try { return (int)ops_val("SELECT inspector_id FROM cx_identity_link WHERE professional_id=? AND status='LINKED' AND inspector_id>0 ORDER BY id DESC LIMIT 1", [$professionalId]); }
    catch (Throwable $e) { return 0; }
}

/**
 * The unified verdict for committing a professional to [from,to].
 *   returns [
 *     'status'    => 'CLEAR' | 'CONFLICT' | 'BLOCKED',
 *     'available' => bool,                 // false if any hard block or clash
 *     'conflicts' => [ ['kind','ref','label','from','to'] … ],
 *     'reasons'   => [ ['level'=>'block'|'warn'|'info','text'] … ],
 *   ]
 * BLOCKED = a lapsed mandatory credential (hard). CONFLICT = an overlapping
 * booking or a busy operations day. CLEAR = neither. Never throws.
 */
function connect_conflict_check($professionalId, $from, $to, $ctx = []) {
    $professionalId = (int)$professionalId;
    $from = trim((string)$from); $to = trim((string)$to) ?: $from;
    $conflicts = []; $reasons = []; $status = 'CLEAR';
    $rank = ['CLEAR' => 0, 'CONFLICT' => 1, 'BLOCKED' => 2];
    $bump = function ($s) use (&$status, $rank) { if (($rank[$s] ?? 0) > ($rank[$status] ?? 0)) $status = $s; };
    if (!$professionalId || $from === '') return ['status' => 'CLEAR', 'available' => true, 'conflicts' => [], 'reasons' => []];

    // Gap-4 — the shift being booked (if any); a named shift only clashes with the same shift.
    $shift = (string)($ctx['shift'] ?? '');
    // 1) Marketplace engagement overlaps for the professional themself.
    foreach (connect_conflict_engagements('professional', $professionalId, $from, $to, (int)($ctx['except_engagement_id'] ?? 0), $shift) as $e) {
        $conflicts[] = ['kind' => 'engagement', 'ref' => (int)$e['id'], 'from' => (string)$e['start_date'], 'to' => (string)$e['end_date'],
            'label' => 'Already ' . strtolower((string)$e['status']) . ' with ' . ((string)$e['poster_name'] ?: 'a client') . ' (' . (string)$e['start_date'] . ' → ' . ((string)$e['end_date'] ?: 'open') . ')'];
        $bump('CONFLICT');
    }

    // 2) The same person's operational side, if identity-linked to an inspector.
    $insp = connect_conflict_inspector_of($professionalId);
    if ($insp > 0) {
        // 2a) overlapping engagements booked against the inspector identity
        foreach (connect_conflict_engagements('inspector', $insp, $from, $to, 0, $shift) as $e) {
            $conflicts[] = ['kind' => 'engagement', 'ref' => (int)$e['id'], 'from' => (string)$e['start_date'], 'to' => (string)$e['end_date'],
                'label' => 'Deployment overlap (' . (string)$e['start_date'] . ' → ' . ((string)$e['end_date'] ?: 'open') . ')'];
            $bump('CONFLICT');
        }
        // 2b) busy days on the operations job calendar (reuse schedule.php)
        if (function_exists('inspector_busy_on')) {
            $exceptJob = (int)($ctx['except_job_id'] ?? 0);
            $busy = [];
            foreach (connect_conflict_days($from, $to) as $d) {
                try { $r = inspector_busy_on($insp, $d, $exceptJob); } catch (Throwable $e) { $r = ''; }
                if ($r !== '') { $busy[] = $d; if (count($busy) >= 3) break; }
            }
            if ($busy) { $conflicts[] = ['kind' => 'schedule', 'ref' => $insp, 'from' => $busy[0], 'to' => end($busy), 'label' => 'Already committed on ' . implode(', ', $busy) . (count($busy) >= 3 ? ' …' : '')]; $bump('CONFLICT'); }
        }
        // 2c) lapsed / blocking credentials on the work date (reuse competence.php)
        if (function_exists('inspector_eligibility')) {
            try {
                $elig = inspector_eligibility($insp, array_merge(['on_date' => $from], is_array($ctx) ? $ctx : []));
                if (($elig['status'] ?? '') === 'BLOCKED') { $bump('BLOCKED'); foreach ((array)($elig['reasons'] ?? []) as $r) if (($r['level'] ?? '') === 'block') $reasons[] = ['level' => 'block', 'text' => (string)$r['text']]; }
                elseif (($elig['status'] ?? '') === 'EXPIRING') { foreach ((array)($elig['reasons'] ?? []) as $r) if (($r['level'] ?? '') === 'warn' || ($r['level'] ?? '') === 'expiring') $reasons[] = ['level' => 'warn', 'text' => (string)$r['text']]; }
            } catch (Throwable $e) {}
        }
    }

    // Human summary reasons for the conflict clashes.
    foreach ($conflicts as $c) $reasons[] = ['level' => $status === 'BLOCKED' ? 'warn' : 'warn', 'text' => $c['label']];
    return ['status' => $status, 'available' => $status === 'CLEAR', 'conflicts' => $conflicts, 'reasons' => $reasons];
}

// ---------------------------------------------------------------------------
//  Availability status model (§24). A single DERIVED status for a professional
//  on a date — never stored, always computed from the records that already
//  exist (like person_state). The vocabulary is the master-prompt's; the ones
//  with a data source are derived, the rest remain available for later sources.
// ---------------------------------------------------------------------------

/** The status vocabulary → [label, tone]. tone: ok | info | warn | bad. */
function connect_availability_states() {
    return [
        'AVAILABLE'        => ['Available', 'ok'],
        'TENTATIVELY_HELD' => ['Tentatively held', 'warn'],
        'PROPOSED'         => ['Proposed', 'warn'],
        'SHORTLISTED'      => ['Shortlisted', 'warn'],
        'BOOKED'           => ['Booked', 'info'],
        'ASSIGNED'         => ['Assigned', 'info'],
        'IN_PROGRESS'      => ['In progress', 'info'],
        'UNAVAILABLE'      => ['Unavailable', 'bad'],
        'ON_LEAVE'         => ['On leave', 'bad'],
        'RESTRICTED'       => ['Restricted', 'bad'],
    ];
}

/**
 * The professional's derived availability status on a date (default today).
 * First match wins, most-committing / least-available first. Returns
 * ['code','label','tone','detail']. Never throws.
 */
function connect_availability_status($professionalId, $onDate = '') {
    $professionalId = (int)$professionalId;
    $onDate = substr(trim((string)$onDate), 0, 10) ?: date('Y-m-d');
    $states = connect_availability_states();
    $mk = function ($code, $detail = '') use ($states) {
        [$label, $tone] = $states[$code] ?? ['Available', 'ok'];
        return ['code' => $code, 'label' => $label, 'tone' => $tone, 'detail' => $detail];
    };
    if (!$professionalId) return $mk('AVAILABLE');
    $val = function ($sql, $args = []) { try { return ops_val($sql, $args); } catch (Throwable $e) { return null; } };

    if (function_exists('connect_engage_migrate')) { try { connect_engage_migrate(); } catch (Throwable $e) {} }
    $insp = connect_conflict_inspector_of($professionalId);

    // 1) A date-bound engagement covering today — professional or linked inspector.
    $subj = $insp > 0 ? "(subject_kind='professional' AND subject_id=$professionalId) OR (subject_kind='inspector' AND subject_id=$insp)"
                      : "subject_kind='professional' AND subject_id=$professionalId";
    $engStatus = $val("SELECT status FROM cx_engagements WHERE ($subj)
                         AND status IN ('ACTIVE','BOOKED')
                         AND COALESCE(NULLIF(start_date,''),'0000-00-00') <= ?
                         AND COALESCE(NULLIF(end_date,''),'9999-12-31')  >= ?
                       ORDER BY CASE status WHEN 'ACTIVE' THEN 0 ELSE 1 END LIMIT 1", [$onDate, $onDate]);
    if ($engStatus === 'ACTIVE') return $mk('IN_PROGRESS', 'active engagement');
    if ($engStatus === 'BOOKED') return $mk('BOOKED', 'booked engagement');

    // 2) The operational calendar (leave vs a job today), via the linked inspector.
    if ($insp > 0 && function_exists('inspector_busy_on')) {
        try {
            $r = inspector_busy_on($insp, $onDate);
            if ($r !== '') {
                if (preg_match('/leave|training|off|holiday|sick/i', $r)) return $mk('ON_LEAVE', $r);
                return $mk('ASSIGNED', $r);
            }
        } catch (Throwable $e) {}
    }

    // 3) The professional's own availability flag.
    $flag = strtoupper((string)$val("SELECT availability FROM cx_professionals WHERE id=?", [$professionalId]));
    if ($flag === 'OFF') return $mk('UNAVAILABLE', 'marked unavailable');
    if ($flag === 'BUSY' || $flag === 'ALLOCATED') return $mk('BOOKED', 'marked busy');

    // 4) Pipeline signals — the strongest live application state.
    $appStatus = $val("SELECT status FROM cx_applications WHERE applicant_professional_id=?
                         AND status IN ('SHORTLISTED','OFFERED')
                       ORDER BY CASE status WHEN 'OFFERED' THEN 0 ELSE 1 END LIMIT 1", [$professionalId]);
    if ($appStatus === 'OFFERED')     return $mk('PROPOSED', 'has a live offer');
    if ($appStatus === 'SHORTLISTED') return $mk('SHORTLISTED', 'shortlisted somewhere');

    return $mk('AVAILABLE');
}

/** A compact one-line badge for a verdict — for a shortlist row or an offer button. */
function connect_conflict_badge($verdict) {
    $s = is_array($verdict) ? ($verdict['status'] ?? 'CLEAR') : (string)$verdict;
    return [
        'BLOCKED'  => ['label' => 'Blocked — credential lapsed', 'tone' => 'bad'],
        'CONFLICT' => ['label' => 'Schedule conflict', 'tone' => 'warn'],
    ][$s] ?? ['label' => 'Available', 'tone' => 'ok'];
}
