<?php
// ============================================================================
//  CONNECT — Requirement reuse: duplicate + templates  (K0+, additive)
//
//  Master brief §49: clients (and staff) should not re-type a requirement they
//  have posted before. Two reuse paths, both built ENTIRELY on the existing
//  cx_requirement_create / cx_positions engine — no parallel posting flow:
//    • DUPLICATE — clone any requirement into a fresh DRAFT (fields + crew
//      positions copied; award, applications and status deliberately NOT copied).
//    • TEMPLATES — save a requirement's shape under a name and start new ones from
//      it (one small owner-scoped table).
//
//  STRICTLY ADDITIVE: one new table (cx_req_templates); the duplicate path adds
//  no schema at all. Rehire of a specific person is already covered by the client
//  bench (§49); this covers reusing the JOB shape.
// ============================================================================

/** The requirement fields that define its shape (copied on duplicate / template). */
function connect_req_shape_fields() {
    return ['title','sector_code','discipline_code','equipment_group','material_code',
            'location','work_type','start_date','end_date','positions','rate_min','rate_max',
            'rate_unit','description'];
}

/** Build a create-payload ($in) from an existing requirement row. */
function connect_req_shape_from_row(array $row) {
    $in = [];
    foreach (connect_req_shape_fields() as $f) $in[$f] = $row[$f] ?? '';
    // carry the deputation / rate terms if the row has them
    foreach (['deputation_basis','rate_inclusive','voucher_cadence'] as $t) if (isset($row[$t]) && $row[$t] !== '') $in[$t] = $row[$t];
    return $in;
}

/**
 * Duplicate a requirement into a new DRAFT for a poster. Copies the shape + crew
 * positions; never the award/applications/status. `$asParty` scopes/owns the copy
 * (a client duplicating its own; 0 = keep the source's poster). Returns new id, 0
 * on failure. `$overrides` can change any shape field (e.g. a new title/dates).
 */
function connect_requirement_duplicate($sourceId, $asParty = 0, array $overrides = [], $post = false) {
    if (!function_exists('cx_requirement_get') || !function_exists('cx_requirement_create')) return 0;
    $src = cx_requirement_get($sourceId);
    if (!$src) return 0;
    $party = (int)$asParty > 0 ? (int)$asParty : (int)($src['poster_party_id'] ?? 0);
    $in = connect_req_shape_from_row($src);
    $in['poster_party_id'] = $party;
    $in['poster_name']     = (string)($src['poster_name'] ?? '');
    if ($in['title'] !== '') $in['title'] = 'Copy of ' . $in['title'];
    foreach ($overrides as $k => $v) $in[$k] = $v;
    $newId = (int)cx_requirement_create($in, (bool)$post);
    if ($newId > 0 && function_exists('cx_positions_for') && function_exists('cx_position_add')) {
        foreach (cx_positions_for($sourceId) as $pos) cx_position_add($newId, $pos);
    }
    return $newId;
}

// ---- Templates --------------------------------------------------------------

function connect_reqtemplates_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_req_templates (
        id $pk, owner_party_id INT DEFAULT 0, label VARCHAR(140) DEFAULT '',
        snapshot TEXT, created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_reqtpl ON cx_req_templates (owner_party_id)"); } catch (Throwable $e) {}
}

/** Save a requirement's shape as a named template (owner-scoped). */
function connect_reqtemplate_save_from_requirement($ownerParty, $sourceId, $label, $by = '') {
    connect_reqtemplates_migrate();
    $src = function_exists('cx_requirement_get') ? cx_requirement_get($sourceId) : null;
    if (!$src) return [false, 'Requirement not found.', 0];
    $label = trim((string)$label); if ($label === '') $label = (string)($src['title'] ?? 'Template');
    $snap = connect_req_shape_from_row($src);
    db()->prepare("INSERT INTO cx_req_templates (owner_party_id,label,snapshot,created_by,created_at) VALUES (?,?,?,?,?)")
        ->execute([(int)$ownerParty, substr($label,0,140), json_encode($snap), substr((string)$by,0,120), date('c')]);
    return [true, 'Saved as a template — start future postings from it.', (int)db()->lastInsertId()];
}

function connect_reqtemplates_for($ownerParty) {
    connect_reqtemplates_migrate();
    return ops_all("SELECT * FROM cx_req_templates WHERE owner_party_id=? ORDER BY id DESC", [(int)$ownerParty]) ?: [];
}

function connect_reqtemplate_delete($id, $ownerParty) {
    connect_reqtemplates_migrate();
    db()->prepare("DELETE FROM cx_req_templates WHERE id=? AND owner_party_id=?")->execute([(int)$id, (int)$ownerParty]);
    return true;
}

/** Create a new DRAFT requirement for a party from a saved template. Returns id. */
function connect_reqtemplate_create_requirement($templateId, $party, array $overrides = [], $post = false) {
    connect_reqtemplates_migrate();
    $t = ops_one("SELECT * FROM cx_req_templates WHERE id=? AND owner_party_id=?", [(int)$templateId, (int)$party]);
    if (!$t) return 0;
    $in = json_decode((string)$t['snapshot'], true) ?: [];
    $in['poster_party_id'] = (int)$party;
    foreach ($overrides as $k => $v) $in[$k] = $v;
    return (int)cx_requirement_create($in, (bool)$post);
}

// ---------------------------------------------------------------------------
//  Gap-6 — near-duplicate requirement detection. The clone action above reuses a
//  requirement deliberately; this warns when a NEW requirement looks like one the
//  same client already has open — same discipline, location, similar title,
//  overlapping dates — so a double-post is caught, not silently created. Read-only,
//  non-blocking (an advisory list, never a hard stop).
// ---------------------------------------------------------------------------
function connect_requirement_duplicates($in, $exceptId = 0, $limit = 5) {
    $poster = (int)($in['poster_party_id'] ?? 0);
    if ($poster <= 0) return [];
    $disc = strtoupper(trim((string)($in['discipline_code'] ?? '')));
    $loc  = strtolower(trim((string)($in['location'] ?? '')));
    $titleToks = function_exists('cx_match_tokens') ? cx_match_tokens((string)($in['title'] ?? '')) : [];
    $start = trim((string)($in['start_date'] ?? '')); $end = trim((string)($in['end_date'] ?? '')) ?: $start;
    try {
        $rows = ops_all("SELECT id, ref_code, title, discipline_code, location, start_date, end_date
                           FROM cx_requirements
                          WHERE poster_party_id=? AND id<>? AND status IN ('OPEN','DRAFT')
                          ORDER BY id DESC LIMIT 200", [$poster, (int)$exceptId]) ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $score = 0; $reasons = [];
        if ($disc !== '' && strtoupper(trim((string)$r['discipline_code'])) === $disc) { $score += 2; $reasons[] = 'same discipline'; }
        $rloc = strtolower(trim((string)$r['location']));
        if ($loc !== '' && $rloc !== '' && ($loc === $rloc || strpos($rloc, $loc) !== false || strpos($loc, $rloc) !== false)) { $score += 2; $reasons[] = 'same location'; }
        if ($titleToks) {
            $rt = function_exists('cx_match_tokens') ? cx_match_tokens((string)$r['title']) : [];
            $ov = count($titleToks) ? count(array_intersect($titleToks, $rt)) / count($titleToks) : 0;
            if ($ov >= 0.6) { $score += 2; $reasons[] = 'similar title'; }
            elseif ($ov >= 0.3) { $score += 1; $reasons[] = 'overlapping title'; }
        }
        if ($start !== '') {
            $rs = trim((string)$r['start_date']); $re = trim((string)$r['end_date']) ?: $rs;
            if ($rs !== '' && $rs <= $end && $re >= $start) { $score += 1; $reasons[] = 'overlapping dates'; }
        }
        // Two strong signals (score >= 3) is the "near-duplicate" bar.
        if ($score >= 3) $out[] = ['id' => (int)$r['id'], 'ref_code' => (string)$r['ref_code'], 'title' => (string)$r['title'], 'score' => $score, 'reasons' => $reasons];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}
