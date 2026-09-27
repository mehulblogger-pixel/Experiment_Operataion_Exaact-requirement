<?php
// ============================================================================
//  ONE CUSTOMER, TWO OFFICES, AND NEITHER READS THE OTHER'S BOOKS.
//
//  The case, in the owner's words: Jindal has an office in Ahmedabad and one in
//  Mumbai. They are already a client of the Ahmedabad office. For a particular
//  job Mumbai will pay, so Mumbai must be able to create its own Jindal record —
//  a subsidiary, or another GSTIN — linked to the same client group but owned by
//  Mumbai. "So no other office can see or edit the details of the client then
//  the office where they are linked with."
//
//  The group machinery already existed (parent_id, gstin, home_branch_id) and
//  the registers were already office-scoped. What did NOT hold was the group
//  WALK: partner_group_ids() returns the whole family, and quotes_for_group()
//  used it with no office filter — so an Ahmedabad user opening Jindal saw
//  Mumbai's quotations through the family tree. The register was locked and the
//  back door was open.
//
//  A group is not a permission. That is the whole of this file.
// ============================================================================

t_section('Client branches — one group, one office each');

t_ok(function_exists('partner_group_ids_visible'), 'ARMING · the scoped group walk exists');

$pdo = db();
$mk = function ($name, $branch, $parent = null, $gstin = '') use ($pdo) {
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, parent_id, home_branch_id, gstin, is_client, status, created_at)
                   VALUES (?,?,?,?,?,?,1,'ACTIVE',?)")
        ->execute(['ZZ' . mt_rand(10000, 99999), $name, $name, $parent, $branch, $gstin, date('c')]);
    return (int) $pdo->lastInsertId();
};
$office = function ($name) use ($pdo) {
    $ex = ops_one("SELECT id FROM offices WHERE name=?", [$name]);
    if ($ex) return (int) $ex['id'];
    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]);
    return (int) $pdo->lastInsertId();
};

$ahm = $office('ZZ Ahmedabad'); $mum = $office('ZZ Mumbai');
$jAhm = $mk('ZZ Jindal Ahmedabad', $ahm);
$jMum = $mk('ZZ Jindal Mumbai', $mum, $jAhm, '27ZZJIN0000A1Z5');
$jNone = $mk('ZZ Jindal Unassigned', null, $jAhm);

t_ok($ahm && $mum && $jAhm && $jMum, 'ARMING · two offices and two Jindal records were created');

// ---------------------------------------------------------------------------
//  1 · THE LEAK
// ---------------------------------------------------------------------------
//  A REAL signed-in coordinator, scoped to Ahmedabad only. Asserting this
//  against a master proves nothing — a master sees everything by design — so the
//  whole point of this block is to stand in the office that must NOT see Mumbai.
$zzUid = null;
$asAhmedabad = function () use (&$zzUid, $ahm) {
    if ($zzUid === null) {
        db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,home_office_id,scope_offices)
                       VALUES ('zz_ahm_coord',?,'ZZ','Ahmedabad','COORDINATOR',0,1,?,?)")
            ->execute([password_hash('x' . bin2hex(random_bytes(6)), PASSWORD_BCRYPT), $ahm, (string) $ahm]);
        $zzUid = (int) db()->lastInsertId();
    }
    $_SESSION['uid'] = $zzUid;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
};

t_nothrow('an Ahmedabad user cannot reach the Mumbai company through the group', function () use ($asAhmedabad, $jAhm, $jMum, $ahm) {
    //  ARMING FIRST. A master must see it, or "Ahmedabad cannot" is meaningless.
    t_as_admin();
    t_ok(in_array($jMum, partner_group_ids_visible($jAhm), true),
        'ARMING · a master DOES see the Mumbai company in Jindal\'s group');
    t_ok(in_array($jMum, partner_group_ids($jAhm), true),
        'ARMING · and the UNSCOPED walk returns it too — so there is a leak to close');

    $asAhmedabad();
    t_eq(scope_offices(), [$ahm], 'ARMING · the fixture user really is scoped to Ahmedabad alone');

    $vis = partner_group_ids_visible($jAhm);
    t_ok(in_array($jAhm, $vis, true), 'Ahmedabad still sees its own Jindal');
    t_ok(!in_array($jMum, $vis, true),
        'and NOT the Mumbai company — this is the assertion the whole feature exists for');
});

t_nothrow('nor its quotations, which is where the money is', function () use ($asAhmedabad, $jAhm, $jMum, $mum, $ahm) {
    //  The record being hidden is only half of it. quotes_for_group() is what
    //  actually printed Mumbai's commercials on an Ahmedabad screen.
    $pdo = db();
    $pdo->prepare("INSERT INTO quotations (quote_no, client_id, office_id, status, is_current, total_amount, subject, created_at)
                   VALUES ('ZZ-Q-MUM',?,?, 'ACCEPTED',1, 500000, 'ZZ Mumbai work', ?)")
        ->execute([$jMum, $mum, date('c')]);
    $qMum = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quotations (quote_no, client_id, office_id, status, is_current, total_amount, subject, created_at)
                   VALUES ('ZZ-Q-AHM',?,?, 'ACCEPTED',1, 100000, 'ZZ Ahmedabad work', ?)")
        ->execute([$jAhm, $ahm, date('c')]);
    $qAhm = (int) $pdo->lastInsertId();
    try {
        t_as_admin();
        $allQ = array_column(quotes_for_group($jAhm), 'id');
        t_ok(in_array($qMum, array_map('intval', $allQ), true),
            'ARMING · a master sees the Mumbai quotation in the group history');

        $asAhmedabad();
        $seen = array_map('intval', array_column(quotes_for_group($jAhm), 'id'));
        t_ok(in_array($qAhm, $seen, true), 'Ahmedabad sees its own quotation');
        t_ok(!in_array($qMum, $seen, true),
            'and NOT the Mumbai one — the group history no longer leaks another office\'s commercials');
    } finally {
        $pdo->exec("DELETE FROM quotations WHERE quote_no IN ('ZZ-Q-MUM','ZZ-Q-AHM')");
    }
});

t_nothrow('the scoped walk keeps an unassigned company visible', function () use ($asAhmedabad, $jAhm, $jNone) {
    //  A record with no office is not secret — it is unfiled. Hiding legacy
    //  clients from everybody would be a worse bug than the one being fixed.
    //
    //  Asserted AS THE SCOPED USER, not as a master: scope_offices() returns ALL
    //  for a master and the filter short-circuits before it runs, so a master
    //  passes this no matter what the filter does. An earlier draft made exactly
    //  that mistake and a mutation that hid every unassigned client went unnoticed.
    $asAhmedabad();
    $ids = partner_group_ids_visible($jAhm);
    t_ok(in_array($jNone, $ids, true), 'an unassigned group company stays in the walk');
});

t_nothrow('the record being looked at is never dropped from its own group', function () use ($asAhmedabad, $jMum) {
    //  Whoever opened the page already passed the check on it. Dropping it here
    //  would blank the screen rather than protect anything — so even an Ahmedabad
    //  user who somehow reaches the Mumbai record still sees it as its own group
    //  root rather than an empty page.
    $asAhmedabad();
    t_ok(in_array($jMum, partner_group_ids_visible($jMum), true), 'the subject of the page is always in its own group');
});

// ---------------------------------------------------------------------------
//  2 · THE CALLERS ACTUALLY USE IT
// ---------------------------------------------------------------------------
t_nothrow('every group walk that shows commercials is the scoped one', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/crm.php');
    //  quotes_for_group() is the one that leaked: it lists a group's quotations.
    $from = strpos($src, 'function quotes_for_group');
    t_ok($from !== false, 'ARMING · quotes_for_group was located');
    $body = substr($src, $from, 1400);
    t_ok(strpos($body, 'partner_group_ids_visible') !== false,
        'it walks the VISIBLE group, not the whole family');
    t_ok(strpos($body, "scope_office_clause('q.office_id')") !== false,
        'and scopes the quotations themselves as well — two filters, both needed');

    //  Registering a contract for a group company must not reach another office's.
    $from2 = strpos($src, 'function crm_add_group_contract');
    t_ok($from2 !== false, 'ARMING · crm_add_group_contract was located');
    $body2 = substr($src, $from2, 1800);
    t_ok(strpos($body2, 'partner_group_ids_visible') !== false,
        'a group contract can only be registered against a company this office may see');
});

// ---------------------------------------------------------------------------
//  3 · CREATING A BRANCH COMPANY
// ---------------------------------------------------------------------------
t_nothrow('the client screen can create a branch company for an office', function () {
    $h = (string) @file_get_contents(dirname(__DIR__) . '/lib/customer360.php');
    $from = strpos($h, "'add_branch_company'");
    t_ok($from !== false, 'the action exists');
    $body = substr($h, $from, 3600);
    //  Every one of these is a way the feature could create the very mess it is
    //  meant to prevent.
    t_ok(strpos($body, 'scope_allows') !== false,
        'you cannot hang a branch off a client you are not allowed to open');
    t_ok(substr_count($body, 'scope_allows') >= 2,
        'and you cannot assign it to an office you do not work in');
    t_ok(strpos($body, 'UPPER(COALESCE(gstin') !== false,
        'the same GSTIN twice is refused — that is the same legal entity twice');
    t_ok(strpos($body, "preg_match('~^[0-9A-Z]{15}\$~'") !== false,
        'a GSTIN is checked for shape before it is stored');
    t_ok(strpos($body, "\$parent['parent_id']") !== false,
        'a branch of a branch still lands in ONE family, not a chain');
    t_ok(strpos($body, 'act_log') !== false, 'and it is audited on both records');

    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/customer360.php');
    t_ok(strpos($v, 'add_branch_company') !== false, 'the screen offers it');
    t_ok(strpos($v, 'only the office you choose can see or edit it') !== false,
        'and says plainly what the choice means, because it is a confidentiality decision');
    t_ok(strpos($v, 'scope_offices') !== false,
        'the office list offers only offices this person works in');
});

// ---- clean up ---------------------------------------------------------------
t_nothrow('fixtures removed', function () use ($pdo) {
    $pdo->exec("DELETE FROM business_partners WHERE legal_name LIKE 'ZZ Jindal %'");
    $pdo->exec("DELETE FROM users WHERE username='zz_ahm_coord'");
    $pdo->exec("DELETE FROM offices WHERE name LIKE 'ZZ %'");
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name LIKE 'ZZ Jindal %'"), 0, 'no test clients remain');
    t_as_nobody();
    t_ok(true, 'session restored for the rest of the suite');
});
