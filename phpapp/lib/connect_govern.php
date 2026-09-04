<?php
// ============================================================================
//  CONNECT — Operational Governance  (slice K10, additive)
//
//  Part-F MVP, the layer that prevents disputes before they happen:
//   - F1 Commercial Protection: a term-sheet attached to the engagement (scope
//     days, hours, overtime, WAITING charges, travel, lodging, PPE, instrument
//     responsibility, revisit & revision & cancellation policy). "90% of
//     commercial disputes die here."
//   - F3 Site Readiness: a checklist the site must satisfy before mobilization,
//     with a readiness score and a READY / HOLD verdict.
//
//  Additive cx_terms + cx_readiness tables; no new permission or lifecycle
//  status (these are attributes and a checklist, not a workflow state).
// ============================================================================

/** The commercial term-sheet fields (F1). */
function cx_terms_fields() {
    return [
        'inspection_days'   => 'Inspection days',
        'max_hours_day'     => 'Max hours/day',
        'overtime_rate'     => 'Overtime rate (₹/hr)',
        'waiting_grace_hrs' => 'Waiting grace (hours)',
        'waiting_rate'      => 'Waiting charge (₹/hr after grace)',
        'travel_by'         => 'Travel paid by',
        'lodging_by'        => 'Boarding & lodging by',
        'ppe_by'            => 'PPE provided by',
        'instrument_by'     => 'Instruments provided by',
        'revisit_policy'    => 'Revisit policy (if material not ready)',
        'revisions_incl'    => 'Report revisions included',
        'cancellation'      => 'Cancellation policy',
    ];
}

/** The site-readiness checklist items (F3); `m` marks a mandatory gate. */
function cx_readiness_items() {
    return [
        'material_ready'   => ['Material ready for inspection', true],
        'docs_available'   => ['Documents available (WPS/PQR, ITP/QAP, drawings, TCs)', true],
        'client_rep'       => ['Client representative present', true],
        'vendor_rep'       => ['Vendor representative present', false],
        'equipment_access' => ['Equipment accessible', true],
        'power_available'  => ['Power available', false],
        'crane_available'  => ['Crane available (if needed)', false],
        'hydro_ready'      => ['Hydro / test setup ready', false],
        'safety_induction' => ['Safety induction arranged', true],
        'gate_pass'        => ['Gate pass issued', true],
    ];
}

function connect_govern_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // One term-sheet per requirement (all fields are strings — free-form policy text or numbers).
    $cols = '';
    foreach (array_keys(cx_terms_fields()) as $f) $cols .= "$f VARCHAR(200) DEFAULT '', ";
    db()->exec("CREATE TABLE IF NOT EXISTS cx_terms (
        id $pk, requirement_id INT DEFAULT 0, $cols
        accepted INT DEFAULT 0, updated_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_readiness (
        id $pk, requirement_id INT DEFAULT 0, item_key VARCHAR(40) DEFAULT '',
        checked INT DEFAULT 0, note VARCHAR(300) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_readiness ON cx_readiness (requirement_id, item_key)"); } catch (Throwable $e) {}
}

/** The stored term-sheet for a requirement (empty row if none yet). */
function cx_terms_get($requirementId) {
    connect_govern_migrate();
    return ops_one("SELECT * FROM cx_terms WHERE requirement_id=?", [(int)$requirementId]) ?: null;
}

/** Save (insert or update) the term-sheet. */
function cx_terms_save($requirementId, array $in) {
    connect_govern_migrate();
    $requirementId = (int)$requirementId;
    $fields = array_keys(cx_terms_fields());
    $exists = cx_terms_get($requirementId);
    $accepted = !empty($in['accepted']) ? 1 : (int)($exists['accepted'] ?? 0);
    if ($exists) {
        $set = implode('=?, ', $fields) . '=?';
        $args = array_map(fn($f) => substr(trim((string)($in[$f] ?? $exists[$f] ?? '')), 0, 200), $fields);
        $args[] = $accepted; $args[] = function_exists('user_name') ? user_name(current_user()) : ''; $args[] = date('c'); $args[] = $requirementId;
        db()->prepare("UPDATE cx_terms SET $set, accepted=?, updated_by=?, updated_at=? WHERE requirement_id=?")->execute($args);
    } else {
        $cols = implode(',', $fields);
        $ph = implode(',', array_fill(0, count($fields), '?'));
        $args = array_map(fn($f) => substr(trim((string)($in[$f] ?? '')), 0, 200), $fields);
        $args = array_merge([$requirementId], $args, [$accepted, function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
        db()->prepare("INSERT INTO cx_terms (requirement_id,$cols,accepted,updated_by,updated_at) VALUES (?,$ph,?,?,?)")->execute($args);
    }
    return true;
}

/** True when every term-sheet field is filled — the job is publishable (F1). */
function cx_terms_complete($requirementId) {
    $t = cx_terms_get($requirementId);
    if (!$t) return false;
    foreach (array_keys(cx_terms_fields()) as $f) if (trim((string)($t[$f] ?? '')) === '') return false;
    return true;
}

/** item_key => bool for a requirement's readiness checklist. */
function cx_readiness_get($requirementId) {
    connect_govern_migrate();
    $out = [];
    foreach (ops_all("SELECT item_key, checked FROM cx_readiness WHERE requirement_id=?", [(int)$requirementId]) ?: [] as $r)
        $out[(string)$r['item_key']] = (int)$r['checked'] === 1;
    return $out;
}

/** Set one readiness item on/off (idempotent upsert). */
function cx_readiness_set($requirementId, $itemKey, $checked) {
    connect_govern_migrate();
    if (!array_key_exists($itemKey, cx_readiness_items())) return false;
    $requirementId = (int)$requirementId;
    $has = ops_one("SELECT id FROM cx_readiness WHERE requirement_id=? AND item_key=?", [$requirementId, $itemKey]);
    if ($has) db()->prepare("UPDATE cx_readiness SET checked=?, updated_at=? WHERE id=?")->execute([$checked ? 1 : 0, date('c'), (int)$has['id']]);
    else db()->prepare("INSERT INTO cx_readiness (requirement_id,item_key,checked,updated_at) VALUES (?,?,?,?)")->execute([$requirementId, $itemKey, $checked ? 1 : 0, date('c')]);
    return true;
}

/**
 * Readiness verdict: [score 0-100, verdict READY|HOLD, missing_mandatory[]].
 * READY only when every mandatory item is checked (F3 / Operations-Advisor seed).
 */
function cx_readiness_score($requirementId) {
    $items = cx_readiness_items();
    $state = cx_readiness_get($requirementId);
    $total = count($items); $done = 0; $missing = [];
    foreach ($items as $key => [$label, $mandatory]) {
        $ok = !empty($state[$key]);
        if ($ok) $done++;
        elseif ($mandatory) $missing[] = $label;
    }
    return [
        'score'   => $total ? (int)round($done / $total * 100) : 0,
        'verdict' => $missing ? 'HOLD' : 'READY',
        'missing_mandatory' => $missing,
        'done' => $done, 'total' => $total,
    ];
}
