<?php
// Taxonomy graph ADMIN — add/edit/retire/relate/alias without code.
t_section('connect taxonomy admin CRUD (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_tax_graph_migrate();
    // add
    $a = connect_tax_node_add('ROLE', 'Rope Access Inspector ZZ');
    $b = connect_tax_node_add('SKILL', 'IRATA Level 1 ZZ');
    t_ok($a && $b, 'nodes add');
    // rename keeps the old name as a synonym
    [$ok,$m] = connect_tax_node_update($a, ['name'=>'Rope Access Technician ZZ']);
    t_ok($ok, 'node renamed');
    $al = array_map(fn($x)=>tax_norm($x['alias']), connect_tax_aliases_for($a));
    t_ok(in_array('rope access inspector zz',$al,true) && in_array('rope access technician zz',$al,true), 'old and new names are both synonyms');
    t_ok((connect_tax_resolve('rope access inspector zz')[0]['id'] ?? 0) === $a, 'the old name still resolves to the node');
    // rename collision refused
    connect_tax_node_add('ROLE','Existing Role ZZ');
    [$bad] = connect_tax_node_update($a, ['name'=>'Existing Role ZZ']);
    t_ok($bad === false, 'a colliding rename is refused');
    // alias add/del
    connect_tax_alias_add($a, 'RAT ZZ');
    t_ok((connect_tax_resolve('RAT ZZ')[0]['id'] ?? 0) === $a, 'a new alias resolves');
    $rat = null; foreach(connect_tax_aliases_for($a) as $x) if(tax_norm($x['alias'])==='rat zz') $rat=(int)$x['id'];
    connect_tax_alias_delete($rat);
    t_ok(empty(connect_tax_resolve('RAT ZZ')), 'a deleted alias no longer resolves');
    // relate + edge delete (both directions)
    connect_tax_relate($a, $b, 'SUGGESTS');
    t_ok(count(connect_tax_edges_for($a)) >= 1, 'relation created');
    connect_tax_relate($a, $b, 'RELATED');
    $eid = (int)connect_tax_edges_for($a)[0]['id'];
    connect_tax_edge_delete($eid);
    // retire hides from drill-down but keeps history
    connect_tax_node_set_status($b, 'RETIRED');
    t_ok(!in_array($b, array_map(fn($x)=>(int)$x['id'], connect_tax_all('SKILL')), true), 'a retired node drops out of active listings');
    connect_tax_node_set_status($b, 'ACTIVE');
    t_ok(in_array($b, array_map(fn($x)=>(int)$x['id'], connect_tax_all('SKILL')), true), 'reactivating brings it back');
    // admin listing filter
    $list = connect_tax_admin_nodes('ROLE','rope access');
    t_ok(count($list) >= 1, 'admin listing filters by kind + text');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
