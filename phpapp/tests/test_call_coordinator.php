<?php
// A call is forwarded to a NAMED coordinator, not just an office. An office has
// several coordinators, and a call handed to "the office" lands in a shared
// inbox where — in the user's words — "it went to one coordinator which I am
// unable to find." Naming the owner is what makes a raised call findable.
t_section('a call names the coordinator it is forwarded to');

$pdo = db();

// Two offices, each with its own coordinators, plus one inspector who must NOT
// be offered as a coordinator.
$pdo->prepare("INSERT INTO offices (name) VALUES ('CC Head Office')")->execute();
$offA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name) VALUES ('CC Branch Office')")->execute();
$offB = (int)$pdo->lastInsertId();

$mk = function ($user, $first, $role, $office) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,home_office_id,is_active) VALUES (?,?,?,?,1)")
        ->execute([$user, $first, $role, $office]);
    return (int)$pdo->lastInsertId();
};
$coordA1 = $mk('cc_a1', 'Anita',  'COORDINATOR',      $offA);
$coordA2 = $mk('cc_a2', 'Arjun',  'ASST_MANAGER',     $offA);
$coordB1 = $mk('cc_b1', 'Bhavna', 'COORDINATOR',      $offB);
$omA     = $mk('cc_om', 'Om',     'OPERATION_MANAGER', $offA);
$mk('cc_insp', 'Ravi', 'INSPECTOR', $offA);   // an inspector — never a coordinator

// office_coordinators lists the office's coordinator-level users, not inspectors.
$listA = office_coordinators($offA);
$idsA  = array_map(fn($c) => (int)$c['id'], $listA);
$hasName = function ($list, $name) {
    foreach ($list as $c) if (stripos((string)($c['name'] ?? ''), $name) !== false) return true;
    return false;
};
t_ok(in_array($coordA1, $idsA, true), 'a COORDINATOR in the office is offered');
t_ok(in_array($coordA2, $idsA, true), 'an ASST_MANAGER in the office is offered');
t_ok(in_array($omA, $idsA, true), 'the OPERATION_MANAGER in the office is offered');
t_ok(!$hasName($listA, 'Ravi'), 'an inspector is never offered as a coordinator');
// It is office-scoped: office B's coordinator is not in office A's list.
t_ok(!in_array($coordB1, $idsA, true), 'a coordinator from another office is not offered');
// The plain "coordinator" designation sorts first.
t_ok((int)$listA[0]['id'] === $coordA1, 'the plain coordinator is listed first');

// The map for the call form keys the lists by office id.
$map = call_office_coordinators_map([['id' => $offA], ['id' => $offB]]);
t_ok(isset($map[$offA]) && isset($map[$offB]), 'the coordinator map is keyed by office');
t_ok(count($map[$offB]) === 1, 'office B has exactly its one coordinator');

// call_coordinator_name resolves the stored id to a display name (and '' when none).
$pdo->prepare("INSERT INTO calls (call_code, executing_office_id, coordinator_id, created_at) VALUES ('CC-1', ?, ?, ?)")
    ->execute([$offB, $coordB1, date('c')]);
$call = ops_one("SELECT * FROM calls WHERE call_code='CC-1'");
t_ok(call_coordinator_name($call) === 'Bhavna', 'the named coordinator resolves to a display name');
$pdo->prepare("INSERT INTO calls (call_code, executing_office_id, created_at) VALUES ('CC-2', ?, ?)")
    ->execute([$offB, date('c')]);
$call2 = ops_one("SELECT * FROM calls WHERE call_code='CC-2'");
t_ok(call_coordinator_name($call2) === '', 'a call with no coordinator resolves to an empty name');

// coordinator_id is a saved field, normalised to NULL when blank (never 0).
t_ok(in_array('coordinator_id', call_save_fields(), true), 'coordinator_id is a saved call field');
t_ok(nzc_call('coordinator_id', '') === null, 'a blank coordinator saves as NULL, not 0');
t_ok(nzc_call('coordinator_id', (string)$coordA1) === $coordA1, 'a chosen coordinator saves as its id');
