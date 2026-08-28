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
