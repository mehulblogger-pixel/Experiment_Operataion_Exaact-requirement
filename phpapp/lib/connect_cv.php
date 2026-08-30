<?php
// ============================================================================
//  CONNECT — CV-assisted passport prefill  (K0+, additive)
//
//  UPLOAD/PASTE CV → EXTRACT → MAP to the taxonomy → SUGGEST → the professional
//  CONFIRMS. The mapping is REAL, not fake: the CV text is scanned against the
//  universal taxonomy's alias vocabulary (roles, skills, methods, equipment,
//  certifications, disciplines) and the geo place names — every hit is an exact
//  alias match, so precision is high and false positives are rare. Nothing is
//  written until the professional confirms; suggestions never overwrite data.
//
//  An LLM extractor is a future provider seam; this deterministic scan is the
//  dependable floor and works offline. Paste is the 100%-reliable input; file
//  extraction (txt / docx / best-effort pdf) sits on top.
// ============================================================================

/** Best-effort plain text from an uploaded CV's bytes. Reliable for text and
 *  docx; best-effort for pdf (many CVs are image-only and yield little — the
 *  paste box is the dependable path). */
function connect_cv_extract_text($bytes, $mime = '', $name = '') {
    $bytes = (string)$bytes; if ($bytes === '') return '';
    $ext = strtolower((string)pathinfo((string)$name, PATHINFO_EXTENSION));
    // Plain text
    if (strpos((string)$mime, 'text/') === 0 || $ext === 'txt' || (strpos($bytes, "%PDF") !== 0 && strncmp($bytes, "PK\x03\x04", 4) !== 0 && ctype_print(substr($bytes, 0, 200)))) {
        return $bytes;
    }
    // DOCX (a zip) — pull the text out of word/document.xml
    if (strncmp($bytes, "PK\x03\x04", 4) === 0 && class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'cv'); @file_put_contents($tmp, $bytes);
        $z = new ZipArchive(); $out = '';
        if ($z->open($tmp) === true) { $xml = $z->getFromName('word/document.xml'); $z->close();
            if ($xml) { $xml = preg_replace('/<\/w:p>/', "\n", $xml); $out = trim(html_entity_decode(strip_tags($xml))); } }
        @unlink($tmp); if ($out !== '') return $out;
    }
    // PDF — decompress FlateDecode streams and pull text-drawing operators.
    if (strpos($bytes, "%PDF") === 0) {
        $txt = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $mm)) {
            foreach ($mm[1] as $s) {
                $d = @gzuncompress($s); if ($d === false) $d = @gzinflate($s); if ($d === false) $d = $s;
                if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', (string)$d, $tm)) foreach ($tm[0] as $t) $txt .= ' ' . stripcslashes(substr($t, 1, -1));
            }
        }
        return trim($txt);
    }
    // Fallback — printable ASCII runs.
    return trim((string)preg_replace('/[^\x20-\x7E\n]+/', ' ', $bytes));
}

/** Load the alias → node map + node meta once (in-memory scan, no per-term SQL). */
function connect_cv_alias_map() {
    static $cache = null; if ($cache !== null) return $cache;
    if (function_exists('connect_tax_graph_migrate')) connect_tax_graph_migrate();
    $alias = []; $meta = [];
    try {
        foreach (ops_all("SELECT a.alias_norm, a.node_id, n.name, n.kind FROM cx_tax_aliases a JOIN cx_tax_nodes n ON n.id=a.node_id WHERE n.status='ACTIVE'") ?: [] as $r) {
            $an = (string)$r['alias_norm']; if ($an === '' || strlen($an) < 2) continue;
            if (!isset($alias[$an])) $alias[$an] = (int)$r['node_id'];
            $meta[(int)$r['node_id']] = ['name' => $r['name'], 'kind' => $r['kind']];
        }
    } catch (Throwable $e) {}
    return $cache = ['alias' => $alias, 'meta' => $meta];
}
/** Load city name → place row once. */
function connect_cv_city_map() {
    static $cache = null; if ($cache !== null) return $cache;
    if (function_exists('connect_geo_migrate')) connect_geo_migrate();
    $m = [];
    try { foreach (ops_all("SELECT id,name,state_name,country_code,lat,lng FROM cx_geo_places WHERE kind='CITY' AND status='ACTIVE'") ?: [] as $c) $m[function_exists('tax_norm') ? tax_norm($c['name']) : strtolower($c['name'])] = $c; }
    catch (Throwable $e) {}
    return $cache = $m;
}

/**
 * Scan CV text → suggested passport nodes + a detected base city. Every hit is
 * an EXACT alias match (no fuzzy guessing). Returns
 *   ['expertise'=>[['node_id','name','kind','relation'], ...], 'base_place'=>row|null].
 */
function connect_cv_scan($text, $limit = 40) {
    $norm = function_exists('tax_norm') ? tax_norm($text) : strtolower(trim((string)$text));
    if ($norm === '') return ['expertise' => [], 'base_place' => null];
    $words = explode(' ', $norm);
    $n = count($words);
    $A = connect_cv_alias_map(); $alias = $A['alias']; $meta = $A['meta'];
    $cities = connect_cv_city_map();

    $relFor = ['ROLE' => 'ADDITIONAL_ROLE', 'DISCIPLINE' => 'ADDITIONAL_ROLE', 'DOMAIN' => 'ADDITIONAL_ROLE',
               'SPECIALIZATION' => 'SPECIALIZATION', 'SKILL' => 'SKILL', 'METHOD' => 'SKILL', 'ACTIVITY' => 'SKILL',
               'EQUIPMENT' => 'EQUIPMENT', 'SYSTEM' => 'EQUIPMENT', 'CERTIFICATION' => 'CERTIFICATION',
               'INDUSTRY' => 'INDUSTRY', 'SECTOR' => 'INDUSTRY'];
    $found = []; $base = null;
    // Slide n-grams (longest first so "pressure vessel inspector" beats "inspector").
    for ($len = 4; $len >= 1; $len--) {
        for ($i = 0; $i + $len <= $n; $i++) {
            $g = implode(' ', array_slice($words, $i, $len)); if (strlen($g) < 2) continue;
            if (isset($alias[$g])) {
                $nid = $alias[$g]; if (!isset($found[$nid]) && isset($meta[$nid])) {
                    $kind = strtoupper($meta[$nid]['kind']);
                    $found[$nid] = ['node_id' => $nid, 'name' => $meta[$nid]['name'], 'kind' => $kind, 'relation' => $relFor[$kind] ?? 'SKILL'];
                }
            }
            if ($base === null && $len <= 3 && isset($cities[$g])) $base = $cities[$g];
        }
    }
    // Promote the first ROLE to PRIMARY_ROLE.
    foreach ($found as &$f) { if ($f['kind'] === 'ROLE') { $f['relation'] = 'PRIMARY_ROLE'; break; } } unset($f);
    return ['expertise' => array_slice(array_values($found), 0, $limit), 'base_place' => $base];
}
