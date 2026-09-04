<?php
// Supplier-type filter (Stage 4 / §17). A client can narrow the pool by how a
// professional is supplied — individuals, or through a supplier company of a
// given kind. This tests the match predicate the search applies to each card.
t_section('supplier-type search filter');

t_ok(count(connect_supplier_filter_options()) >= 5, 'the filter offers the supplier options');

$indiv = ['channel' => 'INDIVIDUAL', 'type' => 'Individual'];
$free  = ['channel' => 'ORG', 'type' => 'Freelance Resource Supplier'];
$man   = ['channel' => 'ORG', 'type' => 'Technical Manpower Supplier'];
$tpia  = ['channel' => 'ORG', 'type' => 'TPIA Supplier'];

// 'Any' matches everything
t_ok(connect_supplier_filter_match($indiv, '') && connect_supplier_filter_match($tpia, ''), 'an empty filter matches everyone');

// Individuals only
t_ok(connect_supplier_filter_match($indiv, 'INDIVIDUAL'), 'Individuals-only matches an individual');
t_ok(!connect_supplier_filter_match($free, 'INDIVIDUAL'), 'Individuals-only excludes an org-supplied pro');

// Through a supplier company (any org)
t_ok(connect_supplier_filter_match($man, 'ORG') && connect_supplier_filter_match($tpia, 'ORG'), 'ORG matches any org-supplied pro');
t_ok(!connect_supplier_filter_match($indiv, 'ORG'), 'ORG excludes an individual');

// Specific supplier kinds
t_ok(connect_supplier_filter_match($free, 'FREELANCE') && !connect_supplier_filter_match($man, 'FREELANCE'), 'FREELANCE matches only freelance suppliers');
t_ok(connect_supplier_filter_match($man, 'MANPOWER') && !connect_supplier_filter_match($tpia, 'MANPOWER'), 'MANPOWER matches only manpower suppliers');
t_ok(connect_supplier_filter_match($tpia, 'TPIA') && !connect_supplier_filter_match($free, 'TPIA'), 'TPIA matches only TPIA suppliers');
