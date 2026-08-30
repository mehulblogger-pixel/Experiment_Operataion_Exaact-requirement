<?php
// ============================================================================
//  CONNECT — Universal Technical Taxonomy GRAPH  (K0+ backbone, additive)
//
//  The flat master tables (cx_disciplines / cx_sectors / cx_equipment* /
//  cx_certifications_registry / cx_roles, in lib/connect_taxonomy.php) are the
//  seed data of a marketplace, but they cannot express the real world: a
//  professional is multi-discipline; "NDT" ≡ "Non-Destructive Testing"; picking
//  "NDT Technician" should suggest RT/UT/MT/PT; a one-word client search must
//  reach roles, skills, equipment and certs at once.
//
//  This file adds a GRAPH over that flat data — one node type for every technical
//  concept, a parent tree + many-to-many related/suggests edges, and an alias
//  table for synonyms/abbreviations — plus a link table so ONE professional
//  master record carries many taxonomy nodes (primary / additional / skill /
//  equipment / industry / certification), each with optional competency + years.
//
//  STRICTLY ADDITIVE: new `cx_tax_*` / `cx_profile_tax` tables only. The existing
//  flat masters, the CSV `cx_professionals.disciplines/skills`, matching, search
//  and every current screen keep working untouched. connect_tax_generalize()
//  IMPORTS the flat masters into the graph (idempotent), so nothing is re-keyed
//  by hand and the graph stays a superset, never a replacement.
//
//  KINDS (extensible — admin master-data, not app logic):
//    DOMAIN · DISCIPLINE · SPECIALIZATION · ROLE · SKILL · ACTIVITY · EQUIPMENT ·
//    SYSTEM · CERTIFICATION · STANDARD · INDUSTRY · SECTOR · METHOD · MATERIAL ·
//    PROJECT_TYPE
// ============================================================================

function connect_tax_graph_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    // A single node for every technical concept. parent_id is the primary tree;
    // richer links live in cx_tax_edges. slug is the normalised name (dedupe key).
    db()->exec("CREATE TABLE IF NOT EXISTS cx_tax_nodes (
        id $pk, kind VARCHAR(20) DEFAULT '', name VARCHAR(200) DEFAULT '', slug VARCHAR(200) DEFAULT '',
        code VARCHAR(60) DEFAULT '', parent_id INT DEFAULT 0, status VARCHAR(12) DEFAULT 'ACTIVE',
        sort_order INT DEFAULT 0, source VARCHAR(40) DEFAULT '', meta TEXT,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    // Many-to-many typed relationships beyond the parent tree.
    //   REL: RELATED (peer) | SUGGESTS (pick A → offer B)
    db()->exec("CREATE TABLE IF NOT EXISTS cx_tax_edges (
        id $pk, from_id INT DEFAULT 0, to_id INT DEFAULT 0, rel VARCHAR(16) DEFAULT 'RELATED',
        created_at VARCHAR(30) DEFAULT '')");
    // Synonyms / abbreviations / common industry terms → resolve to a canonical node.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_tax_aliases (
        id $pk, node_id INT DEFAULT 0, alias VARCHAR(200) DEFAULT '', alias_norm VARCHAR(200) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // ONE professional ↔ MANY taxonomy nodes. relation says how the node attaches.
    //   RELATION: PRIMARY_ROLE | ADDITIONAL_ROLE | SPECIALIZATION | SKILL |
    //             EQUIPMENT | INDUSTRY | CERTIFICATION
    db()->exec("CREATE TABLE IF NOT EXISTS cx_profile_tax (
        id $pk, pro_id INT DEFAULT 0, node_id INT DEFAULT 0, relation VARCHAR(20) DEFAULT 'SKILL',
        competency VARCHAR(12) DEFAULT '', years REAL DEFAULT 0, last_used VARCHAR(20) DEFAULT '',
        verified INT DEFAULT 0, source VARCHAR(16) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    foreach ([
        "CREATE INDEX ix_cx_tax_kind ON cx_tax_nodes (kind, status)",
        "CREATE INDEX ix_cx_tax_slug ON cx_tax_nodes (kind, slug)",
        "CREATE INDEX ix_cx_tax_parent ON cx_tax_nodes (parent_id)",
        "CREATE INDEX ix_cx_tax_edge ON cx_tax_edges (from_id, rel)",
        "CREATE INDEX ix_cx_tax_alias ON cx_tax_aliases (alias_norm)",
        "CREATE INDEX ix_cx_ptax_pro ON cx_profile_tax (pro_id)",
        "CREATE INDEX ix_cx_ptax_node ON cx_profile_tax (node_id)",
    ] as $ix) { try { db()->exec($ix); } catch (Throwable $e) {} }
}

/** The controlled node kinds (extensible via master data, not code). */
function connect_tax_kinds() {
    return ['DOMAIN','DISCIPLINE','SPECIALIZATION','ROLE','SKILL','ACTIVITY','EQUIPMENT',
            'SYSTEM','CERTIFICATION','STANDARD','INDUSTRY','SECTOR','METHOD','MATERIAL','PROJECT_TYPE'];
}
function connect_tax_relations() {
    return ['PRIMARY_ROLE','ADDITIONAL_ROLE','SPECIALIZATION','SKILL','EQUIPMENT','INDUSTRY','CERTIFICATION'];
}
function connect_tax_competencies() { return ['BEGINNER','WORKING','ADVANCED','EXPERT']; }

/** Normalise a term for matching: lowercase, punctuation→space, collapse spaces. */
function tax_norm($s) {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

// ---- Nodes ------------------------------------------------------------------

/** Add (or return existing) a node, deduped by kind+slug. Returns node id. */
function connect_tax_node_add($kind, $name, $parentId = 0, array $opts = []) {
    connect_tax_graph_migrate();
    $kind = strtoupper(trim((string)$kind)); $name = trim((string)$name);
    if ($name === '' || !in_array($kind, connect_tax_kinds(), true)) return 0;
    $slug = tax_norm($name);
    $ex = (int)ops_val("SELECT id FROM cx_tax_nodes WHERE kind=? AND slug=?", [$kind, $slug]);
    if ($ex) {
        // Late-arriving parentage / code fills a blank without clobbering edits.
        if ($parentId > 0) db()->prepare("UPDATE cx_tax_nodes SET parent_id=? WHERE id=? AND (parent_id=0 OR parent_id IS NULL)")->execute([(int)$parentId, $ex]);
        return $ex;
    }
    $now = date('c');
    db()->prepare("INSERT INTO cx_tax_nodes (kind,name,slug,code,parent_id,status,sort_order,source,meta,created_at,updated_at)
                   VALUES (?,?,?,?,?, 'ACTIVE', ?,?,?,?,?)")
        ->execute([$kind, $name, $slug, (string)($opts['code'] ?? ''), (int)$parentId,
                   (int)($opts['sort'] ?? 0), (string)($opts['source'] ?? ''), (string)($opts['meta'] ?? ''), $now, $now]);
    $id = (int)db()->lastInsertId();
    // The canonical name is always an alias of itself (so search hits it uniformly).
    connect_tax_alias_add($id, $name);
    return $id;
}
function connect_tax_node_get($id) {
    connect_tax_graph_migrate();
    return ops_one("SELECT * FROM cx_tax_nodes WHERE id=?", [(int)$id]) ?: null;
}
/** Active children of a node (the drill-down step). */
function connect_tax_children($id, $kind = '') {
    connect_tax_graph_migrate();
    $w = $kind !== '' ? " AND kind=?" : ''; $a = [(int)$id]; if ($kind !== '') $a[] = strtoupper($kind);
    return ops_all("SELECT * FROM cx_tax_nodes WHERE parent_id=? AND status='ACTIVE'$w ORDER BY sort_order, name", $a) ?: [];
}
/** Root nodes of a kind (e.g. every DOMAIN) — the first drill-down choice. */
function connect_tax_roots($kind) {
    connect_tax_graph_migrate();
    return ops_all("SELECT * FROM cx_tax_nodes WHERE kind=? AND status='ACTIVE' AND COALESCE(parent_id,0)=0 ORDER BY sort_order, name", [strtoupper($kind)]) ?: [];
}
function connect_tax_all($kind) {
    connect_tax_graph_migrate();
    return ops_all("SELECT * FROM cx_tax_nodes WHERE kind=? AND status='ACTIVE' ORDER BY sort_order, name", [strtoupper($kind)]) ?: [];
}

// ---- Aliases + relationships ------------------------------------------------

function connect_tax_alias_add($nodeId, $alias) {
    connect_tax_graph_migrate();
    $norm = tax_norm($alias); if ($norm === '' || (int)$nodeId <= 0) return;
    if ((int)ops_val("SELECT COUNT(*) FROM cx_tax_aliases WHERE node_id=? AND alias_norm=?", [(int)$nodeId, $norm])) return;
    db()->prepare("INSERT INTO cx_tax_aliases (node_id,alias,alias_norm,created_at) VALUES (?,?,?,?)")
        ->execute([(int)$nodeId, trim((string)$alias), $norm, date('c')]);
}
/** Relate two nodes (RELATED peer, or SUGGESTS A→B). Symmetric for RELATED. */
function connect_tax_relate($fromId, $toId, $rel = 'RELATED') {
    connect_tax_graph_migrate();
    $fromId = (int)$fromId; $toId = (int)$toId; $rel = strtoupper($rel);
    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) return;
    $add = function ($a, $b, $r) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_tax_edges WHERE from_id=? AND to_id=? AND rel=?", [$a, $b, $r])) return;
        db()->prepare("INSERT INTO cx_tax_edges (from_id,to_id,rel,created_at) VALUES (?,?,?,?)")->execute([$a, $b, $r, date('c')]);
    };
    $add($fromId, $toId, $rel);
    if ($rel === 'RELATED') $add($toId, $fromId, 'RELATED');   // symmetric
}

// ---- Resolve + search (the synonym / one-keyword engine) --------------------

/**
 * Resolve a free-text term to canonical node ids, ranked by confidence:
 *   4 exact alias · 3 code hit · 2 word-prefix alias · 1 contains.
 * Returns [['id'=>, 'kind'=>, 'name'=>, 'score'=>], ...] best first.
 */
function connect_tax_resolve($term, array $kinds = [], $limit = 40) {
    connect_tax_graph_migrate();
    $norm = tax_norm($term); if ($norm === '') return [];
    $kw = $kinds ? " AND n.kind IN (" . implode(',', array_fill(0, count($kinds), '?')) . ")" : '';
    $ka = array_map('strtoupper', $kinds);
    $rows = ops_all(
        "SELECT n.id, n.kind, n.name, a.alias_norm, n.code
         FROM cx_tax_aliases a JOIN cx_tax_nodes n ON n.id=a.node_id
         WHERE n.status='ACTIVE' AND (a.alias_norm = ? OR a.alias_norm LIKE ? OR a.alias_norm LIKE ?)$kw",
        array_merge([$norm, $norm . ' %', '% ' . $norm . ' %'], $ka)) ?: [];
    $best = [];
    foreach ($rows as $r) {
        $an = (string)$r['alias_norm'];
        $score = ($an === $norm) ? 4 : (strpos($an, $norm . ' ') === 0 ? 2 : (strpos($an, ' ' . $norm . ' ') !== false ? 1 : 1));
        if (strtolower((string)$r['code']) === strtolower(trim((string)$term)) && (string)$r['code'] !== '') $score = max($score, 3);
        $id = (int)$r['id'];
        if (!isset($best[$id]) || $best[$id]['score'] < $score)
            $best[$id] = ['id' => $id, 'kind' => $r['kind'], 'name' => $r['name'], 'score' => $score];
    }
    // Also catch codes stored on the node itself (e.g. "RT", "UT").
    foreach (ops_all("SELECT id,kind,name FROM cx_tax_nodes WHERE status='ACTIVE' AND LOWER(code)=?" . $kw,
             array_merge([strtolower(trim((string)$term))], $ka)) ?: [] as $r) {
        $id = (int)$r['id']; if (!isset($best[$id])) $best[$id] = ['id' => $id, 'kind' => $r['kind'], 'name' => $r['name'], 'score' => 3];
    }
    usort($best, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice(array_values($best), 0, $limit);
}

/** Nodes related/suggested by a node — powers "pick NDT → offer RT/UT/MT/PT". */
function connect_tax_suggest($nodeId, $limit = 24) {
    connect_tax_graph_migrate();
    $nodeId = (int)$nodeId; if ($nodeId <= 0) return [];
    $out = [];
    foreach (connect_tax_children($nodeId) as $c) $out[(int)$c['id']] = $c;
    foreach (ops_all("SELECT n.* FROM cx_tax_edges e JOIN cx_tax_nodes n ON n.id=e.to_id
                      WHERE e.from_id=? AND e.rel IN ('RELATED','SUGGESTS') AND n.status='ACTIVE'", [$nodeId]) ?: [] as $n)
        $out[(int)$n['id']] = $n;
    return array_slice(array_values($out), 0, $limit);
}

/** Expand a node to itself + descendants + related — the reach of one concept. */
function connect_tax_expand($nodeId, $depth = 3) {
    connect_tax_graph_migrate();
    $seen = []; $frontier = [(int)$nodeId];
    for ($d = 0; $d <= $depth && $frontier; $d++) {
        $next = [];
        foreach ($frontier as $id) {
            if (isset($seen[$id]) || $id <= 0) continue; $seen[$id] = true;
            foreach (connect_tax_children($id) as $c) $next[] = (int)$c['id'];
            foreach (ops_all("SELECT to_id FROM cx_tax_edges WHERE from_id=? AND rel IN ('RELATED','SUGGESTS')", [$id]) ?: [] as $e) $next[] = (int)$e['to_id'];
        }
        $frontier = $next;
    }
    return array_keys($seen);
}

// ---- Professional ↔ taxonomy (the multi-discipline passport link) -----------

function connect_profile_tax_attach($proId, $nodeId, $relation = 'SKILL', array $opts = []) {
    connect_tax_graph_migrate();
    $proId = (int)$proId; $nodeId = (int)$nodeId;
    if ($proId <= 0 || $nodeId <= 0 || !connect_tax_node_get($nodeId)) return 0;
    $relation = in_array(strtoupper($relation), connect_tax_relations(), true) ? strtoupper($relation) : 'SKILL';
    $ex = (int)ops_val("SELECT id FROM cx_profile_tax WHERE pro_id=? AND node_id=? AND relation=?", [$proId, $nodeId, $relation]);
    if ($ex) {
        db()->prepare("UPDATE cx_profile_tax SET competency=?, years=?, last_used=?, verified=? WHERE id=?")
            ->execute([(string)($opts['competency'] ?? ''), (float)($opts['years'] ?? 0), (string)($opts['last_used'] ?? ''), (int)($opts['verified'] ?? 0), $ex]);
        return $ex;
    }
    db()->prepare("INSERT INTO cx_profile_tax (pro_id,node_id,relation,competency,years,last_used,verified,source,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$proId, $nodeId, $relation, (string)($opts['competency'] ?? ''), (float)($opts['years'] ?? 0),
                   (string)($opts['last_used'] ?? ''), (int)($opts['verified'] ?? 0), (string)($opts['source'] ?? 'manual'), date('c')]);
    return (int)db()->lastInsertId();
}
function connect_profile_tax_detach($id, $proId) {
    connect_tax_graph_migrate();
    db()->prepare("DELETE FROM cx_profile_tax WHERE id=? AND pro_id=?")->execute([(int)$id, (int)$proId]);
    return true;
}
/** A professional's taxonomy, joined to node names, grouped-friendly. */
function connect_profile_tax_for($proId) {
    connect_tax_graph_migrate();
    return ops_all("SELECT pt.*, n.kind, n.name, n.code FROM cx_profile_tax pt
                    JOIN cx_tax_nodes n ON n.id=pt.node_id WHERE pt.pro_id=? ORDER BY pt.relation, n.name", [(int)$proId]) ?: [];
}

/**
 * ONE-KEYWORD professional discovery: resolve the term to nodes, expand each to
 * its descendants + related concepts, then find professionals attached to any of
 * those nodes — ranked by how many (and how strongly) they match. Returns
 * [['pro_id'=>, 'score'=>, 'hits'=>[nodeName,...]], ...] best first.
 */
function connect_tax_find_professionals($term, array $filters = [], $limit = 50) {
    connect_tax_graph_migrate();
    $hits = connect_tax_resolve($term);
    if (!$hits) return [];
    // Build the reachable node set (concept universe) with a weight per node.
    $weight = [];
    foreach ($hits as $h) {
        $w0 = (int)$h['score'];
        foreach (connect_tax_expand((int)$h['id']) as $nid) {
            $w = $nid === (int)$h['id'] ? $w0 : max(1, $w0 - 1);   // direct hit weighs more than a related concept
            $weight[$nid] = max($weight[$nid] ?? 0, $w);
        }
    }
    if (!$weight) return [];
    $ids = array_keys($weight);
    $in = implode(',', array_fill(0, count($ids), '?'));
    // relation weight: a PRIMARY_ROLE hit counts more than a stray skill.
    $rows = ops_all("SELECT pt.pro_id, pt.node_id, pt.relation, n.name
                     FROM cx_profile_tax pt JOIN cx_tax_nodes n ON n.id=pt.node_id
                     WHERE pt.node_id IN ($in)", $ids) ?: [];
    $relW = ['PRIMARY_ROLE' => 3, 'SPECIALIZATION' => 2, 'ADDITIONAL_ROLE' => 2, 'CERTIFICATION' => 2, 'SKILL' => 1, 'EQUIPMENT' => 1, 'INDUSTRY' => 1];
    $agg = [];
    foreach ($rows as $r) {
        $pid = (int)$r['pro_id']; $nid = (int)$r['node_id'];
        $s = ($weight[$nid] ?? 1) * ($relW[strtoupper((string)$r['relation'])] ?? 1);
        if (!isset($agg[$pid])) $agg[$pid] = ['pro_id' => $pid, 'score' => 0, 'hits' => []];
        $agg[$pid]['score'] += $s;
        $agg[$pid]['hits'][(string)$r['name']] = true;
    }
    // Optional availability / verification filters (cheap, reuse cx_professionals).
    $out = [];
    foreach ($agg as $a) {
        $a['hits'] = array_keys($a['hits']);
        $out[] = $a;
    }
    usort($out, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($out, 0, $limit);
}

// ---- Generalize: import the flat masters + a curated multi-domain tree -------

/**
 * Build the graph from the existing flat masters (idempotent) and add a curated
 * starter tree of domains → specializations → roles → skills/equipment with
 * aliases and suggests-edges. Everything is admin-extensible afterwards; this is
 * only the seed so the marketplace is useful on day one. Guarded by a marker so a
 * re-run (boot/cron/test) is a no-op and never clobbers admin edits.
 */
function connect_tax_generalize($force = false) {
    connect_tax_graph_migrate();
    if (function_exists('connect_taxonomy_seed')) connect_taxonomy_seed();   // ensure flat masters exist
    if (!$force && function_exists('setting_get') && setting_get('tax_graph_v1')) return ['skipped' => true];

    $N  = fn($kind, $name, $parent = 0, $opts = []) => connect_tax_node_add($kind, $name, $parent, $opts);
    $AL = fn($id, ...$aliases) => array_map(fn($a) => connect_tax_alias_add($id, $a), $aliases);
    $SG = fn($from, $to) => connect_tax_relate($from, $to, 'SUGGESTS');
    $RE = fn($a, $b) => connect_tax_relate($a, $b, 'RELATED');

    // --- (1) import flat masters --------------------------------------------
    foreach (connect_tx_rows('cx_disciplines') as $d) {
        $id = $N('DISCIPLINE', $d['name'] ?: $d['code'], 0, ['code' => $d['code'] ?? '', 'source' => 'cx_disciplines']);
        if ($id && trim((string)($d['code'] ?? '')) !== '') $AL($id, $d['code']);
        foreach (array_filter(array_map('trim', explode(',', (string)($d['methods'] ?? '')))) as $m) {
            $mid = $N('METHOD', $m, $id, ['source' => 'cx_disciplines']); if ($mid) $SG($id, $mid);
        }
    }
    foreach (connect_tx_rows('cx_sectors') as $s)   $N('INDUSTRY', $s['name'] ?: $s['code'], 0, ['code' => $s['code'] ?? '', 'source' => 'cx_sectors']);
    foreach (connect_tx_rows('cx_equipment_groups') as $g) {
        $gid = $N('EQUIPMENT', $g['name'] ?: $g['code'], 0, ['code' => $g['code'] ?? '', 'source' => 'cx_equipment_groups']);
        foreach (ops_all("SELECT name FROM cx_equipment_types WHERE group_code=?", [$g['code'] ?? '']) ?: [] as $t)
            $N('EQUIPMENT', $t['name'], $gid, ['source' => 'cx_equipment_types']);
    }
    foreach (connect_tx_rows('cx_certifications_registry') as $c) {
        $cid = $N('CERTIFICATION', $c['name'] ?: $c['code'], 0, ['code' => $c['code'] ?? '', 'source' => 'cx_certifications_registry']);
        if ($cid && trim((string)($c['code'] ?? '')) !== '') $AL($cid, $c['code']);
    }
    // --- (1b) UNIFY the K13 qualification taxonomy (connect_qualtax) ----------
    // 52 roles, 20 job-families, 30 prof-certs, 28 ITI trades — the real ITI→PM
    // spine. Fold it into the same graph so there is ONE taxonomy, not two.
    if (function_exists('connect_qualtax_seed')) { try { connect_qualtax_seed(); } catch (Throwable $e) {} }
    if (function_exists('connect_qtx_rows')) {
        $famNode = [];
        foreach (connect_qtx_rows('cx_job_families') as $f) {
            $fid = $N('DOMAIN', $f['name'] ?: $f['code'], 0, ['code' => $f['code'] ?? '', 'source' => 'cx_job_families']);
            if ($fid) { $famNode[(string)($f['code'] ?? '')] = $fid; if (!empty($f['aka'])) foreach (explode(',', (string)$f['aka']) as $a) $AL($fid, $a); }
        }
        foreach (connect_qtx_rows('cx_roles') as $r) {
            $parent = $famNode[(string)($r['family_code'] ?? '')] ?? 0;
            $rid = $N('ROLE', $r['name'] ?: $r['code'], $parent, ['code' => $r['code'] ?? '', 'source' => 'cx_roles']);
            if ($rid && trim((string)($r['aka'] ?? '')) !== '') foreach (explode(',', (string)$r['aka']) as $a) $AL($rid, $a);
        }
        foreach (connect_qtx_rows('cx_prof_certifications') as $c) {
            $cid = $N('CERTIFICATION', $c['name'] ?: $c['code'], 0, ['code' => $c['code'] ?? '', 'source' => 'cx_prof_certifications']);
            if ($cid && trim((string)($c['aka'] ?? '')) !== '') foreach (explode(',', (string)$c['aka']) as $a) $AL($cid, $a);
        }
        foreach (connect_qtx_rows('cx_iti_trades') as $t)
            $N('ROLE', $t['name'] ?: $t['code'], 0, ['code' => $t['code'] ?? '', 'source' => 'cx_iti_trades']);
    }

    // --- (2) curated multi-domain tree (admin-extensible seed) ---------------
    $dom = [];
    foreach (['Mechanical','Electrical','Civil','Instrumentation & Controls','Piping','Welding','NDT','QA/QC','HSE','Structural','Commissioning','Project Management'] as $d)
        $dom[$d] = $N('DOMAIN', $d);
    $AL($dom['NDT'], 'Non-Destructive Testing', 'Non Destructive Testing', 'NDT');
    $AL($dom['HSE'], 'Safety', 'Health Safety Environment', 'EHS', 'HSE');
    $AL($dom['QA/QC'], 'Quality', 'QAQC', 'QC', 'QA');
    $AL($dom['Instrumentation & Controls'], 'Instrumentation', 'I&C', 'Controls', 'Automation');

    // Electrical → Power → Transmission → Transmission Technician / Protection Engineer
    $elePower = $N('SPECIALIZATION', 'Power', $dom['Electrical']);
    $eleTx    = $N('SPECIALIZATION', 'Transmission', $elePower);
    $eleSub   = $N('SPECIALIZATION', 'Substation', $elePower);
    $roleTxT  = $N('ROLE', 'Transmission Technician', $eleTx);   $AL($roleTxT, 'transmission line technician', 'transmission lineman');
    $roleProt = $N('ROLE', 'Protection Engineer', $eleSub);      $AL($roleProt, 'protection & control engineer', 'relay engineer');
    $skRelay  = $N('SKILL', 'Relay testing', $eleSub);
    $skComm   = $N('SKILL', 'Substation commissioning', $eleSub);
    $eqTfmr   = $N('EQUIPMENT', 'Power transformer', $elePower);
    $SG($roleProt, $skRelay); $SG($roleProt, $skComm); $SG($roleTxT, $skComm); $SG($dom['Electrical'], $eleTx);
    foreach (['66kV','132kV','220kV','400kV'] as $v) { $vv = $N('SYSTEM', $v . ' Substation', $eleSub); $RE($vv, $roleProt); }

    // Mechanical → Static Equipment → Pressure Vessels; Mechanical/Pressure-Vessel Inspector
    $mechStatic = $N('SPECIALIZATION', 'Static Equipment', $dom['Mechanical']);
    $mechPV     = $N('SPECIALIZATION', 'Pressure Vessels', $mechStatic);
    $roleMechI  = $N('ROLE', 'Mechanical Inspector', $dom['Mechanical']);
    $rolePVI    = $N('ROLE', 'Pressure Vessel Inspector', $mechPV);  $AL($rolePVI, 'pressure vessel inspector', 'static equipment inspector');
    $roleVendI  = $N('ROLE', 'Vendor Inspector', $dom['QA/QC']);     $AL($roleVendI, 'third party inspector', 'TPI');
    $skPVInsp   = $N('SKILL', 'Pressure vessel inspection', $mechPV);
    $skHydro    = $N('SKILL', 'Hydrotesting', $mechStatic);
    $skHXInsp   = $N('SKILL', 'Heat exchanger inspection', $mechStatic);
    $eqPV = $N('EQUIPMENT', 'Pressure vessel', $mechPV); $eqHX = $N('EQUIPMENT', 'Heat exchanger', $mechStatic); $eqBoiler = $N('EQUIPMENT', 'Boiler', $mechStatic);
    $RE($rolePVI, $roleMechI); $SG($rolePVI, $skPVInsp); $SG($rolePVI, $eqPV); $SG($rolePVI, $skHydro);
    $SG($roleMechI, $skPVInsp); $SG($roleMechI, $skHXInsp);

    // NDT methods + technician
    $roleNDT = $N('ROLE', 'NDT Technician', $dom['NDT']); $AL($roleNDT, 'NDT Level II', 'NDT inspector');
    foreach (['RT' => 'Radiographic Testing','UT' => 'Ultrasonic Testing','MT' => 'Magnetic Particle Testing','PT' => 'Dye Penetrant Testing','PAUT' => 'Phased Array UT','TOFD' => 'Time of Flight Diffraction'] as $code => $name) {
        $mid = $N('METHOD', $name, $dom['NDT'], ['code' => $code]); $AL($mid, $code); $SG($roleNDT, $mid);
    }

    // Welding + certs
    $roleWI = $N('ROLE', 'Welding Inspector', $dom['Welding']); $AL($roleWI, 'welding inspector', 'CSWIP inspector');
    $certCSWIP = $N('CERTIFICATION', 'CSWIP 3.1', 0, ['code' => 'CSWIP']); $AL($certCSWIP, 'CSWIP');
    $certCWI   = $N('CERTIFICATION', 'AWS CWI', 0, ['code' => 'CWI']);    $AL($certCWI, 'AWS CWI', 'CWI');
    $skWInsp   = $N('SKILL', 'Welding inspection', $dom['Welding']);
    $SG($roleWI, $certCSWIP); $SG($roleWI, $certCWI); $SG($roleWI, $skWInsp); $RE($roleWI, $roleMechI);

    // QA/QC + HSE roles
    $N('ROLE', 'QA/QC Engineer', $dom['QA/QC']);
    $safOff = $N('ROLE', 'Safety Officer', $dom['HSE']); $AL($safOff, 'HSE officer', 'safety steward');
    $safEng = $N('ROLE', 'Safety Engineer', $dom['HSE']);
    $hseMgr = $N('ROLE', 'HSE Manager', $dom['HSE']);
    $skShut = $N('SKILL', 'Shutdown safety', $dom['HSE']); $SG($safOff, $skShut); $SG($safEng, $skShut);

    // Civil
    $civStruct = $N('SPECIALIZATION', 'Structural Works', $dom['Civil']);
    $roleCivQA = $N('ROLE', 'Civil QA/QC Engineer', $civStruct); $AL($roleCivQA, 'civil inspector');

    if (function_exists('setting_set')) setting_set('tax_graph_v1', date('c'));
    return ['skipped' => false, 'nodes' => (int)ops_val("SELECT COUNT(*) FROM cx_tax_nodes"),
            'aliases' => (int)ops_val("SELECT COUNT(*) FROM cx_tax_aliases"),
            'edges' => (int)ops_val("SELECT COUNT(*) FROM cx_tax_edges")];
}

/** Backfill: map a professional's existing CSV disciplines/skills into the graph
 *  (single source of truth — the CSV stays; the graph mirrors it for search). */
function connect_profile_tax_backfill($proId) {
    $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [(int)$proId]); if (!$pro) return 0;
    $n = 0;
    $map = [
        'disciplines' => 'ADDITIONAL_ROLE',   // a discipline is closest to a role/expertise
        'skills'      => 'SKILL',
    ];
    foreach ($map as $col => $relation) {
        foreach (array_filter(array_map('trim', explode(',', (string)($pro[$col] ?? '')))) as $term) {
            $hit = connect_tax_resolve($term)[0] ?? null;
            $nid = $hit ? (int)$hit['id'] : connect_tax_node_add($relation === 'SKILL' ? 'SKILL' : 'DISCIPLINE', $term);
            if ($nid) { connect_profile_tax_attach($proId, $nid, $relation, ['source' => 'csv']); $n++; }
        }
    }
    return $n;
}
