<?php
// ============================================================================
//  OFFICE SCOPE — the counts must answer the same question as their lists.
//
//  Three dashboard/Command-Centre numbers were written as bare COUNT(*) and so
//  reported the WHOLE company while the registers they link to scoped by branch.
//  A branch user saw a number they could not reconcile with the list behind it.
//  A fourth went the other way: the open-work-orders count scoped on the
//  executing office alone, while its register also shows the office that
//  CONTRACTED the order — so the count UNDER-reported.
//
//  These tests seed two offices and sign in as a user scoped to one of them,
//  then run the SAME SQL the screens run. They fail on the unscoped version:
//  every assertion below is a number that was wrong before this change.
//
//  They are behavioural, not textual — a test that only greps the view for the
//  word "scope_office_clause" passes against a helper that returns '1=1'.
// ============================================================================

t_section('Office scope — counts agree with their registers');

$osA = 0; $osB = 0; $osUid = 0;

t_nothrow('fixtures: two offices, a coordinator scoped to the first', function () use (&$osA, &$osB, &$osUid) {
    db()->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES ('OSA','Scope Office A','A',0)")->execute();
    $osA = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES ('OSB','Scope Office B','B',0)")->execute();
    $osB = (int)db()->lastInsertId();

    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,home_office_id,scope_offices)
                   VALUES ('os_coord',?,'Scope','Coord','COORDINATOR',0,1,?,?)")
        ->execute([password_hash('x' . bin2hex(random_bytes(6)), PASSWORD_BCRYPT), $osA, (string)$osA]);
    $osUid = (int)db()->lastInsertId();
});

// ---- the actor: a real signed-in coordinator, scoped to office A only -------
$osAs = function () use (&$osUid) {
    $_SESSION['uid'] = $osUid;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
};

t_nothrow('the fixture user really is scoped to one office', function () use ($osAs, &$osA) {
    $osAs();
    $off = scope_offices();
    t_ok(is_array($off) && count($off) === 1 && (int)$off[0] === $osA,
        'scope_offices() returns exactly office A  (got ' . json_encode($off) . ')');
});

// ============================================================================
//  1 — THE TWO-OFFICE RULE FOR WORK ORDERS
//  A work order belongs to the office that CONTRACTED it as well as the one
//  EXECUTING it. Both must see it; an office with no part in it must not.
// ============================================================================
t_nothrow('work orders: contracted-here counts, somebody-else\'s does not', function () use ($osAs, &$osA, &$osB) {
    $mk = function ($exec, $contract) {
        db()->prepare("INSERT INTO calls (call_code,status,executing_office_id,ibo_office_id,sbu,created_at)
                       VALUES (?,'OPEN',?,?,'',?)")
            ->execute(['OSC' . bin2hex(random_bytes(4)), $exec, $contract, date('c')]);
    };
    $mk($osB, $osA);   // executed by B, contracted to A  → A must SEE it
    $mk($osB, $osB);   // executed and contracted by B    → A must NOT see it
    $mk(null, $osA);   // no executing office, contracted to A → A must SEE it

    $osAs();
    [$w, $a] = call_office_clause('c.executing_office_id', 'c.ibo_office_id');
    $n = (int) ops_val("SELECT COUNT(*) FROM calls c WHERE c.call_code LIKE 'OSC%' AND $w", $a);
    t_eq($n, 2, 'A sees the two work orders it contracted, and not B\'s own');

    // The specific row that the old executing-office-only rule hid.
    $seen = (int) ops_val("SELECT COUNT(*) FROM calls c
                           WHERE c.call_code LIKE 'OSC%' AND c.executing_office_id=? AND c.ibo_office_id=? AND $w",
                          array_merge([$osB, $osA], $a));
    t_eq($seen, 1, 'a work order B executes but A contracted IS visible to A');

    $other = (int) ops_val("SELECT COUNT(*) FROM calls c
                            WHERE c.call_code LIKE 'OSC%' AND c.executing_office_id=? AND c.ibo_office_id=? AND $w",
                           array_merge([$osB, $osB], $a));
    t_eq($other, 0, 'a work order wholly B\'s is NOT visible to A');
});

// ============================================================================
//  2 — THE THREE UNSCOPED COUNTS
//  Each asserts the branch number, which differs from the company number.
// ============================================================================
t_nothrow('open leads: the count is the branch\'s, not the company\'s', function () use ($osAs, &$osA, &$osB) {
    db()->prepare("INSERT INTO leads (ref,company_name,status,office_id,created_at) VALUES ('OSL-A','os lead A','OPEN',?,?)")->execute([$osA, date('c')]);
    db()->prepare("INSERT INTO leads (ref,company_name,status,office_id,created_at) VALUES ('OSL-B','os lead B','OPEN',?,?)")->execute([$osB, date('c')]);

    $osAs();
    [$w, $a] = scope_office_clause('office_id');
    $mine    = (int) ops_val("SELECT COUNT(*) FROM leads WHERE status='OPEN' AND ref LIKE 'OSL-%' AND $w", $a);
    $company = (int) ops_val("SELECT COUNT(*) FROM leads WHERE status='OPEN' AND ref LIKE 'OSL-%'");
    t_eq($mine, 1, 'A counts only its own open lead');
    t_ok($company > $mine, 'the company-wide count is larger — so the old count was wrong (company ' . $company . ' vs branch ' . $mine . ')');
});

t_nothrow('open deals: the count is the branch\'s, not the company\'s', function () use ($osAs, &$osA, &$osB) {
    db()->prepare("INSERT INTO opportunities (ref,name,status,office_id,created_at) VALUES ('OSO-A','os opp A','OPEN',?,?)")->execute([$osA, date('c')]);
    db()->prepare("INSERT INTO opportunities (ref,name,status,office_id,created_at) VALUES ('OSO-B','os opp B','OPEN',?,?)")->execute([$osB, date('c')]);

    $osAs();
    [$w, $a] = scope_office_clause('office_id');
    $mine    = (int) ops_val("SELECT COUNT(*) FROM opportunities WHERE status='OPEN' AND ref LIKE 'OSO-%' AND $w", $a);
    $company = (int) ops_val("SELECT COUNT(*) FROM opportunities WHERE status='OPEN' AND ref LIKE 'OSO-%'");
    t_eq($mine, 1, 'A counts only its own open deal');
    t_ok($company > $mine, 'the company-wide count is larger (company ' . $company . ' vs branch ' . $mine . ')');
});

t_nothrow('lapsed quotations: quotes_expired_count() is branch-scoped', function () use ($osAs, &$osA, &$osB) {
    $mkq = function ($office) {
        db()->prepare("INSERT INTO quotations (quote_no,status,is_current,office_id,total_amount,created_at)
                       VALUES (?,'EXPIRED',1,?,0,?)")
            ->execute(['OSQ' . bin2hex(random_bytes(4)), $office, date('c')]);
    };
    $mkq($osA); $mkq($osB); $mkq($osB);

    $osAs();
    $n = quotes_expired_count();
    $all = (int) ops_val("SELECT COUNT(*) FROM quotations WHERE status='EXPIRED' AND COALESCE(is_current,1)=1");
    t_ok($n < $all, 'the branch sees fewer lapsed quotations than the company has (branch ' . $n . ' vs company ' . $all . ')');
    t_ok($n >= 1, 'and it still sees its own');
});

// ============================================================================
//  3 — THE PARTY DIRECTORY
//  A branch sees its own parties. A party with NO branch stays visible to
//  everyone, so the register does not go dark on a company that has not filled
//  the field in yet.
// ============================================================================
t_nothrow('clients: a branch sees its own and the unassigned, never another branch\'s', function () use ($osAs, &$osA, &$osB) {
    $mkp = function ($name, $branch) {
        db()->prepare("INSERT INTO business_partners (legal_name,is_client,is_vendor,status,home_branch_id)
                       VALUES (?,1,0,'ACTIVE',?)")->execute([$name, $branch]);
    };
    $mkp('OSP client A', $osA);
    $mkp('OSP client B', $osB);
    $mkp('OSP client unassigned', null);

    $osAs();
    [$w, $a] = scope_office_clause('home_branch_id');
    $rows = ops_all("SELECT legal_name FROM business_partners WHERE is_client=1 AND legal_name LIKE 'OSP %' AND $w ORDER BY legal_name", $a);
    $names = array_column($rows, 'legal_name');

    t_ok(in_array('OSP client A', $names, true),          'A sees its own client');
    t_ok(in_array('OSP client unassigned', $names, true), 'A sees the client nobody has assigned yet');
    t_ok(!in_array('OSP client B', $names, true),         'A does NOT see B\'s client');
    t_eq(count($names), 2, 'exactly two — its own and the unassigned one');
});

// ============================================================================
//  4 — A MASTER STILL SEES EVERYTHING
//  The change must narrow branch users only. If it narrowed a master too, the
//  company would lose its own overview.
// ============================================================================
t_nothrow('a master / ALL-scope user still sees every branch', function () {
    t_as_admin();
    [$w, $a] = scope_office_clause('home_branch_id');
    t_eq($w, '1=1', 'an ALL-scope user gets no branch filter at all');
    [$cw, $ca] = call_office_clause('c.executing_office_id', 'c.ibo_office_id');
    t_eq($cw, '1=1', 'and no work-order filter either');
    $n = (int) ops_val("SELECT COUNT(*) FROM business_partners WHERE is_client=1 AND legal_name LIKE 'OSP %'");
    t_eq($n, 3, 'a master sees both branches\' clients and the unassigned one');
    t_as_nobody();
});

// ============================================================================
//  5 — THE SCREENS ACTUALLY CALL THE HELPERS
//  The assertions above prove the RULE. These prove the rule is WIRED IN — a
//  correct helper nothing calls would leave every screen exactly as broken.
// ============================================================================
t_nothrow('the screens are wired to the helpers, not to bare COUNT(*)', function () {
    $root = dirname(__DIR__);
    $dash = (string) @file_get_contents($root . '/views/dashboard.php');
    $idx  = (string) @file_get_contents($root . '/index.php');
    $crm  = (string) @file_get_contents($root . '/lib/crm.php');

    t_ok(strpos($dash, "FROM leads WHERE status='OPEN'\"") === false,
        'the open-leads tile no longer runs an unscoped COUNT(*)');
    t_ok(strpos($dash, "FROM opportunities WHERE status='OPEN'\"") === false,
        'the open-deals tile no longer runs an unscoped COUNT(*)');
    t_ok(strpos($dash, 'call_office_clause(') !== false,
        'the open-work-orders count uses the shared two-office rule');
    t_ok(strpos($idx, "scope_office_clause('home_branch_id')") !== false,
        'the party directory scopes on the branch column');
    t_ok(substr_count($idx, "scope_office_clause('home_branch_id')") >= 2,
        'and so do the dashboard party counts, with the same rule');
    t_ok(preg_match('/quotes_expired_count\(\).*?scope_office_clause/s', $crm) === 1,
        'quotes_expired_count() scopes before it counts');
});

// ---- tidy up: leave no fixture behind for the next test file ----------------
t_nothrow('fixtures removed', function () use (&$osA, &$osB, &$osUid) {
    db()->exec("DELETE FROM calls WHERE call_code LIKE 'OSC%'");
    db()->exec("DELETE FROM leads WHERE ref LIKE 'OSL-%'");
    db()->exec("DELETE FROM opportunities WHERE ref LIKE 'OSO-%'");
    db()->exec("DELETE FROM quotations WHERE quote_no LIKE 'OSQ%'");
    db()->exec("DELETE FROM business_partners WHERE legal_name LIKE 'OSP %'");
    db()->prepare("DELETE FROM users WHERE id=?")->execute([$osUid]);
    db()->prepare("DELETE FROM offices WHERE id IN (?,?)")->execute([$osA, $osB]);
    t_as_nobody();
    $left = (int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name LIKE 'OSP %'");
    t_eq($left, 0, 'no temporary records remain');
});
