<?php
// ============================================================================
//  THE SCOPE PLAYBOOK                                  (UAT 1.5.4 · H10 · R-30)
//
//  The owner asked for this in so many words: "we need to create a separate
//  full playbook to test the scope on basis of department, SBU, office,
//  designation etc." What they had found was that a branch coordinator could
//  see every branch's work — "Even Mumbai office coordinator has all access",
//  "All things are available to other offices coordinator including the new
//  inspection call."
//
//  THE DEFECT, measured rather than argued:
//
//      else $offices = $u['home_office_id'] ? [(int)$u['home_office_id']] : 'ALL';
//
//  A role the permission matrix confines to its OWN branch — Coordinator,
//  Inspector, Branch Manager — received EVERY branch the moment nobody filled
//  in their home office. The comment on that line said "OWN default". It was
//  the opposite. Nothing anywhere said so, and the first time a business would
//  learn of it is when one branch reads another's commercial terms.
//
//  Two further holes were in the clause builders the fix feeds, and they had to
//  be closed in the same change or the fix would have been cosmetic: an empty
//  office list was read as "no filter" by scope_office_clause(), scope_clause(),
//  scope_allows() and scope_office_allows() alike.
//
//  WHY THIS FILE IS DIFFERENT FROM test_role_scope_matrix.php: that one asserts
//  what the ROLE DEFAULTS say. This one asserts what a PERSON actually receives
//  and what a register actually returns — which is where the two had parted.
// ============================================================================

t_section('Scope playbook — what each kind of person actually receives');

$pdo = db();
$origSess = $_SESSION;
$keep = ['u' => [], 'o' => [], 'c' => []];

//  Two branches, so "the other branch" is a real place and not a hypothetical.
foreach ([[981, 'Playbook North'], [982, 'Playbook South']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $keep['o'][] = $o[0]; }
    catch (Throwable $e) {}
}
$mkUser = function ($role, $home, $scope = '') use ($pdo, &$keep) {
    $un = 'pb_' . strtolower($role) . '_' . bin2hex(random_bytes(3));
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?,1,0,?,?)")->execute([$un, 'Playbook', $role, $home, $scope]);
    $id = (int) $pdo->lastInsertId(); $keep['u'][] = $id; return $id;
};
$as = function ($id) { $_SESSION['uid'] = $id; current_user(true); ua(true); };
$scopeOf = function ($id) use ($as) { $as($id); $o = scope_offices(); return $o === 'ALL' ? 'ALL' : implode(',', (array) $o); };

// ---------------------------------------------------------------------------
//  1. THE DEFECT ITSELF. A branch role with no branch must not inherit the
//     company. This is the assertion the whole file exists for.
// ---------------------------------------------------------------------------
foreach (['COORDINATOR', 'INSPECTOR', 'BRANCH_MANAGER'] as $role) {
    $u = $mkUser($role, null, '');
    t_eq($scopeOf($u), '', "*** $role with no branch set sees NO office — never every office");
    t_ok(scope_needs_home(), "*** …and the account is flagged as needing a branch, not left silent");
}
//  The same roles, set up properly, are confined to their own branch.
foreach (['COORDINATOR', 'INSPECTOR', 'BRANCH_MANAGER'] as $role) {
    $u = $mkUser($role, 981, '');
    t_eq($scopeOf($u), '981', "$role homed to North is confined to North");
    t_ok(!scope_needs_home(), "…and is not flagged — nothing is wrong with this account");
}
//  An explicit scope still wins, including an explicit company-wide one. A
//  business that deliberately widens somebody must not be overridden.
$wide = $mkUser('COORDINATOR', null, 'ALL');
t_eq($scopeOf($wide), 'ALL', 'an explicit company-wide scope is still honoured');
$two = $mkUser('COORDINATOR', 981, '981,982');
t_eq($scopeOf($two), '981,982', 'an explicit two-branch scope is still honoured');
//  Finance is company-wide BY DESIGN (docs/02-permission-matrix.md). Recorded
//  here so a future tightening cannot quietly take it away.
$fin = $mkUser('FINANCE', 981, '');
t_eq($scopeOf($fin), 'ALL', '*** FINANCE stays company-wide — the matrix says it reconciles across offices');
t_ok(!scope_needs_home(), '…and is never flagged as misconfigured for having no branch');

// ---------------------------------------------------------------------------
//  2. THE CLAUSE BUILDERS AGREE WITH THE SCOPE. A fix to ua() is cosmetic if
//     the four things that read it still answer "no filter" for an empty list.
// ---------------------------------------------------------------------------
$lost = $mkUser('COORDINATOR', null, '');
$as($lost);
t_eq(scope_office_clause('office_id')[0], '1=0', '*** a register returns nothing for an unplaced person');
t_eq(scope_clause('executing_office_id', 'sbu')[0], '1=0', '*** …and so does an office+unit register');
t_ok(!scope_allows(981), '*** …and a single record in North is refused');
t_ok(!scope_allows(982), '*** …as is one in South');
t_ok(!scope_office_allows(981), '*** …through the office-only guard too');
//  The unassigned-record rule still holds for a properly placed person: a
//  complaint nobody filed to a branch stays visible, or it gets lost.
$north = $mkUser('COORDINATOR', 981, '');
$as($north);
t_ok(scope_office_allows(null), 'a record with no branch is still visible to a branch user — it must not get lost');
t_ok(scope_office_allows(981),  '…its own branch is visible');
t_ok(!scope_office_allows(982), '…another branch is not');

// ---------------------------------------------------------------------------
//  3. NO SIGNED-IN PERSON IS NOT A MISCONFIGURED PERSON.
//
//     The nightly export, the Tally run and the CLI have always run unscoped
//     and authorise themselves. Reading their empty scope as "see nothing"
//     turned a working credit-note export into an empty file — measured, and
//     the reason this asks scope_needs_home() rather than merely "is it empty".
// ---------------------------------------------------------------------------
$_SESSION['uid'] = 0; current_user(true); ua(true);
t_ok(!scope_needs_home(), 'with nobody signed in there is no misconfigured account to report');
t_eq(scope_office_clause('office_id')[0], '1=1', '*** a CLI / export run is not scoped to nothing');

// ---------------------------------------------------------------------------
//  4. A REGISTER, END TO END. The clause is one thing; what a list returns is
//     what the owner actually saw. One call in each branch, then ask.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
$mkCall = function ($office, $code) use ($pdo, &$keep) {
    try {
        $pdo->prepare("INSERT INTO calls (call_code, status, executing_office_id, ibo_office_id, created_at)
                       VALUES (?,'OPEN',?,?,?)")->execute([$code, $office, $office, date('c')]);
        $keep['c'][] = $code;
    } catch (Throwable $e) {}
};
$mkCall(981, 'PB-NORTH-1');
$mkCall(982, 'PB-SOUTH-1');
$visible = function ($uid) use ($as) {
    $as($uid);
    [$w, $a] = scope_clause('executing_office_id', 'sbu');
    try { $rows = ops_all("SELECT call_code FROM calls WHERE call_code LIKE 'PB-%' AND ($w)", $a); }
    catch (Throwable $e) { return ['ERR:' . $e->getMessage()]; }
    $out = array_map(fn($r) => $r['call_code'], $rows); sort($out); return $out;
};
t_eq($visible($north), ['PB-NORTH-1'], '*** a North coordinator sees North work and NOT South work');
t_eq($visible($lost),  [],             '*** an unplaced coordinator sees no branch at all');
t_eq($visible($fin),   ['PB-NORTH-1', 'PB-SOUTH-1'], 'Finance sees both branches, as the matrix intends');
t_eq($visible($two),   ['PB-NORTH-1', 'PB-SOUTH-1'], 'a two-branch coordinator sees exactly their two');

// ---------------------------------------------------------------------------
//  5. AN ADMINISTRATOR IS TOLD WHO IS AFFECTED, BEFORE THEY ARE CALLED.
//     Closing the hole safely means nobody is silently left unable to work.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
$unplaced = array_column(scope_unplaced_users(), 'id');
t_ok(in_array($lost, $unplaced, true), '*** the unplaced coordinator is listed for an administrator to fix');
t_ok(!in_array($north, $unplaced, true), '…a properly placed one is not');
t_ok(!in_array($fin, $unplaced, true),   '…and neither is Finance, which needs no branch');

// ---------------------------------------------------------------------------
//  Clean up — this suite shares one database with every other.
// ---------------------------------------------------------------------------
foreach ($keep['c'] as $c) { try { $pdo->prepare("DELETE FROM calls WHERE call_code=?")->execute([$c]); } catch (Throwable $e) {} }
foreach ($keep['u'] as $i) { try { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['o'] as $i) { try { $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
$_SESSION = $origSess; current_user(true); ua(true);
