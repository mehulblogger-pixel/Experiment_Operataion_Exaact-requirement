<?php
// ============================================================================
//  CONNECT — AI Concierge & Requirement Builder  (slice K4, additive)
//
//  "Conversation before forms": instead of one page of 40 fields, the poster is
//  asked one thing at a time, and the requirement is assembled for them to
//  CONFIRM, not create (blueprint M7). Deterministic/rules-based at MVP — it
//  reuses the K0 taxonomy and the K2 requirement engine, and infers the likely
//  certifications from the chosen discipline. No new table, permission or status.
// ============================================================================

/** The ordered steps of the guided intake (labels drive the progress meter). */
function connect_concierge_steps() {
    return [
        1 => 'What do you need?',
        2 => 'Where and when?',
        3 => 'How many, and budget?',
        4 => 'Anything else?',
        5 => 'Confirm and post',
    ];
}

/**
 * Infer the certifications a discipline usually calls for, drawn from the
 * taxonomy's own certifications registry (cx_certifications_registry). Returns
 * a list of certification names — a suggestion the poster confirms, never a gate.
 */
function connect_concierge_suggest_certs($disciplineCode) {
    // Discipline → keywords to look for in the registry (issuer or name).
    $map = [
        'WELD' => ['CSWIP', 'CWI', 'AWS'],
        'NDT'  => ['ASNT', 'PCN', 'ISNT', 'NDT'],
        'COAT' => ['NACE', 'BGAS', 'AMPP', 'CIP'],
        'MECH' => ['API 510', 'API 570', 'API'],
        'PIPE' => ['API 570', 'API 1104'],
        'PIPELINE' => ['API 1104'],
        'INSVC' => ['API 510', 'API 570', 'API 653'],
        'ELEC' => ['IECEx', 'CoPC'],
        'AUDIT'=> ['IRCA', 'Lead Auditor'],
    ];
    $keys = $map[strtoupper((string)$disciplineCode)] ?? [];
    if (!$keys) return [];
    $out = [];
    try {
        foreach (ops_all("SELECT name, issuer FROM cx_certifications_registry ORDER BY sort_order, id") ?: [] as $c) {
            $hay = strtoupper($c['name'] . ' ' . $c['issuer']);
            foreach ($keys as $k) if (strpos($hay, strtoupper($k)) !== false) { $out[$c['name']] = true; break; }
        }
    } catch (Throwable $e) {}
    return array_slice(array_keys($out), 0, 6);
}

/** Staff gate — same as the marketplace desk; introduces NO new permission. */
function connect_concierge_can() {
    return function_exists('connect_market_can') ? connect_market_can()
        : ((function_exists('is_master') && is_master()) || (function_exists('is_coordinator_level') && is_coordinator_level()));
}

/**
 * The guided flow. State is carried forward in hidden fields (stateless, robust)
 * step by step; the final step posts the assembled requirement to the same K2
 * engine, straight to OPEN.
 */
function ops_connect_concierge($method) {
    ops_require(connect_concierge_can(), 'The guided requirement builder is for coordinators, managers and admins.');
    $step = max(1, min(5, (int)($_POST['step'] ?? $_GET['step'] ?? 1)));
    $data = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'discipline_code' => (string)($_POST['discipline_code'] ?? ''),
        'sector_code' => (string)($_POST['sector_code'] ?? ''),
        'location' => trim((string)($_POST['location'] ?? '')),
        'start_date' => (string)($_POST['start_date'] ?? ''),
        'work_type' => (string)($_POST['work_type'] ?? ''),
        'positions' => max(1, (int)($_POST['positions'] ?? 1)),
        'rate_min' => (float)($_POST['rate_min'] ?? 0),
        'rate_max' => (float)($_POST['rate_max'] ?? 0),
        'rate_unit' => (string)($_POST['rate_unit'] ?? ''),
        'description' => trim((string)($_POST['description'] ?? '')),
    ];

    if ($method === 'POST') {
        $nav = (string)($_POST['nav'] ?? '');
        if ($nav === 'post') {
            if ($data['title'] === '') { $step = 1; }
            else {
                $id = cx_requirement_create($data, true); // Confirm → post to OPEN
                flash('Posted — your requirement is open for applications.');
                redirect('/connect-requirement?id=' . $id);
            }
        } elseif ($nav === 'back') {
            $step = max(1, $step - 1);
        } elseif ($nav === 'next') {
            if ($step === 1 && $data['title'] === '') { flash('Tell us what you need first.', 'error'); }
            else $step = min(5, $step + 1);
        }
    }

    view('ops/connect_concierge', [
        'step'        => $step,
        'steps'       => connect_concierge_steps(),
        'data'        => $data,
        'disciplines' => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
        'sectors'     => function_exists('connect_tx_rows') ? connect_tx_rows('cx_sectors') : [],
        'certs'       => connect_concierge_suggest_certs($data['discipline_code']),
    ]);
    return true;
}
