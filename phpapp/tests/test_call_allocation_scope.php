<?php
// Cross-office rule (owner requirement): the EXECUTING office allocates the call;
// the CONTRACTING office sees it (and invoices it) but cannot allocate. Tested
// through the pure decision helper so it does not depend on a live sign-in.
t_section('cross-office allocation rights');

$AMD = 10;   // executing office
$MUM = 20;   // contracting office

// call_exec_office reads the executing office, falling back to the IBO office.
t_eq(call_exec_office(['executing_office_id' => $AMD, 'ibo_office_id' => $MUM]), $AMD, 'the executing office is read from the call');
t_eq(call_exec_office(['executing_office_id' => 0, 'ibo_office_id' => $MUM]), $MUM, 'it falls back to the IBO/contracting office');
t_eq(call_exec_office([]), 0, 'an unset call reports no executing office');

// The core rule, cross-office call executed by Ahmedabad (AMD):
t_ok(call_alloc_allowed($AMD, [$AMD], true), 'the EXECUTING office may allocate');
t_ok(!call_alloc_allowed($AMD, [$MUM], true), 'the CONTRACTING office may NOT allocate — the key rule');
t_ok(call_alloc_allowed($AMD, [$AMD, $MUM], true), 'a user scoped to both offices may allocate');

// Same office: contracting == executing == Ahmedabad → that office allocates.
t_ok(call_alloc_allowed($AMD, [$AMD], true), 'a same-office call is allocated by that office');

// Head office / all-office scope may always allocate.
t_ok(call_alloc_allowed($AMD, 'ALL', true), 'all-office (HO/admin) scope may always allocate');

// A call with no executing office set is not blocked (nothing to enforce).
t_ok(call_alloc_allowed(0, [$MUM], true), 'a call with no executing office is not blocked');

// Only coordinator-level roles allocate at all.
t_ok(!call_alloc_allowed($AMD, [$AMD], false), 'a non-coordinator cannot allocate, even in the executing office');

// Belt-and-braces: the register visibility SQL ORs both offices, so a refactor
// cannot silently drop the contracting side (the office that must still see it).
$src = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, 'c.executing_office_id') !== false
  && strpos($src, 'OR c.ibo_office_id IN') !== false,
  'the calls register scopes on BOTH executing and contracting office');
