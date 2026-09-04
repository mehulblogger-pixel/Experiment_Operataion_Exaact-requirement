<?php
// ============================================================================
//  CONNECT — Client-facing talent search & privacy-safe result cards  (K0+)
//
//  The buyer side of the passport programme. A client (or agency) searches the
//  shared professional pool by ONE keyword + structured filters, and gets ranked
//  cards that show WHAT a person can do (skills, verified credentials, match
//  reasons, location fit) while WHO-they-are and how-to-reach-them stay governed
//  by connect_privacy_resolve. Contact never appears until it is earned — an
//  existing engagement, the pro's 'public' setting, or an approved reveal.
//
//  Reuses connect_pro_search_smart (taxonomy + location ranking) and the privacy
//  resolver — no new ranking, no duplicated masking logic. STRICTLY ADDITIVE.
// ============================================================================

/** Location-tier → short human label, mirroring connect_location_match tiers. */
function connect_client_loc_label($tier) {
    return [1 => 'Exact city', 2 => 'Within travel radius', 3 => 'Selected area',
            4 => 'Pan-India', 5 => 'International'][(int)$tier] ?? '';
}

/**
 * Search the pool for a client. Returns privacy-safe CARD view-models, already
 * ranked (keyword score, then location tier). Only professionals who allow
 * discovery (privacy_listed=1) are returned.
 */
function connect_client_search($clientPartyId, array $f = [], $limit = 40) {
    $rows = function_exists('connect_pro_search_smart') ? connect_pro_search_smart($f, $limit * 2) : [];
    $supFilter = strtoupper(trim((string)($f['supplier'] ?? '')));   // §19 supplier-type filter
    $out = [];
    foreach ($rows as $r) {
        $s = function_exists('connect_privacy_get') ? connect_privacy_get((int)$r['id']) : ['listed' => 1];
        if (empty($s['listed'])) continue;                 // the pro has paused discovery
        $card = connect_client_card($r, $clientPartyId);
        if ($supFilter !== '' && function_exists('connect_supplier_filter_match')
            && !connect_supplier_filter_match($card['supplier'] ?? [], $supFilter)) continue;
        $out[] = $card;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** Build one privacy-safe card for a professional row from the client's view. */
function connect_client_card(array $r, $clientPartyId) {
    $proId   = (int)$r['id'];
    $engaged = function_exists('connect_privacy_engaged') ? connect_privacy_engaged($proId, (int)$clientPartyId) : false;
    $view    = function_exists('connect_privacy_resolve')
        ? connect_privacy_resolve($r, ['party_id' => (int)$clientPartyId, 'engaged' => $engaged])
        : ['display_name' => (string)($r['name'] ?? ''), 'contact_visible' => false, 'rate_mode' => 'band', 'settings' => ['contact' => 'on_request']];

    // Verification tier badge (from the shared verify engine).
    $tierKey = function_exists('connect_verify_tier_for_professional') ? connect_verify_tier_for_professional($proId) : 'registered';
    $tierRank = function_exists('connect_verify_tier_rank') ? (int)connect_verify_tier_rank($tierKey) : 0;
    $tierLbl  = function_exists('connect_verify_tier_label') ? connect_verify_tier_label($tierKey) : 'Registered';

    // Verified certifications (VERIFIED only — the honest count).
    $vCerts = [];
    if (function_exists('connect_cred_certs')) {
        foreach (connect_cred_certs($proId) as $c) if ((int)($c['verified'] ?? 0) === 1) $vCerts[] = $c['name'];
    }

    // Contact state the card CTA switches on.
    $contactSetting = $view['settings']['contact'] ?? 'on_request';
    if (!empty($view['contact_visible']))       $contactState = 'shown';       // engaged / public / revealed
    elseif ($contactSetting === 'hidden')       $contactState = 'message_only';
    else {                                                                      // on_request
        $pending = false;
        if (function_exists('ops_val'))
            $pending = (bool)ops_val("SELECT COUNT(*) FROM cx_pro_contact_reveals WHERE pro_id=? AND client_party_id=? AND status='REQUESTED' AND revoked_at=''", [$proId, (int)$clientPartyId]);
        $contactState = $pending ? 'requested' : 'request';
    }

    // Match reasons (top taxonomy hits) — what made this person surface.
    $hits = [];
    foreach ((array)($r['_match_hits'] ?? []) as $h) {
        $nm = is_array($h) ? ($h['name'] ?? $h['node'] ?? '') : (string)$h;
        if ($nm !== '') $hits[] = $nm;
    }
    $hits = array_values(array_unique($hits));

    $locTier = (int)($r['_loc']['tier'] ?? 0);

    return [
        'id'             => $proId,
        'display_name'   => $view['display_name'],
        'identity_masked'=> !empty($view['identity_masked']),
        'headline'       => (string)($r['headline'] ?? ''),
        'skills'         => (string)($r['skills'] ?? ''),
        'disciplines'    => (string)($r['disciplines'] ?? ''),
        'availability'   => (string)($r['availability'] ?? ''),
        'base_city'      => (string)($r['base_city'] ?? ($r['base_state'] ?? '')),
        'pan_india'      => (int)($r['pan_india'] ?? 0) === 1,
        'loc_tier'       => $locTier,
        'loc_label'      => connect_client_loc_label($locTier),
        'match_hits'     => array_slice($hits, 0, 6),
        'match_score'    => (int)($r['_match_score'] ?? 0),
        'tier_key'       => $tierKey,
        'tier_rank'      => $tierRank,
        'tier_label'     => $tierLbl,
        'verified_certs' => $vCerts,
        'rate_mode'      => $view['rate_mode'] ?? 'band',
        'contact_state'  => $contactState,       // shown | request | requested | message_only
        'contact_reason' => $view['contact_reason'] ?? '',
        'mobile'         => (string)($view['mobile'] ?? ''),
        'email'          => (string)($view['email'] ?? ''),
        'engaged'        => $engaged,
        'on_bench'       => function_exists('connect_client_bench_has') ? connect_client_bench_has((int)$clientPartyId, $proId) : false,
        'supplier'       => function_exists('connect_supplier_type') ? connect_supplier_type($proId) : ['channel' => 'INDIVIDUAL', 'type' => 'Individual'],
    ];
}
