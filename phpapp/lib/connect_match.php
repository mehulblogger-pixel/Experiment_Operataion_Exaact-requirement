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
/**
 * Configurable scoring weights (§23). Admin-tunable via settings; the DEFAULTS
 * are exactly the values the scorer has always used, so behaviour is unchanged
 * until someone deliberately re-weights. One JSON setting, clamped on read.
 */
function connect_match_weights_defaults() {
    return [
        'skills'      => 40,   // max points for skill/discipline overlap
        'reputation'  => 30,   // max points for rating/job history (inspectors)
        'credentials' => 15,   // max points for verified live credentials
        'cred_each'   => 6,    // points per verified credential (up to the cap)
        'elig_eligible'   => 15, 'elig_expiring' => 10, 'elig_check' => 6,
        'elig_unverified' => 9,  'elig_blocked'  => 0,
        'tax_bonus'   => 25,   // max taxonomy-graph bonus (professionals)
        'location'    => 10,   // max location-fit bonus
    ];
}
function connect_match_weights() {
    if (isset($GLOBALS['__cx_match_w']) && is_array($GLOBALS['__cx_match_w'])) return $GLOBALS['__cx_match_w'];
    $d = connect_match_weights_defaults();
    $raw = function_exists('setting_get') ? setting_get('connect_match_weights', '') : '';
    $saved = $raw ? (json_decode((string)$raw, true) ?: []) : [];
    $w = [];
    foreach ($d as $k => $dv) {
        $v = isset($saved[$k]) && is_numeric($saved[$k]) ? (int)$saved[$k] : $dv;
        $w[$k] = max(0, min(100, $v));   // clamp: no negative, no runaway weight
    }
    return $GLOBALS['__cx_match_w'] = $w;
}
function connect_match_weights_save(array $in) {
    $d = connect_match_weights_defaults(); $out = [];
    foreach ($d as $k => $dv) $out[$k] = isset($in[$k]) && is_numeric($in[$k]) ? max(0, min(100, (int)$in[$k])) : $dv;
    if (function_exists('setting_set')) setting_set('connect_match_weights', json_encode($out));
    connect_match_weights_reset_cache();
    return [true, 'Matching weights saved.'];
}
/** Reset the per-request memo (after a save, and for tests). */
function connect_match_weights_reset_cache() { unset($GLOBALS['__cx_match_w']); }

/** Who may tune the matching weights — master/admin only. */
function connect_match_weights_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    return function_exists('is_admin_level') && is_admin_level();
}
/** Admin screen: view/tune the matching weights, or reset to defaults. */
function ops_connect_match_weights($method) {
    ops_require(connect_match_weights_can(), 'Tuning the matching weights is for administrators.');
    if ($method === 'POST') {
        if (($_POST['action'] ?? '') === 'reset') { if (function_exists('setting_set')) setting_set('connect_match_weights', ''); connect_match_weights_reset_cache(); flash('Matching weights reset to defaults.'); }
        else { [, $m] = connect_match_weights_save($_POST); flash($m); }
        redirect('/connect-match-weights');
    }
    view('ops/connect_match_weights', ['weights' => connect_match_weights(), 'defaults' => connect_match_weights_defaults()]);
    return true;
}

function cx_match_score($cand, $req, $reqTerms = null) {
    $id = (int)($cand['id'] ?? 0);
    $kind = (string)($cand['kind'] ?? 'inspector');   // 'inspector' | 'professional'
    $reqTerms = $reqTerms ?? cx_requirement_terms($req);
    $W = connect_match_weights();

    // 1. Skills/discipline overlap (0-W.skills) — the professional's skills vs the ask.
    //    Both pools carry a free-text skills field, so this works for either kind.
    $skillMax = (int)$W['skills'];
    $skillTokens = cx_match_tokens((string)($cand['skills'] ?? '') . ' ' . (string)($cand['disciplines'] ?? ''));
    $overlap = $reqTerms ? count(array_intersect($reqTerms, $skillTokens)) : 0;
    $skillPts = $reqTerms ? min($skillMax, (int)round($overlap / max(1, count($reqTerms)) * $skillMax) + ($overlap > 0 ? (int)round($skillMax * 0.2) : 0)) : (int)round($skillMax * 0.3);

    // 2. Reputation (0-30) — internal inspectors have a job/rating history; a
    //    self-registered professional has none yet (honest zero until they work).
    $stars = null; $repPts = 0; $jobs = 0;
    if ($kind === 'inspector' && function_exists('rating_for')) {
        $r = rating_for($id);
        $stars = $r['stars'] ?? null; $jobs = (int)($r['done'] ?? 0);
        $repPts = (int)round(((int)($r['overall'] ?? 0)) / 100 * (int)$W['reputation']);
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
    $credPts = min((int)$W['credentials'], $verified * (int)$W['cred_each']);

    // 4. Eligibility (0-15) — the competence gate applies to internal inspectors;
    //    a self-registered professional is not gated (no lapsed-cert block), shown
    //    as UNVERIFIED so the desk knows verification is still pending.
    if ($kind === 'inspector' && function_exists('inspector_eligibility')) {
        $elig = inspector_eligibility($id, ['on_date' => substr((string)($req['start_date'] ?? ''), 0, 10) ?: date('Y-m-d')]);
    } else {
        $elig = ['status' => 'UNVERIFIED', 'reasons' => []];
    }
    $eligPts = ['ELIGIBLE' => (int)$W['elig_eligible'], 'EXPIRING' => (int)$W['elig_expiring'], 'CHECK' => (int)$W['elig_check'],
                'UNVERIFIED' => (int)$W['elig_unverified'], 'BLOCKED' => (int)$W['elig_blocked']][$elig['status']] ?? (int)$W['elig_check'];

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

/** Weighted taxonomy-node set a requirement reaches (title + discipline), cached
 *  per requirement. Reuses the universal graph so matching is concept-level, not
 *  substring. Empty when the graph is unavailable (degrades to token scoring). */
function connect_match_req_nodes($req) {
    static $cache = [];
    // Key on the resolved CONTENT (id + title + discipline), not the id alone — otherwise a
    // requirement edited within a request, or a reused id, would serve a stale node set.
    $key = (int)($req['id'] ?? 0) . '|' . md5(((string)($req['title'] ?? '')) . '|' . ((string)($req['discipline_code'] ?? '')));
    if (isset($cache[$key])) return $cache[$key];
    $weights = [];
    if (function_exists('connect_tax_resolve') && function_exists('connect_tax_expand')) {
        // Candidate phrases: the discipline name, and every 1–3 word n-gram of the
        // title tokens — so "pressure vessel inspector" is found inside a longer
        // title like "Pressure vessel inspector for FAT at Dahej".
        $phrases = array_filter([cx_discipline_name($req['discipline_code'] ?? '')]);
        // Tokenise with tax_norm (NOT cx_match_tokens — that drops domain words like
        // "inspector" as stopwords, which would prevent the role phrase forming).
        $toks = function_exists('tax_norm') ? array_values(array_filter(explode(' ', tax_norm((string)($req['title'] ?? ''))), fn($w) => strlen($w) >= 2))
                                            : cx_match_tokens((string)($req['title'] ?? ''));
        $n = count($toks);
        for ($i = 0; $i < $n; $i++) for ($len = 1; $len <= 3 && $i + $len <= $n; $len++)
            $phrases[] = implode(' ', array_slice($toks, $i, $len));
        $phrases = array_slice(array_values(array_unique($phrases)), 0, 40);
        foreach ($phrases as $t) {
            foreach (connect_tax_resolve($t) as $h) {
                if ((int)$h['score'] < 4) continue;   // only confident (exact-ish) hits, avoids title noise
                $w0 = (int)$h['score'];
                foreach (connect_tax_expand((int)$h['id']) as $nid) {
                    $w = $nid === (int)$h['id'] ? $w0 : max(1, $w0 - 1);
                    $weights[$nid] = max($weights[$nid] ?? 0, $w);
                }
            }
        }
    }
    return $cache[$key] = $weights;
}

/** Taxonomy-graph bonus (0-25) + the matched-concept reasons for a professional. */
function connect_match_tax_bonus($proId, $weights) {
    if (!$weights || (int)$proId <= 0) return [0, []];
    $ids = array_keys($weights); $in = implode(',', array_fill(0, count($ids), '?'));
    try { $rows = ops_all("SELECT pt.relation, pt.node_id, n.name FROM cx_profile_tax pt JOIN cx_tax_nodes n ON n.id=pt.node_id WHERE pt.pro_id=? AND pt.node_id IN ($in)", array_merge([(int)$proId], $ids)) ?: []; }
    catch (Throwable $e) { return [0, []]; }
    $relW = ['PRIMARY_ROLE' => 3, 'SPECIALIZATION' => 2, 'ADDITIONAL_ROLE' => 2, 'CERTIFICATION' => 2, 'SKILL' => 1, 'EQUIPMENT' => 1, 'INDUSTRY' => 1];
    $raw = 0; $reasons = [];
    foreach ($rows as $r) {
        $raw += ($weights[(int)$r['node_id']] ?? 1) * ($relW[strtoupper((string)$r['relation'])] ?? 1);
        if (count($reasons) < 4) $reasons['✓ ' . $r['name']] = true;
    }
    $cap = function_exists('connect_match_weights') ? (int)connect_match_weights()['tax_bonus'] : 25;
    return [min($cap, (int)round($raw)), array_keys($reasons)];
}

/**
 * Gap-2 — the SAME graph-taxonomy bonus as connect_match_tax_bonus(), but sourced from
 * FREE TEXT (an inspector's skills / role words) instead of stored cx_profile_tax rows.
 * Internal inspectors carry no profile-tax rows, so without this their concept/synonym/
 * hierarchical match silently degrades to plain substring tokens. Resolves the text to
 * taxonomy nodes exactly as connect_match_req_nodes() resolves the requirement, then
 * scores the overlap against the requirement's weighted nodes. Read-only.
 */
function connect_match_tax_bonus_text($text, $weights) {
    $text = trim((string)$text);
    if (!$weights || $text === '' || !function_exists('connect_tax_resolve') || !function_exists('connect_tax_expand')) return [0, []];
    $toks = function_exists('tax_norm')
        ? array_values(array_filter(explode(' ', tax_norm($text)), fn($w) => strlen($w) >= 2))
        : cx_match_tokens($text);
    $n = count($toks); $phrases = [];
    for ($i = 0; $i < $n; $i++) for ($len = 1; $len <= 3 && $i + $len <= $n; $len++) $phrases[] = implode(' ', array_slice($toks, $i, $len));
    $phrases = array_slice(array_values(array_unique(array_filter($phrases))), 0, 40);
    $have = [];  // node_id => confidence, the concepts this text demonstrably covers
    foreach ($phrases as $t) foreach (connect_tax_resolve($t) as $h) {
        if ((int)$h['score'] < 4) continue;   // only confident hits, same threshold as the requirement side
        foreach (connect_tax_expand((int)$h['id']) as $nid) $have[$nid] = max($have[$nid] ?? 0, (int)$h['score']);
    }
    $raw = 0; $reasons = [];
    foreach ($weights as $nid => $w) {
        if (!isset($have[(int)$nid])) continue;
        $raw += (int)$w;   // relation-agnostic (skill-level) credit for a demonstrated concept
        if (count($reasons) < 4) { try { $nm = (string)ops_val("SELECT name FROM cx_tax_nodes WHERE id=?", [(int)$nid]); if ($nm !== '') $reasons['✓ ' . $nm] = true; } catch (Throwable $e) {} }
    }
    $cap = function_exists('connect_match_weights') ? (int)connect_match_weights()['tax_bonus'] : 25;
    return [min($cap, (int)round($raw)), array_keys($reasons)];
}

/** Location bonus (0-10) + the match detail for a professional vs a job place. */
function connect_match_location_bonus($cand, $job) {
    if (!$job || !function_exists('connect_location_match')) return [0, null];
    $m = connect_location_match($cand, $job);
    $lmax = function_exists('connect_match_weights') ? (int)connect_match_weights()['location'] : 10;
    $tierPts = [1 => $lmax, 2 => (int)round($lmax*0.8), 3 => (int)round($lmax*0.6), 4 => (int)round($lmax*0.4), 5 => (int)round($lmax*0.2), 0 => 0];
    return [$tierPts[(int)$m['tier']] ?? 0, $m];
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
    // Concept-level (graph) + geographic enrichment for the professional pool.
    $reqNodes = connect_match_req_nodes($req);
    $job = (function_exists('connect_geo_resolve') && trim((string)($req['location'] ?? '')) !== '') ? connect_geo_resolve((string)$req['location']) : null;
    $rows = [];

    // Pool A — internal inspectors (full scoring: eligibility + rating + trust).
    foreach (ops_all("SELECT id, name, skills, sbu, designation, staff_kind, passport_token FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE'") ?: [] as $insp) {
        if (!empty($appliedInsp[(int)$insp['id']])) continue;
        $insp['kind'] = 'inspector';
        $m = cx_match_score($insp, $req, $reqTerms);
        // Gap-2 — the inspector pool gets the same concept/synonym/hierarchical taxonomy
        // bonus as the professional pool, resolved from the inspector's skills + role text.
        [$iTax, $iTaxR] = function_exists('connect_match_tax_bonus_text')
            ? connect_match_tax_bonus_text(trim(((string)($insp['skills'] ?? '')) . ' ' . ((string)($insp['designation'] ?? ''))), $reqNodes)
            : [0, []];
        if ($iTax > 0) { $m['score'] = min(100, (int)$m['score'] + $iTax); $m['reasons'] = array_merge($m['reasons'] ?? [], $iTaxR); }
        if (function_exists('connect_trust_score')) { $tt = connect_trust_score((int)$insp['id']); $m['trust'] = (int)$tt['score']; $m['trust_band'] = $tt['band']; }
        $rows[] = $insp + $m;
    }

    // Pool B — self-registered professionals (the shared pool). Same requirement,
    // scored on skills/availability; honest "New" trust until they are verified/rated.
    try {
        foreach (ops_all("SELECT id, name, headline, skills, disciplines, base_city, availability, passport_token,
                                 base_place_id, base_country, base_lat, base_lng, travel_radius_km, pan_india, overseas, intl_regions
                          FROM cx_professionals WHERE is_active=1") ?: [] as $pro) {
            if (!empty($appliedPro[(int)$pro['id']])) continue;
            $cand = ['id' => (int)$pro['id'], 'kind' => 'professional', 'name' => (string)$pro['name'],
                     'skills' => (string)$pro['skills'], 'disciplines' => (string)$pro['disciplines'],
                     'designation' => (string)($pro['headline'] ?? ''), 'passport_token' => (string)($pro['passport_token'] ?? '')];
            $m = cx_match_score($cand, $req, $reqTerms);
            // Concept-level (graph) + geographic bonuses with plain-language reasons.
            [$taxBonus, $taxReasons] = connect_match_tax_bonus((int)$pro['id'], $reqNodes);
            [$locBonus, $locMatch]  = connect_match_location_bonus($pro, $job);
            $m['score'] = min(100, (int)$m['score'] + $taxBonus + $locBonus);
            $m['reasons'] = array_merge($taxReasons,
                $locMatch && (int)$locMatch['tier'] > 0 ? ['✓ ' . $locMatch['label'] . (isset($locMatch['km']) && $locMatch['km'] !== null ? ' (' . (int)$locMatch['km'] . ' km)' : '')]
                    : ($job ? ['⚠ Outside declared area'] : []));
            $m['loc'] = $locMatch;
            if ($taxBonus >= 14) $m['reason'] = 'Strong role & skill match';
            elseif ($taxBonus >= 6 && $m['reason'] === 'New — skills fit') $m['reason'] = 'Relevant expertise';
            // Cross-pool Trust Score (#6) — professionals scored on the same scale
            // (verification tier + honest "New" band until they have history).
            if (function_exists('connect_trust_score_pro')) { $tt = connect_trust_score_pro((int)$pro['id']); $m['trust'] = (int)$tt['score']; $m['trust_band'] = $tt['band']; }
            else { $m['trust'] = 0; $m['trust_band'] = 'New'; }
            $rows[] = $cand + $m;
        }
    } catch (Throwable $e) {}

    // One person, once: collapse a candidate who is linked as BOTH an internal
    // inspector and a self-registered professional (keeps the stronger row).
    if (function_exists('connect_identity_dedupe_rows')) $rows = connect_identity_dedupe_rows($rows);

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
