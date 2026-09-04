<?php
// ============================================================================
//  CONNECT — Trust Score  (slice K5, additive & READ-ONLY)
//
//  The blueprint's M6 reputation number, 0-1000, composed from signals EXAACT
//  already holds — no new table, no new permission, no new status. It is a pure
//  read: every call recomputes from current data, and it explains itself.
//
//  Blueprint weights: Verification 20 · Client performance 25 · Reliability 15 ·
//  Report quality 10 · Conduct 15 · Endorsements 15. A bucket with no data
//  drops out and the rest are renormalised — so a new professional is never
//  punished for silence, and the score never claims evidence it does not have.
//  Confidence banding ("Limited history" under 10 jobs) is stated honestly.
// ============================================================================

/** Count of verified, currently-live credentials (reuses the P1 vault vocab). */
function connect_trust_verified_count($inspectorId) {
    $n = 0;
    try {
        foreach (ops_all("SELECT * FROM inspector_certs WHERE inspector_id=?", [(int)$inspectorId]) ?: [] as $c) {
            $status = function_exists('credential_status') ? credential_status($c) : (string)($c['status'] ?? '');
            if (strtoupper((string)($c['verify_status'] ?? '')) === 'VERIFIED'
                && in_array(strtoupper((string)$status), ['VALID','VERIFIED','CURRENT','ACTIVE'], true)) $n++;
        }
    } catch (Throwable $e) {}
    return $n;
}

/**
 * The 0-1000 Trust Score plus its sub-scores, band and explanation.
 * Returns [score, band, band_class, jobs, verified, limited, subs[]] where each
 * sub is [key, label, weight, value(0-100)|null, counted(bool)].
 */
function connect_trust_score($inspectorId) {
    $inspectorId = (int)$inspectorId;
    $verified = connect_trust_verified_count($inspectorId);

    $r = function_exists('rating_for') ? rating_for($inspectorId) : [];
    $jobs = (int)($r['done'] ?? 0);
    $hasHistory = $jobs > 0;

    // value|null per bucket — null = no data, drops out of the weighting.
    $subs = [
        ['verification', 'Verification',       20, min(100, $verified * 35)],                 // always available (0 if none)
        ['client',       'Client performance', 25, $hasHistory ? (int)($r['overall'] ?? 0) : null],
        ['reliability',  'Reliability',        15, $hasHistory ? (int)($r['attend_score'] ?? 0) : null],
        ['report',       'Report quality',     10, isset($r['report_score']) && $r['report_score'] !== null ? (int)$r['report_score'] : null],
        ['conduct',      'Conduct',            15, (int)($r['comp_score'] ?? 100)],            // clean by default
        ['endorsements', 'Peer endorsements',  15, null],                                       // no endorsement engine yet
    ];

    $wSum = 0; $acc = 0; $out = [];
    foreach ($subs as $s) {
        [$key, $label, $w, $val] = $s;
        $counted = $val !== null;
        if ($counted) { $wSum += $w; $acc += $w * $val; }
        $out[] = ['key' => $key, 'label' => $label, 'weight' => $w, 'value' => $val, 'counted' => $counted];
    }
    $score = $wSum > 0 ? (int)round($acc / $wSum * 10) : 0;   // /100 *1000  == *10
    $score = max(0, min(1000, $score));

    [$band, $bandClass] = connect_trust_band($score, $jobs);
    return [
        'score'      => $score,
        'band'       => $band,
        'band_class' => $bandClass,
        'jobs'       => $jobs,
        'verified'   => $verified,
        'limited'    => $jobs < 10,
        'subs'       => $out,
    ];
}

/**
 * Cross-pool Trust Score (#6) — the SAME 0-1000 shape for a self-registered
 * professional as for an internal inspector, so the unified pool is scored on one
 * scale. A professional has no inspector_certs vault or job history yet, so its
 * verification bucket is driven by the #3 verification tier and the count of
 * VERIFIED checks; buckets with no data drop out and the band honestly says
 * "New" until they have a track record. No new table, no new permission.
 */
function connect_trust_score_pro($proId) {
    $proId = (int)$proId;
    // Verification tier → the verification bucket value (0-100). Honest: a
    // registered-but-unverified professional scores 0 here.
    $tier = function_exists('connect_verify_tier_for_professional') ? connect_verify_tier_for_professional($proId) : 'registered';
    $tierVal = ['registered' => 0, 'id_verified' => 55, 'credential_verified' => 80, 'proven' => 100][$tier] ?? 0;

    // The count of genuinely VERIFIED checks — the "verified" figure the badge and
    // confidence logic use (parallels an inspector's live-credential count).
    $verified = 0;
    try { $verified = (int)ops_val("SELECT COUNT(*) FROM cx_verifications WHERE subject_kind='professional' AND subject_id=? AND status='VERIFIED'", [$proId]); }
    catch (Throwable $e) {}

    // Professionals have no marketplace job/rating history wired yet → those
    // buckets are null (drop out), exactly like a brand-new inspector.
    $jobs = 0;
    // Unlike an internal inspector, a self-registered stranger gets NO "clean by
    // default" conduct prior — trust tracks verification only until they have a
    // real track record, so an unverified newcomer is an honest 0, not inflated.
    $subs = [
        ['verification', 'Verification',       20, (int)$tierVal],
        ['client',       'Client performance', 25, null],
        ['reliability',  'Reliability',        15, null],
        ['report',       'Report quality',     10, null],
        ['conduct',      'Conduct',            15, null],
        ['endorsements', 'Peer endorsements',  15, null],
    ];
    $wSum = 0; $acc = 0; $out = [];
    foreach ($subs as $s) {
        [$key, $label, $w, $val] = $s;
        $counted = $val !== null;
        if ($counted) { $wSum += $w; $acc += $w * $val; }
        $out[] = ['key' => $key, 'label' => $label, 'weight' => $w, 'value' => $val, 'counted' => $counted];
    }
    $score = $wSum > 0 ? max(0, min(1000, (int)round($acc / $wSum * 10))) : 0;
    [$band, $bandClass] = connect_trust_band($score, $jobs);
    return ['score' => $score, 'band' => $band, 'band_class' => $bandClass,
            'jobs' => $jobs, 'verified' => $verified, 'limited' => true, 'tier' => $tier, 'subs' => $out];
}

/** Trust Score for either pool member — 'inspector' or 'professional'. */
function connect_trust_score_for($kind, $id) {
    return ($kind === 'professional') ? connect_trust_score_pro($id) : connect_trust_score((int)$id);
}

/** Band label + class from score and history depth (confidence banding). */
function connect_trust_band($score, $jobs) {
    if ($jobs < 3)  return ['New', 'mut'];
    if ($jobs < 10) return ['Limited history', 'warn'];
    if ($score >= 800) return ['Trusted', 'ok'];
    if ($score >= 650) return ['Established', 'ok'];
    if ($score >= 450) return ['Developing', 'warn'];
    return ['Building', 'warn'];
}

/** Compact badge text for a card: "742 · Established" (or the band alone). */
function connect_trust_badge($inspectorId) {
    $t = connect_trust_score($inspectorId);
    return $t['jobs'] < 3 && $t['verified'] === 0 ? $t['band'] : ($t['score'] . ' · ' . $t['band']);
}
