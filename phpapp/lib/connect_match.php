<?php
// ============================================================================
//  CONNECT — Matching & Recommendation  (slice K3, additive & READ-ONLY)
//
//  Given a marketplace requirement (K2), rank professionals from the pool with a
//  plain-language reason — "Best match", "Highly rated", "Verified & ready" —
//  exactly the recommendation-card experience the blueprint (M8) asks for.
//
//  Pure REUSE: it composes engines that already exist —
//   - inspector_eligibility() + pill (competence/credential readiness),
//   - rating_for() (reputation),
//   - the K0 taxonomy (discipline names) and the inspector's own skills text.
//  No new table, no new permission, no new status; it reads and ranks only.
// ============================================================================

/** Meaningful lowercase tokens (length >= 3) from a blob of text. */
function cx_match_tokens($text) {
    $text = strtolower((string)$text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $stop = ['and','the','for','with','inspection','inspector','technical','site','work','job','per','day'];
    $out = [];
    foreach (preg_split('/\s+/', trim($text)) as $w) {
        if (strlen($w) >= 3 && !in_array($w, $stop, true)) $out[$w] = true;
    }
    return array_keys($out);
}

/** The discipline name for a taxonomy code, '' if unknown. */
function cx_discipline_name($code) {
    $code = trim((string)$code); if ($code === '') return '';
    try { return (string)ops_val("SELECT name FROM cx_disciplines WHERE code=?", [$code]); }
    catch (Throwable $e) { return ''; }
}

/** The set of tokens a requirement wants matched (discipline + title + detail). */
function cx_requirement_terms($req) {
    $bits = [(string)($req['title'] ?? ''), (string)($req['description'] ?? ''),
             cx_discipline_name($req['discipline_code'] ?? '')];
    return cx_match_tokens(implode(' ', $bits));
}

/**
 * Score one professional against a requirement. Returns
 * [score 0-100, parts, reason, eligibility, stars, verified].
 */
function cx_match_score($cand, $req, $reqTerms = null) {
    $id = (int)($cand['id'] ?? 0);
    $kind = (string)($cand['kind'] ?? 'inspector');   // 'inspector' | 'professional'
    $reqTerms = $reqTerms ?? cx_requirement_terms($req);

    // 1. Skills/discipline overlap (0-40) — the professional's skills vs the ask.
    //    Both pools carry a free-text skills field, so this works for either kind.
    $skillTokens = cx_match_tokens((string)($cand['skills'] ?? '') . ' ' . (string)($cand['disciplines'] ?? ''));
    $overlap = $reqTerms ? count(array_intersect($reqTerms, $skillTokens)) : 0;
    $skillPts = $reqTerms ? min(40, (int)round($overlap / max(1, count($reqTerms)) * 40) + ($overlap > 0 ? 8 : 0)) : 12;

    // 2. Reputation (0-30) — internal inspectors have a job/rating history; a
    //    self-registered professional has none yet (honest zero until they work).
    $stars = null; $repPts = 0; $jobs = 0;
    if ($kind === 'inspector' && function_exists('rating_for')) {
        $r = rating_for($id);
        $stars = $r['stars'] ?? null; $jobs = (int)($r['done'] ?? 0);
        $repPts = (int)round(((int)($r['overall'] ?? 0)) / 100 * 30);
    }

    // 3. Verified live credentials (0-15) — from the P1 vault (inspectors only;
    //    professionals have no vault yet — see the credential-vault unification).
    $verified = 0;
    if ($kind === 'inspector') {
        try {
            foreach (ops_all("SELECT * FROM inspector_certs WHERE inspector_id=?", [$id]) ?: [] as $c) {
                $status = function_exists('credential_status') ? credential_status($c) : (string)($c['status'] ?? '');
                if (strtoupper((string)($c['verify_status'] ?? '')) === 'VERIFIED'
                    && in_array(strtoupper((string)$status), ['VALID','VERIFIED','CURRENT','ACTIVE'], true)) $verified++;
            }
        } catch (Throwable $e) {}
    }
    $credPts = min(15, $verified * 6);

    // 4. Eligibility (0-15) — the competence gate applies to internal inspectors;
    //    a self-registered professional is not gated (no lapsed-cert block), shown
    //    as UNVERIFIED so the desk knows verification is still pending.
    if ($kind === 'inspector' && function_exists('inspector_eligibility')) {
        $elig = inspector_eligibility($id, ['on_date' => substr((string)($req['start_date'] ?? ''), 0, 10) ?: date('Y-m-d')]);
    } else {
        $elig = ['status' => 'UNVERIFIED', 'reasons' => []];
    }
    $eligPts = ['ELIGIBLE' => 15, 'EXPIRING' => 10, 'CHECK' => 6, 'UNVERIFIED' => 9, 'BLOCKED' => 0][$elig['status']] ?? 6;

    $score = $skillPts + $repPts + $credPts + $eligPts;

    // Plain-language primary reason — the card's one-liner.
    $reason = 'Available professional';
    if ($elig['status'] === 'BLOCKED')            $reason = 'Not eligible for this work';
    elseif ($skillPts >= 30 && $repPts >= 20)     $reason = 'Best match';
    elseif ($stars !== null && $stars >= 4 && $jobs >= 3) $reason = 'Highly rated';
    elseif ($verified >= 2)                        $reason = 'Verified & ready';
    elseif ($skillPts >= 24)                       $reason = 'Strong skills fit';
    elseif ($kind === 'professional')              $reason = 'New — skills fit';
    elseif ($elig['status'] === 'ELIGIBLE')        $reason = 'Eligible now';

    return [
        'score' => $score, 'reason' => $reason, 'eligibility' => $elig['status'], 'kind' => $kind,
        'stars' => $stars, 'verified' => $verified, 'jobs' => $jobs,
        'parts' => ['skills' => $skillPts, 'reputation' => $repPts, 'credentials' => $credPts, 'eligibility' => $eligPts],
    ];
}

/**
 * Ranked recommendations for a requirement — the card list. Eligible-and-strong
 * float to the top; BLOCKED professionals sink to the bottom (shown, not hidden,
 * so the desk sees why). Excludes anyone who already applied.
 */
function connect_match_for_requirement($req, $limit = 8) {
    if (!is_array($req)) return [];
    $reqId = (int)($req['id'] ?? 0);
    // Who already applied — separately per pool, so neither is double-counted.
    $appliedInsp = []; $appliedPro = [];
    try {
        foreach (ops_all("SELECT inspector_id, applicant_professional_id FROM cx_applications WHERE requirement_id=?", [$reqId]) ?: [] as $a) {
            if ((int)$a['inspector_id'] > 0) $appliedInsp[(int)$a['inspector_id']] = true;
            if ((int)($a['applicant_professional_id'] ?? 0) > 0) $appliedPro[(int)$a['applicant_professional_id']] = true;
        }
    } catch (Throwable $e) {}

    $reqTerms = cx_requirement_terms($req);
    $rows = [];

    // Pool A — internal inspectors (full scoring: eligibility + rating + trust).
    foreach (ops_all("SELECT id, name, skills, sbu, designation, staff_kind, passport_token FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE'") ?: [] as $insp) {
        if (!empty($appliedInsp[(int)$insp['id']])) continue;
        $insp['kind'] = 'inspector';
        $m = cx_match_score($insp, $req, $reqTerms);
        if (function_exists('connect_trust_score')) { $tt = connect_trust_score((int)$insp['id']); $m['trust'] = (int)$tt['score']; $m['trust_band'] = $tt['band']; }
        $rows[] = $insp + $m;
    }

    // Pool B — self-registered professionals (the shared pool). Same requirement,
    // scored on skills/availability; honest "New" trust until they are verified/rated.
    try {
        foreach (ops_all("SELECT id, name, headline, skills, disciplines, base_city, availability, passport_token FROM cx_professionals WHERE is_active=1") ?: [] as $pro) {
            if (!empty($appliedPro[(int)$pro['id']])) continue;
            $cand = ['id' => (int)$pro['id'], 'kind' => 'professional', 'name' => (string)$pro['name'],
                     'skills' => (string)$pro['skills'], 'disciplines' => (string)$pro['disciplines'],
                     'designation' => (string)($pro['headline'] ?? ''), 'passport_token' => (string)($pro['passport_token'] ?? '')];
            $m = cx_match_score($cand, $req, $reqTerms);
            $m['trust'] = 0; $m['trust_band'] = 'New';
            $rows[] = $cand + $m;
        }
    } catch (Throwable $e) {}

    // Sort: BLOCKED last, then by score desc, then rating desc.
    usort($rows, function ($a, $b) {
        $ab = $a['eligibility'] === 'BLOCKED' ? 1 : 0; $bb = $b['eligibility'] === 'BLOCKED' ? 1 : 0;
        if ($ab !== $bb) return $ab <=> $bb;
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return (float)($b['stars'] ?? 0) <=> (float)($a['stars'] ?? 0);
    });
    return array_slice($rows, 0, max(1, (int)$limit));
}
