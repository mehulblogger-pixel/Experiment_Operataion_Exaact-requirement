<?php
// Gap-2 (EXTEND) — the graph taxonomy must count when matching INTERNAL INSPECTORS, not only
// marketplace professionals. Inspectors carry no cx_profile_tax rows, so the matcher's inspector
// pool silently degraded to plain substring tokens. connect_match_tax_bonus_text() now gives the
// inspector pool the same concept/synonym/hierarchical bonus, resolved from the inspector's skills
// + role text. Read-only.
t_section('taxonomy counts for inspector matching (Gap 2)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_tax_graph_migrate();

    // A distinctive taxonomy concept (unique so it can't collide with the seeded graph).
    $nodeId = connect_tax_node_add('ROLE', 'Zephyr Inspection');
    t_ok($nodeId > 0, 'a taxonomy node is seeded');
    // resolve confirms the alias is searchable
    $hits = connect_tax_resolve('zephyr inspection');
    t_ok((bool)array_filter($hits, fn($h) => (int)$h['id'] === $nodeId), 'the concept resolves in the graph');

    // A requirement whose title carries the concept → its weighted node set includes our node.
    $req = ['id' => 0, 'title' => 'Zephyr Inspection Engineer for FAT', 'discipline_code' => '', 'location' => ''];
    $reqNodes = connect_match_req_nodes($req);
    t_ok(isset($reqNodes[$nodeId]), 'the requirement resolves to the concept node');

    // The text bonus: an inspector whose skills demonstrably cover the concept scores a bonus + reason.
    [$bonus, $reasons] = connect_match_tax_bonus_text('Senior Zephyr Inspection lead, CSWIP', $reqNodes);
    t_ok($bonus > 0, 'a matching inspector text earns a taxonomy bonus: ' . $bonus);
    t_ok((bool)array_filter($reasons, fn($r) => strpos($r, 'Zephyr Inspection') !== false), 'the bonus carries the matched-concept reason');
    // Unrelated text earns nothing.
    [$bonus0, $reasons0] = connect_match_tax_bonus_text('Banana cultivation and AutoCAD drafting', $reqNodes);
    t_eq($bonus0, 0, 'unrelated inspector text earns no taxonomy bonus');
    t_eq(count($reasons0), 0, 'unrelated text carries no concept reasons');
    // Empty requirement nodes → no bonus (safe when the requirement resolves to nothing).
    t_eq(connect_match_tax_bonus_text('Zephyr Inspection', [])[0], 0, 'no requirement concepts → no bonus');

    // Integration: the matcher ranks the concept-matching inspector above a token-only one.
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Tax Match Co',1,'ACTIVE')")->execute();
    $client = (int)db()->lastInsertId();
    $rid = (int)cx_requirement_create(['title' => 'Zephyr Inspection Engineer', 'poster_party_id' => $client, 'poster_name' => 'TaxMatch',
        'discipline_code' => '', 'location' => 'Hazira', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'rate_min' => 8000, 'rate_max' => 14000, 'rate_unit' => 'day'], true);
    db()->prepare("INSERT INTO inspectors (name, skills, designation, status, sbu, created_at) VALUES ('Zephyr Ace','Zephyr Inspection, CSWIP','Inspector','ACTIVE','IND',?)")->execute([date('c')]);
    $matchInsp = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name, skills, designation, status, sbu, created_at) VALUES ('Unrelated Bob','Gardening, Cooking','Helper','ACTIVE','IND',?)")->execute([date('c')]);
    $otherInsp = (int)db()->lastInsertId();

    $reqRow = cx_requirement_get($rid);
    $rows = connect_match_for_requirement($reqRow, 200);
    $byId = []; foreach ($rows as $r) if (($r['kind'] ?? '') === 'inspector') $byId[(int)$r['id']] = $r;
    t_ok(isset($byId[$matchInsp]) && isset($byId[$otherInsp]), 'both inspectors appear in the match pool');
    t_ok((int)$byId[$matchInsp]['score'] > (int)$byId[$otherInsp]['score'], 'the concept-matching inspector outranks the unrelated one');
    t_ok((bool)array_filter($byId[$matchInsp]['reasons'] ?? [], fn($r) => strpos($r, 'Zephyr Inspection') !== false),
        'the matching inspector card shows the taxonomy-concept reason');

    // read-only: matching mutated no inspector row
    t_eq((string)ops_val("SELECT skills FROM inspectors WHERE id=?", [$matchInsp]), 'Zephyr Inspection, CSWIP', 'the inspector row is untouched by matching');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
