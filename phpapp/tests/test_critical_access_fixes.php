<?php
// ============================================================================
//  The four critical access defects from docs/99-gaps-and-risks.md.
//
//  Each block asserts the hole is shut AND that the legitimate path still works
//  — a gate that refuses everybody would pass a "can't get in" test and break
//  the company.
// ============================================================================

$pdo = db();
$asUser = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$signOut = function () { unset($_SESSION['uid']); current_user(true); ua(true); };

// ---------------------------------------------------------------------------
t_section('risk 1 — an unrecognised role grants nothing (it used to grant ADMIN)');

// role_defaults() is the pure function behind it, so check it directly first.
t_eq(count(role_defaults('NONSENSE_ROLE')['perms']), 0, 'an unknown role string carries no permissions');
t_eq(count(role_defaults('')['perms']), 0, 'an empty role carries no permissions');
t_eq(role_defaults('NONSENSE_ROLE')['offices'], 'OWN', 'an unknown role is scoped to its own office, not ALL');
t_ok(count(role_defaults('ADMIN')['perms']) > 50, 'the real ADMIN role is untouched and still fully privileged');
t_ok(count(role_defaults('COORDINATOR')['perms']) > 0, 'a real role is unaffected');

$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('caf_typo','Typo','COORDINATOR_TYPO',1)")->execute();
$typoUid = (int)$pdo->lastInsertId();
$asUser($typoUid);
t_eq(user_role(), UNKNOWN_ROLE, 'a typo in the role column resolves to UNKNOWN_ROLE, not ADMIN');
t_ok(!is_master(), 'an unrecognised role is not a master admin');
t_ok(!is_admin_level(), 'an unrecognised role is not management-level');
t_ok(!is_coordinator_level(), 'an unrecognised role is not coordinator-level');
t_ok(!can('settings.manage'), 'an unrecognised role cannot manage settings');
t_ok(!can('users.manage.global'), 'an unrecognised role cannot manage users');
t_ok(!can('mod.calls.view'), 'an unrecognised role holds no module');
t_eq(role_label(current_user()), 'Unrecognised role — no access', 'the screen says plainly what is wrong');

// A per-user permission list must not sneak access back in for a broken role.
$pdo->prepare("UPDATE users SET permissions=? WHERE id=?")->execute(['settings.manage,mod.calls.view', $typoUid]);
$asUser($typoUid);
t_ok(!can('settings.manage'), 'a per-user override does not rescue an unrecognised role');
t_ok(!can('mod.calls.view'), 'nor does it restore module access');

// The same login with a role the app knows works exactly as before.
$pdo->prepare("UPDATE users SET role='COORDINATOR', permissions='' WHERE id=?")->execute([$typoUid]);
$asUser($typoUid);
t_eq(user_role(), 'COORDINATOR', 'correcting the role restores the account');
t_ok(can('mod.calls.view'), 'and its normal access comes back');
$signOut();

// ---------------------------------------------------------------------------
t_section('risk 2 — business partners and purchase orders are no longer ungated');

$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client) VALUES ('CAF Client Ltd','CAF Client',1)")->execute();
$clientId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_vendor) VALUES ('CAF Vendor Ltd','CAF Vendor',1)")->execute();
$vendorId = (int)$pdo->lastInsertId();
$client = ops_one("SELECT * FROM business_partners WHERE id=?", [$clientId]);
$vendor = ops_one("SELECT * FROM business_partners WHERE id=?", [$vendorId]);

// An inspector holds neither directory module — this is the account that could
// previously create and edit clients by typing the URL.
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('CAF Ravi')")->execute();
$cafIns = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active) VALUES ('caf_insp','Ravi','INSPECTOR',?,1)")->execute([$cafIns]);
$inspUid = (int)$pdo->lastInsertId();

$asUser($inspUid);
t_ok(!can('mod.clients.view'), 'the inspector genuinely holds no clients module');
t_ok(!partner_can_view($client), 'an inspector cannot view a client record');
t_ok(!partner_can_edit($client), 'an inspector cannot edit a client record');
t_ok(!partner_can_create(), 'an inspector cannot create a partner');

// A coordinator holds clients/vendors VIEW but not EDIT: read yes, write no.
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('caf_coord','Coord','COORDINATOR',1)")->execute();
$coordUid = (int)$pdo->lastInsertId();
$asUser($coordUid);
t_ok(partner_can_view($client), 'a coordinator can view a client');
t_ok(!partner_can_edit($client), 'a coordinator cannot edit one (they hold view, not edit)');

// A branch manager holds edit on both sides of the directory.
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('caf_bm','BM','BRANCH_MANAGER',1)")->execute();
$bmUid = (int)$pdo->lastInsertId();
$asUser($bmUid);
t_ok(partner_can_view($client) && partner_can_edit($client), 'a branch manager can view and edit a client');
t_ok(partner_can_view($vendor) && partner_can_edit($vendor), 'and a vendor');
t_ok(partner_can_create(), 'and can create a new partner');

// A record that is BOTH: holding either module is enough, so nobody loses half
// the directory because a company happens to be a client as well as a vendor.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, is_vendor) VALUES ('CAF Both Ltd','CAF Both',1,1)")->execute();
$both = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)$pdo->lastInsertId()]);
t_eq(count(partner_modules($both)), 2, 'a client-and-vendor record names both modules');
$asUser($coordUid);
t_ok(partner_can_view($both), 'holding either module is enough to view a dual-role record');

// A purchase order borrows its partner's access.
$pdo->prepare("INSERT INTO partner_purchase_orders (partner_id, po_number) VALUES (?, 'CAF-PO-1')")->execute([$clientId]);
$po = ops_one("SELECT * FROM partner_purchase_orders WHERE id=?", [(int)$pdo->lastInsertId()]);
$asUser($inspUid);
t_ok(!po_can_view($po), 'an inspector cannot open a purchase order');
t_ok(!po_can_edit($po), 'nor change one');
$asUser($bmUid);
t_ok(po_can_view($po) && po_can_edit($po), 'a branch manager can open and change a purchase order');
$signOut();

// ---------------------------------------------------------------------------
t_section('risk 3 — vouchers: module gate, branch scope, and separation of duties');

$pdo->prepare("INSERT INTO offices (name) VALUES ('CAF Office A')")->execute();
$offA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name) VALUES ('CAF Office B')")->execute();
$offB = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('CAF Insp A', ?)")->execute([$offA]);
$insA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('CAF Insp B', ?)")->execute([$offB]);
$insB = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO vouchers (inspector_id, office_id, month, status, created_at) VALUES (?,?, '2026-05', 'DRAFT', ?)")->execute([$insA, $offA, date('c')]);
$vA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vouchers (inspector_id, office_id, month, status, created_at) VALUES (?,?, '2026-05', 'DRAFT', ?)")->execute([$insB, $offB, date('c')]);
$vB = (int)$pdo->lastInsertId();

// -- the module gate now covers vouchers, with an owner exception ------------
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, home_office_id, is_active) VALUES ('caf_iA','InspA','INSPECTOR',?,?,1)")->execute([$insA, $offA]);
$iaUid = (int)$pdo->lastInsertId();
$asUser($iaUid);
t_ok(!can('mod.vouchers.view'), 'an inspector holds no vouchers module');
t_ok(voucher_owned_by_me($vA) === true, 'the inspector owns their own voucher');
t_ok(voucher_owned_by_me($vB) === false, 'and does not own a colleague\'s');
t_ok(voucher_owned_by_me(0) === false, 'a missing voucher id is not owned');
// The gate must let the owner through — if it blocked, ops_require would exit.
$_GET['id'] = $vA;
foreach (['voucher', 'voucher-entry', 'voucher-save', 'voucher-status'] as $r) { ops_module_gate($r); }
unset($_GET['id']);
ops_module_gate('vouchers');   // their own list, no id
t_ok(true, 'the gate lets an engineer reach and fill their own voucher without the module');

// Every route the voucher screen actually links to or posts to must be reachable
// by the owner. Gating one of them and leaving its button on the page is the
// "menu item that refuses" fault this codebase already has enough of.
$_GET['id'] = $vA;
foreach (['voucher-print', 'voucher-csv', 'voucher-file', 'voucher-generate', 'voucher-header'] as $r) {
    ops_module_gate($r);
}
unset($_GET['id']);
t_ok(true, 'every link and form on the voucher screen is reachable by its owner');

// -- the register is scoped to the viewer's offices --------------------------
$scopedRows = function () {
    [$w, $a] = scope_office_clause("COALESCE(v.office_id, i.home_office_id)");
    return ops_all("SELECT v.id FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id WHERE $w", $a);
};
$pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, is_active) VALUES ('caf_coordA','CoordA','COORDINATOR',?,1)")->execute([$offA]);
$coordAUid = (int)$pdo->lastInsertId();
$asUser($coordAUid);
$ids = array_column($scopedRows(), 'id');
t_ok(in_array($vA, $ids), 'a coordinator sees their own office\'s voucher');
t_ok(!in_array($vB, $ids), 'and NOT another branch\'s voucher (this used to be unscoped)');

// A whole-company role still sees both — the scope narrows, it does not blind.
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('caf_dir','Dir','BUSINESS_DIRECTOR',1)")->execute();
$dirUid = (int)$pdo->lastInsertId();
$asUser($dirUid);
$ids = array_column($scopedRows(), 'id');
t_ok(in_array($vA, $ids) && in_array($vB, $ids), 'an all-offices role still sees every branch');

// -- separation of duties ----------------------------------------------------
// Coordinator A submits their own inspector's voucher, then tries to approve it.
$asUser($coordAUid);
$pdo->prepare("UPDATE vouchers SET status='SUBMITTED', submitted_by_uid=? WHERE id=?")->execute([$coordAUid, $vA]);
$vRow = ops_one("SELECT * FROM vouchers WHERE id=?", [$vA]);
t_ok(voucher_is_own_submission($vRow) === true, 'the submitter is recognised as the submitter');
t_ok(voucher_can_approve($vRow) === false, 'the person who submitted a voucher cannot approve it');

// A different coordinator-level person can.
$pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, is_active) VALUES ('caf_coordA2','CoordA2','COORDINATOR',?,1)")->execute([$offA]);
$coordA2Uid = (int)$pdo->lastInsertId();
$asUser($coordA2Uid);
$vRow = ops_one("SELECT * FROM vouchers WHERE id=?", [$vA]);
t_ok(voucher_can_approve($vRow) === true, 'a second pair of eyes at the same level still can');

// An inspector cannot approve anything, own or otherwise.
$asUser($iaUid);
t_ok(voucher_can_approve($vRow) === false, 'an engineer cannot approve a voucher');

// Only a SUBMITTED voucher can be approved.
$pdo->prepare("UPDATE vouchers SET status='DRAFT' WHERE id=?")->execute([$vA]);
$asUser($coordA2Uid);
t_ok(voucher_can_approve(ops_one("SELECT * FROM vouchers WHERE id=?", [$vA])) === false, 'a DRAFT voucher cannot be approved');

// -- accounts can record payment, and can reach the screen to do it ----------
$pdo->prepare("UPDATE vouchers SET status='APPROVED' WHERE id=?")->execute([$vA]);
$vRow = ops_one("SELECT * FROM vouchers WHERE id=?", [$vA]);
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('caf_fin','Fin','FINANCE',1)")->execute();
$finUid = (int)$pdo->lastInsertId();
$asUser($finUid);
t_ok(!is_coordinator_level(), 'Finance is deliberately not in the operations tier');
t_ok(can('finance.reconcile'), 'but Finance does hold finance.reconcile');
t_ok(voucher_can_mark_paid($vRow) === true, 'Finance — who actually pays — can now mark a voucher paid');
t_ok(can_view_voucher($vRow) === true, 'and can open the voucher in order to do it');
$asUser($coordA2Uid);
t_ok(voucher_can_mark_paid($vRow) === true, 'a manager can still mark it paid too');
$asUser($iaUid);
t_ok(voucher_can_mark_paid($vRow) === false, 'an engineer cannot mark their own claim paid');
$signOut();

// ---------------------------------------------------------------------------
t_section('risk 7 — a full permission list fits in users.permissions');

$all = implode(',', array_keys(all_permissions()));
t_ok(strlen($all) > 600, 'the full permission list is longer than the old VARCHAR(600) allowed');
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active) VALUES ('caf_wide','Wide','BRANCH_MANAGER',?,1)")->execute([$all]);
$wideUid = (int)$pdo->lastInsertId();
$back = (string)ops_val("SELECT permissions FROM users WHERE id=?", [$wideUid]);
t_eq(strlen($back), strlen($all), 'it is stored and read back whole, with nothing truncated');
t_eq(substr($back, -strlen('mod.settings.edit')), 'mod.settings.edit', 'the final key survives intact rather than being cut mid-word');
$asUser($wideUid);
t_ok(can('mod.settings.edit'), 'and the last permission in the list actually works');
$signOut();
