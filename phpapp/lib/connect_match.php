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
            // Cross-pool Trust Score (#6) — professionals scored on the same scale
            // (verification tier + honest "New" band until they have history).
            if (function_exists('connect_trust_score_pro')) { $tt = connect_trust_score_pro((int)$pro['id']); $m['trust'] = (int)$tt['score']; $m['trust_band'] = $tt['band']; }
            else { $m['trust'] = 0; $m['trust_band'] = 'New'; }
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

// ============================================================================
//  OPTIONAL AI RE-RANKING (#6) — complements the rules, never replaces them.
//
//  The deterministic matcher above is the source of truth and the explanation.
//  When an AI provider is configured, a coordinator can ask it to RE-ORDER and
//  annotate the rule-provided shortlist for nuance the rules miss (e.g. matching
//  "pressure-vessel FAT witness" to a profile that says "ASME VIII stage
//  inspection"). Hard guarantees:
//    - AI may only reorder / annotate the candidates the rules already returned —
//      it can never introduce a candidate or un-block a BLOCKED one.
//    - Any AI failure, disabled provider, or unparseable reply falls straight
//      back to the deterministic order. AI is additive, never load-bearing.
// ============================================================================

/** Is AI ranking available and switched on? (Reuses the ai.php seam; a setting
 *  lets an admin turn the feature off even when a provider is configured.) */
function connect_match_ai_available() {
    if (!function_exists('ai_enabled') || !ai_enabled()) return false;
    if (function_exists('setting_get') && (string)setting_get('connect_ai_match', '1') === '0') return false;
    return true;
}

/** The compact candidate line the model ranks on (no confidential fields). */
function connect_match_ai_candidate_brief($r) {
    return [
        'id'    => (int)($r['id'] ?? 0),
        'kind'  => (string)($r['kind'] ?? 'inspector'),
        'name'  => (string)($r['name'] ?? ''),
        'skills'=> (string)($r['skills'] ?? ''),
        'designation' => (string)($r['designation'] ?? ''),
        'availability' => (string)($r['availability'] ?? ''),
        'rule_score'   => (int)($r['score'] ?? 0),
        'eligibility'  => (string)($r['eligibility'] ?? ''),
        'trust'        => (int)($r['trust'] ?? 0),
        'verified'     => (int)($r['verified'] ?? 0),
    ];
}

/**
 * Re-rank a rule-provided shortlist with AI. $chat is an injectable callable
 * ($system,$user)=>[$text,$err] (defaults to ai_chat) so this is testable without
 * a network. Returns [rows, used] — `used` is false whenever AI did not safely
 * apply, and then `rows` is the untouched deterministic order.
 */
function connect_match_ai_rerank($req, array $rows, $chat = null) {
    if (!$rows) return [$rows, false];
    $chat = is_callable($chat) ? $chat : (function_exists('ai_chat') ? 'ai_chat' : null);
    if (!$chat) return [$rows, false];

    // Only the non-blocked candidates are offered to the model; BLOCKED stay put.
    $rankable = array_values(array_filter($rows, fn($r) => ($r['eligibility'] ?? '') !== 'BLOCKED'));
    $blocked  = array_values(array_filter($rows, fn($r) => ($r['eligibility'] ?? '') === 'BLOCKED'));
    if (count($rankable) < 2) return [$rows, false];

    $byId = [];
    foreach ($rankable as $r) $byId[(string)$r['kind'] . ':' . (int)$r['id']] = $r;

    $cands = array_map('connect_match_ai_candidate_brief', $rankable);
    $system = "You rank technical-services candidates for a job. You are given the requirement and a shortlist "
        . "already filtered and scored by deterministic rules. Re-order ONLY these candidates by real-world fit and "
        . "add a short reason (max 12 words) each. You must not invent candidates or change eligibility. "
        . "Reply with ONLY a JSON array like "
        . '[{"kind":"professional","id":3,"reason":"ASME VIII stage match"}], best first. Include every candidate exactly once.';
    $user = "REQUIREMENT: " . json_encode([
        'title' => (string)($req['title'] ?? ''), 'discipline' => cx_discipline_name($req['discipline_code'] ?? ''),
        'location' => (string)($req['location'] ?? ''), 'detail' => (string)($req['description'] ?? ''),
    ]) . "\n\nCANDIDATES: " . json_encode($cands);

    try { [$text, $err] = $chat($system, $user, 800); }
    catch (Throwable $e) { return [$rows, false]; }
    if (!empty($err) || !is_string($text) || trim($text) === '') return [$rows, false];

    // Parse the model's JSON (tolerate a code-fence or surrounding prose).
    if (preg_match('/\[.*\]/s', $text, $mm)) $text = $mm[0];
    $order = json_decode($text, true);
    if (!is_array($order) || !$order) return [$rows, false];

    $ranked = []; $seen = []; $valid = 0;
    foreach ($order as $o) {
        if (!is_array($o)) continue;
        $key = (string)($o['kind'] ?? '') . ':' . (int)($o['id'] ?? 0);
        if (!isset($byId[$key]) || isset($seen[$key])) continue;   // ignore invented / duplicate ids
        $row = $byId[$key];
        $reason = trim((string)($o['reason'] ?? ''));
        if ($reason !== '') $row['ai_reason'] = mb_substr($reason, 0, 120);
        $row['ai_ranked'] = true;
        $ranked[] = $row; $seen[$key] = true; $valid++;
    }
    if ($valid === 0) return [$rows, false];   // the reply named no real candidate — fall back
    // Any candidate the model dropped is appended in its deterministic position,
    // so nobody silently disappears.
    foreach ($rankable as $r) {
        $key = (string)$r['kind'] . ':' . (int)$r['id'];
        if (!isset($seen[$key])) $ranked[] = $r;
    }
    if (count($ranked) !== count($rankable)) return [$rows, false];   // safety: never lose anyone

    return [array_merge($ranked, $blocked), true];
}

/**
 * Recommendations, optionally AI-ranked. Always returns the deterministic set;
 * when $useAi and a provider is available, it overlays the AI order + reasons.
 * Returns [rows, ai_used].
 */
function connect_match_for_requirement_ranked($req, $limit = 8, $useAi = false, $chat = null) {
    $rows = connect_match_for_requirement($req, $limit);
    if (!$useAi || !$rows) return [$rows, false];
    if (!$chat && !connect_match_ai_available()) return [$rows, false];
    return connect_match_ai_rerank($req, $rows, $chat);
}
