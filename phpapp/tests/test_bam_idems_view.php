<?php
// R8 — BRANCH_APP_MANAGER holds the IDEMS configuration permissions
// (idems.type.manage, idems.timestamp.edit) but had no mod.idems.view, so there was
// no Reporting rail item and the config screens were reachable only via Admin tiles.
// The role now gets read access to the IDEMS (Reporting) module.
t_section('BRANCH_APP_MANAGER can see the Reporting (IDEMS) module');

$def = role_defaults('BRANCH_APP_MANAGER')['perms'];
t_ok(in_array('mod.idems.view', $def, true), 'the role default now grants mod.idems.view');
// Read-only: it must NOT silently gain edit on the module.
t_ok(!in_array('mod.idems.edit', $def, true), 'the role gets read access only, not edit');
// The config permissions it already had are unchanged.
t_ok(in_array('idems.type.manage', $def, true) && in_array('idems.timestamp.edit', $def, true),
    'its existing IDEMS config permissions are retained');

// A real BRANCH_APP_MANAGER login resolves the view permission through the gate.
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('r8_bam','Bam','BRANCH_APP_MANAGER',1)")->execute();
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid; current_user(true); ua(true);
t_ok(can('mod.idems.view') === true, 'a BRANCH_APP_MANAGER can view the IDEMS module');
t_ok(can('mod.idems.edit') === false, 'a BRANCH_APP_MANAGER cannot edit IDEMS records');
unset($_SESSION['uid']); current_user(true); ua(true);
