<?php
// ============================================================================
//  DO NOT ASK SOMEBODY TO CONFIRM WHAT YOU ALREADY KNOW.
//
//  The matcher has always scored its evidence — 96 for an exact mobile number,
//  94 for an e-mail, 72 for a full name — and the screen threw the score away.
//  Every match was shown the same way, with the same manual "Confirm same
//  person" button, whether the two records shared a phone number or merely a
//  surname. The owner's words were "this is really very weird", and they were
//  right: the system had already worked out the answer and asked anyway.
//
//  Two failures, not one:
//    * the certain case became a chore, and
//    * the UNCERTAIN case looked exactly like it — which is how a confirmation
//      button turns into a reflex nobody reads.
//
//  The tests below spend most of their effort on when the system must REFUSE to
//  decide, because that is where an automatic link does damage.
// ============================================================================

t_section('Marketplace matching — the system decides what it knows, and asks what it does not');

t_ok(function_exists('candpool_autolink'), 'ARMING · the auto-link exists');
t_ok(function_exists('candpool_confidence'), 'ARMING · confidence is readable');

// ---------------------------------------------------------------------------
//  1 · ONE IDEA OF CONFIDENCE ACROSS THE PRODUCT
// ---------------------------------------------------------------------------
t_nothrow('the evidence is scored, strongest first', function () {
    t_eq(candpool_confidence('mobile'), 96, 'an exact mobile number');
    t_eq(candpool_confidence('email'),  94, 'an exact e-mail address');
    t_eq(candpool_confidence('name'),   72, 'a full name');
    t_eq(candpool_confidence('nonsense'), 0, 'anything unrecognised scores nothing');
    t_ok(candpool_confidence('mobile') > candpool_confidence('email'),
        'a phone number is better evidence than an e-mail — e-mail addresses get reused, numbers less so');
    t_ok(candpool_confidence('email') > candpool_confidence('name'), 'and both beat a name');
});

t_nothrow('the threshold admits exact identifiers and nothing else', function () {
    $min = CANDPOOL_AUTOLINK_MIN;
    t_ok(candpool_confidence('mobile') >= $min, 'a mobile match links automatically');
    t_ok(candpool_confidence('email')  >= $min, 'an e-mail match links automatically');
    t_ok(candpool_confidence('name')    < $min, 'a NAME match never does — this is the whole safety margin');
});

t_nothrow('the score matches the one the rest of the product already uses', function () {
    //  cand_dupes() scores candidate-to-candidate matching with the same numbers.
    //  Two different ideas of "how sure are we" is how a product ends up arguing
    //  with itself on two screens.
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/recruit.php');
    foreach ([96 => 'same mobile', 94 => 'same email'] as $score => $what)
        t_ok(strpos($src, (string) $score) !== false,
            "the existing duplicate matcher also scores $score ($what)");
});

// ---------------------------------------------------------------------------
//  2 · WHEN IT MUST REFUSE TO DECIDE. The heart of it.
// ---------------------------------------------------------------------------
t_nothrow('a name-only match is never linked automatically', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/candpool.php');
    $from = strpos($src, 'function candpool_autolink');
    t_ok($from !== false, 'ARMING · the function was located');
    $body = substr($src, $from, 2600);
    t_ok(strpos($body, 'CANDPOOL_AUTOLINK_MIN') !== false, 'it compares against the threshold');
    t_ok(strpos($body, "'too-weak'") !== false, 'and refuses weak evidence by name');
});

t_nothrow('AMBIGUITY is never resolved silently — two strong matches means a person decides', function () {
    //  A father and son on one mobile; a shared site phone. Linking the first of
    //  them would be confidently wrong, which is worse than asking, because a
    //  wrong link is invisible while a question is not.
    $src  = (string) @file_get_contents(dirname(__DIR__) . '/lib/candpool.php');
    $from = strpos($src, 'function candpool_autolink');
    $body = substr($src, $from, 2600);
    t_ok(strpos($body, "'ambiguous'") !== false, 'the ambiguous case has its own refusal');
    t_ok(preg_match('~count\(\$atBest\)\s*!==\s*1~', $body) === 1,
        'and it links ONLY when exactly one professional matches at the best strength');
    //  The check must come BEFORE the write, or it guards nothing.
    //
    //  strrpos, not strpos: the FIRST mention of the ledger call in this function
    //  is the function_exists() guard at the top, which sits above everything and
    //  would make this assertion pass no matter where the real call was. The last
    //  mention is the write.
    $amb   = strpos($body, "'ambiguous'");
    $write = strrpos($body, 'connect_identity_candidate_link_create');
    t_ok($amb !== false && $write !== false && $amb < $write,
        'and it refuses before linking, not after');
});

t_nothrow('an already-linked candidate is never linked a second time', function () {
    $src  = (string) @file_get_contents(dirname(__DIR__) . '/lib/candpool.php');
    $body = substr($src, strpos($src, 'function candpool_autolink'), 2600);
    t_ok(strpos($body, 'connect_identity_of_candidate') !== false, 'it checks for an existing link');
    t_ok(strpos($body, "'already-linked'") !== false, 'and says so rather than trying');
});

t_nothrow('a candidate with nothing to match on is left alone', function () {
    foreach ([0, -1, 999999999] as $bad) {
        [$ok, $why] = candpool_autolink($bad);
        t_ok(!$ok, 'not linked: candidate id ' . $bad);
        t_ok(is_string($why) && $why !== '', 'and it reports why: ' . $why);
    }
});

// ---------------------------------------------------------------------------
//  3 · THE LINK IS HONEST ABOUT ITSELF
// ---------------------------------------------------------------------------
t_nothrow('an automatic link records HOW it was made, so it can be judged later', function () {
    $src  = (string) @file_get_contents(dirname(__DIR__) . '/lib/candpool.php');
    $body = substr($src, strpos($src, 'function candpool_autolink'), 2600);
    t_ok(strpos($body, "'auto-' . ") !== false,
        "the ledger records 'auto-mobile' / 'auto-email', never a bare 'manual'");
    t_ok(stripos($body, 'confidence') !== false, 'and the note carries the confidence');
});

t_nothrow('the screen says it linked automatically, and on what evidence', function () {
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/candidate_detail.php');
    t_ok(strpos($v, 'linked automatically') !== false, 'it does not pretend a person confirmed it');
    t_ok(strpos($v, 'candpool_reason_words') !== false, 'and names the evidence in plain words');
    t_ok(strpos($v, 'Unlink') !== false, 'and Unlink is still offered — an automatic link is reversible');
    //  A weak match must explain itself too, or the remaining tick is as
    //  arbitrary as the old one.
    t_ok(strpos($v, 'two people can share a name') !== false
      || strpos($v, 'Name only') !== false,
        'and a name-only match says why it is still a question');
    t_ok(strpos($v, 'More than one person matches this strongly') !== false,
        'as does a strong match held back for ambiguity');
});

t_nothrow('nothing is merged — the link stays a relationship', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/candpool.php');
    $body = substr($src, strpos($src, 'function candpool_autolink'), 2600);
    //  It goes through the SAME ledger call the manual button uses, so it cannot
    //  be a weaker or different kind of write.
    t_ok(strpos($body, 'connect_identity_candidate_link_create') !== false,
        'it uses the same ledger door as the manual confirmation');
    t_ok(strpos($body, 'UPDATE candidates') === false && strpos($body, 'DELETE') === false,
        'and writes nothing to the candidate itself');
});

t_nothrow('it runs when a candidate is created, where the evidence is fresh', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($src, 'candpool_autolink') !== false, 'the create path calls it');
    //  …and tells the person, rather than doing it invisibly.
    t_ok(strpos($src, 'Also recognised on the marketplace') !== false,
        'and says so in the confirmation message');
});

// ---------------------------------------------------------------------------
//  4 · AGAINST REAL ROWS. Everything above reads the source; this exercises it.
//      A static check can prove the ambiguity branch EXISTS. Only this proves it
//      is reached.
// ---------------------------------------------------------------------------
t_nothrow('end to end: it links the certain case and refuses the ambiguous one', function () {
    if (!function_exists('connect_identity_migrate') || !t_table_exists('cx_professionals')) {
        t_ok(true, 'marketplace tables absent on this build — nothing to exercise'); return;
    }
    //  The auto-link goes through the SAME ledger door as the manual button, so
    //  it is subject to the same entitlement and permission guard. That is the
    //  point — an automatic path must never be a weaker path — and it means this
    //  test needs a session that may actually link, or it proves nothing but the
    //  guard. (A workspace without the marketplace simply never auto-links, which
    //  is correct behaviour, not a failure.)
    t_as_admin();
    if (function_exists('connect_identity_guard') && !connect_identity_guard()) {
        t_ok(true, 'the marketplace is not entitled in this run — the link is correctly refused');
        return;
    }
    connect_identity_migrate();
    $pdo = db();
    $mk = function ($name, $mob, $em) use ($pdo) {
        $pdo->prepare("INSERT INTO cx_professionals (name, mobile, email, verification_tier, availability, created_at)
                       VALUES (?,?,?,'registered','AVAILABLE',?)")->execute([$name, $mob, $em, date('c')]);
        return (int) $pdo->lastInsertId();
    };
    $mkc = function ($fn, $ln, $mob, $em) use ($pdo) {
        $pdo->prepare("INSERT INTO candidates (cand_code, first_name, last_name, mobile, email, stage, created_at)
                       VALUES (?,?,?,?,?,'RECEIVED',?)")
            ->execute(['ZZCV' . mt_rand(10000, 99999), $fn, $ln, $mob, $em, date('c')]);
        return (int) $pdo->lastInsertId();
    };

    //  (a) ONE professional on an exact mobile → linked, and marked as automatic.
    $p1 = $mk('ZZ Auto One', '9812345671', 'zzauto1@example.com');
    $c1 = $mkc('ZZ Auto', 'One', '9812345671', '');
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
    [$ok1, $why1, $pid1] = candpool_autolink($c1);
    t_ok($ok1, 'an exact mobile match links without asking' . ($ok1 ? '' : ' (got: ' . $why1 . ')'));
    t_eq($why1, 'mobile', 'and the evidence is recorded as the mobile');
    t_eq($pid1, $p1, 'to the right professional');
    $row = connect_identity_of_candidate($c1);
    t_ok($row && strpos((string) $row['method'], 'auto-') === 0,
        'the ledger says it was automatic, not manual (method=' . (string) ($row['method'] ?? '') . ')');

    //  (b) Calling it again changes nothing.
    [$ok1b, $why1b] = candpool_autolink($c1);
    t_ok(!$ok1b, 'a second run does not link again');
    t_eq($why1b, 'already-linked', 'and says why');

    //  (c) TWO professionals on the SAME mobile → refused. The father-and-son case.
    $mk('ZZ Auto Two A', '9812345672', 'zzauto2a@example.com');
    $mk('ZZ Auto Two B', '9812345672', 'zzauto2b@example.com');
    $c2 = $mkc('ZZ Auto', 'Two', '9812345672', '');
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
    [$ok2, $why2] = candpool_autolink($c2);
    t_ok(!$ok2, 'two people on one mobile is NOT resolved automatically');
    t_eq($why2, 'ambiguous', 'and it is refused as ambiguous, for a person to judge');
    t_ok(!connect_identity_of_candidate($c2), 'and nothing was written');

    //  (d) A NAME-only match → refused, however unusual the name.
    $mk('ZZ Auto Three', '9812345673', 'zzauto3@example.com');
    $c3 = $mkc('ZZ Auto', 'Three', '', '');
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
    [$ok3, $why3] = candpool_autolink($c3);
    t_ok(!$ok3, 'a shared name alone is never enough');
    t_ok(in_array($why3, ['too-weak', 'no-match'], true), 'refused as weak evidence (' . $why3 . ')');
    t_ok(!connect_identity_of_candidate($c3), 'and nothing was written');

    //  (e) No evidence at all → nothing happens, quietly.
    $c4 = $mkc('ZZ Auto', 'Four', '', '');
    [$ok4] = candpool_autolink($c4);
    t_ok(!$ok4, 'a candidate matching nobody is left alone');

    //  ---- clean up every fixture --------------------------------------------
    //
    //  Including the AUDIT rows the link wrote. Deleting the link rows alone left
    //  activities pointing at ids that no longer existed, and a later test
    //  (test_p6_batch1, Y-D) correctly asserts there are no such dangling
    //  references — it caught this, which is the whole point of having it.
    $ids = [$c1, $c2, $c3, $c4];
    $linkIds = array_column(ops_all("SELECT id FROM cx_identity_link WHERE candidate_id IN (" . implode(',', $ids) . ")") ?: [], 'id');
    $pdo->exec("DELETE FROM cx_identity_link WHERE candidate_id IN (" . implode(',', $ids) . ")");
    if ($linkIds)
        $pdo->exec("DELETE FROM activities WHERE entity_kind='IDENTITY_LINK' AND entity_id IN (" . implode(',', array_map('intval', $linkIds)) . ")");
    $pdo->exec("DELETE FROM candidates WHERE id IN (" . implode(',', $ids) . ")");
    $pdo->exec("DELETE FROM cx_professionals WHERE name LIKE 'ZZ Auto %'");
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
    t_eq((int) ops_val("SELECT COUNT(*) FROM cx_professionals WHERE name LIKE 'ZZ Auto %'"), 0, 'fixtures removed');
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities a WHERE a.entity_kind='IDENTITY_LINK'
                          AND NOT EXISTS (SELECT 1 FROM cx_identity_link l WHERE l.id = a.entity_id)"), 0,
        'and no audit row is left pointing at a link that no longer exists');
});

//  THE SESSION GOES BACK AS IT WAS.
//
//  t_as_admin() above is global: the next file in the suite inherits it. That
//  broke test_client_hold, which relies on there being no signed-in manager so
//  that a client block bites. A test that changes the world and does not change
//  it back is a test that fails somebody else's.
t_as_nobody();
t_ok(true, 'session restored for the rest of the suite');
