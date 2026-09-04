<?php
// ============================================================================
//  Connect K0+ — the universal taxonomy GRAPH (backbone of the passport).
//
//  Proves the reusable foundation: the flat masters AND the K13 qualification
//  taxonomy are unified into one node graph; aliases/synonyms resolve; a pick
//  suggests related concepts; ONE professional carries MANY nodes; and a single
//  keyword discovers professionals across roles, skills, equipment and certs.
//  The graph is built at boot (connect_tax_generalize), so it is already present.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect universal taxonomy graph (K0+)');

connect_tax_graph_migrate();

// --- the graph was generalized at boot from BOTH taxonomies + curated tree ---
$byKind = [];
foreach (ops_all("SELECT kind, COUNT(*) c FROM cx_tax_nodes GROUP BY kind") ?: [] as $r) $byKind[strtoupper($r['kind'])] = (int)$r['c'];
t_ok((int)ops_val("SELECT COUNT(*) FROM cx_tax_nodes") > 150, 'the graph holds a real node set (unified masters + curated)');
foreach (['DOMAIN','DISCIPLINE','ROLE','CERTIFICATION','EQUIPMENT','METHOD','INDUSTRY'] as $k)
    t_ok(($byKind[$k] ?? 0) > 0, "graph has $k nodes");
t_ok(($byKind['ROLE'] ?? 0) >= 50, 'the K13 role spine (ITI→PM) was folded in — many ROLE nodes');

// --- one-keyword resolve reaches the right canonical concept -----------------
$first = function ($term, $kind = '') {
    foreach (connect_tax_resolve($term) as $h) if ($kind === '' || strtoupper($h['kind']) === strtoupper($kind)) return $h;
    return null;
};
t_ok($first('pressure vessel inspector', 'ROLE') !== null, "'pressure vessel inspector' resolves to a ROLE");
t_ok($first('transmission technician', 'ROLE') !== null, "'transmission technician' resolves to a ROLE");
t_ok($first('welding inspector', 'ROLE') !== null, "'welding inspector' resolves to a ROLE");
t_ok($first('CSWIP', 'CERTIFICATION') !== null, "'CSWIP' resolves to a CERTIFICATION");
t_ok($first('RT', 'METHOD') !== null, "the abbreviation 'RT' resolves to an NDT METHOD");

// --- synonyms/aliases collapse to the same node ------------------------------
$ndtA = $first('NDT', 'DOMAIN'); $ndtB = $first('non destructive testing', 'DOMAIN');
t_ok($ndtA && $ndtB && (int)$ndtA['id'] === (int)$ndtB['id'], "'NDT' and 'non destructive testing' resolve to the SAME node");

// --- a pick suggests related concepts (NDT → RT/UT/MT/PT) --------------------
$ndtRole = $first('NDT Technician', 'ROLE');
$sug = $ndtRole ? array_map(fn($n) => tax_norm($n['name']), connect_tax_suggest((int)$ndtRole['id'])) : [];
foreach (['radiographic testing','ultrasonic testing','magnetic particle testing','dye penetrant testing'] as $m)
    t_ok(in_array($m, $sug, true), "NDT Technician suggests $m");

// --- ONE professional, MANY nodes; multi-discipline discovery ----------------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,verification_tier,disciplines,skills,created_at)
                   VALUES ('multi@pro.test','Multi Skill',1,'proven','NDT','Welding inspection, Hydrotesting',?)")->execute([date('c')]);
    $pid = (int)db()->lastInsertId();
    $pvi = (int)$first('pressure vessel inspector', 'ROLE')['id'];
    $wi  = (int)$first('welding inspector', 'ROLE')['id'];
    $ut  = (int)$first('ultrasonic testing', 'METHOD')['id'];
    connect_profile_tax_attach($pid, $pvi, 'PRIMARY_ROLE');
    connect_profile_tax_attach($pid, $wi,  'ADDITIONAL_ROLE');
    connect_profile_tax_attach($pid, $ut,  'SKILL', ['competency' => 'EXPERT', 'years' => 8]);
    t_eq(count(connect_profile_tax_for($pid)), 3, 'one professional carries three taxonomy nodes (multi-discipline)');

    // a single keyword finds them, via role OR skill OR related concept
    $byRole = connect_tax_find_professionals('pressure vessel inspector');
    t_ok(!empty($byRole) && (int)$byRole[0]['pro_id'] === $pid, "search 'pressure vessel inspector' finds the professional");
    $byWeld = connect_tax_find_professionals('welding inspector');
    t_ok(in_array($pid, array_map(fn($r) => (int)$r['pro_id'], $byWeld), true), "the same person is found by 'welding inspector' too");
    $byUt   = connect_tax_find_professionals('ultrasonic testing');
    t_ok(in_array($pid, array_map(fn($r) => (int)$r['pro_id'], $byUt), true), "and by the skill 'ultrasonic testing'");

    // --- CSV backfill: existing disciplines/skills become searchable nodes ----
    $n = connect_profile_tax_backfill($pid);
    t_ok($n >= 2, 'the existing CSV disciplines/skills backfill into the graph');
    // idempotent attach — no duplicate rows for the same (pro,node,relation)
    connect_profile_tax_attach($pid, $pvi, 'PRIMARY_ROLE');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_profile_tax WHERE pro_id=? AND node_id=? AND relation='PRIMARY_ROLE'", [$pid, $pvi]), 1, 'attaching the same node twice does not duplicate');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// --- node dedupe: re-adding a node by name returns the same id ---------------
$own2 = !db()->inTransaction();
if ($own2) db()->beginTransaction();
try {
    $a = connect_tax_node_add('SKILL', 'Radiographic Testing Xyz Unique');
    $b = connect_tax_node_add('SKILL', 'radiographic testing xyz unique');   // different case → same slug
    t_eq($a, $b, 'nodes dedupe by kind+slug (case-insensitive)');
} finally { if ($own2 && db()->inTransaction()) db()->rollBack(); }
