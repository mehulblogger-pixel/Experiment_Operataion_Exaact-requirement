<?php
// ============================================================================
//  ADR-001 — "Recruitment may only start from an approved hiring request."
//
//  The owner decided the question ADR-001 left open. These tests hold the three
//  halves of that decision apart, because the risk is not in refusing the direct
//  route — it is in refusing too much:
//
//    1. ENFORCED   a NEW requisition cannot be raised directly, by any door.
//    2. UNTOUCHED  requisitions that ALREADY EXIST stay fully workable. A
//                  requirement raised before the policy is not stranded and is
//                  not retro-fitted with an invented approval (ADR-001 §1).
//    3. REVERSIBLE a workspace whose authorisation is its client's order turns
//                  the policy off and the direct route works again.
//
//  The governed route must never be blocked by any of this — gating it would
//  close the only door left.
// ============================================================================

t_section('ADR-001 — recruitment starts from an approved request');

// The policy is a workspace setting, so each block states the world it tests in
// and puts it back. A leaked setting would silently change every later file.
$adrSet = function ($on) { setting_set('requisition_requires_request', $on ? '1' : '0'); };
$adrWas = function_exists('setting_get') ? (string) setting_get('requisition_requires_request', '1') : '1';

t_nothrow('the policy is ENFORCED by default — the owner\'s decision', function () {
    // A brand-new workspace has no stored value at all; the default is what
    // decides, and the default is the decision. settings_cache() is the real
    // read path and is returned by reference, so unsetting the key there is
    // exactly the state a fresh workspace is in.
    db()->prepare("DELETE FROM settings WHERE skey=?")->execute(['requisition_requires_request']);
    $c = &settings_cache(); unset($c['requisition_requires_request']);
    t_ok(!hreq_direct_path_allowed(), 'with nothing configured, the direct route is refused');
    t_ok(hreq_direct_path_block_reason() !== '', 'and it says why');
});

t_nothrow('the refusal tells the person what to do instead', function () use ($adrSet) {
    $adrSet(true);
    $why = hreq_direct_path_block_reason();
    t_ok(stripos($why, 'approved') !== false, 'it names the approval');
    t_ok(stripos($why, 'start recruiting') !== false, 'and names the button that does the job');
    t_ok(strlen($why) > 60, 'it is a sentence, not a shrug');
});

t_nothrow('switching the policy OFF restores the direct route', function () use ($adrSet) {
    $adrSet(false);
    t_ok(hreq_direct_path_allowed(), 'a workspace authorised by its client\'s order may still raise directly');
    t_eq(hreq_direct_path_block_reason(), '', 'and nothing is refused');
    $adrSet(true);
    t_ok(!hreq_direct_path_allowed(), 'switching it back on refuses again');
});

// ---------------------------------------------------------------------------
//  THE WRITE PATHS — every door that CREATES a requisition asks the policy.
//  These are the assertions that fail if somebody adds a third door later.
// ---------------------------------------------------------------------------
t_nothrow('every door that creates a requisition asks the policy', function () {
    $root = dirname(__DIR__);
    $ops  = (string) @file_get_contents($root . '/lib/ops.php');
    $pc   = (string) @file_get_contents($root . '/lib/projcosting.php');
    $hr   = (string) @file_get_contents($root . '/lib/hiringreq.php');

    // The direct form: asked in the CREATE branch, next to the INSERT.
    t_ok(preg_match('/hreq_direct_path_block_reason\(\).*?INSERT INTO requisitions/s', $ops) === 1,
        'the direct requisition form asks before it inserts');
    // Project costing: the route's own comment calls it the direct path.
    t_ok(preg_match('/function pc_make_requisition.*?hreq_direct_path_block_reason/s', $pc) === 1,
        'project costing → requirement asks too, so the policy has no side door');
    // The governed route must NOT be gated — it is the only way in once the
    // policy is on. A mutation that "helpfully" gates it would strand everybody.
    t_ok(preg_match('/function hreq_to_requisition.*?hreq_direct_path_block_reason/s', $hr) !== 1,
        'the APPROVED route is never blocked by the policy');
});

// ---------------------------------------------------------------------------
//  THE PART THAT MATTERS MOST — existing requirements are not collateral.
// ---------------------------------------------------------------------------
t_nothrow('a requisition raised BEFORE the policy stays fully workable', function () use ($adrSet) {
    $adrSet(false);                                    // the world it was raised in
    db()->prepare("INSERT INTO requisitions (req_code, designation, quantity, status, hiring_request_id, created_at)
                   VALUES ('ADR-LEGACY-1','Inspector',2,'OPEN',NULL,?)")->execute([date('c')]);
    $rid = (int) db()->lastInsertId();
    t_ok($rid > 0, 'a direct requisition existed before the policy was switched on');

    $adrSet(true);                                     // the owner switches it on

    // It must still be readable, editable and recruitable. The M4 execution
    // boundary is the thing that decides whether recruitment may spend it, and
    // a direct requisition has always been allowed to proceed there (§16).
    $row = ops_one("SELECT * FROM requisitions WHERE id=?", [$rid]);
    t_ok(!empty($row), 'it is still there');
    t_eq((string) $row['status'], 'OPEN', 'still open');

    t_nothrow('it can still be edited', function () use ($rid) {
        db()->prepare("UPDATE requisitions SET quantity=3 WHERE id=?")->execute([$rid]);
        t_eq((int) ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rid]), 3, 'the edit saved');
    });

    if (function_exists('hreq_req_block_reason')) {
        t_eq(hreq_req_block_reason($rid), '',
            'recruitment may still spend it — no approval is invented for it, and none is demanded');
    }

    db()->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rid]);
});

t_nothrow('the policy refuses only CREATE, never EDIT', function () {
    $root = dirname(__DIR__);
    $ops  = (string) @file_get_contents($root . '/lib/ops.php');
    // The guard sits after the create/edit fork, in the else branch that inserts.
    // If it moved above the fork it would refuse edits too, which is the outage
    // this whole test file exists to prevent.
    // Find the guard that sits beside the INSERT — not the friendlier one on the
    // route, which deliberately comes earlier so nobody fills in a dead wizard.
    $ins = strpos($ops, 'INSERT INTO requisitions (');
    $cut = $ins !== false ? strrpos(substr($ops, 0, $ins), 'hreq_direct_path_block_reason()') : false;
    $upd = strpos($ops, 'flash("Requisition {$req[\'req_code\']} updated.")');
    t_ok($ins !== false && $cut !== false && $upd !== false && $upd < $cut,
        'the create-branch guard is below the update branch — an edit never reaches it');
});

t_nothrow('the settings screen can turn it off again', function () {
    $root = dirname(__DIR__);
    $view = (string) @file_get_contents($root . '/views/ops/settings.php');
    $ops  = (string) @file_get_contents($root . '/lib/ops.php');
    t_ok(strpos($view, 'requisition_requires_request') !== false,
        'the policy is on the settings screen, so it is not developer-only');
    t_ok(strpos($ops, "setting_set('requisition_requires_request'") !== false,
        'and the screen actually saves it');
    t_ok(strpos($ops, "\$_POST['recruit_policy_form']") !== false,
        'guarded by a form marker, so an unrelated save cannot silently switch it');
});

// ---- put the workspace back exactly as this file found it ------------------
t_nothrow('the policy setting is restored for the next test file', function () use ($adrWas) {
    setting_set('requisition_requires_request', $adrWas);
    t_eq((string) setting_get('requisition_requires_request', '1'), $adrWas, 'restored');
});
